<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class CursoEvento
{
    private function existsTurmaAbertaSql()
    {
        return 'EXISTS (
                    SELECT 1
                    FROM turmas t_open
                    WHERE t_open.curso_evento_id = ce.id
                      AND t_open.deleted_at IS NULL
                      AND t_open.status = "aberta"
                )';
    }

    public function allPublic(array $filters = array())
    {
        $sql = 'SELECT ce.*,
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
                  AND ' . $this->existsTurmaAbertaSql();
        $params = array();

        if (!empty($filters['categoria_id'])) {
            $sql .= ' AND ce.categoria_id = :categoria_id';
            $params['categoria_id'] = (int) $filters['categoria_id'];
        }

        if (array_key_exists('destaque', $filters) && $filters['destaque'] !== '') {
            $sql .= ' AND ce.destaque = :destaque';
            $params['destaque'] = (int) $filters['destaque'];
        }

        if (!empty($filters['busca'])) {
            $sql .= ' AND (
                ce.nome LIKE :busca
                OR ce.descricao_curta LIKE :busca
                OR ce.descricao_completa LIKE :busca
                OR c.nome LIKE :busca
            )';
            $params['busca'] = '%' . $this->normalizarBuscaPublica($filters['busca']) . '%';
        }

        $sql .= ' ORDER BY ce.destaque DESC, ce.ordem ASC, ce.nome ASC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function allPublicByCategoriaId($categoriaId)
    {
        return $this->allPublic(array('categoria_id' => (int) $categoriaId));
    }

    private function normalizarBuscaPublica($busca)
    {
        $busca = (string) $busca;
        $busca = trim(strip_tags($busca));

        return $this->limitarTextoSeguro($busca, 80);
    }

    private function normalizarBuscaAdmin($busca)
    {
        $busca = (string) $busca;
        $busca = trim(strip_tags($busca));

        return $this->limitarTextoSeguro($busca, 120);
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

    private function montarFiltrosAdminSql(array $filters, array &$params)
    {
        $sql = ' WHERE ce.deleted_at IS NULL';

        $busca = isset($filters['busca']) ? $this->normalizarBuscaAdmin($filters['busca']) : '';
        if ($busca !== '') {
            $sql .= ' AND (
                ce.nome LIKE :busca
                OR ce.slug LIKE :busca
                OR CAST(ce.id AS CHAR) = :busca_id
                OR c.nome LIKE :busca
            )';
            $params['busca'] = '%' . $busca . '%';
            $params['busca_id'] = ctype_digit($busca) ? (int) $busca : -1;
        }

        $categoriaId = isset($filters['categoria_id']) ? (int) $filters['categoria_id'] : 0;
        if ($categoriaId > 0) {
            $sql .= ' AND ce.categoria_id = :categoria_id';
            $params['categoria_id'] = $categoriaId;
        }

        $status = isset($filters['status']) ? trim((string) $filters['status']) : '';
        if ($status !== '') {
            $sql .= ' AND ce.status = :status';
            $params['status'] = $status;
        }

        $tipo = isset($filters['tipo']) ? trim((string) $filters['tipo']) : '';
        if ($tipo !== '') {
            $sql .= ' AND ce.tipo = :tipo';
            $params['tipo'] = $tipo;
        }

        $modalidade = isset($filters['modalidade']) ? trim((string) $filters['modalidade']) : '';
        if ($modalidade !== '') {
            $sql .= ' AND ce.modalidade = :modalidade';
            $params['modalidade'] = $modalidade;
        }

        return $sql;
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

        $params = array();
        $whereSql = $this->montarFiltrosAdminSql($filters, $params);

        $countSql = 'SELECT COUNT(*) AS total
                     FROM cursos_eventos ce
                     LEFT JOIN categorias c ON c.id = ce.categoria_id' . $whereSql;
        $countStmt = Database::connection()->prepare($countSql);
        $countStmt->execute($params);
        $countRow = $countStmt->fetch(PDO::FETCH_ASSOC);
        $total = (int) ($countRow['total'] ?? 0);

        $statusTotalsSql = 'SELECT ce.status, COUNT(*) AS total
                            FROM cursos_eventos ce
                            LEFT JOIN categorias c ON c.id = ce.categoria_id' . $whereSql . '
                            GROUP BY ce.status';
        $statusStmt = Database::connection()->prepare($statusTotalsSql);
        $statusStmt->execute($params);
        $statusTotalsRows = $statusStmt->fetchAll(PDO::FETCH_ASSOC);
        $statusTotals = array();
        foreach ($statusTotalsRows as $row) {
            $statusKey = isset($row['status']) ? (string) $row['status'] : '';
            if ($statusKey !== '') {
                $statusTotals[$statusKey] = (int) ($row['total'] ?? 0);
            }
        }

        $offset = ($page - 1) * $perPage;
        $dataSql = 'SELECT ce.*,
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
                    ) p_total ON p_total.curso_evento_id = ce.id' . $whereSql . '
                    ORDER BY ce.ordem ASC, ce.nome ASC
                    LIMIT :limit OFFSET :offset';

        $dataStmt = Database::connection()->prepare($dataSql);
        foreach ($params as $key => $value) {
            $dataStmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $dataStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $dataStmt->execute();

        return array(
            'items' => $dataStmt->fetchAll(PDO::FETCH_ASSOC),
            'pagination' => array(
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'pages' => $perPage > 0 ? (int) ceil($total / $perPage) : 1,
            ),
            'status_totals' => $statusTotals,
        );
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

    public function allForSelect(array $statuses = array())
    {
        $sql = 'SELECT id, nome, slug, tipo, status
                FROM cursos_eventos
                WHERE deleted_at IS NULL';
        $params = array();

        if (!empty($statuses)) {
            $placeholders = array();
            foreach (array_values($statuses) as $index => $status) {
                $placeholder = ':status_' . $index;
                $placeholders[] = $placeholder;
                $params['status_' . $index] = $status;
            }

            $sql .= ' AND status IN (' . implode(', ', $placeholders) . ')';
        }

        $sql .= ' ORDER BY ordem ASC, nome ASC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

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

    public function topPublicBySales($limit = 5)
    {
        $limit = (int) $limit;
        if ($limit < 1 || $limit > 20) {
            $limit = 5;
        }

        $sql = 'SELECT ce.*,
                       c.nome AS categoria_nome,
                       COALESCE(vendas.total_vendas, 0) AS total_vendas,
                       COALESCE(vendas.receita_total, 0) AS receita_total
                FROM cursos_eventos ce
                LEFT JOIN categorias c ON c.id = ce.categoria_id
                INNER JOIN (
                    SELECT pi.curso_evento_id,
                           SUM(CASE WHEN pi.quantidade > 0 THEN pi.quantidade ELSE 1 END) AS total_vendas,
                           SUM(pi.valor_total) AS receita_total
                    FROM pedido_itens pi
                    INNER JOIN pedidos p
                        ON p.id = pi.pedido_id
                       AND p.deleted_at IS NULL
                       AND COALESCE(p.is_presente, 0) = 0
                    LEFT JOIN comprovantes_pix cp
                        ON cp.pedido_id = p.id
                       AND cp.deleted_at IS NULL
                    WHERE pi.deleted_at IS NULL
                      AND pi.status = "ativo"
                      AND (p.status IN ("aprovado", "pago") OR cp.status = "aprovado")
                    GROUP BY pi.curso_evento_id
                ) vendas ON vendas.curso_evento_id = ce.id
                WHERE ce.deleted_at IS NULL
                  AND ce.status = "ativo"
                  AND ' . $this->existsTurmaAbertaSql() . '
                ORDER BY vendas.total_vendas DESC, vendas.receita_total DESC, ce.nome ASC
                LIMIT ' . $limit;

        $stmt = Database::connection()->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO cursos_eventos
             (categoria_id, nome, slug, tipo, modalidade, thumbnail, descricao_curta, descricao_completa, carga_horaria, valor, valor_promocional,
              objetivo_geral, objetivos_especificos, publico_alvo, pre_requisitos_texto, pre_requisitos_itens, ementa,
              conteudo_programatico_tipo, conteudo_programatico_texto, conteudo_programatico_modulos,
              metodologia, produto_final, avaliacao,
              em_promocao, destaque, ordem, status, created_at, updated_at, deleted_at)
             VALUES
             (:categoria_id, :nome, :slug, :tipo, :modalidade, :thumbnail, :descricao_curta, :descricao_completa, :carga_horaria, :valor, :valor_promocional,
              :objetivo_geral, :objetivos_especificos, :publico_alvo, :pre_requisitos_texto, :pre_requisitos_itens, :ementa,
              :conteudo_programatico_tipo, :conteudo_programatico_texto, :conteudo_programatico_modulos,
              :metodologia, :produto_final, :avaliacao,
              :em_promocao, :destaque, :ordem, :status, NOW(), NOW(), NULL)'
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
            'valor_promocional' => array_key_exists('valor_promocional', $data) ? $data['valor_promocional'] : null,
            'usar_turmas' => array_key_exists('usar_turmas', $data) ? (int) (bool) $data['usar_turmas'] : 1,
            'objetivo_geral' => array_key_exists('objetivo_geral', $data) ? $data['objetivo_geral'] : null,
            'objetivos_especificos' => array_key_exists('objetivos_especificos', $data) ? $data['objetivos_especificos'] : null,
            'publico_alvo' => array_key_exists('publico_alvo', $data) ? $data['publico_alvo'] : null,
            'pre_requisitos_texto' => array_key_exists('pre_requisitos_texto', $data) ? $data['pre_requisitos_texto'] : null,
            'pre_requisitos_itens' => array_key_exists('pre_requisitos_itens', $data) ? $data['pre_requisitos_itens'] : null,
            'ementa' => array_key_exists('ementa', $data) ? $data['ementa'] : null,
            'conteudo_programatico_tipo' => isset($data['conteudo_programatico_tipo']) ? $data['conteudo_programatico_tipo'] : 'texto',
            'conteudo_programatico_texto' => array_key_exists('conteudo_programatico_texto', $data) ? $data['conteudo_programatico_texto'] : null,
            'conteudo_programatico_modulos' => array_key_exists('conteudo_programatico_modulos', $data) ? $data['conteudo_programatico_modulos'] : null,
            'metodologia' => array_key_exists('metodologia', $data) ? $data['metodologia'] : null,
            'produto_final' => array_key_exists('produto_final', $data) ? $data['produto_final'] : null,
            'avaliacao' => array_key_exists('avaliacao', $data) ? $data['avaliacao'] : null,
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
                 valor_promocional = :valor_promocional,
                 usar_turmas = :usar_turmas,
                 objetivo_geral = :objetivo_geral,
                 objetivos_especificos = :objetivos_especificos,
                 publico_alvo = :publico_alvo,
                 pre_requisitos_texto = :pre_requisitos_texto,
                 pre_requisitos_itens = :pre_requisitos_itens,
                 ementa = :ementa,
                 conteudo_programatico_tipo = :conteudo_programatico_tipo,
                 conteudo_programatico_texto = :conteudo_programatico_texto,
                 conteudo_programatico_modulos = :conteudo_programatico_modulos,
                 metodologia = :metodologia,
                 produto_final = :produto_final,
                 avaliacao = :avaliacao,
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
            'valor_promocional' => array_key_exists('valor_promocional', $data) ? $data['valor_promocional'] : null,
            'usar_turmas' => array_key_exists('usar_turmas', $data) ? (int) (bool) $data['usar_turmas'] : 1,
            'objetivo_geral' => array_key_exists('objetivo_geral', $data) ? $data['objetivo_geral'] : null,
            'objetivos_especificos' => array_key_exists('objetivos_especificos', $data) ? $data['objetivos_especificos'] : null,
            'publico_alvo' => array_key_exists('publico_alvo', $data) ? $data['publico_alvo'] : null,
            'pre_requisitos_texto' => array_key_exists('pre_requisitos_texto', $data) ? $data['pre_requisitos_texto'] : null,
            'pre_requisitos_itens' => array_key_exists('pre_requisitos_itens', $data) ? $data['pre_requisitos_itens'] : null,
            'ementa' => array_key_exists('ementa', $data) ? $data['ementa'] : null,
            'conteudo_programatico_tipo' => isset($data['conteudo_programatico_tipo']) ? $data['conteudo_programatico_tipo'] : 'texto',
            'conteudo_programatico_texto' => array_key_exists('conteudo_programatico_texto', $data) ? $data['conteudo_programatico_texto'] : null,
            'conteudo_programatico_modulos' => array_key_exists('conteudo_programatico_modulos', $data) ? $data['conteudo_programatico_modulos'] : null,
            'metodologia' => array_key_exists('metodologia', $data) ? $data['metodologia'] : null,
            'produto_final' => array_key_exists('produto_final', $data) ? $data['produto_final'] : null,
            'avaliacao' => array_key_exists('avaliacao', $data) ? $data['avaliacao'] : null,
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

    public function updateStatus($id, $status)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE cursos_eventos
             SET status = :status,
                 updated_at = NOW()
             WHERE id = :id'
        );

        $stmt->execute(array(
            'status' => $status,
            'id' => $id,
        ));
    }
}
