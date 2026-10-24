<?php

declare(strict_types=1);

namespace Zana\Logging;

/**
 * Values added to every record by ContextProcessor, for example the
 * correlation id of the request being handled.
 *
 * It is a singleton shared by all channels: whoever sets a value (such as
 * the HTTP kernel) must remove it when the unit of work ends.
 */
final class LogContext
{
    /** @var array<string, scalar|null> */
    private array $values = [];

    public function set(string $key, string|int|float|bool|null $value): void
    {
        $this->values[$key] = $value;
    }

    public function remove(string $key): void
    {
        unset($this->values[$key]);
    }

    /**
     * @return array<string, scalar|null>
     */
    public function all(): array
    {
        return $this->values;
    }

    public function clear(): void
    {
        $this->values = [];
    }
}
