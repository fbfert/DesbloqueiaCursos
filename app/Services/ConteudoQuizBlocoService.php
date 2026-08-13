<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Models\ConteudoQuiz;
use App\Models\ConteudoQuizBloco;
use App\Models\ConteudoQuizPergunta;
use App\Services\Quiz\QuizSorteioService;
use Exception;

/**
 * Regras dos blocos de sorteio do banco de questoes.
 */
class ConteudoQuizBlocoService
{
    const TIPOS_QUESTAO = array('multipla_escolha', 'discursiva');
    const STATUS_VALIDOS = array('ativo', 'inativo');

    private $quizModel;
    private $blocoModel;
    private $perguntaModel;
    private $sorteioService;
    private $auditService;

    public function __construct()
    {
        $this->quizModel      = new ConteudoQuiz();
        $this->blocoModel     = new ConteudoQuizBloco();
        $this->perguntaModel  = new ConteudoQuizPergunta();
        $this->sorteioService = new QuizSorteioService();
        $this->auditService   = new AuditService();
    }

    /**
     * Blocos do quiz com a contagem do banco de questoes de cada um.
     */
    public function listarComResumo($quizId)
    {
        $quizId = (int) $quizId;
        $blocos = $this->blocoModel->listForQuiz($quizId);
        $resumo = $this->perguntaModel->resumoBancoPorBloco($quizId);

        foreach ($blocos as &$bloco) {
            $blocoId  = (int) $bloco['id'];
            $contagem = isset($resumo[$blocoId]) ? $resumo[$blocoId] : array('total' => 0, 'facil' => 0, 'media' => 0, 'dificil' => 0);

            $bloco['banco']        = $contagem;
            $bloco['distribuicao'] = $this->sorteioService->normalizarDistribuicao($bloco['distribuicao_dificuldade_json']);
            $bloco['cotas']        = $bloco['distribuicao'] !== null
                ? $this->sorteioService->distribuirCotas((int) $bloco['quantidade_sortear'], $bloco['distribuicao'])
                : array();
            $bloco['suficiente']   = $contagem['total'] >= (int) $bloco['quantidade_sortear'];
        }
        unset($bloco);

        return $blocos;
    }

    public function salvar(array $dados, $usuarioId = null, $ip = null, $userAgent = null)
    {
        $quizId = (int) ($dados['quiz_id'] ?? 0);
        $id     = (int) ($dados['id'] ?? 0);

        $quiz = $this->quizModel->findById($quizId);
        if (!$quiz) {
            return array('ok' => false, 'message' => 'Quiz não encontrado.');
        }

        $codigo = strtoupper(trim((string) ($dados['codigo'] ?? '')));
        $titulo = trim((string) ($dados['titulo'] ?? ''));

        if ($codigo === '' || !preg_match('/^[A-Z0-9_\-]{2,40}$/', $codigo)) {
            return array('ok' => false, 'message' => 'Informe um código com 2 a 40 caracteres (letras, números, hífen ou sublinhado).');
        }
        if ($titulo === '') {
            return array('ok' => false, 'message' => 'Informe o título do bloco.');
        }

        $tipoQuestao = (string) ($dados['tipo_questao'] ?? 'multipla_escolha');
        if (!in_array($tipoQuestao, self::TIPOS_QUESTAO, true)) {
            return array('ok' => false, 'message' => 'Tipo de questão inválido para o bloco.');
        }

        $status = (string) ($dados['status'] ?? 'ativo');
        if (!in_array($status, self::STATUS_VALIDOS, true)) {
            $status = 'ativo';
        }

        $quantidade = (int) ($dados['quantidade_sortear'] ?? 0);
        if ($quantidade < 0) {
            return array('ok' => false, 'message' => 'A quantidade a sortear não pode ser negativa.');
        }

        $distribuicao = $this->normalizarDistribuicaoEntrada($dados);
        if (isset($distribuicao['error'])) {
            return array('ok' => false, 'message' => $distribuicao['error']);
        }

        $existentePorCodigo = $this->blocoModel->findByCodigo($quizId, $codigo);
        if ($existentePorCodigo && (int) $existentePorCodigo['id'] !== $id) {
            return array('ok' => false, 'message' => 'Já existe um bloco com o código "' . $codigo . '" neste simulado.');
        }

        $campos = array(
            'quiz_id'                       => $quizId,
            'codigo'                        => $codigo,
            'titulo'                        => $titulo,
            'descricao'                     => isset($dados['descricao']) ? trim((string) $dados['descricao']) : null,
            'tipo_questao'                  => $tipoQuestao,
            'quantidade_sortear'            => $quantidade,
            'distribuicao_dificuldade_json' => $distribuicao['valor'],
            'conta_para_percentual'         => $tipoQuestao === 'discursiva' ? 0 : (isset($dados['conta_para_percentual']) ? (int) (bool) $dados['conta_para_percentual'] : 1),
            'obrigatorio_para_envio'        => isset($dados['obrigatorio_para_envio']) ? (int) (bool) $dados['obrigatorio_para_envio'] : 1,
            'status'                        => $status,
        );

        try {
            if ($id > 0) {
                $bloco = $this->blocoModel->findById($id);
                if (!$bloco || (int) $bloco['quiz_id'] !== $quizId) {
                    return array('ok' => false, 'message' => 'Bloco não pertence a este simulado.');
                }
                $campos['ordem'] = isset($dados['ordem']) && $dados['ordem'] !== '' ? (int) $dados['ordem'] : (int) $bloco['ordem'];
                $this->blocoModel->update($campos, $id);
                $acao = 'quiz.bloco.atualizado';
            } else {
                $campos['ordem'] = isset($dados['ordem']) && $dados['ordem'] !== '' ? (int) $dados['ordem'] : $this->blocoModel->nextOrderForQuiz($quizId);
                $id   = $this->blocoModel->create($campos);
                $acao = 'quiz.bloco.criado';
            }

            $this->auditService->record(
                $acao,
                'conteudo_quiz_blocos',
                (int) $id,
                array('quiz_id' => $quizId, 'codigo' => $codigo, 'quantidade' => $quantidade, 'status' => $status),
                $usuarioId,
                $ip,
                $userAgent
            );

            return array('ok' => true, 'id' => (int) $id);
        } catch (Exception $e) {
            Logger::error('quiz.bloco.salvar.erro', array('quiz_id' => $quizId, 'bloco_id' => $id));
            return array('ok' => false, 'message' => 'Não foi possível salvar o bloco.');
        }
    }

