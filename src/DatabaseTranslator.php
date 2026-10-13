<?php

declare(strict_types=1);

namespace WilyCode\TranslationDatabase;

use JsonException;
use Psr\SimpleCache\CacheInterface;
use WilyCode\Database\Driver\DatabaseInterface;
use WilyCode\Translation\LanguageInterface;
use WilyCode\Translation\MessageFormatter;
use WilyCode\Translation\TranslatorInterface;

/** Database-backed lazy translation provider. */
final class DatabaseTranslator implements TranslatorInterface
{
    /** @var array<string, array<string, string|list<string>>> */
    private array $primary = [];

    /** @var array<string, array<string, string|list<string>>> */
    private array $fallback = [];

    /** @var array<string, true> */
    private array $loaded = [];

    public function __construct(
        private readonly LanguageInterface $language,
        private readonly DatabaseInterface $database,
        private readonly ?LanguageInterface $fallbackLanguage = null,
        private readonly ?CacheInterface $cache = null,
        private readonly int $cacheTtl = 86400,
        private readonly string $cachePrefix = 'wilycode.translation_database',
    ) {
        if ($this->cacheTtl < 1) {
            throw new \InvalidArgumentException('Translation cache TTL must be greater than zero.');
        }
    }

    public function translate(string $resource, string $key, array $parameters = []): string
    {
        $this->assertIdentifier($resource, 'resource');
        $this->assertIdentifier($key, 'translation key');
        $this->ensureLoaded($resource);

        $value = $this->primary[$resource][$key]
            ?? $this->fallback[$resource][$key]
            ?? null;

        if (!is_string($value)) {
            return $resource . '.' . $key;
        }

        return MessageFormatter::format($value, $parameters);
    }

    public function translatePlural(string $resource, string $key, int $count, array $parameters = []): string
    {
        $this->assertIdentifier($resource, 'resource');
        $this->assertIdentifier($key, 'translation key');
        $this->ensureLoaded($resource);
        $parameters['count'] = $count;

        $forms = $this->primary[$resource][$key] ?? null;
        $pluralLanguage = $this->language;

        if (!is_array($forms) || $forms === []) {
            $forms = $this->fallback[$resource][$key] ?? null;
            $pluralLanguage = $this->fallbackLanguage ?? $this->language;
        }

        if (!is_array($forms) || $forms === []) {
            return $resource . '.' . $key;
        }

        $message = $forms[$pluralLanguage->getPluralForm($count)] ?? reset($forms);

        return MessageFormatter::format((string) $message, $parameters);
    }

    private function ensureLoaded(string $resource): void
    {
        if (isset($this->loaded[$resource])) {
            return;
        }

        $rows = $this->loadRows($resource);
        $primaryCode = $this->language->getCode();
        $fallbackCode = $this->fallbackLanguage?->getCode();

        foreach ($rows as $row) {
            $key = (string) ($row['key'] ?? '');
            $language = (string) ($row['language'] ?? '');
            if ($key === '' || $language === '') {
                continue;
            }

            $value = (string) ($row['plural'] ?? '0') === '1'
                ? $this->decodePlural((string) ($row['translation'] ?? ''))
                : (string) ($row['translation'] ?? '');

            if ($language === $primaryCode) {
                $this->primary[$resource][$key] = $value;
            } elseif ($fallbackCode !== null && $language === $fallbackCode) {
                $this->fallback[$resource][$key] = $value;
            }
        }

        $this->loaded[$resource] = true;
    }

    /** @return list<array<string, mixed>> */
    private function loadRows(string $resource): array
    {
        if ($this->cache === null) {
            return $this->fetchRows($resource);
        }

        $fallback = $this->fallbackLanguage?->getCode() ?? '-';
        $cacheKey = $this->cachePrefix . '.' . hash(
            'sha256',
            $resource . '|' . $this->language->getCode() . '|' . $fallback,
        );

        $cached = $this->cache->get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $rows = $this->fetchRows($resource);
        $this->cache->set($cacheKey, $rows, $this->cacheTtl);

        return $rows;
    }

    /** @return list<array<string, mixed>> */
    private function fetchRows(string $resource): array
    {
        $sql =
            'SELECT tk.name AS `key`, tk.plural, t.content AS translation, t.language '
            . 'FROM translation_key tk '
            . 'JOIN translation t ON t.`key` = tk.id '
            . 'JOIN translation_resource tr ON tr.id = tk.resource '
            . 'WHERE tr.name = ? AND ';

        $parameters = [$resource, $this->language->getCode()];
        if ($this->fallbackLanguage === null) {
            $sql .= 't.language = ?';
        } else {
            $sql .= '(t.language = ? OR t.language = ?)';
            $parameters[] = $this->fallbackLanguage->getCode();
        }

        /** @var list<array<string, mixed>> */
        return $this->database->fetchAll($sql, $parameters);
    }

    private function assertIdentifier(string $value, string $label): void
    {
        if (preg_match('/^[A-Za-z0-9_-]+(?:\.[A-Za-z0-9_-]+)*$/D', $value) !== 1) {
            throw new \InvalidArgumentException('Invalid ' . $label . ': ' . $value);
        }
    }

    /** @return list<string> */
    private function decodePlural(string $json): array
    {
        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new TranslationFormatException(
                'Plural translation must be a valid JSON array: ' . $e->getMessage(),
                0,
                $e,
            );
        }

        if (!is_array($decoded) || $decoded === [] || !array_is_list($decoded)) {
            throw new TranslationFormatException('Plural translation must be a non-empty JSON array of strings.');
        }

        foreach ($decoded as $value) {
            if (!is_string($value)) {
                throw new TranslationFormatException('Every plural translation form must be a string.');
            }
        }

        return $decoded;
    }
}
