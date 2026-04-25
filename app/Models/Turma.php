<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class Turma
{
    public function findBySlug($slug)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM turmas
             WHERE slug = :slug
               AND deleted_at IS NULL
             LIMIT 1'
        );

        $stmt->execute(array('slug' => $slug));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findByCodigo($codigo)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM turmas
             WHERE codigo = :codigo
               AND deleted_at IS NULL
             LIMIT 1'
        );

        $stmt->execute(array('codigo' => $codigo));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

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

    public function findAdminById($id)
    {
        $stmt = Database::connection()->prepare(
            'SELECT t.*,
                    ce.nome AS curso_nome,
                    ce.slug AS curso_slug,
                    ce.tipo AS curso_tipo,
                    ce.modalidade AS curso_modalidade,
                    ce.valor AS curso_valor,
                    c.nome AS categoria_nome,
                    u.nome AS professor_responsavel_nome
             FROM turmas t
             INNER JOIN cursos_eventos ce ON ce.id = t.curso_evento_id
             LEFT JOIN categorias c ON c.id = ce.categoria_id
             LEFT JOIN usuario_turmas ut ON ut.turma_id = t.id AND ut.tipo_vinculo = "professor" AND ut.deleted_at IS NULL
             LEFT JOIN usuarios u ON u.id = ut.usuario_id AND u.deleted_at IS NULL
             WHERE t.id = :id
               AND t.deleted_at IS NULL
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

    public function forPublicCourse($cursoId, $onlyOpen = false)
    {
        $sql = 'SELECT t.*
                FROM turmas t
                INNER JOIN cursos_eventos ce ON ce.id = t.curso_evento_id
                WHERE t.curso_evento_id = :curso_evento_id
                  AND t.deleted_at IS NULL
                  AND ce.deleted_at IS NULL
                  AND ce.status = "ativo"';

        if ($onlyOpen) {
            $sql .= ' AND t.status = "aberta"';
        }

        $sql .= ' ORDER BY t.data_inicio IS NULL, t.data_inicio ASC, t.nome ASC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(array('curso_evento_id' => $cursoId));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findPublicOpenForCourse($cursoId, $turmaId)
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
               AND t.curso_evento_id = :curso_evento_id
               AND t.deleted_at IS NULL
               AND t.status = "aberta"
               AND ce.deleted_at IS NULL
               AND ce.status = "ativo"
             LIMIT 1'
        );

        $stmt->execute(array(
            'id' => $turmaId,
            'curso_evento_id' => $cursoId,
        ));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function allWithCourse()
    {
        $stmt = Database::connection()->query(
            'SELECT t.*,
                    ce.nome AS curso_nome,
                    ce.tipo AS curso_tipo,
                    ce.modalidade AS curso_modalidade,
                    c.nome AS categoria_nome,
                    u.nome AS professor_responsavel_nome
             FROM turmas t
             INNER JOIN cursos_eventos ce ON ce.id = t.curso_evento_id
             LEFT JOIN categorias c ON c.id = ce.categoria_id
             LEFT JOIN usuario_turmas ut ON ut.turma_id = t.id AND ut.tipo_vinculo = "professor" AND ut.deleted_at IS NULL
             LEFT JOIN usuarios u ON u.id = ut.usuario_id AND u.deleted_at IS NULL
             WHERE t.deleted_at IS NULL
               AND ce.deleted_at IS NULL
             ORDER BY t.data_inicio IS NULL, t.data_inicio ASC, t.nome ASC'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function allForSelect()
    {
        $stmt = Database::connection()->query(
            'SELECT t.*,
                    ce.nome AS curso_nome
             FROM turmas t
             INNER JOIN cursos_eventos ce ON ce.id = t.curso_evento_id
             WHERE t.deleted_at IS NULL
               AND ce.deleted_at IS NULL
             ORDER BY t.data_inicio IS NULL, t.data_inicio ASC, t.nome ASC'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO turmas
             (curso_evento_id, nome, slug, codigo, data_inicio, data_fim, vagas, status, created_at, updated_at, deleted_at)
             VALUES
             (:curso_evento_id, :nome, :slug, :codigo, :data_inicio, :data_fim, :vagas, :status, NOW(), NOW(), NULL)'
        );

        $stmt->execute(array(
            'curso_evento_id' => $data['curso_evento_id'],
            'nome' => $data['nome'],
            'slug' => $data['slug'],
            'codigo' => $data['codigo'],
            'data_inicio' => isset($data['data_inicio']) && $data['data_inicio'] !== '' ? $data['data_inicio'] : null,
            'data_fim' => isset($data['data_fim']) && $data['data_fim'] !== '' ? $data['data_fim'] : null,
            'vagas' => isset($data['vagas']) && $data['vagas'] !== '' ? (int) $data['vagas'] : null,
            'status' => isset($data['status']) ? $data['status'] : 'planejada',
        ));

        return (int) Database::connection()->lastInsertId();
    }

    public function update(array $data, $id)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE turmas
             SET curso_evento_id = :curso_evento_id,
                 nome = :nome,
                 slug = :slug,
                 codigo = :codigo,
                 data_inicio = :data_inicio,
                 data_fim = :data_fim,
                 vagas = :vagas,
                 status = :status,
                 updated_at = NOW()
             WHERE id = :id'
        );

        $stmt->execute(array(
            'curso_evento_id' => $data['curso_evento_id'],
            'nome' => $data['nome'],
            'slug' => $data['slug'],
            'codigo' => $data['codigo'],
            'data_inicio' => isset($data['data_inicio']) && $data['data_inicio'] !== '' ? $data['data_inicio'] : null,
            'data_fim' => isset($data['data_fim']) && $data['data_fim'] !== '' ? $data['data_fim'] : null,
            'vagas' => isset($data['vagas']) && $data['vagas'] !== '' ? (int) $data['vagas'] : null,
            'status' => isset($data['status']) ? $data['status'] : 'planejada',
            'id' => $id,
        ));
    }

    public function softDelete($id)
    {
        $stmt = Database::connection()->prepare('UPDATE turmas SET deleted_at = NOW(), updated_at = NOW() WHERE id = :id');
        $stmt->execute(array('id' => $id));
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
