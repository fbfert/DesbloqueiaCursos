<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class ConteudoLink
{
    public function findByItemId($itemId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM conteudo_links
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
                'UPDATE conteudo_links
                 SET url = :url,
                     modo_abertura = :modo_abertura,
                     provedor = :provedor,
                     embed_html = :embed_html,
                     updated_at = NOW()
                 WHERE item_id = :item_id'
            );
            $stmt->execute(array(
                'url' => (string) $data['url'],
                'modo_abertura' => isset($data['modo_abertura']) ? (string) $data['modo_abertura'] : 'nova_aba',
                'provedor' => isset($data['provedor']) ? $data['provedor'] : null,
                'embed_html' => isset($data['embed_html']) ? $data['embed_html'] : null,
                'item_id' => (int) $itemId,
            ));
            return (int) $existing['id'];
        }

        $stmt = Database::connection()->prepare(
            'INSERT INTO conteudo_links
             (item_id, url, modo_abertura, provedor, embed_html, created_at, updated_at)
             VALUES
             (:item_id, :url, :modo_abertura, :provedor, :embed_html, NOW(), NOW())'
        );
        $stmt->execute(array(
            'item_id' => (int) $itemId,
            'url' => (string) $data['url'],
            'modo_abertura' => isset($data['modo_abertura']) ? (string) $data['modo_abertura'] : 'nova_aba',
            'provedor' => isset($data['provedor']) ? $data['provedor'] : null,
            'embed_html' => isset($data['embed_html']) ? $data['embed_html'] : null,
        ));
        return (int) Database::connection()->lastInsertId();
    }
}

