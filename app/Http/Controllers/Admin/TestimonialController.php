<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Testimonial;
use App\Support\Media;
use Illuminate\Http\Request;

class TestimonialController extends Controller
{
    use HandlesImageUploads;

    public function index()
    {
        return view('admin.testimonials.index', ['testimonials' => Testimonial::orderBy('sort_order')->orderBy('id')->get()]);
    }

    public function create()
    {
        return view('admin.testimonials.form', ['testimonial' => new Testimonial(['is_published' => true, 'rating' => 5])]);
    }

    public function store(Request $request)
    {
        return $this->save($request, new Testimonial);
    }

    public function edit(Testimonial $testimonial)
    {
        return view('admin.testimonials.form', compact('testimonial'));
    }

    public function update(Request $request, Testimonial $testimonial)
    {
        return $this->save($request, $testimonial);
    }

    public function destroy(Testimonial $testimonial)
    {
        Media::delete($testimonial->photo);
        $testimonial->delete();

        return redirect()->route('admin.testimonials.index')->with('status', 'Testimonial deleted.');
    }

    private function save(Request $request, Testimonial $testimonial)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'role' => ['nullable', 'string', 'max:190'],
            'quote' => ['required', 'string', 'max:5000'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'photo' => $this->imageRule(),
        ], $this->imageMessages(['photo' => 'Photo']));
        unset($data['photo']);

        $testimonial->fill($data);
        $testimonial->sort_order = (int) ($data['sort_order'] ?? 0);
        $testimonial->is_published = $request->boolean('is_published');
        $this->syncImage($request, $testimonial, 'photo', 'testimonials');
        $testimonial->save();

        return redirect()->route('admin.testimonials.edit', $testimonial)->with('status', 'Testimonial saved.');
    }
}
