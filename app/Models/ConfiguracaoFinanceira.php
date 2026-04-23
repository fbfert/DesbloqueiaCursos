<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class ConfiguracaoFinanceira
{
    public function current()
    {
        $stmt = Database::connection()->query(
            'SELECT *
             FROM configuracoes_financeiras
             WHERE deleted_at IS NULL
             ORDER BY id DESC
             LIMIT 1'
        );

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function save(array $data)
    {
        $current = $this->current();

        $payload = array(
            'data_corte_financeiro' => !empty($data['data_corte_financeiro']) ? $data['data_corte_financeiro'] : null,
            'percentual_rateio_maximo' => isset($data['percentual_rateio_maximo']) ? (float) $data['percentual_rateio_maximo'] : 75.00,
            'observacao_repasse' => isset($data['observacao_repasse']) ? trim((string) $data['observacao_repasse']) : null,
        );

        if ($current) {
            $stmt = Database::connection()->prepare(
                'UPDATE configuracoes_financeiras
                 SET data_corte_financeiro = :data_corte_financeiro,
                     percentual_rateio_maximo = :percentual_rateio_maximo,
                     observacao_repasse = :observacao_repasse,
                     updated_at = NOW()
                 WHERE id = :id'
            );

            $stmt->execute(array_merge($payload, array('id' => (int) $current['id'])));
            return (int) $current['id'];
        }

        $stmt = Database::connection()->prepare(
            'INSERT INTO configuracoes_financeiras
             (data_corte_financeiro, percentual_rateio_maximo, observacao_repasse, created_at, updated_at, deleted_at)
             VALUES
             (:data_corte_financeiro, :percentual_rateio_maximo, :observacao_repasse, NOW(), NOW(), NULL)'
        );

        $stmt->execute($payload);

        return (int) Database::connection()->lastInsertId();
    }
}