    /**
     * Exclusao logica com justificativa obrigatoria (padrao do projeto).
     */
    public function excluir($blocoId, $quizId, $justificativa, $usuarioId = null, $ip = null, $userAgent = null)
    {
        $blocoId = (int) $blocoId;
        $bloco   = $this->blocoModel->findById($blocoId);
        if (!$bloco || (int) $bloco['quiz_id'] !== (int) $quizId) {
            return array('ok' => false, 'message' => 'Bloco não encontrado.');
        }

        $justificativa = trim((string) $justificativa);
        if (mb_strlen($justificativa) < 5) {
            return array('ok' => false, 'message' => 'Informe uma justificativa com pelo menos 5 caracteres para excluir o bloco.');
        }

        $vinculadas = $this->perguntaModel->countForBloco($blocoId);
        if ($vinculadas > 0) {
            return array(
                'ok' => false,
                'message' => 'Este bloco tem ' . $vinculadas . ' questão(ões) vinculada(s). Mova ou exclua as questões antes.',
            );
        }

        $pdo = Database::connection();
        $transacaoPropia = !$pdo->inTransaction();
        if ($transacaoPropia) {
            $pdo->beginTransaction();
        }
        try {
            $trashService = new TrashService();
            $trashService->record(
                'conteudo_quiz_blocos',
                $blocoId,
                $justificativa,
                $bloco,
                $usuarioId,
                $ip,
                $userAgent
            );

            $this->blocoModel->softDelete($blocoId);

            if ($transacaoPropia) {
                $pdo->commit();
            }
            return array('ok' => true);
        } catch (Exception $e) {
            if ($transacaoPropia && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Logger::error('quiz.bloco.excluir.erro', array('bloco_id' => $blocoId));
            return array('ok' => false, 'message' => 'Não foi possível excluir o bloco.');
        }
    }

    public function alternarStatus($blocoId, $quizId, $usuarioId = null)
    {
        $bloco = $this->blocoModel->findById((int) $blocoId);
        if (!$bloco || (int) $bloco['quiz_id'] !== (int) $quizId) {
            return array('ok' => false, 'message' => 'Bloco não encontrado.');
        }

        $novoStatus = (string) $bloco['status'] === 'ativo' ? 'inativo' : 'ativo';
        $this->blocoModel->updateStatus((int) $blocoId, $novoStatus);

        $this->auditService->record(
            'quiz.bloco.status',
            'conteudo_quiz_blocos',
            (int) $blocoId,
            array('quiz_id' => (int) $quizId, 'status' => $novoStatus),
            $usuarioId
        );

        return array('ok' => true, 'status' => $novoStatus);
    }

    public function reordenar($quizId, array $ordens)
    {
        $quizId = (int) $quizId;
        $mapa   = array();
        foreach ($this->blocoModel->listForQuiz($quizId) as $bloco) {
            $mapa[(int) $bloco['id']] = true;
        }

        foreach ($ordens as $blocoId => $ordem) {
            if (isset($mapa[(int) $blocoId])) {
                $this->blocoModel->updateOrdem((int) $blocoId, (int) $ordem);
            }
        }

        return array('ok' => true);
    }

    /**
     * Le a distribuicao de dificuldade vinda do formulario.
     *
     * @return array ['valor' => string|null] ou ['error' => string]
     */
    private function normalizarDistribuicaoEntrada(array $dados)
    {
        if (empty($dados['usar_distribuicao'])) {
            return array('valor' => null);
        }

        $percentuais = array();
        $soma        = 0.0;
        foreach (QuizSorteioService::DIFICULDADES as $dificuldade) {
            $chave = 'distribuicao_' . $dificuldade;
            $valor = isset($dados[$chave]) && $dados[$chave] !== '' ? (float) str_replace(',', '.', (string) $dados[$chave]) : 0.0;
            if ($valor < 0) {
                return array('error' => 'Os percentuais de dificuldade não podem ser negativos.');
            }
            $percentuais[$dificuldade] = $valor;
            $soma += $valor;
        }

        if ($soma <= 0) {
            return array('valor' => null);
        }
        if (abs($soma - 100.0) > 0.01) {
            return array('error' => 'A soma dos percentuais de dificuldade deve ser 100% (atualmente ' . rtrim(rtrim(number_format($soma, 2, ',', '.'), '0'), ',') . '%).');
        }

        return array('valor' => json_encode($percentuais, JSON_UNESCAPED_UNICODE));
    }
}
