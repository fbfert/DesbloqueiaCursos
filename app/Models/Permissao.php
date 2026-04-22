<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class Permissao
{
    public function all()
    {
        $stmt = Database::connection()->query(
            'SELECT *
             FROM permissoes
             ORDER BY modulo ASC, acao ASC, id ASC'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function groupedByModule()
    {
        $grouped = array();

        foreach ($this->all() as $permissao) {
            $module = $permissao['modulo'];
            if (!isset($grouped[$module])) {
                $grouped[$module] = array();
            }
            $grouped[$module][] = $permissao;
        }

        return $grouped;
    }

    public function findByIds(array $ids)
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        if (!$ids) {
            return array();
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM permissoes
             WHERE id IN (' . $placeholders . ')
             ORDER BY modulo ASC, acao ASC, id ASC'
        );
        $stmt->execute($ids);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
