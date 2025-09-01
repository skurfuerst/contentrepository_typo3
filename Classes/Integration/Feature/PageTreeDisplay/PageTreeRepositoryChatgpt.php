<?php

namespace Sandstorm\ContentrepositoryTypo3\Integration\Feature\PageTreeDisplay;

use Neos\ContentRepository\Core\ContentRepository;
use Neos\ContentRepository\Core\NodeType\NodeTypeName;
use Neos\ContentRepository\Core\Projection\ContentGraph\ContentSubgraphInterface;
use Neos\ContentRepository\Core\Projection\ContentGraph\Filter\CountChildNodesFilter;
use Neos\ContentRepository\Core\Projection\ContentGraph\Filter\FindChildNodesFilter;
use Neos\ContentRepository\Core\Projection\ContentGraph\Filter\Pagination\Pagination;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateId;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Sandstorm\ContentrepositoryTypo3\Registry\ContentRepositoryRegistry;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * TODO: UNUSED; REMOVE AT END
 *
 *
 * * Fetches ALL pages in the page tree using Neos ContentRepository as backend,
 * but maintaining TYPO3 page record structure compatibility.
 *
 * This works as a bridge between TYPO3's expected page structure and Neos ContentRepository.
 *
 * @internal this class is not public API yet, as it needs to be proven stable enough first.
 */
class PageTreeRepositoryChatgpt extends \TYPO3\CMS\Backend\Tree\Repository\PageTreeRepository
{
    /**
     * Fields to be returned (matching original TYPO3 structure)
     *
     * @var string[]
     */
    protected readonly array $fields;

    /**
     * The workspace ID to operate on
     */
    protected readonly int $currentWorkspace;

    /**
     * Full page tree when selected without permissions applied.
     */
    protected array $fullPageTree = [];

    protected readonly array $additionalQueryRestrictions;

    protected ?string $additionalWhereClause = null;

    /**
     * Neos ContentRepository Registry
     */
    protected ContentRepositoryRegistry $contentRepositoryRegistry;

    /**
     * Content Repository instance
     */
    protected ?ContentRepository $contentRepository = null;

    /**
     * Content Subgraph for querying
     */
    protected ?ContentSubgraphInterface $contentSubgraph = null;

    /**
     * @param int $workspaceId the workspace ID to be checked for.
     * @param array $additionalFieldsToQuery an array with more fields that should be accessed.
     * @param array $additionalQueryRestrictions an array with more restrictions to add
     */
    public function __construct(int $workspaceId = 0, array $additionalFieldsToQuery = [], array $additionalQueryRestrictions = [])
    {
        $this->currentWorkspace = $workspaceId;
        $this->fields = array_merge([
            'uid',
            'pid',
            'sorting',
            'starttime',
            'endtime',
            'hidden',
            'fe_group',
            'title',
            'nav_title',
            'nav_hide',
            'php_tree_stop',
            'doktype',
            'is_siteroot',
            'module',
            'extendToSubpages',
            'content_from_pid',
            't3ver_oid',
            't3ver_wsid',
            't3ver_state',
            't3ver_stage',
            'perms_userid',
            'perms_user',
            'perms_groupid',
            'perms_group',
            'perms_everybody',
            'mount_pid',
            'shortcut',
            'shortcut_mode',
            'mount_pid_ol',
            'url',
            'sys_language_uid',
            'l10n_parent',
        ], $additionalFieldsToQuery);
        $this->additionalQueryRestrictions = $additionalQueryRestrictions;

        // Initialize Neos ContentRepository if available
        $this->initializeContentRepository();
    }

    /**
     * Initialize Neos ContentRepository connection
     */
    protected function initializeContentRepository(): void
    {
        $this->contentRepositoryRegistry = GeneralUtility::makeInstance(ContentRepositoryRegistry::class);
        $this->contentRepository = $this->contentRepositoryRegistry->get(
            ContentRepositoryId::fromString('default')
        );
    }

    /**
     * Get content subgraph for querying
     */
    protected function getContentSubgraph(): ?ContentSubgraphInterface
    {
        if ($this->contentRepository === null) {
            return null;
        }

        if ($this->contentSubgraph === null) {
            $workspaceName = $this->currentWorkspace === 0
                ? WorkspaceName::forLive()
                : WorkspaceName::fromString('workspace-' . $this->currentWorkspace);

            // Create dimension space point (simplified for default language)
            $dimensionSpacePoint = \Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePoint::createWithoutDimensions();

            $this->contentSubgraph = $this->contentRepository->getContentSubgraph(
                $workspaceName,
                $dimensionSpacePoint
            );
        }

        return $this->contentSubgraph;
    }

