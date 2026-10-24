<?php

declare(strict_types=1);

namespace Zana\Logging\Handler;

use Zana\Logging\LogLevel;
use Zana\Logging\LogRecord;

/**
 * Discards every record.
 */
final class NullHandler implements HandlerInterface
{
    public function handles(LogLevel $level): bool
    {
        return true;
    }

    public function handle(LogRecord $record): void
    {
    }
}
