<?php

namespace App\Services;

class ImageService
{
    public function toBase64OrDefault($file, $default = 'images/default-logo.png')
    {
        if (!empty($file)) {
            $path = storage_path('app/public/' . $file);

            if (file_exists($path)) {
                return "data:" . mime_content_type($path) . ";base64,"
                    . base64_encode(file_get_contents($path));
            }
        }

        return asset($default);
    }

    public function transform($model, array $fields)
    {
        foreach ($fields as $field) {
            $model->$field = $this->toBase64OrDefault($model->$field);
        }

        return $model;
    }
}
