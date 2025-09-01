<?php
declare(strict_types=1);

namespace Sandstorm\ContentrepositoryTypo3\Registry\Factory\EventStore;

use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\EventStore\DoctrineAdapter\DoctrineEventStore;
use Neos\EventStore\EventStoreInterface;
use Psr\Clock\ClockInterface;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

class DoctrineEventStoreFactory implements EventStoreFactoryInterface
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {
    }

    /** @param array<string, mixed> $options */
    public function build(ContentRepositoryId $contentRepositoryId, array $options, ClockInterface $clock): EventStoreInterface
    {
        $databaseTableName = self::databaseTableName($contentRepositoryId);

        return new DoctrineEventStore(
            $this->connectionPool->getConnectionForTable($databaseTableName),
            $databaseTableName,
            $clock
        );
    }

    public static function databaseTableName(ContentRepositoryId $contentRepositoryId): string
    {
        return sprintf('cr_%s_events', $contentRepositoryId->value);
    }
}
