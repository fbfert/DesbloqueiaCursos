<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use Exception;

class AccessLogService
{
    public function record($usuarioId, $evento, $resultado, $ipAddress, $userAgent, array $metadata = array())
    {
        try {
            $stmt = Database::connection()->prepare(
                'INSERT INTO acessos_logs
                 (usuario_id, evento, resultado, ip_address, user_agent, metadados, created_at)
                 VALUES
                 (:usuario_id, :evento, :resultado, :ip_address, :user_agent, :metadados, NOW())'
            );

            $stmt->execute(array(
                'usuario_id' => $usuarioId,
                'evento' => $evento,
                'resultado' => $resultado,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'metadados' => json_encode($metadata),
            ));
        } catch (Exception $exception) {
            Logger::info('access_log_fallback', array(
                'usuario_id' => $usuarioId,
                'evento' => $evento,
                'resultado' => $resultado,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'metadata' => $metadata,
            ));
        }
    }
}
