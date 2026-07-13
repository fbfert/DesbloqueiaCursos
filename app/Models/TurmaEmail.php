<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class TurmaEmail
{
    public function create(array $data)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO turma_emails
             (turma_id, assunto, corpo_html, total_destinatarios, criado_por_usuario_id, created_at, updated_at)
             VALUES
             (:turma_id, :assunto, :corpo_html, :total_destinatarios, :criado_por_usuario_id, NOW(), NOW())'
        );

        $stmt->execute(array(
            'turma_id' => (int) $data['turma_id'],
            'assunto' => $data['assunto'],
            'corpo_html' => $data['corpo_html'],
            'total_destinatarios' => isset($data['total_destinatarios']) ? (int) $data['total_destinatarios'] : 0,
            'criado_por_usuario_id' => isset($data['criado_por_usuario_id']) && $data['criado_por_usuario_id']
                ? (int) $data['criado_por_usuario_id']
                : null,
        ));

        return (int) Database::connection()->lastInsertId();
    }

    public function findById($id)
    {
        $stmt = Database::connection()->prepare(
            'SELECT te.*, t.nome AS turma_nome, t.codigo AS turma_codigo, u.nome AS criado_por_nome
             FROM turma_emails te
             JOIN turmas t ON t.id = te.turma_id
             LEFT JOIN usuarios u ON u.id = te.criado_por_usuario_id
             WHERE te.id = :id
             LIMIT 1'
        );

        $stmt->execute(array('id' => (int) $id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Lotes de uma turma, com o placar de envio agregado a partir de emails_envios.
     * Uma unica query agregada evita N+1 ao montar o historico.
     */
    public function listByTurma($turmaId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT te.*,
                    u.nome AS criado_por_nome,
                    COALESCE(SUM(ee.status = "enviado"), 0) AS enviados,
                    COALESCE(SUM(ee.status = "falhou"), 0) AS falhas,
                    COALESCE(SUM(ee.status = "pendente"), 0) AS pendentes
             FROM turma_emails te
             LEFT JOIN usuarios u ON u.id = te.criado_por_usuario_id
             LEFT JOIN emails_envios ee
                    ON ee.entidade_tipo = "turma_email"
                   AND ee.entidade_id = te.id
             WHERE te.turma_id = :turma_id
             GROUP BY te.id
             ORDER BY te.id DESC'
        );

        $stmt->execute(array('turma_id' => (int) $turmaId));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateTotalDestinatarios($id, $total)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE turma_emails
             SET total_destinatarios = :total,
                 updated_at = NOW()
             WHERE id = :id'
        );

        $stmt->execute(array(
            'total' => (int) $total,
            'id' => (int) $id,
        ));
    }
}
