<?php

namespace App\Models;

use App\Core\Database;
use App\Support\AppApi\Tempo;
use PDO;

/**
 * Certificado retido aguardando o aluno informar o CPF (migração 083).
 * Status: aguardando_cpf → emitido | cancelado.
 */
class CertificadoRetido
{
    const AGUARDANDO = 'aguardando_cpf';

    /** Retenção ativa da inscrição, travada para atualização (usar dentro de transação). */
    public function findAtivaPorInscricaoParaAtualizar($inscricaoId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM certificados_retidos WHERE inscricao_id = :id AND status = :status ORDER BY id LIMIT 1 FOR UPDATE'
        );
        $stmt->execute(array('id' => (int) $inscricaoId, 'status' => self::AGUARDANDO));
        $linha = $stmt->fetch(PDO::FETCH_ASSOC);

        return $linha ?: null;
    }

    public function findById($id)
    {
        $stmt = Database::connection()->prepare('SELECT * FROM certificados_retidos WHERE id = :id LIMIT 1');
        $stmt->execute(array('id' => (int) $id));
        $linha = $stmt->fetch(PDO::FETCH_ASSOC);

        return $linha ?: null;
    }

    public function criar(array $dados)
    {
        $agora = Tempo::sql();
        $stmt = Database::connection()->prepare(
            'INSERT INTO certificados_retidos
             (inscricao_id, usuario_id, modo, opcoes, contexto, solicitado_por, status, tentativas, created_at, updated_at)
             VALUES (:inscricao_id, :usuario_id, :modo, :opcoes, :contexto, :solicitado_por, :status, 0, :created_at, :updated_at)'
        );
        $stmt->execute(array(
            'inscricao_id' => (int) $dados['inscricao_id'],
            'usuario_id' => (int) $dados['usuario_id'],
            'modo' => (string) $dados['modo'],
            'opcoes' => json_encode($dados['opcoes'], JSON_UNESCAPED_UNICODE),
            'contexto' => json_encode($dados['contexto'], JSON_UNESCAPED_UNICODE),
            'solicitado_por' => !empty($dados['solicitado_por']) ? (int) $dados['solicitado_por'] : null,
            'status' => self::AGUARDANDO,
            'created_at' => $agora,
            'updated_at' => $agora,
        ));

        return (int) Database::connection()->lastInsertId();
    }

    /** Nova solicitação para a mesma inscrição: guarda os parâmetros mais recentes. */
    public function atualizarSolicitacao($id, array $dados)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE certificados_retidos
                SET modo = :modo, opcoes = :opcoes, contexto = :contexto, solicitado_por = :solicitado_por, updated_at = :agora
              WHERE id = :id'
        );
        $stmt->execute(array(
            'modo' => (string) $dados['modo'],
            'opcoes' => json_encode($dados['opcoes'], JSON_UNESCAPED_UNICODE),
            'contexto' => json_encode($dados['contexto'], JSON_UNESCAPED_UNICODE),
            'solicitado_por' => !empty($dados['solicitado_por']) ? (int) $dados['solicitado_por'] : null,
            'agora' => Tempo::sql(),
            'id' => (int) $id,
        ));
    }

    public function ativasDoUsuario($usuarioId, $limite = 50)
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM certificados_retidos WHERE usuario_id = :id AND status = :status ORDER BY id LIMIT ' . max(1, (int) $limite)
        );
        $stmt->execute(array('id' => (int) $usuarioId, 'status' => self::AGUARDANDO));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function contarAtivasDoUsuario($usuarioId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM certificados_retidos WHERE usuario_id = :id AND status = :status'
        );
        $stmt->execute(array('id' => (int) $usuarioId, 'status' => self::AGUARDANDO));

        return (int) $stmt->fetchColumn();
    }

    public function marcarEmitido($id, $certificadoId)
    {
        $stmt = Database::connection()->prepare(
            "UPDATE certificados_retidos
                SET status = 'emitido', certificado_id = :certificado_id, ultima_falha = NULL,
                    tentativas = tentativas + 1, updated_at = :agora
              WHERE id = :id AND status = :status"
        );
        $stmt->execute(array(
            'certificado_id' => $certificadoId ? (int) $certificadoId : null,
            'agora' => Tempo::sql(),
            'id' => (int) $id,
            'status' => self::AGUARDANDO,
        ));
    }

    public function registrarFalha($id, $mensagem)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE certificados_retidos
                SET ultima_falha = :falha, tentativas = tentativas + 1, updated_at = :agora
              WHERE id = :id'
        );
        $stmt->execute(array(
            'falha' => mb_substr((string) $mensagem, 0, 500),
            'agora' => Tempo::sql(),
            'id' => (int) $id,
        ));
    }

    public function cancelar($id)
    {
        $stmt = Database::connection()->prepare(
            "UPDATE certificados_retidos SET status = 'cancelado', updated_at = :agora WHERE id = :id AND status = :status"
        );
        $stmt->execute(array('agora' => Tempo::sql(), 'id' => (int) $id, 'status' => self::AGUARDANDO));

        return $stmt->rowCount() > 0;
    }

    /** Último aviso por e-mail enviado ao aluno sobre qualquer retenção (timestamp ou null). */
    public function ultimoAvisoDoUsuario($usuarioId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT MAX(aviso_enviado_em) FROM certificados_retidos WHERE usuario_id = :id'
        );
        $stmt->execute(array('id' => (int) $usuarioId));
        $valor = $stmt->fetchColumn();

        return $valor ? Tempo::timestamp($valor) : null;
    }

    public function marcarAviso($id)
    {
        $stmt = Database::connection()->prepare('UPDATE certificados_retidos SET aviso_enviado_em = :agora WHERE id = :id');
        $stmt->execute(array('agora' => Tempo::sql(), 'id' => (int) $id));
    }

    /** Lista do admin: retenções aguardando CPF, com aluno, curso, turma e autor. */
    public function listarAguardando()
    {
        $stmt = Database::connection()->prepare(
            'SELECT r.*,
                    u.nome AS aluno_nome, u.email AS aluno_email,
                    c.nome AS curso_nome, t.nome AS turma_nome,
                    s.nome AS solicitante_nome
               FROM certificados_retidos r
               JOIN usuarios u ON u.id = r.usuario_id
               JOIN inscricoes i ON i.id = r.inscricao_id
               JOIN cursos_eventos c ON c.id = i.curso_evento_id
               LEFT JOIN turmas t ON t.id = i.turma_id
               LEFT JOIN usuarios s ON s.id = r.solicitado_por
              WHERE r.status = :status
              ORDER BY r.created_at DESC, r.id DESC'
        );
        $stmt->execute(array('status' => self::AGUARDANDO));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
