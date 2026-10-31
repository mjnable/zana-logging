<?php

declare(strict_types=1);

namespace Zana\Logging;

use Psr\Log\LoggerInterface;
use Zana\Configuration\ConfigurationInterface;
use Zana\Logging\Formatter\FormatterInterface;
use Zana\Logging\Formatter\JsonFormatter;
use Zana\Logging\Formatter\LineFormatter;
use Zana\Logging\Handler\ArrayHandler;
use Zana\Logging\Handler\HandlerInterface;
use Zana\Logging\Handler\NullHandler;
use Zana\Logging\Handler\StreamHandler;
use Zana\Logging\Processor\ContextProcessor;

/**
 * Builds and caches named channels from the "logging" configuration.
 *
 *     'logging' => [
 *         'default' => 'app',
 *         'channels' => [
 *             'app' => ['handler' => 'stream', 'path' => 'var/log/app.log', 'level' => 'info', 'formatter' => 'json'],
 *         ],
 *     ]
 *
 * A file channel can rotate: 'max_size' => '10M' (bytes, or K, M, G) and
 * 'max_files' => 5 (2.6).
 */
final class LogManager
{
    /** @var array<string, Logger> */
    private array $channels = [];

    /** @var array<string, HandlerInterface> */
    private array $handlers = [];

    public function __construct(
        private readonly ConfigurationInterface $config,
        private readonly string $basePath,
        private readonly LogContext $context,
    ) {
    }

    public function defaultChannel(): string
    {
        return $this->config->string('logging.default', 'app');
    }

    /**
     * The files the configured stream channels write to, by channel.
     *
     * @return array<string, string>
     */
    public function files(): array
    {
        $files = [];
        foreach ($this->config->array('logging.channels', []) as $name => $settings) {
            if (!is_array($settings) || ($settings['handler'] ?? 'stream') !== 'stream') {
                continue;
            }
            $path = $this->path((string) ($settings['path'] ?? 'php://stderr'));
            if (!str_starts_with($path, 'php://')) {
                $files[(string) $name] = $path;
            }
        }

        return $files;
    }

    /**
     * @throws \InvalidArgumentException For an unknown channel, handler or formatter
     */
    public function channel(?string $name = null): LoggerInterface
    {
        $name ??= $this->defaultChannel();

        return $this->channels[$name] ??= new Logger(
            $name,
            [$this->handler($name)],
            [new ContextProcessor($this->context)],
        );
    }

    /**
     * The handler of a channel, e.g. to read an array channel's records in tests.
     */
    public function handler(?string $channel = null): HandlerInterface
    {
        $channel ??= $this->defaultChannel();

        return $this->handlers[$channel] ??= $this->createHandler($channel);
    }

    private function createHandler(string $channel): HandlerInterface
    {
        $settings = $this->config->array("logging.channels.{$channel}", []);

        if ($settings === []) {
            if ($channel !== $this->defaultChannel() || $this->config->has('logging.channels')) {
                throw new \InvalidArgumentException("Log channel \"{$channel}\" is not configured.");
            }

            return new StreamHandler('php://stderr', LogLevel::Warning);
        }

        $level = LogLevel::fromName($settings['level'] ?? 'debug');

        return match ($settings['handler'] ?? 'stream') {
            'stream' => new StreamHandler(
                $this->path((string) ($settings['path'] ?? 'php://stderr')),
                $level,
                $this->formatter($settings['formatter'] ?? 'line'),
                isset($settings['max_size']) ? self::bytes($channel, $settings['max_size']) : null,
                (int) ($settings['max_files'] ?? 5),
            ),
            'array' => new ArrayHandler($level),
            'null' => new NullHandler(),
            default => throw new \InvalidArgumentException(sprintf('Log channel "%s" has an unknown handler: %s.', $channel, var_export($settings['handler'], true))),
        };
    }

    private function formatter(mixed $name): FormatterInterface
    {
        return match ($name) {
            'line' => new LineFormatter(),
            'json' => new JsonFormatter(),
            default => throw new \InvalidArgumentException('Unknown log formatter: ' . var_export($name, true) . '.'),
        };
    }

    /**
     * "10M", "500K", "1G" or a number of bytes.
     */
    private static function bytes(string $channel, mixed $size): int
    {
        if (is_int($size) || (is_string($size) && ctype_digit($size))) {
            return (int) $size;
        }
        if (is_string($size) && preg_match('/^(\d+)\s*([KMG])B?$/i', trim($size), $match) === 1) {
            return (int) $match[1] * ['K' => 1024, 'M' => 1024 ** 2, 'G' => 1024 ** 3][strtoupper($match[2])];
        }

        throw new \InvalidArgumentException(sprintf('Log channel "%s" has an invalid max_size: %s. Use bytes, or K, M or G, e.g. "10M".', $channel, var_export($size, true)));
    }

    private function path(string $path): string
    {
        $isAbsolute = str_starts_with($path, 'php://')
            || str_starts_with($path, '/')
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;

        return $isAbsolute ? $path : rtrim($this->basePath, '/') . '/' . $path;
    }
}
