<?php

declare(strict_types=1);

namespace Zana\Logging\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Zana\Configuration\Configuration;
use Zana\Core\Application;
use Zana\Logging\Handler\ArrayHandler;
use Zana\Logging\Handler\NullHandler;
use Zana\Logging\Handler\StreamHandler;
use Zana\Logging\LogContext;
use Zana\Logging\LoggingServiceProvider;
use Zana\Logging\LogManager;

final class LogManagerTest extends TestCase
{
    public function testChannelsComeFromConfiguration(): void
    {
        $manager = $this->manager([
            'default' => 'app',
            'channels' => [
                'app' => ['handler' => 'array', 'level' => 'info'],
                'quiet' => ['handler' => 'null'],
                'file' => ['handler' => 'stream', 'path' => 'var/log/app.log', 'formatter' => 'json'],
            ],
        ]);

        $manager->channel()->debug('below the level');
        $manager->channel()->info('kept');

        $handler = $manager->handler();
        $this->assertInstanceOf(ArrayHandler::class, $handler);
        $this->assertCount(1, $handler->records());
        $this->assertSame($manager->channel(), $manager->channel('app'), 'channels are cached');
        $this->assertInstanceOf(NullHandler::class, $manager->handler('quiet'));
        $this->assertInstanceOf(StreamHandler::class, $manager->handler('file'));
    }

    public function testContextValuesReachEveryChannel(): void
    {
        $context = new LogContext();
        $manager = new LogManager(new Configuration(['logging' => ['channels' => ['app' => ['handler' => 'array']]]]), '/app', $context);

        $context->set('correlation_id', 'c-1');
        $manager->channel()->warning('x');

        $handler = $manager->handler();
        $this->assertInstanceOf(ArrayHandler::class, $handler);
        $this->assertSame(['correlation_id' => 'c-1'], $handler->records()[0]->extra);
    }

    public function testWithoutConfigurationTheDefaultChannelLogsWarningsToStderr(): void
    {
        $handler = $this->manager([])->handler();

        $this->assertInstanceOf(StreamHandler::class, $handler);
        $this->assertFalse($handler->handles(\Zana\Logging\LogLevel::Info));
        $this->assertTrue($handler->handles(\Zana\Logging\LogLevel::Warning));
    }

    public function testInvalidConfigurationIsReported(): void
    {
        foreach ([
            [['channels' => ['app' => ['handler' => 'array']]], 'missing', 'Log channel "missing" is not configured.'],
            [['channels' => ['app' => ['handler' => 'syslog']]], 'app', "unknown handler: 'syslog'"],
            [['channels' => ['app' => ['handler' => 'stream', 'formatter' => 'xml']]], 'app', "Unknown log formatter: 'xml'"],
        ] as [$config, $channel, $message]) {
            try {
                $this->manager($config)->channel($channel);
                $this->fail("Expected: {$message}");
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString($message, $e->getMessage());
            }
        }
    }

    public function testTheProviderRegistersTheDefaultChannel(): void
    {
        $base = sys_get_temp_dir() . '/zana-logging-app-' . bin2hex(random_bytes(4));
        mkdir("{$base}/config", 0777, true);
        file_put_contents("{$base}/config/logging.php", "<?php return ['channels' => ['app' => ['handler' => 'array']]];");

        $app = Application::configure($base)->withProviders(LoggingServiceProvider::class)->build();
        $logger = $app->container()->get(LoggerInterface::class);
        $logger->error('through the container');

        $handler = $app->container()->get(LogManager::class)->handler();
        $this->assertInstanceOf(ArrayHandler::class, $handler);
        $this->assertTrue($handler->hasRecord('error', 'through the container'));
        $this->assertSame($app->container()->get(LogContext::class), $app->container()->get(LogContext::class));

        unlink("{$base}/config/logging.php");
        rmdir("{$base}/config");
        rmdir($base);
    }

    /**
     * @param array<string, mixed> $logging
     */
    private function manager(array $logging): LogManager
    {
        return new LogManager(new Configuration($logging === [] ? [] : ['logging' => $logging]), '/app', new LogContext());
    }
}
