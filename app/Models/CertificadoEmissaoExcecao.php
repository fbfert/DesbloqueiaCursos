<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class CertificadoEmissaoExcecao
{
    public function create(array $data)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO certificados_emissao_excecoes
             (certificado_id, inscricao_id, curso_evento_id, turma_id, participante_pedido_id, usuario_id, emitido_por_usuario_id,
              justificativa, situacao_elegibilidade, motivos_pendencias, snapshot_elegibilidade, ip_address, user_agent,
              created_at, updated_at, deleted_at)
             VALUES
             (:certificado_id, :inscricao_id, :curso_evento_id, :turma_id, :participante_pedido_id, :usuario_id, :emitido_por_usuario_id,
              :justificativa, :situacao_elegibilidade, :motivos_pendencias, :snapshot_elegibilidade, :ip_address, :user_agent,
              NOW(), NOW(), NULL)'
        );

        $stmt->execute(array(
            'certificado_id' => array_key_exists('certificado_id', $data) ? $data['certificado_id'] : null,
            'inscricao_id' => $data['inscricao_id'],
            'curso_evento_id' => $data['curso_evento_id'],
            'turma_id' => array_key_exists('turma_id', $data) ? $data['turma_id'] : null,
            'participante_pedido_id' => $data['participante_pedido_id'],
            'usuario_id' => array_key_exists('usuario_id', $data) ? $data['usuario_id'] : null,
            'emitido_por_usuario_id' => $data['emitido_por_usuario_id'],
            'justificativa' => $data['justificativa'],
            'situacao_elegibilidade' => array_key_exists('situacao_elegibilidade', $data) ? $data['situacao_elegibilidade'] : null,
            'motivos_pendencias' => array_key_exists('motivos_pendencias', $data) ? (is_array($data['motivos_pendencias']) ? json_encode($data['motivos_pendencias'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $data['motivos_pendencias']) : null,
            'snapshot_elegibilidade' => array_key_exists('snapshot_elegibilidade', $data) ? (is_array($data['snapshot_elegibilidade']) ? json_encode($data['snapshot_elegibilidade'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $data['snapshot_elegibilidade']) : null,
            'ip_address' => array_key_exists('ip_address', $data) ? $data['ip_address'] : null,
            'user_agent' => array_key_exists('user_agent', $data) ? $data['user_agent'] : null,
        ));

        return (int) Database::connection()->lastInsertId();
    }

    public function findByCertificadoId($certificadoId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT cex.*,
                    u_emitido.nome AS emitido_por_nome,
                    u_emitido.email AS emitido_por_email,
                    u_aluno.nome AS usuario_nome,
                    u_aluno.email AS usuario_email
             FROM certificados_emissao_excecoes cex
             LEFT JOIN usuarios u_emitido ON u_emitido.id = cex.emitido_por_usuario_id
             LEFT JOIN usuarios u_aluno ON u_aluno.id = cex.usuario_id
             WHERE cex.certificado_id = :certificado_id
               AND cex.deleted_at IS NULL
             ORDER BY cex.id DESC
             LIMIT 1'
        );
        $stmt->execute(array('certificado_id' => (int) $certificadoId));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        if (!empty($row['snapshot_elegibilidade'])) {
            $snapshot = json_decode($row['snapshot_elegibilidade'], true);
            $row['snapshot_elegibilidade'] = is_array($snapshot) ? $snapshot : null;
        }

        if (!empty($row['motivos_pendencias'])) {
            $motivos = json_decode($row['motivos_pendencias'], true);
            if (is_array($motivos)) {
                $row['motivos_pendencias'] = implode('; ', $motivos);
            }
        }

        return $row;
    }

    public function listByInscricaoId($inscricaoId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT cex.*,
                    u_emitido.nome AS emitido_por_nome,
                    u_emitido.email AS emitido_por_email,
                    u_aluno.nome AS usuario_nome,
                    u_aluno.email AS usuario_email
             FROM certificados_emissao_excecoes cex
             LEFT JOIN usuarios u_emitido ON u_emitido.id = cex.emitido_por_usuario_id
             LEFT JOIN usuarios u_aluno ON u_aluno.id = cex.usuario_id
             WHERE cex.inscricao_id = :inscricao_id
               AND cex.deleted_at IS NULL
             ORDER BY cex.id DESC'
        );
        $stmt->execute(array('inscricao_id' => (int) $inscricaoId));
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            if (!empty($row['snapshot_elegibilidade'])) {
                $snapshot = json_decode($row['snapshot_elegibilidade'], true);
                $row['snapshot_elegibilidade'] = is_array($snapshot) ? $snapshot : null;
            }

            if (!empty($row['motivos_pendencias'])) {
                $motivos = json_decode($row['motivos_pendencias'], true);
                if (is_array($motivos)) {
                    $row['motivos_pendencias'] = implode('; ', $motivos);
                }
            }
        }
        unset($row);

        return $rows;
    }
}
