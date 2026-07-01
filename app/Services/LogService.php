<?php

namespace App\Services;

class LogService
{
    public function recentEntries($needle = '', $limit = 30)
    {
        $path = BASE_PATH . '/storage/logs/app-' . date('Y-m-d') . '.log';

        if (!is_file($path)) {
            return array(
                'ok' => false,
                'path' => $path,
                'entries' => array(),
                'message' => 'Arquivo de log não encontrado.',
            );
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return array(
                'ok' => false,
                'path' => $path,
                'entries' => array(),
                'message' => 'Não foi possível ler o arquivo de log.',
            );
        }

        $entries = array();
        $needle = trim((string) $needle);

        for ($index = count($lines) - 1; $index >= 0; $index--) {
            $line = trim((string) $lines[$index]);
            if ($line === '') {
                continue;
            }

            if ($needle !== '' && stripos($line, $needle) === false) {
                continue;
            }

            $decoded = json_decode($line, true);
            if (!is_array($decoded)) {
                $decoded = array(
                    'timestamp' => null,
                    'level' => 'raw',
                    'message' => $line,
                    'context' => array(),
                );
            }

            $decoded['_raw'] = $line;
            $entries[] = $decoded;

            if (count($entries) >= (int) $limit) {
                break;
            }
        }

        return array(
            'ok' => true,
            'path' => $path,
            'entries' => $entries,
            'message' => null,
        );
    }
}
