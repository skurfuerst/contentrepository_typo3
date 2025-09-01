<?php

declare(strict_types=1);

namespace Sandstorm\ContentrepositoryTypo3\Registry;

use Neos\ContentGraph\DoctrineDbalAdapter\DoctrineDbalContentGraphProjectionFactory;
use Neos\ContentRepository\Core\ContentRepository;
use Neos\ContentRepository\Core\Dimension\ContentDimensionSourceInterface;
use Neos\ContentRepository\Core\Factory\AuthProviderFactoryInterface;
use Neos\ContentRepository\Core\Factory\CommandHookFactoryInterface;
use Neos\ContentRepository\Core\Factory\CommandHooksFactory;
use Neos\ContentRepository\Core\Factory\ContentRepositoryFactory;
use Neos\ContentRepository\Core\Factory\ContentRepositoryServiceFactoryInterface;
use Neos\ContentRepository\Core\Factory\ContentRepositoryServiceInterface;
use Neos\ContentRepository\Core\Factory\ContentRepositorySubscriberFactories;
use Neos\ContentRepository\Core\Factory\ProjectionSubscriberFactory;
use Neos\ContentRepository\Core\Infrastructure\Property\Normalizer\ArrayNormalizer;
use Neos\ContentRepository\Core\Infrastructure\Property\Normalizer\CollectionTypeDenormalizer;
use Neos\ContentRepository\Core\Infrastructure\Property\Normalizer\ScalarNormalizer;
use Neos\ContentRepository\Core\Infrastructure\Property\Normalizer\UriNormalizer;
use Neos\ContentRepository\Core\Infrastructure\Property\Normalizer\ValueObjectArrayDenormalizer;
use Neos\ContentRepository\Core\Infrastructure\Property\Normalizer\ValueObjectBoolDenormalizer;
use Neos\ContentRepository\Core\Infrastructure\Property\Normalizer\ValueObjectFloatDenormalizer;
use Neos\ContentRepository\Core\Infrastructure\Property\Normalizer\ValueObjectIntDenormalizer;
use Neos\ContentRepository\Core\Infrastructure\Property\Normalizer\ValueObjectStringDenormalizer;
use Neos\ContentRepository\Core\NodeType\NodeTypeManager;
use Neos\ContentRepository\Core\Projection\CatchUpHook\CatchUpHookFactories;
use Neos\ContentRepository\Core\Projection\CatchUpHook\CatchUpHookFactoryInterface;
use Neos\ContentRepository\Core\Projection\ContentGraph\ContentGraphProjectionFactoryInterface;
use Neos\ContentRepository\Core\Projection\ContentGraph\ContentGraphReadModelInterface;
use Neos\ContentRepository\Core\Projection\ContentGraph\ContentSubgraphInterface;
use Neos\ContentRepository\Core\Projection\ContentGraph\Node;
use Neos\ContentRepository\Core\Projection\ProjectionFactoryInterface;
use Neos\ContentRepository\Core\Projection\ProjectionStateInterface;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryIds;
use Neos\ContentRepository\Core\Subscription\Store\SubscriptionStoreInterface;
use Neos\ContentRepository\Core\Subscription\SubscriptionId;
use Sandstorm\ContentrepositoryTypo3\Registry\Exception\ContentRepositoryNotFoundException;
use Sandstorm\ContentrepositoryTypo3\Registry\Factory\AuthProvider\StaticAuthProviderFactory;
use Sandstorm\ContentrepositoryTypo3\Registry\Factory\Clock\ClockFactoryInterface;
use Sandstorm\ContentrepositoryTypo3\Registry\Factory\Clock\SystemClockFactory;
use Sandstorm\ContentrepositoryTypo3\Registry\Factory\ContentDimensionSource\ConfigurationBasedContentDimensionSourceFactory;
use Sandstorm\ContentrepositoryTypo3\Registry\Factory\ContentDimensionSource\ContentDimensionSourceFactoryInterface;
use Sandstorm\ContentrepositoryTypo3\Registry\Factory\ContentGraphProjectionFactoryAdapter;
use Sandstorm\ContentrepositoryTypo3\Registry\Factory\EventStore\DoctrineEventStoreFactory;
use Sandstorm\ContentrepositoryTypo3\Registry\Factory\EventStore\EventStoreFactoryInterface;
use Sandstorm\ContentrepositoryTypo3\Registry\Factory\NodeTypeManager\DefaultNodeTypeManagerFactory;
use Sandstorm\ContentrepositoryTypo3\Registry\Factory\NodeTypeManager\NodeTypeManagerFactoryInterface;
use Sandstorm\ContentrepositoryTypo3\Registry\Factory\SubscriptionStore\SubscriptionStoreFactory;
use Sandstorm\ContentrepositoryTypo3\Registry\Factory\SubscriptionStore\SubscriptionStoreFactoryInterface;
use Sandstorm\ContentrepositoryTypo3\Registry\SubgraphCachingInMemory\FlushSubgraphCachePoolCatchUpHookFactory;
use Sandstorm\ContentrepositoryTypo3\Registry\SubgraphCachingInMemory\SubgraphCachePool;
use Neos\EventStore\EventStoreInterface;
use Neos\Utility\Arrays;
use Neos\Utility\PositionalArraySorter;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Sandstorm\ContentrepositoryTypo3\Registry\Exception\InvalidConfigurationException;
use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Serializer;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\SingletonInterface;

