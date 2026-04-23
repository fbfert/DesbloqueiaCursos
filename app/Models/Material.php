<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class Material
{
    public function findById($id)
    {
        $stmt = Database::connection()->prepare('SELECT * FROM materiais WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(array('id' => $id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function listForContext($cursoId, $turmaId = null, $moduloId = null, $aulaId = null)
    {
        $sql = 'SELECT *
                FROM materiais
                WHERE curso_evento_id = :curso_evento_id
                  AND deleted_at IS NULL';
        $params = array('curso_evento_id' => $cursoId);

        if ($turmaId !== null) {
            $sql .= ' AND (turma_id = :turma_id OR turma_id IS NULL)';
            $params['turma_id'] = $turmaId;
        } else {
            $sql .= ' AND turma_id IS NULL';
        }

        if ($moduloId !== null) {
            $sql .= ' AND (modulo_id = :modulo_id OR modulo_id IS NULL)';
            $params['modulo_id'] = $moduloId;
        }

        if ($aulaId !== null) {
            $sql .= ' AND (aula_id = :aula_id OR aula_id IS NULL)';
            $params['aula_id'] = $aulaId;
        }

        $sql .= ' ORDER BY visivel DESC, ordem ASC, id ASC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO materiais
             (curso_evento_id, turma_id, modulo_id, aula_id, titulo, descricao, arquivo_caminho, arquivo_nome_original, arquivo_mime_type, arquivo_tamanho_bytes, tipo_arquivo, visivel, ordem, created_at, updated_at, deleted_at)
             VALUES
             (:curso_evento_id, :turma_id, :modulo_id, :aula_id, :titulo, :descricao, :arquivo_caminho, :arquivo_nome_original, :arquivo_mime_type, :arquivo_tamanho_bytes, :tipo_arquivo, :visivel, :ordem, NOW(), NOW(), NULL)'
        );

        $stmt->execute(array(
            'curso_evento_id' => $data['curso_evento_id'],
            'turma_id' => isset($data['turma_id']) ? $data['turma_id'] : null,
            'modulo_id' => isset($data['modulo_id']) ? $data['modulo_id'] : null,
            'aula_id' => isset($data['aula_id']) ? $data['aula_id'] : null,
            'titulo' => $data['titulo'],
            'descricao' => isset($data['descricao']) ? $data['descricao'] : null,
            'arquivo_caminho' => $data['arquivo_caminho'],
            'arquivo_nome_original' => isset($data['arquivo_nome_original']) ? $data['arquivo_nome_original'] : null,
            'arquivo_mime_type' => isset($data['arquivo_mime_type']) ? $data['arquivo_mime_type'] : null,
            'arquivo_tamanho_bytes' => isset($data['arquivo_tamanho_bytes']) ? $data['arquivo_tamanho_bytes'] : null,
            'tipo_arquivo' => isset($data['tipo_arquivo']) ? $data['tipo_arquivo'] : 'outro',
            'visivel' => !empty($data['visivel']) ? 1 : 0,
            'ordem' => isset($data['ordem']) ? (int) $data['ordem'] : 1,
        ));

        return (int) Database::connection()->lastInsertId();
    }

    public function update(array $data, $id)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE materiais
             SET curso_evento_id = :curso_evento_id,
                 turma_id = :turma_id,
                 modulo_id = :modulo_id,
                 aula_id = :aula_id,
                 titulo = :titulo,
                 descricao = :descricao,
                 arquivo_caminho = :arquivo_caminho,
                 arquivo_nome_original = :arquivo_nome_original,
                 arquivo_mime_type = :arquivo_mime_type,
                 arquivo_tamanho_bytes = :arquivo_tamanho_bytes,
                 tipo_arquivo = :tipo_arquivo,
                 visivel = :visivel,
                 ordem = :ordem,
                 updated_at = NOW()
             WHERE id = :id'
        );

        $stmt->execute(array(
            'curso_evento_id' => $data['curso_evento_id'],
            'turma_id' => isset($data['turma_id']) ? $data['turma_id'] : null,
            'modulo_id' => isset($data['modulo_id']) ? $data['modulo_id'] : null,
            'aula_id' => isset($data['aula_id']) ? $data['aula_id'] : null,
            'titulo' => $data['titulo'],
            'descricao' => isset($data['descricao']) ? $data['descricao'] : null,
            'arquivo_caminho' => $data['arquivo_caminho'],
            'arquivo_nome_original' => isset($data['arquivo_nome_original']) ? $data['arquivo_nome_original'] : null,
            'arquivo_mime_type' => isset($data['arquivo_mime_type']) ? $data['arquivo_mime_type'] : null,
            'arquivo_tamanho_bytes' => isset($data['arquivo_tamanho_bytes']) ? $data['arquivo_tamanho_bytes'] : null,
            'tipo_arquivo' => isset($data['tipo_arquivo']) ? $data['tipo_arquivo'] : 'outro',
            'visivel' => !empty($data['visivel']) ? 1 : 0,
            'ordem' => isset($data['ordem']) ? (int) $data['ordem'] : 1,
            'id' => $id,
        ));
    }

    public function softDelete($id)
    {
        $stmt = Database::connection()->prepare('UPDATE materiais SET deleted_at = NOW(), updated_at = NOW() WHERE id = :id');
        $stmt->execute(array('id' => $id));
    }
}
