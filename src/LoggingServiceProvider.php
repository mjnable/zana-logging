<?php

declare(strict_types=1);

namespace Zana\Logging;

use Psr\Log\LoggerInterface;
use Zana\Configuration\ConfigurationInterface;
use Zana\Container\Contracts\ServiceCollectionInterface;
use Zana\Container\Contracts\ServiceResolverInterface;
use Zana\Core\ApplicationInterface;
use Zana\Core\Deployment\DeploymentCheck;
use Zana\Core\ServiceProvider;

/**
 * Registers LogManager, LogContext and the default channel as LoggerInterface,
 * and a deployment check that the log files can be written.
 */
final class LoggingServiceProvider extends ServiceProvider
{
    public function register(ServiceCollectionInterface $services): void
    {
        $services
            ->singleton(LogContext::class)
            ->singleton(LogManager::class, static fn (ServiceResolverInterface $r): LogManager => new LogManager(
                $r->get(ConfigurationInterface::class),
                $r->get(ApplicationInterface::class)->basePath(),
                $r->get(LogContext::class),
            ))->uses(ConfigurationInterface::class, ApplicationInterface::class, LogContext::class)
            ->singleton(LoggerInterface::class, static fn (ServiceResolverInterface $r): LoggerInterface => $r->get(LogManager::class)->channel())->uses(LogManager::class)
            ->singleton(LogFilesCheck::class, static fn (ServiceResolverInterface $r): LogFilesCheck => new LogFilesCheck($r->get(LogManager::class)))->uses(LogManager::class)
            ->tag(DeploymentCheck::TAG, LogFilesCheck::class);
    }

    public function origin(): string
    {
        return 'package:mj-zana/logging';
    }
}
