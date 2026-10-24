<?php

declare(strict_types=1);

namespace Zana\Logging;

/**
 * One log entry, passed through processors to handlers.
 */
final readonly class LogRecord
{
    /**
     * @param string $message The message with its {placeholders} interpolated
     * @param array<string, mixed> $context The original context
     * @param array<string, mixed> $extra Values added by processors
     */
    public function __construct(
        public \DateTimeImmutable $datetime,
        public string $channel,
        public LogLevel $level,
        public string $message,
        public array $context = [],
        public array $extra = [],
    ) {
    }

    /**
     * @param array<string, mixed> $extra
     */
    public function withExtra(array $extra): self
    {
        return new self($this->datetime, $this->channel, $this->level, $this->message, $this->context, [...$this->extra, ...$extra]);
    }
}
