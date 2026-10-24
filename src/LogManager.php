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
            'stream' => new StreamHandler($this->path((string) ($settings['path'] ?? 'php://stderr')), $level, $this->formatter($settings['formatter'] ?? 'line')),
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

    private function path(string $path): string
    {
        $isAbsolute = str_starts_with($path, 'php://')
            || str_starts_with($path, '/')
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;

        return $isAbsolute ? $path : rtrim($this->basePath, '/') . '/' . $path;
    }
}
