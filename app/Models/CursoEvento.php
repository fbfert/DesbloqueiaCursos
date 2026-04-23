<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class CursoEvento
{
    public function allPublic()
    {
        $stmt = Database::connection()->query(
            'SELECT ce.*,
                    c.nome AS categoria_nome,
                    COALESCE(t_total.total_turmas, 0) AS total_turmas
             FROM cursos_eventos ce
             LEFT JOIN categorias c ON c.id = ce.categoria_id
             LEFT JOIN (
                SELECT curso_evento_id, COUNT(*) AS total_turmas
                FROM turmas
                WHERE deleted_at IS NULL
                GROUP BY curso_evento_id
             ) t_total ON t_total.curso_evento_id = ce.id
             WHERE ce.deleted_at IS NULL
               AND ce.status = "ativo"
             ORDER BY ce.destaque DESC, ce.ordem ASC, ce.nome ASC'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

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

    public function findPublicById($id)
    {
        $stmt = Database::connection()->prepare(
            'SELECT ce.*,
                    c.nome AS categoria_nome,
                    c.slug AS categoria_slug
             FROM cursos_eventos ce
             LEFT JOIN categorias c ON c.id = ce.categoria_id
             WHERE ce.id = :id
               AND ce.deleted_at IS NULL
               AND ce.status = "ativo"
             LIMIT 1'
        );

        $stmt->execute(array('id' => $id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
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

    public function findBySlug($slug)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM cursos_eventos
             WHERE slug = :slug
               AND deleted_at IS NULL
             LIMIT 1'
        );

        $stmt->execute(array('slug' => $slug));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findAdminById($id)
    {
        $stmt = Database::connection()->prepare(
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
             WHERE ce.id = :id
               AND ce.deleted_at IS NULL
             LIMIT 1'
        );

        $stmt->execute(array('id' => $id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function allForSelect()
    {
        $stmt = Database::connection()->query(
            'SELECT id, nome, slug, tipo, status
             FROM cursos_eventos
             WHERE deleted_at IS NULL
             ORDER BY ordem ASC, nome ASC'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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

    public function create(array $data)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO cursos_eventos
             (categoria_id, nome, slug, tipo, modalidade, thumbnail, descricao_curta, descricao_completa, carga_horaria, valor, em_promocao, destaque, ordem, status, created_at, updated_at, deleted_at)
             VALUES
             (:categoria_id, :nome, :slug, :tipo, :modalidade, :thumbnail, :descricao_curta, :descricao_completa, :carga_horaria, :valor, :em_promocao, :destaque, :ordem, :status, NOW(), NOW(), NULL)'
        );

        $stmt->execute(array(
            'categoria_id' => isset($data['categoria_id']) ? $data['categoria_id'] : null,
            'nome' => $data['nome'],
            'slug' => $data['slug'],
            'tipo' => isset($data['tipo']) ? $data['tipo'] : 'curso',
            'modalidade' => isset($data['modalidade']) ? $data['modalidade'] : 'presencial',
            'thumbnail' => isset($data['thumbnail']) ? $data['thumbnail'] : null,
            'descricao_curta' => isset($data['descricao_curta']) ? $data['descricao_curta'] : null,
            'descricao_completa' => isset($data['descricao_completa']) ? $data['descricao_completa'] : null,
            'carga_horaria' => isset($data['carga_horaria']) ? $data['carga_horaria'] : null,
            'valor' => isset($data['valor']) ? $data['valor'] : 0,
            'em_promocao' => !empty($data['em_promocao']) ? 1 : 0,
            'destaque' => !empty($data['destaque']) ? 1 : 0,
            'ordem' => isset($data['ordem']) ? (int) $data['ordem'] : 0,
            'status' => isset($data['status']) ? $data['status'] : 'rascunho',
        ));

        return (int) Database::connection()->lastInsertId();
    }

    public function update(array $data, $id)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE cursos_eventos
             SET categoria_id = :categoria_id,
                 nome = :nome,
                 slug = :slug,
                 tipo = :tipo,
                 modalidade = :modalidade,
                 thumbnail = :thumbnail,
                 descricao_curta = :descricao_curta,
                 descricao_completa = :descricao_completa,
                 carga_horaria = :carga_horaria,
                 valor = :valor,
                 em_promocao = :em_promocao,
                 destaque = :destaque,
                 ordem = :ordem,
                 status = :status,
                 updated_at = NOW()
             WHERE id = :id'
        );

        $stmt->execute(array(
            'categoria_id' => isset($data['categoria_id']) ? $data['categoria_id'] : null,
            'nome' => $data['nome'],
            'slug' => $data['slug'],
            'tipo' => isset($data['tipo']) ? $data['tipo'] : 'curso',
            'modalidade' => isset($data['modalidade']) ? $data['modalidade'] : 'presencial',
            'thumbnail' => isset($data['thumbnail']) ? $data['thumbnail'] : null,
            'descricao_curta' => isset($data['descricao_curta']) ? $data['descricao_curta'] : null,
            'descricao_completa' => isset($data['descricao_completa']) ? $data['descricao_completa'] : null,
            'carga_horaria' => isset($data['carga_horaria']) ? $data['carga_horaria'] : null,
            'valor' => isset($data['valor']) ? $data['valor'] : 0,
            'em_promocao' => !empty($data['em_promocao']) ? 1 : 0,
            'destaque' => !empty($data['destaque']) ? 1 : 0,
            'ordem' => isset($data['ordem']) ? (int) $data['ordem'] : 0,
            'status' => isset($data['status']) ? $data['status'] : 'rascunho',
            'id' => $id,
        ));
    }

    public function softDelete($id)
    {
        $stmt = Database::connection()->prepare('UPDATE cursos_eventos SET deleted_at = NOW(), updated_at = NOW() WHERE id = :id');
        $stmt->execute(array('id' => $id));
    }
}