    public function setAdditionalWhereClause(string $additionalWhereClause): void
    {
        $this->additionalWhereClause = $additionalWhereClause;
    }

    /**
     * Main entry point for this repository, to fetch the tree data for a page.
     * Uses Neos ContentRepository if available, falls back to TYPO3 database queries.
     *
     * @param int $entryPoint the page ID to fetch the tree for
     * @param callable|null $callback a callback to be used to check for permissions and filter out pages not to be included.
     */
    public function getTree(
        int       $entryPoint,
        ?callable $callback = null,
        array     $dbMounts = []
    ): array
    {
        $contentSubgraph = $this->getContentSubgraph();

        if ($contentSubgraph !== null) {
            return $this->getTreeFromContentRepository($entryPoint, $callback, $dbMounts);
        } else {
            return $this->getTreeFromDatabase($entryPoint, $callback, $dbMounts);
        }
    }

    /**
     * Get tree using Neos ContentRepository
     */
    protected function getTreeFromContentRepository(int $entryPoint, ?callable $callback, array $dbMounts): array
    {
        $contentSubgraph = $this->getContentSubgraph();

        if ($entryPoint === 0) {
            // Get sites root
            $sitesRootNode = $contentSubgraph->findRootNodeByType(
                NodeTypeName::fromString('Neos.Neos:Sites')
            );
            if ($sitesRootNode === null) {
                return [];
            }
            $tree = $this->buildTreeFromNode($sitesRootNode);
        } else {
            // Convert TYPO3 UID to Node Aggregate ID
            $nodeAggregateId = $this->convertUidToNodeAggregateId($entryPoint);
            $node = $contentSubgraph->findNodeById($nodeAggregateId);
            if ($node === null) {
                return [];
            }
            $tree = $this->buildTreeFromNode($node);
        }

        if (!empty($tree) && $callback !== null) {
            $this->applyCallbackToChildren($tree, $callback);
        }

        return $tree;
    }

    /**
     * Fallback to original TYPO3 database implementation
     */
    protected function getTreeFromDatabase(int $entryPoint, ?callable $callback, array $dbMounts): array
    {
        // This would contain the original TYPO3 database-based implementation
        // For now, return a basic structure
        return [
            'uid' => $entryPoint,
            'pid' => 0,
            'title' => 'Fallback to TYPO3 Database',
            'sorting' => 0,
            'starttime' => 0,
            'endtime' => 0,
            'hidden' => 0,
            'fe_group' => '',
            'nav_title' => '',
            'nav_hide' => 0,
            'php_tree_stop' => 0,
            'doktype' => 1,
            'is_siteroot' => 0,
            'module' => '',
            'extendToSubpages' => 0,
            'content_from_pid' => 0,
            't3ver_oid' => 0,
            't3ver_wsid' => $this->currentWorkspace,
            't3ver_state' => 0,
            't3ver_stage' => 0,
            'perms_userid' => 1,
            'perms_user' => 31,
            'perms_groupid' => 0,
            'perms_group' => 27,
            'perms_everybody' => 0,
            'mount_pid' => 0,
            'shortcut' => '',
            'shortcut_mode' => 0,
            'mount_pid_ol' => 0,
            'url' => '',
            'sys_language_uid' => 0,
            'l10n_parent' => 0,
            '_children' => []
        ];
    }

    /**
     * Build tree structure from a Neos node with TYPO3-compatible field structure
     */
    protected function buildTreeFromNode(Node $node): array
    {
        $contentSubgraph = $this->getContentSubgraph();

        // Map Neos node properties to TYPO3 page record structure
        $tree = $this->mapNodeToPageRecord($node);

        // Get child nodes
        $filter = FindChildNodesFilter::create();

        $childNodes = $contentSubgraph->findChildNodes(
            $node->aggregateId,
            $filter
        );

        foreach ($childNodes as $childNode) {
            $tree['_children'][] = $this->buildTreeFromNode($childNode);
        }

        return $tree;
    }

