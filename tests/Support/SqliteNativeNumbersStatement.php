<?php

namespace Tests\Support;

use App\Support\NativeNumbersStatement;

/** The same conversion for the tests' SQLite database, which names its column types differently. */
class SqliteNativeNumbersStatement extends NativeNumbersStatement
{
    protected function columnKind(array $meta): ?string
    {
        $declared = strtolower($meta['sqlite:decl_type'] ?? '');

        return match (true) {
            $declared !== '' && preg_match('/int/', $declared) === 1 => 'int',
            $declared !== '' && preg_match('/float|double|real/', $declared) === 1 => 'float',
            $declared !== '' => null,
            default => ['integer' => 'int', 'double' => 'float'][$meta['native_type'] ?? ''] ?? null, // COUNT(*) and such
        };
    }
}
