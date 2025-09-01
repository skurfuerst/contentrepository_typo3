<?php
declare(strict_types=1);
namespace Sandstorm\ContentrepositoryTypo3\Registry\Factory\EventStore;

use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\EventStore\EventStoreInterface;
use Psr\Clock\ClockInterface;

interface EventStoreFactoryInterface
{
    /** @param array<string, mixed> $options */
    public function build(ContentRepositoryId $contentRepositoryId, array $options, ClockInterface $clock): EventStoreInterface;
}
