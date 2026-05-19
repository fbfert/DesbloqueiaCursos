<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class ConteudoProgressoAluno
{
    public function findByContext($alunoId, $inscricaoId, $itemId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM conteudo_progresso_aluno
             WHERE aluno_id = :aluno_id
               AND inscricao_id = :inscricao_id
               AND item_id = :item_id
               AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(array(
            'aluno_id' => (int) $alunoId,
            'inscricao_id' => (int) $inscricaoId,
            'item_id' => (int) $itemId,
        ));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function upsert(array $data)
    {
        $existing = $this->findByContext((int) $data['aluno_id'], (int) $data['inscricao_id'], (int) $data['item_id']);
        if ($existing) {
            $stmt = Database::connection()->prepare(
                'UPDATE conteudo_progresso_aluno
                 SET status = :status,
                     percentual = :percentual,
                     obrigatorio = :obrigatorio,
                     primeiro_acesso_em = COALESCE(primeiro_acesso_em, :primeiro_acesso_em),
                     ultimo_acesso_em = :ultimo_acesso_em,
                     concluido_em = :concluido_em,
                     updated_at = NOW()
                 WHERE id = :id'
            );
            $stmt->execute(array(
                'status' => (string) $data['status'],
                'percentual' => isset($data['percentual']) ? $data['percentual'] : 0.00,
                'obrigatorio' => !empty($data['obrigatorio']) ? 1 : 0,
                'primeiro_acesso_em' => isset($data['primeiro_acesso_em']) ? $data['primeiro_acesso_em'] : null,
                'ultimo_acesso_em' => isset($data['ultimo_acesso_em']) ? $data['ultimo_acesso_em'] : null,
                'concluido_em' => isset($data['concluido_em']) ? $data['concluido_em'] : null,
                'id' => (int) $existing['id'],
            ));
            return (int) $existing['id'];
        }

        $stmt = Database::connection()->prepare(
            'INSERT INTO conteudo_progresso_aluno
             (curso_evento_id, turma_id, inscricao_id, aluno_id, modulo_id, item_id, status, percentual, obrigatorio,
              primeiro_acesso_em, ultimo_acesso_em, concluido_em, created_at, updated_at, deleted_at)
             VALUES
             (:curso_evento_id, :turma_id, :inscricao_id, :aluno_id, :modulo_id, :item_id, :status, :percentual, :obrigatorio,
              :primeiro_acesso_em, :ultimo_acesso_em, :concluido_em, NOW(), NOW(), NULL)'
        );
        $stmt->execute(array(
            'curso_evento_id' => (int) $data['curso_evento_id'],
            'turma_id' => isset($data['turma_id']) ? $data['turma_id'] : null,
            'inscricao_id' => (int) $data['inscricao_id'],
            'aluno_id' => (int) $data['aluno_id'],
            'modulo_id' => (int) $data['modulo_id'],
            'item_id' => (int) $data['item_id'],
            'status' => (string) $data['status'],
            'percentual' => isset($data['percentual']) ? $data['percentual'] : 0.00,
            'obrigatorio' => !empty($data['obrigatorio']) ? 1 : 0,
            'primeiro_acesso_em' => isset($data['primeiro_acesso_em']) ? $data['primeiro_acesso_em'] : null,
            'ultimo_acesso_em' => isset($data['ultimo_acesso_em']) ? $data['ultimo_acesso_em'] : null,
            'concluido_em' => isset($data['concluido_em']) ? $data['concluido_em'] : null,
        ));

        return (int) Database::connection()->lastInsertId();
    }

    public function listForInscricao($inscricaoId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM conteudo_progresso_aluno
             WHERE inscricao_id = :inscricao_id
               AND deleted_at IS NULL
             ORDER BY updated_at DESC, id DESC'
        );
        $stmt->execute(array('inscricao_id' => (int) $inscricaoId));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

