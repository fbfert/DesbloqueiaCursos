<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class CursoRateio
{
    public function findById($id)
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
             WHERE cr.id = :id
               AND cr.deleted_at IS NULL
             LIMIT 1'
        );

        $stmt->execute(array('id' => $id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findByContext($apuracaoId, $cursoEventoId, $turmaId = null, $excludeId = null)
    {
        $sql = 'SELECT cr.*,
                       ce.nome AS curso_nome,
                       ce.slug AS curso_slug,
                       t.nome AS turma_nome,
                       t.codigo AS turma_codigo
                FROM cursos_rateio cr
                INNER JOIN cursos_eventos ce ON ce.id = cr.curso_evento_id
                LEFT JOIN turmas t ON t.id = cr.turma_id
                WHERE cr.apuracao_id = :apuracao_id
                  AND cr.curso_evento_id = :curso_evento_id
                  AND ((cr.turma_id IS NULL AND :turma_id IS NULL) OR cr.turma_id = :turma_id)';

        if (!empty($excludeId)) {
            $sql .= ' AND cr.id <> :exclude_id';
        }

        $sql .= ' AND cr.deleted_at IS NULL
                  LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $params = array(
            'apuracao_id' => $apuracaoId,
            'curso_evento_id' => $cursoEventoId,
            'turma_id' => $turmaId,
        );

        if (!empty($excludeId)) {
            $params['exclude_id'] = $excludeId;
        }

        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

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
             (apuracao_id, curso_evento_id, turma_id, competencia, base_bruta, desconto_cupons, base_liquida, percentual_total, valor_rateio_total, status, observacoes, fechado_em, created_at, updated_at, deleted_at)
             VALUES
             (:apuracao_id, :curso_evento_id, :turma_id, :competencia, :base_bruta, :desconto_cupons, :base_liquida, :percentual_total, :valor_rateio_total, :status, :observacoes, :fechado_em, NOW(), NOW(), NULL)'
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
            'observacoes' => isset($data['observacoes']) ? $data['observacoes'] : null,
            'fechado_em' => isset($data['fechado_em']) ? $data['fechado_em'] : null,
        ));

        return (int) Database::connection()->lastInsertId();
    }

    public function update(array $data, $id)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE cursos_rateio
             SET apuracao_id = :apuracao_id,
                 curso_evento_id = :curso_evento_id,
                 turma_id = :turma_id,
                 competencia = :competencia,
                 base_bruta = :base_bruta,
                 desconto_cupons = :desconto_cupons,
                 base_liquida = :base_liquida,
                 percentual_total = :percentual_total,
                 valor_rateio_total = :valor_rateio_total,
                 status = :status,
                 observacoes = :observacoes,
                 fechado_em = :fechado_em,
                 updated_at = NOW()
             WHERE id = :id'
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
            'observacoes' => isset($data['observacoes']) ? $data['observacoes'] : null,
            'fechado_em' => isset($data['fechado_em']) ? $data['fechado_em'] : null,
            'id' => $id,
        ));
    }

    public function allAdmin()
    {
        $stmt = Database::connection()->query(
            'SELECT cr.*,
                    ce.nome AS curso_nome,
                    ce.slug AS curso_slug,
                    t.nome AS turma_nome,
                    t.codigo AS turma_codigo
             FROM cursos_rateio cr
             INNER JOIN cursos_eventos ce ON ce.id = cr.curso_evento_id
             LEFT JOIN turmas t ON t.id = cr.turma_id
             WHERE cr.deleted_at IS NULL
             ORDER BY cr.competencia DESC, cr.id DESC'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function softDelete($id)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE cursos_rateio
             SET deleted_at = NOW(),
                 updated_at = NOW()
             WHERE id = :id'
        );

        $stmt->execute(array('id' => $id));
    }
}
