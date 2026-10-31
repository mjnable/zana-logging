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
 *
 * With $maxSize, a file is rotated before a line would make it larger:
 * app.log becomes app.log.1, app.log.1 becomes app.log.2, and so on; the
 * oldest beyond $maxFiles is deleted (FE-43).
 */
final class StreamHandler implements HandlerInterface
{
    /** @var resource|null */
    private $stream = null;

    private readonly FormatterInterface $formatter;

    /**
     * @param string|resource $target A path, a php:// stream URL, or an open stream
     * @param int|null $maxSize Bytes a file may reach before it is rotated; null never rotates
     * @param int $maxFiles Rotated files kept (app.log.1 … app.log.N)
     */
    public function __construct(
        private readonly mixed $target,
        private readonly LogLevel $level = LogLevel::Debug,
        ?FormatterInterface $formatter = null,
        private readonly ?int $maxSize = null,
        private readonly int $maxFiles = 5,
    ) {
        if (!is_string($target) && !is_resource($target)) {
            throw new \InvalidArgumentException('A stream handler needs a path or a stream resource.');
        }
        if (($maxSize !== null && $maxSize < 1) || $maxFiles < 1) {
            throw new \InvalidArgumentException('A log file needs a positive max_size and max_files.');
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

    /**
     * Logging never breaks the application: when the file cannot be opened or
     * written, the line goes to PHP's error log instead, with the reason.
     */
    public function handle(LogRecord $record): void
    {
        $line = $this->formatter->format($record) . "\n";
        try {
            $stream = $this->stream();
            if ($this->maxSize !== null && is_string($this->target) && !str_starts_with($this->target, 'php://')) {
                $size = fstat($stream)['size'] ?? 0;
                if ($size > 0 && $size + strlen($line) > $this->maxSize) {
                    $this->rotate($this->target);
                    $stream = $this->stream();
                }
            }
            if (@fwrite($stream, $line) === false) {
                throw new \RuntimeException('Cannot write to the log stream ' . (is_string($this->target) ? $this->target : 'resource') . '.');
            }
        } catch (\RuntimeException $e) {
            error_log('[zana] ' . $e->getMessage() . ' ' . rtrim($line));
        }
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
     * Shift path.N to path.N+1 (deleting the last), then path to path.1.
     * Another process may rotate at the same time; a failed rename only means
     * that its lines go to the newer file.
     */
    private function rotate(string $path): void
    {
        $this->close();
        @unlink("{$path}.{$this->maxFiles}");
        for ($n = $this->maxFiles - 1; $n >= 1; $n--) {
            if (is_file("{$path}.{$n}")) {
                @rename("{$path}.{$n}", "{$path}." . ($n + 1));
            }
        }
        @rename($path, "{$path}.1");
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
            if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
                throw new \RuntimeException("Cannot create the log directory {$directory}.");
            }
        }

        $stream = @fopen($path, 'a');
        if ($stream === false) {
            throw new \RuntimeException("Cannot open the log stream {$path}.");
        }

        return $this->stream = $stream;
    }
}
