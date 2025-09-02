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

    private static function nodeTypeFromTtContentTca(mixed $value)
    {
        global $TCA;
        $nodeTypeDef = [
            'superTypes' => [
                'TYPO3:Content' => true
            ],
            'properties' => []
        ];
        foreach ($TCA['tt_content']['columns'] as $column => $columnDef) {
            $nodeTypeDef['properties'][$column] = [
                // TODO: string?? or more type safe??
                'type' => 'string'
            ];
        }

        return $nodeTypeDef;
    }

    private static function nodeTypeFromPageTca(mixed $value)
    {
        global $TCA;
        $nodeTypeDef = [
            'superTypes' => [
                'TYPO3:Document' => true
            ],
            'properties' => []
        ];
        foreach ($TCA['pages']['columns'] as $column => $columnDef) {
            $nodeTypeDef['properties'][$column] = [
                // TODO: string?? or more type safe??
                'type' => 'string'
            ];
        }

        return $nodeTypeDef;
    }

    /** @param array<string, mixed> $options */
    public function build(ContentRepositoryId $contentRepositoryId, array $options): NodeTypeManager
    {
        return NodeTypeManager::createFromArrayConfigurationLoader(
            function () {
                $nodeTypes = [
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
                    'TYPO3:Content' => [
                        'abstract' => true,
                    ],
                ];

                global $TCA;
                foreach ($TCA['tt_content']['columns']['CType']['config']['items'] as $itemDef) {
                    $nodeTypes['TYPO3:Content.' . $itemDef['value']] = self::nodeTypeFromTtContentTca($itemDef['value']);
                }

                foreach ($TCA['pages']['columns']['doktype']['config']['items'] as $itemDef) {
                    $nodeTypes['TYPO3:Document.' . $itemDef['value']] = self::nodeTypeFromPageTca($itemDef['value']);
                }

                return $nodeTypes;
            }
        );
    }
}
