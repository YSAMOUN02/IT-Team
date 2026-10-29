<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** An admin can turn off a student's whole account, or their access to one course, and turn it back on later. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('disabled_at')->nullable()->after('role');
        });
        Schema::table('enrollments', function (Blueprint $table) {
            $table->timestamp('disabled_at')->nullable()->after('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('disabled_at'));
        Schema::table('enrollments', fn (Blueprint $table) => $table->dropColumn('disabled_at'));
    }
};
