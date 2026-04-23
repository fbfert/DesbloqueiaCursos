<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class RepasseProfessor
{
    public function create(array $data)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO repasses_professores
             (apuracao_id, usuario_id, tipo_fiscal, percentual, base_liquida, valor_bruto, retencao_percentual, valor_retenido, valor_liquido,
              documento_obrigatorio, status, competencia, documento_validado_em, created_at, updated_at, deleted_at)
             VALUES
             (:apuracao_id, :usuario_id, :tipo_fiscal, :percentual, :base_liquida, :valor_bruto, :retencao_percentual, :valor_retenido, :valor_liquido,
              :documento_obrigatorio, :status, :competencia, :documento_validado_em, NOW(), NOW(), NULL)'
        );

        $stmt->execute(array(
            'apuracao_id' => $data['apuracao_id'],
            'usuario_id' => $data['usuario_id'],
            'tipo_fiscal' => isset($data['tipo_fiscal']) ? $data['tipo_fiscal'] : 'pf',
            'percentual' => $data['percentual'],
            'base_liquida' => $data['base_liquida'],
            'valor_bruto' => $data['valor_bruto'],
            'retencao_percentual' => $data['retencao_percentual'],
            'valor_retenido' => $data['valor_retenido'],
            'valor_liquido' => $data['valor_liquido'],
            'documento_obrigatorio' => !empty($data['documento_obrigatorio']) ? 1 : 0,
            'status' => isset($data['status']) ? $data['status'] : 'pendente',
            'competencia' => $data['competencia'],
            'documento_validado_em' => isset($data['documento_validado_em']) ? $data['documento_validado_em'] : null,
        ));

        return (int) Database::connection()->lastInsertId();
    }

    public function findByApuracao($apuracaoId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT rp.*,
                    u.nome AS usuario_nome,
                    u.email AS usuario_email
             FROM repasses_professores rp
             INNER JOIN usuarios u ON u.id = rp.usuario_id
             WHERE rp.apuracao_id = :apuracao_id
               AND rp.deleted_at IS NULL
             ORDER BY u.nome ASC'
        );

        $stmt->execute(array('apuracao_id' => $apuracaoId));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function allAdmin()
    {
        $stmt = Database::connection()->query(
            'SELECT rp.*,
                    ap.competencia,
                    u.nome AS usuario_nome,
                    u.email AS usuario_email
             FROM repasses_professores rp
             INNER JOIN apuracoes_mensais ap ON ap.id = rp.apuracao_id
             INNER JOIN usuarios u ON u.id = rp.usuario_id
             WHERE rp.deleted_at IS NULL
             ORDER BY ap.competencia DESC, rp.id DESC'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById($id)
    {
        $stmt = Database::connection()->prepare(
            'SELECT rp.*,
                    ap.competencia,
                    u.nome AS usuario_nome,
                    u.email AS usuario_email
             FROM repasses_professores rp
             INNER JOIN apuracoes_mensais ap ON ap.id = rp.apuracao_id
             INNER JOIN usuarios u ON u.id = rp.usuario_id
             WHERE rp.id = :id
               AND rp.deleted_at IS NULL
             LIMIT 1'
        );

        $stmt->execute(array('id' => $id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findByApuracaoAndUsuario($apuracaoId, $usuarioId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT rp.*,
                    ap.competencia,
                    u.nome AS usuario_nome,
                    u.email AS usuario_email
             FROM repasses_professores rp
             INNER JOIN apuracoes_mensais ap ON ap.id = rp.apuracao_id
             INNER JOIN usuarios u ON u.id = rp.usuario_id
             WHERE rp.apuracao_id = :apuracao_id
               AND rp.usuario_id = :usuario_id
               AND rp.deleted_at IS NULL
             LIMIT 1'
        );

        $stmt->execute(array(
            'apuracao_id' => $apuracaoId,
            'usuario_id' => $usuarioId,
        ));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function updateStatus($repasseId, $status, $documentoValidadoEm = null)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE repasses_professores
             SET status = :status,
                 documento_validado_em = :documento_validado_em,
                 updated_at = NOW()
             WHERE id = :id'
        );

        $stmt->execute(array(
            'status' => $status,
            'documento_validado_em' => $documentoValidadoEm,
            'id' => $repasseId,
        ));
    }

    public function forProfessor($usuarioId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT rp.*,
                    ap.competencia,
                    ap.status AS apuracao_status,
                    u.nome AS usuario_nome,
                    u.email AS usuario_email
             FROM repasses_professores rp
             INNER JOIN apuracoes_mensais ap ON ap.id = rp.apuracao_id
             INNER JOIN usuarios u ON u.id = rp.usuario_id
             WHERE rp.usuario_id = :usuario_id
               AND rp.deleted_at IS NULL
             ORDER BY ap.competencia DESC, rp.id DESC'
        );

        $stmt->execute(array('usuario_id' => $usuarioId));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function forApuracao($apuracaoId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT rp.*,
                    ap.competencia,
                    u.nome AS usuario_nome,
                    u.email AS usuario_email
             FROM repasses_professores rp
             INNER JOIN apuracoes_mensais ap ON ap.id = rp.apuracao_id
             INNER JOIN usuarios u ON u.id = rp.usuario_id
             WHERE rp.apuracao_id = :apuracao_id
               AND rp.deleted_at IS NULL
             ORDER BY u.nome ASC'
        );

        $stmt->execute(array('apuracao_id' => $apuracaoId));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
