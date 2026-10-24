<?php

declare(strict_types=1);

namespace Zana\Logging;

use Closure;
use Psr\Log\AbstractLogger;
use Zana\Logging\Handler\HandlerInterface;

/**
 * A PSR-3 logger for one channel.
 *
 * Each call creates a LogRecord with an interpolated message, runs the
 * processors in order, and gives the record to every handler that handles
 * its level.
 */
final class Logger extends AbstractLogger
{
    /**
     * @param list<HandlerInterface> $handlers
     * @param list<callable(LogRecord): LogRecord> $processors
     * @param (Closure(): \DateTimeImmutable)|null $clock
     */
    public function __construct(
        private readonly string $channel,
        private readonly array $handlers,
        private readonly array $processors = [],
        private readonly ?Closure $clock = null,
    ) {
    }

    public function channel(): string
    {
        return $this->channel;
    }

    /**
     * @param array<mixed> $context
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $level = LogLevel::fromName($level);

        $handlers = array_filter($this->handlers, static fn (HandlerInterface $h): bool => $h->handles($level));
        if ($handlers === []) {
            return;
        }

        /** @var array<string, mixed> $context */
        $record = new LogRecord(
            $this->clock !== null ? ($this->clock)() : new \DateTimeImmutable(),
            $this->channel,
            $level,
            self::interpolate((string) $message, $context),
            $context,
        );

        foreach ($this->processors as $processor) {
            $record = $processor($record);
        }

        foreach ($handlers as $handler) {
            $handler->handle($record);
        }
    }

    /**
     * Replace {key} placeholders with scalar, null, Stringable or date context values (PSR-3 §1.2).
     *
     * @param array<string, mixed> $context
     */
    public static function interpolate(string $message, array $context): string
    {
        if (!str_contains($message, '{')) {
            return $message;
        }

        $replacements = [];
        foreach ($context as $key => $value) {
            $text = match (true) {
                $value === null => 'null',
                is_bool($value) => $value ? 'true' : 'false',
                is_scalar($value), $value instanceof \Stringable => (string) $value,
                $value instanceof \DateTimeInterface => $value->format(\DateTimeInterface::ATOM),
                default => null,
            };
            if ($text !== null) {
                $replacements['{' . $key . '}'] = $text;
            }
        }

        return strtr($message, $replacements);
    }
}
