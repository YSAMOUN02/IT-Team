<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\DocxReader;
use App\Support\Media;
use Illuminate\Http\Request;
use Throwable;

/** Uploads from the rich-text editor (TinyMCE expects {"location": url}). */
class UploadController extends Controller
{
    /** An image picked, dropped or pasted (screenshots) into the editor. */
    public function __invoke(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,gif,webp', 'max:'.Media::uploadMaxKb()],
        ], [
            'file.max' => 'The picture is bigger than '.Media::sizeLabel(Media::uploadMaxKb()).'.',
            'file.mimes' => 'Use a JPG, PNG, WebP or GIF picture.',
        ]);

        // Saved under the type it really is; GIFs are left as they are (they may be animated).
        $path = Media::storeImage($request->file('file'), 'editor', 1600);

        return response()->json(['location' => Media::url($path)]);
    }

    /** A Word document (.docx) turned into editor HTML, with its pictures saved. */
    public function word(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'extensions:docx', 'max:51200'],
        ], [
            'file.extensions' => 'Choose a Word document saved as .docx (in Word: File → Save As → Word Document).',
        ]);

        try {
            $doc = new DocxReader($request->file('file')->getRealPath());
            $blocks = $doc->blocks();
        } catch (Throwable) {
            return response()->json(['message' => 'This file could not be read as a Word document.'], 422);
        }

        $name = pathinfo($request->file('file')->getClientOriginalName(), PATHINFO_FILENAME);
        $images = 0;
        foreach ($blocks as &$block) {
            $block['images'] = array_values(array_filter(array_map(fn ($picture) => $doc->storeImage($picture, 'editor', $name), $block['images'])));
            $images += count($block['images']);
        }
        unset($block);

        return response()->json([
            'html' => DocxReader::polish(DocxReader::html($blocks, fn ($url) => $url)),
            'paragraphs' => count($blocks),
            'images' => $images,
        ]);
    }
}
