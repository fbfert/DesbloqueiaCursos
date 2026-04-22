<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class CursoEvento
{
    public function allWithCategoryAndCounts()
    {
        $stmt = Database::connection()->query(
            'SELECT ce.*,
                    c.nome AS categoria_nome,
                    COALESCE(t_total.total_turmas, 0) AS total_turmas,
                    COALESCE(p_total.total_pessoas_vinculadas, 0) AS total_pessoas_vinculadas
             FROM cursos_eventos ce
             LEFT JOIN categorias c ON c.id = ce.categoria_id
             LEFT JOIN (
                SELECT curso_evento_id, COUNT(*) AS total_turmas
                FROM turmas
                WHERE deleted_at IS NULL
                GROUP BY curso_evento_id
             ) t_total ON t_total.curso_evento_id = ce.id
             LEFT JOIN (
                SELECT curso_evento_id, COUNT(*) AS total_pessoas_vinculadas
                FROM curso_pessoas_vinculadas
                WHERE deleted_at IS NULL
                GROUP BY curso_evento_id
             ) p_total ON p_total.curso_evento_id = ce.id
             WHERE ce.deleted_at IS NULL
             ORDER BY ce.ordem ASC, ce.nome ASC'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById($id)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM cursos_eventos
             WHERE id = :id
               AND deleted_at IS NULL
             LIMIT 1'
        );

        $stmt->execute(array('id' => $id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findAccessibleByUser($usuarioId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT DISTINCT ce.*
             FROM cursos_eventos ce
             INNER JOIN (
                SELECT uc.curso_evento_id AS curso_evento_id
                FROM usuario_cursos uc
                WHERE uc.usuario_id = :usuario_id
                  AND uc.deleted_at IS NULL
                UNION
                SELECT cpv.curso_evento_id AS curso_evento_id
                FROM curso_pessoas_vinculadas cpv
                WHERE cpv.usuario_id = :usuario_id
                  AND cpv.tipo_pessoa = "professor"
                  AND cpv.deleted_at IS NULL
             ) acessos ON acessos.curso_evento_id = ce.id
             WHERE ce.deleted_at IS NULL
             ORDER BY ce.ordem ASC, ce.nome ASC'
        );

        $stmt->execute(array('usuario_id' => $usuarioId));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
