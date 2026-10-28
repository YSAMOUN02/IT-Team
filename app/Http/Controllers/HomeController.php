<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Course;
use App\Models\Page;
use App\Models\Partner;
use App\Models\Testimonial;

class HomeController extends Controller
{
    public function __invoke()
    {
        return view('home', [
            'page' => Page::findBySlug('home') ?? new Page(['title' => config('app.name'), 'template' => 'home']),
            'courses' => Course::published()->take(6)->get(),
            'lessons' => Article::ofType('tutorial')->published()->take(3)->get(),
            'news' => Article::ofType('news')->published()->take(3)->get(),
            'testimonials' => Testimonial::published()->get(),
            'partners' => Partner::published()->get(),
        ]);
    }
}
