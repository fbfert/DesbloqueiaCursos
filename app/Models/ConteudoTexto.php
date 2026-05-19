<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class ConteudoTexto
{
    public function findByItemId($itemId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM conteudo_textos
             WHERE item_id = :item_id
             LIMIT 1'
        );
        $stmt->execute(array('item_id' => (int) $itemId));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function upsertByItemId($itemId, $conteudo)
    {
        $existing = $this->findByItemId($itemId);
        if ($existing) {
            $stmt = Database::connection()->prepare(
                'UPDATE conteudo_textos
                 SET conteudo = :conteudo,
                     updated_at = NOW()
                 WHERE item_id = :item_id'
            );
            $stmt->execute(array('conteudo' => $conteudo, 'item_id' => (int) $itemId));
            return (int) $existing['id'];
        }

        $stmt = Database::connection()->prepare(
            'INSERT INTO conteudo_textos
             (item_id, conteudo, created_at, updated_at)
             VALUES
             (:item_id, :conteudo, NOW(), NOW())'
        );
        $stmt->execute(array('item_id' => (int) $itemId, 'conteudo' => $conteudo));
        return (int) Database::connection()->lastInsertId();
    }
}

