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

    public function storeUploadedFile(array $file, $directory, $prefix = 'arquivo')
    {
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new \InvalidArgumentException('Arquivo invalido para upload.');
        }

        $directory = trim((string) $directory, '/\\');
        $absoluteDirectory = $this->privatePath($directory);

        if (!is_dir($absoluteDirectory)) {
            if (!@mkdir($absoluteDirectory, 0775, true) && !is_dir($absoluteDirectory)) {
                throw new \RuntimeException('Nao foi possivel criar o diretorio de upload.');
            }
        }

        $originalName = isset($file['name']) ? basename($file['name']) : 'arquivo';
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $safeName = $prefix . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(6));
        if ($extension !== '') {
            $safeName .= '.' . $extension;
        }

        $relativePath = $directory . '/' . $safeName;
        $absolutePath = $this->privatePath($relativePath);

        if (!move_uploaded_file($file['tmp_name'], $absolutePath)) {
            throw new \RuntimeException('Nao foi possivel salvar o arquivo enviado.');
        }

        return array(
            'relative_path' => $relativePath,
            'absolute_path' => $absolutePath,
            'original_name' => $originalName,
            'mime_type' => isset($file['type']) ? $file['type'] : null,
            'size' => isset($file['size']) ? (int) $file['size'] : null,
        );
    }
}
