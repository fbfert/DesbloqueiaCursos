<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class CupomRelacao
{
    public function forCupom($cupomId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM cupons_relacoes
             WHERE cupom_id = :cupom_id
             ORDER BY tipo_relacao ASC, valor_relacao ASC, id ASC'
        );

        $stmt->execute(array('cupom_id' => $cupomId));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function sync($cupomId, array $relacoes)
    {
        $pdo = Database::connection();

        $delete = $pdo->prepare('DELETE FROM cupons_relacoes WHERE cupom_id = :cupom_id');
        $delete->execute(array('cupom_id' => $cupomId));

        if ($relacoes) {
            $insert = $pdo->prepare(
                'INSERT INTO cupons_relacoes
                 (cupom_id, tipo_relacao, valor_relacao, created_at)
                 VALUES
                 (:cupom_id, :tipo_relacao, :valor_relacao, NOW())'
            );

            foreach ($relacoes as $relacao) {
                $insert->execute(array(
                    'cupom_id' => $cupomId,
                    'tipo_relacao' => $relacao['tipo_relacao'],
                    'valor_relacao' => $relacao['valor_relacao'],
                ));
            }
        }
    }
}
