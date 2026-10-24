<?php

declare(strict_types=1);

namespace Zana\Logging;

use Psr\Log\InvalidArgumentException;

/**
 * Thrown for a level PSR-3 does not define.
 */
final class InvalidLogLevelException extends InvalidArgumentException
{
}
