<?php

namespace App\Http\Controllers;

use App\Models\Page;

class PageController extends Controller
{
    public function show(string $slug)
    {
        $page = Page::findBySlug($slug) ?? abort(404);
        $view = view()->exists('pages.'.$page->template) ? 'pages.'.$page->template : 'pages.default';

        return view($view, ['page' => $page]);
    }
}
