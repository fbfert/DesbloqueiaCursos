<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class CupomHistorico
{
    public function forCupom($cupomId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM cupons_historico
             WHERE cupom_id = :cupom_id
             ORDER BY id DESC'
        );

        $stmt->execute(array('cupom_id' => $cupomId));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create($cupomId, $acao, $observacao = null, array $metadados = array(), $alteradoPorUsuarioId = null)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO cupons_historico
             (cupom_id, acao, observacao, metadados, alterado_por_usuario_id, created_at)
             VALUES
             (:cupom_id, :acao, :observacao, :metadados, :alterado_por_usuario_id, NOW())'
        );

        $stmt->execute(array(
            'cupom_id' => $cupomId,
            'acao' => $acao,
            'observacao' => $observacao,
            'metadados' => json_encode($metadados),
            'alterado_por_usuario_id' => $alteradoPorUsuarioId,
        ));
    }
}
