<?php

namespace Sandstorm\ContentrepositoryTypo3\Integration\Feature\Routing;

use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Sandstorm\ContentrepositoryTypo3\Integration\Feature\NodeIdHandling\NodeIdGenerator;
use Sandstorm\ContentrepositoryTypo3\Integration\Feature\PageTreeDisplay\PatchedPageTreeRepository;
use Sandstorm\ContentrepositoryTypo3\Registry\ContentRepositoryRegistry;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class PatchedPageRepository extends PageRepository
{
    private \Neos\ContentRepository\Core\ContentRepository $contentRepository;
    private \Neos\ContentRepository\Core\Projection\ContentGraph\ContentSubgraphInterface $subgraph;

    public function __construct(?Context $context = null)
    {
        parent::__construct($context);

        $contentRepositoryRegistry = GeneralUtility::makeInstance(ContentRepositoryRegistry::class);
        $this->contentRepository = $contentRepositoryRegistry->get(
            ContentRepositoryId::fromString('default')
        );
        $this->subgraph = $this->contentRepository->getContentSubgraph(
            WorkspaceName::forLive(),
            DimensionSpacePoint::createWithoutDimensions()
        );
    }

    public function getPage(int $uid, bool $disableGroupAccessCheck = false): array
    {
        $nodeAggregateId = NodeIdGenerator::fromNumericTypo3Id($uid);
        $node = $this->subgraph->findNodeById($nodeAggregateId);
        $parentNode = $this->subgraph->findParentNode($nodeAggregateId);

        return PatchedPageTreeRepository::buildTypo3PageArrayForNode($node, $parentNode?->aggregateId, $this->contentRepository->getNodeTypeManager());
    }
}