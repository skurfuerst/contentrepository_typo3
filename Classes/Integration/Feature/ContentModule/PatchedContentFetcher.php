<?php

namespace Sandstorm\ContentrepositoryTypo3\Integration\Feature\ContentModule;

use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Core\NodeType\NodeTypeManager;
use Neos\ContentRepository\Core\Projection\ContentGraph\Filter\FindChildNodesFilter;
use Neos\ContentRepository\Core\Projection\ContentGraph\Node;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateId;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Sandstorm\ContentrepositoryTypo3\Integration\Feature\NodeIdHandling\NodeIdGenerator;
use Sandstorm\ContentrepositoryTypo3\Registry\ContentRepositoryRegistry;
use TYPO3\CMS\Backend\View\BackendLayout\ContentFetcher;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class PatchedContentFetcher extends ContentFetcher
{
    public function getContentRecordsPerColumn(?int $columnNumber = null, ?int $languageId = null): array {
        $contentRepositoryRegistry = GeneralUtility::makeInstance(ContentRepositoryRegistry::class);
        $contentRepository = $contentRepositoryRegistry->get(ContentRepositoryId::fromString('default'));
        $nodeTypeManager = $contentRepository->getNodeTypeManager();
        $subgraph = $contentRepository->getContentSubgraph(WorkspaceName::forLive(),DimensionSpacePoint::createWithoutDimensions());

        $parentNodeId = NodeIdGenerator::fromNumericTypo3Id($this->context->getPageId());

        // TODO: column stuff

        $children = $subgraph->findChildNodes($parentNodeId, FindChildNodesFilter::create(nodeTypes: 'TYPO3:Content'));
        $result = [];
        foreach ($children as $childNode) {
            $result[] = self::buildTypo3TtContentArrayForNode($childNode, $parentNodeId, $nodeTypeManager);
        }

        return $result;
    }

    public function getUnusedRecords(): iterable {
        return [];
    }

    public static function buildTypo3TtContentArrayForNode(Node $node, NodeAggregateId $parentId, NodeTypeManager $nodeTypeManager): array
    {
        $record = [
            ...$node->properties,

            'uid' => intval($node->aggregateId->value),
            'pid' => intval($parentId->value),
            'l18n_parent' => '',
            't3ver_wsid' => '',
            't3ver_oid' => '',
            't3ver_state' => null,
            't3ver_stage' => '',
            'crdate' => $node->timestamps->originalCreated->getTimestamp(),
            'tstamp' => $node->timestamps->originalLastModified?->getTimestamp() ?? $node->timestamps->originalCreated->getTimestamp(),
            'starttime' => 0,
            'endtime' => 0,
            'deleted' => 0,
            'sorting' => 0,

        ];

        return $record;
    }

}