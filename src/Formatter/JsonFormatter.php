<?php

declare(strict_types=1);

namespace Zana\Logging\Formatter;

use Zana\Logging\LogRecord;

/**
 * One JSON object per record: datetime, channel, level, message, context, extra.
 */
final class JsonFormatter implements FormatterInterface
{
    private const JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR;

    public function format(LogRecord $record): string
    {
        return (string) json_encode([
            'datetime' => $record->datetime->format(\DateTimeInterface::ATOM),
            'channel' => $record->channel,
            'level' => $record->level->value,
            'message' => $record->message,
            'context' => (object) Normalizer::normalize($record->context),
            'extra' => (object) Normalizer::normalize($record->extra),
        ], self::JSON_FLAGS);
    }
}
