<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use PDO;
use Tests\Support\SqliteNativeNumbersStatement;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // TEXT_NUMBERS=1 php artisan test: the database returns every number as text, as on hosts whose
        // MySQL driver is not mysqlnd, and the site's conversion turns them back. Everything must still pass.
        if (env('TEXT_NUMBERS')) {
            $this->databaseReturnsNumbersAsText();
            DB::connection()->getPdo()->setAttribute(PDO::ATTR_STATEMENT_CLASS, [SqliteNativeNumbersStatement::class]);
        }
    }

    protected function databaseReturnsNumbersAsText(): void
    {
        DB::connection()->getPdo()->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, true);
    }
}
