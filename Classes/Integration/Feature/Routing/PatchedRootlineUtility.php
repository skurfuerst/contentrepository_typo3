<?php

namespace Sandstorm\ContentrepositoryTypo3\Integration\Feature\Routing;

use Neos\ContentRepository\Core\ContentRepository;
use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Core\Projection\ContentGraph\ContentSubgraphInterface;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Sandstorm\ContentrepositoryTypo3\Integration\Feature\NodeIdHandling\NodeIdGenerator;
use Sandstorm\ContentrepositoryTypo3\Integration\Feature\PageTreeDisplay\PatchedPageTreeRepository;
use Sandstorm\ContentrepositoryTypo3\Registry\ContentRepositoryRegistry;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\RootlineUtility;

class PatchedRootlineUtility extends RootlineUtility
{

    private ContentRepository $contentRepository;
    private ContentSubgraphInterface $subgraph;

    public function __construct(int $uid, string $mountPointParameter = '', ?Context $context = null) {
        parent::__construct($uid, $mountPointParameter, $context);

        $contentRepositoryRegistry = GeneralUtility::makeInstance(ContentRepositoryRegistry::class);
        $this->contentRepository = $contentRepositoryRegistry->get(
            ContentRepositoryId::fromString('default')
        );
        $this->subgraph = $this->contentRepository->getContentSubgraph(
            WorkspaceName::forLive(),
            DimensionSpacePoint::createWithoutDimensions()
        );
    }
    public function get(): array
    {
        $nodeAggregateId = NodeIdGenerator::fromNumericTypo3Id($this->pageUid);

        $rootline = [];
        $node = $this->subgraph->findNodeById($nodeAggregateId);
        do {
            $parentNode = $this->subgraph->findParentNode($node->aggregateId);
            if ($parentNode === null) {
                break;
            }
            $rootline[] = PatchedPageTreeRepository::buildTypo3PageArrayForNode($node, $parentNode->aggregateId, $this->contentRepository->getNodeTypeManager());;
            $node = $parentNode;
        } while (true);

        return array_reverse($rootline);
    }
}