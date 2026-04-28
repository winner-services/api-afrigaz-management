<?php

namespace App\Services;

class ImageService
{
    public function getBase64(?string $filePath): string
    {
        if (!$filePath) {
            return asset('images/default-logo.png');
        }

        $path = storage_path('app/public/' . $filePath);

        if (file_exists($path)) {
            $mime = mime_content_type($path);
            $data = base64_encode(file_get_contents($path));
            return "data:$mime;base64,$data";
        }

        return asset('images/default-logo.png');
    }

    public function getUrl(?string $filePath): string
    {
        if (!$filePath) {
            return asset('images/default-logo.png');
        }

        return asset('storage/' . $filePath);
    }
}
