<?php

namespace App\Services;

class FileStorageService
{
    private $basePath;

    public function __construct($basePath = null)
    {
        $this->basePath = $basePath ?: BASE_PATH . '/storage/private_uploads';
    }

    public function privatePath($relativePath)
    {
        return rtrim($this->basePath, '/\\') . '/' . ltrim($relativePath, '/\\');
    }
}
