<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A teacher can give part of a question's points (e.g. 70% of 2 points = 1.4), so a score needs one decimal.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->decimal('score', 8, 1)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->unsignedInteger('score')->default(0)->change();
        });
    }
};