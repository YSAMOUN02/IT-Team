<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A student who used every try at a test without passing asks the teacher (an admin) for more. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retake_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('message')->nullable(); // the student's reason
            $table->string('status', 20)->default('pending'); // pending, approved, declined
            $table->unsignedTinyInteger('extra_attempts')->default(0); // tries added when approved
            $table->text('reply')->nullable(); // the teacher's note to the student
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['quiz_id', 'user_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retake_requests');
    }
};
