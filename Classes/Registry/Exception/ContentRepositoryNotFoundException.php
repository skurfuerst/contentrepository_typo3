<?php

declare(strict_types=1);

namespace Sandstorm\ContentrepositoryTypo3\Registry\Exception;

use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;

final class ContentRepositoryNotFoundException extends \InvalidArgumentException
{

    public static function notConfigured(ContentRepositoryId $contentRepositoryId): self
    {
        return new self(sprintf('A content repository with id "%s" is not configured', $contentRepositoryId->value), 1650557155);
    }
}
