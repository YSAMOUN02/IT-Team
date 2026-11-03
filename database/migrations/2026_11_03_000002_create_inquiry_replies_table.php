<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Replies sent from Admin > Messages through the site's email (teamitcambodia@gmail.com):
 * each one is kept under its message, with the signature as it was when sent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inquiry_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inquiry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // the admin who sent it
            $table->string('to_email');
            $table->string('subject', 200);
            $table->text('body');
            $table->text('signature')->nullable();
            $table->timestamps();
        });

        Schema::table('inquiries', function (Blueprint $table) {
            $table->timestamp('replied_at')->nullable()->after('read_at');
        });
    }

    public function down(): void
    {
        Schema::table('inquiries', fn (Blueprint $table) => $table->dropColumn('replied_at'));
        Schema::dropIfExists('inquiry_replies');
    }
};
