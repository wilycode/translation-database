# Contributing to WilyCode Translation Database

Thanks for your interest in contributing. Keep changes focused and compatible with PHP 8.4 or newer.

## Local development

```bash
composer install
composer validate --strict
composer test
```

Please include tests for changes to database loading, pluralization, fallback behavior, and PSR-16 caching. Use parameterized SQL; never interpolate resource names or user-provided language codes into SQL statements.

## Pull requests

- Open an issue to discuss significant API changes.
- Keep each pull request focused and describe the intended behavior.
- Include PHPUnit coverage and update `README.md` for user-visible changes.
- Run `composer test` and `composer validate --strict` before submission.

For the wider family of PHP libraries, see the [WilyCode PHP Ecosystem](https://github.com/wilycode/wilycode).
