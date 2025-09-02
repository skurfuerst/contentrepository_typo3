<?php

namespace Sandstorm\ContentrepositoryTypo3\Integration\Feature\BackendPageLayout;

use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Core\Projection\ContentGraph\ContentSubgraphInterface;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Sandstorm\ContentrepositoryTypo3\Integration\Feature\ContentModule\PatchedContentFetcher;
use Sandstorm\ContentrepositoryTypo3\Integration\Feature\DataHandler\ProcessDatamapHook;
use Sandstorm\ContentrepositoryTypo3\Integration\Feature\NodeIdHandling\NodeIdGenerator;
use Sandstorm\ContentrepositoryTypo3\Integration\Feature\PageTreeDisplay\PatchedPageTreeRepository;
use Sandstorm\ContentrepositoryTypo3\Registry\ContentRepositoryRegistry;
use TYPO3\CMS\Backend\Utility\BackendUtility;

class PatchedBackendUtility
{
    private \Neos\ContentRepository\Core\ContentRepository $contentRepository;
    private ContentSubgraphInterface $subgraph;

    public function __construct(ContentRepositoryRegistry $contentRepositoryRegistry)
    {
        $this->contentRepository = $contentRepositoryRegistry->get(
            ContentRepositoryId::fromString('default')
        );
        $this->subgraph = $this->contentRepository->getContentSubgraph(
            WorkspaceName::forLive(),
            DimensionSpacePoint::createWithoutDimensions()
        );
    }


    public function BEgetRootLine($params) {
        $uid = $params[0];

        $nodeAggregateId = NodeIdGenerator::fromNumericTypo3Id($uid);

        $rootline = [];
        $node = $this->subgraph->findNodeById($nodeAggregateId);
        do {
            if ($node === null) {
                break;
            }
            $parentNode = $this->subgraph->findParentNode($node->aggregateId);
            if ($parentNode === null) {
                break;
            }
            $rootline[] = PatchedPageTreeRepository::buildTypo3PageArrayForNode($node, $parentNode->aggregateId, $this->contentRepository->getNodeTypeManager());;
            $node = $parentNode;
        } while (true);

        if (count($rootline) === 0) {
            return [
                [
                    'uid' => $uid,
                ] // add a fake rootline element -> WORKAROUND FOR TYPO3\CMS\Core\Page\PageLayoutResolver::getLayoutIdentifierForPage(): Argument #1 ($page) must be of type array, bool given, called in /var/www/html/vendor/typo3/cms-backend/Classes/View/BackendLayoutView.php on line 158
            ];
        }

        return array_reverse($rootline);
    }

    public function getRecord($params) {
        $orig = $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['BackendUtility_UNSAFE']['getRecord'];
        unset($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['BackendUtility_UNSAFE']['getRecord']);

        try {
            $table = $params[0];
            $uid = $params[1];

            if ($table === 'tt_content') {
                if (ProcessDatamapHook::isNew($uid)) {
                    return [];
                }
                $node = $this->subgraph->findNodeById(NodeIdGenerator::fromNumericTypo3Id($uid));
                $parentNode = $this->subgraph->findParentNode(NodeIdGenerator::fromNumericTypo3Id($uid));

                return PatchedContentFetcher::buildTypo3TtContentArrayForNode($node, $parentNode?->aggregateId, $this->contentRepository->getNodeTypeManager());
            }

            if ($table === 'pages') {
                if (ProcessDatamapHook::isNew($uid)) {
                    return [];
                }
                $node = $this->subgraph->findNodeById(NodeIdGenerator::fromNumericTypo3Id($uid));

                $parentNode = $this->subgraph->findParentNode(NodeIdGenerator::fromNumericTypo3Id($uid));

                return PatchedPageTreeRepository::buildTypo3PageArrayForNode($node, $parentNode?->aggregateId, $this->contentRepository->getNodeTypeManager());
            }

            return BackendUtility::getRecord(...$params);
        } finally {
            $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['BackendUtility_UNSAFE']['getRecord'] = $orig;
        }
    }
}