<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Course;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __invoke(Request $request)
    {
        $term = trim((string) $request->query('s', ''));
        $like = '%'.addcslashes($term, '%_\\').'%';

        $courses = $term === '' ? collect() : Course::published()
            ->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('summary', 'like', $like)->orWhere('body', 'like', $like))
            ->get();

        $articles = $term === '' ? collect() : Article::published()
            ->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('excerpt', 'like', $like)->orWhere('body', 'like', $like))
            ->take(30)->get();

        return view('search', compact('term', 'courses', 'articles'));
    }
}
