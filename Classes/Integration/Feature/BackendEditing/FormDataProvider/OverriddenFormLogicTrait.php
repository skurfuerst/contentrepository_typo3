<?php

namespace Sandstorm\ContentrepositoryTypo3\Integration\Feature\BackendEditing\FormDataProvider;

use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Sandstorm\ContentrepositoryTypo3\Integration\Feature\ContentModule\PatchedContentFetcher;
use Sandstorm\ContentrepositoryTypo3\Integration\Feature\NodeIdHandling\NodeIdGenerator;
use Sandstorm\ContentrepositoryTypo3\Registry\ContentRepositoryRegistry;
use TYPO3\CMS\Core\Utility\GeneralUtility;

trait OverriddenFormLogicTrait
{
    protected function getRecordFromDatabase($tableName, $uid)
    {
        if ($tableName !== 'pages' && $tableName !== 'tt_content') {
            return parent::getRecordFromDatabase($tableName, $uid);
        }

        $contentRepositoryRegistry = GeneralUtility::makeInstance(ContentRepositoryRegistry::class);
        $contentRepository = $contentRepositoryRegistry->get(ContentRepositoryId::fromString('default'));
        $nodeTypeManager = $contentRepository->getNodeTypeManager();
        $subgraph = $contentRepository->getContentSubgraph(WorkspaceName::forLive(),DimensionSpacePoint::createWithoutDimensions());

        $node = $subgraph->findNodeById(NodeIdGenerator::fromNumericTypo3Id($uid));
        $parentNode = $subgraph->findParentNode(NodeIdGenerator::fromNumericTypo3Id($uid));

        return PatchedContentFetcher::buildTypo3TtContentArrayForNode($node, $parentNode->aggregateId, $nodeTypeManager);
    }
}