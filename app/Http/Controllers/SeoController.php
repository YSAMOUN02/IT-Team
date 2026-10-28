<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Course;
use App\Models\Page;

/** /sitemap.xml and /robots.txt, generated from the live content. */
class SeoController extends Controller
{
    public function sitemap()
    {
        $urls = collect();
        $add = fn (string $loc, $lastmod = null, ?string $image = null) => $urls->push([
            'loc' => $loc, 'lastmod' => $lastmod?->toAtomString(), 'image' => $image ? url($image) : null,
        ]);

        $pages = Page::where('is_published', true)->get()->keyBy('slug');
        $add(url('/'), $pages['home']->updated_at ?? null);
        foreach (['courses' => 'courses.index', 'tutorials' => 'tutorials.index', 'news' => 'news.index', 'about' => 'about', 'contact' => 'contact'] as $slug => $route) {
            if ($slug === 'news' && ! Article::ofType('news')->published()->exists()) {
                continue; // no empty list pages
            }
            $add(route($route), $pages[$slug]->updated_at ?? null);
        }

        foreach (Course::published()->get() as $course) {
            $add($course->url(), $course->updated_at, $course->mediaUrl());
        }
        foreach (Article::published()->get() as $article) {
            $add($article->url(), $article->updated_at, $article->mediaUrl());
        }

        return response()->view('seo.sitemap', ['urls' => $urls], 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function robots()
    {
        // Keep test/staging copies out of search engines; only production is indexed.
        if (! app()->isProduction()) {
            return response("User-agent: *\nDisallow: /\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $private = ['/admin', '/my/', '/checkout/', '/orders/', '/learn/', '/search', '/login', '/register',
            '/forgot-password', '/reset-password/', '/newsletter/', '/certificates/', '/payway/', '/auth/',
            '/courses/*/checkout', '/courses/*/enroll'];

        $lines = array_merge(['User-agent: *'], array_map(fn ($path) => 'Disallow: '.$path, $private), ['', 'Sitemap: '.url('sitemap.xml')]);

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
