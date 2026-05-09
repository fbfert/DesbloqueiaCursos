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
        $sql = 'SELECT m.*,
                       mo.titulo AS modulo_titulo,
                       a.titulo AS aula_titulo,
                       a.ordem AS aula_ordem
                FROM materiais m
                LEFT JOIN modulos mo ON mo.id = m.modulo_id AND mo.deleted_at IS NULL
                LEFT JOIN aulas a ON a.id = m.aula_id AND a.deleted_at IS NULL
                WHERE m.curso_evento_id = :curso_evento_id
                  AND m.deleted_at IS NULL';
        $params = array('curso_evento_id' => $cursoId);

        if ($turmaId !== null) {
            $sql .= ' AND m.turma_id = :turma_id';
            $params['turma_id'] = $turmaId;
        } else {
            $sql .= ' AND m.turma_id IS NULL';
        }

        if ($moduloId !== null) {
            $sql .= ' AND m.modulo_id = :modulo_id';
            $params['modulo_id'] = $moduloId;
        }

        if ($aulaId !== null) {
            $sql .= ' AND m.aula_id = :aula_id';
            $params['aula_id'] = $aulaId;
        }

        $sql .= ' ORDER BY FIELD(m.status, "publicado", "rascunho", "oculto") ASC, m.ordem ASC, m.id ASC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO materiais
             (curso_evento_id, turma_id, modulo_id, aula_id, titulo, descricao, tipo_material, arquivo_caminho, arquivo_nome_original, arquivo_mime_type, arquivo_tamanho_bytes, tipo_arquivo, url, visivel, status, criado_por, atualizado_por, ordem, created_at, updated_at, deleted_at)
             VALUES
             (:curso_evento_id, :turma_id, :modulo_id, :aula_id, :titulo, :descricao, :tipo_material, :arquivo_caminho, :arquivo_nome_original, :arquivo_mime_type, :arquivo_tamanho_bytes, :tipo_arquivo, :url, :visivel, :status, :criado_por, :atualizado_por, :ordem, NOW(), NOW(), NULL)'
        );

        $stmt->execute(array(
            'curso_evento_id' => $data['curso_evento_id'],
            'turma_id' => isset($data['turma_id']) ? $data['turma_id'] : null,
            'modulo_id' => isset($data['modulo_id']) ? $data['modulo_id'] : null,
            'aula_id' => isset($data['aula_id']) ? $data['aula_id'] : null,
            'titulo' => $data['titulo'],
            'descricao' => isset($data['descricao']) ? $data['descricao'] : null,
            'tipo_material' => isset($data['tipo_material']) ? $data['tipo_material'] : 'arquivo_protegido',
            'arquivo_caminho' => isset($data['arquivo_caminho']) ? $data['arquivo_caminho'] : null,
            'arquivo_nome_original' => isset($data['arquivo_nome_original']) ? $data['arquivo_nome_original'] : null,
            'arquivo_mime_type' => isset($data['arquivo_mime_type']) ? $data['arquivo_mime_type'] : null,
            'arquivo_tamanho_bytes' => isset($data['arquivo_tamanho_bytes']) ? $data['arquivo_tamanho_bytes'] : null,
            'tipo_arquivo' => isset($data['tipo_arquivo']) ? $data['tipo_arquivo'] : 'outro',
            'url' => isset($data['url']) ? $data['url'] : null,
            'visivel' => !empty($data['visivel']) ? 1 : 0,
            'status' => isset($data['status']) ? $data['status'] : 'publicado',
            'criado_por' => isset($data['criado_por']) ? $data['criado_por'] : null,
            'atualizado_por' => isset($data['atualizado_por']) ? $data['atualizado_por'] : null,
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
                 tipo_material = :tipo_material,
                 arquivo_caminho = :arquivo_caminho,
                 arquivo_nome_original = :arquivo_nome_original,
                 arquivo_mime_type = :arquivo_mime_type,
                 arquivo_tamanho_bytes = :arquivo_tamanho_bytes,
                 tipo_arquivo = :tipo_arquivo,
                 url = :url,
                 visivel = :visivel,
                 status = :status,
                 criado_por = COALESCE(:criado_por, criado_por),
                 atualizado_por = :atualizado_por,
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
            'tipo_material' => isset($data['tipo_material']) ? $data['tipo_material'] : 'arquivo_protegido',
            'arquivo_caminho' => isset($data['arquivo_caminho']) ? $data['arquivo_caminho'] : null,
            'arquivo_nome_original' => isset($data['arquivo_nome_original']) ? $data['arquivo_nome_original'] : null,
            'arquivo_mime_type' => isset($data['arquivo_mime_type']) ? $data['arquivo_mime_type'] : null,
            'arquivo_tamanho_bytes' => isset($data['arquivo_tamanho_bytes']) ? $data['arquivo_tamanho_bytes'] : null,
            'tipo_arquivo' => isset($data['tipo_arquivo']) ? $data['tipo_arquivo'] : 'outro',
            'url' => isset($data['url']) ? $data['url'] : null,
            'visivel' => !empty($data['visivel']) ? 1 : 0,
            'status' => isset($data['status']) ? $data['status'] : 'publicado',
            'criado_por' => isset($data['criado_por']) ? $data['criado_por'] : null,
            'atualizado_por' => isset($data['atualizado_por']) ? $data['atualizado_por'] : null,
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
