<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class ApuracaoMensal
{
    public function findByCompetencia($competencia)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM apuracoes_mensais
             WHERE competencia = :competencia
               AND deleted_at IS NULL
             LIMIT 1'
        );

        $stmt->execute(array('competencia' => $competencia));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findById($id)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM apuracoes_mensais
             WHERE id = :id
               AND deleted_at IS NULL
             LIMIT 1'
        );

        $stmt->execute(array('id' => $id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function allAdmin()
    {
        $stmt = Database::connection()->query(
            'SELECT *
             FROM apuracoes_mensais
             WHERE deleted_at IS NULL
             ORDER BY competencia DESC, id DESC'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO apuracoes_mensais
             (competencia, data_inicio, data_fim, base_bruta, desconto_cupons, base_liquida, percentual_rateio_total, valor_rateio_total,
              valor_retenido_total, status, fechada_em, criada_por_usuario_id, created_at, updated_at, deleted_at)
             VALUES
             (:competencia, :data_inicio, :data_fim, :base_bruta, :desconto_cupons, :base_liquida, :percentual_rateio_total, :valor_rateio_total,
              :valor_retenido_total, :status, :fechada_em, :criada_por_usuario_id, NOW(), NOW(), NULL)'
        );

        $stmt->execute(array(
            'competencia' => $data['competencia'],
            'data_inicio' => $data['data_inicio'],
            'data_fim' => $data['data_fim'],
            'base_bruta' => $data['base_bruta'],
            'desconto_cupons' => $data['desconto_cupons'],
            'base_liquida' => $data['base_liquida'],
            'percentual_rateio_total' => $data['percentual_rateio_total'],
            'valor_rateio_total' => $data['valor_rateio_total'],
            'valor_retenido_total' => $data['valor_retenido_total'],
            'status' => isset($data['status']) ? $data['status'] : 'aberta',
            'fechada_em' => isset($data['fechada_em']) ? $data['fechada_em'] : null,
            'criada_por_usuario_id' => isset($data['criada_por_usuario_id']) ? $data['criada_por_usuario_id'] : null,
        ));

        return (int) Database::connection()->lastInsertId();
    }

    public function updateSummary($apuracaoId, array $data)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE apuracoes_mensais
             SET base_bruta = :base_bruta,
                 desconto_cupons = :desconto_cupons,
                 base_liquida = :base_liquida,
                 percentual_rateio_total = :percentual_rateio_total,
                 valor_rateio_total = :valor_rateio_total,
                 valor_retenido_total = :valor_retenido_total,
                 status = :status,
                 fechada_em = :fechada_em,
                 updated_at = NOW()
             WHERE id = :id'
        );

        $stmt->execute(array_merge($data, array('id' => $apuracaoId)));
    }
}
