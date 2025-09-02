<?php

/*
 * This file is part of the Neos.Neos package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

declare(strict_types=1);

namespace Sandstorm\ContentrepositoryTypo3\Integration\Feature\RootNode;

use Neos\ContentRepository\Core\ContentRepository;
use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Core\DimensionSpace\InterDimensionalVariationGraph;
use Neos\ContentRepository\Core\DimensionSpace\OriginDimensionSpacePoint;
use Neos\ContentRepository\Core\Feature\NodeCreation\Command\CreateNodeAggregateWithNode;
use Neos\ContentRepository\Core\Feature\NodeModification\Dto\PropertyValuesToWrite;
use Neos\ContentRepository\Core\Feature\NodeRemoval\Command\RemoveNodeAggregate;
use Neos\ContentRepository\Core\Feature\NodeVariation\Command\CreateNodeVariant;
use Neos\ContentRepository\Core\Feature\RootNodeCreation\Command\CreateRootNodeAggregateWithNode;
use Neos\ContentRepository\Core\Feature\WorkspaceCreation\Command\CreateRootWorkspace;
use Neos\ContentRepository\Core\NodeType\NodeTypeManager;
use Neos\ContentRepository\Core\NodeType\NodeTypeName;
use Neos\ContentRepository\Core\Projection\ContentGraph\NodeAggregate;
use Neos\ContentRepository\Core\SharedModel\Exception\NodeTypeNotFound;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateId;
use Neos\ContentRepository\Core\SharedModel\Node\NodeName;
use Neos\ContentRepository\Core\SharedModel\Node\NodeVariantSelectionStrategy;
use Neos\ContentRepository\Core\SharedModel\Workspace\ContentStreamId;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Sandstorm\ContentrepositoryTypo3\Integration\Feature\NodeIdHandling\NodeIdGenerator;

/**
 * @internal in Neos, corresponds to Neos\Neos\Domain\Service\SiteServiceInterals
 */
readonly class RootNodeCreator
{
    private NodeTypeManager $nodeTypeManager;

    private InterDimensionalVariationGraph $interDimensionalVariationGraph;

    public function __construct(
        private ContentRepository $contentRepository,
        private readonly NodeIdGenerator $nodeIdGenerator,
    )
    {
        $this->nodeTypeManager = $this->contentRepository->getNodeTypeManager();
        $this->interDimensionalVariationGraph = $this->contentRepository->getVariationGraph();
    }

    public function removeSiteNode(NodeName $siteNodeName): void
    {
        $dimensionSpacePoints = $this->interDimensionalVariationGraph->getDimensionSpacePoints()->points;
        $arbitraryDimensionSpacePoint = reset($dimensionSpacePoints) ?: null;
        if (!$arbitraryDimensionSpacePoint instanceof DimensionSpacePoint) {
            throw new \InvalidArgumentException(
                'Cannot prune site "' . $siteNodeName->value
                . '" due to the dimension space being empty',
                1651921482
            );
        }

        // todo only remove site node in base workspace and rebase dependant workspaces to avoid also the security hacks here.
        foreach ($this->contentRepository->findWorkspaces() as $workspace) {
            $contentGraph = $this->contentRepository->getContentGraph($workspace->workspaceName);
            $sitesNodeAggregate = $contentGraph?->findRootNodeAggregateByType(
                NodeTypeNameFactory::forSites()
            );
            if (!$sitesNodeAggregate) {
                // nothing to prune, we could probably also return here directly?
                continue;
            }
            $siteNodeAggregate = $contentGraph->findChildNodeAggregateByName(
                $sitesNodeAggregate->nodeAggregateId,
                $siteNodeName
            );
            if ($siteNodeAggregate instanceof NodeAggregate) {
                $this->contentRepository->handle(RemoveNodeAggregate::create(
                    $workspace->workspaceName,
                    $siteNodeAggregate->nodeAggregateId,
                    $arbitraryDimensionSpacePoint,
                    NodeVariantSelectionStrategy::STRATEGY_ALL_VARIANTS,
                ));
            }
        }
    }

    public function createSiteNodeIfNotExists(NodeName $siteNodeName, NodeTypeName $nodeTypeName): void
    {
        $liveWorkspace = $this->contentRepository->findWorkspaceByName(WorkspaceName::forLive());
        if ($liveWorkspace === null) {
            // CreateRootWorkspace was denied: Creation of root workspaces is currently only allowed with disabled authorization checks
            $this->contentRepository->handle(
                CreateRootWorkspace::create(
                    WorkspaceName::forLive(),
                    ContentStreamId::create()
                )
            );
        }

        $sitesNodeIdentifier = $this->getOrCreateRootNodeAggregate();
        $siteNodeType = $this->nodeTypeManager->getNodeType($nodeTypeName);
        if (!$siteNodeType) {
            throw new NodeTypeNotFound(
                'Cannot create a site using a non-existing node type.',
                1412372375
            );
        }

        /*if (!$siteNodeType->isOfType(NodeTypeNameFactory::NAME_SITE)) {
            throw new \RuntimeException('TODO: Site Node not of correct type');
        }*/

        $siteNodeAggregate = $this->contentRepository->getContentGraph(WorkspaceName::forLive())
            ->findChildNodeAggregateByName(
                $sitesNodeIdentifier,
                $siteNodeName,
            );
        if ($siteNodeAggregate instanceof NodeAggregate) {
            // Site node already exists
            return;
        }

        $rootDimensionSpacePoints = $this->interDimensionalVariationGraph->getRootGeneralizations();
        $arbitraryRootDimensionSpacePoint = array_shift($rootDimensionSpacePoints);

        $siteNodeAggregateId = $this->nodeIdGenerator->newNodeId($this->contentRepository->id);
        $this->contentRepository->handle(CreateNodeAggregateWithNode::create(
            WorkspaceName::forLive(),
            $siteNodeAggregateId,
            $nodeTypeName,
            OriginDimensionSpacePoint::fromDimensionSpacePoint($arbitraryRootDimensionSpacePoint),
            $sitesNodeIdentifier,
            null,
            PropertyValuesToWrite::fromArray([
                'title' => $siteNodeName->value
            ])
        )->withNodeName($siteNodeName));

        // Handle remaining root dimension space points by creating peer variants
        foreach ($rootDimensionSpacePoints as $rootDimensionSpacePoint) {
            $this->contentRepository->handle(CreateNodeVariant::create(
                WorkspaceName::forLive(),
                $siteNodeAggregateId,
                OriginDimensionSpacePoint::fromDimensionSpacePoint($arbitraryRootDimensionSpacePoint),
                OriginDimensionSpacePoint::fromDimensionSpacePoint($rootDimensionSpacePoint),
            ));
        }
    }

    /**
     * Retrieve the root Node Aggregate ID for the specified $workspace
     * If no root node of the specified $rootNodeTypeName exist, it will be created
     */
    private function getOrCreateRootNodeAggregate(): NodeAggregateId
    {
        $rootNodeTypeName = NodeTypeNameFactory::forSites();
        $rootNodeAggregate = $this->contentRepository->getContentGraph(WorkspaceName::forLive())->findRootNodeAggregateByType(
            $rootNodeTypeName
        );
        if ($rootNodeAggregate !== null) {
            return $rootNodeAggregate->nodeAggregateId;
        }
        $rootNodeAggregateId = NodeAggregateId::fromString('000000000');
        $this->contentRepository->handle(CreateRootNodeAggregateWithNode::create(
            WorkspaceName::forLive(),
            $rootNodeAggregateId,
            $rootNodeTypeName,
        ));
        return $rootNodeAggregateId;
    }
}
