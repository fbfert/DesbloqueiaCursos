<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class Atividade
{
    public function findById($id)
    {
        $stmt = Database::connection()->prepare('SELECT * FROM atividades WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(array('id' => $id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function listForContext($cursoId, $turmaId = null, $moduloId = null, $aulaId = null, $status = null, $usuarioId = null)
    {
        $sql = 'SELECT a.*,
                       mo.titulo AS modulo_titulo,
                       au.titulo AS aula_titulo,
                       au.status AS aula_status,
                       mo.status AS modulo_status,
                       (SELECT COUNT(*)
                        FROM atividades_entregas ae_contagem
                        WHERE ae_contagem.atividade_id = a.id
                          AND ae_contagem.deleted_at IS NULL) AS total_entregas';

        if ($usuarioId !== null && (int) $usuarioId > 0) {
            $sql .= ',
                       ae_usuario.id AS entrega_usuario_id,
                       ae_usuario.status AS entrega_usuario_status,
                       ae_usuario.resposta_texto AS entrega_usuario_resposta_texto,
                       ae_usuario.nota AS entrega_usuario_nota,
                       ae_usuario.feedback AS entrega_usuario_feedback,
                       ae_usuario.entregue_em AS entrega_usuario_entregue_em,
                       ae_usuario.corrigido_em AS entrega_usuario_corrigido_em,
                       ae_usuario.arquivo_nome_original AS entrega_usuario_arquivo_nome_original,
                       ae_usuario.arquivo_caminho AS entrega_usuario_arquivo_caminho';
        }

        $sql .= '
                FROM atividades a
                LEFT JOIN modulos mo ON mo.id = a.modulo_id AND mo.deleted_at IS NULL
                LEFT JOIN aulas au ON au.id = a.aula_id AND au.deleted_at IS NULL';

        if ($usuarioId !== null && (int) $usuarioId > 0) {
            $sql .= '
                LEFT JOIN atividades_entregas ae_usuario
                    ON ae_usuario.atividade_id = a.id
                   AND ae_usuario.usuario_id = :usuario_id
                   AND ae_usuario.deleted_at IS NULL';
        }

        $sql .= '
                WHERE a.curso_evento_id = :curso_evento_id
                  AND a.deleted_at IS NULL';

        $params = array('curso_evento_id' => $cursoId);

        if ($usuarioId !== null && (int) $usuarioId > 0) {
            $params['usuario_id'] = (int) $usuarioId;
        }

        if ($turmaId !== null) {
            $sql .= ' AND a.turma_id = :turma_id';
            $params['turma_id'] = $turmaId;
        } else {
            $sql .= ' AND a.turma_id IS NULL';
        }

        if ($moduloId !== null) {
            $sql .= ' AND a.modulo_id = :modulo_id';
            $params['modulo_id'] = $moduloId;
        }

        if ($aulaId !== null) {
            $sql .= ' AND a.aula_id = :aula_id';
            $params['aula_id'] = $aulaId;
        }

        if ($status !== null && $status !== '') {
            $sql .= ' AND a.status = :status';
            $params['status'] = $status;
        }

        $sql .= ' ORDER BY FIELD(a.status, "publicado", "rascunho", "oculto") ASC, a.ordem ASC, a.id ASC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO atividades
             (curso_evento_id, turma_id, modulo_id, aula_id, titulo, descricao, tipo_entrega, prazo, nota_maxima, visivel, status, criado_por, atualizado_por, ordem, created_at, updated_at, deleted_at)
             VALUES
             (:curso_evento_id, :turma_id, :modulo_id, :aula_id, :titulo, :descricao, :tipo_entrega, :prazo, :nota_maxima, :visivel, :status, :criado_por, :atualizado_por, :ordem, NOW(), NOW(), NULL)'
        );

        $stmt->execute(array(
            'curso_evento_id' => $data['curso_evento_id'],
            'turma_id' => isset($data['turma_id']) ? $data['turma_id'] : null,
            'modulo_id' => $data['modulo_id'],
            'aula_id' => $data['aula_id'],
            'titulo' => $data['titulo'],
            'descricao' => isset($data['descricao']) ? $data['descricao'] : null,
            'tipo_entrega' => isset($data['tipo_entrega']) ? $data['tipo_entrega'] : 'texto',
            'prazo' => isset($data['prazo']) ? $data['prazo'] : null,
            'nota_maxima' => isset($data['nota_maxima']) ? $data['nota_maxima'] : 10,
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
            'UPDATE atividades
             SET curso_evento_id = :curso_evento_id,
                 turma_id = :turma_id,
                 modulo_id = :modulo_id,
                 aula_id = :aula_id,
                 titulo = :titulo,
                 descricao = :descricao,
                 tipo_entrega = :tipo_entrega,
                 prazo = :prazo,
                 nota_maxima = :nota_maxima,
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
            'modulo_id' => $data['modulo_id'],
            'aula_id' => $data['aula_id'],
            'titulo' => $data['titulo'],
            'descricao' => isset($data['descricao']) ? $data['descricao'] : null,
            'tipo_entrega' => isset($data['tipo_entrega']) ? $data['tipo_entrega'] : 'texto',
            'prazo' => isset($data['prazo']) ? $data['prazo'] : null,
            'nota_maxima' => isset($data['nota_maxima']) ? $data['nota_maxima'] : 10,
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
        $stmt = Database::connection()->prepare('UPDATE atividades SET deleted_at = NOW(), updated_at = NOW() WHERE id = :id');
        $stmt->execute(array('id' => $id));
    }
}
