<?php

use Sandstorm\ContentrepositoryTypo3\Integration\Feature\BackendEditing\FormDataProvider\PatchedDatabaseEditRow;
use Sandstorm\ContentrepositoryTypo3\Integration\Feature\BackendEditing\FormDataProvider\PatchedDatabaseParentPageRow;
use Sandstorm\ContentrepositoryTypo3\Integration\Feature\BackendPageLayout\PatchedBackendUtility;
use Sandstorm\ContentrepositoryTypo3\Integration\Feature\ContentModule\PatchedContentFetcher;
use Sandstorm\ContentrepositoryTypo3\Integration\Feature\DataHandler\ProcessDatamapHook;
use Sandstorm\ContentrepositoryTypo3\Integration\Feature\PageTreeDisplay\PatchedPageTreeRepository;
use TYPO3\CMS\Backend\Form\FormDataProvider\DatabaseEditRow;
use TYPO3\CMS\Backend\Form\FormDataProvider\DatabaseParentPageRow;
use TYPO3\CMS\Backend\Tree\Repository\PageTreeRepository;
use TYPO3\CMS\Backend\View\BackendLayout\ContentFetcher;

$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][PageTreeRepository::class]['className'] = PatchedPageTreeRepository::class;
$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][ContentFetcher::class]['className'] = PatchedContentFetcher::class;
$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][DatabaseEditRow::class]['className'] = PatchedDatabaseEditRow::class;
$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][DatabaseParentPageRow::class]['className'] = PatchedDatabaseParentPageRow::class;

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['BackendUtility_UNSAFE']['BEgetRootline'] = PatchedBackendUtility::class . '->BEgetRootline';
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['BackendUtility_UNSAFE']['getRecord'] = PatchedBackendUtility::class . '->getRecord';

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][] = ProcessDatamapHook::class;
