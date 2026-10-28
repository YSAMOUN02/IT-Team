<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Course;
use App\Models\Inquiry;
use App\Models\Page;
use App\Models\Partner;
use App\Models\Testimonial;
use Illuminate\Http\Request;

/** The search box in the admin top bar: titles and names across all content. */
class SearchController extends Controller
{
    public function __invoke(Request $request)
    {
        $term = trim((string) $request->query('q', ''));
        $like = '%'.addcslashes($term, '%_\\').'%';
        $groups = [];

        if ($term !== '') {
            $groups['Courses'] = Course::where('title', 'like', $like)->orWhere('summary', 'like', $like)->take(10)->get()
                ->map(fn ($c) => [$c->title, route('admin.courses.edit', $c), $c->mediaUrl() ?? $c->mediaUrl('icon'), $c->summary]);

            foreach (Article::TYPES as $type => $label) {
                $groups[$label] = Article::ofType($type)->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('excerpt', 'like', $like))
                    ->take(10)->get()
                    ->map(fn ($a) => [$a->title, route('admin.articles.edit', $a), $a->mediaUrl(), $a->published_at?->format('d M Y')]);
            }

            $groups['Pages'] = Page::where('title', 'like', $like)->orWhere('slug', 'like', $like)->get()
                ->map(fn ($p) => [$p->title, route('admin.pages.edit', $p), null, '/'.$p->slug]);
            $groups['Testimonials'] = Testimonial::where('name', 'like', $like)->orWhere('quote', 'like', $like)->take(10)->get()
                ->map(fn ($t) => [$t->name, route('admin.testimonials.edit', $t), $t->mediaUrl('photo'), $t->role]);
            $groups['Partners'] = Partner::where('name', 'like', $like)->take(10)->get()
                ->map(fn ($p) => [$p->name, route('admin.partners.edit', $p), $p->mediaUrl('logo'), $p->url]);
            $groups['Messages'] = Inquiry::where(fn ($q) => $q->where('subject', 'like', $like)->orWhere('name', 'like', $like)->orWhere('email', 'like', $like))
                ->latest()->take(10)->get()
                ->map(fn ($i) => [$i->subject, route('admin.inquiries.show', $i), null, $i->name.' · '.$i->created_at->format('d M Y')]);

            $groups = array_filter($groups, fn ($items) => $items->isNotEmpty());
        }

        return view('admin.search', compact('term', 'groups'));
    }
}