/**
 * @api
 */
final class ContentRepositoryRegistry implements SingletonInterface
{
    /**
     * @var array<string, ContentRepositoryFactory>
     */
    private array $factoryInstances = [];

    /**
     * @var array<string, mixed>
     */
    private array $settings;

    public function __construct(
        private readonly LoggerInterface        $logger,
        private readonly ContainerInterface     $container,
        private readonly SubgraphCachePool      $subgraphCachePool,
        private readonly ExtensionConfiguration $extensionConfiguration
    )
    {
        $this->initializeSettings();
    }

    private function initializeSettings(): void
    {
        // In TYPO3 v13, configuration is typically loaded from extension configuration
        // or site configuration. This is a simplified example.
        $this->settings = []; // TODO: FIX ME LATER  $this->extensionConfiguration->get('content_repository_registry') ?? [];

        // Fallback to default configuration if not set
        if (empty($this->settings)) {
            $this->settings = $this->getDefaultSettings();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function getDefaultSettings(): array
    {
        return [
            'contentRepositories' => [
                'default' => [
                    'preset' => 'default',
                ]
            ],
            'presets' => [
                'default' => [

                    'eventStore' => [
                        'factoryObjectName' => DoctrineEventStoreFactory::class
                    ],

                    'nodeTypeManager' => [
                        'factoryObjectName' => DefaultNodeTypeManagerFactory::class
                    ],

                    'contentDimensionSource' => [
                        'factoryObjectName' => ConfigurationBasedContentDimensionSourceFactory::class
                    ],

                    'authProvider' => [
                        'factoryObjectName' => StaticAuthProviderFactory::class
                    ],

                    'clock' => [
                        'factoryObjectName' => SystemClockFactory::class
                    ],

                    'subscriptionStore' => [
                        'factoryObjectName' => SubscriptionStoreFactory::class
                    ],

                    'propertyConverters' => [
                        'DateTimeNormalizer' => [
                            'className' => DateTimeNormalizer::class
                        ],
                        'ScalarNormalizer' => [
                            'className' => ScalarNormalizer::class
                        ],
                        'EnumNormalizer' => [
                            'className' => BackedEnumNormalizer::class
                        ],
                        'ArrayNormalizer' => [
                            'className' => ArrayNormalizer::class
                        ],
                        'UriNormalizer' => [
                            'className' => UriNormalizer::class
                        ],
                        'ValueObjectArrayDenormalizer' => [
                            'className' => ValueObjectArrayDenormalizer::class
                        ],
                        'ValueObjectBoolDenormalizer' => [
                            'className' => ValueObjectBoolDenormalizer::class
                        ],
                        'ValueObjectFloatDenormalizer' => [
                            'className' => ValueObjectFloatDenormalizer::class
                        ],
                        'ValueObjectIntDenormalizer' => [
                            'className' => ValueObjectIntDenormalizer::class
                        ],
                        'ValueObjectStringDenormalizer' => [
                            'className' => ValueObjectStringDenormalizer::class
                        ],
                        // WE SKIP (flow specific):
                        // 'DoctrinePersistentObjectNormalizer' => [
                        //    'className' => 'Neos\ContentRepositoryRegistry\Infrastructure\Property\Normalizer\DoctrinePersistentObjectNormalizer'
                        //],
                        'CollectionTypeDenormalizer' => [
                            'className' => CollectionTypeDenormalizer::class
                        ],
                        // WE SKIP (flow specific):
                        // 'ProxyAwareObjectNormalizer' => [
                        //     'className' => 'Neos\ContentRepositoryRegistry\Infrastructure\Property\Normalizer\ProxyAwareObjectNormalizer'
                        //]
                    ],

                    'contentGraphProjection' => [
                        // NOTE: This introduces a soft-dependency to the neos/contentgraph-doctrinedbaladapter package, but it can be overridden when a different adapter is used
                        'factoryObjectName' => ContentGraphProjectionFactoryAdapter::class,

                        'catchUpHooks' => [
                            'Neos.ContentRepositoryRegistry:FlushSubgraphCachePool' => [
                                'factoryObjectName' => FlushSubgraphCachePoolCatchUpHookFactory::class
                            ]
                        ]
                    ]

                    // additional projections:
                    //
                    // 'projections' => [
                    //     'My.Package:SomeProjection' => [ // just a name
                    //         'factoryObjectName' => 'My\Package\Projection\SomeProjectionFactory',
                    //         'options' => [],
                    //         'catchUpHooks' => []
                    //     ]
                    // ],

                    // Command Hooks
                    //
                    // 'commandHooks' => [
                    //     'My.Package:SomeCommandHook' => [ // just a name
                    //         'factoryObjectName' => 'My\Package\CommandHook\SomeCommandHookFactory'
                    //     ]
                    // ]
                ]
            ]
        ];
    }

    /**
     * This is the main entry point for TYPO3 installations to fetch a content repository.
     * A content repository is not a singleton and must be fetched by its identifier.
     *
     * To get a hold of a content repository identifier, it has to be passed along.
     *
     * For TYPO3 web requests, the current content repository can be inferred by the site configuration.
     * For CLI applications, it's a necessity to specify the content repository as argument from the outside,
     * generally via `--content-repository default`
     *
     * The content repository identifier should never be hard-coded without being aware of its implications.
     *
     * Hint: in case you are already in a service that is scoped to a content repository or a projection catchup hook,
     * the content repository will likely be already available via e.g. the service factory.
     *
     * @throws ContentRepositoryNotFoundException | InvalidConfigurationException
     */
    public function get(ContentRepositoryId $contentRepositoryId): ContentRepository
    {
        return $this->getFactory($contentRepositoryId)->getOrBuild();
    }

    public function getContentRepositoryIds(): ContentRepositoryIds
    {
        /** @var array<string> $contentRepositoryIds */
        $contentRepositoryIds = array_keys($this->settings['contentRepositories'] ?? []);
        return ContentRepositoryIds::fromArray($contentRepositoryIds);
    }

    public function subgraphForNode(Node $node): ContentSubgraphInterface
    {
        $contentRepository = $this->get($node->contentRepositoryId);

        return $this->subgraphCachePool->getContentSubgraph(
            $contentRepository,
            $node->workspaceName,
            $node->dimensionSpacePoint,
            $node->visibilityConstraints
        );
    }

    /**
     * Access content repository services.
     *
     * The services are a low level extension mechanism and only few are part of the public API.
     *
     * @param ContentRepositoryId $contentRepositoryId
     * @param ContentRepositoryServiceFactoryInterface<T> $contentRepositoryServiceFactory
     * @return ContentRepositoryServiceInterface
     * @throws ContentRepositoryNotFoundException | InvalidConfigurationException
     * @template T of ContentRepositoryServiceInterface
     */
    public function buildService(ContentRepositoryId $contentRepositoryId, ContentRepositoryServiceFactoryInterface $contentRepositoryServiceFactory): ContentRepositoryServiceInterface
    {
        return $this->getFactory($contentRepositoryId)->buildService($contentRepositoryServiceFactory);
    }

    /**
     * @internal for test cases only
     */
    public function resetFactoryInstance(ContentRepositoryId $contentRepositoryId): void
    {
        if (array_key_exists($contentRepositoryId->value, $this->factoryInstances)) {
            unset($this->factoryInstances[$contentRepositoryId->value]);
        }
    }

    /**
     * @throws ContentRepositoryNotFoundException | InvalidConfigurationException
     */
    private function getFactory(
        ContentRepositoryId $contentRepositoryId
    ): ContentRepositoryFactory
    {
        // This cache is CRUCIAL, because it ensures that the same CR always deals with the same objects internally, even if multiple services
        // are called on the same CR.
        if (!array_key_exists($contentRepositoryId->value, $this->factoryInstances)) {
            $this->factoryInstances[$contentRepositoryId->value] = $this->buildFactory($contentRepositoryId);
        }
        return $this->factoryInstances[$contentRepositoryId->value];
    }

    /**
     * @throws ContentRepositoryNotFoundException | InvalidConfigurationException
     */
    private function buildFactory(ContentRepositoryId $contentRepositoryId): ContentRepositoryFactory
    {
        if (!is_array($this->settings['contentRepositories'] ?? null)) {
            throw InvalidConfigurationException::fromMessage('No Content Repositories are configured');
        }

        if (!isset($this->settings['contentRepositories'][$contentRepositoryId->value]) || !is_array($this->settings['contentRepositories'][$contentRepositoryId->value])) {
            throw ContentRepositoryNotFoundException::notConfigured($contentRepositoryId);
        }
        $contentRepositorySettings = $this->settings['contentRepositories'][$contentRepositoryId->value];
        if (isset($contentRepositorySettings['preset'])) {
            is_string($contentRepositorySettings['preset']) || throw InvalidConfigurationException::fromMessage('Invalid "preset" configuration for Content Repository "%s". Expected string, got: %s', $contentRepositoryId->value, get_debug_type($contentRepositorySettings['preset']));
            if (!isset($this->settings['presets'][$contentRepositorySettings['preset']]) || !is_array($this->settings['presets'][$contentRepositorySettings['preset']])) {
                throw InvalidConfigurationException::fromMessage('Content Repository settings "%s" refer to a preset "%s", but there are not presets configured', $contentRepositoryId->value, $contentRepositorySettings['preset']);
            }
            $contentRepositorySettings = Arrays::arrayMergeRecursiveOverrule($this->settings['presets'][$contentRepositorySettings['preset']], $contentRepositorySettings);
            unset($contentRepositorySettings['preset']);
        }
        try {
            /** @var CatchUpHookFactoryInterface<ContentGraphReadModelInterface>|null $contentGraphCatchUpHookFactory */
            $contentGraphCatchUpHookFactory = $this->buildCatchUpHookFactory($contentRepositoryId, 'contentGraph', $contentRepositorySettings['contentGraphProjection']);
            $clock = $this->buildClock($contentRepositoryId, $contentRepositorySettings);
            return new ContentRepositoryFactory(
                $contentRepositoryId,
                $this->buildEventStore($contentRepositoryId, $contentRepositorySettings, $clock),
                $this->buildNodeTypeManager($contentRepositoryId, $contentRepositorySettings),
                $this->buildContentDimensionSource($contentRepositoryId, $contentRepositorySettings),
                $this->buildPropertySerializer($contentRepositoryId, $contentRepositorySettings),
                $this->buildAuthProviderFactory($contentRepositoryId, $contentRepositorySettings),
                $clock,
                $this->buildSubscriptionStore($contentRepositoryId, $clock, $contentRepositorySettings),
                $this->buildContentGraphProjectionFactory($contentRepositoryId, $contentRepositorySettings),
                $contentGraphCatchUpHookFactory,
                $this->buildCommandHooksFactory($contentRepositoryId, $contentRepositorySettings),
                $this->buildAdditionalSubscribersFactories($contentRepositoryId, $contentRepositorySettings),
                $this->logger,
            );
        } catch (\Exception $exception) {
            throw InvalidConfigurationException::fromException($contentRepositoryId, $exception);
        }
    }

    /** @param array<string, mixed> $contentRepositorySettings */
    private function buildEventStore(ContentRepositoryId $contentRepositoryId, array $contentRepositorySettings, ClockInterface $clock): EventStoreInterface
    {
        isset($contentRepositorySettings['eventStore']['factoryObjectName']) || throw InvalidConfigurationException::fromMessage('Content repository "%s" does not have eventStore.factoryObjectName configured.', $contentRepositoryId->value);
        $eventStoreFactory = $this->container->get($contentRepositorySettings['eventStore']['factoryObjectName']);
        if (!$eventStoreFactory instanceof EventStoreFactoryInterface) {
            throw InvalidConfigurationException::fromMessage('eventStore.factoryObjectName for content repository "%s" is not an instance of %s but %s.', $contentRepositoryId->value, EventStoreFactoryInterface::class, get_debug_type($eventStoreFactory));
        }
        return $eventStoreFactory->build($contentRepositoryId, $contentRepositorySettings['eventStore']['options'] ?? [], $clock);
    }

    /** @param array<string, mixed> $contentRepositorySettings */
    private function buildNodeTypeManager(ContentRepositoryId $contentRepositoryId, array $contentRepositorySettings): NodeTypeManager
    {
        isset($contentRepositorySettings['nodeTypeManager']['factoryObjectName']) || throw InvalidConfigurationException::fromMessage('Content repository "%s" does not have nodeTypeManager.factoryObjectName configured.', $contentRepositoryId->value);
        $nodeTypeManagerFactory = $this->container->get($contentRepositorySettings['nodeTypeManager']['factoryObjectName']);
        if (!$nodeTypeManagerFactory instanceof NodeTypeManagerFactoryInterface) {
            throw InvalidConfigurationException::fromMessage('nodeTypeManager.factoryObjectName for content repository "%s" is not an instance of %s but %s.', $contentRepositoryId->value, NodeTypeManagerFactoryInterface::class, get_debug_type($nodeTypeManagerFactory));
        }
        return $nodeTypeManagerFactory->build($contentRepositoryId, $contentRepositorySettings['nodeTypeManager']['options'] ?? []);
    }

    /** @param array<string, mixed> $contentRepositorySettings */
    private function buildContentDimensionSource(ContentRepositoryId $contentRepositoryId, array $contentRepositorySettings): ContentDimensionSourceInterface
    {
        isset($contentRepositorySettings['contentDimensionSource']['factoryObjectName']) || throw InvalidConfigurationException::fromMessage('Content repository "%s" does not have contentDimensionSource.factoryObjectName configured.', $contentRepositoryId->value);
        $contentDimensionSourceFactory = $this->container->get($contentRepositorySettings['contentDimensionSource']['factoryObjectName']);
        if (!$contentDimensionSourceFactory instanceof ContentDimensionSourceFactoryInterface) {
            throw InvalidConfigurationException::fromMessage('contentDimensionSource.factoryObjectName for content repository "%s" is not an instance of %s but %s.', $contentRepositoryId->value, NodeTypeManagerFactoryInterface::class, get_debug_type($contentDimensionSourceFactory));
        }
        // Note: contentDimensions can be specified on the top-level for easier use.
        // They can still be overridden in the specific "contentDimensionSource" options
        $options = $contentRepositorySettings['contentDimensionSource']['options'] ?? [];
        if (isset($contentRepositorySettings['contentDimensions'])) {
            $options['contentDimensions'] = Arrays::arrayMergeRecursiveOverrule($contentRepositorySettings['contentDimensions'], $options['contentDimensions'] ?? []);
        }
        return $contentDimensionSourceFactory->build($contentRepositoryId, $options);
    }

    /** @param array<string, mixed> $contentRepositorySettings */
    private function buildPropertySerializer(ContentRepositoryId $contentRepositoryId, array $contentRepositorySettings): Serializer
    {
        (isset($contentRepositorySettings['propertyConverters']) && is_array($contentRepositorySettings['propertyConverters'])) || throw InvalidConfigurationException::fromMessage('Content repository "%s" does not have propertyConverters configured, or the value is no array.', $contentRepositoryId->value);
        $propertyConvertersConfiguration = (new PositionalArraySorter($contentRepositorySettings['propertyConverters']))->toArray();

        $normalizers = [];
        foreach ($propertyConvertersConfiguration as $propertyConverterConfiguration) {
            $normalizer = new $propertyConverterConfiguration['className']();
            if (!$normalizer instanceof NormalizerInterface && !$normalizer instanceof DenormalizerInterface) {
                throw InvalidConfigurationException::fromMessage('Serializers can only be created of %s and %s, %s given', NormalizerInterface::class, DenormalizerInterface::class, get_debug_type($normalizer));
            }
            $normalizers[] = $normalizer;
        }
        return new Serializer($normalizers);
    }

    /** @param array<string, mixed> $contentRepositorySettings */
    private function buildContentGraphProjectionFactory(ContentRepositoryId $contentRepositoryId, array $contentRepositorySettings): ContentGraphProjectionFactoryInterface
    {
        if (!isset($contentRepositorySettings['contentGraphProjection']['factoryObjectName'])) {
            throw InvalidConfigurationException::fromMessage('Content repository "%s" does not have the contentGraphProjection.factoryObjectName configured.', $contentRepositoryId->value);
        }

        $contentGraphProjectionFactory = $this->container->get($contentRepositorySettings['contentGraphProjection']['factoryObjectName']);
        if (!$contentGraphProjectionFactory instanceof ContentGraphProjectionFactoryInterface) {
            throw InvalidConfigurationException::fromMessage('Projection factory object name of contentGraphProjection (content repository "%s") is not an instance of %s but %s.', $contentRepositoryId->value, ContentGraphProjectionFactoryInterface::class, get_debug_type($contentGraphProjectionFactory));
        }
        return $contentGraphProjectionFactory;
    }

    /**
     * @param array<string, mixed> $projectionOptions
     * @return CatchUpHookFactoryInterface<ProjectionStateInterface>|null
     */
    private function buildCatchUpHookFactory(ContentRepositoryId $contentRepositoryId, string $projectionName, array $projectionOptions): ?CatchUpHookFactoryInterface
    {
        if (!isset($projectionOptions['catchUpHooks'])) {
            return null;
        }
        $catchUpHookFactories = CatchUpHookFactories::create();
        foreach ($projectionOptions['catchUpHooks'] as $catchUpHookName => $catchUpHookOptions) {
            if ($catchUpHookOptions === null) {
                // Allow catch up hooks to be disabled by setting their configuration to `null`
                continue;
            }
            $catchUpHookFactory = $this->container->get($catchUpHookOptions['factoryObjectName']);
            if (!$catchUpHookFactory instanceof CatchUpHookFactoryInterface) {
                throw InvalidConfigurationException::fromMessage('CatchUpHook factory object name for hook "%s" in projection "%s" (content repository "%s") is not an instance of %s but %s', $catchUpHookName, $projectionName, $contentRepositoryId->value, CatchUpHookFactoryInterface::class, get_debug_type($catchUpHookFactory));
            }
            $catchUpHookFactories = $catchUpHookFactories->with($catchUpHookFactory);
        }
        if ($catchUpHookFactories->isEmpty()) {
            return null;
        }
        return $catchUpHookFactories;
    }

    /** @param array<string, mixed> $contentRepositorySettings */
    private function buildCommandHooksFactory(ContentRepositoryId $contentRepositoryId, array $contentRepositorySettings): CommandHooksFactory
    {
        $commandHooksSettings = $contentRepositorySettings['commandHooks'] ?? [];
        if (!is_array($commandHooksSettings)) {
            throw InvalidConfigurationException::fromMessage('Content repository "%s" does not have the "commandHooks" configured properly. Expected array, got %s.', $contentRepositoryId->value, get_debug_type($commandHooksSettings));
        }
        $commandHookFactories = [];
        foreach ((new PositionalArraySorter($commandHooksSettings))->toArray() as $name => $commandHookSettings) {
            // Allow to unset/disable command hooks
            if ($commandHookSettings === null) {
                continue;
            }
            $commandHookFactory = $this->container->get($commandHookSettings['factoryObjectName']);
            if (!$commandHookFactory instanceof CommandHookFactoryInterface) {
                throw InvalidConfigurationException::fromMessage('Factory object name for command hook "%s" (content repository "%s") is not an instance of %s but %s.', $name, $contentRepositoryId->value, CommandHookFactoryInterface::class, get_debug_type($commandHookFactory));
            }
            $commandHookFactories[] = $commandHookFactory;
        }
        return new CommandHooksFactory(...$commandHookFactories);
    }

    /** @param array<string, mixed> $contentRepositorySettings */
    private function buildAdditionalSubscribersFactories(ContentRepositoryId $contentRepositoryId, array $contentRepositorySettings): ContentRepositorySubscriberFactories
    {
        if (!is_array($contentRepositorySettings['projections'] ?? [])) {
            throw InvalidConfigurationException::fromMessage('Content repository "%s" expects projections configured as array.', $contentRepositoryId->value);
        }
        /** @var array<ProjectionSubscriberFactory> $projectionSubscriberFactories */
        $projectionSubscriberFactories = [];
        foreach (($contentRepositorySettings['projections'] ?? []) as $projectionName => $projectionOptions) {
            // Allow projections to be disabled by setting their configuration to `null`
            if ($projectionOptions === null) {
                continue;
            }
            if (!is_array($projectionOptions)) {
                throw InvalidConfigurationException::fromMessage('Projection "%s" (content repository "%s") must be configured as array got %s', $projectionName, $contentRepositoryId->value, get_debug_type($projectionOptions));
            }
            $projectionFactory = isset($projectionOptions['factoryObjectName']) ? $this->container->get($projectionOptions['factoryObjectName']) : null;
            if (!$projectionFactory instanceof ProjectionFactoryInterface) {
                throw InvalidConfigurationException::fromMessage('Projection factory object name for projection "%s" (content repository "%s") is not an instance of %s but %s.', $projectionName, $contentRepositoryId->value, ProjectionFactoryInterface::class, get_debug_type($projectionFactory));
            }
            $projectionSubscriberFactories[$projectionName] = new ProjectionSubscriberFactory(
                SubscriptionId::fromString($projectionName),
                $projectionFactory,
                $this->buildCatchUpHookFactory($contentRepositoryId, $projectionName, $projectionOptions),
                $projectionOptions['options'] ?? [],
            );
        }
        return ContentRepositorySubscriberFactories::fromArray($projectionSubscriberFactories);
    }

    /** @param array<string, mixed> $contentRepositorySettings */
    private function buildAuthProviderFactory(ContentRepositoryId $contentRepositoryId, array $contentRepositorySettings): AuthProviderFactoryInterface
    {
        isset($contentRepositorySettings['authProvider']['factoryObjectName']) || throw InvalidConfigurationException::fromMessage('Content repository "%s" does not have authProvider.factoryObjectName configured.', $contentRepositoryId->value);
        $authProviderFactory = $this->container->get($contentRepositorySettings['authProvider']['factoryObjectName']);
        if (!$authProviderFactory instanceof AuthProviderFactoryInterface) {
            throw InvalidConfigurationException::fromMessage('authProvider.factoryObjectName for content repository "%s" is not an instance of %s but %s.', $contentRepositoryId->value, AuthProviderFactoryInterface::class, get_debug_type($authProviderFactory));
        }
        return $authProviderFactory;
    }

    /** @param array<string, mixed> $contentRepositorySettings */
    private function buildClock(ContentRepositoryId $contentRepositoryIdentifier, array $contentRepositorySettings): ClockInterface
    {
        isset($contentRepositorySettings['clock']['factoryObjectName']) || throw InvalidConfigurationException::fromMessage('Content repository "%s" does not have clock.factoryObjectName configured.', $contentRepositoryIdentifier->value);
        $clockFactory = $this->container->get($contentRepositorySettings['clock']['factoryObjectName']);
        if (!$clockFactory instanceof ClockFactoryInterface) {
            throw InvalidConfigurationException::fromMessage('clock.factoryObjectName for content repository "%s" is not an instance of %s but %s.', $contentRepositoryIdentifier->value, ClockFactoryInterface::class, get_debug_type($clockFactory));
        }
        return $clockFactory->build($contentRepositoryIdentifier, $contentRepositorySettings['clock']['options'] ?? []);
    }

    /** @param array<string, mixed> $contentRepositorySettings */
    private function buildSubscriptionStore(ContentRepositoryId $contentRepositoryId, ClockInterface $clock, array $contentRepositorySettings): SubscriptionStoreInterface
    {
        isset($contentRepositorySettings['subscriptionStore']['factoryObjectName']) || throw InvalidConfigurationException::fromMessage('Content repository "%s" does not have subscriptionStore.factoryObjectName configured.', $contentRepositoryId->value);
        $subscriptionStoreFactory = $this->container->get($contentRepositorySettings['subscriptionStore']['factoryObjectName']);
        if (!$subscriptionStoreFactory instanceof SubscriptionStoreFactoryInterface) {
            throw InvalidConfigurationException::fromMessage('subscriptionStore.factoryObjectName for content repository "%s" is not an instance of %s but %s.', $contentRepositoryId->value, SubscriptionStoreFactoryInterface::class, get_debug_type($subscriptionStoreFactory));
        }
        return $subscriptionStoreFactory->build($contentRepositoryId, $clock, $contentRepositorySettings['subscriptionStore']['options'] ?? []);
    }
}