<?php

declare(strict_types=1);

namespace Zana\Logging\Handler;

use Zana\Logging\LogLevel;
use Zana\Logging\LogRecord;

/**
 * Receives log records: writes, stores or forwards them.
 */
interface HandlerInterface
{
    public function handles(LogLevel $level): bool;

    public function handle(LogRecord $record): void;
}
