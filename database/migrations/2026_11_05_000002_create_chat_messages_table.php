<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A student's conversation with the school: one per student. Students write from My account > Chat;
 * admins answer from Admin > Chat, by chat or by email (an email is kept here too, with its subject).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete(); // whose conversation
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete(); // who wrote it
            $table->boolean('from_student'); // false = an admin, writing for the school
            $table->text('body');
            $table->string('subject', 200)->nullable(); // sent by email: its subject
            $table->text('signature')->nullable(); // sent by email: the signature under it
            $table->timestamp('emailed_at')->nullable();
            $table->timestamp('read_at')->nullable(); // seen by the other side
            $table->timestamps();

            $table->index(['student_id', 'id']);
            $table->index(['from_student', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
