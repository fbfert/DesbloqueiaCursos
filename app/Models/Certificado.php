<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class Certificado
{
    public function findById($id)
    {
        $stmt = Database::connection()->prepare(
            'SELECT c.*,
                    ce.nome AS curso_nome,
                    ce.slug AS curso_slug,
                    t.nome AS turma_nome,
                    p.codigo AS pedido_codigo,
                    i.status AS inscricao_status,
                    pp.nome AS participante_nome,
                    pp.cpf AS participante_cpf,
                    ct.nome AS template_nome
             FROM certificados c
             LEFT JOIN cursos_eventos ce ON ce.id = c.curso_evento_id
             LEFT JOIN inscricoes i ON i.id = c.inscricao_id
             LEFT JOIN participantes_pedido pp ON pp.id = c.participante_pedido_id
             LEFT JOIN turmas t ON t.id = c.turma_id
             LEFT JOIN pedidos p ON p.id = c.pedido_id
             LEFT JOIN certificados_templates ct ON ct.id = c.template_id
             WHERE c.id = :id
               AND c.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(array('id' => $id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findByCodigo($codigo)
    {
        $stmt = Database::connection()->prepare(
            'SELECT c.*,
                    ce.nome AS curso_nome,
                    ce.slug AS curso_slug,
                    t.nome AS turma_nome,
                    p.codigo AS pedido_codigo,
                    i.status AS inscricao_status,
                    pp.nome AS participante_nome,
                    pp.cpf AS participante_cpf,
                    ct.nome AS template_nome
             FROM certificados c
             LEFT JOIN cursos_eventos ce ON ce.id = c.curso_evento_id
             LEFT JOIN inscricoes i ON i.id = c.inscricao_id
             LEFT JOIN participantes_pedido pp ON pp.id = c.participante_pedido_id
             LEFT JOIN turmas t ON t.id = c.turma_id
             LEFT JOIN pedidos p ON p.id = c.pedido_id
             LEFT JOIN certificados_templates ct ON ct.id = c.template_id
             WHERE c.codigo = :codigo
               AND c.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(array('codigo' => $codigo));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findByInscricao($inscricaoId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM certificados
             WHERE inscricao_id = :inscricao_id
               AND deleted_at IS NULL
             ORDER BY id DESC
             LIMIT 1'
        );
        $stmt->execute(array('inscricao_id' => $inscricaoId));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function listAdmin()
    {
        $stmt = Database::connection()->query(
            'SELECT c.*,
                    ce.nome AS curso_nome,
                    t.nome AS turma_nome,
                    i.status AS inscricao_status,
                    pp.nome AS participante_nome,
                    pp.cpf AS participante_cpf
             FROM certificados c
             LEFT JOIN cursos_eventos ce ON ce.id = c.curso_evento_id
             LEFT JOIN inscricoes i ON i.id = c.inscricao_id
             LEFT JOIN participantes_pedido pp ON pp.id = c.participante_pedido_id
             LEFT JOIN turmas t ON t.id = c.turma_id
             WHERE c.deleted_at IS NULL
             ORDER BY c.id DESC'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listEligible()
    {
        $stmt = Database::connection()->query(
            'SELECT i.*,
                    p.codigo AS pedido_codigo,
                    p.pagador_nome,
                    p.pagador_email,
                    ce.nome AS curso_nome,
                    ce.slug AS curso_slug,
                    t.nome AS turma_nome,
                    u.nome AS aluno_nome,
                    u.email AS aluno_email,
                    pp.nome AS participante_nome,
                    pp.cpf AS participante_cpf
             FROM inscricoes i
             INNER JOIN pedidos p ON p.id = i.pedido_id
             INNER JOIN participantes_pedido pp ON pp.id = i.participante_pedido_id
             INNER JOIN cursos_eventos ce ON ce.id = i.curso_evento_id
             LEFT JOIN turmas t ON t.id = i.turma_id
             LEFT JOIN usuarios u ON u.id = i.usuario_id
             LEFT JOIN certificados c ON c.inscricao_id = i.id AND c.deleted_at IS NULL AND c.status = "emitido"
             WHERE i.deleted_at IS NULL
               AND i.apto_certificado = 1
               AND c.id IS NULL
             ORDER BY i.id DESC'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function searchManualCandidates(array $filters = array())
    {
        $limit = isset($filters['limit']) ? (int) $filters['limit'] : 100;
        if ($limit <= 0) {
            $limit = 100;
        }
        if ($limit > 200) {
            $limit = 200;
        }

        $sql = 'SELECT i.*,
                       p.codigo AS pedido_codigo,
                       p.status AS pedido_status,
                       p.pagador_nome,
                       p.pagador_email,
                       pp.nome AS participante_nome,
                       pp.cpf AS participante_cpf,
                       ce.nome AS curso_nome,
                       ce.slug AS curso_slug,
                       t.nome AS turma_nome,
                       u.nome AS aluno_nome,
                       u.email AS aluno_email,
                       c.id AS certificado_id,
                       c.codigo AS certificado_codigo,
                       c.status AS certificado_status,
                       c.emitido_em AS certificado_emitido_em
                FROM inscricoes i
                INNER JOIN pedidos p ON p.id = i.pedido_id
                INNER JOIN participantes_pedido pp ON pp.id = i.participante_pedido_id
                INNER JOIN cursos_eventos ce ON ce.id = i.curso_evento_id
                LEFT JOIN turmas t ON t.id = i.turma_id
                LEFT JOIN usuarios u ON u.id = i.usuario_id
                LEFT JOIN certificados c ON c.inscricao_id = i.id AND c.deleted_at IS NULL AND c.status = "emitido"
                WHERE i.deleted_at IS NULL
                  AND i.status <> "excluida"';
        $params = array();

        $cursoEventoId = isset($filters['curso_evento_id']) ? (int) $filters['curso_evento_id'] : 0;
        if ($cursoEventoId > 0) {
            $sql .= ' AND i.curso_evento_id = :curso_evento_id';
            $params['curso_evento_id'] = $cursoEventoId;
        }

        $turmaId = isset($filters['turma_id']) ? (int) $filters['turma_id'] : 0;
        if ($turmaId > 0) {
            $sql .= ' AND i.turma_id = :turma_id';
            $params['turma_id'] = $turmaId;
        }

        $busca = isset($filters['busca']) ? trim((string) $filters['busca']) : '';
        if ($busca !== '') {
            $sql .= ' AND (
                        CAST(i.id AS CHAR) LIKE :busca
                        OR pp.nome LIKE :busca
                        OR pp.cpf LIKE :busca
                        OR p.pagador_nome LIKE :busca
                        OR p.pagador_email LIKE :busca
                        OR ce.nome LIKE :busca
                        OR t.nome LIKE :busca
                        OR u.nome LIKE :busca
                        OR u.email LIKE :busca
                    )';
            $params['busca'] = '%' . $busca . '%';
        }

        $statusInscricao = isset($filters['status_inscricao']) ? trim((string) $filters['status_inscricao']) : '';
        $aptidao = isset($filters['aptidao']) ? trim((string) $filters['aptidao']) : 'todos';
        if ($statusInscricao !== '') {
            $sql .= ' AND i.status = :status_inscricao';
            $params['status_inscricao'] = $statusInscricao;
        } elseif ($aptidao !== 'pendentes' && empty($filters['mostrar_status_excluidos'])) {
            $sql .= ' AND i.status NOT IN ("pendente", "cancelada", "reprovada")';
        }

        $certificado = isset($filters['certificado']) ? trim((string) $filters['certificado']) : 'todos';
        if ($certificado === 'sem_certificado') {
            $sql .= ' AND c.id IS NULL';
        } elseif ($certificado === 'com_certificado') {
            $sql .= ' AND c.id IS NOT NULL';
        }

        if ($aptidao === 'aptos') {
            $sql .= ' AND i.apto_certificado = 1';
        } elseif ($aptidao === 'pendentes') {
            $sql .= ' AND i.apto_certificado = 0';
        }

        $sql .= ' ORDER BY i.id DESC
                  LIMIT ' . (int) $limit;

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO certificados
             (template_id, curso_evento_id, turma_id, pedido_id, inscricao_id, participante_pedido_id, usuario_id,
              codigo, versao, nome_participante, cpf_participante, cpf_mascarado, titulo, status, pdf_caminho,
              pdf_nome_original, emitido_por_usuario_id, emitido_em, cancelado_por_usuario_id, cancelado_em,
              revogado_por_usuario_id, revogado_em, reemissao_de_certificado_id, substituido_por_certificado_id,
              emissao_excepcional, emissao_excepcional_justificativa, created_at, updated_at, deleted_at)
             VALUES
             (:template_id, :curso_evento_id, :turma_id, :pedido_id, :inscricao_id, :participante_pedido_id, :usuario_id,
              :codigo, :versao, :nome_participante, :cpf_participante, :cpf_mascarado, :titulo, :status, :pdf_caminho,
              :pdf_nome_original, :emitido_por_usuario_id, :emitido_em, :cancelado_por_usuario_id, :cancelado_em,
              :revogado_por_usuario_id, :revogado_em, :reemissao_de_certificado_id, :substituido_por_certificado_id,
              :emissao_excepcional, :emissao_excepcional_justificativa, NOW(), NOW(), NULL)'
        );

        $stmt->execute(array(
            'template_id' => isset($data['template_id']) ? $data['template_id'] : null,
            'curso_evento_id' => $data['curso_evento_id'],
            'turma_id' => isset($data['turma_id']) ? $data['turma_id'] : null,
            'pedido_id' => isset($data['pedido_id']) ? $data['pedido_id'] : null,
            'inscricao_id' => $data['inscricao_id'],
            'participante_pedido_id' => $data['participante_pedido_id'],
            'usuario_id' => isset($data['usuario_id']) ? $data['usuario_id'] : null,
            'codigo' => $data['codigo'],
            'versao' => isset($data['versao']) ? (int) $data['versao'] : 1,
            'nome_participante' => $data['nome_participante'],
            'cpf_participante' => $data['cpf_participante'],
            'cpf_mascarado' => $data['cpf_mascarado'],
            'titulo' => $data['titulo'],
            'status' => isset($data['status']) ? $data['status'] : 'emitido',
            'pdf_caminho' => isset($data['pdf_caminho']) ? $data['pdf_caminho'] : null,
            'pdf_nome_original' => isset($data['pdf_nome_original']) ? $data['pdf_nome_original'] : null,
            'emitido_por_usuario_id' => isset($data['emitido_por_usuario_id']) ? $data['emitido_por_usuario_id'] : null,
            'emitido_em' => isset($data['emitido_em']) ? $data['emitido_em'] : date('Y-m-d H:i:s'),
            'cancelado_por_usuario_id' => isset($data['cancelado_por_usuario_id']) ? $data['cancelado_por_usuario_id'] : null,
            'cancelado_em' => isset($data['cancelado_em']) ? $data['cancelado_em'] : null,
            'revogado_por_usuario_id' => isset($data['revogado_por_usuario_id']) ? $data['revogado_por_usuario_id'] : null,
            'revogado_em' => isset($data['revogado_em']) ? $data['revogado_em'] : null,
            'reemissao_de_certificado_id' => isset($data['reemissao_de_certificado_id']) ? $data['reemissao_de_certificado_id'] : null,
            'substituido_por_certificado_id' => isset($data['substituido_por_certificado_id']) ? $data['substituido_por_certificado_id'] : null,
            'emissao_excepcional' => !empty($data['emissao_excepcional']) ? 1 : 0,
            'emissao_excepcional_justificativa' => isset($data['emissao_excepcional_justificativa']) ? $data['emissao_excepcional_justificativa'] : null,
        ));

        return (int) Database::connection()->lastInsertId();
    }

    public function update(array $data, $id)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE certificados
             SET template_id = :template_id,
                 curso_evento_id = :curso_evento_id,
                 turma_id = :turma_id,
                 pedido_id = :pedido_id,
                 inscricao_id = :inscricao_id,
                 participante_pedido_id = :participante_pedido_id,
                 usuario_id = :usuario_id,
                 codigo = :codigo,
                 versao = :versao,
                 nome_participante = :nome_participante,
                 cpf_participante = :cpf_participante,
                 cpf_mascarado = :cpf_mascarado,
                 titulo = :titulo,
                 status = :status,
                 pdf_caminho = :pdf_caminho,
                 pdf_nome_original = :pdf_nome_original,
                 emitido_por_usuario_id = :emitido_por_usuario_id,
                 emitido_em = :emitido_em,
                 cancelado_por_usuario_id = :cancelado_por_usuario_id,
                 cancelado_em = :cancelado_em,
                 revogado_por_usuario_id = :revogado_por_usuario_id,
                 revogado_em = :revogado_em,
                 reemissao_de_certificado_id = :reemissao_de_certificado_id,
                 substituido_por_certificado_id = :substituido_por_certificado_id,
                 emissao_excepcional = :emissao_excepcional,
                 emissao_excepcional_justificativa = :emissao_excepcional_justificativa,
                 updated_at = NOW()
             WHERE id = :id'
        );

        $stmt->execute(array(
            'template_id' => isset($data['template_id']) ? $data['template_id'] : null,
            'curso_evento_id' => $data['curso_evento_id'],
            'turma_id' => isset($data['turma_id']) ? $data['turma_id'] : null,
            'pedido_id' => isset($data['pedido_id']) ? $data['pedido_id'] : null,
            'inscricao_id' => $data['inscricao_id'],
            'participante_pedido_id' => $data['participante_pedido_id'],
            'usuario_id' => isset($data['usuario_id']) ? $data['usuario_id'] : null,
            'codigo' => $data['codigo'],
            'versao' => isset($data['versao']) ? (int) $data['versao'] : 1,
            'nome_participante' => $data['nome_participante'],
            'cpf_participante' => $data['cpf_participante'],
            'cpf_mascarado' => $data['cpf_mascarado'],
            'titulo' => $data['titulo'],
            'status' => isset($data['status']) ? $data['status'] : 'emitido',
            'pdf_caminho' => isset($data['pdf_caminho']) ? $data['pdf_caminho'] : null,
            'pdf_nome_original' => isset($data['pdf_nome_original']) ? $data['pdf_nome_original'] : null,
            'emitido_por_usuario_id' => isset($data['emitido_por_usuario_id']) ? $data['emitido_por_usuario_id'] : null,
            'emitido_em' => isset($data['emitido_em']) ? $data['emitido_em'] : date('Y-m-d H:i:s'),
            'cancelado_por_usuario_id' => isset($data['cancelado_por_usuario_id']) ? $data['cancelado_por_usuario_id'] : null,
            'cancelado_em' => isset($data['cancelado_em']) ? $data['cancelado_em'] : null,
            'revogado_por_usuario_id' => isset($data['revogado_por_usuario_id']) ? $data['revogado_por_usuario_id'] : null,
            'revogado_em' => isset($data['revogado_em']) ? $data['revogado_em'] : null,
            'reemissao_de_certificado_id' => isset($data['reemissao_de_certificado_id']) ? $data['reemissao_de_certificado_id'] : null,
            'substituido_por_certificado_id' => isset($data['substituido_por_certificado_id']) ? $data['substituido_por_certificado_id'] : null,
            'emissao_excepcional' => !empty($data['emissao_excepcional']) ? 1 : 0,
            'emissao_excepcional_justificativa' => isset($data['emissao_excepcional_justificativa']) ? $data['emissao_excepcional_justificativa'] : null,
            'id' => $id,
        ));
    }

    public function updateStatus($id, $status, $fields = array())
    {
        $columns = array('status = :status', 'updated_at = NOW()');
        $params = array(
            'status' => $status,
            'id' => $id,
        );

        foreach ($fields as $key => $value) {
            $columns[] = $key . ' = :' . $key;
            $params[$key] = $value;
        }

        $sql = 'UPDATE certificados SET ' . implode(', ', $columns) . ' WHERE id = :id';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
    }

    public function historyForCertificado($certificadoId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM certificados_historico
             WHERE certificado_id = :certificado_id
             ORDER BY id DESC'
        );
        $stmt->execute(array('certificado_id' => $certificadoId));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function validationLogsForCertificado($certificadoId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM certificados_validacao_logs
             WHERE certificado_id = :certificado_id
             ORDER BY id DESC'
        );
        $stmt->execute(array('certificado_id' => $certificadoId));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createHistory($certificadoId, $statusAnterior, $statusNovo, $observacao = null, $usuarioId = null)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO certificados_historico
             (certificado_id, status_anterior, status_novo, observacao, alterado_por_usuario_id, created_at)
             VALUES
             (:certificado_id, :status_anterior, :status_novo, :observacao, :alterado_por_usuario_id, NOW())'
        );
        $stmt->execute(array(
            'certificado_id' => $certificadoId,
            'status_anterior' => $statusAnterior,
            'status_novo' => $statusNovo,
            'observacao' => $observacao,
            'alterado_por_usuario_id' => $usuarioId,
        ));
    }

    public function logValidation($certificadoId, $codigo, $cpfInformado, $resultado, $ipAddress = null, $userAgent = null)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO certificados_validacao_logs
             (certificado_id, codigo, cpf_informado, resultado, ip_address, user_agent, created_at)
             VALUES
             (:certificado_id, :codigo, :cpf_informado, :resultado, :ip_address, :user_agent, NOW())'
        );
        $stmt->execute(array(
            'certificado_id' => $certificadoId,
            'codigo' => $codigo,
            'cpf_informado' => $cpfInformado,
            'resultado' => $resultado,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ));
    }
}
