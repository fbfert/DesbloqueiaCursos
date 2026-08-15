<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class Usuario
{
    private static $hasCidadeEstadoColumns = null;

    public function allForAdmin(array $filters = array())
    {
        $allowedSort = array('id', 'nome', 'email', 'cpf', 'status');
        $sortBy = isset($filters['sort_by']) && in_array($filters['sort_by'], $allowedSort, true) ? $filters['sort_by'] : 'id';
        $sortDir = isset($filters['sort_dir']) && strtolower((string) $filters['sort_dir']) === 'asc' ? 'ASC' : 'DESC';
        $search = isset($filters['q']) ? trim((string) $filters['q']) : '';
        $status = isset($filters['status']) ? trim((string) $filters['status']) : '';
        $perfil = isset($filters['perfil']) ? trim((string) $filters['perfil']) : '';

        $where = array('u.deleted_at IS NULL');
        $params = array();

        if ($search !== '') {
            $where[] = '(u.nome LIKE :q OR u.email LIKE :q OR u.cpf LIKE :q)';
            $params['q'] = '%' . $search . '%';
        }

        if (in_array($status, array('ativo', 'inativo', 'bloqueado'), true)) {
            $where[] = 'u.status = :status';
            $params['status'] = $status;
        }

        if ($perfil !== '') {
            $where[] = 'EXISTS (
                SELECT 1
                FROM usuario_perfis upx
                INNER JOIN perfis px ON px.id = upx.perfil_id AND px.deleted_at IS NULL
                WHERE upx.usuario_id = u.id
                  AND px.slug = :perfil
            )';
            $params['perfil'] = $perfil;
        }

        $sql = 'SELECT u.*,
                       COALESCE(GROUP_CONCAT(DISTINCT p.nome ORDER BY p.nome SEPARATOR ", "), "") AS perfis
                FROM usuarios u
                LEFT JOIN usuario_perfis up ON up.usuario_id = u.id
                LEFT JOIN perfis p ON p.id = up.perfil_id AND p.deleted_at IS NULL
                WHERE ' . implode(' AND ', $where) . '
                GROUP BY u.id
                ORDER BY u.' . $sortBy . ' ' . $sortDir;

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById($id)
    {
        $stmt = Database::connection()->prepare(
            'SELECT u.*,
                    COALESCE(GROUP_CONCAT(DISTINCT p.nome ORDER BY p.nome SEPARATOR ", "), "") AS perfis
             FROM usuarios u
             LEFT JOIN usuario_perfis up ON up.usuario_id = u.id
             LEFT JOIN perfis p ON p.id = up.perfil_id AND p.deleted_at IS NULL
             WHERE u.deleted_at IS NULL AND u.id = :id
             GROUP BY u.id
             LIMIT 1'
        );
        $stmt->execute(array('id' => (int) $id));

        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        return $usuario ?: null;
    }

    public function findAlunoById($id)
    {
        $stmt = Database::connection()->prepare(
            'SELECT u.*,
                    COALESCE(GROUP_CONCAT(DISTINCT p.nome ORDER BY p.nome SEPARATOR ", "), "") AS perfis
             FROM usuarios u
             LEFT JOIN usuario_perfis up ON up.usuario_id = u.id
             LEFT JOIN perfis p ON p.id = up.perfil_id AND p.deleted_at IS NULL
             WHERE u.deleted_at IS NULL
               AND u.id = :id
             GROUP BY u.id
             LIMIT 1'
        );
        $stmt->execute(array('id' => (int) $id));

        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        return $usuario ?: null;
    }

    public function findByLogin($login)
    {
        $login = trim((string) $login);
        $cpf = preg_replace('/\D+/', '', $login);

        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM usuarios
             WHERE deleted_at IS NULL
               AND (email = :email OR cpf = :cpf)
             LIMIT 1'
        );

        $stmt->execute(array(
            'email' => strtolower($login),
            'cpf' => $cpf,
        ));

        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        return $usuario ?: null;
    }

    public function findByEmail($email)
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM usuarios WHERE deleted_at IS NULL AND email = :email LIMIT 1'
        );
        $stmt->execute(array('email' => strtolower(trim((string) $email))));

        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        return $usuario ?: null;
    }

    public function findByCpf($cpf)
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM usuarios WHERE deleted_at IS NULL AND cpf = :cpf LIMIT 1'
        );
        $stmt->execute(array('cpf' => preg_replace('/\D+/', '', (string) $cpf)));

        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        return $usuario ?: null;
    }

    public function buscarAlunosParaPedidoManual($termo, $limit = 12)
    {
        $termo = trim((string) $termo);
        $limit = (int) $limit;
        if ($limit < 1 || $limit > 30) {
            $limit = 12;
        }

        $where = array(
            'u.deleted_at IS NULL',
            'u.status = "ativo"',
            'p.slug = "aluno"',
        );
        $params = array();

        if ($termo !== '') {
            $where[] = '(u.nome LIKE :q OR u.email LIKE :q OR u.cpf LIKE :q)';
            $params['q'] = '%' . $termo . '%';
        }

        $sql = 'SELECT u.id,
                       u.nome,
                       u.email,
                       u.cpf,
                       u.telefone
                FROM usuarios u
                INNER JOIN usuario_perfis up ON up.usuario_id = u.id
                INNER JOIN perfis p ON p.id = up.perfil_id
                WHERE ' . implode(' AND ', $where) . '
                GROUP BY u.id
                ORDER BY u.nome ASC
                LIMIT ' . $limit;

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarAlunosParaCertificadoRapido($termo, $limit = 20)
    {
        $termo = trim((string) $termo);
        $limit = (int) $limit;
        if ($limit < 1 || $limit > 50) {
            $limit = 20;
        }

        $digits = preg_replace('/\D+/', '', $termo);
        $termLower = mb_strtolower($termo, 'UTF-8');
        $termCompact = preg_replace('/\s+/', '', $termLower);
        $where = array(
            'u.deleted_at IS NULL',
        );
        $params = array();

        if ($termo !== '') {
            $where[] = '('
                . 'LOWER(TRIM(u.nome)) LIKE :q_nome'
                . ' OR REPLACE(LOWER(TRIM(u.nome)), " ", "") LIKE :q_nome_compact'
                . ' OR LOWER(TRIM(u.email)) LIKE :q_email'
                . ' OR REPLACE(LOWER(TRIM(u.email)), " ", "") LIKE :q_email_compact'
                . ($digits !== '' ? ' OR REPLACE(REPLACE(REPLACE(u.cpf, ".", ""), "-", ""), " ", "") LIKE :q_cpf' : '')
                . ')';
            $params['q_nome'] = '%' . $termLower . '%';
            $params['q_nome_compact'] = '%' . $termCompact . '%';
            $params['q_email'] = '%' . $termLower . '%';
            $params['q_email_compact'] = '%' . $termCompact . '%';
            if ($digits !== '') {
                $params['q_cpf'] = '%' . $digits . '%';
            }
        }

        $sql = 'SELECT u.id,
                       u.nome,
                       u.email,
                       u.cpf,
                       u.telefone
                FROM usuarios u
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY u.nome ASC
                LIMIT ' . $limit;

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByRecoveryToken($token)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM usuarios
             WHERE deleted_at IS NULL
               AND token_recuperacao = :token
               AND token_recuperacao_expira_em >= NOW()
             LIMIT 1'
        );
        $stmt->execute(array('token' => $token));

        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        return $usuario ?: null;
    }

    public function create(array $data)
    {
        $hasCidadeEstado = $this->supportsCidadeEstadoColumns();
        $sql = $hasCidadeEstado
            ? 'INSERT INTO usuarios
               (nome, email, cpf, senha_hash, telefone, cidade, estado, status, tentativas_login, bloqueado_ate,
                token_recuperacao, token_recuperacao_expira_em, ultimo_login_em, created_at, updated_at, deleted_at)
               VALUES
               (:nome, :email, :cpf, :senha_hash, :telefone, :cidade, :estado, :status, 0, NULL, NULL, NULL, NULL, NOW(), NOW(), NULL)'
            : 'INSERT INTO usuarios
               (nome, email, cpf, senha_hash, telefone, status, tentativas_login, bloqueado_ate,
                token_recuperacao, token_recuperacao_expira_em, ultimo_login_em, created_at, updated_at, deleted_at)
               VALUES
               (:nome, :email, :cpf, :senha_hash, :telefone, :status, 0, NULL, NULL, NULL, NULL, NOW(), NOW(), NULL)';

        $params = array(
            'nome' => $data['nome'],
            'email' => strtolower(trim($data['email'])),
            'cpf' => preg_replace('/\D+/', '', $data['cpf']),
            'senha_hash' => $data['senha_hash'],
            'telefone' => isset($data['telefone']) ? preg_replace('/\D+/', '', $data['telefone']) : null,
            'status' => 'ativo',
        );

        if ($hasCidadeEstado) {
            $params['cidade'] = isset($data['cidade']) ? trim((string) $data['cidade']) : null;
            $params['estado'] = isset($data['estado']) ? strtoupper(trim((string) $data['estado'])) : null;
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return (int) Database::connection()->lastInsertId();
    }

    public function updatePassword($usuarioId, $senhaHash)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE usuarios
             SET senha_hash = :senha_hash,
                 token_recuperacao = NULL,
                 token_recuperacao_expira_em = NULL,
                 tentativas_login = 0,
                 bloqueado_ate = NULL,
                 updated_at = NOW()
             WHERE id = :id'
        );

        $stmt->execute(array(
            'senha_hash' => $senhaHash,
            'id' => $usuarioId,
        ));
    }

    public function setRecoveryToken($usuarioId, $token, $validadeMinutos = 60)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE usuarios
             SET token_recuperacao = :token,
                 token_recuperacao_expira_em = DATE_ADD(NOW(), INTERVAL ' . (int) $validadeMinutos . ' MINUTE),
                 updated_at = NOW()
             WHERE id = :id'
        );

        $stmt->execute(array(
            'token' => $token,
            'id' => $usuarioId,
        ));
    }

    public function updateProfile($usuarioId, array $data)
    {
        $hasCidadeEstado = $this->supportsCidadeEstadoColumns();
        $sql = 'UPDATE usuarios
                SET nome = :nome,
                    email = :email,
                    cpf = :cpf,
                    telefone = :telefone';

        if ($hasCidadeEstado) {
            $sql .= ',
                    cidade = :cidade,
                    estado = :estado';
        }

        $sql .= ',
                    updated_at = NOW()
                WHERE id = :id
                  AND deleted_at IS NULL';

        $params = array(
            'id' => (int) $usuarioId,
            'nome' => $data['nome'],
            'email' => strtolower(trim((string) $data['email'])),
            'cpf' => preg_replace('/\D+/', '', (string) $data['cpf']),
            'telefone' => isset($data['telefone']) ? preg_replace('/\D+/', '', (string) $data['telefone']) : null,
        );

        if ($hasCidadeEstado) {
            $params['cidade'] = isset($data['cidade']) ? trim((string) $data['cidade']) : null;
            $params['estado'] = isset($data['estado']) ? strtoupper(trim((string) $data['estado'])) : null;
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
    }

    public function updateAdmin($usuarioId, array $data)
    {
        $hasCidadeEstado = $this->supportsCidadeEstadoColumns();
        $sql = 'UPDATE usuarios
                SET nome = :nome,
                    email = :email,
                    cpf = :cpf,
                    telefone = :telefone,
                    status = :status,
                    updated_at = NOW()';

        if ($hasCidadeEstado) {
            $sql .= ',
                    cidade = :cidade,
                    estado = :estado';
        }

        $params = array(
            'id' => (int) $usuarioId,
            'nome' => $data['nome'],
            'email' => strtolower(trim((string) $data['email'])),
            'cpf' => preg_replace('/\D+/', '', (string) $data['cpf']),
            'telefone' => isset($data['telefone']) ? preg_replace('/\D+/', '', (string) $data['telefone']) : null,
            'status' => isset($data['status']) ? $data['status'] : 'ativo',
        );

        if ($hasCidadeEstado) {
            $params['cidade'] = isset($data['cidade']) ? trim((string) $data['cidade']) : null;
            $params['estado'] = isset($data['estado']) ? strtoupper(trim((string) $data['estado'])) : null;
        }

        if (!empty($data['senha_hash'])) {
            $sql .= ', senha_hash = :senha_hash';
            $params['senha_hash'] = $data['senha_hash'];
        }

        $sql .= ' WHERE id = :id
                  AND deleted_at IS NULL';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
    }

    public function softDelete($usuarioId)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE usuarios
             SET deleted_at = NOW(),
                 updated_at = NOW()
             WHERE id = :id
               AND deleted_at IS NULL'
        );
        $stmt->execute(array('id' => (int) $usuarioId));
    }

    public function setStatus($usuarioId, $status)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE usuarios
             SET status = :status,
                 updated_at = NOW()
             WHERE id = :id
               AND deleted_at IS NULL'
        );
        $stmt->execute(array(
            'id' => (int) $usuarioId,
            'status' => (string) $status,
        ));
    }

    private function supportsCidadeEstadoColumns()
    {
        if (self::$hasCidadeEstadoColumns !== null) {
            return self::$hasCidadeEstadoColumns;
        }

        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) AS total
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = "usuarios"
               AND COLUMN_NAME IN ("cidade", "estado")'
        );
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        self::$hasCidadeEstadoColumns = !empty($row) && (int) $row['total'] === 2;

        return self::$hasCidadeEstadoColumns;
    }

    public function resetLoginAttempts($usuarioId)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE usuarios
             SET tentativas_login = 0,
                 bloqueado_ate = NULL,
                 ultimo_login_em = NOW(),
                 updated_at = NOW()
             WHERE id = :id'
        );

        $stmt->execute(array('id' => $usuarioId));
    }

    public function incrementLoginAttempts($usuarioId, $currentAttempts, $lockMinutes, $maxAttempts = 5)
    {
        $nextAttempts = (int) $currentAttempts + 1;
        $bloquear = $nextAttempts >= (int) $maxAttempts;

        // O prazo e calculado pelo PHP, e nao por DATE_ADD(NOW(), ...), porque
        // quem o le e AuthService::isBlocked(), que compara com time(). Neste
        // servidor o PHP roda em UTC e o MySQL em horario local: gravado por
        // NOW(), o bloqueado_ate nascia tres horas no passado em relacao ao
        // relogio do PHP, e um bloqueio de 15 minutos nunca chegava a valer.
        $params = array(
            'tentativas' => $nextAttempts,
            'id' => $usuarioId,
        );
        $lockSql = '';
        if ($bloquear) {
            $lockSql = ', bloqueado_ate = :bloqueado_ate';
            $params['bloqueado_ate'] = date('Y-m-d H:i:s', time() + (max(1, (int) $lockMinutes) * 60));
        }

        $stmt = Database::connection()->prepare(
            'UPDATE usuarios
             SET tentativas_login = :tentativas' . $lockSql . ',
                 updated_at = NOW()
             WHERE id = :id'
        );

        $stmt->execute($params);

        return $nextAttempts;
    }

    public function professores()
    {
        $stmt = Database::connection()->query(
            'SELECT DISTINCT u.id, u.nome, u.email, u.cpf
             FROM usuarios u
             INNER JOIN usuario_perfis up ON up.usuario_id = u.id
             INNER JOIN perfis p ON p.id = up.perfil_id
             WHERE u.deleted_at IS NULL
               AND p.deleted_at IS NULL
               AND p.slug = "professor"
             ORDER BY u.nome ASC'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function contarParaPresente(array $filters = array())
    {
        $params = array();
        $where = $this->buildPresenteWhere($filters, $params);

        $sql = 'SELECT COUNT(DISTINCT u.id) AS total
                FROM usuarios u
                WHERE ' . $where;

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? (int) $row['total'] : 0;
    }

    public function listarParaPresente(array $filters = array(), $limit = 25, $offset = 0)
    {
        $limit = max(1, (int) $limit);
        $offset = max(0, (int) $offset);
        $params = array();
        $where = $this->buildPresenteWhere($filters, $params);
        $hasCidadeEstado = $this->supportsCidadeEstadoColumns();

        $cidadeSql = $hasCidadeEstado ? 'u.cidade AS cidade,' : 'NULL AS cidade,';
        $estadoSql = $hasCidadeEstado ? 'u.estado AS estado,' : 'NULL AS estado,';

        $sql = 'SELECT u.id,
                       u.nome,
                       u.email,
                       u.cpf,
                       ' . $cidadeSql . '
                       ' . $estadoSql . '
                       u.status,
                       u.created_at,
                       COALESCE(GROUP_CONCAT(DISTINCT p.nome ORDER BY p.nome SEPARATOR ", "), "") AS perfis,
                       COALESCE(GROUP_CONCAT(DISTINCT ce.nome ORDER BY ce.nome SEPARATOR ", "), "") AS cursos_comprados,
                       COUNT(DISTINCT CASE WHEN ped.id IS NOT NULL THEN ce.id END) AS total_cursos_comprados,
                       COUNT(DISTINCT ped.id) AS total_pedidos_comprados
                FROM usuarios u
                LEFT JOIN usuario_perfis up ON up.usuario_id = u.id
                LEFT JOIN perfis p ON p.id = up.perfil_id AND p.deleted_at IS NULL
                LEFT JOIN pedidos ped
                    ON (ped.comprador_usuario_id = u.id OR ped.pagador_usuario_id = u.id)
                   AND ped.deleted_at IS NULL
                   AND ped.status IN ("aprovado", "pago")
                   AND COALESCE(ped.is_presente, 0) = 0
                LEFT JOIN pedido_itens pi
                    ON pi.pedido_id = ped.id
                   AND pi.deleted_at IS NULL
                LEFT JOIN cursos_eventos ce
                    ON ce.id = pi.curso_evento_id
                   AND ce.deleted_at IS NULL
                WHERE ' . $where . '
                GROUP BY u.id
                ORDER BY u.nome ASC, u.id ASC
                LIMIT ' . $limit . ' OFFSET ' . $offset;

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function idsParaPresente(array $filters = array())
    {
        $params = array();
        $where = $this->buildPresenteWhere($filters, $params);

        $sql = 'SELECT DISTINCT u.id
                FROM usuarios u
                WHERE ' . $where . '
                ORDER BY u.id ASC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return array_map('intval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id'));
    }

    private function buildPresenteWhere(array $filters, array &$params)
    {
        $where = array('u.deleted_at IS NULL', 'u.status = "ativo"');

        $nome = isset($filters['nome']) ? trim((string) $filters['nome']) : '';
        if ($nome !== '') {
            $where[] = '(u.nome LIKE :nome OR u.email LIKE :nome OR u.cpf LIKE :nome)';
            $params['nome'] = '%' . $nome . '%';
        }

        $cidade = isset($filters['cidade']) ? trim((string) $filters['cidade']) : '';
        if ($cidade !== '' && $this->supportsCidadeEstadoColumns()) {
            $where[] = 'u.cidade LIKE :cidade';
            $params['cidade'] = '%' . $cidade . '%';
        }

        $perfil = isset($filters['perfil']) ? trim((string) $filters['perfil']) : '';
        if ($perfil !== '') {
            $where[] = 'EXISTS (
                SELECT 1
                FROM usuario_perfis upx
                INNER JOIN perfis px ON px.id = upx.perfil_id AND px.deleted_at IS NULL
                WHERE upx.usuario_id = u.id
                  AND px.slug = :perfil
            )';
            $params['perfil'] = $perfil;
        }

        $dataInicio = isset($filters['data_inicio']) ? trim((string) $filters['data_inicio']) : '';
        if ($dataInicio !== '') {
            $where[] = 'DATE(u.created_at) >= :data_inicio';
            $params['data_inicio'] = $dataInicio;
        }

        $dataFim = isset($filters['data_fim']) ? trim((string) $filters['data_fim']) : '';
        if ($dataFim !== '') {
            $where[] = 'DATE(u.created_at) <= :data_fim';
            $params['data_fim'] = $dataFim;
        }

        $cursoEventoId = isset($filters['curso_evento_id']) ? (int) $filters['curso_evento_id'] : 0;
        $comprasTipo = isset($filters['compras_tipo']) ? trim((string) $filters['compras_tipo']) : '';

        if ($comprasTipo === 'nenhuma') {
            $where[] = 'NOT EXISTS (
                SELECT 1
                FROM pedidos ped_n
                INNER JOIN pedido_itens pi_n ON pi_n.pedido_id = ped_n.id AND pi_n.deleted_at IS NULL
                WHERE ped_n.deleted_at IS NULL
                  AND (ped_n.comprador_usuario_id = u.id OR ped_n.pagador_usuario_id = u.id)
                  AND ped_n.status IN ("aprovado", "pago")
                  AND COALESCE(ped_n.is_presente, 0) = 0
            )';
        } elseif ($cursoEventoId > 0) {
            $where[] = 'EXISTS (
                SELECT 1
                FROM pedidos ped_c
                INNER JOIN pedido_itens pi_c ON pi_c.pedido_id = ped_c.id AND pi_c.deleted_at IS NULL
                WHERE ped_c.deleted_at IS NULL
                  AND (ped_c.comprador_usuario_id = u.id OR ped_c.pagador_usuario_id = u.id)
                  AND ped_c.status IN ("aprovado", "pago")
                  AND COALESCE(ped_c.is_presente, 0) = 0
                  AND pi_c.curso_evento_id = :curso_evento_id
            )';
            $params['curso_evento_id'] = $cursoEventoId;
        } elseif ($comprasTipo === 'qualquer') {
            $where[] = 'EXISTS (
                SELECT 1
                FROM pedidos ped_q
                INNER JOIN pedido_itens pi_q ON pi_q.pedido_id = ped_q.id AND pi_q.deleted_at IS NULL
                WHERE ped_q.deleted_at IS NULL
                  AND (ped_q.comprador_usuario_id = u.id OR ped_q.pagador_usuario_id = u.id)
                  AND ped_q.status IN ("aprovado", "pago")
                  AND COALESCE(ped_q.is_presente, 0) = 0
            )';
        }

        return implode(' AND ', $where);
    }
}
