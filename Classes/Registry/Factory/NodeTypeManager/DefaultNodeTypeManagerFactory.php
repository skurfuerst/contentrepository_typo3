<?php
declare(strict_types=1);
namespace Sandstorm\ContentrepositoryTypo3\Registry\Factory\NodeTypeManager;

use Neos\ContentRepository\Core\NodeType\NodeTypeManager;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Sandstorm\ContentrepositoryTypo3\Registry\Configuration\NodeTypeEnrichmentService;
use Neos\Flow\Configuration\ConfigurationManager;

readonly class DefaultNodeTypeManagerFactory implements NodeTypeManagerFactoryInterface
{
    public function __construct(
    ) {
    }

    /** @param array<string, mixed> $options */
    public function build(ContentRepositoryId $contentRepositoryId, array $options): NodeTypeManager
    {
        return NodeTypeManager::createFromArrayConfigurationLoader(
            function () {
                return [
                     // TODO: NODE TYPES HERE :)
                ];
            }
        );
    }
}
