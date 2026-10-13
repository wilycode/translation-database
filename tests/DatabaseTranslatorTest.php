<?php

declare(strict_types=1);

namespace WilyCode\TranslationDatabase\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WilyCode\Translation\Language\En;
use WilyCode\Translation\Language\Pl;
use WilyCode\Translation\TranslatorInterface;
use WilyCode\TranslationDatabase\DatabaseTranslator;
use WilyCode\TranslationDatabase\TranslationFormatException;

#[CoversClass(DatabaseTranslator::class)]
final class DatabaseTranslatorTest extends TestCase
{
    public function testImplementsSharedTranslationContractAndLoadsLazily(): void
    {
        $database = new FakeDatabase();
        $database->returnRows = [
            ['key' => 'hello', 'plural' => '0', 'translation' => 'Hello {name}', 'language' => 'en'],
            ['key' => 'hello', 'plural' => '0', 'translation' => 'Cześć {name}', 'language' => 'pl'],
            ['key' => 'only_fallback', 'plural' => '0', 'translation' => 'Only EN', 'language' => 'en'],
            ['key' => 'items', 'plural' => '1', 'translation' => '["{count} item","{count} items"]', 'language' => 'en'],
            ['key' => 'items', 'plural' => '1', 'translation' => '["{count} element","{count} elementy","{count} elementów"]', 'language' => 'pl'],
        ];

        $translator = new DatabaseTranslator(new Pl(), $database, new En());
        self::assertInstanceOf(TranslatorInterface::class, $translator);
        self::assertSame(0, $database->calls);
        self::assertSame('Cześć Ada', $translator->translate('app.messages', 'hello', ['name' => 'Ada']));
        self::assertSame('Only EN', $translator->translate('app.messages', 'only_fallback'));
        self::assertSame('2 elementy', $translator->translatePlural('app.messages', 'items', 2));
        self::assertSame('5 elementów', $translator->translatePlural('app.messages', 'items', 5));
        self::assertSame(1, $database->calls);
    }

    public function testQueriesWithBoundLanguageParameters(): void
    {
        $database = new FakeDatabase();
        $translator = new DatabaseTranslator(new Pl(), $database, new En());
        $translator->translate('app.messages', 'missing');

        self::assertSame(['app.messages', 'pl', 'en'], $database->lastParams);
        self::assertStringContainsString('FROM translation_key tk', (string) $database->lastQuery);
        self::assertStringContainsString('t.language = ? OR t.language = ?', (string) $database->lastQuery);
    }

    public function testQueriesOnlyPrimaryLanguageWhenFallbackIsAbsent(): void
    {
        $database = new FakeDatabase();
        $translator = new DatabaseTranslator(new En(), $database);
        $translator->translate('app.messages', 'missing');

        self::assertSame(['app.messages', 'en'], $database->lastParams);
        self::assertStringContainsString('t.language = ?', (string) $database->lastQuery);
        self::assertStringNotContainsString('OR t.language', (string) $database->lastQuery);
    }

    public function testFallbackPluralUsesFallbackLanguageRules(): void
    {
        $database = new FakeDatabase();
        $database->returnRows = [
            ['key' => 'items', 'plural' => 1, 'translation' => '["{count} item","{count} items"]', 'language' => 'en'],
        ];
        $translator = new DatabaseTranslator(new Pl(), $database, new En());

        self::assertSame('1 item', $translator->translatePlural('app.messages', 'items', 1));
        self::assertSame('2 items', $translator->translatePlural('app.messages', 'items', 2));
        self::assertSame('5 items', $translator->translatePlural('app.messages', 'items', 5));
    }

    public function testExplicitCountOverridesParameters(): void
    {
        $database = new FakeDatabase();
        $database->returnRows = [
            ['key' => 'items', 'plural' => 1, 'translation' => '["{count} item","{count} items"]', 'language' => 'en'],
        ];
        $translator = new DatabaseTranslator(new En(), $database);

        self::assertSame('2 items', $translator->translatePlural('app.messages', 'items', 2, ['count' => 999]));
    }

    public function testMissingAndWrongTypeMessagesReturnQualifiedKey(): void
    {
        $database = new FakeDatabase();
        $database->returnRows = [
            ['key' => 'plural', 'plural' => 1, 'translation' => '["one","many"]', 'language' => 'en'],
            ['key' => 'singular', 'plural' => 0, 'translation' => 'single', 'language' => 'en'],
        ];
        $translator = new DatabaseTranslator(new En(), $database);

        self::assertSame('app.messages.missing', $translator->translate('app.messages', 'missing'));
        self::assertSame('app.messages.plural', $translator->translate('app.messages', 'plural'));
        self::assertSame('app.messages.singular', $translator->translatePlural('app.messages', 'singular', 2));
    }

    public function testMissingRequestedPluralFormFallsBackToFirst(): void
    {
        $database = new FakeDatabase();
        $database->returnRows = [
            ['key' => 'items', 'plural' => 1, 'translation' => '["{count} item"]', 'language' => 'en'],
        ];
        self::assertSame('2 item', new DatabaseTranslator(new En(), $database)->translatePlural('app.messages', 'items', 2));
    }

