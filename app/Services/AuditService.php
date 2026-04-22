<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use Exception;

class AuditService
{
    public function record($action, $entityType, $entityId = null, array $metadata = array(), $usuarioId = null, $ipAddress = null, $userAgent = null)
    {
        try {
            $stmt = Database::connection()->prepare(
                'INSERT INTO auditoria_logs
                 (usuario_id, acao, entidade_tipo, entidade_id, ip_address, user_agent, metadados, created_at)
                 VALUES
                 (:usuario_id, :acao, :entidade_tipo, :entidade_id, :ip_address, :user_agent, :metadados, NOW())'
            );

            $stmt->execute(array(
                'usuario_id' => $usuarioId,
                'acao' => $action,
                'entidade_tipo' => $entityType,
                'entidade_id' => $entityId,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'metadados' => json_encode($metadata),
            ));
        } catch (Exception $exception) {
            Logger::info('audit.recorded', array(
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'usuario_id' => $usuarioId,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'metadata' => $metadata,
            ));
        }
    }
}
