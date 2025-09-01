<?php

namespace Sandstorm\ContentrepositoryTypo3\Registry\Factory;

use Neos\ContentGraph\DoctrineDbalAdapter\DoctrineDbalContentGraphProjectionFactory;
use Neos\ContentRepository\Core\Factory\SubscriberFactoryDependencies;
use Neos\ContentRepository\Core\Projection\ContentGraph\ContentGraphProjectionFactoryInterface;
use Neos\ContentRepository\Core\Projection\ContentGraph\ContentGraphProjectionInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;

class ContentGraphProjectionFactoryAdapter implements ContentGraphProjectionFactoryInterface
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
    )
    {
    }

    public function build(SubscriberFactoryDependencies $projectionFactoryDependencies): ContentGraphProjectionInterface
    {
        // WORKAROUND for working with TYPO3 databases
        $fakeDatabaseTableName = sprintf('cr_%s_events', $projectionFactoryDependencies->contentRepositoryId);
        $connection = $this->connectionPool->getConnectionForTable($fakeDatabaseTableName);
        $originalFactory = new DoctrineDbalContentGraphProjectionFactory($connection);

        return $originalFactory->build($projectionFactoryDependencies);
    }
}