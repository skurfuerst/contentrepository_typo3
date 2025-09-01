<?php

namespace Sandstorm\ContentrepositoryTypo3\Integration\Feature\NodeIdHandling;

use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateId;
use TYPO3\CMS\Core\Database\ConnectionPool;

class NodeIdGenerator
{
    public function __construct(private readonly ConnectionPool $connectionPool)
    {
    }

    public function newNodeId(ContentRepositoryId $contentRepositoryId): NodeAggregateId
    {
        $eventTableName = 'cr_' . $contentRepositoryId->value . '_events';
        $connection = $this->connectionPool->getConnectionForTable($eventTableName);
        $maxId = $connection->executeQuery('SELECT count(*) FROM ' . $eventTableName . ' WHERE type="NodeAggregateWithNodeWasCreated"')->fetchOne() ?? 0;
        return self::fromNumericTypo3Id(intval($maxId)+1);
    }

    public static function fromNumericTypo3Id(int $numericId): NodeAggregateId
    {
        return NodeAggregateId::fromString(sprintf('%09d', $numericId));
    }
}