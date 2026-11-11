<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * More question types: "match_text" is the right-hand side of a matching pair, and "slot" is the
 * blank a word belongs in for drag and drop (empty = an extra word that fits nowhere).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quiz_options', function (Blueprint $table) {
            $table->text('match_text')->nullable()->after('text');
            $table->unsignedSmallInteger('slot')->nullable()->after('is_correct');
        });
    }

    public function down(): void
    {
        Schema::table('quiz_options', function (Blueprint $table) {
            $table->dropColumn(['match_text', 'slot']);
        });
    }
};
