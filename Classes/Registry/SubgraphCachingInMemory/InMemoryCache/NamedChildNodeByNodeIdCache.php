<?php

/*
 * This file is part of the Neos.ContentRepository package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

declare(strict_types=1);

namespace Sandstorm\ContentrepositoryTypo3\Registry\SubgraphCachingInMemory\InMemoryCache;

use Neos\ContentRepository\Core\Projection\ContentGraph\Node;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateId;
use Neos\ContentRepository\Core\SharedModel\Node\NodeName;

/**
 * Parent Node ID + Node Name => Child Node
 *
 * @internal
 */
final class NamedChildNodeByNodeIdCache
{
    /**
     * first level: Parent Node ID
     * Second Level: Node Name
     * Value: Node
     * @var array<string,array<string,Node>>
     */
    protected array $nodes = [];

    public function add(
        NodeAggregateId $parentNodeAggregateId,
        ?NodeName $nodeName,
        Node $node
    ): void {
        if ($nodeName === null) {
            return;
        }

        $this->nodes[$parentNodeAggregateId->value][$nodeName->value] = $node;
    }

    public function contains(NodeAggregateId $parentNodeAggregateId, NodeName $nodeName): bool
    {
        return isset($this->nodes[$parentNodeAggregateId->value][$nodeName->value]);
    }

    public function get(NodeAggregateId $parentNodeAggregateId, NodeName $nodeName): ?Node
    {
        return $this->nodes[$parentNodeAggregateId->value][$nodeName->value] ?? null;
    }
}
