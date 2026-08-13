<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Models\ConteudoQuiz;
use App\Models\ConteudoQuizCorrecaoDiscursiva;
use App\Models\ConteudoQuizPergunta;
use App\Models\ConteudoQuizResposta;
use App\Models\ConteudoQuizTentativa;
use App\Services\Quiz\CorretorDiscursivaInterface;
use App\Services\Quiz\CorretorDiscursivaManual;
use Exception;

/**
 * Correcao das questoes discursivas.
 *
 * A nota discursiva e informativa: nao altera o percentual objetivo, a
 * aprovacao da tentativa nem a aptidao para certificado.
 *
 * O corretor e injetado pela interface CorretorDiscursivaInterface. Hoje so
 * existe o corretor manual; uma correcao automatizada por IA podera ser
 * plugada aqui no futuro sem mudar este servico.
 */
class ConteudoQuizDiscursivaService
{
    private $correcaoModel;
    private $tentativaModel;
    private $respostaModel;
    private $perguntaModel;
    private $quizModel;
    private $auditService;
    private $corretor;

    public function __construct(?CorretorDiscursivaInterface $corretor = null)
    {
        $this->correcaoModel  = new ConteudoQuizCorrecaoDiscursiva();
        $this->tentativaModel = new ConteudoQuizTentativa();
        $this->respostaModel  = new ConteudoQuizResposta();
        $this->perguntaModel  = new ConteudoQuizPergunta();
        $this->quizModel      = new ConteudoQuiz();
        $this->auditService   = new AuditService();
        $this->corretor       = $corretor ?: new CorretorDiscursivaManual();
    }

    /**
     * Fila de discursivas de um quiz (pendentes primeiro).
     */
    public function listarFila($quizId, $cursoId, array $filtros = array())
    {
        return $this->correcaoModel->listFila((int) $quizId, (int) $cursoId, $filtros);
    }

    public function contarPendentes($quizId, $cursoId = null, $turmaId = null)
    {
        return $this->correcaoModel->countPendentesPorQuiz((int) $quizId, $cursoId, $turmaId);
    }

    public function obterCorrecao($correcaoId)
    {
        $correcao = $this->correcaoModel->findById((int) $correcaoId);
        if (!$correcao) {
            return null;
        }

        $tentativa = $this->tentativaModel->findById((int) $correcao['tentativa_id']);
        $pergunta  = $this->perguntaModel->findById((int) $correcao['pergunta_id']);
        $resposta  = $this->respostaModel->findByTentativaEPergunta(
            (int) $correcao['tentativa_id'],
            (int) $correcao['pergunta_id']
        );

        return array(
            'correcao'  => $correcao,
            'tentativa' => $tentativa,
            'pergunta'  => $pergunta,
            'resposta'  => $resposta,
            'historico' => $this->correcaoModel->listHistorico((int) $correcaoId),
        );
    }

