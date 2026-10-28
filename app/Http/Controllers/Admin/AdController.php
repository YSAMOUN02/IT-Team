<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdClick;
use App\Models\Setting;
use App\Support\Ads;
use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/** Admin > Ads: per placement your own banner image or Google AdSense, plus the AdSense account. */
class AdController extends Controller
{
    public function edit()
    {
        return view('admin.ads', [
            'placements' => config('ads'),
            'publisher' => Ads::publisherId(),
            'values' => Setting::allCached(),
            'clicks' => AdClick::countsSince(now()->subDays(30)),
        ]);
    }

    public function update(Request $request)
    {
        $placements = array_keys(config('ads'));

        // "t.me/teamit" or "www.facebook.com/…" without https:// still works as a link.
        foreach ($placements as $key) {
            $link = trim((string) $request->input("ad_{$key}_link"));
            if ($link !== '' && ! str_starts_with($link, '/') && ! preg_match('#^https?://#i', $link)) {
                $request->merge(["ad_{$key}_link" => 'https://'.$link]);
            }
        }

        $rules = [
            'ads_publisher_id' => ['nullable', 'regex:/^(ca-)?pub-\d{10,20}$/'],
            'ads_txt_extra' => ['nullable', 'string', 'max:2000'],
        ];
        $image = ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,webp', 'max:8192'];
        foreach ($placements as $key) {
            $rules["ad_{$key}_type"] = ['nullable', Rule::in(array_keys(Ads::TYPES))];
            $rules["ad_{$key}_image"] = $image;
            $rules["ad_{$key}_image_phone"] = $image;
            $rules["ad_{$key}_link"] = ['nullable', 'string', 'max:500', 'regex:#^(/(?!/)\S*|https?://[^\s/]+\.[^\s]+)$#i'];
            $rules["ad_{$key}_alt"] = ['nullable', 'string', 'max:200'];
            $rules["ad_{$key}_slot"] = ['nullable', 'regex:/^\d{6,20}$/'];
            $rules["ad_{$key}_format"] = ['nullable', Rule::in(array_keys(Ads::FORMATS))];
            $rules["ad_{$key}_code"] = ['nullable', 'string', 'max:5000'];
        }
        $messages = [
            'ads_publisher_id.regex' => 'The publisher ID looks like ca-pub-1234567890123456.',
            'regex' => 'An ad slot ID is the number from AdSense, e.g. 1234567890.',
            'mimes' => 'Use a JPG, PNG, WebP or GIF picture.',
        ];
        foreach ($placements as $key) {
            $messages["ad_{$key}_link.regex"] = 'Use a page of this site (/courses/pivot-table) or a full web address (https://t.me/…).';
        }
        $data = $request->validate($rules, $messages);

        $current = Setting::allCached();
        $values = [
            'ads_publisher_id' => $data['ads_publisher_id'] ?? null,
            'ads_auto' => $request->boolean('ads_auto') ? '1' : null,
            'ads_txt_extra' => $data['ads_txt_extra'] ?? null,
        ];
        foreach ($placements as $key) {
            $values["ad_{$key}_enabled"] = $request->boolean("ad_{$key}_enabled") ? '1' : null;
            $values["ad_{$key}_type"] = $data["ad_{$key}_type"] ?? Ads::type($key);
            $values["ad_{$key}_link"] = $data["ad_{$key}_link"] ?? null;
            $values["ad_{$key}_alt"] = $data["ad_{$key}_alt"] ?? null;
            $values["ad_{$key}_slot"] = $data["ad_{$key}_slot"] ?? null;
            $values["ad_{$key}_format"] = $data["ad_{$key}_format"] ?? null;
            $values["ad_{$key}_code"] = $data["ad_{$key}_code"] ?? null;
            foreach (["ad_{$key}_image", "ad_{$key}_image_phone"] as $field) {
                if ($request->hasFile($field)) {
                    Media::delete($current[$field] ?? null);
                    [$values[$field], $values["{$field}_size"]] = $this->storeBanner($request->file($field), $key);
                } elseif ($request->boolean("remove_{$field}")) {
                    Media::delete($current[$field] ?? null);
                    $values[$field] = $values["{$field}_size"] = null;
                }
            }
        }
        Setting::put($values);

        return redirect()->route('admin.ads.edit')->with('status', 'Ad settings saved.');
    }

    /**
     * Save a banner (big photos scaled down, animated GIFs left as they are) and
     * return [path, "widthxheight"].
     */
    private function storeBanner(UploadedFile $file, string $placement): array
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension());
        $bytes = Media::shrink((string) file_get_contents($file->getRealPath()), $extension, 2400);
        $path = Media::put($bytes, 'promos', 'banner-'.str_replace('_', '-', $placement), $extension);
        $size = @getimagesizefromstring($bytes);

        return [$path, $size ? $size[0].'x'.$size[1] : null];
    }
}
