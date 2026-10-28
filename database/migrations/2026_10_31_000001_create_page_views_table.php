<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Website visitor statistics: one row per page opened (no IP addresses are kept). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_views', function (Blueprint $table) {
            $table->id();
            $table->char('visitor_id', 32)->index();   // random, from a first-party cookie (1 year)
            $table->char('session_id', 32)->index();   // a visit: ends after 30 minutes without a page
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('path', 255);
            $table->string('title', 255)->nullable();
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete(); // course page or its checkout
            $table->string('kind', 20)->default('page');  // page | course | checkout | lesson | quiz
            $table->string('source', 40)->default('Direct');
            $table->string('referrer_host', 120)->nullable();
            $table->string('device', 10);
            $table->string('browser', 30);
            $table->string('os', 20);
            $table->unsignedInteger('seconds')->default(0); // time the page was actually on screen
            $table->timestamps();

            $table->index(['created_at', 'path']);
            $table->index(['created_at', 'course_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_views');
    }
};
