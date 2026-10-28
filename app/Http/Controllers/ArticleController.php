<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Page;

class ArticleController extends Controller
{
    public function tutorials()
    {
        return $this->listing('tutorial', 'tutorials');
    }

    public function newsIndex()
    {
        return $this->listing('news', 'news');
    }

    public function tutorial(string $slug)
    {
        return $this->detail('tutorial', $slug);
    }

    public function newsItem(string $slug)
    {
        return $this->detail('news', $slug);
    }

    private function listing(string $type, string $pageSlug)
    {
        return view('articles.index', [
            'type' => $type,
            'page' => Page::findBySlug($pageSlug),
            'articles' => Article::ofType($type)->published()->paginate(12),
        ]);
    }

    private function detail(string $type, string $slug)
    {
        $article = Article::ofType($type)->published()->where('slug', $slug)->firstOrFail();

        return view('articles.show', [
            'article' => $article,
            'related' => Article::ofType($type)->published()->whereKeyNot($article->id)->take(3)->get(),
        ]);
    }
}
