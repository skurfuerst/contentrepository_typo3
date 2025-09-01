<?php

declare(strict_types=1);

namespace Sandstorm\ContentrepositoryTypo3\Registry\Factory\SubscriptionStore;

use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepository\Core\Subscription\Store\SubscriptionStoreInterface;
use Neos\ContentRepository\Dbal\SubscriptionStore\DoctrineSubscriptionStore;
use Psr\Clock\ClockInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * @api
 */
final readonly class SubscriptionStoreFactory implements SubscriptionStoreFactoryInterface
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
    )
    {
    }

    /** @param array<string, mixed> $options */
    public function build(ContentRepositoryId $contentRepositoryId, ClockInterface $clock, array $options): SubscriptionStoreInterface
    {
        $databaseTableName = sprintf('cr_%s_subscriptions', $contentRepositoryId->value);
        $connection = $this->connectionPool->getConnectionForTable($databaseTableName);
        return new DoctrineSubscriptionStore($databaseTableName, $connection, $clock);
    }
}
