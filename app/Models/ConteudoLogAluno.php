<?php

namespace App\Models;

use App\Core\Database;

class ConteudoLogAluno
{
    public function create(array $data)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO conteudo_logs_aluno
             (curso_evento_id, turma_id, inscricao_id, aluno_id, modulo_id, item_id, acao, dados_json, ip, user_agent, created_at)
             VALUES
             (:curso_evento_id, :turma_id, :inscricao_id, :aluno_id, :modulo_id, :item_id, :acao, :dados_json, :ip, :user_agent, NOW())'
        );

        $stmt->execute(array(
            'curso_evento_id' => (int) $data['curso_evento_id'],
            'turma_id' => isset($data['turma_id']) ? $data['turma_id'] : null,
            'inscricao_id' => isset($data['inscricao_id']) ? $data['inscricao_id'] : null,
            'aluno_id' => (int) $data['aluno_id'],
            'modulo_id' => isset($data['modulo_id']) ? $data['modulo_id'] : null,
            'item_id' => isset($data['item_id']) ? $data['item_id'] : null,
            'acao' => (string) $data['acao'],
            'dados_json' => isset($data['dados_json']) ? $data['dados_json'] : null,
            'ip' => isset($data['ip']) ? $data['ip'] : null,
            'user_agent' => isset($data['user_agent']) ? $data['user_agent'] : null,
        ));

        return (int) Database::connection()->lastInsertId();
    }
}

