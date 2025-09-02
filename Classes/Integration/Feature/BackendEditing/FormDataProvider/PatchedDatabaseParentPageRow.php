<?php

namespace Sandstorm\ContentrepositoryTypo3\Integration\Feature\BackendEditing\FormDataProvider;

use TYPO3\CMS\Backend\Form\FormDataProvider\DatabaseParentPageRow;

class PatchedDatabaseParentPageRow extends DatabaseParentPageRow
{
    use OverriddenFormLogicTrait;
}