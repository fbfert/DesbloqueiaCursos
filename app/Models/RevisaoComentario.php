<?php

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Comentarios de revisao (spec 0002-perfil-revisor).
 *
 * O alvo e polimorfico: alvo_tipo + alvo_id apontam para item de conteudo,
 * modulo, pergunta de quiz ou alternativa. Sem chave estrangeira, no mesmo
 * padrao de emails_envios (entidade_tipo / entidade_id); a integridade e
 * validada em RevisaoComentarioService.
 */
class RevisaoComentario
{
    public function findById($id)
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM revisao_comentarios WHERE id = :id AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute(array('id' => (int) $id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $dados)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO revisao_comentarios
             (curso_evento_id, alvo_tipo, alvo_id, trecho, comentario, severidade, status, autor_id, created_at, updated_at)
             VALUES
             (:curso_evento_id, :alvo_tipo, :alvo_id, :trecho, :comentario, :severidade, :status, :autor_id, NOW(), NOW())'
        );
        $stmt->execute(array(
            'curso_evento_id' => (int) $dados['curso_evento_id'],
            'alvo_tipo' => $dados['alvo_tipo'],
            'alvo_id' => (int) $dados['alvo_id'],
            'trecho' => isset($dados['trecho']) && $dados['trecho'] !== '' ? $dados['trecho'] : null,
            'comentario' => $dados['comentario'],
            'severidade' => $dados['severidade'],
            'status' => isset($dados['status']) ? $dados['status'] : 'aberto',
            'autor_id' => (int) $dados['autor_id'],
        ));
        return (int) Database::connection()->lastInsertId();
    }

    public function updateTexto($id, array $dados)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE revisao_comentarios
             SET comentario = :comentario, severidade = :severidade, trecho = :trecho, updated_at = NOW()
             WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute(array(
            'comentario' => $dados['comentario'],
            'severidade' => $dados['severidade'],
            'trecho' => isset($dados['trecho']) && $dados['trecho'] !== '' ? $dados['trecho'] : null,
            'id' => (int) $id,
        ));
        return $stmt->rowCount();
    }

    public function updateTriagem($id, $status, $resposta, $triadoPor)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE revisao_comentarios
             SET status = :status, resposta = :resposta, triado_por = :triado_por,
                 triado_em = NOW(), updated_at = NOW()
             WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute(array(
            'status' => $status,
            'resposta' => $resposta !== '' ? $resposta : null,
            'triado_por' => (int) $triadoPor,
            'id' => (int) $id,
        ));
        return $stmt->rowCount();
    }

    public function softDelete($id)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE revisao_comentarios SET deleted_at = NOW() WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute(array('id' => (int) $id));
        return $stmt->rowCount();
    }

    /**
     * Comentarios de um alvo especifico, do mais novo para o mais antigo.
     */
    public function listarPorAlvo($alvoTipo, $alvoId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT rc.*, u.nome AS autor_nome
             FROM revisao_comentarios rc
             LEFT JOIN usuarios u ON u.id = rc.autor_id
             WHERE rc.alvo_tipo = :alvo_tipo AND rc.alvo_id = :alvo_id AND rc.deleted_at IS NULL
             ORDER BY rc.created_at DESC, rc.id DESC'
        );
        $stmt->execute(array('alvo_tipo' => $alvoTipo, 'alvo_id' => (int) $alvoId));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Fila do curso, com filtros opcionais de severidade, status e autor.
     */
    public function listarPorCurso($cursoId, array $filtros = array())
    {
        $sql = 'SELECT rc.*, u.nome AS autor_nome, t.nome AS triado_por_nome
                FROM revisao_comentarios rc
                LEFT JOIN usuarios u ON u.id = rc.autor_id
                LEFT JOIN usuarios t ON t.id = rc.triado_por
                WHERE rc.curso_evento_id = :curso_evento_id AND rc.deleted_at IS NULL';
        $params = array('curso_evento_id' => (int) $cursoId);

        if (!empty($filtros['status'])) {
            $sql .= ' AND rc.status = :status';
            $params['status'] = $filtros['status'];
        }
        if (!empty($filtros['severidade'])) {
            $sql .= ' AND rc.severidade = :severidade';
            $params['severidade'] = $filtros['severidade'];
        }
        if (!empty($filtros['autor_id'])) {
            $sql .= ' AND rc.autor_id = :autor_id';
            $params['autor_id'] = (int) $filtros['autor_id'];
        }
        if (!empty($filtros['alvo_tipo'])) {
            $sql .= ' AND rc.alvo_tipo = :alvo_tipo';
            $params['alvo_tipo'] = $filtros['alvo_tipo'];
        }

        // Aberto primeiro, e dentro dele o mais grave no topo: e a ordem em que
        // a fila precisa ser tratada.
        $sql .= " ORDER BY FIELD(rc.status, 'aberto', 'aceito', 'resolvido', 'recusado'),
                           FIELD(rc.severidade, 'erro', 'impreciso', 'duvida', 'sugestao'),
                           rc.created_at ASC";

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Contagem por status e por severidade, para os contadores das telas.
     */
    public function resumoPorCurso($cursoId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT status, severidade, COUNT(*) AS total
             FROM revisao_comentarios
             WHERE curso_evento_id = :curso_evento_id AND deleted_at IS NULL
             GROUP BY status, severidade'
        );
        $stmt->execute(array('curso_evento_id' => (int) $cursoId));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Erros ainda em aberto: o numero que aparece como alerta na tela do curso.
     */
    public function contarErrosAbertos($cursoId)
    {
        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*) FROM revisao_comentarios
             WHERE curso_evento_id = :curso_evento_id
               AND severidade = 'erro' AND status = 'aberto' AND deleted_at IS NULL"
        );
        $stmt->execute(array('curso_evento_id' => (int) $cursoId));
        return (int) $stmt->fetchColumn();
    }

    /**
     * Quantos comentarios por alvo, para os contadores da arvore do curso.
     */
    public function contagemPorAlvo($cursoId, $alvoTipo)
    {
        $stmt = Database::connection()->prepare(
            'SELECT alvo_id, COUNT(*) AS total,
                    SUM(CASE WHEN status = :aberto THEN 1 ELSE 0 END) AS abertos
             FROM revisao_comentarios
             WHERE curso_evento_id = :curso_evento_id AND alvo_tipo = :alvo_tipo AND deleted_at IS NULL
             GROUP BY alvo_id'
        );
        $stmt->execute(array(
            'curso_evento_id' => (int) $cursoId,
            'alvo_tipo' => $alvoTipo,
            'aberto' => 'aberto',
        ));
        $saida = array();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $linha) {
            $saida[(int) $linha['alvo_id']] = array(
                'total' => (int) $linha['total'],
                'abertos' => (int) $linha['abertos'],
            );
        }
        return $saida;
    }
}
