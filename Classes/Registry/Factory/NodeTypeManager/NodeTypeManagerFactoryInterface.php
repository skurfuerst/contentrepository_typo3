<?php
declare(strict_types=1);
namespace Sandstorm\ContentrepositoryTypo3\Registry\Factory\NodeTypeManager;

use Neos\ContentRepository\Core\NodeType\NodeTypeManager;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;

interface NodeTypeManagerFactoryInterface
{
    /** @param array<string, mixed> $options */
    public function build(ContentRepositoryId $contentRepositoryId, array $options): NodeTypeManager;
}
