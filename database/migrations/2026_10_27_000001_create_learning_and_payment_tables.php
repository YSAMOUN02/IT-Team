<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Admins manage the site; students buy courses and learn.
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('student')->after('email');
            $table->string('phone', 40)->nullable()->after('role');
        });
        DB::table('users')->update(['role' => 'admin']); // everyone so far is an admin

        Schema::table('courses', function (Blueprint $table) {
            $table->decimal('price_amount', 10, 2)->nullable()->after('price');
            $table->string('currency', 3)->default('USD')->after('price_amount');
            $table->unsignedInteger('access_days')->nullable()->after('currency'); // null = lifetime
            $table->boolean('certificate_enabled')->default(true)->after('access_days');
        });

        // Curriculum: course -> modules -> lessons and quizzes (Cisco NetAcad style).
        Schema::create('course_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('course_lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_module_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->unsignedInteger('position')->default(0);
            $table->string('duration', 20)->nullable();
            $table->string('video_url')->nullable();
            $table->string('video_path')->nullable();
            $table->longText('body')->nullable();
            $table->string('attachment_path')->nullable(); // practice workbook
            $table->string('attachment_name')->nullable();
            $table->string('solution_path')->nullable();   // its answers
            $table->string('solution_name')->nullable();
            $table->boolean('is_preview')->default(false);
            $table->boolean('is_published')->default(true);
            $table->timestamps();

            $table->unique(['course_id', 'slug']);
        });

        Schema::create('quizzes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_module_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20)->default('quiz'); // practice, quiz, mock, final
            $table->string('title');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->unsignedInteger('time_limit')->nullable(); // minutes
            $table->unsignedTinyInteger('pass_percent')->default(70);
            $table->unsignedInteger('max_attempts')->nullable(); // null = unlimited
            $table->boolean('shuffle_questions')->default(false);
            $table->boolean('show_answers')->default(true); // review correct answers after submitting
            $table->boolean('is_preview')->default(false);
            $table->boolean('is_published')->default(true);
            $table->timestamps();

            $table->unique(['course_id', 'slug']);
        });

        Schema::create('quiz_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20)->default('single'); // single, multiple, true_false
            $table->text('prompt');
            $table->string('image')->nullable();
            $table->text('explanation')->nullable();
            $table->unsignedSmallInteger('points')->default(1);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('quiz_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_question_id')->constrained()->cascadeOnDelete();
            $table->text('text');
            $table->boolean('is_correct')->default(false);
            $table->unsignedInteger('position')->default(0);
        });

        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('question_order')->nullable();
            $table->json('answers')->nullable();
            $table->unsignedInteger('score')->default(0);
            $table->unsignedInteger('max_score')->default(0);
            $table->unsignedTinyInteger('percent')->default(0);
            $table->boolean('passed')->default(false);
            $table->timestamp('started_at');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['quiz_id', 'user_id']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique(); // PayWay tran_id
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            $table->string('course_title');
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3);
            $table->unsignedInteger('access_days')->nullable();
            $table->string('payment_option', 30)->nullable();
            $table->string('gateway', 20)->default('payway');
            $table->string('status', 20)->default('pending'); // pending, paid, failed, cancelled, refunded, review
            $table->string('gateway_status', 30)->nullable();
            $table->string('apv', 40)->nullable();
            $table->json('gateway_payload')->nullable();
            $table->string('buyer_name');
            $table->string('buyer_email');
            $table->string('buyer_phone', 40)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 20)->default('payment'); // payment, manual
            $table->timestamp('starts_at');
            $table->timestamp('expires_at')->nullable(); // null = lifetime
            $table->timestamp('completed_at')->nullable();
            $table->string('certificate_code', 20)->nullable()->unique();
            $table->timestamps();

            $table->unique(['user_id', 'course_id']);
        });

        Schema::create('lesson_progress', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_lesson_id')->constrained()->cascadeOnDelete();
            $table->timestamp('completed_at');
            $table->primary(['user_id', 'course_lesson_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_progress');
        Schema::dropIfExists('enrollments');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('quiz_attempts');
        Schema::dropIfExists('quiz_options');
        Schema::dropIfExists('quiz_questions');
        Schema::dropIfExists('quizzes');
        Schema::dropIfExists('course_lessons');
        Schema::dropIfExists('course_modules');
        Schema::table('courses', fn (Blueprint $table) => $table->dropColumn(['price_amount', 'currency', 'access_days', 'certificate_enabled']));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['role', 'phone']));
    }
};
