<?php

declare(strict_types=1);

namespace Zana\Logging\Formatter;

use Zana\Logging\LogRecord;

/**
 * "[2026-10-24T10:00:00+00:00] app.INFO: message {"key":"value"} {"extra":1}"
 *
 * Empty context and extra are omitted.
 */
final class LineFormatter implements FormatterInterface
{
    private const JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR;

    public function format(LogRecord $record): string
    {
        $line = sprintf(
            '[%s] %s.%s: %s',
            $record->datetime->format(\DateTimeInterface::ATOM),
            $record->channel,
            strtoupper($record->level->value),
            str_replace(["\r\n", "\n", "\r"], ' ', $record->message)
        );

        foreach ([$record->context, $record->extra] as $values) {
            if ($values !== []) {
                $line .= ' ' . json_encode(Normalizer::normalize($values), self::JSON_FLAGS);
            }
        }

        return $line;
    }
}
