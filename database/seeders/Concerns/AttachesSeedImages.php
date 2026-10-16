<?php

namespace Database\Seeders\Concerns;

use Spatie\MediaLibrary\HasMedia;

trait AttachesSeedImages
{
    /**
     * Copy database/seeders/images/{path} into the model's media collection.
     * Does nothing when the image file has not been added to the project yet.
     */
    protected function attachImage(HasMedia $model, string $path, string $collection): void
    {
        $file = database_path("seeders/images/{$path}");

        if (! file_exists($file)) {
            return;
        }

        $model->addMedia($file)->preservingOriginal()->toMediaCollection($collection);
    }
}
