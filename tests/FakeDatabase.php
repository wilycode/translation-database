<?php

declare(strict_types=1);

namespace WilyCode\TranslationDatabase\Tests;

use WilyCode\Database\Driver\DatabaseInterface;
use WilyCode\Database\Driver\Exception\NestedTransactionException;

/**
 * Minimal in-memory DatabaseInterface test double.
 *
 * We only need fetchAll() for DatabaseTranslator tests, but we implement the full
 * interface so the type-hint in DatabaseTranslator stays consistent.
 */
final class FakeDatabase implements DatabaseInterface
{
    /** @var array<int, array<string, mixed>> */
    public array $returnRows = [];

    /** @var array<string, list<array<string, mixed>>> */
    public array $rowsByResource = [];

    /** Number of fetchAll() calls (used by tests). */
    public int $calls = 0;

    public ?string $lastQuery = null;

    /** @var array<int, scalar|null>|null */
    public ?array $lastParams = null;

    /** @var array<int, mixed>|null */
    public ?array $lastTypes = null;

    private bool $inTransaction = false;

    public function execute(string $query, array $params = [], array $types = []): int
    {
        return 1;
    }

    public function fetch(string $query, array $params = [], array $types = []): array
    {
        return [];
    }

    public function fetchAll(string $query, array $params = [], array $types = []): array
    {
        $this->calls++;
        $this->lastQuery = $query;
        $this->lastParams = $params;
        $this->lastTypes = $types;

        $resource = (string) ($params[0] ?? '');
        return $this->rowsByResource[$resource] ?? $this->returnRows;
    }

    public function fetchColumn(string $query, array $params = [], int $columnIndex = 0, array $types = []): mixed
    {
        return null;
    }

    public function getLastInsertId(): string
    {
        return '0';
    }


    public function transaction(callable $fn): mixed
    {
        if ($this->inTransaction) {
            throw new NestedTransactionException(
                'Nested transactions are not supported by FakeDatabase.'
            );
        }

        $this->inTransaction = true;

        try {
            $result = $fn($this);
            $this->inTransaction = false;
            return $result;
        } catch (\Throwable $e) {
            $this->inTransaction = false;
            throw $e;
        }
    }

    public function inTransaction(): bool
    {
        return $this->inTransaction;
    }
}
