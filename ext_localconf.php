<?php

use Sandstorm\ContentrepositoryTypo3\Integration\Feature\DataHandler\ProcessDatamapHook;

$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\TYPO3\CMS\Backend\Tree\Repository\PageTreeRepository::class]['className'] = \Sandstorm\ContentrepositoryTypo3\Integration\Feature\PageTreeDisplay\PageTreeRepository::class;


$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][] = ProcessDatamapHook::class;
