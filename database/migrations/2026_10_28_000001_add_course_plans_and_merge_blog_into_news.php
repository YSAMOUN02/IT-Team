<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Course length shown to students (set by the admin) and free courses.
        Schema::table('courses', function (Blueprint $table) {
            $table->string('duration', 60)->nullable()->after('summary');
            $table->boolean('is_free')->default(false)->after('currency');
        });

        // A course is sold as one or more access plans, e.g. 1 month / 3 months / 1 year,
        // each with its own price and an optional discount (always, or between two dates).
        Schema::create('course_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('label', 60)->nullable();
            $table->unsignedInteger('days')->nullable(); // null = lifetime
            $table->decimal('price', 10, 2);
            $table->unsignedTinyInteger('discount_percent')->default(0);
            $table->date('discount_starts_at')->nullable();
            $table->date('discount_ends_at')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('plan_id')->nullable()->after('course_id')->constrained('course_plans')->nullOnDelete();
            $table->string('plan_label', 60)->nullable()->after('plan_id');
            $table->decimal('original_amount', 10, 2)->nullable()->after('amount');
            $table->unsignedTinyInteger('discount_percent')->default(0)->after('original_amount');
        });

        // The single price each course had becomes its first plan.
        foreach (DB::table('courses')->where('price_amount', '>', 0)->get() as $course) {
            DB::table('course_plans')->insert([
                'course_id' => $course->id,
                'days' => $course->access_days,
                'price' => $course->price_amount,
                'position' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        Schema::table('courses', fn (Blueprint $table) => $table->dropColumn(['price_amount', 'access_days']));

        // News and blog are one section: blog posts become news.
        foreach (DB::table('articles')->where('type', 'post')->get() as $post) {
            $slug = $post->slug;
            for ($i = 2; DB::table('articles')->where('type', 'news')->where('slug', $slug)->exists(); $i++) {
                $slug = $post->slug.'-'.$i;
            }
            DB::table('articles')->where('id', $post->id)->update(['type' => 'news', 'slug' => $slug]);
        }
        DB::table('pages')->where('slug', 'blog')->delete();
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->decimal('price_amount', 10, 2)->nullable();
            $table->unsignedInteger('access_days')->nullable();
        });
        foreach (DB::table('course_plans')->orderBy('position')->get()->unique('course_id') as $plan) {
            DB::table('courses')->where('id', $plan->course_id)->update(['price_amount' => $plan->price, 'access_days' => $plan->days]);
        }
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plan_id');
            $table->dropColumn(['plan_label', 'original_amount', 'discount_percent']);
        });
        Schema::dropIfExists('course_plans');
        Schema::table('courses', fn (Blueprint $table) => $table->dropColumn(['duration', 'is_free']));
    }
};
