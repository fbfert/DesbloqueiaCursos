<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class FrontendMenuExibicaoRegra
{
    public function listarPorMenu($menuId)
    {
        $menuId = (int) $menuId;
        if ($menuId <= 0) {
            return array();
        }

        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM frontend_menu_exibicao_regras
             WHERE menu_id = :menu_id
             ORDER BY ativo DESC, ordem ASC, id ASC'
        );
        $stmt->execute(array('menu_id' => $menuId));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function salvarRegrasDoMenu($menuId, array $regras)
    {
        $menuId = (int) $menuId;
        if ($menuId <= 0) {
            return 0;
        }

        $pdo = Database::connection();
        $pdo->prepare('DELETE FROM frontend_menu_exibicao_regras WHERE menu_id = :menu_id')
            ->execute(array('menu_id' => $menuId));

        if (empty($regras)) {
            return 0;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO frontend_menu_exibicao_regras
             (menu_id, tipo_regra, alvo_tipo, alvo_valor, ativo, ordem, criado_em, atualizado_em)
             VALUES
             (:menu_id, :tipo_regra, :alvo_tipo, :alvo_valor, :ativo, :ordem, NOW(), NOW())'
        );

        $contador = 0;
        foreach ($regras as $regra) {
            if (!is_array($regra)) {
                continue;
            }

            $stmt->execute(array(
                'menu_id' => $menuId,
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

    public function excluirPorMenu($menuId)
    {
        $menuId = (int) $menuId;
        if ($menuId <= 0) {
            return 0;
        }

        $stmt = Database::connection()->prepare(
            'DELETE FROM frontend_menu_exibicao_regras
             WHERE menu_id = :menu_id'
        );
        $stmt->execute(array('menu_id' => $menuId));

        return $stmt->rowCount();
    }

    public function buscarRegrasAtivasPorMenus(array $menuIds)
    {
        $menuIds = array_values(array_unique(array_filter(array_map('intval', $menuIds), function ($value) {
            return $value > 0;
        })));

        if (empty($menuIds)) {
            return array();
        }

        $placeholders = implode(',', array_fill(0, count($menuIds), '?'));
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM frontend_menu_exibicao_regras
             WHERE ativo = 1
               AND menu_id IN (' . $placeholders . ')
             ORDER BY menu_id ASC, ordem ASC, id ASC'
        );
        $stmt->execute($menuIds);

        $regras = array();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $menuId = (int) $row['menu_id'];
            if (!isset($regras[$menuId])) {
                $regras[$menuId] = array();
            }
            $regras[$menuId][] = $row;
        }

        return $regras;
    }
}
