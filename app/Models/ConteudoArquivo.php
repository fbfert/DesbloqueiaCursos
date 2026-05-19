<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class ConteudoArquivo
{
    public function findById($id)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM conteudo_arquivos
             WHERE id = :id
             LIMIT 1'
        );
        $stmt->execute(array('id' => (int) $id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByItemId($itemId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM conteudo_arquivos
             WHERE item_id = :item_id
             LIMIT 1'
        );
        $stmt->execute(array('item_id' => (int) $itemId));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $data)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO conteudo_arquivos
             (item_id, nome_original, nome_arquivo, caminho, mime_type, extensao, tamanho_bytes, versao_atual_id, permite_download, created_at, updated_at)
             VALUES
             (:item_id, :nome_original, :nome_arquivo, :caminho, :mime_type, :extensao, :tamanho_bytes, :versao_atual_id, :permite_download, NOW(), NOW())'
        );
        $stmt->execute(array(
            'item_id' => (int) $data['item_id'],
            'nome_original' => isset($data['nome_original']) ? $data['nome_original'] : null,
            'nome_arquivo' => isset($data['nome_arquivo']) ? $data['nome_arquivo'] : null,
            'caminho' => isset($data['caminho']) ? $data['caminho'] : null,
            'mime_type' => isset($data['mime_type']) ? $data['mime_type'] : null,
            'extensao' => isset($data['extensao']) ? $data['extensao'] : null,
            'tamanho_bytes' => array_key_exists('tamanho_bytes', $data) ? $data['tamanho_bytes'] : null,
            'versao_atual_id' => array_key_exists('versao_atual_id', $data) ? $data['versao_atual_id'] : null,
            'permite_download' => array_key_exists('permite_download', $data) ? (int) (bool) $data['permite_download'] : 1,
        ));
        return (int) Database::connection()->lastInsertId();
    }

    public function update(array $data, $id)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE conteudo_arquivos
             SET nome_original = :nome_original,
                 nome_arquivo = :nome_arquivo,
                 caminho = :caminho,
                 mime_type = :mime_type,
                 extensao = :extensao,
                 tamanho_bytes = :tamanho_bytes,
                 versao_atual_id = :versao_atual_id,
                 permite_download = :permite_download,
                 updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute(array(
            'nome_original' => isset($data['nome_original']) ? $data['nome_original'] : null,
            'nome_arquivo' => isset($data['nome_arquivo']) ? $data['nome_arquivo'] : null,
            'caminho' => isset($data['caminho']) ? $data['caminho'] : null,
            'mime_type' => isset($data['mime_type']) ? $data['mime_type'] : null,
            'extensao' => isset($data['extensao']) ? $data['extensao'] : null,
            'tamanho_bytes' => array_key_exists('tamanho_bytes', $data) ? $data['tamanho_bytes'] : null,
            'versao_atual_id' => array_key_exists('versao_atual_id', $data) ? $data['versao_atual_id'] : null,
            'permite_download' => array_key_exists('permite_download', $data) ? (int) (bool) $data['permite_download'] : 1,
            'id' => (int) $id,
        ));
    }

    public function setVersaoAtual($id, $versaoAtualId)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE conteudo_arquivos
             SET versao_atual_id = :versao_atual_id,
                 updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute(array(
            'versao_atual_id' => $versaoAtualId !== null ? (int) $versaoAtualId : null,
            'id' => (int) $id,
        ));

        return $stmt->rowCount() > 0;
    }
}

