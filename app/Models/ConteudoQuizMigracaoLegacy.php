<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class ConteudoQuizMigracaoLegacy
{
    public function findByItemId($itemId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM conteudo_quiz_migracoes_legacy WHERE item_id = :item_id LIMIT 1'
        );
        $stmt->execute(array('item_id' => (int) $itemId));
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findByHash($hash)
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM conteudo_quiz_migracoes_legacy WHERE hash_origem = :hash LIMIT 1'
        );
        $stmt->execute(array('hash' => (string) $hash));
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function create(array $data)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO conteudo_quiz_migracoes_legacy
             (item_id, quiz_id, conteudo_texto_id, html_original, hash_origem, status, detalhes_erro, migrado_em, created_at, updated_at)
             VALUES
             (:item_id, :quiz_id, :conteudo_texto_id, :html_original, :hash_origem, :status, :detalhes_erro, :migrado_em, NOW(), NOW())'
        );
        $stmt->execute($this->buildParams($data));
        return (int) Database::connection()->lastInsertId();
    }

    public function update(array $data, $id)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE conteudo_quiz_migracoes_legacy SET
              quiz_id = :quiz_id,
              status = :status,
              detalhes_erro = :detalhes_erro,
              migrado_em = :migrado_em,
              updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute(array(
            'quiz_id'       => isset($data['quiz_id']) ? (int) $data['quiz_id'] : null,
            'status'        => (string) ($data['status'] ?? 'pendente'),
            'detalhes_erro' => isset($data['detalhes_erro']) ? (string) $data['detalhes_erro'] : null,
            'migrado_em'    => isset($data['migrado_em']) ? $data['migrado_em'] : null,
            'id'            => (int) $id,
        ));
    }

    public function listAll()
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM conteudo_quiz_migracoes_legacy ORDER BY id ASC'
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function buildParams(array $data)
    {
        return array(
            'item_id'          => (int) ($data['item_id'] ?? 0),
            'quiz_id'          => isset($data['quiz_id']) ? (int) $data['quiz_id'] : null,
            'conteudo_texto_id'=> isset($data['conteudo_texto_id']) ? (int) $data['conteudo_texto_id'] : null,
            'html_original'    => isset($data['html_original']) ? (string) $data['html_original'] : null,
            'hash_origem'      => (string) ($data['hash_origem'] ?? ''),
            'status'           => (string) ($data['status'] ?? 'pendente'),
            'detalhes_erro'    => isset($data['detalhes_erro']) ? (string) $data['detalhes_erro'] : null,
            'migrado_em'       => isset($data['migrado_em']) ? $data['migrado_em'] : null,
        );
    }
}
