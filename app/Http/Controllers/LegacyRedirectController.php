<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Course;

/** 301s from the old WordPress permalinks to the new routes. */
class LegacyRedirectController extends Controller
{
    public function course(string $slug)
    {
        $course = Course::where('slug', $slug)->first();

        return redirect($course ? $course->url() : route('courses.index'), 301);
    }

    public function tutorial(string $slug)
    {
        $article = Article::ofType('tutorial')->where('slug', $slug)->first();

        return redirect($article ? $article->url() : route('tutorials.index'), 301);
    }

    public function post(string $year, string $month, string $day, string $slug)
    {
        // WordPress posts now live in News.
        $article = Article::ofType('news')->where('slug', $slug)->first();

        return redirect($article ? $article->url() : route('news.index'), 301);
    }
}
