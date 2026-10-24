<?php

declare(strict_types=1);

namespace Zana\Logging\Processor;

use Zana\Logging\LogContext;
use Zana\Logging\LogRecord;

/**
 * Copies the current LogContext values into each record's extra.
 */
final readonly class ContextProcessor
{
    public function __construct(private LogContext $context)
    {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        $values = $this->context->all();

        return $values === [] ? $record : $record->withExtra($values);
    }
}
