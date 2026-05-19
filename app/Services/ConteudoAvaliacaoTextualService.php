<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Models\ConteudoAvaliacaoEntrega;
use App\Models\ConteudoAvaliacaoTextual;
use App\Models\ConteudoItem;
use App\Models\ConteudoLogAluno;
use App\Models\CursoEvento;
use Exception;

class ConteudoAvaliacaoTextualService
{
    private const STATUS_VALIDOS = array('enviada', 'reenviada', 'corrigida', 'devolvida', 'aprovada', 'reprovada', 'cancelada');

    private $avaliacaoModel;
    private $entregaModel;
    private $itemModel;
    private $logModel;
    private $cursoModel;

    public function __construct()
    {
        $this->avaliacaoModel = new ConteudoAvaliacaoTextual();
        $this->entregaModel = new ConteudoAvaliacaoEntrega();
        $this->itemModel = new ConteudoItem();
        $this->logModel = new ConteudoLogAluno();
        $this->cursoModel = new CursoEvento();
    }

    public function enviarResposta($dados)
    {
        $dados = (array) $dados;

        $itemId = isset($dados['item_id']) ? (int) $dados['item_id'] : 0;
        $avaliacaoId = isset($dados['avaliacao_id']) ? (int) $dados['avaliacao_id'] : 0;
        $cursoEventoId = isset($dados['curso_evento_id']) ? (int) $dados['curso_evento_id'] : 0;
        $alunoId = isset($dados['aluno_id']) ? (int) $dados['aluno_id'] : 0;
        $inscricaoId = isset($dados['inscricao_id']) ? (int) $dados['inscricao_id'] : 0;

        if ($itemId <= 0) {
            return array('ok' => false, 'message' => 'Item inválido para avaliação.');
        }
        if ($cursoEventoId <= 0) {
            return array('ok' => false, 'message' => 'Curso inválido para avaliação.');
        }
        if ($alunoId <= 0) {
            return array('ok' => false, 'message' => 'Aluno inválido para avaliação.');
        }
        if ($inscricaoId <= 0) {
            return array('ok' => false, 'message' => 'Inscrição inválida para avaliação.');
        }

        $item = $this->itemModel->findById($itemId);
        if (!$item || (int) $item['curso_evento_id'] !== $cursoEventoId) {
            return array('ok' => false, 'message' => 'Item não encontrado para o curso informado.');
        }

        $avaliacao = $avaliacaoId > 0 ? $this->avaliacaoModel->findById($avaliacaoId) : $this->avaliacaoModel->findByItemId($itemId);
        if (!$avaliacao) {
            return array('ok' => false, 'message' => 'Avaliação textual não encontrada para este item.');
        }

        $validacao = $this->validarConfiguracaoAvaliacao($avaliacao);
        if (empty($validacao['ok'])) {
            return $validacao;
        }

        $resposta = isset($dados['resposta']) ? trim((string) $dados['resposta']) : '';
        if ($resposta === '') {
            return array('ok' => false, 'message' => 'Informe sua resposta.');
        }

        if (!$this->podeReenviar((int) $avaliacao['id'], $alunoId, $inscricaoId)) {
            return array('ok' => false, 'message' => 'Reenvio não permitido para esta avaliação.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $tentativa = isset($dados['tentativa']) ? (int) $dados['tentativa'] : 1;
            if ($tentativa <= 0) {
                $tentativa = 1;
            }

            $ultimaEntrega = $this->entregaModel->findLatestByContext((int) $avaliacao['id'], $alunoId, $inscricaoId);
            $statusEntrega = $ultimaEntrega ? 'reenviada' : 'enviada';

            $entregaId = $this->entregaModel->create(array(
                'avaliacao_id' => (int) $avaliacao['id'],
                'item_id' => $itemId,
                'curso_evento_id' => $cursoEventoId,
                'turma_id' => isset($dados['turma_id']) && $dados['turma_id'] !== '' ? (int) $dados['turma_id'] : null,
                'inscricao_id' => $inscricaoId,
                'aluno_id' => $alunoId,
                'resposta' => $resposta,
                'status' => $statusEntrega,
                'enviado_em' => date('Y-m-d H:i:s'),
                'tentativa' => $tentativa,
            ));

            $acaoLog = $statusEntrega === 'reenviada' ? 'reenviou_avaliacao' : 'enviou_avaliacao';

            $this->logModel->create(array(
                'curso_evento_id' => $cursoEventoId,
                'turma_id' => isset($dados['turma_id']) && $dados['turma_id'] !== '' ? (int) $dados['turma_id'] : null,
                'inscricao_id' => $inscricaoId,
                'aluno_id' => $alunoId,
                'modulo_id' => isset($dados['modulo_id']) && $dados['modulo_id'] !== '' ? (int) $dados['modulo_id'] : null,
                'item_id' => $itemId,
                'acao' => $acaoLog,
                'dados_json' => json_encode(array('entrega_id' => $entregaId, 'avaliacao_id' => (int) $avaliacao['id'])),
                'ip' => isset($dados['ip']) ? $dados['ip'] : null,
                'user_agent' => isset($dados['user_agent']) ? $dados['user_agent'] : null,
            ));

            // Integração futura: notificar professor/administrativo via EmailService.

            $pdo->commit();
            return array('ok' => true, 'id' => $entregaId);
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('conteudo.avaliacao_textual.enviar_falhou', array('message' => $exception->getMessage()));
            throw $exception;
        }
    }

    public function podeReenviar($avaliacaoId, $alunoId, $inscricaoId)
    {
        $avaliacaoId = (int) $avaliacaoId;
        $alunoId = (int) $alunoId;
        $inscricaoId = (int) $inscricaoId;

        if ($avaliacaoId <= 0 || $alunoId <= 0 || $inscricaoId <= 0) {
            return false;
        }

        $avaliacao = $this->avaliacaoModel->findById($avaliacaoId);
        if (!$avaliacao) {
            return false;
        }

        $ultimaEntrega = $this->entregaModel->findLatestByContext($avaliacaoId, $alunoId, $inscricaoId);
        if (!$ultimaEntrega) {
            return true;
        }

        $agora = time();
        $prazo = !empty($avaliacao['prazo']) ? strtotime((string) $avaliacao['prazo']) : false;

        $prazoLiberado = !empty($ultimaEntrega['prazo_liberado_ate']) ? strtotime((string) $ultimaEntrega['prazo_liberado_ate']) : false;
        $liberacaoAtiva = $prazoLiberado !== false && $agora <= $prazoLiberado;

        if (empty($avaliacao['permite_reenvio'])) {
            return $liberacaoAtiva;
        }

        $livreAtePrazo = !empty($avaliacao['reenvio_livre_ate_prazo']);

        if ($prazo === false) {
            return true;
        }

        if ($agora <= $prazo) {
            return $livreAtePrazo ? true : $liberacaoAtiva;
        }

        return $liberacaoAtiva;
    }

    public function corrigirEntrega($entregaId, $dados)
    {
        $entregaId = (int) $entregaId;
        if ($entregaId <= 0) {
            return array('ok' => false, 'message' => 'Entrega inválida.');
        }

        $entrega = $this->entregaModel->findById($entregaId);
        if (!$entrega) {
            return array('ok' => false, 'message' => 'Entrega não encontrada.');
        }

        $dados = (array) $dados;
        $nota = array_key_exists('nota', $dados) ? $dados['nota'] : null;
        $feedback = isset($dados['feedback']) ? $dados['feedback'] : null;
        $corrigidoPor = isset($dados['corrigido_por']) ? (int) $dados['corrigido_por'] : null;

        $avaliacao = $this->avaliacaoModel->findById((int) $entrega['avaliacao_id']);
        $notaMinima = $avaliacao ? (array_key_exists('nota_minima', $avaliacao) ? $avaliacao['nota_minima'] : null) : null;

        $statusInformado = isset($dados['status']) ? (string) $dados['status'] : '';
        if ($statusInformado !== '' && !in_array($statusInformado, self::STATUS_VALIDOS, true)) {
            return array('ok' => false, 'message' => 'Status inválido.');
        }

        if ($statusInformado === 'cancelada' || $statusInformado === 'devolvida') {
            $status = $statusInformado;
        } else {
            if ($notaMinima !== null && $notaMinima !== '' && $nota !== null && $nota !== '') {
                $status = ((float) $nota >= (float) $notaMinima) ? 'aprovada' : 'reprovada';
            } else {
                $status = 'corrigida';
            }
        }

        $atualizar = array(
            'status' => $status,
            'nota' => $nota,
            'feedback' => $feedback,
            'corrigido_por' => $corrigidoPor,
            'corrigido_em' => date('Y-m-d H:i:s'),
        );

        $ok = $this->entregaModel->update($atualizar, $entregaId);
        if ($ok) {
            $this->logModel->create(array(
                'curso_evento_id' => (int) $entrega['curso_evento_id'],
                'turma_id' => $entrega['turma_id'],
                'inscricao_id' => $entrega['inscricao_id'],
                'aluno_id' => (int) $entrega['aluno_id'],
                'modulo_id' => null,
                'item_id' => (int) $entrega['item_id'],
                'acao' => 'recebeu_correcao',
                'dados_json' => json_encode(array('entrega_id' => $entregaId, 'status' => $status, 'nota' => $nota)),
            ));
        }

        return array('ok' => $ok);
    }

    public function liberarNovoPrazo($entregaId, $novoPrazo, $usuarioId)
    {
        $entregaId = (int) $entregaId;
        if ($entregaId <= 0) {
            return array('ok' => false, 'message' => 'Entrega inválida.');
        }

        $entrega = $this->entregaModel->findById($entregaId);
        if (!$entrega) {
            return array('ok' => false, 'message' => 'Entrega não encontrada.');
        }

        $novoPrazo = trim((string) $novoPrazo);
        if ($novoPrazo === '') {
            return array('ok' => false, 'message' => 'Informe o novo prazo.');
        }

        $ok = $this->entregaModel->update(array(
            'prazo_liberado_ate' => $novoPrazo,
            'liberado_reenvio_por' => $usuarioId ? (int) $usuarioId : null,
            'liberado_reenvio_em' => date('Y-m-d H:i:s'),
        ), $entregaId);

        return array('ok' => $ok);
    }

    public function listarPendentesProfessor($professorId)
    {
        $cursoIds = $this->cursoIdsAcessiveisProfessor($professorId);
        $itens = $this->entregaModel->listPendentesByCursoIds($cursoIds, 50, 0);
        return array('ok' => true, 'items' => $itens);
    }

    public function contarPendentesProfessor($professorId)
    {
        $cursoIds = $this->cursoIdsAcessiveisProfessor($professorId);
        $total = $this->entregaModel->countPendentesByCursoIds($cursoIds);
        return array('ok' => true, 'total' => $total);
    }

    private function cursoIdsAcessiveisProfessor($professorId)
    {
        $professorId = (int) $professorId;
        if ($professorId <= 0) {
            return array();
        }

        $cursos = $this->cursoModel->findAccessibleByUser($professorId);
        $ids = array();
        foreach ($cursos as $curso) {
            $ids[] = (int) $curso['id'];
        }
        return array_values(array_unique($ids));
    }

    private function validarConfiguracaoAvaliacao(array $avaliacao)
    {
        $peso = isset($avaliacao['peso']) ? (float) $avaliacao['peso'] : 1.00;
        if ($peso <= 0) {
            return array('ok' => false, 'message' => 'O peso da avaliação deve ser maior que zero.');
        }

        $notaMaxima = array_key_exists('nota_maxima', $avaliacao) ? $avaliacao['nota_maxima'] : null;
        $notaMinima = array_key_exists('nota_minima', $avaliacao) ? $avaliacao['nota_minima'] : null;
        if ($notaMaxima !== null && $notaMinima !== null && (float) $notaMinima > (float) $notaMaxima) {
            return array('ok' => false, 'message' => 'A nota mínima não pode ser maior que a nota máxima.');
        }

        return array('ok' => true);
    }
}
