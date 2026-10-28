<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('wp_id')->nullable()->unique();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('icon')->nullable();
            $table->string('badge')->nullable();
            $table->text('summary')->nullable();
            $table->longText('body')->nullable();
            $table->string('image')->nullable();
            $table->string('price_prefix')->nullable();
            $table->string('price')->nullable();
            $table->string('price_suffix')->nullable();
            $table->json('features')->nullable();
            $table->string('cta_label')->nullable();
            $table->string('cta_url')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });

        // Tutorials (the WP "tdcpt_location" type) and blog posts share one table.
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('wp_id')->nullable();
            $table->string('type', 20)->default('tutorial');
            $table->string('title');
            $table->string('slug');
            $table->text('excerpt')->nullable();
            $table->longText('body')->nullable();
            $table->string('image')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_published')->default(true);
            $table->timestamps();

            $table->unique(['type', 'slug']);
            $table->unique(['type', 'wp_id']);
            $table->index(['type', 'is_published', 'published_at']);
        });

        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('wp_id')->nullable()->unique();
            $table->string('name');
            $table->string('role')->nullable();
            $table->text('quote');
            $table->string('photo')->nullable();
            $table->unsignedTinyInteger('rating')->default(5);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });

        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('logo')->nullable();
            $table->string('url')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });

        // Fixed pages (home, about, contact, coming soon). Their editable fields live in
        // `sections`, shaped by the template definition in config/page_templates.php.
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('wp_id')->nullable();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('template', 40)->default('default');
            $table->json('sections')->nullable();
            $table->longText('body')->nullable();
            $table->string('meta_description', 300)->nullable();
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('inquiries', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('subject');
            $table->text('message');
            $table->string('ip', 45)->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inquiries');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('pages');
        Schema::dropIfExists('partners');
        Schema::dropIfExists('testimonials');
        Schema::dropIfExists('articles');
        Schema::dropIfExists('courses');
    }
};
