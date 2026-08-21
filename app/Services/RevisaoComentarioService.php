<?php

namespace App\Services;

use App\Models\RevisaoComentario;

/**
 * Regras dos comentarios de revisao (spec 0002-perfil-revisor).
 *
 * Este Service e o unico ponto do fluxo do revisor que grava. Ele escreve
 * exclusivamente em revisao_comentarios: nenhum metodo aqui toca em
 * conteudo_*, conteudo_quiz_* ou qualquer tabela de conteudo. E essa separacao
 * — e nao a interface — que sustenta a premissa da feature.
 */
class RevisaoComentarioService
{
    const SEVERIDADES = array('erro', 'impreciso', 'sugestao', 'duvida');
    const STATUS = array('aberto', 'aceito', 'recusado', 'resolvido');
    const STATUS_TRIAGEM = array('aceito', 'recusado', 'resolvido');

    private $comentarioModel;
    private $escopo;
    private $trash;
    private $audit;

    public function __construct(array $dependencies = array())
    {
        $this->comentarioModel = isset($dependencies['comentarioModel'])
            ? $dependencies['comentarioModel'] : new RevisaoComentario();
        $this->escopo = isset($dependencies['escopo'])
            ? $dependencies['escopo'] : new RevisorAcademicScopeService();
        $this->trash = isset($dependencies['trash'])
            ? $dependencies['trash'] : new TrashService();
        $this->audit = isset($dependencies['audit'])
            ? $dependencies['audit'] : new AuditService();
    }

    /**
     * Registra um apontamento. Nasce sempre com status "aberto": o revisor
     * nunca define o desfecho do proprio comentario.
     */
    public function criar(array $payload, $usuarioId)
    {
        $cursoId = (int) ($payload['curso_evento_id'] ?? 0);
        $alvoTipo = trim((string) ($payload['alvo_tipo'] ?? ''));
        $alvoId = (int) ($payload['alvo_id'] ?? 0);
        $comentario = trim((string) ($payload['comentario'] ?? ''));
        $severidade = trim((string) ($payload['severidade'] ?? 'sugestao'));
        $trecho = trim((string) ($payload['trecho'] ?? ''));

        $erros = array();

        $contexto = $this->escopo->validarContexto($usuarioId, $cursoId);
        if (empty($contexto['ok'])) {
            $erros['curso_evento_id'] = $contexto['message'];
        }
        if (!in_array($alvoTipo, RevisorAcademicScopeService::ALVOS, true)) {
            $erros['alvo_tipo'] = 'Tipo de alvo inválido.';
        }
        if (!in_array($severidade, self::SEVERIDADES, true)) {
            $erros['severidade'] = 'Severidade inválida.';
        }
        if ($comentario === '') {
            $erros['comentario'] = 'Escreva o apontamento.';
        }
        if (!$erros && !$this->escopo->alvoPertenceAoCurso($alvoTipo, $alvoId, $cursoId)) {
            $erros['alvo_id'] = 'O item comentado não pertence a este curso.';
        }

        if ($erros) {
            return array('ok' => false, 'errors' => $erros);
        }

        $id = $this->comentarioModel->create(array(
            'curso_evento_id' => $cursoId,
            'alvo_tipo' => $alvoTipo,
            'alvo_id' => $alvoId,
            'trecho' => $trecho,
            'comentario' => $comentario,
            'severidade' => $severidade,
            'status' => 'aberto',
            'autor_id' => (int) $usuarioId,
        ));

        $this->audit->record(
            'revisao.comentario.criado',
            'revisao_comentario',
            $id,
            array('curso_evento_id' => $cursoId, 'alvo_tipo' => $alvoTipo, 'alvo_id' => $alvoId, 'severidade' => $severidade),
            $usuarioId
        );

        return array('ok' => true, 'id' => $id);
    }

