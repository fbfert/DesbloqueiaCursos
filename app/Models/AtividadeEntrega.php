<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class AtividadeEntrega
{
    public function findById($id)
    {
        $stmt = Database::connection()->prepare('SELECT * FROM atividades_entregas WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(array('id' => $id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByAtividadeUsuario($atividadeId, $usuarioId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM atividades_entregas
             WHERE atividade_id = :atividade_id
               AND usuario_id = :usuario_id
               AND deleted_at IS NULL
             LIMIT 1'
        );

        $stmt->execute(array(
            'atividade_id' => $atividadeId,
            'usuario_id' => $usuarioId,
        ));

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function listForAtividade($atividadeId, $status = null)
    {
        $sql = 'SELECT ae.*,
                       atv.prazo AS atividade_prazo,
                       atv.titulo AS atividade_titulo,
                       u.nome AS usuario_nome,
                       u.email AS usuario_email,
                       u.cpf AS usuario_cpf,
                       i.status AS inscricao_status,
                       i.percentual_progresso AS inscricao_percentual,
                       p.status AS pedido_status,
                       p.codigo AS pedido_codigo
                FROM atividades_entregas ae
                INNER JOIN usuarios u ON u.id = ae.usuario_id
                INNER JOIN atividades atv ON atv.id = ae.atividade_id AND atv.deleted_at IS NULL
                LEFT JOIN inscricoes i ON i.usuario_id = ae.usuario_id
                   AND i.curso_evento_id = ae.curso_evento_id
                   AND ((i.turma_id IS NULL AND ae.turma_id IS NULL) OR i.turma_id = ae.turma_id)
                   AND i.deleted_at IS NULL
                LEFT JOIN pedidos p ON p.id = i.pedido_id
                WHERE ae.atividade_id = :atividade_id
                  AND ae.deleted_at IS NULL';

        $statusSql = '';
        $params = array('atividade_id' => $atividadeId);
        if ($status !== null && $status !== '') {
            if (is_array($status)) {
                $placeholders = array();
                foreach (array_values($status) as $indice => $statusItem) {
                    $placeholder = ':status_' . $indice;
                    $placeholders[] = $placeholder;
                    $params['status_' . $indice] = $statusItem;
                }
                if (!empty($placeholders)) {
                    $statusSql = ' AND ae.status IN (' . implode(', ', $placeholders) . ')';
                }
            } else {
                $statusSql = ' AND ae.status = :status';
                $params['status'] = $status;
            }
        }

        $sql .= $statusSql;
        $sql .= ' ORDER BY FIELD(ae.status, "corrigida", "devolvida", "reenviada", "atrasada", "enviada") ASC, ae.updated_at DESC, ae.id DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listNaoEnviadasForAtividade($atividadeId)
    {
        $sql = 'SELECT
                        NULL AS id,
                        atv.id AS atividade_id,
                        atv.curso_evento_id,
                        atv.turma_id,
                        atv.modulo_id,
                        atv.aula_id,
                        i.usuario_id,
                        NULL AS resposta_texto,
                        NULL AS arquivo_nome_original,
                        NULL AS arquivo_nome_fisico,
                        NULL AS arquivo_caminho,
                        NULL AS arquivo_mime,
                        NULL AS arquivo_tamanho,
                        "nao_enviada" AS status,
                        NULL AS nota,
                        NULL AS feedback,
                        NULL AS corrigido_por,
                        NULL AS entregue_em,
                        NULL AS corrigido_em,
                        atv.prazo AS atividade_prazo,
                        atv.titulo AS atividade_titulo,
                        u.nome AS usuario_nome,
                        u.email AS usuario_email,
                        u.cpf AS usuario_cpf,
                        i.status AS inscricao_status,
                        i.percentual_progresso AS inscricao_percentual,
                        p.status AS pedido_status,
                        p.codigo AS pedido_codigo
                 FROM atividades atv
                 INNER JOIN inscricoes i ON i.curso_evento_id = atv.curso_evento_id
                    AND ((i.turma_id IS NULL AND atv.turma_id IS NULL) OR i.turma_id = atv.turma_id)
                    AND i.deleted_at IS NULL
                 INNER JOIN usuarios u ON u.id = i.usuario_id
                 INNER JOIN pedidos p ON p.id = i.pedido_id
                 LEFT JOIN comprovantes_pix cp ON cp.pedido_id = i.pedido_id AND cp.is_atual = 1 AND cp.deleted_at IS NULL
                 LEFT JOIN atividades_entregas ae ON ae.atividade_id = atv.id
                    AND ae.usuario_id = i.usuario_id
                    AND ae.deleted_at IS NULL
                 WHERE atv.id = :atividade_id
                   AND atv.deleted_at IS NULL
                   AND atv.status = "publicado"
                   AND i.status IN ("ativa", "em_andamento", "concluida", "concluida_sem_certificado", "certificado_emitido")
                   AND (p.status IN ("aprovado", "pago") OR cp.status = "aprovado")
                   AND ae.id IS NULL
                 ORDER BY u.nome ASC, u.id ASC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(array('atividade_id' => $atividadeId));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO atividades_entregas
             (atividade_id, curso_evento_id, turma_id, modulo_id, aula_id, usuario_id, resposta_texto, arquivo_nome_original, arquivo_nome_fisico, arquivo_caminho, arquivo_mime, arquivo_tamanho, status, nota, feedback, corrigido_por, entregue_em, corrigido_em, created_at, updated_at, deleted_at)
             VALUES
             (:atividade_id, :curso_evento_id, :turma_id, :modulo_id, :aula_id, :usuario_id, :resposta_texto, :arquivo_nome_original, :arquivo_nome_fisico, :arquivo_caminho, :arquivo_mime, :arquivo_tamanho, :status, :nota, :feedback, :corrigido_por, :entregue_em, :corrigido_em, NOW(), NOW(), NULL)'
        );

        $stmt->execute(array(
            'atividade_id' => $data['atividade_id'],
            'curso_evento_id' => $data['curso_evento_id'],
            'turma_id' => isset($data['turma_id']) ? $data['turma_id'] : null,
            'modulo_id' => $data['modulo_id'],
            'aula_id' => $data['aula_id'],
            'usuario_id' => $data['usuario_id'],
            'resposta_texto' => isset($data['resposta_texto']) ? $data['resposta_texto'] : null,
            'arquivo_nome_original' => isset($data['arquivo_nome_original']) ? $data['arquivo_nome_original'] : null,
            'arquivo_nome_fisico' => isset($data['arquivo_nome_fisico']) ? $data['arquivo_nome_fisico'] : null,
            'arquivo_caminho' => isset($data['arquivo_caminho']) ? $data['arquivo_caminho'] : null,
            'arquivo_mime' => isset($data['arquivo_mime']) ? $data['arquivo_mime'] : null,
            'arquivo_tamanho' => isset($data['arquivo_tamanho']) ? $data['arquivo_tamanho'] : null,
            'status' => isset($data['status']) ? $data['status'] : 'enviada',
            'nota' => array_key_exists('nota', $data) ? $data['nota'] : null,
            'feedback' => isset($data['feedback']) ? $data['feedback'] : null,
            'corrigido_por' => isset($data['corrigido_por']) ? $data['corrigido_por'] : null,
            'entregue_em' => isset($data['entregue_em']) ? $data['entregue_em'] : null,
            'corrigido_em' => isset($data['corrigido_em']) ? $data['corrigido_em'] : null,
        ));

        return (int) Database::connection()->lastInsertId();
    }

    public function update(array $data, $id)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE atividades_entregas
             SET atividade_id = :atividade_id,
                 curso_evento_id = :curso_evento_id,
                 turma_id = :turma_id,
                 modulo_id = :modulo_id,
                 aula_id = :aula_id,
                 usuario_id = :usuario_id,
                 resposta_texto = :resposta_texto,
                 arquivo_nome_original = :arquivo_nome_original,
                 arquivo_nome_fisico = :arquivo_nome_fisico,
                 arquivo_caminho = :arquivo_caminho,
                 arquivo_mime = :arquivo_mime,
                 arquivo_tamanho = :arquivo_tamanho,
                 status = :status,
                 nota = :nota,
                 feedback = :feedback,
                 corrigido_por = :corrigido_por,
                 entregue_em = :entregue_em,
                 corrigido_em = :corrigido_em,
                 updated_at = NOW()
             WHERE id = :id'
        );

        $stmt->execute(array(
            'atividade_id' => $data['atividade_id'],
            'curso_evento_id' => $data['curso_evento_id'],
            'turma_id' => isset($data['turma_id']) ? $data['turma_id'] : null,
            'modulo_id' => $data['modulo_id'],
            'aula_id' => $data['aula_id'],
            'usuario_id' => $data['usuario_id'],
            'resposta_texto' => isset($data['resposta_texto']) ? $data['resposta_texto'] : null,
            'arquivo_nome_original' => isset($data['arquivo_nome_original']) ? $data['arquivo_nome_original'] : null,
            'arquivo_nome_fisico' => isset($data['arquivo_nome_fisico']) ? $data['arquivo_nome_fisico'] : null,
            'arquivo_caminho' => isset($data['arquivo_caminho']) ? $data['arquivo_caminho'] : null,
            'arquivo_mime' => isset($data['arquivo_mime']) ? $data['arquivo_mime'] : null,
            'arquivo_tamanho' => isset($data['arquivo_tamanho']) ? $data['arquivo_tamanho'] : null,
            'status' => isset($data['status']) ? $data['status'] : 'enviada',
            'nota' => array_key_exists('nota', $data) ? $data['nota'] : null,
            'feedback' => isset($data['feedback']) ? $data['feedback'] : null,
            'corrigido_por' => isset($data['corrigido_por']) ? $data['corrigido_por'] : null,
            'entregue_em' => isset($data['entregue_em']) ? $data['entregue_em'] : null,
            'corrigido_em' => isset($data['corrigido_em']) ? $data['corrigido_em'] : null,
            'id' => $id,
        ));
    }

    public function softDelete($id)
    {
        $stmt = Database::connection()->prepare('UPDATE atividades_entregas SET deleted_at = NOW(), updated_at = NOW() WHERE id = :id');
        $stmt->execute(array('id' => $id));
    }
}
