<?php

namespace Sandstorm\ContentrepositoryTypo3\Integration\Feature\PageTreeDisplay;


use Neos\ContentRepository\Core\ContentRepository;
use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Core\NodeType\NodeTypeManager;
use Neos\ContentRepository\Core\NodeType\NodeTypeName;
use Neos\ContentRepository\Core\Projection\ContentGraph\ContentSubgraphInterface;
use Neos\ContentRepository\Core\Projection\ContentGraph\Filter\FindChildNodesFilter;
use Neos\ContentRepository\Core\Projection\ContentGraph\Node;
use Neos\ContentRepository\Core\Projection\ContentGraph\Nodes;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateId;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Sandstorm\ContentrepositoryTypo3\Integration\Feature\NodeIdHandling\NodeIdGenerator;
use Sandstorm\ContentrepositoryTypo3\Integration\Feature\RootNode\NodeTypeNameFactory;
use Sandstorm\ContentrepositoryTypo3\Registry\ContentRepositoryRegistry;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Fetches ALL pages in the page tree using Neos ContentRepository as backend,
 * but maintaining TYPO3 page record structure compatibility.
 *
 * This works as a bridge between TYPO3's expected page structure and Neos ContentRepository.
 *
 * @internal this class is not public API yet, as it needs to be proven stable enough first.
 */
class PatchedPageTreeRepository extends \TYPO3\CMS\Backend\Tree\Repository\PageTreeRepository
{
    private ContentRepository $contentRepository;

    public function __construct(int $workspaceId = 0, array $additionalFieldsToQuery = [], array $additionalQueryRestrictions = [])
    {
        parent::__construct($workspaceId, $additionalFieldsToQuery, $additionalQueryRestrictions);
        $contentRepositoryRegistry = GeneralUtility::makeInstance(ContentRepositoryRegistry::class);
        $this->contentRepository = $contentRepositoryRegistry->get(
            ContentRepositoryId::fromString('default')
        );
    }

    private static function mapNodeTypeNameToDoktype(NodeTypeName $nodeTypeName): int
    {
        if ($nodeTypeName->equals(NodeTypeNameFactory::forSites())) {
            return 0;
        }
        if (!str_starts_with($nodeTypeName->value, 'TYPO3:Document.')) {
            throw new \RuntimeException('TODO: Node type ' . $nodeTypeName->value . ' does not start with TYPO3:Document.');
        }

        return intval(substr($nodeTypeName->value, 15));

    }

    /**
     * Get the page tree based on a given page record and a given depth
     *
     * @param array $pageTree The page record of the top level page you want to get the page tree of
     * @param int $depth Number of levels to fetch
     * @param ?array $entryPointIds entryPointIds to include (null in case no entry-points were provided)
     */
    public function getTreeLevels(array $pageTree, int $depth, ?array $entryPointIds = null): array
    {
        $subgraph = $this->getContentSubgraph();
        if (intval($pageTree['uid']) === 0) {
            $rootNode = $subgraph->findRootNodeByType(NodeTypeNameFactory::forSites());
        } else {
            $rootNode = $subgraph->findNodeById(NodeIdGenerator::fromNumericTypo3Id($pageTree['uid']));
        }

        $children = $subgraph->findChildNodes($rootNode->aggregateId, FindChildNodesFilter::create(nodeTypes: 'TYPO3:Document'));

        $loaderFunction = null;
        $loaderFunction = function(Node $node, $level) use ($subgraph, &$loaderFunction) {
            $children = $subgraph->findChildNodes($node->aggregateId, FindChildNodesFilter::create(nodeTypes: 'TYPO3:Document'));
            return $this->mapNodesToPageRecordsAndLoadChildren($children, $node->aggregateId, $level + 1, $loaderFunction);
        };

        $pageTree['_children'] = $this->mapNodesToPageRecordsAndLoadChildren($children, $rootNode->aggregateId, 1, $loaderFunction);
        return $pageTree;
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

    private function mapNodesToPageRecordsAndLoadChildren(Nodes $nodes, NodeAggregateId $parentId, int $currentLevel, \Closure $loadChildrenFn): array
    {
        $result = [];
        foreach ($nodes as $node) {
            $result[] = $this->mapNodeToPageRecordAndLoadChildren($node, $parentId, $currentLevel, $loadChildrenFn);
        }

        return $result;
    }

    private function mapNodeToPageRecordAndLoadChildren(Node $node, NodeAggregateId $parentId, int $currentLevel, \Closure $loadChildrenFn): array
    {
        $nodeTypeManager = $this->contentRepository->getNodeTypeManager();

        $nodeType = $nodeTypeManager->getNodeType($node->nodeTypeName);

        $typo3PageArray = self::buildTypo3PageArrayForNode($node, $parentId, $nodeTypeManager);
        $typo3PageArray['_children'] = $loadChildrenFn($node, $currentLevel);

        return $typo3PageArray;
    }

    public static function buildTypo3PageArrayForNode(Node $node, NodeAggregateId|null $parentId, NodeTypeManager $nodeTypeManager): array
    {
        $properties = $node->properties;
        $nodeType = $nodeTypeManager->getNodeType($node->nodeTypeName);

        return [
            ...$node->properties,

            // Core TYPO3 fields mapped from Neos
            'uid' => intval($node->aggregateId->value),
            'pid' => $parentId ? intval($parentId->value) : 0,
            'sorting' => 0,
            'starttime' => null,
            'endtime' => null,
            'hidden' => false, // TODO
            'fe_group' => '',
            'title' => (string)($properties['title'] ?? ''),
            'nav_title' => (string)($properties['navigationTitle'] ?? $properties['title'] ?? ''),
            'nav_hide' => false,
            'php_tree_stop' => 0,
            'doktype' => self::mapNodeTypeNameToDoktype($node->nodeTypeName),
            'is_siteroot' => $nodeType->isOfType(NodeTypeNameFactory::forSite()),
            'module' => '', // Not directly applicable
            'extendToSubpages' => 0, // Not directly applicable
            'content_from_pid' => 0, // Not directly applicable
            't3ver_oid' => 0, // Workspace handling different in Neos
            't3ver_wsid' => 0,
            't3ver_state' => 0, // Different versioning system
            't3ver_stage' => 0, // Different versioning system
            'perms_userid' => 1, // Default admin
            'perms_user' => 31, // Default permissions
            //'perms_groupid' => 0,
            'crdate' => $node->timestamps->originalCreated->getTimestamp(),
            'tstamp' => $node->timestamps->originalLastModified?->getTimestamp() ?? $node->timestamps->originalCreated->getTimestamp(),
            'SYS_LASTCHANGED' => $node->timestamps->originalLastModified?->getTimestamp() ?? $node->timestamps->originalCreated->getTimestamp(),
            'perms_group' => 27,
            'perms_everybody' => 0,
            'mount_pid' => 0,
            'shortcut' => '',
            'shortcut_mode' => '',
            'mount_pid_ol' => 0,
            'url' => '',
            'sys_language_uid' => 0,
            'l10n_parent' => 0, // Different translation handling in Neos
        ];
    }
}