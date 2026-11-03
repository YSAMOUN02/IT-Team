<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Admin > Tracking. Page views now know which post they belong to (visits already recorded are
 * linked here too), and banners count views: how often a banner was actually on screen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('page_views', function (Blueprint $table) {
            $table->foreignId('article_id')->nullable()->after('course_id')->constrained()->nullOnDelete();
            $table->index(['created_at', 'article_id']);
        });

        Schema::create('ad_views', function (Blueprint $table) {
            $table->id();
            $table->string('placement', 40);
            $table->char('visitor_id', 32)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['placement', 'created_at']);
        });

        // Paths are stored decoded and cut at 255 characters, as TrackController does.
        $sections = ['tutorial' => 'tutorials', 'news' => 'news'];
        foreach (DB::table('articles')->get(['id', 'type', 'slug']) as $article) {
            if (isset($sections[$article->type])) {
                DB::table('page_views')
                    ->where('path', mb_substr('/'.$sections[$article->type].'/'.$article->slug, 0, 255))
                    ->update(['article_id' => $article->id, 'kind' => 'post']);
            }
        }
    }

    public function down(): void
    {
        DB::table('page_views')->where('kind', 'post')->update(['kind' => 'page']);
        Schema::table('page_views', function (Blueprint $table) {
            $table->dropForeign(['article_id']);
            $table->dropIndex(['created_at', 'article_id']);
            $table->dropColumn('article_id');
        });
        Schema::dropIfExists('ad_views');
    }
};
