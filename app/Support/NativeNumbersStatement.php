<?php

namespace App\Support;

use PDO;
use PDOStatement;

/**
 * Some shared hosts run a MySQL driver built on libmysql instead of mysqlnd, and it returns every
 * number as text ("12", not 12). Comparing ids with === then fails, and pages answer 404 on that
 * server only. This turns numeric columns back into numbers, exactly as mysqlnd does (DECIMAL and
 * SUM stay text on both), so the site behaves the same on every host. With mysqlnd it is not used.
 */
class NativeNumbersStatement extends PDOStatement
{
    /** Column name => 'int' or 'float'; read once per statement. */
    private ?array $numberColumns = null;

    protected function __construct()
    {
    }

    public static function applyIfNeeded(PDO $pdo): void
    {
        if (! str_contains((string) $pdo->getAttribute(PDO::ATTR_CLIENT_VERSION), 'mysqlnd')) {
            $pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS, [static::class]);
        }
    }

    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        $this->readColumns();
        $row = parent::fetch($mode, $cursorOrientation, $cursorOffset);

        return is_object($row) || is_array($row) ? $this->convert($row) : $row;
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        $this->readColumns();

        return array_map(fn ($row) => is_object($row) || is_array($row) ? $this->convert($row) : $row, parent::fetchAll($mode, ...$args));
    }

    /** 'int' or 'float' for a number column, null for anything else. */
    protected function columnKind(array $meta): ?string
    {
        return match ($meta['native_type'] ?? '') {
            'TINY', 'SHORT', 'LONG', 'LONGLONG', 'INT24', 'YEAR' => 'int',
            'FLOAT', 'DOUBLE' => 'float',
            default => null,
        };
    }

    private function readColumns(): void
    {
        if ($this->numberColumns !== null) {
            return;
        }

        $this->numberColumns = [];
        for ($i = 0, $count = $this->columnCount(); $i < $count; $i++) {
            $meta = $this->getColumnMeta($i) ?: [];
            if (isset($meta['name']) && $kind = $this->columnKind($meta)) {
                $this->numberColumns[$meta['name']] = $kind;
            }
        }
    }

    private function convert(array|object $row): array|object
    {
        foreach ($this->numberColumns as $name => $kind) {
            $value = is_array($row) ? ($row[$name] ?? null) : ($row->{$name} ?? null);
            if (! is_string($value) || ! is_numeric($value)) {
                continue;
            }

            // A number too big for PHP stays text, as with mysqlnd.
            $number = $kind === 'float' ? (float) $value : ((string) (int) $value === $value ? (int) $value : $value);
            if (is_array($row)) {
                $row[$name] = $number;
            } else {
                $row->{$name} = $number;
            }
        }

        return $row;
    }
}
