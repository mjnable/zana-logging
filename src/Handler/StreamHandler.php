<?php

declare(strict_types=1);

namespace Zana\Logging\Handler;

use Zana\Logging\Formatter\FormatterInterface;
use Zana\Logging\Formatter\LineFormatter;
use Zana\Logging\LogLevel;
use Zana\Logging\LogRecord;

/**
 * Writes one formatted line per record to a file path or a stream resource.
 * A file and its directory are created on the first write.
 */
final class StreamHandler implements HandlerInterface
{
    /** @var resource|null */
    private $stream = null;

    private readonly FormatterInterface $formatter;

    /**
     * @param string|resource $target A path, a php:// stream URL, or an open stream
     */
    public function __construct(
        private readonly mixed $target,
        private readonly LogLevel $level = LogLevel::Debug,
        ?FormatterInterface $formatter = null,
    ) {
        if (!is_string($target) && !is_resource($target)) {
            throw new \InvalidArgumentException('A stream handler needs a path or a stream resource.');
        }

        if (is_resource($target)) {
            $this->stream = $target;
        }

        $this->formatter = $formatter ?? new LineFormatter();
    }

    public function handles(LogLevel $level): bool
    {
        return $level->isAtLeast($this->level);
    }

    public function handle(LogRecord $record): void
    {
        fwrite($this->stream(), $this->formatter->format($record) . "\n");
    }

    /**
     * Close a stream this handler opened itself; a given resource stays open.
     * The next record reopens the path.
     */
    public function close(): void
    {
        if (is_string($this->target) && is_resource($this->stream)) {
            fclose($this->stream);
        }

        if (is_string($this->target)) {
            $this->stream = null;
        }
    }

    public function __destruct()
    {
        $this->close();
    }

    /**
     * @return resource
     */
    private function stream()
    {
        if ($this->stream !== null) {
            return $this->stream;
        }

        /** @var string $path */
        $path = $this->target;
        if (!str_starts_with($path, 'php://')) {
            $directory = dirname($path);
            if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
                throw new \RuntimeException("Cannot create the log directory {$directory}.");
            }
        }

        $stream = fopen($path, 'a');
        if ($stream === false) {
            throw new \RuntimeException("Cannot open the log stream {$path}.");
        }

        return $this->stream = $stream;
    }
}
