<?php

declare(strict_types=1);

namespace Zana\Logging\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Log\InvalidArgumentException;
use Zana\Logging\Formatter\JsonFormatter;
use Zana\Logging\Formatter\LineFormatter;
use Zana\Logging\Handler\ArrayHandler;
use Zana\Logging\Handler\NullHandler;
use Zana\Logging\Handler\StreamHandler;
use Zana\Logging\LogContext;
use Zana\Logging\Logger;
use Zana\Logging\LogLevel;
use Zana\Logging\LogRecord;
use Zana\Logging\Processor\ContextProcessor;

final class LoggerTest extends TestCase
{
    private const TIME = '2026-10-24T10:00:00+00:00';

    public function testMessagesAreInterpolatedAndContextIsKept(): void
    {
        $handler = new ArrayHandler();
        $logger = $this->logger([$handler]);
        $when = new \DateTimeImmutable(self::TIME);
        $stringable = new class () implements \Stringable {
            public function __toString(): string
            {
                return 'S';
            }
        };

        $logger->info('{user} measured {bmi} at {when}, flag {flag}, missing {nope}, null {none}, obj {obj}, str {s}', [
            'user' => 'ada',
            'bmi' => 22.5,
            'when' => $when,
            'flag' => false,
            'none' => null,
            'obj' => new \stdClass(),
            's' => $stringable,
        ]);

        $record = $handler->records()[0];
        $this->assertSame('ada measured 22.5 at ' . self::TIME . ', flag false, missing {nope}, null null, obj {obj}, str S', $record->message);
        $this->assertSame('ada', $record->context['user']);
        $this->assertSame(['app', LogLevel::Info], [$record->channel, $record->level]);
    }

    public function testHandlersOnlyReceiveTheirLevels(): void
    {
        $all = new ArrayHandler();
        $errors = new ArrayHandler(LogLevel::Error);
        $logger = $this->logger([$all, $errors]);

        $logger->debug('d');
        $logger->warning('w');
        $logger->critical('c');

        $this->assertCount(3, $all->records());
        $this->assertSame(['c'], array_map(static fn (LogRecord $r): string => $r->message, $errors->records()));
        $this->assertTrue($all->hasRecord('warning', 'w'));
        $this->assertFalse($all->hasRecord(LogLevel::Error));

        $all->clear();
        $this->assertSame([], $all->records());
    }

    public function testProcessorsRunInOrder(): void
    {
        $handler = new ArrayHandler();
        $logger = new Logger('app', [$handler], [
            static fn (LogRecord $r): LogRecord => $r->withExtra(['step' => 1, 'first' => true]),
            static fn (LogRecord $r): LogRecord => $r->withExtra(['step' => 2]),
        ]);

        $logger->notice('x');

        $this->assertSame(['step' => 2, 'first' => true], $handler->records()[0]->extra);
    }

    public function testUnknownLevelsAreRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Unknown log level: 'verbose'.");