    /**
     * O revisor edita o proprio apontamento — e apenas enquanto ele estiver
     * aberto. Depois de triado, o texto e o registro de uma decisao ja tomada.
     */
    public function editar($id, array $payload, $usuarioId)
    {
        $comentario = $this->comentarioModel->findById($id);
        if (!$comentario) {
            return array('ok' => false, 'errors' => array('id' => 'Comentário não encontrado.'));
        }
        if ((int) $comentario['autor_id'] !== (int) $usuarioId) {
            return array('ok' => false, 'errors' => array('id' => 'Só o autor pode editar o próprio apontamento.'));
        }
        if ($comentario['status'] !== 'aberto') {
            return array('ok' => false, 'errors' => array('status' => 'Este apontamento já foi triado e não pode mais ser editado.'));
        }

        $texto = trim((string) ($payload['comentario'] ?? ''));
        $severidade = trim((string) ($payload['severidade'] ?? $comentario['severidade']));
        $erros = array();
        if ($texto === '') {
            $erros['comentario'] = 'Escreva o apontamento.';
        }
        if (!in_array($severidade, self::SEVERIDADES, true)) {
            $erros['severidade'] = 'Severidade inválida.';
        }
        if ($erros) {
            return array('ok' => false, 'errors' => $erros);
        }

        $this->comentarioModel->updateTexto($id, array(
            'comentario' => $texto,
            'severidade' => $severidade,
            'trecho' => trim((string) ($payload['trecho'] ?? $comentario['trecho'])),
        ));

        $this->audit->record('revisao.comentario.editado', 'revisao_comentario', $id, array(), $usuarioId);

        return array('ok' => true, 'id' => (int) $id);
    }

    /**
     * Triagem pelo gestor de conteudo. Recusar exige resposta: recusar em
     * silencio ensina o revisor a parar de apontar.
     */
    public function triar($id, $status, $resposta, $gestorId)
    {
        $comentario = $this->comentarioModel->findById($id);
        if (!$comentario) {
            return array('ok' => false, 'errors' => array('id' => 'Comentário não encontrado.'));
        }
        if (!in_array($status, self::STATUS_TRIAGEM, true)) {
            return array('ok' => false, 'errors' => array('status' => 'Situação inválida para triagem.'));
        }

        $resposta = trim((string) $resposta);
        if ($status === 'recusado' && $resposta === '') {
            return array('ok' => false, 'errors' => array('resposta' => 'Explique por que o apontamento foi recusado.'));
        }

        $this->comentarioModel->updateTriagem($id, $status, $resposta, $gestorId);

        $this->audit->record(
            'revisao.comentario.triado',
            'revisao_comentario',
            $id,
            array('de' => $comentario['status'], 'para' => $status),
            $gestorId
        );

        return array('ok' => true, 'id' => (int) $id);
    }

    /**
     * Exclusao logica, sempre por TrashService, com justificativa — como toda
     * exclusao do projeto.
     */
    public function excluir($id, $justificativa, $usuarioId, array $contexto = array())
    {
        $comentario = $this->comentarioModel->findById($id);
        if (!$comentario) {
            return array('ok' => false, 'errors' => array('id' => 'Comentário não encontrado.'));
        }

        // TrashService::record lanca InvalidArgumentException quando a
        // justificativa vem vazia. E a trava que garante que nada seja excluido
        // sem motivo registrado — por isso o softDelete so acontece depois.
        try {
            $this->trash->record(
                'revisao_comentario',
                (int) $id,
                $justificativa,
                $comentario,
                $usuarioId,
                isset($contexto['ip']) ? $contexto['ip'] : null,
                isset($contexto['user_agent']) ? $contexto['user_agent'] : null
            );
        } catch (\InvalidArgumentException $e) {
            return array('ok' => false, 'errors' => array('justificativa' => 'A justificativa da exclusão é obrigatória.'));
        }

        $this->comentarioModel->softDelete($id);

        return array('ok' => true, 'id' => (int) $id);
    }

    /**
     * Rotulos para a interface, em um lugar so.
     */
    public static function rotuloSeveridade($severidade)
    {
        $mapa = array(
            'erro' => 'Erro',
            'impreciso' => 'Impreciso',
            'sugestao' => 'Sugestão',
            'duvida' => 'Dúvida',
        );
        return isset($mapa[$severidade]) ? $mapa[$severidade] : $severidade;
    }

    public static function rotuloStatus($status)
    {
        $mapa = array(
            'aberto' => 'Em aberto',
            'aceito' => 'Aceito',
            'recusado' => 'Recusado',
            'resolvido' => 'Resolvido',
        );
        return isset($mapa[$status]) ? $mapa[$status] : $status;
    }
}
