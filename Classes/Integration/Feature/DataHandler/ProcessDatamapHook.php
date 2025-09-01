<?php

namespace Sandstorm\ContentrepositoryTypo3\Integration\Feature\DataHandler;

use Neos\ContentRepository\Core\DimensionSpace\OriginDimensionSpacePoint;
use Neos\ContentRepository\Core\Feature\NodeCreation\Command\CreateNodeAggregateWithNode;
use Neos\ContentRepository\Core\Feature\NodeModification\Dto\PropertyValuesToWrite;
use Neos\ContentRepository\Core\NodeType\NodeTypeName;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Sandstorm\ContentrepositoryTypo3\Integration\Feature\NodeIdHandling\NodeIdGenerator;
use Sandstorm\ContentrepositoryTypo3\Registry\ContentRepositoryRegistry;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ProcessDatamapHook {

    private \Neos\ContentRepository\Core\ContentRepository $contentRepository;

    public function __construct(ContentRepositoryRegistry $contentRepositoryRegistry, private readonly NodeIdGenerator $nodeIdGenerator)
    {
        $this->contentRepository = $contentRepositoryRegistry->get(
            ContentRepositoryId::fromString('default')
        );
    }

    public function processDatamap_beforeStart(DataHandler $dataHandler) {
        var_dump($dataHandler->datamap);
        if (array_key_exists('pages', $dataHandler->datamap)) {
            // special content logic for us
            foreach ($dataHandler->datamap['pages'] as $pageUid => $pageData) {
                if (str_contains(   $pageUid, 'NEW')) {
                    $parentNodeAggregateId = NodeIdGenerator::fromNumericTypo3Id($pageData['pid']);
                    $newNodeAggregateId = $this->nodeIdGenerator->newNodeId($this->contentRepository->id);

                    $this->contentRepository->handle(CreateNodeAggregateWithNode::create(
                        workspaceName: WorkspaceName::forLive(), // TODO: workspace?
                        nodeAggregateId: $newNodeAggregateId,
                        nodeTypeName: NodeTypeName::fromString('TYPO3:Page'), // TODO: from $pageData['doktype']
                        originDimensionSpacePoint: OriginDimensionSpacePoint::createWithoutDimensions(), // TODO: dimension support,
                        parentNodeAggregateId: $parentNodeAggregateId,
                        // TODO: succeeding sibling NAID (for position)
                        initialPropertyValues: PropertyValuesToWrite::fromArray([
                            'title' => $pageData['title'],
                        ])
                    ));
                }
            }

            // prevent original logic
            unset($dataHandler->datamap['pages']);
        }
    }
}
