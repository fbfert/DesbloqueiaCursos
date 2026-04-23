<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class CursoRateio
{
    public function findByApuracao($apuracaoId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT cr.*,
                    ce.nome AS curso_nome,
                    ce.slug AS curso_slug,
                    t.nome AS turma_nome,
                    t.codigo AS turma_codigo
             FROM cursos_rateio cr
             INNER JOIN cursos_eventos ce ON ce.id = cr.curso_evento_id
             LEFT JOIN turmas t ON t.id = cr.turma_id
             WHERE cr.apuracao_id = :apuracao_id
               AND cr.deleted_at IS NULL
             ORDER BY cr.id ASC'
        );

        $stmt->execute(array('apuracao_id' => $apuracaoId));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO cursos_rateio
             (apuracao_id, curso_evento_id, turma_id, competencia, base_bruta, desconto_cupons, base_liquida, percentual_total, valor_rateio_total, status, fechado_em, created_at, updated_at, deleted_at)
             VALUES
             (:apuracao_id, :curso_evento_id, :turma_id, :competencia, :base_bruta, :desconto_cupons, :base_liquida, :percentual_total, :valor_rateio_total, :status, :fechado_em, NOW(), NOW(), NULL)'
        );

        $stmt->execute(array(
            'apuracao_id' => $data['apuracao_id'],
            'curso_evento_id' => $data['curso_evento_id'],
            'turma_id' => isset($data['turma_id']) ? $data['turma_id'] : null,
            'competencia' => $data['competencia'],
            'base_bruta' => $data['base_bruta'],
            'desconto_cupons' => $data['desconto_cupons'],
            'base_liquida' => $data['base_liquida'],
            'percentual_total' => $data['percentual_total'],
            'valor_rateio_total' => $data['valor_rateio_total'],
            'status' => isset($data['status']) ? $data['status'] : 'calculado',
            'fechado_em' => isset($data['fechado_em']) ? $data['fechado_em'] : null,
        ));

        return (int) Database::connection()->lastInsertId();
    }
}
