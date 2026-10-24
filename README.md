# Zana Logging

A PSR-3 logger with named channels, handlers, formatters and processors.
Requires PHP 8.5 or later.

```
composer require mj-zana/logging
```

## Usage

Register `LoggingServiceProvider` and configure channels in `config/logging.php`:

```php
return [
    'default' => 'app',
    'channels' => [
        'app' => ['handler' => 'stream', 'path' => 'var/log/app.log', 'level' => 'info', 'formatter' => 'json'],
        'audit' => ['handler' => 'stream', 'path' => 'var/log/audit.log'],
        'test' => ['handler' => 'array'],
    ],
];
```

```php
public function __construct(private LoggerInterface $logger) {}

$this->logger->info('BMI {bmi} recorded for {patient}', ['bmi' => 22.5, 'patient' => $id]);

$manager->channel('audit')->notice('Patient record exported');
```

- **Handlers:** `stream` (a file, created with its directory, or `php://stderr`),
  `array` (keeps records for tests) and `null`.
- **Formatters:** `line` and `json`. Exceptions, dates, objects and resources in
  the context are normalized.
- **Without configuration:** the default channel logs warnings and above to
  `php://stderr`.

`LogContext` values (for example the correlation id that `mj-zana/http` sets for
each request) are added to every record's `extra`.

## License

MIT — see [LICENSE](LICENSE).
