<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class Presenca
{
    public function findById($id)
    {
        $stmt = Database::connection()->prepare('SELECT * FROM presencas WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(array('id' => $id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function listForInscricao($inscricaoId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM presencas
             WHERE inscricao_id = :inscricao_id
               AND deleted_at IS NULL
             ORDER BY data_presenca DESC, id DESC'
        );
        $stmt->execute(array('inscricao_id' => $inscricaoId));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByContext($inscricaoId, $participantePedidoId, $aulaId, $dataPresenca)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM presencas
             WHERE inscricao_id = :inscricao_id
               AND participante_pedido_id = :participante_pedido_id
               AND aula_id <=> :aula_id
               AND data_presenca = :data_presenca
               AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(array(
            'inscricao_id' => $inscricaoId,
            'participante_pedido_id' => $participantePedidoId,
            'aula_id' => $aulaId,
            'data_presenca' => $dataPresenca,
        ));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function listForContext($cursoId, $turmaId = null)
    {
        $sql = 'SELECT *
                FROM presencas
                WHERE curso_evento_id = :curso_evento_id
                  AND deleted_at IS NULL';
        $params = array('curso_evento_id' => $cursoId);

        if ($turmaId !== null) {
            $sql .= ' AND turma_id = :turma_id';
            $params['turma_id'] = $turmaId;
        }

        $sql .= ' ORDER BY data_presenca DESC, id DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO presencas
             (inscricao_id, pedido_id, pedido_item_id, participante_pedido_id, usuario_id, curso_evento_id, turma_id, aula_id, data_presenca, status, observacao, marcado_por_usuario_id, created_at, updated_at, deleted_at)
             VALUES
             (:inscricao_id, :pedido_id, :pedido_item_id, :participante_pedido_id, :usuario_id, :curso_evento_id, :turma_id, :aula_id, :data_presenca, :status, :observacao, :marcado_por_usuario_id, NOW(), NOW(), NULL)'
        );

        $stmt->execute(array(
            'inscricao_id' => $data['inscricao_id'],
            'pedido_id' => $data['pedido_id'],
            'pedido_item_id' => $data['pedido_item_id'],
            'participante_pedido_id' => $data['participante_pedido_id'],
            'usuario_id' => isset($data['usuario_id']) ? $data['usuario_id'] : null,
            'curso_evento_id' => $data['curso_evento_id'],
            'turma_id' => isset($data['turma_id']) ? $data['turma_id'] : null,
            'aula_id' => isset($data['aula_id']) ? $data['aula_id'] : null,
            'data_presenca' => $data['data_presenca'],
            'status' => isset($data['status']) ? $data['status'] : 'presente',
            'observacao' => isset($data['observacao']) ? $data['observacao'] : null,
            'marcado_por_usuario_id' => isset($data['marcado_por_usuario_id']) ? $data['marcado_por_usuario_id'] : null,
        ));

        return (int) Database::connection()->lastInsertId();
    }

    public function upsert(array $data)
    {
        $existente = $this->findByContext(
            $data['inscricao_id'],
            $data['participante_pedido_id'],
            isset($data['aula_id']) ? $data['aula_id'] : null,
            $data['data_presenca']
        );

        if ($existente) {
            $stmt = Database::connection()->prepare(
                'UPDATE presencas
                 SET status = :status,
                     observacao = :observacao,
                     marcado_por_usuario_id = :marcado_por_usuario_id,
                     updated_at = NOW()
                 WHERE id = :id'
            );

            $stmt->execute(array(
                'status' => isset($data['status']) ? $data['status'] : 'presente',
                'observacao' => isset($data['observacao']) ? $data['observacao'] : null,
                'marcado_por_usuario_id' => isset($data['marcado_por_usuario_id']) ? $data['marcado_por_usuario_id'] : null,
                'id' => $existente['id'],
            ));

            return (int) $existente['id'];
        }

        return $this->create($data);
    }

    public function softDelete($id)
    {
        $stmt = Database::connection()->prepare('UPDATE presencas SET deleted_at = NOW(), updated_at = NOW() WHERE id = :id');
        $stmt->execute(array('id' => $id));
    }
}
