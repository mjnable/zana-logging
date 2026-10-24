<?php

declare(strict_types=1);

namespace Zana\Logging\Formatter;

use Zana\Logging\LogRecord;

/**
 * Turns a record into one line of text (without the line break).
 */
interface FormatterInterface
{
    public function format(LogRecord $record): string;
}
