# Changelog

All notable changes to this project will be documented in this file.

## 1.1.0 - 2026-11-18

### Changed

- Requires the 2.0 of the suite core, which runs on [laravel-privacy](https://github.com/jeffersongoncalves/laravel-privacy), the engine shared by the privacy suites. No change to this package's API, config or tables.

## 1.0.0 - 2026-11-17

First release of laravel-us-privacy-requests, consumer requests under the US state privacy laws (on top of laravel-us-privacy).

- Access, correct, delete, portability, third parties (Oregon, Delaware, Minnesota, Maryland) and profiling (Minnesota) requests.
- Deadline and extension of the consumer's state (Iowa 90 days, the others 45, extendable once by 45); strictest rules when the state is unknown.
- Appeals: `appeal()`, `extendAppeal()` (Colorado, Oregon, Minnesota) and `decideAppeal()` within each state's window (45 or 60 days); no appeal in Utah.
- Access and portability export JSON to a private disk; delete deidentifies every record of the consumer.
- `php artisan us-privacy:requests-overdue` fires events for overdue requests and appeals.

## [Unreleased]
