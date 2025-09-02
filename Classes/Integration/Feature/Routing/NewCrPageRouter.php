<?php

namespace Sandstorm\ContentrepositoryTypo3\Integration\Feature\Routing;

use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Core\Projection\ContentGraph\Filter\FindDescendantNodesFilter;
use Neos\ContentRepository\Core\Projection\ContentGraph\Filter\PropertyValue\Criteria\PropertyValueEquals;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepository\Core\SharedModel\Node\PropertyName;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Sandstorm\ContentrepositoryTypo3\Integration\Feature\NodeIdHandling\NodeIdGenerator;
use Sandstorm\ContentrepositoryTypo3\Registry\ContentRepositoryRegistry;
use TYPO3\CMS\Core\Domain\Page;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Routing\RouteNotFoundException;
use TYPO3\CMS\Core\Routing\RouteResultInterface;
use TYPO3\CMS\Core\Routing\RouterInterface;
use TYPO3\CMS\Core\Routing\SiteMatcher;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class NewCrPageRouter implements RouterInterface
{

    private \Neos\ContentRepository\Core\ContentRepository $contentRepository;
    private \Neos\ContentRepository\Core\Projection\ContentGraph\ContentSubgraphInterface $subgraph;

    public function __construct()
    {
        $contentRepositoryRegistry = GeneralUtility::makeInstance(ContentRepositoryRegistry::class);
        $this->contentRepository = $contentRepositoryRegistry->get(
            ContentRepositoryId::fromString('default')
        );
        $this->subgraph = $this->contentRepository->getContentSubgraph(
            WorkspaceName::forLive(),
            DimensionSpacePoint::createWithoutDimensions()
        );
    }

    public function matchRequest(ServerRequestInterface $request, ?RouteResultInterface $previousResult = null): RouteResultInterface
    {
        $urlPath = $previousResult->getTail();
        $urlPathParts = explode('/', $urlPath);

        $site = $request->getAttribute('site');
        assert($site instanceof Site);

        $rootNodeId = NodeIdGenerator::fromNumericTypo3Id($site->getRootPageId());

        $node = $this->subgraph->findNodeById($rootNodeId);

        if (strlen($urlPath) > 0) {
            $childNodes = $this->subgraph->findDescendantNodes($node->aggregateId, FindDescendantNodesFilter::create(
                nodeTypes: 'TYPO3:Document',
                propertyValue: PropertyValueEquals::create(
                    PropertyName::fromString('slug'),
                    '/' . $urlPath,
                    false
                )
            ));
            $node = $childNodes->first();

            if ($node === null) {
                throw new RouteNotFoundException('The requested page does not exist for URI path ' . $urlPathPart, 1557839801);
            }
        }

        $routeArguments = [];

        if ($node === null) {
            throw new RouteNotFoundException('The requested page does not exist for URI path ' . $urlPath, 1557839801);
        }

        return new PageArguments(intval($node->aggregateId->value), 0, $routeArguments, [], []);
    }


    public function generateUri($route, array $parameters = [], string $fragment = '', string $type = self::ABSOLUTE_URL): UriInterface
    {
        if ($route instanceof Page) {
            $route = $route->getPageId();
        }
        $pageIdToLinkTo = NodeIdGenerator::fromNumericTypo3Id($route);

        $siteMatcher = GeneralUtility::makeInstance(SiteMatcher::class);
        $site = $siteMatcher->matchByPageId($route);

        $targetNode = $this->subgraph->findNodeById($pageIdToLinkTo) ;
        return $site->getBase()->withPath($site->getBase()->getPath() . $targetNode->getProperty('slug'));
    }
}