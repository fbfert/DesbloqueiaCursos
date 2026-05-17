<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class PresenteCampanha
{
    public function findById($id)
    {
        $stmt = Database::connection()->prepare(
            'SELECT pc.*,
                    ce.nome AS curso_nome,
                    ce.slug AS curso_slug,
                    t.nome AS turma_nome,
                    u.nome AS criado_por_nome,
                    uu.nome AS atualizado_por_nome,
                    COALESCE(SUM(CASE WHEN pb.status = "ativo" THEN 1 ELSE 0 END), 0) AS total_ativos,
                    COALESCE(SUM(CASE WHEN pb.status = "cancelado" THEN 1 ELSE 0 END), 0) AS total_cancelados,
                    COALESCE(SUM(CASE WHEN pb.status = "expirado" THEN 1 ELSE 0 END), 0) AS total_expirados,
                    COUNT(pb.id) AS total_beneficiarios
             FROM presentes_campanhas pc
             INNER JOIN cursos_eventos ce ON ce.id = pc.curso_evento_id
             LEFT JOIN turmas t ON t.id = pc.turma_id
             LEFT JOIN usuarios u ON u.id = pc.criado_por_usuario_id
             LEFT JOIN usuarios uu ON uu.id = pc.atualizado_por_usuario_id
             LEFT JOIN presentes_beneficiarios pb ON pb.campanha_id = pc.id AND pb.deleted_at IS NULL
             WHERE pc.deleted_at IS NULL
               AND pc.id = :id
             GROUP BY pc.id
             LIMIT 1'
        );

        $stmt->execute(array('id' => (int) $id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function allForBackoffice(array $filters = array())
    {
        $sql = 'SELECT pc.*,
                       ce.nome AS curso_nome,
                       t.nome AS turma_nome,
                       u.nome AS criado_por_nome,
                       COALESCE(SUM(CASE WHEN pb.status = "ativo" THEN 1 ELSE 0 END), 0) AS total_ativos,
                       COALESCE(SUM(CASE WHEN pb.status = "cancelado" THEN 1 ELSE 0 END), 0) AS total_cancelados,
                       COALESCE(SUM(CASE WHEN pb.status = "expirado" THEN 1 ELSE 0 END), 0) AS total_expirados,
                       COUNT(pb.id) AS total_beneficiarios
                FROM presentes_campanhas pc
                INNER JOIN cursos_eventos ce ON ce.id = pc.curso_evento_id
                LEFT JOIN turmas t ON t.id = pc.turma_id
                LEFT JOIN usuarios u ON u.id = pc.criado_por_usuario_id
                LEFT JOIN presentes_beneficiarios pb ON pb.campanha_id = pc.id AND pb.deleted_at IS NULL
                WHERE pc.deleted_at IS NULL';

        $params = array();

        $q = isset($filters['q']) ? trim((string) $filters['q']) : '';
        if ($q !== '') {
            $sql .= ' AND (pc.titulo LIKE :q OR pc.justificativa LIKE :q OR ce.nome LIKE :q OR t.nome LIKE :q OR u.nome LIKE :q)';
            $params['q'] = '%' . $q . '%';
        }

        $curso = isset($filters['curso_evento_id']) ? (int) $filters['curso_evento_id'] : 0;
        if ($curso > 0) {
            $sql .= ' AND pc.curso_evento_id = :curso_evento_id';
            $params['curso_evento_id'] = $curso;
        }

        $turma = isset($filters['turma_id']) ? (int) $filters['turma_id'] : 0;
        if ($turma > 0) {
            $sql .= ' AND pc.turma_id = :turma_id';
            $params['turma_id'] = $turma;
        }

        $status = isset($filters['status']) ? trim((string) $filters['status']) : '';
        if ($status !== '') {
            $sql .= ' AND pc.status = :status';
            $params['status'] = $status;
        }

        $criador = isset($filters['criado_por_usuario_id']) ? (int) $filters['criado_por_usuario_id'] : 0;
        if ($criador > 0) {
            $sql .= ' AND pc.criado_por_usuario_id = :criado_por_usuario_id';
            $params['criado_por_usuario_id'] = $criador;
        }

        $de = isset($filters['de']) ? trim((string) $filters['de']) : '';
        if ($de !== '') {
            $sql .= ' AND DATE(pc.criado_em) >= :de';
            $params['de'] = $de;
        }

        $ate = isset($filters['ate']) ? trim((string) $filters['ate']) : '';
        if ($ate !== '') {
            $sql .= ' AND DATE(pc.criado_em) <= :ate';
            $params['ate'] = $ate;
        }

        $beneficiario = isset($filters['beneficiario']) ? trim((string) $filters['beneficiario']) : '';
        if ($beneficiario !== '') {
            $sql .= ' AND EXISTS (
                        SELECT 1
                        FROM presentes_beneficiarios pbx
                        INNER JOIN usuarios ux ON ux.id = pbx.usuario_id
                        WHERE pbx.campanha_id = pc.id
                          AND pbx.deleted_at IS NULL
                          AND (ux.nome LIKE :beneficiario OR ux.email LIKE :beneficiario OR ux.cpf LIKE :beneficiario)
                    )';
            $params['beneficiario'] = '%' . $beneficiario . '%';
        }

        $sql .= ' GROUP BY pc.id
                  ORDER BY pc.criado_em DESC, pc.id DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO presentes_campanhas
             (titulo, justificativa, curso_evento_id, turma_id, acesso_tipo, acesso_dias, acesso_expira_em,
              email_modelo_evento, email_assunto, email_corpo, status, criado_por_usuario_id, atualizado_por_usuario_id,
              criado_em, atualizado_em, deleted_at)
             VALUES
             (:titulo, :justificativa, :curso_evento_id, :turma_id, :acesso_tipo, :acesso_dias, :acesso_expira_em,
              :email_modelo_evento, :email_assunto, :email_corpo, :status, :criado_por_usuario_id, :atualizado_por_usuario_id,
              NOW(), NOW(), NULL)'
        );

        $stmt->execute(array(
            'titulo' => $data['titulo'],
            'justificativa' => $data['justificativa'],
            'curso_evento_id' => $data['curso_evento_id'],
            'turma_id' => isset($data['turma_id']) ? $data['turma_id'] : null,
            'acesso_tipo' => isset($data['acesso_tipo']) ? $data['acesso_tipo'] : 'sem_prazo',
            'acesso_dias' => isset($data['acesso_dias']) ? $data['acesso_dias'] : null,
            'acesso_expira_em' => isset($data['acesso_expira_em']) ? $data['acesso_expira_em'] : null,
            'email_modelo_evento' => isset($data['email_modelo_evento']) ? $data['email_modelo_evento'] : null,
            'email_assunto' => $data['email_assunto'],
            'email_corpo' => $data['email_corpo'],
            'status' => isset($data['status']) ? $data['status'] : 'ativo',
            'criado_por_usuario_id' => isset($data['criado_por_usuario_id']) ? $data['criado_por_usuario_id'] : null,
            'atualizado_por_usuario_id' => isset($data['atualizado_por_usuario_id']) ? $data['atualizado_por_usuario_id'] : null,
        ));

        return (int) Database::connection()->lastInsertId();
    }

    public function updateStatus($id, $status, $usuarioId = null)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE presentes_campanhas
             SET status = :status,
                 atualizado_por_usuario_id = :atualizado_por_usuario_id,
                 atualizado_em = NOW()
             WHERE id = :id
               AND deleted_at IS NULL'
        );

        $stmt->execute(array(
            'status' => $status,
            'atualizado_por_usuario_id' => $usuarioId,
            'id' => (int) $id,
        ));
    }

    public function updateEmailContent($id, $assunto, $corpo, $evento = null, $usuarioId = null)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE presentes_campanhas
             SET email_assunto = :email_assunto,
                 email_corpo = :email_corpo,
                 email_modelo_evento = :email_modelo_evento,
                 atualizado_por_usuario_id = :atualizado_por_usuario_id,
                 atualizado_em = NOW()
             WHERE id = :id
               AND deleted_at IS NULL'
        );

        $stmt->execute(array(
            'email_assunto' => $assunto,
            'email_corpo' => $corpo,
            'email_modelo_evento' => $evento,
            'atualizado_por_usuario_id' => $usuarioId,
            'id' => (int) $id,
        ));
    }

    public function createBeneficiario(array $data)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO presentes_beneficiarios
             (campanha_id, usuario_id, pedido_id, inscricao_id, acesso_expira_em, status, cancelado_por_usuario_id,
              cancelado_em, cancelamento_tipo, cancelamento_justificativa, criado_em, atualizado_em, deleted_at)
             VALUES
             (:campanha_id, :usuario_id, :pedido_id, :inscricao_id, :acesso_expira_em, :status, :cancelado_por_usuario_id,
              :cancelado_em, :cancelamento_tipo, :cancelamento_justificativa, NOW(), NOW(), NULL)'
        );

        $stmt->execute(array(
            'campanha_id' => $data['campanha_id'],
            'usuario_id' => $data['usuario_id'],
            'pedido_id' => $data['pedido_id'],
            'inscricao_id' => $data['inscricao_id'],
            'acesso_expira_em' => isset($data['acesso_expira_em']) ? $data['acesso_expira_em'] : null,
            'status' => isset($data['status']) ? $data['status'] : 'ativo',
            'cancelado_por_usuario_id' => isset($data['cancelado_por_usuario_id']) ? $data['cancelado_por_usuario_id'] : null,
            'cancelado_em' => isset($data['cancelado_em']) ? $data['cancelado_em'] : null,
            'cancelamento_tipo' => isset($data['cancelamento_tipo']) ? $data['cancelamento_tipo'] : null,
            'cancelamento_justificativa' => isset($data['cancelamento_justificativa']) ? $data['cancelamento_justificativa'] : null,
        ));

        return (int) Database::connection()->lastInsertId();
    }

    public function beneficiariosForCampanha($campanhaId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT pb.*,
                    u.nome AS usuario_nome,
                    u.email AS usuario_email,
                    u.cidade AS usuario_cidade,
                    COALESCE(GROUP_CONCAT(DISTINCT pp.nome ORDER BY pp.nome SEPARATOR ", "), "") AS usuario_perfil,
                    ped.codigo AS pedido_codigo,
                    ped.status AS pedido_status,
                    ped.total AS pedido_total,
                    i.status AS inscricao_status,
                    ce.nome AS curso_nome,
                    t.nome AS turma_nome
             FROM presentes_beneficiarios pb
             INNER JOIN usuarios u ON u.id = pb.usuario_id
             LEFT JOIN usuario_perfis up ON up.usuario_id = u.id
             LEFT JOIN perfis pp ON pp.id = up.perfil_id AND pp.deleted_at IS NULL
             INNER JOIN pedidos ped ON ped.id = pb.pedido_id
             INNER JOIN inscricoes i ON i.id = pb.inscricao_id
             INNER JOIN cursos_eventos ce ON ce.id = i.curso_evento_id
             LEFT JOIN turmas t ON t.id = i.turma_id
             WHERE pb.deleted_at IS NULL
               AND pb.campanha_id = :campanha_id
             GROUP BY pb.id
             ORDER BY pb.id DESC'
        );

        $stmt->execute(array('campanha_id' => (int) $campanhaId));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function beneficiarioIdsForCampanha($campanhaId, $status = null)
    {
        $sql = 'SELECT id
                FROM presentes_beneficiarios
                WHERE deleted_at IS NULL
                  AND campanha_id = :campanha_id';
        $params = array('campanha_id' => (int) $campanhaId);

        if ($status !== null) {
            $sql .= ' AND status = :status';
            $params['status'] = (string) $status;
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return array_map('intval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id'));
    }
}
