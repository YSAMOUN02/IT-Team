<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class PageController extends Controller
{
    use HandlesImageUploads;

    public function index()
    {
        return view('admin.pages.index', ['pages' => Page::orderBy('id')->get()]);
    }

    public function edit(Page $page)
    {
        return view('admin.pages.form', ['page' => $page, 'definition' => $page->templateDefinition()]);
    }

    public function update(Request $request, Page $page)
    {
        $definition = $page->templateDefinition();

        // "Founder › Photo" in error messages instead of "uploads.founder.image".
        $labels = [];
        foreach ($definition['groups'] as $group => $config) {
            foreach ($config['fields'] as $field => $meta) {
                if ($meta['type'] === 'image') {
                    $labels["uploads.$group.$field"] = $config['label'].' › '.$meta['label'];
                }
            }
        }

        $request->validate([
            'title' => ['required', 'string', 'max:190'],
            'meta_description' => ['nullable', 'string', 'max:300'],
            'body' => ['nullable', 'string'],
            'uploads.*.*' => $this->imageRule(),
        ], $this->imageMessages($labels));

        $sections = $page->sections ?? [];

        foreach ($definition['groups'] as $group => $config) {
            foreach ($config['fields'] as $field => $meta) {
                $key = "$group.$field";
                $current = Arr::get($sections, $key);

                $value = match ($meta['type']) {
                    'image' => $this->imageValue($request, $key, $current),
                    'list' => array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) $request->input("sections.$key", ''))))),
                    default => $request->input("sections.$key"),
                };
                Arr::set($sections, $key, $value);
            }
        }

        $page->fill($request->only('title', 'meta_description'));
        if ($definition['body'] ?? false) {
            $page->body = $request->input('body');
        }
        $page->sections = $sections;
        $page->is_published = $request->boolean('is_published');
        $page->save();

        return redirect()->route('admin.pages.edit', $page)->with('status', 'Page saved.');
    }

    private function imageValue(Request $request, string $key, ?string $current): ?string
    {
        if ($request->hasFile("uploads.$key")) {
            Media::delete($current);

            return Media::storeImage($request->file("uploads.$key"), 'pages');
        }
        if ($request->boolean("remove.$key")) {
            Media::delete($current);

            return null;
        }

        return $current;
    }
}