    /**
     * Map Neos node to TYPO3 page record structure
     */
    protected function mapNodeToPageRecord(Node $node): array
    {
        $properties = $node->properties->values;
        $contentSubgraph = $this->getContentSubgraph();

        // Get parent node for pid
        $parentNode = $contentSubgraph->findParentNode($node->aggregateId);
        $pid = $parentNode ? $this->convertNodeAggregateIdToUid($parentNode->aggregateId) : 0;

        return [
            // Core TYPO3 fields mapped from Neos
            'uid' => $this->convertNodeAggregateIdToUid($node->aggregateId),
            'pid' => $pid,
            'sorting' => (int)($properties['_sortingOrder'] ?? 0),
            'starttime' => $this->convertDateTimeToTimestamp($properties['_hiddenBeforeDateTime'] ?? null),
            'endtime' => $this->convertDateTimeToTimestamp($properties['_hiddenAfterDateTime'] ?? null),
            'hidden' => ($properties['_hidden'] ?? false) ? 1 : 0,
            'fe_group' => $this->mapAccessRoles($properties['_accessRoles'] ?? []),
            'title' => (string)($properties['title'] ?? ''),
            'nav_title' => (string)($properties['navigationTitle'] ?? $properties['title'] ?? ''),
            'nav_hide' => ($properties['_hiddenInIndex'] ?? false) ? 1 : 0,
            'php_tree_stop' => 0, // Not directly applicable in Neos
            'doktype' => $this->mapNodeTypeToDoktype($node->nodeTypeName->value),
            'is_siteroot' => $this->isSiteRoot($node) ? 1 : 0,
            'module' => '', // Not directly applicable
            'extendToSubpages' => 0, // Not directly applicable
            'content_from_pid' => 0, // Not directly applicable
            't3ver_oid' => 0, // Workspace handling different in Neos
            't3ver_wsid' => $this->currentWorkspace,
            't3ver_state' => 0, // Different versioning system
            't3ver_stage' => 0, // Different versioning system
            'perms_userid' => 1, // Default admin
            'perms_user' => 31, // Default permissions
            'perms_groupid' => 0,
            'perms_group' => 27,
            'perms_everybody' => 0,
            'mount_pid' => 0,
            'shortcut' => (string)($properties['targetNode'] ?? ''), // For shortcut nodes
            'shortcut_mode' => $this->mapShortcutMode($node->nodeTypeName->value),
            'mount_pid_ol' => 0,
            'url' => (string)($properties['externalUrl'] ?? ''), // For external URL nodes
            'sys_language_uid' => $this->getCurrentLanguageUid(),
            'l10n_parent' => 0, // Different translation handling in Neos
            '_children' => []
        ];
    }

    /**
     * Convert Neos NodeAggregateId to TYPO3 UID
     */
    protected function convertNodeAggregateIdToUid(NodeAggregateId $nodeAggregateId): int
    {
        // Simple hash-based conversion - in production you might want a mapping table
        return abs(crc32($nodeAggregateId->value)) % 2147483647; // Keep within integer range
    }

    /**
     * Convert TYPO3 UID to Neos NodeAggregateId (requires mapping)
     */
    protected function convertUidToNodeAggregateId(int $uid): NodeAggregateId
    {
        // This would need a proper mapping table in production
        // For now, create a deterministic UUID from the UID
        $uuid = sprintf(
            '%08x-0000-0000-0000-%012x',
            $uid,
            $uid
        );
        return NodeAggregateId::fromString($uuid);
    }

    /**
     * Convert DateTime to timestamp
     */
    protected function convertDateTimeToTimestamp($dateTime): int
    {
        if ($dateTime === null) {
            return 0;
        }

        if ($dateTime instanceof \DateTimeInterface) {
            return $dateTime->getTimestamp();
        }

        if (is_string($dateTime)) {
            $dt = \DateTime::createFromFormat('Y-m-d H:i:s', $dateTime);
            return $dt ? $dt->getTimestamp() : 0;
        }

        return 0;
    }

    /**
     * Map Neos access roles to TYPO3 fe_group format
     */
    protected function mapAccessRoles(array $accessRoles): string
    {
        if (empty($accessRoles)) {
            return '';
        }

        // Convert role names to group IDs (simplified mapping)
        $groupIds = [];
        foreach ($accessRoles as $role) {
            $groupIds[] = abs(crc32($role)) % 1000; // Simple hash to group ID
        }

        return implode(',', $groupIds);
    }

