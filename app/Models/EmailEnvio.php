<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class EmailEnvio
{
    public function create(array $data)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO emails_envios
             (usuario_id, entidade_tipo, entidade_id, evento, template, destinatario_email, destinatario_nome, assunto,
              contexto_json, status, tentativas, ultimo_erro, resposta_smtp, enviado_em, falhou_em, created_at, updated_at)
             VALUES
             (:usuario_id, :entidade_tipo, :entidade_id, :evento, :template, :destinatario_email, :destinatario_nome, :assunto,
              :contexto_json, :status, :tentativas, :ultimo_erro, :resposta_smtp, :enviado_em, :falhou_em, NOW(), NOW())'
        );

        $stmt->execute(array(
            'usuario_id' => isset($data['usuario_id']) ? $data['usuario_id'] : null,
            'entidade_tipo' => isset($data['entidade_tipo']) ? $data['entidade_tipo'] : null,
            'entidade_id' => isset($data['entidade_id']) ? $data['entidade_id'] : null,
            'evento' => $data['evento'],
            'template' => $data['template'],
            'destinatario_email' => $data['destinatario_email'],
            'destinatario_nome' => isset($data['destinatario_nome']) ? $data['destinatario_nome'] : null,
            'assunto' => $data['assunto'],
            'contexto_json' => isset($data['contexto_json']) ? $data['contexto_json'] : null,
            'status' => isset($data['status']) ? $data['status'] : 'pendente',
            'tentativas' => isset($data['tentativas']) ? (int) $data['tentativas'] : 0,
            'ultimo_erro' => isset($data['ultimo_erro']) ? $data['ultimo_erro'] : null,
            'resposta_smtp' => isset($data['resposta_smtp']) ? $data['resposta_smtp'] : null,
            'enviado_em' => isset($data['enviado_em']) ? $data['enviado_em'] : null,
            'falhou_em' => isset($data['falhou_em']) ? $data['falhou_em'] : null,
        ));

        return (int) Database::connection()->lastInsertId();
    }

    public function findById($id)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM emails_envios
             WHERE id = :id
             LIMIT 1'
        );

        $stmt->execute(array('id' => $id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function latest($limit = 50)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM emails_envios
             ORDER BY id DESC
             LIMIT :limit'
        );
        $limit = (int) $limit;
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function markSent($emailEnvioId, $respostaSmtp = null)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE emails_envios
             SET status = "enviado",
                 ultimo_erro = NULL,
                 resposta_smtp = :resposta_smtp,
                 enviado_em = NOW(),
                 falhou_em = NULL,
                 tentativas = tentativas + 1,
                 updated_at = NOW()
             WHERE id = :id'
        );

        $stmt->execute(array(
            'resposta_smtp' => $respostaSmtp,
            'id' => $emailEnvioId,
        ));
    }

    public function markFailed($emailEnvioId, $ultimoErro)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE emails_envios
             SET status = "falhou",
                 ultimo_erro = :ultimo_erro,
                 resposta_smtp = NULL,
                 falhou_em = NOW(),
                 tentativas = tentativas + 1,
                 updated_at = NOW()
             WHERE id = :id'
        );

        $stmt->execute(array(
            'ultimo_erro' => $ultimoErro,
            'id' => $emailEnvioId,
        ));
    }

    public function listForAdmin()
    {
        $stmt = Database::connection()->query(
            'SELECT *
             FROM emails_envios
             ORDER BY id DESC
             LIMIT 100'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countByStatus(array $statuses, $dateField = null, $start = null, $end = null)
    {
        $statuses = array_values(array_filter(array_map('strval', $statuses)));
        if (empty($statuses)) {
            return 0;
        }

        $allowedDateFields = array('created_at', 'updated_at', 'enviado_em', 'falhou_em');
        $dateField = $dateField && in_array($dateField, $allowedDateFields, true) ? $dateField : null;

        $placeholders = array();
        $params = array();
        foreach ($statuses as $idx => $status) {
            $key = 'status_' . $idx;
            $placeholders[] = ':' . $key;
            $params[$key] = $status;
        }

        $sql = 'SELECT COUNT(*) AS total
                FROM emails_envios
                WHERE status IN (' . implode(', ', $placeholders) . ')';

        if ($dateField && $start) {
            $sql .= ' AND ' . $dateField . ' >= :start';
            $params['start'] = $start;
        }

        if ($dateField && $end) {
            $sql .= ' AND ' . $dateField . ' <= :end';
            $params['end'] = $end;
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? (int) $row['total'] : 0;
    }

    public function listFiltered(array $filters = array(), $limit = 100, $offset = 0)
    {
        $limit = (int) $limit;
        if ($limit <= 0) {
            $limit = 100;
        }
        if ($limit > 200) {
            $limit = 200;
        }
        $offset = (int) $offset;
        if ($offset < 0) {
            $offset = 0;
        }

        $where = array();
        $params = array();

        $status = isset($filters['status']) ? trim((string) $filters['status']) : '';
        if (in_array($status, array('pendente', 'enviado', 'falhou'), true)) {
            $where[] = 'status = :status';
            $params['status'] = $status;
        }

        $q = isset($filters['q']) ? trim((string) $filters['q']) : '';
        if ($q !== '') {
            $where[] = '(destinatario_email LIKE :q OR assunto LIKE :q OR evento LIKE :q OR template LIKE :q)';
            $params['q'] = '%' . $q . '%';
        }

        $de = isset($filters['de']) ? trim((string) $filters['de']) : '';
        if ($de !== '') {
            $where[] = 'DATE(created_at) >= :de';
            $params['de'] = $de;
        }

        $ate = isset($filters['ate']) ? trim((string) $filters['ate']) : '';
        if ($ate !== '') {
            $where[] = 'DATE(created_at) <= :ate';
            $params['ate'] = $ate;
        }

        $sql = 'SELECT *
                FROM emails_envios';
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY id DESC
                  LIMIT :limit OFFSET :offset';

        $stmt = Database::connection()->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countFiltered(array $filters = array())
    {
        $where = array();
        $params = array();

        $status = isset($filters['status']) ? trim((string) $filters['status']) : '';
        if (in_array($status, array('pendente', 'enviado', 'falhou'), true)) {
            $where[] = 'status = :status';
            $params['status'] = $status;
        }

        $q = isset($filters['q']) ? trim((string) $filters['q']) : '';
        if ($q !== '') {
            $where[] = '(destinatario_email LIKE :q OR assunto LIKE :q OR evento LIKE :q OR template LIKE :q)';
            $params['q'] = '%' . $q . '%';
        }

        $de = isset($filters['de']) ? trim((string) $filters['de']) : '';
        if ($de !== '') {
            $where[] = 'DATE(created_at) >= :de';
            $params['de'] = $de;
        }

        $ate = isset($filters['ate']) ? trim((string) $filters['ate']) : '';
        if ($ate !== '') {
            $where[] = 'DATE(created_at) <= :ate';
            $params['ate'] = $ate;
        }

        $sql = 'SELECT COUNT(*) AS total
                FROM emails_envios';
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (int) $row['total'] : 0;
    }

    public function markRequeued($emailEnvioId)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE emails_envios
             SET status = "pendente",
                 updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute(array('id' => (int) $emailEnvioId));
    }

    public function deleteFailedById($id)
    {
        $stmt = Database::connection()->prepare(
            'DELETE FROM emails_envios
             WHERE id = :id
               AND status = "falhou"
             LIMIT 1'
        );

        $stmt->execute(array('id' => (int) $id));

        return $stmt->rowCount() > 0;
    }
}
