<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class CupomCurso
{
    public function forCupom($cupomId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT cc.*,
                    ce.nome AS curso_nome,
                    ce.slug AS curso_slug,
                    ce.status AS curso_status
             FROM cupom_cursos cc
             INNER JOIN cursos_eventos ce ON ce.id = cc.curso_id
             WHERE cc.cupom_id = :cupom_id
               AND ce.deleted_at IS NULL
             ORDER BY ce.ordem ASC, ce.nome ASC, ce.id ASC'
        );

        $stmt->execute(array('cupom_id' => $cupomId));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function courseIdsForCupom($cupomId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT curso_id
             FROM cupom_cursos
             WHERE cupom_id = :cupom_id'
        );

        $stmt->execute(array('cupom_id' => $cupomId));

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $ids = array();
        foreach ($rows as $row) {
            $ids[] = (int) $row['curso_id'];
        }

        return $ids;
    }

    public function sync($cupomId, array $cursoIds)
    {
        $cursoIds = array_values(array_unique(array_map('intval', $cursoIds)));
        $pdo = Database::connection();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $delete = $pdo->prepare('DELETE FROM cupom_cursos WHERE cupom_id = :cupom_id');
            $delete->execute(array('cupom_id' => $cupomId));

            if ($cursoIds) {
                $insert = $pdo->prepare(
                    'INSERT INTO cupom_cursos (cupom_id, curso_id, created_at)
                     VALUES (:cupom_id, :curso_id, NOW())'
                );

                foreach ($cursoIds as $cursoId) {
                    $insert->execute(array(
                        'cupom_id' => $cupomId,
                        'curso_id' => $cursoId,
                    ));
                }
            }

            if ($ownsTransaction) {
                $pdo->commit();
            }
        } catch (\Exception $exception) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }
}