    /**
     * Map Neos node type to TYPO3 doktype
     */
    protected function mapNodeTypeToDoktype(string $nodeTypeName): int
    {
        $mapping = [
            'Neos.Neos:Document' => 1, // Standard page
            'Neos.NodeTypes:Page' => 1, // Standard page
            'Neos.Neos:Shortcut' => 4, // Shortcut
            'Neos.NodeTypes:HomePage' => 1, // Home page
            'Neos.NodeTypes:ExternalUrl' => 3, // External URL
            'Neos.Neos:Sites' => 254, // Sites root
            'Neos.Neos:Site' => 1, // Site root as standard page
        ];

        return $mapping[$nodeTypeName] ?? 1; // Default to standard page
    }

    /**
     * Check if node is a site root
     */
    protected function isSiteRoot(Node $node): bool
    {
        return $node->nodeTypeName->value === 'Neos.Neos:Site' ||
            ($node->properties->values['isSiteRoot'] ?? false);
    }

    /**
     * Map shortcut mode from node type
     */
    protected function mapShortcutMode(string $nodeTypeName): int
    {
        if ($nodeTypeName === 'Neos.Neos:Shortcut') {
            return 1; // Selected page
        }

        return 0;
    }

    /**
     * Get current language UID (simplified)
     */
    protected function getCurrentLanguageUid(): int
    {
        // In a real implementation, this would get the current dimension space point language
        return 0; // Default language
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
        $contentSubgraph = $this->getContentSubgraph();

        // Convert TYPO3 page record to Node if possible
        $nodeAggregateId = $this->convertUidToNodeAggregateId($pageTree['uid']);
        $node = $contentSubgraph->findNodeById($nodeAggregateId);

        if ($node === null) {
            return $pageTree; // Return original if node not found
        }

        $tree = $this->mapNodeToPageRecord($node);

        if ($depth > 0) {
            $this->addChildrenToTreeLevel($tree, $depth - 1, $entryPointIds);
        }

        return $tree;
    }

    /**
     * Add children to tree up to specified depth
     */
    protected function addChildrenToTreeLevel(array &$tree, int $remainingDepth, ?array $entryPointIds): void
    {
        if ($remainingDepth < 0) {
            return;
        }

        $contentSubgraph = $this->getContentSubgraph();
        $nodeAggregateId = $this->convertUidToNodeAggregateId($tree['uid']);

        $filter = FindChildNodesFilter::create();
        $childNodes = $contentSubgraph->findChildNodes($nodeAggregateId, $filter);

        foreach ($childNodes as $childNode) {
            $childUid = $this->convertNodeAggregateIdToUid($childNode->aggregateId);

            // Filter by entry point IDs if specified
            if ($entryPointIds !== null && !in_array($childUid, $entryPointIds, true)) {
                continue;
            }

            $childTree = $this->mapNodeToPageRecord($childNode);

            if ($remainingDepth > 0) {
                $this->addChildrenToTreeLevel($childTree, $remainingDepth - 1, $entryPointIds);
            }

            $tree['_children'][] = $childTree;
        }
    }

    /**
     * Useful to get a list of pages, with a specific depth
     *
     * @param int[] $entryPointIds
     */
    public function getFlattenedPages(array $entryPointIds, int $depth): array
    {
        $contentSubgraph = $this->getContentSubgraph();

        if ($contentSubgraph !== null) {
            return $this->getFlattenedPagesFromContentRepository($entryPointIds, $depth);
        } else {
            return $this->getFlattenedPagesFromDatabase($entryPointIds, $depth);
        }
    }

    /**
     * Get flattened pages using Neos ContentRepository
     */
    protected function getFlattenedPagesFromContentRepository(array $entryPointIds, int $depth): array
    {
        $allPageRecords = [];
        $contentSubgraph = $this->getContentSubgraph();

        foreach ($entryPointIds as $entryPointId) {
            $nodeAggregateId = $this->convertUidToNodeAggregateId($entryPointId);
            $node = $contentSubgraph->findNodeById($nodeAggregateId);

            if ($node !== null) {
                $allPageRecords[] = $this->mapNodeToPageRecord($node);
                $descendants = $this->getDescendantNodes($node, $depth);
                foreach ($descendants as $descendantNode) {
                    $allPageRecords[] = $this->mapNodeToPageRecord($descendantNode);
                }
            }
        }

        return $allPageRecords;
    }