    public function testIgnoresIncompleteAndUnrelatedLanguageRows(): void
    {
        $database = new FakeDatabase();
        $database->returnRows = [
            ['key' => '', 'plural' => 0, 'translation' => 'skip', 'language' => 'pl'],
            ['key' => 'other', 'plural' => 0, 'translation' => 'skip', 'language' => 'de'],
            ['key' => 'missing_language', 'plural' => 0, 'translation' => 'skip', 'language' => ''],
        ];
        $translator = new DatabaseTranslator(new Pl(), $database, new En());

        self::assertSame('app.messages.other', $translator->translate('app.messages', 'other'));
        self::assertSame('app.messages.missing_language', $translator->translate('app.messages', 'missing_language'));
    }

    public function testDifferentResourcesLoadSeparately(): void
    {
        $database = new FakeDatabase();
        $database->rowsByResource = [
            'app.messages' => [['key' => 'title', 'plural' => 0, 'translation' => 'Hello', 'language' => 'en']],
            'admin.messages' => [['key' => 'title', 'plural' => 0, 'translation' => 'Admin', 'language' => 'en']],
        ];
        $translator = new DatabaseTranslator(new En(), $database);

        self::assertSame('Hello', $translator->translate('app.messages', 'title'));
        self::assertSame('Admin', $translator->translate('admin.messages', 'title'));
        self::assertSame('Hello', $translator->translate('app.messages', 'title'));
        self::assertSame(2, $database->calls);
    }

    public function testCacheSharesRowsAcrossInstancesAndForwardsTtl(): void
    {
        $database = new FakeDatabase();
        $database->returnRows = [['key' => 'hello', 'plural' => 0, 'translation' => 'Hello', 'language' => 'en']];
        $cache = new ArrayCache();

        $first = new DatabaseTranslator(new En(), $database, cache: $cache, cacheTtl: 3600, cachePrefix: 'test');
        self::assertSame('Hello', $first->translate('app.messages', 'hello'));
        $second = new DatabaseTranslator(new En(), $database, cache: $cache, cacheTtl: 3600, cachePrefix: 'test');
        self::assertSame('Hello', $second->translate('app.messages', 'hello'));

        self::assertSame(1, $database->calls);
        self::assertSame(1, $cache->setCalls);
        self::assertSame(3600, $cache->lastTtl);
        self::assertStringStartsWith('test.', (string) $cache->lastKey);
        self::assertGreaterThanOrEqual(2, $cache->getCalls);
    }

    public function testCacheKeysDifferentiatePrimaryFallbackAndResource(): void
    {
        $database = new FakeDatabase();
        $cache = new ArrayCache();

        (new DatabaseTranslator(new En(), $database, cache: $cache))->translate('app.messages', 'missing');
        (new DatabaseTranslator(new Pl(), $database, cache: $cache))->translate('app.messages', 'missing');
        (new DatabaseTranslator(new Pl(), $database, new En(), cache: $cache))->translate('app.messages', 'missing');
        (new DatabaseTranslator(new Pl(), $database, new En(), cache: $cache))->translate('admin.messages', 'missing');
        self::assertSame(4, $database->calls);
    }

    public function testInvalidTtlThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new DatabaseTranslator(new En(), new FakeDatabase(), cacheTtl: 0);
    }

    public function testInvalidResourceCannotReachTheDatabase(): void
    {
        $database = new FakeDatabase();
        $translator = new DatabaseTranslator(new En(), $database);
        try {
            $translator->translate('../secrets', 'hello');
            self::fail('Expected invalid resource.');
        } catch (InvalidArgumentException $e) {
            self::assertSame(0, $database->calls);
        }
    }

    public function testInvalidKeyCannotReachTheDatabase(): void
    {
        $database = new FakeDatabase();
        $translator = new DatabaseTranslator(new En(), $database);
        try {
            $translator->translate('app.messages', 'key/../../other');
            self::fail('Expected invalid key.');
        } catch (InvalidArgumentException $e) {
            self::assertSame(0, $database->calls);
        }
    }

    public function testMalformedPluralJsonThrows(): void
    {
        $this->expectPluralException('{');
    }

    public function testEmptyPluralArrayThrows(): void
    {
        $this->expectPluralException('[]');
    }

    public function testJsonObjectInsteadOfPluralListThrows(): void
    {
        $this->expectPluralException('{"one":"singular","other":"plural"}');
    }

    public function testNonStringPluralFormThrows(): void
    {
        $this->expectPluralException('["one",12]');
    }

    public function testScalarPluralJsonThrows(): void
    {
        $this->expectPluralException('"one"');
    }

    private function expectPluralException(string $json): void
    {
        $database = new FakeDatabase();
        $database->returnRows = [['key' => 'items', 'plural' => 1, 'translation' => $json, 'language' => 'en']];
        $translator = new DatabaseTranslator(new En(), $database);

        $this->expectException(TranslationFormatException::class);
        $translator->translatePlural('app.messages', 'items', 2);
    }
}
