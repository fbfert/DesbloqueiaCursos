<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class ConteudoItem
{
    public function findById($id)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM conteudo_itens
             WHERE id = :id
               AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(array('id' => $id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function listForModulo($moduloId, $status = null)
    {
        $sql = 'SELECT *
                FROM conteudo_itens
                WHERE modulo_id = :modulo_id
                  AND deleted_at IS NULL';
        $params = array('modulo_id' => (int) $moduloId);

        if ($status !== null && $status !== '') {
            $sql .= ' AND status = :status';
            $params['status'] = (string) $status;
        }

        $sql .= ' ORDER BY ordem ASC, id ASC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listObrigatoriosPublicadosForCurso($cursoEventoId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM conteudo_itens
             WHERE curso_evento_id = :curso_evento_id
               AND obrigatorio = 1
               AND status = "publicado"
               AND deleted_at IS NULL
             ORDER BY modulo_id ASC, ordem ASC, id ASC'
        );
        $stmt->execute(array('curso_evento_id' => (int) $cursoEventoId));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO conteudo_itens
             (curso_evento_id, modulo_id, tipo, titulo, descricao_curta, obrigatorio, ordem, status, abre_em, criado_por, atualizado_por, created_at, updated_at, deleted_at)
             VALUES
             (:curso_evento_id, :modulo_id, :tipo, :titulo, :descricao_curta, :obrigatorio, :ordem, :status, :abre_em, :criado_por, :atualizado_por, NOW(), NOW(), NULL)'
        );

        $stmt->execute(array(
            'curso_evento_id' => (int) $data['curso_evento_id'],
            'modulo_id' => (int) $data['modulo_id'],
            'tipo' => (string) $data['tipo'],
            'titulo' => (string) $data['titulo'],
            'descricao_curta' => isset($data['descricao_curta']) ? $data['descricao_curta'] : null,
            'obrigatorio' => !empty($data['obrigatorio']) ? 1 : 0,
            'ordem' => isset($data['ordem']) ? (int) $data['ordem'] : 0,
            'status' => isset($data['status']) ? (string) $data['status'] : 'rascunho',
            'abre_em' => isset($data['abre_em']) && $data['abre_em'] !== '' ? (string) $data['abre_em'] : null,
            'criado_por' => isset($data['criado_por']) ? (int) $data['criado_por'] : null,
            'atualizado_por' => isset($data['atualizado_por']) ? (int) $data['atualizado_por'] : null,
        ));

        return (int) Database::connection()->lastInsertId();
    }

    public function update(array $data, $id)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE conteudo_itens
             SET modulo_id = :modulo_id,
                 tipo = :tipo,
                 titulo = :titulo,
                 descricao_curta = :descricao_curta,
                 obrigatorio = :obrigatorio,
                 ordem = :ordem,
                 status = :status,
                 abre_em = :abre_em,
                 criado_por = COALESCE(:criado_por, criado_por),
                 atualizado_por = :atualizado_por,
                 updated_at = NOW()
             WHERE id = :id'
        );

        $stmt->execute(array(
            'modulo_id' => (int) $data['modulo_id'],
            'tipo' => (string) $data['tipo'],
            'titulo' => (string) $data['titulo'],
            'descricao_curta' => isset($data['descricao_curta']) ? $data['descricao_curta'] : null,
            'obrigatorio' => !empty($data['obrigatorio']) ? 1 : 0,
            'ordem' => isset($data['ordem']) ? (int) $data['ordem'] : 0,
            'status' => isset($data['status']) ? (string) $data['status'] : 'rascunho',
            'abre_em' => isset($data['abre_em']) && $data['abre_em'] !== '' ? (string) $data['abre_em'] : null,
            'criado_por' => isset($data['criado_por']) ? (int) $data['criado_por'] : null,
            'atualizado_por' => isset($data['atualizado_por']) ? (int) $data['atualizado_por'] : null,
            'id' => (int) $id,
        ));
    }

    public function softDelete($id)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE conteudo_itens
             SET deleted_at = NOW(),
                 updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute(array('id' => (int) $id));
    }

    public function updateStatus($id, $status, $actorUserId = null)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE conteudo_itens
             SET status = :status,
                 atualizado_por = :atualizado_por,
                 updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute(array(
            'status' => (string) $status,
            'atualizado_por' => $actorUserId ? (int) $actorUserId : null,
            'id' => (int) $id,
        ));

        return $stmt->rowCount() > 0;
    }

    public function moverParaModulo($id, $novoModuloId, $actorUserId = null)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE conteudo_itens
             SET modulo_id = :modulo_id,
                 atualizado_por = :atualizado_por,
                 updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute(array(
            'modulo_id' => (int) $novoModuloId,
            'atualizado_por' => $actorUserId ? (int) $actorUserId : null,
            'id' => (int) $id,
        ));

        return $stmt->rowCount() > 0;
    }
}