        $this->logger([new NullHandler()])->log('verbose', 'x');
    }

    public function testLevelsAreOrdered(): void
    {
        $this->assertTrue(LogLevel::Emergency->isAtLeast(LogLevel::Alert));
        $this->assertTrue(LogLevel::Warning->isAtLeast(LogLevel::Warning));
        $this->assertFalse(LogLevel::Info->isAtLeast(LogLevel::Notice));
        $this->assertSame(LogLevel::Error, LogLevel::fromName('ERROR'));
    }

    public function testNoRecordIsCreatedWhenNobodyListens(): void
    {
        $processorCalls = 0;
        $logger = new Logger('app', [new ArrayHandler(LogLevel::Error)], [
            static function (LogRecord $r) use (&$processorCalls): LogRecord {
                $processorCalls++;

                return $r;
            },
        ]);

        $logger->debug('ignored');

        $this->assertSame(0, $processorCalls);
    }

    // ── formatters ─────────────────────────────────────────────────────────

    public function testLineFormatter(): void
    {
        $formatter = new LineFormatter();

        $this->assertSame('[' . self::TIME . '] app.INFO: plain', $formatter->format($this->record('plain')));
        $this->assertSame(
            '[' . self::TIME . '] app.INFO: two lines {"path":"a/b","ratio":1.0} {"correlation_id":"c1"}',
            $formatter->format($this->record("two\nlines", ['path' => 'a/b', 'ratio' => 1.0], ['correlation_id' => 'c1']))
        );
    }

    public function testJsonFormatter(): void
    {
        $json = (new JsonFormatter())->format($this->record('hi', [], ['k' => 'v']));

        $this->assertSame('{"datetime":"' . self::TIME . '","channel":"app","level":"info","message":"hi","context":{},"extra":{"k":"v"}}', $json);
    }

    public function testExceptionsAndOtherValuesAreFormatted(): void
    {
        $previous = new \LogicException('root cause');
        $exception = new \RuntimeException('outer', 42, $previous);
        $stream = fopen('php://memory', 'r');

        $json = (new JsonFormatter())->format($this->record('failed', [
            'exception' => $exception,
            'when' => new \DateTimeImmutable(self::TIME),
            'object' => new \stdClass(),
            'level' => LogLevel::Error,
            'stream' => $stream,
            'list' => [1, [2]],
        ]));

        /** @var array{context: array<string, mixed>} $decoded */
        $decoded = json_decode($json, true);
        $context = $decoded['context'];

        $this->assertSame(['class' => \RuntimeException::class, 'message' => 'outer', 'code' => 42], array_slice((array) $context['exception'], 0, 3));
        $this->assertSame('root cause', $context['exception']['previous']['message']);
        $this->assertStringContainsString('LoggerTest.php:', $context['exception']['file']);
        $this->assertSame(self::TIME, $context['when']);
        $this->assertSame('[object stdClass]', $context['object']);
        $this->assertSame('error', $context['level']);
        $this->assertSame('[resource stream]', $context['stream']);
        $this->assertSame([1, [2]], $context['list']);
    }

    // ── stream handler ─────────────────────────────────────────────────────

    public function testStreamHandlerWritesLinesAndCreatesDirectories(): void
    {
        $path = sys_get_temp_dir() . '/zana-logging-' . bin2hex(random_bytes(4)) . '/nested/app.log';
        $handler = new StreamHandler($path, LogLevel::Info);
        $logger = $this->logger([$handler]);

        $logger->debug('skipped');
        $logger->info('first');
        $logger->error('second');

        $lines = file($path, FILE_IGNORE_NEW_LINES) ?: [];
        $this->assertCount(2, $lines);
        $this->assertStringEndsWith('app.INFO: first', $lines[0]);
        $this->assertStringEndsWith('app.ERROR: second', $lines[1]);

        $handler->close();
        unlink($path);
        rmdir(dirname($path));
        rmdir(dirname($path, 2));
    }

    public function testStreamHandlerAcceptsResources(): void
    {
        $stream = fopen('php://memory', 'w+');
        $this->assertNotFalse($stream);
        $this->logger([new StreamHandler($stream, formatter: new JsonFormatter())])->alert('to memory');

        rewind($stream);
        $this->assertStringContainsString('"message":"to memory"', (string) stream_get_contents($stream));
    }

    // ── context ────────────────────────────────────────────────────────────

    public function testContextProcessorAddsCurrentValues(): void
    {
        $context = new LogContext();
        $handler = new ArrayHandler();
        $logger = new Logger('app', [$handler], [new ContextProcessor($context)]);

        $logger->info('before');
        $context->set('correlation_id', 'abc');
        $context->set('user_id', 7);
        $logger->info('during');
        $context->remove('user_id');
        $logger->info('after');
        $context->clear();
        $logger->info('cleared');

        $this->assertSame(
            [[], ['correlation_id' => 'abc', 'user_id' => 7], ['correlation_id' => 'abc'], []],
            array_map(static fn (LogRecord $r): array => $r->extra, $handler->records())
        );
    }

    /**
     * @param list<\Zana\Logging\Handler\HandlerInterface> $handlers
     */
    private function logger(array $handlers): Logger
    {
        return new Logger('app', $handlers, [], static fn (): \DateTimeImmutable => new \DateTimeImmutable(self::TIME));
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $extra
     */
    private function record(string $message, array $context = [], array $extra = []): LogRecord
    {
        return new LogRecord(new \DateTimeImmutable(self::TIME), 'app', LogLevel::Info, $message, $context, $extra);
    }
}