    /**
     * Get flattened pages from database
     */
    protected function getFlattenedPagesFromDatabase(array $entryPointIds, int $depth): array
    {
        // Original TYPO3 database implementation would go here
        return [];
    }

    /**
     * Get descendant nodes up to specified depth
     */
    protected function getDescendantNodes(Node $node, int $depth): array
    {
        if ($depth <= 0) {
            return [];
        }

        $contentSubgraph = $this->getContentSubgraph();
        $descendants = [];

        $filter = FindDescendantNodesFilter::create()
            ->withMaximumLevels($depth);

        $descendantNodes = $contentSubgraph->findDescendantNodes(
            $node->aggregateId,
            $filter
        );

        return iterator_to_array($descendantNodes);
    }

    public function hasChildren(int $pid): bool
    {
        $contentSubgraph = $this->getContentSubgraph();

        if ($contentSubgraph !== null) {
            $nodeAggregateId = $this->convertUidToNodeAggregateId($pid);
            return $contentSubgraph->countChildNodes($nodeAggregateId, CountChildNodesFilter::create()) > 0;
        } else {
            // Fallback to database query
            return false; // Original implementation would go here
        }
    }

    /**
     * Retrieve the page tree based on the given search filter
     */
    public function fetchFilteredTree(string $searchFilter, array $allowedMountPointPageIds, string $additionalWhereClause): array
    {
        $contentSubgraph = $this->getContentSubgraph();

        if ($contentSubgraph !== null) {
            return $this->fetchFilteredTreeFromContentRepository($searchFilter, $allowedMountPointPageIds);
        } else {
            return $this->fetchFilteredTreeFromDatabase($searchFilter, $allowedMountPointPageIds, $additionalWhereClause);
        }
    }

    /**
     * Fetch filtered tree using Neos ContentRepository
     */
    protected function fetchFilteredTreeFromContentRepository(string $searchFilter, array $allowedMountPointPageIds): array
    {
        // Search for nodes matching the filter
        $foundNodes = $this->searchNodes($searchFilter);

        // Filter nodes based on mount points
        $filteredNodes = $this->filterNodesByMountPoints($foundNodes, $allowedMountPointPageIds);

        // Build tree structure from filtered nodes
        return $this->buildTreeFromFilteredNodes($filteredNodes);
    }

    /**
     * Fetch filtered tree from database
     */
    protected function fetchFilteredTreeFromDatabase(string $searchFilter, array $allowedMountPointPageIds, string $additionalWhereClause): array
    {
        // Original TYPO3 database implementation would go here
        return [];
    }

    /**
     * Search for nodes matching the search filter
     */
    protected function searchNodes(string $searchFilter): array
    {
        $contentSubgraph = $this->getContentSubgraph();

        $sitesRootNode = $contentSubgraph->findRootNodeByType(
            NodeTypeName::fromString('Neos.Neos:Sites')
        );

        if ($sitesRootNode === null) {
            return [];
        }

        $filter = FindDescendantNodesFilter::create();
        $allDescendants = $contentSubgraph->findDescendantNodes(
            $sitesRootNode->aggregateId,
            $filter
        );

        $matchingNodes = [];
        foreach ($allDescendants as $node) {
            if ($this->nodeMatchesSearchFilter($node, $searchFilter)) {
                $matchingNodes[] = $node;
            }
        }

        return $matchingNodes;
    }

