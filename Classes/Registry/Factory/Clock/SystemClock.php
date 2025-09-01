<?php
declare(strict_types=1);
namespace Sandstorm\ContentrepositoryTypo3\Registry\Factory\Clock;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;

/**
 * @internal
 */
final class SystemClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }
}
