<?php

declare(strict_types=1);

namespace Zana\Logging\Tests;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\TestCase;
use Zana\Configuration\Configuration;
use Zana\Core\Application;
use Zana\Core\Deployment\CheckStatus;
use Zana\Logging\Handler\StreamHandler;
use Zana\Logging\LogContext;
use Zana\Logging\LogFilesCheck;
use Zana\Logging\LogLevel;
use Zana\Logging\LogManager;
use Zana\Logging\LogRecord;

/**
 * FE-43: log files rotate by size, a file that cannot be written never breaks
 * the application, and a deployment check says so before going live.
 */
final class LogFilesTest extends TestCase
{
    private string $dir = '';

    private string|false $errorLog = false;

    private string $errorLogFile = '';

    #[Before]
    public function prepare(): void
    {
        $this->dir = str_replace('\\', '/', sys_get_temp_dir()) . '/zana-log-files-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
        $this->errorLogFile = "{$this->dir}/php-error.log";
        $this->errorLog = ini_set('error_log', $this->errorLogFile);
    }

    #[After]
    public function cleanUp(): void
    {
        ini_set('error_log', $this->errorLog === false ? '' : $this->errorLog);
        exec(PHP_OS_FAMILY === 'Windows' ? 'rmdir /s /q ' . escapeshellarg(str_replace('/', '\\', $this->dir)) : 'rm -rf ' . escapeshellarg($this->dir));
    }

    public function testFilesRotateBySizeAndKeepTheNewest(): void
    {
        $handler = new StreamHandler("{$this->dir}/app.log", LogLevel::Debug, null, maxSize: 200, maxFiles: 2);
        for ($i = 1; $i <= 12; $i++) {
            $handler->handle(self::record("entry {$i} " . str_repeat('x', 40)));
        }
        $handler->close();

        $files = array_map('basename', glob("{$this->dir}/app.log*") ?: []);
        sort($files);
        $this->assertSame(['app.log', 'app.log.1', 'app.log.2'], $files, 'two rotated files are kept');
        foreach ($files as $file) {
            $this->assertLessThanOrEqual(200, filesize("{$this->dir}/{$file}"));
        }
        $this->assertStringContainsString('entry 12 ', (string) file_get_contents("{$this->dir}/app.log"));
        $this->assertStringNotContainsString('entry 1 ', (string) file_get_contents("{$this->dir}/app.log.2"), 'the oldest entries are gone');
    }

    public function testTheChannelReadsMaxSizeWithUnits(): void
    {
        $logs = $this->manager(['app' => ['path' => 'var/log/app.log', 'max_size' => '1K', 'max_files' => 1]]);
        $logger = $logs->channel();
        for ($i = 0; $i < 40; $i++) {
            $logger->warning(str_repeat('y', 60));
        }
        $handler = $logs->handler();
        $this->assertInstanceOf(StreamHandler::class, $handler);
        $handler->close();

        $this->assertLessThanOrEqual(1024, filesize("{$this->dir}/var/log/app.log"));
        $this->assertFileExists("{$this->dir}/var/log/app.log.1");
        $this->assertFileDoesNotExist("{$this->dir}/var/log/app.log.2");
    }

    public function testAnInvalidMaxSizeIsExplained(): void
    {
        $this->expectExceptionMessage('Log channel "app" has an invalid max_size: \'ten megs\'. Use bytes, or K, M or G, e.g. "10M".');

        $this->manager(['app' => ['path' => 'var/log/app.log', 'max_size' => 'ten megs']])->channel();
    }

    public function testAnUnwritableFileGoesToTheErrorLogInsteadOfFailing(): void
    {
        file_put_contents("{$this->dir}/var", 'a file where the log folder should be');
        $handler = new StreamHandler("{$this->dir}/var/log/app.log");

        $handler->handle(self::record('Mail could not be sent'));

        $error = (string) file_get_contents($this->errorLogFile);
        $this->assertStringContainsString('[zana] Cannot create the log directory', $error);
        $this->assertStringContainsString('Mail could not be sent', $error, 'the entry is kept');
    }

    public function testTheDeploymentCheckWarnsAboutUnwritableLogFiles(): void
    {
        $application = Application::configure($this->dir)->withEnvironment('testing')->build();

        file_put_contents("{$this->dir}/var", 'a file where the log folder should be');
        $blocked = (new LogFilesCheck($this->manager(['app' => ['path' => 'var/log/app.log'], 'audit' => ['path' => 'logs/audit.log'], 'stderr' => ['path' => 'php://stderr']])))->check($application);
        $this->assertSame(CheckStatus::Warning, $blocked->status);
        $this->assertStringContainsString('The log channel "app" cannot write', $blocked->message);
        $this->assertStringNotContainsString('audit', $blocked->message, 'a folder that does not exist yet can be created');

        unlink("{$this->dir}/var");
        $this->assertSame(CheckStatus::Ok, (new LogFilesCheck($this->manager(['app' => ['path' => 'var/log/app.log']])))->check($application)->status);
    }

    /**
     * @param array<string, array<string, mixed>> $channels
     */
    private function manager(array $channels): LogManager
    {
        return new LogManager(new Configuration(['logging' => ['default' => 'app', 'channels' => $channels]]), $this->dir, new LogContext());
    }

    private static function record(string $message): LogRecord
    {
        return new LogRecord(new \DateTimeImmutable('2026-10-31 12:00:00'), 'app', LogLevel::Error, $message, []);
    }
}
