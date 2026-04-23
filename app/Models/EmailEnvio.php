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
                 resposta_smtp = :resposta_smtp,
                 enviado_em = NOW(),
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
}
