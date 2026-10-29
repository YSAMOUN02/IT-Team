<?php

namespace App\Http\Controllers\Admin;

use App\Support\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

trait HandlesImageUploads
{
    /**
     * Apply the "<column>" file input and "remove_<column>" checkbox from the
     * request to the model's image column, deleting the replaced file.
     */
    protected function syncImage(Request $request, Model $model, string $column, string $folder): void
    {
        if ($request->hasFile($column)) {
            Media::delete($model->getOriginal($column));
            $model->{$column} = Media::storeImage($request->file($column), $folder);
        } elseif ($request->boolean('remove_'.$column)) {
            Media::delete($model->getOriginal($column));
            $model->{$column} = null;
        }
    }

    protected function imageRule(array $types = Media::IMAGE_TYPES): array
    {
        return ['nullable', 'file', 'mimes:'.implode(',', $types), 'max:'.Media::uploadMaxKb()];
    }

    /**
     * Messages that say which picture and why, e.g. "Cover image: the picture is bigger than 20 MB."
     * $labels = [input name => label]; dots work for nested inputs ("uploads.logo").
     */
    protected function imageMessages(array $labels, array $types = Media::IMAGE_TYPES): array
    {
        $limit = Media::sizeLabel(Media::uploadMaxKb());
        // "JPG, PNG, GIF, WebP or SVG"
        $names = array_values(array_map(fn ($type) => $type === 'webp' ? 'WebP' : strtoupper($type), array_diff($types, ['jpeg'])));
        $list = count($names) > 1 ? implode(', ', array_slice($names, 0, -1)).' or '.end($names) : implode('', $names);
        $messages = [];
        foreach ($labels as $field => $label) {
            $messages["$field.uploaded"] = "$label: the picture did not arrive. It may be bigger than this server accepts ($limit).";
            $messages["$field.mimes"] = "$label: use a $list picture. iPhone HEIC photos need to be saved as JPG first.";
            $messages["$field.max"] = "$label: the picture is bigger than $limit. Choose a smaller one.";
        }

        return $messages;
    }
}
