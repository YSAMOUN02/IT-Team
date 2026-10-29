<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\Media;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    use HandlesImageUploads;

    /** The favicon may also be an .ico file. */
    private const IMAGE_TYPES = [...Media::IMAGE_TYPES, 'ico'];

    public function edit()
    {
        return view('admin.settings', [
            'groups' => config('site_settings'),
            'values' => Setting::allCached(),
        ]);
    }

    public function update(Request $request)
    {
        $numbers = [];
        $images = [];
        foreach (config('site_settings') as $group) {
            foreach ($group['fields'] as $key => $meta) {
                if ($meta['type'] === 'number') {
                    $numbers["settings.$key"] = ['nullable', 'integer', 'min:'.($meta['min'] ?? 0), 'max:'.($meta['max'] ?? 999)];
                }
                if ($meta['type'] === 'image') {
                    $images["uploads.$key"] = $meta['label'];
                }
            }
        }

        $request->validate($numbers + [
            'uploads.*' => $this->imageRule(self::IMAGE_TYPES),
            'socials' => ['array', 'max:30'],
            'socials.*.platform' => ['nullable', 'in:'.implode(',', array_keys(config('socials')))],
            'socials.*.url' => ['nullable', 'url', 'max:500'],
        ], [
            'socials.*.url.url' => 'Each social link must be a full address starting with https://',
        ] + $this->imageMessages($images, self::IMAGE_TYPES));

        $current = Setting::allCached();
        $values = [];

        foreach (config('site_settings') as $group) {
            foreach ($group['fields'] as $key => $meta) {
                if ($meta['type'] === 'socials') {
                    // Rows of platform + link; empty rows are dropped, order is kept.
                    $values[$key] = json_encode(array_values(array_filter(array_map(
                        fn ($row) => ['platform' => $row['platform'] ?? 'website', 'url' => trim((string) ($row['url'] ?? ''))],
                        (array) $request->input('socials', [])
                    ), fn ($row) => $row['url'] !== '')), JSON_UNESCAPED_SLASHES);
                    continue;
                }
                if ($meta['type'] === 'image') {
                    if ($request->hasFile("uploads.$key")) {
                        Media::delete($current[$key] ?? null);
                        $values[$key] = Media::storeImage($request->file("uploads.$key"), 'settings');
                    } elseif ($request->boolean("remove.$key")) {
                        Media::delete($current[$key] ?? null);
                        $values[$key] = null;
                    }
                    continue;
                }
                $values[$key] = $request->input("settings.$key");
            }
        }

        Setting::put($values);

        return redirect()->route('admin.settings.edit')->with('status', 'Settings saved.');
    }
}
