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

    public function historyForEntity($entityType, $entityId, $limit = 20)
    {
        try {
            $stmt = Database::connection()->prepare(
                'SELECT al.acao,
                        al.entidade_tipo,
                        al.entidade_id,
                        al.usuario_id,
                        al.ip_address,
                        al.user_agent,
                        al.metadados,
                        al.created_at,
                        u.nome AS usuario_nome,
                        u.email AS usuario_email
                 FROM auditoria_logs al
                 LEFT JOIN usuarios u ON u.id = al.usuario_id
                 WHERE al.entidade_tipo = :entidade_tipo
                   AND al.entidade_id = :entidade_id
                 ORDER BY al.created_at DESC, al.id DESC
                 LIMIT ' . (int) $limit
            );

            $stmt->execute(array(
                'entidade_tipo' => $entityType,
                'entidade_id' => $entityId,
            ));

            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $historico = array();
            foreach ($rows as $row) {
                $row['metadados'] = !empty($row['metadados']) ? json_decode($row['metadados'], true) : array();
                if (!is_array($row['metadados'])) {
                    $row['metadados'] = array();
                }
                $historico[] = $row;
            }

            return $historico;
        } catch (Exception $exception) {
            Logger::error('audit.history.falhou', array(
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'message' => $exception->getMessage(),
            ));

            return array();
        }
    }
}
