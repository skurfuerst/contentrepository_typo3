<?php

namespace Sandstorm\ContentrepositoryTypo3\Integration\Feature\BackendPageLayout;

use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Psr\Http\Message\ServerRequestInterface;
use Sandstorm\ContentrepositoryTypo3\Integration\Feature\NodeIdHandling\NodeIdGenerator;
use Sandstorm\ContentrepositoryTypo3\Integration\Feature\PageTreeDisplay\PatchedPageTreeRepository;
use Sandstorm\ContentrepositoryTypo3\Registry\ContentRepositoryRegistry;
use TYPO3\CMS\Backend\Controller\PageLayoutController;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class PageLayoutControllerPatched extends PageLayoutController
{

    protected function initialize(ServerRequestInterface $request): void
    {
        parent::initialize($request);

        $contentRepositoryRegistry = GeneralUtility::makeInstance(ContentRepositoryRegistry::class);
        $contentRepository = $contentRepositoryRegistry->get(ContentRepositoryId::fromString('default'));
        $nodeTypeManager = $contentRepository->getNodeTypeManager();
        $subgraph = $contentRepository->getContentSubgraph(WorkspaceName::forLive(),DimensionSpacePoint::createWithoutDimensions());

        $pageNode = $subgraph->findNodeById(NodeIdGenerator::fromNumericTypo3Id($this->id));
        $parentPageNode = $subgraph->findParentNode(NodeIdGenerator::fromNumericTypo3Id($this->id));

        $this->pageinfo = PatchedPageTreeRepository::buildTypo3PageArrayForNode($pageNode, $parentPageNode->aggregateId, $nodeTypeManager);
    }
}