    /**
     * Check if a node matches the search filter
     */
    protected function nodeMatchesSearchFilter(Node $node, string $searchFilter): bool
    {
        // Check if the search filter is a numeric ID
        if (is_numeric($searchFilter)) {
            $uid = $this->convertNodeAggregateIdToUid($node->aggregateId);
            return $uid == (int)$searchFilter;
        }

        // Search in node properties (title, etc.)
        $properties = $node->properties->values;

        $searchableProperties = ['title', 'uriPathSegment', 'navigationTitle'];
        foreach ($searchableProperties as $propertyName) {
            if (isset($properties[$propertyName]) &&
                stripos((string)$properties[$propertyName], $searchFilter) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Filter nodes based on mount points
     */
    protected function filterNodesByMountPoints(array $nodes, array $allowedMountPointPageIds): array
    {
        if (empty($allowedMountPointPageIds)) {
            return $nodes;
        }

        $filteredNodes = [];

        foreach ($nodes as $node) {
            $isAllowed = false;

            // Check if node is within any allowed mount point
            foreach ($allowedMountPointPageIds as $mountPointId) {
                if ($this->isNodeWithinMountPoint($node, $mountPointId)) {
                    $isAllowed = true;
                    break;
                }
            }

            if ($isAllowed) {
                $filteredNodes[] = $node;
            }
        }

        return $filteredNodes;
    }

    /**
     * Check if a node is within a mount point
     */
    protected function isNodeWithinMountPoint(Node $node, int $mountPointId): bool
    {
        $contentSubgraph = $this->getContentSubgraph();

        // Walk up the tree to see if we find the mount point
        $currentNode = $node;

        while ($currentNode !== null) {
            $currentUid = $this->convertNodeAggregateIdToUid($currentNode->aggregateId);
            if ($currentUid === $mountPointId) {
                return true;
            }

            $parentNode = $contentSubgraph->findParentNode($currentNode->aggregateId);
            $currentNode = $parentNode;
        }

        return false;
    }

    /**
     * Build tree structure from filtered nodes with TYPO3-compatible structure
     */
    protected function buildTreeFromFilteredNodes(array $nodes): array
    {
        $tree = [
            'uid' => 0,
            'pid' => 0,
            'sorting' => 0,
            'starttime' => 0,
            'endtime' => 0,
            'hidden' => 0,
            'fe_group' => '',
            'title' => $GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename'] ?? 'TYPO3',
            'nav_title' => '',
            'nav_hide' => 0,
            'php_tree_stop' => 0,
            'doktype' => 254, // Sites root
            'is_siteroot' => 1,
            'module' => '',
            'extendToSubpages' => 0,
            'content_from_pid' => 0,
            't3ver_oid' => 0,
            't3ver_wsid' => $this->currentWorkspace,
            't3ver_state' => 0,
            't3ver_stage' => 0,
            'perms_userid' => 1,
            'perms_user' => 31,
            'perms_groupid' => 0,
            'perms_group' => 27,
            'perms_everybody' => 0,
            'mount_pid' => 0,
            'shortcut' => '',
            'shortcut_mode' => 0,
            'mount_pid_ol' => 0,
            'url' => '',
            'sys_language_uid' => 0,
            'l10n_parent' => 0,
            '_children' => []
        ];

        // Group nodes by their parent to build hierarchy
        $nodesByParent = [];
        $contentSubgraph = $this->getContentSubgraph();

        foreach ($nodes as $node) {
            $parentNode = $contentSubgraph->findParentNode($node->aggregateId);
            $parentUid = $parentNode ? $this->convertNodeAggregateIdToUid($parentNode->aggregateId) : 0;

            if (!isset($nodesByParent[$parentUid])) {
                $nodesByParent[$parentUid] = [];
            }

            $nodesByParent[$parentUid][] = $this->mapNodeToPageRecord($node);
        }

        // Build tree recursively
        $this->addFilteredChildrenToTree($tree, $nodesByParent);

        return $tree;
    }

    /**
     * Add filtered children to tree structure
     */
    protected function addFilteredChildrenToTree(array &$tree, array $nodesByParent): void
    {
        $nodeUid = $tree['uid'];

        if (isset($nodesByParent[$nodeUid])) {
            $tree['_children'] = $nodesByParent[$nodeUid];

            foreach ($tree['_children'] as &$child) {
                $this->addFilteredChildrenToTree($child, $nodesByParent);
            }
        }
    }

    /**
     * Removes items from a tree based on a callback, usually used for permission checks
     */
    protected function applyCallbackToChildren(array &$tree, callable $callback): void
    {
        if (!isset($tree['_children'])) {
            return;
        }

        foreach ($tree['_children'] as $k => &$childPage) {
            if (!$callback($childPage)) {
                unset($tree['_children'][$k]);
                continue;
            }
            $this->applyCallbackToChildren($childPage, $callback);
        }

        // Re-index array after unsetting elements
        $tree['_children'] = array_values($tree['_children']);
    }

    protected function getBackendUser(): \TYPO3\CMS\Core\Authentication\BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }
}