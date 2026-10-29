<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Support\Media;
use App\Support\Slug;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ArticleController extends Controller
{
    use HandlesImageUploads;

    public function index(Request $request)
    {
        $type = $this->type($request->query('type'));
        $status = in_array($request->query('status'), ['live', 'hidden'], true) ? $request->query('status') : 'all';

        return view('admin.articles.index', [
            'type' => $type,
            'status' => $status,
            'totals' => [
                'all' => Article::ofType($type)->count(),
                'live' => Article::ofType($type)->where('is_published', true)->count(),
                'hidden' => Article::ofType($type)->where('is_published', false)->count(),
            ],
            'articles' => Article::ofType($type)
                ->when($request->query('q'), fn ($q, $term) => $q->where('title', 'like', '%'.$term.'%'))
                ->when($status !== 'all', fn ($q) => $q->where('is_published', $status === 'live'))
                ->orderByDesc('published_at')->orderByDesc('id')
                ->paginate(20)->withQueryString(),
        ]);
    }

    public function create(Request $request)
    {
        return view('admin.articles.form', ['article' => new Article([
            'type' => $this->type($request->query('type')),
            'is_published' => true,
            'published_at' => now(),
        ])]);
    }

    public function store(Request $request)
    {
        return $this->save($request, new Article);
    }

    public function edit(Article $article)
    {
        return view('admin.articles.form', compact('article'));
    }

    public function update(Request $request, Article $article)
    {
        return $this->save($request, $article);
    }

    public function destroy(Article $article)
    {
        Media::delete($article->image);
        $article->delete();

        return redirect()->route('admin.articles.index', ['type' => $article->type])->with('status', 'Deleted.');
    }

    private function save(Request $request, Article $article)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(Article::TYPES))],
            'title' => ['required', 'string', 'max:190'],
            'slug' => ['nullable', 'string', 'max:190'],
            'excerpt' => ['nullable', 'string', 'max:2000'],
            'body' => ['nullable', 'string'],
            'published_at' => ['nullable', 'date'],
            'image' => $this->imageRule(),
        ], $this->imageMessages(['image' => 'Featured image']));
        unset($data['image']);

        $article->fill($data);
        $article->slug = Slug::unique(($data['slug'] ?? null) ?: $data['title'], Article::ofType($data['type']), $article->id);
        $article->is_published = $request->boolean('is_published');
        $this->syncImage($request, $article, 'image', 'articles');
        $article->save();

        return redirect()->route('admin.articles.edit', $article)->with('status', 'Saved.');
    }

    private function type(?string $type): string
    {
        return array_key_exists((string) $type, Article::TYPES) ? $type : 'tutorial';
    }
}
