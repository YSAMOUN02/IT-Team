<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Short answers are marked by a teacher. "marks" keeps the teacher's right / wrong per question;
 * "needs_marking" is set while a submitted try has short answers nobody has marked yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->json('marks')->nullable()->after('answers');
            $table->boolean('needs_marking')->default(false)->after('passed');
            $table->index('needs_marking');
        });
    }

    public function down(): void
    {
        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->dropIndex(['needs_marking']);
            $table->dropColumn(['marks', 'needs_marking']);
        });
    }
};
