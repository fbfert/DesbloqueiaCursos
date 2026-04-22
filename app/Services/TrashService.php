<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use Exception;
use InvalidArgumentException;

class TrashService
{
    public function requireReason($reason)
    {
        $reason = trim((string) $reason);

        if ($reason === '') {
            throw new InvalidArgumentException('A justificativa da lixeira e obrigatoria.');
        }

        return $reason;
    }

    public function record($entityType, $entityId, $reason, array $snapshot = array(), $usuarioId = null, $ipAddress = null, $userAgent = null)
    {
        $reason = $this->requireReason($reason);

        try {
            $stmt = Database::connection()->prepare(
                'INSERT INTO lixeira
                 (entidade_tipo, entidade_id, justificativa, snapshot_dados, excluido_por_usuario_id, ip_address, user_agent, created_at)
                 VALUES
                 (:entidade_tipo, :entidade_id, :justificativa, :snapshot_dados, :excluido_por_usuario_id, :ip_address, :user_agent, NOW())'
            );

            $stmt->execute(array(
                'entidade_tipo' => $entityType,
                'entidade_id' => $entityId,
                'justificativa' => $reason,
                'snapshot_dados' => json_encode($snapshot),
                'excluido_por_usuario_id' => $usuarioId,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ));
        } catch (Exception $exception) {
            Logger::info('trash.recorded', array(
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'reason' => $reason,
                'snapshot' => $snapshot,
                'usuario_id' => $usuarioId,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ));
        }
    }
}
