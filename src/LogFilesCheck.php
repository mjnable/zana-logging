<?php

declare(strict_types=1);

namespace Zana\Logging;

use Zana\Core\ApplicationInterface;
use Zana\Core\Deployment\CheckResult;
use Zana\Core\Deployment\DeploymentCheck;

/**
 * Warns when a log file of a configured channel cannot be written (FE-43):
 * on a server where var/ is not writable, errors would go nowhere. Runs at
 * startup and in the doctor command; the startup warning goes to the error
 * log, as the log files are the problem.
 */
final readonly class LogFilesCheck implements DeploymentCheck
{
    public function __construct(private LogManager $logs)
    {
    }

    public function check(ApplicationInterface $application): CheckResult
    {
        $problems = [];
        foreach ($this->logs->files() as $channel => $file) {
            if (!self::writable($file)) {
                $problems[] = "The log channel \"{$channel}\" cannot write {$file}: make its folder writable.";
            }
        }

        return $problems === [] ? CheckResult::ok() : CheckResult::warning(implode("\n", $problems));
    }

    /**
     * The file is writable, or the nearest existing folder above it is, so it can be created.
     */
    private static function writable(string $file): bool
    {
        if (is_file($file)) {
            return is_writable($file);
        }
        $directory = dirname($file);
        while (!file_exists($directory) && dirname($directory) !== $directory) {
            $directory = dirname($directory);
        }

        return is_dir($directory) && is_writable($directory);   // a file in the way blocks the folder
    }
}
