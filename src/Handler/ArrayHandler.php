<?php

declare(strict_types=1);

namespace Zana\Logging\Handler;

use Zana\Logging\LogLevel;
use Zana\Logging\LogRecord;

/**
 * Keeps records in memory, for tests.
 */
final class ArrayHandler implements HandlerInterface
{
    /** @var list<LogRecord> */
    private array $records = [];

    public function __construct(private readonly LogLevel $level = LogLevel::Debug)
    {
    }

    public function handles(LogLevel $level): bool
    {
        return $level->isAtLeast($this->level);
    }

    public function handle(LogRecord $record): void
    {
        $this->records[] = $record;
    }

    /**
     * @return list<LogRecord>
     */
    public function records(): array
    {
        return $this->records;
    }

    /**
     * Whether a record of the level has a message containing the text.
     */
    public function hasRecord(LogLevel|string $level, string $messageContains = ''): bool
    {
        $level = LogLevel::fromName($level);

        foreach ($this->records as $record) {
            if ($record->level === $level && str_contains($record->message, $messageContains)) {
                return true;
            }
        }

        return false;
    }

    public function clear(): void
    {
        $this->records = [];
    }
}
