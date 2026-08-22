<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class ConteudoAvaliacaoEntregaImagem
{
    public function findById($id)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM conteudo_avaliacoes_entregas_imagens
             WHERE id = :id
             LIMIT 1'
        );
        $stmt->execute(array('id' => (int) $id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function listByEntregaId($entregaId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM conteudo_avaliacoes_entregas_imagens
             WHERE entrega_id = :entrega_id
             ORDER BY ordem ASC, id ASC'
        );
        $stmt->execute(array('entrega_id' => (int) $entregaId));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO conteudo_avaliacoes_entregas_imagens
             (entrega_id, nome_original, nome_arquivo, caminho, mime_type, extensao, tamanho_bytes, ordem, created_at)
             VALUES
             (:entrega_id, :nome_original, :nome_arquivo, :caminho, :mime_type, :extensao, :tamanho_bytes, :ordem, NOW())'
        );
        $stmt->execute(array(
            'entrega_id' => (int) $data['entrega_id'],
            'nome_original' => isset($data['nome_original']) ? (string) $data['nome_original'] : null,
            'nome_arquivo' => isset($data['nome_arquivo']) ? (string) $data['nome_arquivo'] : null,
            'caminho' => (string) $data['caminho'],
            'mime_type' => isset($data['mime_type']) ? (string) $data['mime_type'] : null,
            'extensao' => isset($data['extensao']) ? (string) $data['extensao'] : null,
            'tamanho_bytes' => isset($data['tamanho_bytes']) ? (int) $data['tamanho_bytes'] : null,
            'ordem' => isset($data['ordem']) ? (int) $data['ordem'] : 0,
        ));
        return (int) Database::connection()->lastInsertId();
    }
}
