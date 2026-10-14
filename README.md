# WilyCode Translation Database

[![Tests](https://github.com/wilycode/translation-database/actions/workflows/tests.yml/badge.svg)](https://github.com/wilycode/translation-database/actions/workflows/tests.yml)
[![Packagist](https://img.shields.io/packagist/v/wilycode/translation-database.svg)](https://packagist.org/packages/wilycode/translation-database)
[![PHP](https://img.shields.io/badge/PHP-%5E8.4-777BB4.svg)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

A lightweight, **lazy-loading database translation provider** for PHP 8.4+. Read translations through the WilyCode database driver contract, resolve missing messages using a fallback language, support plural forms, and optionally cache database rows with PSR-16.

Implements `WilyCode\Translation\TranslatorInterface`. No framework or specific database adapter is required by this package.

> ### 🧩 Part of the [WilyCode PHP Ecosystem](https://github.com/wilycode/wilycode)
>
> WilyCode provides independent PHP packages with their own repositories, releases, and Composer installation. **[Explore the WilyCode ecosystem →](https://github.com/wilycode/wilycode)** for related libraries, documentation, and integrations.

---

## Contents

- [Why Translation Database?](#why-translation-database)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Database Schema](#database-schema)
- [Translations and Placeholders](#translations-and-placeholders)
- [Pluralization](#pluralization)
- [Language Fallback](#language-fallback)
- [Lazy Loading and PSR-16 Cache](#lazy-loading-and-psr-16-cache)
- [API Reference](#api-reference)
- [SQL Queries and Security](#sql-queries-and-security)
- [Limitations](#limitations)
- [Development and Testing](#development-and-testing)
- [Related Packages](#related-packages)
- [License](#license)

## Why Translation Database?

Store messages in database tables while keeping application code dependent on a small, shared translation interface.

| Capability | Behavior |
| --- | --- |
| Database resources | Groups messages by resource name, such as `app.messages` |
| Lazy loading | Fetches a resource on its first use within a translator instance |
| Language fallback | Looks up missing messages in a configured fallback language |
| Pluralization | Uses the selected language's plural-form rules |
| Placeholders | Replaces `{name}`, `{count}`, and other named parameters |
| PSR-16 cache | Optionally reuses fetched resource rows across translator instances |
| Database abstraction | Works with any `WilyCode\Database\Driver\DatabaseInterface` implementation |

**This package reads translations.** It does not provide a translation editing interface, migrations, database connections, or locale-specific pluralization rule implementations.

## Requirements

- PHP **8.4** or newer, with the JSON extension.
- [`wilycode/translation`](https://github.com/wilycode/translation) **`^0.1.1`** (required).
- [`wilycode/database`](https://github.com/wilycode/database) **`^0.1`** (required contract).
- [`psr/simple-cache`](https://packagist.org/packages/psr/simple-cache) **`^3.0`** (required interface; a cache implementation is optional at runtime).
- A database adapter supplied by your application (for example, [`wilycode/database-pdo`](https://github.com/wilycode/database-pdo)).
- A `LanguageInterface` implementation. The ready-made languages in [`wilycode/translation-languages`](https://github.com/wilycode/translation-languages) are optional.

## Installation

```bash
composer require wilycode/translation-database:^0.1
```

To use the ready-made Polish and English language rules shown below:

```bash
composer require wilycode/translation-languages:^0.2
```

You can also implement `LanguageInterface` in your application or copy only the language classes you need from the language repository. **Installing all 25 languages is not required.**

Install or configure a `DatabaseInterface` adapter separately. This package does not open a database connection itself.

## Quick Start

### 1. Create the schema

The repository includes [`resources/schema.sql`](resources/schema.sql), a **MySQL/InnoDB** schema containing four tables:

```text
translation_resource
translation_language
translation_key
translation
```

Apply `resources/schema.sql` to your database before using the provider. The script inserts `en` and `pl` into `translation_language`; add other language codes as needed.

### 2. Insert example translations

This example assumes the bundled schema has been installed:

```sql
INSERT INTO translation_resource (name)
VALUES ('app.messages');

INSERT INTO translation_key (resource, name, plural)
SELECT id, 'welcome', 0
FROM translation_resource WHERE name = 'app.messages';

INSERT INTO translation_key (resource, name, plural)
SELECT id, 'items', 1
FROM translation_resource WHERE name = 'app.messages';

INSERT INTO translation (`key`, language, content)
SELECT tk.id, 'en', 'Welcome, {name}!'
FROM translation_key tk
JOIN translation_resource tr ON tr.id = tk.resource
WHERE tr.name = 'app.messages' AND tk.name = 'welcome';

INSERT INTO translation (`key`, language, content)
SELECT tk.id, 'pl', 'Witaj, {name}!'
FROM translation_key tk
JOIN translation_resource tr ON tr.id = tk.resource
WHERE tr.name = 'app.messages' AND tk.name = 'welcome';

INSERT INTO translation (`key`, language, content)
SELECT tk.id, 'en', '["{count} item", "{count} items"]'
FROM translation_key tk
JOIN translation_resource tr ON tr.id = tk.resource
WHERE tr.name = 'app.messages' AND tk.name = 'items';

INSERT INTO translation (`key`, language, content)
SELECT tk.id, 'pl', '["{count} element", "{count} elementy", "{count} elementów"]'
FROM translation_key tk
JOIN translation_resource tr ON tr.id = tk.resource
WHERE tr.name = 'app.messages' AND tk.name = 'items';
```

### 3. Create the translator

```php
<?php

declare(strict_types=1);

use WilyCode\Database\Driver\DatabaseInterface;
use WilyCode\Translation\Language\En;
use WilyCode\Translation\Language\Pl;
use WilyCode\TranslationDatabase\DatabaseTranslator;

function showExample(DatabaseInterface $database): void
{
    $translator = new DatabaseTranslator(
        language: new Pl(),
        database: $database,
        fallbackLanguage: new En(),
    );

    echo $translator->translate('app.messages', 'welcome', ['name' => 'Ada']);
    // Witaj, Ada!

    echo $translator->translatePlural('app.messages', 'items', 5);
    // 5 elementów
}

// Call showExample($database) with your configured DatabaseInterface adapter.
```

`DatabaseTranslator` implements `TranslatorInterface`, so it can be injected wherever the shared translation contract is expected.

## Database Schema

The shipped MySQL schema uses four tables:

| Table | Purpose |
| --- | --- |
| `translation_resource` | Resource name (`app.messages`, `admin.errors`, etc.) |
| `translation_language` | Supported two-letter language codes (`pl`, `en`, etc.) |
| `translation_key` | Message key, resource reference, and plural flag |
| `translation` | Translation text identified by message key and language code |

A translation is uniquely identified by its resource, key, and language. Plural forms live in a single `translation.content` JSON array for keys marked `plural = 1`.

**Schema compatibility:** the provided SQL uses MySQL-specific syntax (`AUTO_INCREMENT`, backticks, InnoDB). To use another database engine, adapt the SQL schema and the provider's fixed MySQL-style query before deploying. The `DatabaseInterface` contract alone does not make the shipped SQL cross-database portable.

**Language codes:** `translation_language.code` is `CHAR(2)`, matching the ISO-style codes returned by the supplied `LanguageInterface` classes. Regional formatting codes such as `en-GB` or `pt-BR` are separate from translation language codes; use the region-specific pluralization implementation when required.

## Translations and Placeholders

Call `translate()` with a resource name and message key:

```php
$translator->translate('app.messages', 'welcome', ['name' => 'Ada']);
// Witaj, Ada!
```

`MessageFormatter` from `wilycode/translation` performs simple placeholder replacement. It is not an ICU MessageFormat parser and does **not** HTML-escape values.

If a singular value is absent or is stored as a plural list, `translate()` returns the qualified key, for example `app.messages.unknown`.

Both resource and key identifiers allow letters, digits, underscores, hyphens, and dot-separated segments. Invalid identifiers cause an `InvalidArgumentException` before a database query is executed.

## Pluralization

Mark a translation key as plural by setting `translation_key.plural = 1`. Its content must be a **non-empty JSON array of strings**, indexed in the order defined by the language's pluralization rule.

Example values:

| Language | Stored `content` |
| --- | --- |
| English | `["{count} item", "{count} items"]` |
| Polish | `["{count} element", "{count} elementy", "{count} elementów"]` |

```php
$translator->translatePlural('app.messages', 'items', 1); // 1 element
$translator->translatePlural('app.messages', 'items', 2); // 2 elementy
$translator->translatePlural('app.messages', 'items', 5); // 5 elementów
```

`{count}` is always replaced by the `$count` argument, even when the parameters array includes another value for `count`.

When a requested index is missing from a non-empty plural list, the first available string is used. Supply every plural form to avoid unexpected output.

Invalid JSON, empty plural arrays, JSON objects, or non-string array values cause a `TranslationFormatException` when that resource is loaded.

## Language Fallback

With a fallback language configured, the provider fetches translations for **both language codes in a single resource query**:

```php
$translator = new DatabaseTranslator(
    language: new Pl(),
    database: $database,
    fallbackLanguage: new En(),
);
```

If a message is missing in Polish but exists in English, the English message is used. For plural messages, the **fallback language's pluralization rule** selects the correct plural form (not the primary language's rule).

If the message is missing in both languages, the return value is `resource.key` (e.g. `app.messages.welcome`). Without a fallback language, only the primary language's rows are queried.

## Lazy Loading and PSR-16 Cache

### In-memory resource loading

The first lookup for a resource performs a database query. The returned messages are stored in the **current `DatabaseTranslator` instance**. Repeated lookups for that resource do not repeat the database query.

A fresh translator instance starts with an empty in-memory store.

### Optional PSR-16 row cache

```php
use Psr\SimpleCache\CacheInterface;
use WilyCode\TranslationDatabase\DatabaseTranslator;

function createCachedTranslator(
    \WilyCode\Translation\LanguageInterface $language,
    \WilyCode\Database\Driver\DatabaseInterface $database,
    CacheInterface $cache,
): DatabaseTranslator {
    return new DatabaseTranslator(
        language: $language,
        database: $database,
        cache: $cache,
        cacheTtl: 3600,
        cachePrefix: 'myapp.translations',
    );
}
```

The optional cache is **not** a replacement for the in-instance store. It holds fetched database **rows**, keyed by the resource name and both language codes. Subsequent translator instances can reuse those cached rows for the supplied TTL.

| Option | Default | Meaning |
| --- | --- | --- |
| `cache` | `null` | PSR-16 cache implementation; `null` disables shared caching |
| `cacheTtl` | `86400` | Row cache lifetime in seconds (must be positive) |
| `cachePrefix` | `wilycode.translation_database` | Prefix for generated cache keys |

**Cache invalidation:** updates to database translations are not detected automatically. Refresh the translator instance to discard its in-memory rows; also invalidate the appropriate PSR-16 entries (or use a dedicated cache store) after editing translations. Use a distinct `cachePrefix` per application/environment/database because the key does not contain a database identifier.

The provider has **no write or cache-invalidation API**. Cache expiration applies to the shared PSR-16 rows, not to messages already loaded into a long-lived translator instance.

## API Reference

### Constructor

```php
new DatabaseTranslator(
    language: $language,
    database: $database,
    fallbackLanguage: $fallbackLanguage,
    cache: $cache,
    cacheTtl: 86400,
    cachePrefix: 'wilycode.translation_database',
);
```

| Parameter | Type | Description |
| --- | --- | --- |
| `language` | `LanguageInterface` | Primary language and plural rules |
| `database` | `DatabaseInterface` | Database driver providing `fetchAll()` |
| `fallbackLanguage` | `?LanguageInterface` | Optional fallback language; defaults to `null` |
| `cache` | `?CacheInterface` | Optional PSR-16 cache; defaults to `null` |
| `cacheTtl` | `int` | Positive cache duration in seconds; defaults to `86400` |
| `cachePrefix` | `string` | Cache key prefix; defaults to `wilycode.translation_database` |

### Public methods

| Method | Return type | Purpose |
| --- | --- | --- |
| `translate(string $resource, string $key, array $parameters = [])` | `string` | Find and format a singular message |
| `translatePlural(string $resource, string $key, int $count, array $parameters = [])` | `string` | Select and format the correct plural message |

### Exceptions

| Exception | When |
| --- | --- |
| `InvalidArgumentException` | Invalid resource/key identifier or non-positive cache TTL |
| `TranslationFormatException` | Malformed plural JSON or invalid plural array contents |
| Database or cache exceptions | Propagated from the configured driver or PSR-16 cache implementation |

`TranslationFormatException` extends PHP's `RuntimeException`.

## SQL Queries and Security

The provider uses a fixed SQL query joining `translation_key`, `translation`, and `translation_resource`. Resource and language values are passed as **bound parameters** to `DatabaseInterface::fetchAll()`; no untrusted identifier is interpolated into SQL.

```sql
SELECT tk.name AS `key`, tk.plural, t.content AS translation, t.language
FROM translation_key tk
JOIN translation t ON t.`key` = tk.id
JOIN translation_resource tr ON tr.id = tk.resource
WHERE tr.name = ? AND (t.language = ? OR t.language = ?)
```

The example above shows the fallback case. Without a fallback language, the final condition is `t.language = ?`.

- Treat translated strings and placeholders as untrusted HTML output; escape when rendering in the browser.
- Use trusted cache implementations and separate cache prefixes between unrelated databases or environments.
- Keep database credentials and connection configuration in the host application, not in translation resources.
- Validate and manage the underlying rows using your application or administration layer; this provider is read-only.

## Limitations

- MySQL-specific supplied schema and SQL query; other database engines require adaptation.
- Read-only: does not create, edit, or delete translations.
- No automatic invalidation of loaded resources or PSR-16 entries.
- No implicit installation of all language implementations; select or implement the ones you need.
- No ICU plural/message parsing, HTML escaping, or UI for managing language catalogues.
- Strict plural JSON format (`list<string>`); invalid plural values throw when their resource is loaded.

## Development and Testing

```bash
composer validate --strict
composer install
composer test
```

GitHub Actions runs PHPUnit on PHP 8.4. Tests use a `FakeDatabase` implementation of the public database contract and an in-memory PSR-16 cache. No live database server is required for unit tests; integration testing with your chosen database adapter remains the application's responsibility.

See [CONTRIBUTING.md](CONTRIBUTING.md) for contribution guidelines and [CHANGELOG.md](CHANGELOG.md) for release history.

## Related Packages

| Package | Purpose |
| --- | --- |
| [`wilycode/translation`](https://github.com/wilycode/translation) | Shared translation interfaces, message and locale formatting |
| [`wilycode/translation-languages`](https://github.com/wilycode/translation-languages) | Language pluralization rules and regional resources |
| [`wilycode/translation-array`](https://github.com/wilycode/translation-array) | PHP array-based translation provider |
| [`wilycode/translation-database`](https://github.com/wilycode/translation-database) | Database-backed translation provider (this package) |
| [`wilycode/database`](https://github.com/wilycode/database) | Database abstraction used by the provider |

## License

MIT. See [LICENSE](LICENSE).
