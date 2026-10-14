# Changelog

All notable changes to WilyCode Translation Database are documented here.

## [Unreleased]

### Changed

- Updated the database contract dependency to `wilycode/database`.
- Updated the suggested PDO adapter to `wilycode/database-pdo` and refreshed documentation.

## [0.1.0] - 2026-10-14

### Added

- Database-backed `DatabaseTranslator` implementing `TranslatorInterface`.
- Resource-level lazy loading, language fallback, and pluralization.
- Optional PSR-16 row caching with configurable TTL and key prefix.
- MySQL/InnoDB installation schema under `resources/schema.sql`.
- Validation of plural JSON arrays as non-empty lists of strings.
- PHPUnit tests and GitHub Actions for PHP 8.4.
- Complete English documentation and integration examples.

### Changed

- Aligned package naming with `wilycode/translation-*` and namespace `WilyCode\TranslationDatabase`.
- Updated dependency on `wilycode/translation:^0.1.1` and development examples using `wilycode/translation-languages:^0.2`.
