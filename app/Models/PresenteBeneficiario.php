<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class PresenteBeneficiario
{
    public function findById($id)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM presentes_beneficiarios
             WHERE deleted_at IS NULL
               AND id = :id
             LIMIT 1'
        );

        $stmt->execute(array('id' => (int) $id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findByCampanhaAndUsuario($campanhaId, $usuarioId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM presentes_beneficiarios
             WHERE deleted_at IS NULL
               AND campanha_id = :campanha_id
               AND usuario_id = :usuario_id
             LIMIT 1'
        );

        $stmt->execute(array(
            'campanha_id' => (int) $campanhaId,
            'usuario_id' => (int) $usuarioId,
        ));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function updateStatus($id, $status, $usuarioId = null, $cancelamentoTipo = null, $cancelamentoJustificativa = null)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE presentes_beneficiarios
             SET status = :status,
                 cancelado_por_usuario_id = :cancelado_por_usuario_id,
                 cancelado_em = :cancelado_em,
                 cancelamento_tipo = :cancelamento_tipo,
                 cancelamento_justificativa = :cancelamento_justificativa,
                 atualizado_em = NOW()
             WHERE id = :id
               AND deleted_at IS NULL'
        );

        $stmt->execute(array(
            'status' => $status,
            'cancelado_por_usuario_id' => $usuarioId,
            'cancelado_em' => $status === 'cancelado' ? date('Y-m-d H:i:s') : null,
            'cancelamento_tipo' => $cancelamentoTipo,
            'cancelamento_justificativa' => $cancelamentoJustificativa,
            'id' => (int) $id,
        ));
    }

    public function markExpiredByCampaign($campanhaId)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE presentes_beneficiarios
             SET status = "expirado",
                 atualizado_em = NOW()
             WHERE campanha_id = :campanha_id
               AND deleted_at IS NULL
               AND status = "ativo"
               AND acesso_expira_em IS NOT NULL
               AND acesso_expira_em < NOW()'
        );

        $stmt->execute(array('campanha_id' => (int) $campanhaId));
    }
}
