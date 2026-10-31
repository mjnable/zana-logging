# Changelog

All Zana packages share one version number and are released together. A release that did
not change this package is not listed.

## 2.6.0 — 2026-10-31

### Added

- Size rotation for file channels: `max_size` (bytes, or `K`, `M`, `G`) and `max_files` (5) (FE-43).
- `LogFilesCheck`, a deployment check that warns when a configured log file cannot be written;
  `LogManager::files()`.

### Changed

- `StreamHandler` no longer throws when its file cannot be created or written: the line goes to PHP's
  error log with the reason, so logging never breaks the application.
## 2.4.0 — 2026-10-26

### Changed

- Factory registrations declare the services they resolve with `uses()`, so the container checks
  their lifetimes at startup (FE-40).
- Requires `mj-zana/core` ^2.4.

## 2.3.0 — 2026-10-26

### Changed

- Tests and tooling (`tests/`, `phpunit.xml.dist`, `phpstan.neon.dist`) are no longer in Composer
  archives, so they stay out of production `vendor/` folders (FE-17).

## 2.0.0 — 2026-10-26

The final 2.0 release. No changes since 2.0.0-rc.1.

## 2.0.0-alpha.3 — 2026-10-24

First release of the 2.0 line. A PSR-3 logger with named channels, handlers, formatters and processors. Requires PHP 8.5 or later.

Documentation: [the logging guide](https://github.com/mjnable/zana-docs/blob/main/guides/logging.md).
