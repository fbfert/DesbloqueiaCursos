<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class ConteudoVideo
{
    public function findByItemId($itemId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM conteudo_videos
             WHERE item_id = :item_id
             LIMIT 1'
        );
        $stmt->execute(array('item_id' => (int) $itemId));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function upsertByItemId($itemId, array $data)
    {
        $existing = $this->findByItemId($itemId);
        if ($existing) {
            $stmt = Database::connection()->prepare(
                'UPDATE conteudo_videos
                 SET url = :url,
                     provedor = :provedor,
                     embed_html = :embed_html,
                     duracao_segundos = :duracao_segundos,
                     updated_at = NOW()
                 WHERE item_id = :item_id'
            );
            $stmt->execute(array(
                'url' => (string) $data['url'],
                'provedor' => isset($data['provedor']) ? $data['provedor'] : null,
                'embed_html' => isset($data['embed_html']) ? $data['embed_html'] : null,
                'duracao_segundos' => array_key_exists('duracao_segundos', $data) ? $data['duracao_segundos'] : null,
                'item_id' => (int) $itemId,
            ));
            return (int) $existing['id'];
        }

        $stmt = Database::connection()->prepare(
            'INSERT INTO conteudo_videos
             (item_id, url, provedor, embed_html, duracao_segundos, created_at, updated_at)
             VALUES
             (:item_id, :url, :provedor, :embed_html, :duracao_segundos, NOW(), NOW())'
        );
        $stmt->execute(array(
            'item_id' => (int) $itemId,
            'url' => (string) $data['url'],
            'provedor' => isset($data['provedor']) ? $data['provedor'] : null,
            'embed_html' => isset($data['embed_html']) ? $data['embed_html'] : null,
            'duracao_segundos' => array_key_exists('duracao_segundos', $data) ? $data['duracao_segundos'] : null,
        ));
        return (int) Database::connection()->lastInsertId();
    }
}

