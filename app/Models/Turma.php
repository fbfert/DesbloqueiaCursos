<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class Turma
{
    public function allWithCourse()
    {
        $stmt = Database::connection()->query(
            'SELECT t.*,
                    ce.nome AS curso_nome,
                    ce.tipo AS curso_tipo,
                    ce.modalidade AS curso_modalidade,
                    c.nome AS categoria_nome
             FROM turmas t
             INNER JOIN cursos_eventos ce ON ce.id = t.curso_evento_id
             LEFT JOIN categorias c ON c.id = ce.categoria_id
             WHERE t.deleted_at IS NULL
               AND ce.deleted_at IS NULL
             ORDER BY t.data_inicio IS NULL, t.data_inicio ASC, t.nome ASC'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findAccessibleByUser($usuarioId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT DISTINCT t.*,
                    ce.nome AS curso_nome,
                    ce.tipo AS curso_tipo,
                    ce.modalidade AS curso_modalidade
             FROM turmas t
             INNER JOIN cursos_eventos ce ON ce.id = t.curso_evento_id
             INNER JOIN usuario_turmas ut ON ut.turma_id = t.id
             WHERE ut.usuario_id = :usuario_id
               AND ut.deleted_at IS NULL
               AND t.deleted_at IS NULL
               AND ce.deleted_at IS NULL
             ORDER BY t.data_inicio IS NULL, t.data_inicio ASC, t.nome ASC'
        );

        $stmt->execute(array('usuario_id' => $usuarioId));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
