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
                     'TYPO3:Sites' => [
                         'superTypes' => [
                             'Neos.ContentRepository:Root' => true
                         ]
                     ],
                    'TYPO3:Site' => [
                        'abstract' => true
                    ],
                    'TYPO3:SiteRootPage' => [
                        'superTypes' => [
                            'TYPO3:Document' => true,
                            'TYPO3:Site' => true
                        ],
                    ],
                    'TYPO3:Page' => [
                        'superTypes' => [
                            'TYPO3:Document' => true
                        ]
                    ],
                    'TYPO3:Document' => [
                        'abstract' => true,
                        'properties' => [
                            'title' => [
                                'type' => 'string'
                            ]
                        ]
                    ],
                ];
            }
        );
    }
}
