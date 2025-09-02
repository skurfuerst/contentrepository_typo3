<?php

namespace Sandstorm\ContentrepositoryTypo3\Integration\Feature\FrontendRendering;

use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Core\Projection\ContentGraph\ContentSubgraphInterface;
use Neos\ContentRepository\Core\Projection\ContentGraph\Filter\FindChildNodesFilter;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Sandstorm\ContentrepositoryTypo3\Integration\Feature\ContentModule\PatchedContentFetcher;
use Sandstorm\ContentrepositoryTypo3\Integration\Feature\NodeIdHandling\NodeIdGenerator;
use Sandstorm\ContentrepositoryTypo3\Registry\ContentRepositoryRegistry;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\Event\ModifyRecordsAfterFetchingContentEvent;

class ModifyRecordsAfterFetchingContentListener
{
    private \Neos\ContentRepository\Core\ContentRepository $contentRepository;
    private ContentSubgraphInterface $subgraph;

    public function __construct(ContentRepositoryRegistry $contentRepositoryRegistry)
    {
        $this->contentRepository = $contentRepositoryRegistry->get(
            ContentRepositoryId::fromString('default')
        );
        $this->subgraph = $this->contentRepository->getContentSubgraph(
            WorkspaceName::forLive(),
            DimensionSpacePoint::createWithoutDimensions()
        );
    }

    public function __invoke(ModifyRecordsAfterFetchingContentEvent $event): void
    {
        $configuration = $event->getConfiguration();
        if ($configuration['table'] !== 'tt_content') {
            return;
        }

        $pageAggregateId = NodeIdGenerator::fromNumericTypo3Id($this->getCurrentPageId());
        $children = $this->subgraph->findChildNodes($pageAggregateId, FindChildNodesFilter::create(
            nodeTypes: 'TYPO3:Content'
        ));
        $result = [];
        foreach ($children as $childNode) {
            $result[] = PatchedContentFetcher::buildTypo3TtContentArrayForNode($childNode, $pageAggregateId, $this->contentRepository->getNodeTypeManager());
        }

        $event->setRecords($result);
    }

    /**
     * Method 2: Get current page ID via Context API (recommended)
     */
    private function getCurrentPageId(): int
    {
        return $GLOBALS['TSFE']->page['uid'];
    }

}