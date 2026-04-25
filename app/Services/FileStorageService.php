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

    public function storeUploadedFile(array $file, $directory, $prefix = 'arquivo', array $options = array())
    {
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new \InvalidArgumentException('Arquivo invalido para upload.');
        }

        if (!empty($file['error']) && (int) $file['error'] !== UPLOAD_ERR_OK) {
            throw new \InvalidArgumentException('Upload invalido.');
        }

        $maxSize = isset($options['max_size_bytes']) ? (int) $options['max_size_bytes'] : 0;
        if ($maxSize > 0 && !empty($file['size']) && (int) $file['size'] > $maxSize) {
            throw new \InvalidArgumentException('Arquivo excede o tamanho maximo permitido.');
        }

        $originalName = isset($file['name']) ? basename($file['name']) : 'arquivo';
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowedExtensions = isset($options['allowed_extensions']) && is_array($options['allowed_extensions'])
            ? array_map('strtolower', $options['allowed_extensions'])
            : array();

        if (!empty($allowedExtensions) && !in_array($extension, $allowedExtensions, true)) {
            throw new \InvalidArgumentException('Extensao de arquivo nao permitida.');
        }

        $mimeType = $this->detectMimeType($file['tmp_name'], isset($file['type']) ? $file['type'] : null);
        $allowedMimeTypes = isset($options['allowed_mime_types']) && is_array($options['allowed_mime_types'])
            ? array_map('strtolower', $options['allowed_mime_types'])
            : array();

        if (!empty($allowedMimeTypes) && !in_array(strtolower((string) $mimeType), $allowedMimeTypes, true)) {
            throw new \InvalidArgumentException('Tipo MIME de arquivo nao permitido.');
        }

        $directory = trim((string) $directory, '/\\');
        $absoluteDirectory = $this->privatePath($directory);

        if (!is_dir($absoluteDirectory)) {
            if (!@mkdir($absoluteDirectory, 0775, true) && !is_dir($absoluteDirectory)) {
                throw new \RuntimeException('Não foi possivel criar o diretorio de upload.');
            }
        }

        $safeName = $prefix . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(6));
        if ($extension !== '') {
            $safeName .= '.' . $extension;
        }

        $relativePath = $directory . '/' . $safeName;
        $absolutePath = $this->privatePath($relativePath);

        if (!move_uploaded_file($file['tmp_name'], $absolutePath)) {
            throw new \RuntimeException('Não foi possivel salvar o arquivo enviado.');
        }

        return array(
            'relative_path' => $relativePath,
            'absolute_path' => $absolutePath,
            'original_name' => $originalName,
            'mime_type' => $mimeType,
            'size' => isset($file['size']) ? (int) $file['size'] : null,
        );
    }

    private function detectMimeType($path, $fallback = null)
    {
        if (!is_file($path)) {
            return $fallback;
        }

        if (function_exists('finfo_open')) {
            $finfo = @finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = @finfo_file($finfo, $path);
                @finfo_close($finfo);
                if ($mime) {
                    return $mime;
                }
            }
        }

        return $fallback;
    }
}

