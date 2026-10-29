<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Support\Media;
use Illuminate\Http\Request;

class PartnerController extends Controller
{
    use HandlesImageUploads;

    public function index()
    {
        return view('admin.partners.index', ['partners' => Partner::orderBy('sort_order')->orderBy('id')->get()]);
    }

    public function create()
    {
        return view('admin.partners.form', ['partner' => new Partner(['is_published' => true])]);
    }

    public function store(Request $request)
    {
        return $this->save($request, new Partner);
    }

    public function edit(Partner $partner)
    {
        return view('admin.partners.form', compact('partner'));
    }

    public function update(Request $request, Partner $partner)
    {
        return $this->save($request, $partner);
    }

    public function destroy(Partner $partner)
    {
        Media::delete($partner->logo);
        $partner->delete();

        return redirect()->route('admin.partners.index')->with('status', 'Partner deleted.');
    }

    private function save(Request $request, Partner $partner)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'url' => ['nullable', 'url', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'logo' => $this->imageRule(),
        ], $this->imageMessages(['logo' => 'Logo']));
        unset($data['logo']);

        $partner->fill($data);
        $partner->sort_order = (int) ($data['sort_order'] ?? 0);
        $partner->is_published = $request->boolean('is_published');
        $this->syncImage($request, $partner, 'logo', 'partners');
        $partner->save();

        return redirect()->route('admin.partners.edit', $partner)->with('status', 'Partner saved.');
    }
}
