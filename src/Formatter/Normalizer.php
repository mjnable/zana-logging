<?php

declare(strict_types=1);

namespace Zana\Logging\Formatter;

/**
 * Makes context values JSON-encodable: exceptions, dates, objects, resources.
 *
 * @internal
 */
final class Normalizer
{
    private const MAX_DEPTH = 8;

    public static function normalize(mixed $value, int $depth = 0): mixed
    {
        if ($depth > self::MAX_DEPTH) {
            return '[max depth]';
        }

        return match (true) {
            $value === null, is_scalar($value) => $value,
            $value instanceof \Throwable => self::exception($value, $depth),
            $value instanceof \DateTimeInterface => $value->format(\DateTimeInterface::ATOM),
            $value instanceof \JsonSerializable => self::normalize($value->jsonSerialize(), $depth + 1),
            $value instanceof \Stringable => (string) $value,
            $value instanceof \UnitEnum => $value instanceof \BackedEnum ? $value->value : $value->name,
            is_array($value) => array_map(static fn (mixed $v): mixed => self::normalize($v, $depth + 1), $value),
            is_object($value) => '[object ' . $value::class . ']',
            is_resource($value) => '[resource ' . get_resource_type($value) . ']',
            default => '[' . get_debug_type($value) . ']',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private static function exception(\Throwable $exception, int $depth): array
    {
        $data = [
            'class' => $exception::class,
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'file' => $exception->getFile() . ':' . $exception->getLine(),
        ];

        if ($exception->getPrevious() !== null) {
            $data['previous'] = self::normalize($exception->getPrevious(), $depth + 1);
        }

        return $data;
    }
}
