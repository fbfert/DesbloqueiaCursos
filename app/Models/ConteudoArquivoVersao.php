<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class ConteudoArquivoVersao
{
    public function findById($id)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM conteudo_arquivos_versoes
             WHERE id = :id
             LIMIT 1'
        );
        $stmt->execute(array('id' => (int) $id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function listForArquivo($arquivoId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM conteudo_arquivos_versoes
             WHERE arquivo_id = :arquivo_id
             ORDER BY versao DESC, id DESC'
        );
        $stmt->execute(array('arquivo_id' => (int) $arquivoId));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO conteudo_arquivos_versoes
             (arquivo_id, item_id, nome_original, nome_arquivo, caminho, mime_type, extensao, tamanho_bytes, versao, substituido_por, criado_por, created_at)
             VALUES
             (:arquivo_id, :item_id, :nome_original, :nome_arquivo, :caminho, :mime_type, :extensao, :tamanho_bytes, :versao, :substituido_por, :criado_por, NOW())'
        );

        $stmt->execute(array(
            'arquivo_id' => (int) $data['arquivo_id'],
            'item_id' => (int) $data['item_id'],
            'nome_original' => isset($data['nome_original']) ? $data['nome_original'] : null,
            'nome_arquivo' => isset($data['nome_arquivo']) ? $data['nome_arquivo'] : null,
            'caminho' => isset($data['caminho']) ? $data['caminho'] : null,
            'mime_type' => isset($data['mime_type']) ? $data['mime_type'] : null,
            'extensao' => isset($data['extensao']) ? $data['extensao'] : null,
            'tamanho_bytes' => array_key_exists('tamanho_bytes', $data) ? $data['tamanho_bytes'] : null,
            'versao' => isset($data['versao']) ? (int) $data['versao'] : 1,
            'substituido_por' => array_key_exists('substituido_por', $data) ? $data['substituido_por'] : null,
            'criado_por' => isset($data['criado_por']) ? (int) $data['criado_por'] : null,
        ));

        return (int) Database::connection()->lastInsertId();
    }
}

