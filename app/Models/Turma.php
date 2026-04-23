<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class Turma
{
    public function findById($id)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM turmas
             WHERE id = :id
               AND deleted_at IS NULL
             LIMIT 1'
        );

        $stmt->execute(array('id' => $id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findPublicById($id)
    {
        $stmt = Database::connection()->prepare(
            'SELECT t.*,
                    ce.nome AS curso_nome,
                    ce.slug AS curso_slug,
                    ce.tipo AS curso_tipo,
                    ce.modalidade AS curso_modalidade,
                    ce.valor AS curso_valor,
                    ce.em_promocao AS curso_em_promocao,
                    ce.usar_turmas AS curso_usar_turmas,
                    c.nome AS categoria_nome
             FROM turmas t
             INNER JOIN cursos_eventos ce ON ce.id = t.curso_evento_id
             LEFT JOIN categorias c ON c.id = ce.categoria_id
             WHERE t.id = :id
               AND t.deleted_at IS NULL
               AND ce.deleted_at IS NULL
               AND ce.status = "ativo"
             LIMIT 1'
        );

        $stmt->execute(array('id' => $id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function forCourse($cursoId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT t.*
             FROM turmas t
             WHERE t.curso_evento_id = :curso_evento_id
               AND t.deleted_at IS NULL
             ORDER BY t.data_inicio IS NULL, t.data_inicio ASC, t.nome ASC'
        );

        $stmt->execute(array('curso_evento_id' => $cursoId));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

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
