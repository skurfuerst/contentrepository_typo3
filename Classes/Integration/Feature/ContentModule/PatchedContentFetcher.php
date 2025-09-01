<?php

namespace Sandstorm\ContentrepositoryTypo3\Integration\Feature\ContentModule;

use TYPO3\CMS\Backend\View\BackendLayout\ContentFetcher;

class PatchedContentFetcher extends ContentFetcher
{
    public function getContentRecordsPerColumn(?int $columnNumber = null, ?int $languageId = null): array {
        var_dump($columnNumber, $languageId);
        return [];
    }

}