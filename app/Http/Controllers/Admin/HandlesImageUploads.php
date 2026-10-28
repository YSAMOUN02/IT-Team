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
            $model->{$column} = Media::store($request->file($column), $folder);
        } elseif ($request->boolean('remove_'.$column)) {
            Media::delete($model->getOriginal($column));
            $model->{$column} = null;
        }
    }

    protected function imageRule(): array
    {
        return ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,webp,svg', 'max:8192'];
    }
}
