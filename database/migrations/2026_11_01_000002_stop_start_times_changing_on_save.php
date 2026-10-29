<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * MariaDB / MySQL with explicit_defaults_for_timestamp off (XAMPP's MariaDB 10.4) silently gives
 * the first NOT NULL timestamp of a table "ON UPDATE CURRENT_TIMESTAMP". These were therefore
 * set to "now" on every save: an exam's start time became its submit time (time taken 0:00),
 * and an enrollment's start date moved whenever the enrollment changed. An explicit default
 * stops it; the values already there stay as they are.
 */
return new class extends Migration
{
    private const COLUMNS = ['quiz_attempts' => 'started_at', 'enrollments' => 'starts_at', 'lesson_progress' => 'completed_at'];

    public function up(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }
        foreach (self::COLUMNS as $table => $column) {
            DB::statement("ALTER TABLE `$table` MODIFY `$column` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP");
        }
    }

    public function down(): void
    {
        // Nothing to undo: the columns keep their values and only stop changing by themselves.
    }
};
