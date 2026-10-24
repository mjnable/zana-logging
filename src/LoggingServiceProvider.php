<?php

declare(strict_types=1);

namespace Zana\Logging;

use Psr\Log\LoggerInterface;
use Zana\Configuration\ConfigurationInterface;
use Zana\Container\Contracts\ServiceCollectionInterface;
use Zana\Container\Contracts\ServiceResolverInterface;
use Zana\Core\ApplicationInterface;
use Zana\Core\ServiceProvider;

/**
 * Registers LogManager, LogContext and the default channel as LoggerInterface.
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
            ))
            ->singleton(LoggerInterface::class, static fn (ServiceResolverInterface $r): LoggerInterface => $r->get(LogManager::class)->channel());
    }

    public function origin(): string
    {
        return 'package:mj-zana/logging';
    }
}
