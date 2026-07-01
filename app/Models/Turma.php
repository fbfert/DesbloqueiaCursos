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
               AND t.status <> "excluida"
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

    public function allWithCourse(array $filters = array())
    {
        $sql = 'SELECT t.*,
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
                WHERE ce.deleted_at IS NULL';
        $params = array();

        if (!empty($filters['excluded_only'])) {
            $sql .= ' AND (t.deleted_at IS NOT NULL OR t.status = "excluida")';
        } elseif (!empty($filters['deleted_only'])) {
            $sql .= ' AND t.deleted_at IS NOT NULL';
        } else {
            $sql .= ' AND t.deleted_at IS NULL AND t.status <> "excluida"';
        }

        if (!empty($filters['statuses']) && is_array($filters['statuses'])) {
            $statusPlaceholders = array();
            foreach (array_values($filters['statuses']) as $index => $status) {
                $placeholder = 'status_' . $index;
                $statusPlaceholders[] = ':' . $placeholder;
                $params[$placeholder] = $status;
            }
            if ($statusPlaceholders) {
                $sql .= ' AND t.status IN (' . implode(', ', $statusPlaceholders) . ')';
            }
        }

        if (!empty($filters['curso_id'])) {
            $sql .= ' AND t.curso_evento_id = :curso_id';
            $params['curso_id'] = (int) $filters['curso_id'];
        }

        if (!empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            if ($search !== '') {
                $sql .= ' AND (t.nome LIKE :search OR ce.nome LIKE :search OR t.codigo LIKE :search OR t.slug LIKE :search)';
                $params['search'] = '%' . $search . '%';
            }
        }

        $orderMode = isset($filters['order_mode']) ? (string) $filters['order_mode'] : 'padrao';
        if ($orderMode === 'relevancia') {
            $sql .= ' ORDER BY FIELD(t.status, "planejada", "aberta") ASC, t.data_inicio IS NULL, t.data_inicio ASC, t.nome ASC';
        } elseif ($orderMode === 'excluidas') {
            $sql .= ' ORDER BY t.deleted_at DESC, t.data_inicio IS NULL, t.data_inicio ASC, t.nome ASC';
        } else {
            $sql .= ' ORDER BY t.data_inicio IS NULL, t.data_inicio ASC, t.nome ASC';
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function paginateAdmin(array $filters = array(), $page = 1, $perPage = 20)
    {
        $page = (int) $page;
        if ($page < 1) {
            $page = 1;
        }

        $perPage = (int) $perPage;
        if ($perPage < 1) {
            $perPage = 20;
        }
        if ($perPage > 100) {
            $perPage = 100;
        }

        $sql = 'FROM turmas t
                INNER JOIN cursos_eventos ce ON ce.id = t.curso_evento_id
                LEFT JOIN categorias c ON c.id = ce.categoria_id
                LEFT JOIN usuario_turmas ut ON ut.turma_id = t.id AND ut.tipo_vinculo = "professor" AND ut.deleted_at IS NULL
                LEFT JOIN usuarios u ON u.id = ut.usuario_id AND u.deleted_at IS NULL
                WHERE ce.deleted_at IS NULL';
        $params = array();

        $sql .= ' AND t.deleted_at IS NULL AND t.status <> "excluida"';

        if (!empty($filters['curso_id'])) {
            $sql .= ' AND t.curso_evento_id = :curso_id';
            $params['curso_id'] = (int) $filters['curso_id'];
        }

        $status = isset($filters['status']) ? trim((string) $filters['status']) : '';
        if ($status !== '') {
            $sql .= ' AND t.status = :status';
            $params['status'] = $status;
        }

        $search = isset($filters['search']) ? trim((string) $filters['search']) : '';
        if ($search !== '') {
            $search = $this->limitarTextoSeguro(strip_tags($search), 120);
            $sql .= ' AND (
                t.nome LIKE :search
                OR t.codigo LIKE :search
                OR t.slug LIKE :search
                OR ce.nome LIKE :search
                OR CAST(t.id AS CHAR) = :search_id
            )';
            $params['search'] = '%' . $search . '%';
            $params['search_id'] = ctype_digit($search) ? (int) $search : -1;
        }

        $countStmt = Database::connection()->prepare('SELECT COUNT(*) AS total ' . $sql);
        $countStmt->execute($params);
        $countRow = $countStmt->fetch(PDO::FETCH_ASSOC);
        $total = (int) ($countRow['total'] ?? 0);

        $statusTotalsStmt = Database::connection()->prepare(
            'SELECT t.status, COUNT(*) AS total ' . $sql . ' GROUP BY t.status'
        );
        $statusTotalsStmt->execute($params);
        $statusTotalsRows = $statusTotalsStmt->fetchAll(PDO::FETCH_ASSOC);
        $statusTotals = array();
        foreach ($statusTotalsRows as $row) {
            $statusKey = isset($row['status']) ? (string) $row['status'] : '';
            if ($statusKey !== '') {
                $statusTotals[$statusKey] = (int) ($row['total'] ?? 0);
            }
        }

        $orderMode = isset($filters['order_mode']) ? (string) $filters['order_mode'] : 'padrao';
        if ($orderMode === 'relevancia') {
            $orderSql = ' ORDER BY FIELD(t.status, "planejada", "aberta") ASC, t.data_inicio IS NULL, t.data_inicio ASC, t.nome ASC';
        } elseif ($orderMode === 'excluidas') {
            $orderSql = ' ORDER BY t.deleted_at DESC, t.data_inicio IS NULL, t.data_inicio ASC, t.nome ASC';
        } else {
            $orderSql = ' ORDER BY t.data_inicio IS NULL, t.data_inicio ASC, t.nome ASC';
        }

        $offset = ($page - 1) * $perPage;
        $dataSql = 'SELECT t.*,
                           ce.nome AS curso_nome,
                           ce.tipo AS curso_tipo,
                           ce.modalidade AS curso_modalidade,
                           c.nome AS categoria_nome,
                           u.nome AS professor_responsavel_nome ' . $sql . $orderSql . ' LIMIT :limit OFFSET :offset';
        $stmt = Database::connection()->prepare($dataSql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return array(
            'items' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'pagination' => array(
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'pages' => $perPage > 0 ? (int) ceil($total / $perPage) : 1,
            ),
            'status_totals' => $statusTotals,
        );
    }

    private function limitarTextoSeguro($texto, $limite)
    {
        $texto = (string) $texto;
        $limite = (int) $limite;

        if ($limite <= 0) {
            return '';
        }

        if (function_exists('mb_substr')) {
            return mb_substr($texto, 0, $limite);
        }

        return substr($texto, 0, $limite);
    }

    public function allForSelect()
    {
        $stmt = Database::connection()->query(
            'SELECT t.*,
                    ce.nome AS curso_nome
             FROM turmas t
             INNER JOIN cursos_eventos ce ON ce.id = t.curso_evento_id
             WHERE t.deleted_at IS NULL
               AND t.status <> "excluida"
               AND ce.deleted_at IS NULL
             ORDER BY t.data_inicio IS NULL, t.data_inicio ASC, t.nome ASC'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO turmas
             (curso_evento_id, nome, slug, codigo, data_inicio, data_fim, local, vagas, status, created_at, updated_at, deleted_at)
             VALUES
             (:curso_evento_id, :nome, :slug, :codigo, :data_inicio, :data_fim, :local, :vagas, :status, NOW(), NOW(), NULL)'
        );

        $stmt->execute(array(
            'curso_evento_id' => $data['curso_evento_id'],
            'nome' => $data['nome'],
            'slug' => $data['slug'],
            'codigo' => $data['codigo'],
            'data_inicio' => isset($data['data_inicio']) && $data['data_inicio'] !== '' ? $data['data_inicio'] : null,
            'data_fim' => isset($data['data_fim']) && $data['data_fim'] !== '' ? $data['data_fim'] : null,
            'local' => array_key_exists('local', $data) && $data['local'] !== '' ? $data['local'] : null,
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
                 local = :local,
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
            'local' => array_key_exists('local', $data) && $data['local'] !== '' ? $data['local'] : null,
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
               AND t.status <> "excluida"
               AND ce.deleted_at IS NULL
             ORDER BY t.data_inicio IS NULL, t.data_inicio ASC, t.nome ASC'
        );

        $stmt->execute(array('usuario_id' => $usuarioId));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
