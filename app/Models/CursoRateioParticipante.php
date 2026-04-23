<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class CursoRateioParticipante
{
    public function create(array $data)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO cursos_rateio_participantes
             (curso_rateio_id, usuario_id, tipo_fiscal, percentual, valor_base, valor_rateado, retencao_percentual, valor_retenido, valor_liquido, status, created_at, updated_at, deleted_at)
             VALUES
             (:curso_rateio_id, :usuario_id, :tipo_fiscal, :percentual, :valor_base, :valor_rateado, :retencao_percentual, :valor_retenido, :valor_liquido, :status, NOW(), NOW(), NULL)'
        );

        $stmt->execute(array(
            'curso_rateio_id' => $data['curso_rateio_id'],
            'usuario_id' => $data['usuario_id'],
            'tipo_fiscal' => isset($data['tipo_fiscal']) ? $data['tipo_fiscal'] : 'pf',
            'percentual' => $data['percentual'],
            'valor_base' => $data['valor_base'],
            'valor_rateado' => $data['valor_rateado'],
            'retencao_percentual' => $data['retencao_percentual'],
            'valor_retenido' => $data['valor_retenido'],
            'valor_liquido' => $data['valor_liquido'],
            'status' => isset($data['status']) ? $data['status'] : 'pendente',
        ));

        return (int) Database::connection()->lastInsertId();
    }

    public function findByRateio($cursoRateioId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT crp.*,
                    u.nome AS usuario_nome,
                    u.email AS usuario_email
             FROM cursos_rateio_participantes crp
             INNER JOIN usuarios u ON u.id = crp.usuario_id
             WHERE crp.curso_rateio_id = :curso_rateio_id
               AND crp.deleted_at IS NULL
             ORDER BY crp.id ASC'
        );

        $stmt->execute(array('curso_rateio_id' => $cursoRateioId));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function allForProfessor($usuarioId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT crp.*,
                    cr.competencia,
                    ce.nome AS curso_nome,
                    ce.slug AS curso_slug,
                    t.nome AS turma_nome,
                    t.codigo AS turma_codigo
             FROM cursos_rateio_participantes crp
             INNER JOIN cursos_rateio cr ON cr.id = crp.curso_rateio_id
             INNER JOIN cursos_eventos ce ON ce.id = cr.curso_evento_id
             LEFT JOIN turmas t ON t.id = cr.turma_id
             WHERE crp.usuario_id = :usuario_id
               AND crp.deleted_at IS NULL
             ORDER BY cr.competencia DESC, crp.id DESC'
        );

        $stmt->execute(array('usuario_id' => $usuarioId));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