    /**
     * Registra a correcao (nota, rubrica, feedback, corretor, data) e grava o
     * historico da alteracao.
     */
    public function corrigir(array $dados)
    {
        $correcaoId = (int) ($dados['correcao_id'] ?? 0);
        $usuarioId  = isset($dados['usuario_id']) ? (int) $dados['usuario_id'] : null;

        $correcao = $this->correcaoModel->findById($correcaoId);
        if (!$correcao) {
            return array('ok' => false, 'message' => 'Correção não encontrada.');
        }

        $tentativa = $this->tentativaModel->findById((int) $correcao['tentativa_id']);
        if (!$tentativa) {
            return array('ok' => false, 'message' => 'Tentativa não encontrada.');
        }
        if ((string) $tentativa['status'] === 'em_andamento') {
            return array('ok' => false, 'message' => 'A tentativa ainda não foi enviada pelo aluno.');
        }

        $pergunta = $this->perguntaModel->findById((int) $correcao['pergunta_id']);
        $resposta = $this->respostaModel->findByTentativaEPergunta(
            (int) $correcao['tentativa_id'],
            (int) $correcao['pergunta_id']
        );

        $contexto = array(
            'pergunta_id' => (int) $correcao['pergunta_id'],
            'enunciado'   => $pergunta ? (string) $pergunta['enunciado'] : '',
            'rubrica'     => isset($dados['rubrica']) ? $dados['rubrica'] : ($pergunta['rubrica'] ?? null),
            'nota_maxima' => (float) $correcao['nota_maxima'],
            'resposta'    => $resposta ? (string) ($resposta['texto_resposta'] ?? '') : '',
            'nota'        => isset($dados['nota']) ? $dados['nota'] : null,
            'feedback'    => isset($dados['feedback']) ? $dados['feedback'] : null,
            'corretor_id' => $usuarioId,
        );

        if (!$this->corretor->suporta($contexto)) {
            return array('ok' => false, 'message' => 'Esta resposta não pode ser avaliada pelo corretor selecionado.');
        }

        $avaliacao = $this->corretor->corrigir($contexto);
        if (empty($avaliacao['ok'])) {
            return array('ok' => false, 'message' => $avaliacao['message'] ?? 'Não foi possível registrar a correção.');
        }

        $pdo = Database::connection();
        $transacaoPropia = !$pdo->inTransaction();
        if ($transacaoPropia) {
            $pdo->beginTransaction();
        }

        try {
            $agora = date('Y-m-d H:i:s');

            $this->correcaoModel->update(array(
                'resposta_id'         => $resposta ? (int) $resposta['id'] : $correcao['resposta_id'],
                'bloco_id'            => $correcao['bloco_id'],
                'status'              => 'corrigida',
                'nota'                => $avaliacao['nota'],
                'nota_maxima'         => (float) $correcao['nota_maxima'],
                'rubrica'             => $avaliacao['rubrica'],
                'feedback'            => $avaliacao['feedback'],
                'origem'              => $this->corretor->origem(),
                'corretor_id'         => $usuarioId,
                'corretor_referencia' => $this->corretor->referencia(),
                'corrigida_em'        => $agora,
            ), $correcaoId);

            $this->correcaoModel->registrarHistorico(array(
                'correcao_id'       => $correcaoId,
                'nota_anterior'     => $correcao['nota'],
                'nota_nova'         => $avaliacao['nota'],
                'status_anterior'   => (string) $correcao['status'],
                'status_novo'       => 'corrigida',
                'rubrica_anterior'  => $correcao['rubrica'],
                'rubrica_nova'      => $avaliacao['rubrica'],
                'feedback_anterior' => $correcao['feedback'],
                'feedback_novo'     => $avaliacao['feedback'],
                'origem'            => $this->corretor->origem(),
                'usuario_id'        => $usuarioId,
            ));

            $this->atualizarStatusDaTentativa((int) $correcao['tentativa_id']);

            if ($transacaoPropia) {
                $pdo->commit();
            }

            // Auditoria sem o texto da resposta nem do feedback.
            $this->auditService->record(
                'quiz.discursiva.corrigida',
                'conteudo_quiz_correcoes_discursivas',
                $correcaoId,
                array(
                    'tentativa_id' => (int) $correcao['tentativa_id'],
                    'pergunta_id'  => (int) $correcao['pergunta_id'],
                    'nota'         => $avaliacao['nota'],
                    'origem'       => $this->corretor->origem(),
                ),
                $usuarioId,
                isset($dados['ip']) ? $dados['ip'] : null,
                isset($dados['user_agent']) ? $dados['user_agent'] : null
            );

            return array('ok' => true, 'nota' => $avaliacao['nota']);
        } catch (Exception $e) {
            if ($transacaoPropia && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Logger::error('quiz.discursiva.corrigir.erro', array('correcao_id' => $correcaoId));
            return array('ok' => false, 'message' => 'Não foi possível registrar a correção.');
        }
    }

    /**
     * A tentativa fica 'corrigida' na discursiva quando nao ha mais pendencias.
     * A aprovacao objetiva permanece intocada.
     */
    private function atualizarStatusDaTentativa($tentativaId)
    {
        $correcoes = $this->correcaoModel->listForTentativa((int) $tentativaId);
        if (count($correcoes) === 0) {
            return;
        }

        foreach ($correcoes as $correcao) {
            if ((string) $correcao['status'] === 'pendente') {
                $this->tentativaModel->atualizarStatusDiscursiva((int) $tentativaId, 'pendente');
                return;
            }
        }

        $this->tentativaModel->atualizarStatusDiscursiva((int) $tentativaId, 'corrigida');
    }
}
