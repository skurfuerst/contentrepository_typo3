<?php

namespace Sandstorm\ContentrepositoryTypo3\Integration\Feature\DataHandler;

use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Core\DimensionSpace\OriginDimensionSpacePoint;
use Neos\ContentRepository\Core\Feature\NodeCreation\Command\CreateNodeAggregateWithNode;
use Neos\ContentRepository\Core\Feature\NodeModification\Command\SetNodeProperties;
use Neos\ContentRepository\Core\Feature\NodeMove\Command\MoveNodeAggregate;
use Neos\ContentRepository\Core\Feature\NodeModification\Dto\PropertyValuesToWrite;
use Neos\ContentRepository\Core\Feature\NodeMove\Dto\RelationDistributionStrategy;
use Neos\ContentRepository\Core\NodeType\NodeTypeName;
use Neos\ContentRepository\Core\Projection\ContentGraph\ContentSubgraphInterface;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Sandstorm\ContentrepositoryTypo3\Integration\Feature\NodeIdHandling\NodeIdGenerator;
use Sandstorm\ContentrepositoryTypo3\Integration\Feature\RootNode\NodeTypeNameFactory;
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

    private function getContentSubgraph(): ContentSubgraphInterface
    {
        // TODO: WORKSPACE SUPPORT IN BACKEND
        // TODO: LANGUAGE SUPPORT IN BACKEND??
        return $this->contentRepository->getContentSubgraph(
            WorkspaceName::forLive(),
            DimensionSpacePoint::createWithoutDimensions()
        );
    }

    public function processDatamap_beforeStart(DataHandler $dataHandler) {
        var_dump($dataHandler->datamap);
        if (array_key_exists('pages', $dataHandler->datamap)) {
            // special content logic for us
            $subgraph = $this->getContentSubgraph();
            // HINT: WORK ON CONTENT SUBGRAPH HERE
            // HINT: create node, then move it around!!!

            foreach ($dataHandler->datamap['pages'] as $pageUid => $pageData) {
                if (str_contains($pageUid, 'NEW')) {
                    $pid = (int)$pageData['pid'];
                    $newNodeAggregateId = $this->nodeIdGenerator->newNodeId($this->contentRepository->id);

                    // Step 1: CREATE - Always create as a child first (we'll move it later if needed)
                    $initialParentNodeAggregateId = null;

                    if ($pid < 0) {
                        // Negative PID: Will be moved after creation to be sibling after referenced page
                        $referencedPageId = abs($pid);
                        $referencedNodeAggregateId = NodeIdGenerator::fromNumericTypo3Id($referencedPageId);
                        $referencedNode = $subgraph->findNodeById($referencedNodeAggregateId);
                        if ($referencedNode === null) {
                            throw new \RuntimeException("Referenced page with ID {$referencedPageId} not found");
                        }
                        // Create as child of the referenced node (which is wrong, but we will move it anyways)
                        $initialParentNodeAggregateId = $referencedNodeAggregateId;

                    } elseif ($pid === 0) {
                        // PID 0: create at root level
                        $initialParentNodeAggregateId = $subgraph->findRootNodeByType(NodeTypeNameFactory::forSites())?->aggregateId;

                    } else {
                        // Positive PID: create as child of the referenced page
                        $initialParentNodeAggregateId = NodeIdGenerator::fromNumericTypo3Id($pid);
                    }

                    // CREATE the node
                    // TODO: in TYPO3, it shouold be created as FIRST CHILD, but Neos creates it as LAST CHILD. -> so in TYPO3, we need to move it to the 1st position instead.
                    $this->contentRepository->handle(CreateNodeAggregateWithNode::create(
                        workspaceName: WorkspaceName::forLive(), // TODO: workspace?
                        nodeAggregateId: $newNodeAggregateId,
                        nodeTypeName: NodeTypeName::fromString('TYPO3:Page'), // TODO: from $pageData['doktype']
                        originDimensionSpacePoint: OriginDimensionSpacePoint::createWithoutDimensions(), // TODO: dimension support,
                        parentNodeAggregateId: $initialParentNodeAggregateId,
                        initialPropertyValues: PropertyValuesToWrite::fromArray([
                            'title' => $pageData['title'],
                        ])
                    ));


                    // Step 2: MOVE - If negative PID, move the node to be positioned after the referenced node
                    if ($pid < 0) {
                        $referencedPageId = abs($pid);
                        $referencedNodeAggregateId = NodeIdGenerator::fromNumericTypo3Id($referencedPageId);

                        $parentId = $subgraph->findParentNode($referencedNodeAggregateId)->aggregateId;
                        $this->contentRepository->handle(MoveNodeAggregate::create(
                            workspaceName: WorkspaceName::forLive(),
                            dimensionSpacePoint: DimensionSpacePoint::createWithoutDimensions(),
                            nodeAggregateId: $newNodeAggregateId,
                            relationDistributionStrategy: RelationDistributionStrategy::STRATEGY_SCATTER,
                            newParentNodeAggregateId: $parentId,
                            newPrecedingSiblingNodeAggregateId: $referencedNodeAggregateId, // Position AFTER referenced node
                        ));
                    }
                } else {
                    // MODIFICATION of data
                    $nodeAggregateId = NodeIdGenerator::fromNumericTypo3Id($pageUid);
                    $this->contentRepository->handle(SetNodeProperties::create(
                        workspaceName: WorkspaceName::forLive(),
                        originDimensionSpacePoint: OriginDimensionSpacePoint::createWithoutDimensions(),
                        nodeAggregateId: $nodeAggregateId,
                        propertyValues: PropertyValuesToWrite::fromArray([
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