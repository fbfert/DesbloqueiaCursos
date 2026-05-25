<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class FrontendModuloExibicaoRegra
{
    public function listarPorModulo($moduloId)
    {
        $moduloId = (int) $moduloId;
        if ($moduloId <= 0) {
            return array();
        }

        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM frontend_modulo_exibicao_regras
             WHERE modulo_id = :modulo_id
             ORDER BY ativo DESC, ordem ASC, id ASC'
        );
        $stmt->execute(array('modulo_id' => $moduloId));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function salvarRegrasDoModulo($moduloId, array $regras)
    {
        $moduloId = (int) $moduloId;
        if ($moduloId <= 0) {
            return 0;
        }

        $pdo = Database::connection();
        $pdo->prepare('DELETE FROM frontend_modulo_exibicao_regras WHERE modulo_id = :modulo_id')
            ->execute(array('modulo_id' => $moduloId));

        if (empty($regras)) {
            return 0;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO frontend_modulo_exibicao_regras
             (modulo_id, tipo_regra, alvo_tipo, alvo_valor, ativo, ordem, criado_em, atualizado_em)
             VALUES
             (:modulo_id, :tipo_regra, :alvo_tipo, :alvo_valor, :ativo, :ordem, NOW(), NOW())'
        );

        $contador = 0;
        foreach ($regras as $regra) {
            if (!is_array($regra)) {
                continue;
            }

            $stmt->execute(array(
                'modulo_id' => $moduloId,
                'tipo_regra' => (string) $regra['tipo_regra'],
                'alvo_tipo' => (string) $regra['alvo_tipo'],
                'alvo_valor' => (string) $regra['alvo_valor'],
                'ativo' => (int) $regra['ativo'],
                'ordem' => (int) $regra['ordem'],
            ));
            $contador++;
        }

        return $contador;
    }

    public function excluirPorModulo($moduloId)
    {
        $moduloId = (int) $moduloId;
        if ($moduloId <= 0) {
            return 0;
        }

        $stmt = Database::connection()->prepare(
            'DELETE FROM frontend_modulo_exibicao_regras
             WHERE modulo_id = :modulo_id'
        );
        $stmt->execute(array('modulo_id' => $moduloId));

        return $stmt->rowCount();
    }

    public function buscarRegrasAtivasPorModulos(array $moduloIds)
    {
        $moduloIds = array_values(array_unique(array_filter(array_map('intval', $moduloIds), function ($value) {
            return $value > 0;
        })));

        if (empty($moduloIds)) {
            return array();
        }

        $placeholders = implode(',', array_fill(0, count($moduloIds), '?'));
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM frontend_modulo_exibicao_regras
             WHERE ativo = 1
               AND modulo_id IN (' . $placeholders . ')
             ORDER BY modulo_id ASC, ordem ASC, id ASC'
        );
        $stmt->execute($moduloIds);

        $regras = array();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $moduloId = (int) $row['modulo_id'];
            if (!isset($regras[$moduloId])) {
                $regras[$moduloId] = array();
            }
            $regras[$moduloId][] = $row;
        }

        return $regras;
    }
}
