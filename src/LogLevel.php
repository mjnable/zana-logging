<?php

declare(strict_types=1);

namespace Zana\Logging;

use Psr\Log\LogLevel as PsrLogLevel;

/**
 * The PSR-3 levels, ordered by severity.
 */
enum LogLevel: string
{
    case Debug = PsrLogLevel::DEBUG;
    case Info = PsrLogLevel::INFO;
    case Notice = PsrLogLevel::NOTICE;
    case Warning = PsrLogLevel::WARNING;
    case Error = PsrLogLevel::ERROR;
    case Critical = PsrLogLevel::CRITICAL;
    case Alert = PsrLogLevel::ALERT;
    case Emergency = PsrLogLevel::EMERGENCY;

    /**
     * @throws InvalidLogLevelException For a level PSR-3 does not define
     */
    public static function fromName(mixed $level): self
    {
        if ($level instanceof self) {
            return $level;
        }

        $case = is_string($level) ? self::tryFrom(strtolower($level)) : null;

        return $case ?? throw new InvalidLogLevelException(
            'Unknown log level: ' . (is_scalar($level) ? var_export($level, true) : get_debug_type($level)) . '.'
        );
    }

    public function severity(): int
    {
        return match ($this) {
            self::Debug => 100,
            self::Info => 200,
            self::Notice => 250,
            self::Warning => 300,
            self::Error => 400,
            self::Critical => 500,
            self::Alert => 550,
            self::Emergency => 600,
        };
    }

    public function isAtLeast(self $minimum): bool
    {
        return $this->severity() >= $minimum->severity();
    }
}
