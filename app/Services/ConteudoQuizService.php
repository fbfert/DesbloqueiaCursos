<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Models\ConteudoItem;
use App\Models\ConteudoModulo;
use App\Models\ConteudoProgressoAluno;
use App\Models\ConteudoQuiz;
use App\Models\ConteudoQuizAlternativa;
use App\Models\ConteudoQuizBloco;
use App\Models\ConteudoQuizCorrecaoDiscursiva;
use App\Models\ConteudoQuizItemUtilizado;
use App\Models\ConteudoQuizPergunta;
use App\Models\ConteudoQuizResposta;
use App\Models\ConteudoQuizTentativa;
use App\Models\Inscricao;
use App\Services\AuditService;
use App\Services\Quiz\QuizRandomizerInterface;
use App\Services\Quiz\QuizRandomizerSeguro;
use App\Services\Quiz\QuizSorteioService;
use Exception;
use PDOException;

class ConteudoQuizService
{
    /** Limite padrao de caracteres da resposta discursiva. */
    const LIMITE_DISCURSIVA_PADRAO = 50000;
    /** Minimo de caracteres para considerar uma discursiva respondida. */
    const MINIMO_DISCURSIVA = 3;

    private $quizModel;
    private $perguntaModel;
    private $alternativaModel;
    private $tentativaModel;
    private $respostaModel;
    private $blocoModel;
    private $itemUtilizadoModel;
    private $correcaoModel;
    private $itemModel;
    private $moduloModel;
    private $progressoModel;
    private $inscricaoModel;
    private $auditService;
    private $randomizer;
    private $sorteioService;

    /**
     * @param QuizRandomizerInterface|null $randomizer Injetavel para tornar o
     *        sorteio determinista nos testes. Em producao usa CSPRNG.
     */
    public function __construct(?QuizRandomizerInterface $randomizer = null)
    {
        $this->quizModel          = new ConteudoQuiz();
        $this->perguntaModel      = new ConteudoQuizPergunta();
        $this->alternativaModel   = new ConteudoQuizAlternativa();
        $this->tentativaModel     = new ConteudoQuizTentativa();
        $this->respostaModel      = new ConteudoQuizResposta();
        $this->blocoModel         = new ConteudoQuizBloco();
        $this->itemUtilizadoModel = new ConteudoQuizItemUtilizado();
        $this->correcaoModel      = new ConteudoQuizCorrecaoDiscursiva();
        $this->itemModel          = new ConteudoItem();
        $this->moduloModel        = new ConteudoModulo();
        $this->progressoModel     = new ConteudoProgressoAluno();
        $this->inscricaoModel     = new Inscricao();
        $this->auditService       = new AuditService();
        $this->randomizer         = $randomizer ?: new QuizRandomizerSeguro();
        $this->sorteioService     = new QuizSorteioService($this->randomizer);
    }

    // ------------------------------------------------------------------
    // LEITURA PARA O ALUNO (sem revelar gabarito)
    // ------------------------------------------------------------------

    public function findQuizParaAluno($itemId, $alunoId, $inscricaoId)
    {
        $quiz = $this->quizModel->findByItemId((int) $itemId);
        if (!$quiz) {
            return null;
        }

        $tentativaEmAndamento = $this->tentativaModel->findEmAndamento((int) $quiz['id'], (int) $inscricaoId);
        if ($tentativaEmAndamento) {
            // Fecha automaticamente uma tentativa cujo prazo venceu enquanto o
            // aluno estava fora, antes de montar a tela.
            $encerramento = $this->encerrarSeExpirada($tentativaEmAndamento);
            if (!empty($encerramento['encerrada'])) {
                $tentativaEmAndamento = null;
            }
        }

        $snapshot = $tentativaEmAndamento
            ? $this->decodeSnapshot($tentativaEmAndamento['quiz_snapshot_json'] ?? null)
            : null;

        $estrutura = $this->estruturaDoQuiz($quiz, (int) $inscricaoId);

        // Em modo blocos, antes de iniciar não existe prova montada: as
        // questões só surgem no sorteio da tentativa. Listar o banco inteiro
        // aqui daria um total enganoso (o tamanho do banco, não o da prova) e
        // carregaria enunciados que o aluno ainda não deve receber.
        if ($tentativaEmAndamento === null && !empty($estrutura['por_blocos'])) {
            $perguntas       = array();
            $totalPerguntas  = (int) $estrutura['total_questoes'];
        } else {
            $perguntas      = $this->montarPerguntasParaAluno($quiz, $snapshot);
            $totalPerguntas = count($perguntas);
        }

        $quiz['perguntas']           = $perguntas;
        $quiz['total_perguntas']     = $totalPerguntas;
        $quiz['tentativas_usadas']   = $this->tentativaModel->countForInscricao((int) $quiz['id'], (int) $inscricaoId);
        $quiz['pode_nova_tentativa'] = $this->podeFazerNovaTentativa((int) $quiz['id'], (int) $inscricaoId);
        $quiz['estrutura']           = $estrutura;
        $quiz['tentativa_em_andamento'] = $tentativaEmAndamento;
        $quiz['tempo']               = $tentativaEmAndamento ? $this->tempoDaTentativa($tentativaEmAndamento) : null;
        $quiz['blocos_snapshot']     = $snapshot && !empty($snapshot['blocos']) ? $snapshot['blocos'] : array();

        return $quiz;
    }

    /**
     * Resumo mostrado ao aluno antes de comecar: estrutura da prova, duracao,
     * quantidade de questoes, tentativas restantes e regra de aprovacao.
     *
     * @return array
     */
    public function estruturaDoQuiz(array $quiz, $inscricaoId = 0)
    {
        $quizId      = (int) $quiz['id'];
        $inscricaoId = (int) $inscricaoId;
        $porBlocos   = $this->usaBancoDeQuestoes($quiz);

        $blocos          = array();
        $totalObjetivas  = 0;
        $totalDiscursivas = 0;

        if ($porBlocos) {
            foreach ($this->blocoModel->listForQuiz($quizId, true) as $bloco) {
                $quantidade = (int) $bloco['quantidade_sortear'];
                $blocos[] = array(
                    'id'                    => (int) $bloco['id'],
                    'codigo'                => (string) $bloco['codigo'],
                    'titulo'                => (string) $bloco['titulo'],
                    'descricao'             => $bloco['descricao'],
                    'tipo_questao'          => (string) $bloco['tipo_questao'],
                    'quantidade'            => $quantidade,
                    'conta_para_percentual' => (int) $bloco['conta_para_percentual'],
                );
                if ((string) $bloco['tipo_questao'] === 'discursiva') {
                    $totalDiscursivas += $quantidade;
                } else {
                    $totalObjetivas += $quantidade;
                }
            }
        } else {
            $perguntas = $this->perguntaModel->listForQuiz($quizId);
            foreach ($perguntas as $pergunta) {
                if ((string) ($pergunta['tipo'] ?? '') === 'discursiva') {
                    $totalDiscursivas++;
                } else {
                    $totalObjetivas++;
                }
            }
        }

        $tentativasMaximas = $quiz['tentativas_maximas'] !== null ? (int) $quiz['tentativas_maximas'] : null;
        $usadas            = $inscricaoId > 0 ? $this->tentativaModel->countForInscricao($quizId, $inscricaoId) : 0;

        return array(
            'por_blocos'          => $porBlocos,
            'blocos'              => $blocos,
            'total_objetivas'     => $totalObjetivas,
            'total_discursivas'   => $totalDiscursivas,
            'total_questoes'      => $totalObjetivas + $totalDiscursivas,
            'duracao_minutos'     => isset($quiz['duracao_minutos']) && $quiz['duracao_minutos'] !== null ? (int) $quiz['duracao_minutos'] : null,
            'percentual_minimo'   => (float) ($quiz['percentual_minimo'] ?? 0),
            'exige_aprovacao'     => !empty($quiz['exige_aprovacao']),
            'tentativas_maximas'  => $tentativasMaximas,
            'tentativas_usadas'   => $usadas,
            'tentativas_restantes'=> $tentativasMaximas !== null ? max(0, $tentativasMaximas - $usadas) : null,
            'acao_ao_expirar'     => (string) ($quiz['acao_ao_expirar'] ?? 'enviar_automatico'),
        );
    }

    /**
     * Tempo restante calculado no servidor a partir de iniciada_em/expira_em.
     * O relogio do navegador nunca e considerado.
     *
     * @return array|null NULL quando a tentativa nao tem limite de tempo.
     */
    public function tempoDaTentativa(array $tentativa)
    {
        if (empty($tentativa['expira_em'])) {
            return null;
        }

        $agora   = time();
        $expira  = strtotime((string) $tentativa['expira_em']);
        $inicio  = !empty($tentativa['iniciada_em']) ? strtotime((string) $tentativa['iniciada_em']) : $agora;
        $duracao = isset($tentativa['duracao_minutos']) && $tentativa['duracao_minutos'] !== null
            ? (int) $tentativa['duracao_minutos']
            : null;

        $restante = $expira !== false ? ($expira - $agora) : 0;

        return array(
            'duracao_minutos'    => $duracao,
            'iniciada_em'        => $tentativa['iniciada_em'] ?? null,
            'expira_em'          => $tentativa['expira_em'],
            'segundos_restantes' => max(0, (int) $restante),
            'segundos_decorridos'=> max(0, $agora - ($inicio !== false ? $inicio : $agora)),
            'expirada'           => $restante <= 0,
        );
    }

    public function findQuizCompleto($itemId)
    {
        $quiz = $this->quizModel->findByItemId((int) $itemId);
        if (!$quiz) {
            return null;
        }

        $perguntas = $this->perguntaModel->listForQuiz((int) $quiz['id']);
        foreach ($perguntas as &$pergunta) {
            $pergunta['alternativas'] = $this->alternativaModel->listForPergunta((int) $pergunta['id']);
        }
        unset($pergunta);

        $quiz['perguntas']       = $perguntas;
        $quiz['total_perguntas'] = count($perguntas);
        $quiz['blocos']          = $this->blocoModel->listForQuiz((int) $quiz['id']);
        return $quiz;
    }

    /**
     * Banco de questoes filtrado (tela administrativa).
     */
    public function listarBancoQuestoes($quizId, array $filtros = array())
    {
        return $this->perguntaModel->listBanco((int) $quizId, $filtros);
    }

    public function listarTemas($quizId)
    {
        return $this->perguntaModel->listTemas((int) $quizId);
    }

    /**
     * Alternativas de uma questão (uso administrativo — inclui o gabarito).
     */
    public function listarAlternativasDaPergunta($perguntaId)
    {
        return $this->alternativaModel->listForPergunta((int) $perguntaId);
    }

    // ------------------------------------------------------------------
    // TENTATIVAS DO ALUNO
    // ------------------------------------------------------------------

    public function podeFazerNovaTentativa($quizId, $inscricaoId)
    {
        $quiz = $this->quizModel->findById((int) $quizId);
        if (!$quiz) {
            return false;
        }

        $emAndamento = $this->tentativaModel->findEmAndamento((int) $quizId, (int) $inscricaoId);
        if ($emAndamento) {
            return true; // continuar tentativa existente
        }

        if ($quiz['tentativas_maximas'] === null) {
            return true;
        }

        $usadas = $this->tentativaModel->countForInscricao((int) $quizId, (int) $inscricaoId);
        return $usadas < (int) $quiz['tentativas_maximas'];
    }

    public function iniciarOuRetomar(array $contexto)
    {
        $quizId      = (int) ($contexto['quiz_id'] ?? 0);
        $inscricaoId = (int) ($contexto['inscricao_id'] ?? 0);
        $alunoId     = (int) ($contexto['aluno_id'] ?? 0);
        $cursoId     = (int) ($contexto['curso_evento_id'] ?? 0);
        $turmaId     = isset($contexto['turma_id']) && $contexto['turma_id'] ? (int) $contexto['turma_id'] : null;

        if ($quizId <= 0 || $inscricaoId <= 0 || $alunoId <= 0 || $cursoId <= 0) {
            return array('ok' => false, 'message' => 'Parâmetros inválidos para iniciar quiz.');
        }

        $quiz = $this->quizModel->findById($quizId);
        if (!$quiz) {
            return array('ok' => false, 'message' => 'Quiz não encontrado.');
        }

        $emAndamento = $this->tentativaModel->findEmAndamento($quizId, $inscricaoId);
        if ($emAndamento) {
            $encerramento = $this->encerrarSeExpirada($emAndamento);
            if (empty($encerramento['encerrada'])) {
                // Retomada NUNCA gera novo sorteio: o snapshot original e mantido.
                return array('ok' => true, 'tentativa' => $emAndamento, 'retomada' => true);
            }
        }

        $pdo             = Database::connection();
        $transacaoPropia = !$pdo->inTransaction();
        if ($transacaoPropia) {
            $pdo->beginTransaction();
        }

        try {
            // Trava a faixa de tentativas desta inscricao: duas requisicoes
            // concorrentes nao criam tentativas duplicadas nem ultrapassam o
            // limite configurado.
            $stmtLock = $pdo->prepare(
                'SELECT id, status, numero_tentativa
                 FROM conteudo_quiz_tentativas
                 WHERE quiz_id = :quiz_id AND inscricao_id = :inscricao_id AND deleted_at IS NULL
                 ORDER BY numero_tentativa DESC
                 FOR UPDATE'
            );
            $stmtLock->execute(array('quiz_id' => $quizId, 'inscricao_id' => $inscricaoId));
            $existentes = $stmtLock->fetchAll(\PDO::FETCH_ASSOC);

            $maiorNumero = 0;
            $validas     = 0;
            foreach ($existentes as $linha) {
                $maiorNumero = max($maiorNumero, (int) $linha['numero_tentativa']);
                if ((string) $linha['status'] === 'em_andamento') {
                    $tentativaAtiva = $this->tentativaModel->findById((int) $linha['id']);
                    if ($transacaoPropia) {
                        $pdo->commit();
                    }
                    return array('ok' => true, 'tentativa' => $tentativaAtiva, 'retomada' => true);
                }
                if ((string) $linha['status'] !== 'cancelada') {
                    $validas++;
                }
            }

            $tentativasMaximas = $quiz['tentativas_maximas'] !== null ? (int) $quiz['tentativas_maximas'] : null;
            if ($tentativasMaximas !== null && $validas >= $tentativasMaximas) {
                if ($transacaoPropia) {
                    $pdo->rollBack();
                }
                return array('ok' => false, 'message' => 'Você atingiu o número máximo de tentativas para este quiz.');
            }

            $numero   = $maiorNumero + 1;
            $sorteio  = $this->buildSnapshot($quiz, array(
                'inscricao_id'     => $inscricaoId,
                'aluno_id'         => $alunoId,
                'numero_tentativa' => $numero,
            ));
            $snapshot = $sorteio['snapshot'];

            if (empty($snapshot['perguntas'])) {
                if ($transacaoPropia) {
                    $pdo->rollBack();
                }
                return array('ok' => false, 'message' => 'Este quiz ainda não tem questões suficientes para iniciar uma tentativa.');
            }

            $iniciadaEm = date('Y-m-d H:i:s');
            $duracao    = isset($snapshot['duracao_minutos']) && $snapshot['duracao_minutos'] !== null
                ? (int) $snapshot['duracao_minutos']
                : null;
            $expiraEm = $duracao !== null && $duracao > 0
                ? date('Y-m-d H:i:s', strtotime($iniciadaEm) + ($duracao * 60))
                : null;

            $tentativaId = $this->tentativaModel->create(array(
                'quiz_id'                => $quizId,
                'curso_evento_id'        => $cursoId,
                'turma_id'               => $turmaId,
                'inscricao_id'           => $inscricaoId,
                'aluno_id'               => $alunoId,
                'numero_tentativa'       => $numero,
                'status'                 => 'em_andamento',
                'total_perguntas'        => count($snapshot['perguntas']),
                'total_objetivas'        => (int) $snapshot['sorteio']['total_objetivas'],
                'total_discursivas'      => (int) $snapshot['sorteio']['total_discursivas'],
                'discursiva_status'      => (int) $snapshot['sorteio']['total_discursivas'] > 0 ? 'pendente' : 'nao_aplicavel',
                'sorteio_com_repeticao'  => !empty($snapshot['sorteio']['com_repeticao']) ? 1 : 0,
                'sorteio_auditoria_json' => $snapshot['sorteio']['ocorrencias'],
                'quiz_snapshot_json'     => $snapshot,
                'duracao_minutos'        => $duracao,
                'expira_em'              => $expiraEm,
                'iniciada_em'            => $iniciadaEm,
                'ultima_atividade_em'    => $iniciadaEm,
            ));

            $this->registrarItensUtilizados($tentativaId, $quizId, $inscricaoId, $alunoId, $numero, $snapshot);

            if ($transacaoPropia) {
                $pdo->commit();
            }

            if (!empty($snapshot['sorteio']['com_repeticao']) || !empty($snapshot['sorteio']['ocorrencias'])) {
                // Auditoria sem dados pessoais nem conteudo de questoes.
                $this->auditService->record(
                    'quiz.sorteio.ocorrencias',
                    'conteudo_quiz_tentativas',
                    (int) $tentativaId,
                    array(
                        'quiz_id'       => $quizId,
                        'com_repeticao' => !empty($snapshot['sorteio']['com_repeticao']),
                        'ocorrencias'   => $snapshot['sorteio']['ocorrencias'],
                    ),
                    $alunoId
                );
            }

            $tentativa = $this->tentativaModel->findById($tentativaId);
            return array('ok' => true, 'tentativa' => $tentativa, 'retomada' => false);
        } catch (PDOException $e) {
            if ($transacaoPropia && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            // Corrida perdida na chave unica (quiz_id, inscricao_id, numero):
            // outra requisicao ja criou a tentativa; devolve a existente.
            if ((string) $e->getCode() === '23000') {
                $existente = $this->tentativaModel->findEmAndamento($quizId, $inscricaoId);
                if ($existente) {
                    return array('ok' => true, 'tentativa' => $existente, 'retomada' => true);
                }
                return array('ok' => false, 'message' => 'Não foi possível iniciar a tentativa. Tente novamente.');
            }
            Logger::error('quiz.iniciar.erro', array('quiz_id' => $quizId, 'codigo' => $e->getCode()));
            return array('ok' => false, 'message' => 'Não foi possível iniciar a tentativa.');
        } catch (Exception $e) {
            if ($transacaoPropia && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            // Sem mensagem crua: ela pode carregar trecho de SQL com enunciados.
            Logger::error('quiz.iniciar.erro', array('quiz_id' => $quizId, 'excecao' => get_class($e)));
            return array('ok' => false, 'message' => 'Não foi possível iniciar a tentativa.');
        }
    }

    /**
     * Encerra a tentativa quando o prazo do servidor venceu.
     *
     * Regra explicita (configuravel em conteudo_quizzes.acao_ao_expirar):
     *  - 'enviar_automatico'  : envia o que estiver salvo e corrige (padrao PND);
     *  - 'encerrar_sem_envio' : encerra a tentativa sem corrigir.
     *
     * @return array ['encerrada' => bool, 'motivo' => string|null, 'resultado' => array|null]
     */
    public function encerrarSeExpirada($tentativa)
    {
        if (!is_array($tentativa) || (string) ($tentativa['status'] ?? '') !== 'em_andamento') {
            return array('encerrada' => false);
        }
        if (empty($tentativa['expira_em'])) {
            return array('encerrada' => false);
        }

        $expira = strtotime((string) $tentativa['expira_em']);
        if ($expira === false || $expira > time()) {
            return array('encerrada' => false);
        }

        $quiz = $this->quizModel->findById((int) $tentativa['quiz_id']);
        if (!$quiz) {
            return array('encerrada' => false);
        }

        $acao = (string) ($quiz['acao_ao_expirar'] ?? 'enviar_automatico');

        if ($acao === 'encerrar_sem_envio') {
            $this->tentativaModel->update(array(
                'status'                   => 'cancelada',
                'total_perguntas'          => (int) $tentativa['total_perguntas'],
                'total_objetivas'          => (int) ($tentativa['total_objetivas'] ?? 0),
                'total_discursivas'        => (int) ($tentativa['total_discursivas'] ?? 0),
                'total_acertos'            => 0,
                'pontos_obtidos'           => 0,
                'pontos_totais'            => 0,
                'percentual'               => 0,
                'aprovado'                 => 0,
                'discursiva_status'        => (string) ($tentativa['discursiva_status'] ?? 'nao_aplicavel'),
                'quiz_snapshot_json'       => $tentativa['quiz_snapshot_json'],
                'encerrada_por_tempo'      => 1,
                'tempo_utilizado_segundos' => $this->calcularTempoUtilizado($tentativa),
                'ultima_atividade_em'      => date('Y-m-d H:i:s'),
                'enviada_em'               => null,
                'corrigida_em'             => null,
            ), (int) $tentativa['id']);

            Logger::info('quiz.tempo.encerrada_sem_envio', array(
                'tentativa_id' => (int) $tentativa['id'],
                'quiz_id'      => (int) $tentativa['quiz_id'],
            ));

            return array('encerrada' => true, 'motivo' => 'encerrar_sem_envio', 'resultado' => null);
        }

        // Envio automatico do que estiver salvo.
        $envio = $this->enviarTentativa(array(
            'tentativa_id' => (int) $tentativa['id'],
            'aluno_id'     => (int) $tentativa['aluno_id'],
            'respostas'    => array(),
            'automatico'   => true,
        ));

        Logger::info('quiz.tempo.envio_automatico', array(
            'tentativa_id' => (int) $tentativa['id'],
            'quiz_id'      => (int) $tentativa['quiz_id'],
            'ok'           => !empty($envio['ok']),
        ));

        return array(
            'encerrada' => !empty($envio['ok']),
            'motivo'    => 'enviar_automatico',
            'resultado' => isset($envio['resultado']) ? $envio['resultado'] : null,
        );
    }

    /**
     * Consulta o tempo restante de uma tentativa do próprio aluno.
     * Aplica a regra de expiração quando o prazo já venceu.
     *
     * @return array
     */
    public function consultarTempo($tentativaId, $alunoId)
    {
        $tentativa = $this->validarTentativaDoAluno((int) $tentativaId, (int) $alunoId);
        if (is_array($tentativa) && isset($tentativa['error'])) {
            return array('ok' => false, 'message' => $tentativa['error']);
        }

        $encerramento = $this->encerrarSeExpirada($tentativa);
        if (!empty($encerramento['encerrada'])) {
            return array(
                'ok'       => true,
                'expirada' => true,
                'motivo'   => $encerramento['motivo'],
                'tempo'    => array('segundos_restantes' => 0, 'expirada' => true),
            );
        }

        $tempo = $this->tempoDaTentativa($tentativa);
        return array(
            'ok'       => true,
            'expirada' => $tempo !== null ? !empty($tempo['expirada']) : false,
            'tempo'    => $tempo,
        );
    }

    /**
     * Fecha todas as tentativas vencidas (uso por rotina agendada/admin).
     *
     * @return int Quantidade de tentativas encerradas.
     */
    public function encerrarTentativasExpiradas($limite = 50)
    {
        $encerradas = 0;
        foreach ($this->tentativaModel->listExpiradas($limite) as $tentativa) {
            $resultado = $this->encerrarSeExpirada($tentativa);
            if (!empty($resultado['encerrada'])) {
                $encerradas++;
            }
        }
        return $encerradas;
    }

    public function salvarRascunho(array $dados)
    {
        $tentativaId = (int) ($dados['tentativa_id'] ?? 0);
        $alunoId     = (int) ($dados['aluno_id'] ?? 0);
        $itemId      = (int) ($dados['item_id'] ?? 0);
        $respostas   = isset($dados['respostas']) && is_array($dados['respostas']) ? $dados['respostas'] : array();
        $discursivas = isset($dados['discursivas']) && is_array($dados['discursivas']) ? $dados['discursivas'] : array();
        $revisoes    = isset($dados['revisoes']) && is_array($dados['revisoes']) ? $dados['revisoes'] : array();

        $tentativa = $this->validarTentativaDoAluno($tentativaId, $alunoId);
        if (is_array($tentativa) && isset($tentativa['error'])) {
            return array('ok' => false, 'message' => $tentativa['error']);
        }

        $quiz = $this->quizModel->findById((int) $tentativa['quiz_id']);
        if (!$quiz) {
            return array('ok' => false, 'message' => 'Quiz não encontrado.');
        }

        if ($itemId > 0) {
            $item = $this->itemModel->findById($itemId);
            if (!$item || (int) $quiz['item_id'] !== (int) $item['id']) {
                return array('ok' => false, 'message' => 'O quiz não pertence ao conteúdo informado.');
            }
        }

        if ((string) ($tentativa['status'] ?? '') !== 'em_andamento') {
            return array('ok' => false, 'message' => 'Esta tentativa já foi enviada ou encerrada.');
        }

        // Prazo conferido no servidor: rascunho fora do tempo dispara a regra
        // de expiracao em vez de gravar respostas novas.
        $encerramento = $this->encerrarSeExpirada($tentativa);
        if (!empty($encerramento['encerrada'])) {
            return array(
                'ok'       => false,
                'expirada' => true,
                'message'  => 'O tempo da prova terminou. A tentativa foi encerrada automaticamente.',
            );
        }

        $pdo             = Database::connection();
        $transacaoPropia = !$pdo->inTransaction();
        if ($transacaoPropia) {
            $pdo->beginTransaction();
        }
        try {
            $this->gravarRespostas($tentativa, $quiz, $respostas, $discursivas, false);
            $this->gravarMarcacoesRevisao($tentativa, $revisoes);
            $this->tentativaModel->tocarAtividade($tentativaId);
            if ($transacaoPropia) {
                $pdo->commit();
            }

            $tempo = $this->tempoDaTentativa($this->tentativaModel->findById($tentativaId));
            return array('ok' => true, 'tempo' => $tempo);
        } catch (Exception $e) {
            if ($transacaoPropia && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            // Nunca registrar respostas ou dados pessoais no log de erro.
            Logger::error('quiz.rascunho.erro', array('tentativa_id' => $tentativaId));
            return array('ok' => false, 'message' => 'Não foi possível salvar o rascunho.');
        }
    }

    /**
     * Marca/desmarca questoes para revisao sem alterar as respostas.
     */
    public function marcarParaRevisao(array $dados)
    {
        $tentativaId = (int) ($dados['tentativa_id'] ?? 0);
        $alunoId     = (int) ($dados['aluno_id'] ?? 0);
        $perguntaId  = (int) ($dados['pergunta_id'] ?? 0);
        $marcada     = !empty($dados['marcada']);

        $tentativa = $this->validarTentativaDoAluno($tentativaId, $alunoId);
        if (is_array($tentativa) && isset($tentativa['error'])) {
            return array('ok' => false, 'message' => $tentativa['error']);
        }
        if ((string) ($tentativa['status'] ?? '') !== 'em_andamento') {
            return array('ok' => false, 'message' => 'Esta tentativa já foi enviada ou encerrada.');
        }

        $snapshot = $this->decodeSnapshot($tentativa['quiz_snapshot_json'] ?? null);
        $mapa     = $this->mapaPerguntasDoSnapshot($tentativa, $snapshot);
        if (!isset($mapa[$perguntaId])) {
            return array('ok' => false, 'message' => 'Questão não pertence a esta tentativa.');
        }

        $this->gravarMarcacoesRevisao($tentativa, array($perguntaId => $marcada));
        return array('ok' => true, 'marcada' => $marcada);
    }

    public function enviarTentativa(array $dados)
    {
        $tentativaId = (int) ($dados['tentativa_id'] ?? 0);
        $alunoId     = (int) ($dados['aluno_id'] ?? 0);
        $respostas   = isset($dados['respostas']) && is_array($dados['respostas']) ? $dados['respostas'] : array();
        $discursivas = isset($dados['discursivas']) && is_array($dados['discursivas']) ? $dados['discursivas'] : array();
        $itemId      = (int) ($dados['item_id'] ?? 0);
        $inscricaoId = (int) ($dados['inscricao_id'] ?? 0);
        $automatico  = !empty($dados['automatico']);
        $ip          = isset($dados['ip']) ? (string) $dados['ip'] : null;
        $ua          = isset($dados['user_agent']) ? (string) $dados['user_agent'] : null;

        $tentativa = $this->validarTentativaDoAluno($tentativaId, $alunoId);
        if (is_array($tentativa) && isset($tentativa['error'])) {
            return array('ok' => false, 'message' => $tentativa['error']);
        }

        $quiz = $this->quizModel->findById((int) $tentativa['quiz_id']);
        if (!$quiz) {
            return array('ok' => false, 'message' => 'Quiz não encontrado.');
        }

        // Envio fora do prazo: nao aceita respostas novas, corrige o que ja
        // estava salvo e marca a tentativa como encerrada por tempo.
        $expirada = $this->tentativaExpirada($tentativa);
        if ($expirada && !$automatico) {
            $automatico  = true;
            $respostas   = array();
            $discursivas = array();
        }

        if ($itemId > 0) {
            $item = $this->itemModel->findById($itemId);
            if (!$item || (int) $item['id'] !== (int) $quiz['item_id']) {
                return array('ok' => false, 'message' => 'O quiz não pertence ao conteúdo informado.');
            }
            if ((int) $item['curso_evento_id'] !== (int) $tentativa['curso_evento_id']) {
                return array('ok' => false, 'message' => 'O conteúdo não pertence à inscrição informada.');
            }
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $stmtLock = $pdo->prepare(
                'SELECT * FROM conteudo_quiz_tentativas WHERE id = :id AND aluno_id = :aluno_id AND deleted_at IS NULL FOR UPDATE'
            );
            $stmtLock->execute(array('id' => $tentativaId, 'aluno_id' => $alunoId));
            $tentativaBloqueada = $stmtLock->fetch(\PDO::FETCH_ASSOC);
            if (!$tentativaBloqueada) {
                $pdo->rollBack();
                return array('ok' => false, 'message' => 'Tentativa não encontrada.');
            }

            if ((string) ($tentativaBloqueada['status'] ?? '') !== 'em_andamento') {
                $pdo->commit();
                return array(
                    'ok' => true,
                    'resultado' => $this->resultadoPersistidoDaTentativa($tentativaBloqueada),
                    'tentativa' => $tentativaBloqueada,
                    'reutilizada' => true,
                );
            }

            // O snapshot da tentativa e a fonte de verdade: apenas as questoes
            // sorteadas sao validadas, gravadas e corrigidas.
            $snapshot  = $this->decodeSnapshot($tentativaBloqueada['quiz_snapshot_json'] ?? null);
            $perguntas = $this->perguntasDoSnapshot($tentativaBloqueada, $snapshot);

            $this->gravarRespostas($tentativaBloqueada, $quiz, $respostas, $discursivas, true);

            if (!$automatico) {
                $pendencia = $this->validarObrigatoriasDoSnapshot($tentativaId, $perguntas);
                if ($pendencia !== null) {
                    $pdo->rollBack();
                    return array('ok' => false, 'message' => $pendencia);
                }
            }

            $resultado = $this->calcularResultado($tentativaId, $quiz, $perguntas);
            $agora     = date('Y-m-d H:i:s');

            $this->tentativaModel->update(array(
                'status'                   => 'corrigida',
                'total_perguntas'          => $resultado['total_perguntas'],
                'total_objetivas'          => $resultado['total_objetivas'],
                'total_discursivas'        => $resultado['total_discursivas'],
                'total_acertos'            => $resultado['total_acertos'],
                'pontos_obtidos'           => $resultado['pontos_obtidos'],
                'pontos_totais'            => $resultado['pontos_totais'],
                'percentual'               => $resultado['percentual'],
                'aprovado'                 => $resultado['aprovado'],
                'discursiva_status'        => $resultado['discursiva_status'],
                'quiz_snapshot_json'       => $tentativaBloqueada['quiz_snapshot_json'],
                'encerrada_por_tempo'      => $automatico && $expirada ? 1 : 0,
                'tempo_utilizado_segundos' => $this->calcularTempoUtilizado($tentativaBloqueada),
                'ultima_atividade_em'      => $agora,
                'enviada_em'               => $agora,
                'corrigida_em'             => $agora,
            ), $tentativaId);

            $item = $itemId > 0 ? $this->itemModel->findById($itemId) : $this->itemModel->findById((int) $quiz['item_id']);
            if ($item) {
                $this->atualizarProgressoConteudo($tentativaBloqueada, $quiz, $resultado, $item);
            }

            Logger::info('quiz.envio', array(
                'tentativa_id' => $tentativaId,
                'quiz_id'      => (int) $quiz['id'],
                'percentual'   => $resultado['percentual'],
                'aprovado'     => $resultado['aprovado'],
                'automatico'   => $automatico ? 1 : 0,
            ));

            $pdo->commit();

            $tentativaAtualizada = $this->tentativaModel->findById($tentativaId);
            return array(
                'ok'         => true,
                'resultado'  => $resultado,
                'tentativa'  => $tentativaAtualizada,
                'automatico' => $automatico,
            );
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            // Sem respostas, textos completos ou dados pessoais no log.
            Logger::error('quiz.envio.erro', array('tentativa_id' => $tentativaId));
            return array('ok' => false, 'message' => 'Não foi possível registrar o envio do quiz.');
        }
    }

    public function obterTentativaParaAluno($tentativaId, $alunoId)
    {
        $tentativa = $this->tentativaModel->findById((int) $tentativaId);
        if (!$tentativa || (int) $tentativa['aluno_id'] !== (int) $alunoId) {
            return null;
        }

        $quiz = $this->quizModel->findById((int) $tentativa['quiz_id']);
        if (!$quiz) {
            return null;
        }

        $respostas = $this->respostaModel->listForTentativa((int) $tentativaId);
        $respostasPorPergunta = array();
        foreach ($respostas as $r) {
            $respostasPorPergunta[(int) $r['pergunta_id']] = $r;
        }

        $snapshot = $this->decodeSnapshot($tentativa['quiz_snapshot_json'] ?? null);
        $perguntas = $this->montarPerguntasParaAluno($quiz, $snapshot, $tentativa, $respostasPorPergunta);

        return array(
            'tentativa'  => $tentativa,
            'quiz'       => $quiz,
            'perguntas'  => $perguntas,
            'blocos'     => $snapshot && !empty($snapshot['blocos']) ? $snapshot['blocos'] : array(),
            'desempenho' => $this->desempenhoDaTentativa($tentativa),
            'discursivas'=> $this->correcoesDaTentativa($tentativa, $quiz),
            'tempo'      => $this->tempoDaTentativa($tentativa),
        );
    }

    /**
     * Desempenho objetivo por bloco e por tema, calculado sobre o snapshot.
     *
     * @return array
     */
    public function desempenhoDaTentativa(array $tentativa)
    {
        $snapshot  = $this->decodeSnapshot($tentativa['quiz_snapshot_json'] ?? null);
        $perguntas = $this->perguntasDoSnapshot($tentativa, $snapshot);

        $respostas = array();
        foreach ($this->respostaModel->listForTentativa((int) $tentativa['id']) as $resposta) {
            $respostas[(int) $resposta['pergunta_id']] = $resposta;
        }

        $blocos = array();
        $temas  = array();
        $totais = array('objetivas' => 0, 'acertos' => 0);

        foreach ($perguntas as $pergunta) {
            if (!$this->perguntaEObjetiva($pergunta)) {
                continue;
            }

            $perguntaId    = (int) $pergunta['id'];
            $codigoBloco   = (string) ($pergunta['bloco_codigo'] ?? 'GERAL');
            $tituloBloco   = (string) ($pergunta['bloco_titulo'] ?? 'Questões objetivas');
            $tema          = trim((string) ($pergunta['tema'] ?? ''));
            $resposta      = isset($respostas[$perguntaId]) ? $respostas[$perguntaId] : null;
            $acertou       = $resposta !== null && !empty($resposta['correta']);

            if (!isset($blocos[$codigoBloco])) {
                $blocos[$codigoBloco] = array(
                    'codigo' => $codigoBloco, 'titulo' => $tituloBloco,
                    'total' => 0, 'acertos' => 0, 'percentual' => 0.0,
                );
            }
            $blocos[$codigoBloco]['total']++;
            $totais['objetivas']++;
            if ($acertou) {
                $blocos[$codigoBloco]['acertos']++;
                $totais['acertos']++;
            }

            if ($tema !== '') {
                if (!isset($temas[$tema])) {
                    $temas[$tema] = array('tema' => $tema, 'total' => 0, 'acertos' => 0, 'percentual' => 0.0);
                }
                $temas[$tema]['total']++;
                if ($acertou) {
                    $temas[$tema]['acertos']++;
                }
            }
        }

        foreach ($blocos as $codigo => $dados) {
            $blocos[$codigo]['percentual'] = $dados['total'] > 0
                ? round(($dados['acertos'] / $dados['total']) * 100, 2)
                : 0.0;
        }
        foreach ($temas as $chave => $dados) {
            $temas[$chave]['percentual'] = $dados['total'] > 0
                ? round(($dados['acertos'] / $dados['total']) * 100, 2)
                : 0.0;
        }
        ksort($temas);

        return array(
            'blocos'          => array_values($blocos),
            'temas'           => array_values($temas),
            'total_objetivas' => $totais['objetivas'],
            'total_acertos'   => $totais['acertos'],
            'percentual'      => $totais['objetivas'] > 0
                ? round(($totais['acertos'] / $totais['objetivas']) * 100, 2)
                : 0.0,
        );
    }

    /**
     * Correcoes discursivas da tentativa, com a resposta do aluno.
     * Feedback e nota so aparecem quando a correcao ja foi registrada.
     */
    public function correcoesDaTentativa(array $tentativa, ?array $quiz = null)
    {
        $tentativaId = (int) $tentativa['id'];
        $correcoes   = $this->correcaoModel->listForTentativa($tentativaId);
        if (count($correcoes) === 0) {
            return array();
        }

        $snapshot  = $this->decodeSnapshot($tentativa['quiz_snapshot_json'] ?? null);
        $perguntas = $this->mapaPerguntasDoSnapshot($tentativa, $snapshot);

        $lista = array();
        foreach ($correcoes as $correcao) {
            $perguntaId = (int) $correcao['pergunta_id'];
            $resposta   = $this->respostaModel->findByTentativaEPergunta($tentativaId, $perguntaId);
            $pergunta   = isset($perguntas[$perguntaId]) ? $perguntas[$perguntaId] : array();
            $corrigida  = (string) $correcao['status'] === 'corrigida';

            $lista[] = array(
                'correcao_id'   => (int) $correcao['id'],
                'pergunta_id'   => $perguntaId,
                'enunciado'     => (string) ($pergunta['enunciado'] ?? ''),
                'bloco_codigo'  => (string) ($pergunta['bloco_codigo'] ?? ''),
                'resposta'      => $resposta ? (string) ($resposta['texto_resposta'] ?? '') : '',
                'status'        => (string) $correcao['status'],
                'nota'          => $corrigida && $correcao['nota'] !== null ? (float) $correcao['nota'] : null,
                'nota_maxima'   => (float) $correcao['nota_maxima'],
                'rubrica'       => $corrigida ? $correcao['rubrica'] : null,
                'feedback'      => $corrigida ? $correcao['feedback'] : null,
                'corrigida_em'  => $correcao['corrigida_em'],
                'origem'        => (string) $correcao['origem'],
            );
        }

        return $lista;
    }

    public function listarTentativasAluno($quizId, $inscricaoId)
    {
        return $this->tentativaModel->listForInscricao((int) $quizId, (int) $inscricaoId);
    }

    // ------------------------------------------------------------------
    // EDITOR ADMIN DE PERGUNTAS
    // ------------------------------------------------------------------

    public function salvarPergunta(array $dados)
    {
        $quizId    = (int) ($dados['quiz_id'] ?? 0);
        $id        = (int) ($dados['id'] ?? 0);
        $enunciado = trim((string) ($dados['enunciado'] ?? ''));

        if ($quizId <= 0) {
            return array('ok' => false, 'message' => 'Quiz inválido.');
        }
        if ($enunciado === '') {
            return array('ok' => false, 'message' => 'Informe o enunciado da pergunta.');
        }

        $quiz = $this->quizModel->findById($quizId);
        if (!$quiz) {
            return array('ok' => false, 'message' => 'Quiz não encontrado.');
        }

        $tipo = isset($dados['tipo']) && (string) $dados['tipo'] === 'discursiva' ? 'discursiva' : 'multipla_escolha';

        // Bloco: precisa pertencer ao mesmo quiz e aceitar o tipo da questao.
        $blocoId = isset($dados['bloco_id']) && $dados['bloco_id'] !== '' ? (int) $dados['bloco_id'] : null;
        if ($blocoId !== null && $blocoId > 0) {
            $bloco = $this->blocoModel->findById($blocoId);
            if (!$bloco || (int) $bloco['quiz_id'] !== $quizId) {
                return array('ok' => false, 'message' => 'Bloco inválido para este simulado.');
            }
            if ((string) $bloco['tipo_questao'] !== $tipo) {
                return array(
                    'ok' => false,
                    'message' => 'O tipo da questão não corresponde ao tipo do bloco "' . $bloco['titulo'] . '".',
                );
            }
        } else {
            $blocoId = null;
        }

        $dificuldade = $this->sorteioService->normalizarDificuldade($dados['dificuldade'] ?? null);
        $metadados   = array(
            'bloco_id'    => $blocoId,
            'tipo'        => $tipo,
            'dificuldade' => $dificuldade,
            'tema'        => isset($dados['tema']) ? trim((string) $dados['tema']) : null,
            'status'      => isset($dados['status']) && (string) $dados['status'] === 'inativo' ? 'inativo' : 'ativo',
            'referencia'  => isset($dados['referencia']) ? trim((string) $dados['referencia']) : null,
            'rubrica'     => isset($dados['rubrica']) ? trim((string) $dados['rubrica']) : null,
            'nota_maxima' => isset($dados['nota_maxima']) && $dados['nota_maxima'] !== '' ? (float) $dados['nota_maxima'] : null,
        );

        if ($tipo === 'discursiva') {
            return $this->salvarPerguntaDiscursiva($quizId, $id, $enunciado, $dados, $metadados);
        }

        $alternativas = isset($dados['alternativas']) && is_array($dados['alternativas']) ? $dados['alternativas'] : array();

        // Validação backend: ao menos 2 alternativas e exatamente 1 correta
        $alternativasValidas = array_filter($alternativas, function ($a) {
            return trim((string) ($a['texto'] ?? '')) !== '';
        });
        usort($alternativasValidas, function ($a, $b) {
            $ordemA = isset($a['ordem']) ? (int) $a['ordem'] : 0;
            $ordemB = isset($b['ordem']) ? (int) $b['ordem'] : 0;
            if ($ordemA === $ordemB) {
                return 0;
            }
            return $ordemA < $ordemB ? -1 : 1;
        });
        if (count($alternativasValidas) < 2) {
            return array('ok' => false, 'message' => 'Cada pergunta deve ter pelo menos duas alternativas.');
        }
        $numCorretas = count(array_filter($alternativasValidas, function ($a) {
            return !empty($a['correta']);
        }));
        if ($numCorretas !== 1) {
            return array('ok' => false, 'message' => 'Cada pergunta deve ter exatamente uma alternativa correta.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            if ($id > 0) {
                $pergunta = $this->perguntaModel->findById($id);
                if (!$pergunta || (int) $pergunta['quiz_id'] !== $quizId) {
                    $pdo->rollBack();
                    return array('ok' => false, 'message' => 'Pergunta não pertence a este quiz.');
                }
                $this->perguntaModel->update(array_merge($metadados, array(
                    'enunciado'   => $enunciado,
                    'explicacao'  => isset($dados['explicacao']) ? trim((string) $dados['explicacao']) : null,
                    'peso'        => isset($dados['peso']) && $dados['peso'] !== '' ? (float) $dados['peso'] : 1.00,
                    'obrigatoria' => !empty($dados['obrigatoria']) ? 1 : 0,
                    'ordem'       => isset($dados['ordem']) ? (int) $dados['ordem'] : (int) ($pergunta['ordem'] ?? 0),
                )), $id);
                $perguntaId = $id;
            } else {
                $perguntaId = $this->perguntaModel->create(array_merge($metadados, array(
                    'quiz_id'    => $quizId,
                    'enunciado'  => $enunciado,
                    'explicacao' => isset($dados['explicacao']) ? trim((string) $dados['explicacao']) : null,
                    'peso'       => isset($dados['peso']) && $dados['peso'] !== '' ? (float) $dados['peso'] : 1.00,
                    'obrigatoria'=> !empty($dados['obrigatoria']) ? 1 : 0,
                    'ordem'      => $this->perguntaModel->nextOrderForQuiz($quizId),
                )));
            }

            // Salvar alternativas (substitui todas)
            $idsExistentes = array();
            $existentesDB = $this->alternativaModel->listForPergunta($perguntaId);
            foreach ($existentesDB as $e) {
                $idsExistentes[] = (int) $e['id'];
            }

            $idsRecebidos = array();
            $ordem = 1;
            foreach ($alternativasValidas as $alt) {
                $altId = isset($alt['id']) ? (int) $alt['id'] : 0;
                $texto = trim((string) ($alt['texto'] ?? ''));
                $correta = !empty($alt['correta']) ? 1 : 0;
                $ordemAlt = isset($alt['ordem']) && $alt['ordem'] !== '' ? (int) $alt['ordem'] : $ordem;

                if ($altId > 0 && $this->alternativaModel->findByPerguntaAndId($perguntaId, $altId)) {
                    $this->alternativaModel->update(array(
                        'texto'   => $texto,
                        'correta' => $correta,
                        'ordem'   => $ordemAlt,
                    ), $altId);
                    $idsRecebidos[] = $altId;
                } else {
                    $novoId = $this->alternativaModel->create(array(
                        'pergunta_id' => $perguntaId,
                        'texto'       => $texto,
                        'correta'     => $correta,
                        'ordem'       => $ordemAlt,
                    ));
                    $idsRecebidos[] = $novoId;
                }
                $ordem++;
            }

            // Soft delete das alternativas removidas
            foreach ($idsExistentes as $existenteId) {
                if (!in_array($existenteId, $idsRecebidos, true)) {
                    $this->alternativaModel->softDelete($existenteId);
                }
            }

            $pdo->commit();
            return array('ok' => true, 'id' => $perguntaId);
        } catch (Exception $e) {
            $pdo->rollBack();
            // Sem mensagem crua: ela pode carregar o enunciado dentro do SQL.
            Logger::error('quiz.pergunta.salvar.erro', array('quiz_id' => $quizId, 'pergunta_id' => $id, 'excecao' => get_class($e)));
            throw $e;
        }
    }

    /**
     * Questao discursiva: sem alternativas, com rubrica e nota maxima.
     */
    private function salvarPerguntaDiscursiva($quizId, $id, $enunciado, array $dados, array $metadados)
    {
        $notaMaxima = $metadados['nota_maxima'];
        if ($notaMaxima !== null && $notaMaxima <= 0) {
            return array('ok' => false, 'message' => 'A nota máxima da discursiva deve ser maior que zero.');
        }
        if ($notaMaxima === null) {
            $metadados['nota_maxima'] = 10.00;
        }

        $campos = array_merge($metadados, array(
            'enunciado'   => $enunciado,
            'explicacao'  => isset($dados['explicacao']) ? trim((string) $dados['explicacao']) : null,
            'peso'        => isset($dados['peso']) && $dados['peso'] !== '' ? (float) $dados['peso'] : 1.00,
            'obrigatoria' => 1,
        ));

        if ((int) $id > 0) {
            $pergunta = $this->perguntaModel->findById((int) $id);
            if (!$pergunta || (int) $pergunta['quiz_id'] !== (int) $quizId) {
                return array('ok' => false, 'message' => 'Pergunta não pertence a este quiz.');
            }
            $campos['ordem'] = isset($dados['ordem']) ? (int) $dados['ordem'] : (int) ($pergunta['ordem'] ?? 0);
            $this->perguntaModel->update($campos, (int) $id);

            // Uma questao convertida para discursiva nao mantem alternativas.
            foreach ($this->alternativaModel->listForPergunta((int) $id) as $alternativa) {
                $this->alternativaModel->softDelete((int) $alternativa['id']);
            }

            return array('ok' => true, 'id' => (int) $id);
        }

        $campos['quiz_id'] = (int) $quizId;
        $campos['ordem']   = $this->perguntaModel->nextOrderForQuiz((int) $quizId);
        $novoId = $this->perguntaModel->create($campos);

        return array('ok' => true, 'id' => (int) $novoId);
    }

    public function excluirPergunta($perguntaId, $quizId, $usuarioId)
    {
        $pergunta = $this->perguntaModel->findById((int) $perguntaId);
        if (!$pergunta || (int) $pergunta['quiz_id'] !== (int) $quizId) {
            return array('ok' => false, 'message' => 'Pergunta não encontrada.');
        }

        $quiz = $this->quizModel->findById((int) $quizId);
        if (!$quiz) {
            return array('ok' => false, 'message' => 'Quiz não encontrado.');
        }

        // Verificar se há respostas vinculadas a esta pergunta
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM conteudo_quiz_respostas WHERE pergunta_id = :id AND deleted_at IS NULL'
        );
        $stmt->execute(array('id' => (int) $perguntaId));
        if ((int) $stmt->fetchColumn() > 0) {
            return array('ok' => false, 'message' => 'Não é possível excluir uma pergunta que já tem respostas de alunos.');
        }

        $this->perguntaModel->softDelete((int) $perguntaId);
        return array('ok' => true);
    }

    public function reordenarPerguntas($quizId, array $ordens)
    {
        $quizId = (int) $quizId;
        $quiz = $this->quizModel->findById($quizId);
        if (!$quiz) {
            return array('ok' => false, 'message' => 'Quiz não encontrado.');
        }

        $perguntas = $this->perguntaModel->listForQuiz($quizId);
        $mapa = array();
        foreach ($perguntas as $p) {
            $mapa[(int) $p['id']] = true;
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            foreach ($ordens as $perguntaId => $ordem) {
                if (isset($mapa[(int) $perguntaId])) {
                    $this->perguntaModel->updateOrdem((int) $perguntaId, (int) $ordem);
                }
            }
            $pdo->commit();
            return array('ok' => true);
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function validarQuiz($quizId)
    {
        $quiz = $this->quizModel->findById((int) $quizId);
        if (!$quiz) {
            return array('ok' => false, 'message' => 'Quiz não encontrado.', 'erros' => array());
        }

        $erros = array();
        $perguntas = $this->perguntaModel->listForQuiz((int) $quizId);

        if (count($perguntas) === 0) {
            $erros[] = 'O quiz não tem nenhuma pergunta.';
        }

        foreach ($perguntas as $pergunta) {
            $pid = (int) $pergunta['id'];
            if ((string) ($pergunta['tipo'] ?? '') === 'discursiva') {
                continue; // discursiva nao tem alternativas
            }

            $alternativas = $this->alternativaModel->listForPergunta($pid);

            if (count($alternativas) < 2) {
                $erros[] = 'Pergunta "' . mb_substr((string) $pergunta['enunciado'], 0, 40) . '..." tem menos de 2 alternativas.';
            }
            $corretas = array_filter($alternativas, function ($a) { return !empty($a['correta']); });
            if (count($corretas) !== 1) {
                $erros[] = 'Pergunta "' . mb_substr((string) $pergunta['enunciado'], 0, 40) . '..." deve ter exatamente 1 alternativa correta (tem ' . count($corretas) . ').';
            }
        }

        $composicao = $this->validarComposicao((int) $quizId);
        foreach ($composicao['erros'] as $erro) {
            $erros[] = $erro;
        }

        return array(
            'ok'         => count($erros) === 0,
            'erros'      => $erros,
            'alertas'    => $composicao['alertas'],
            'composicao' => $composicao['blocos'],
        );
    }

    /**
     * Valida se o banco de questoes comporta a composicao configurada.
     *
     * Impede publicar um simulado sem questoes suficientes; a excecao
     * consciente (conteudo_quizzes.permitir_banco_insuficiente) transforma o
     * erro em alerta, com mensagem clara.
     *
     * @return array ['ok' => bool, 'erros' => string[], 'alertas' => string[], 'blocos' => array]
     */
    public function validarComposicao($quizId)
    {
        $quizId = (int) $quizId;
        $quiz   = $this->quizModel->findById($quizId);
        if (!$quiz) {
            return array('ok' => false, 'erros' => array('Quiz não encontrado.'), 'alertas' => array(), 'blocos' => array());
        }

        if (!$this->usaBancoDeQuestoes($quiz)) {
            return array('ok' => true, 'erros' => array(), 'alertas' => array(), 'blocos' => array());
        }

        $blocos = $this->blocoModel->listForQuiz($quizId, true);
        if (count($blocos) === 0) {
            return array(
                'ok'      => false,
                'erros'   => array('O simulado está configurado por blocos, mas nenhum bloco ativo foi cadastrado.'),
                'alertas' => array(),
                'blocos'  => array(),
            );
        }

        $problemas = array();
        $alertas   = array();
        $resumo    = array();

        foreach ($blocos as $bloco) {
            $blocoId    = (int) $bloco['id'];
            $codigo     = (string) $bloco['codigo'];
            $quantidade = (int) $bloco['quantidade_sortear'];
            $pool       = $this->perguntaModel->listDisponiveisParaSorteio($quizId, $blocoId, (string) $bloco['tipo_questao']);

            $porDificuldade = array('facil' => 0, 'media' => 0, 'dificil' => 0);
            foreach ($pool as $pergunta) {
                $porDificuldade[$this->sorteioService->normalizarDificuldade($pergunta['dificuldade'] ?? null)]++;
            }

            $disponiveis  = count($pool);
            $distribuicao = $this->sorteioService->normalizarDistribuicao($bloco['distribuicao_dificuldade_json']);
            $cotas        = $distribuicao !== null
                ? $this->sorteioService->distribuirCotas($quantidade, $distribuicao)
                : array();

            $item = array(
                'bloco_id'     => $blocoId,
                'codigo'       => $codigo,
                'titulo'       => (string) $bloco['titulo'],
                'tipo_questao' => (string) $bloco['tipo_questao'],
                'quantidade'   => $quantidade,
                'disponiveis'  => $disponiveis,
                'por_dificuldade' => $porDificuldade,
                'cotas'        => $cotas,
                'suficiente'   => $disponiveis >= $quantidade,
            );

            if ($quantidade <= 0) {
                $problemas[] = 'O bloco "' . $codigo . '" não tem quantidade a sortear configurada.';
            } elseif ($disponiveis < $quantidade) {
                $problemas[] = 'O bloco "' . $codigo . '" precisa de ' . $quantidade
                    . ' questão(ões) e o banco tem apenas ' . $disponiveis . ' ativa(s).';
            }

            foreach ($cotas as $dificuldade => $cota) {
                if ($cota > 0 && $porDificuldade[$dificuldade] < $cota) {
                    $alertas[] = 'No bloco "' . $codigo . '", a distribuição pede ' . $cota
                        . ' questão(ões) de dificuldade "' . $dificuldade . '" e o banco tem '
                        . $porDificuldade[$dificuldade] . '. O sorteio completará com outras dificuldades.';
                }
            }

            $resumo[] = $item;
        }

        if (count($problemas) > 0 && !empty($quiz['permitir_banco_insuficiente'])) {
            // A exceção consciente vem primeiro: é a informação mais
            // importante para quem está publicando o simulado.
            $excecoes = array();
            foreach ($problemas as $problema) {
                $excecoes[] = 'Exceção autorizada no simulado: ' . $problema
                    . ' As tentativas serão geradas com menos questões do que o previsto.';
            }
            $alertas   = array_merge($excecoes, $alertas);
            $problemas = array();
        }

        return array(
            'ok'      => count($problemas) === 0,
            'erros'   => $problemas,
            'alertas' => $alertas,
            'blocos'  => $resumo,
        );
    }

    // ------------------------------------------------------------------
    // RELATÓRIOS ADMIN
    // ------------------------------------------------------------------

    public function listarResultadosAdmin($quizId, $cursoId, $turmaId = null, array $filtros = array())
    {
        return $this->tentativaModel->listForRelatorio((int) $quizId, (int) $cursoId, $turmaId, $filtros);
    }

    public function resumoResultadosQuizAdmin($itemId, $cursoId, $turmaId = null, array $filtros = array())
    {
        $quiz = $this->quizModel->findByItemId((int) $itemId);
        if (!$quiz) {
            return array();
        }

        $filtrosConsulta = $filtros;
        if ($turmaId) {
            $filtrosConsulta['turma_id'] = (int) $turmaId;
        }
        $tentativas = $this->tentativaModel->listForRelatorio((int) $quiz['id'], (int) $cursoId, $turmaId, $filtrosConsulta);

        // Agrupa por aluno: melhor tentativa
        $porAluno = array();
        foreach ($tentativas as $t) {
            $aid = (int) $t['aluno_id'];
            if (!isset($porAluno[$aid])) {
                $porAluno[$aid] = array(
                    'aluno_id'          => $aid,
                    'aluno_nome'        => $t['aluno_nome'],
                    'aluno_email'       => $t['aluno_email'],
                    'total_tentativas'  => 0,
                    'melhor_percentual' => 0,
                    'ultimo_percentual' => 0,
                    'aprovado'          => false,
                    'ultima_em'         => null,
                    'status'            => $t['status'],
                );
            }
            $porAluno[$aid]['total_tentativas']++;
            $pct = (float) $t['percentual'];
            if ($pct > (float) $porAluno[$aid]['melhor_percentual']) {
                $porAluno[$aid]['melhor_percentual'] = $pct;
            }
            $porAluno[$aid]['ultimo_percentual'] = $pct;
            $porAluno[$aid]['ultima_em']         = $t['enviada_em'] ?? $t['corrigida_em'];
            if (!empty($t['aprovado'])) {
                $porAluno[$aid]['aprovado'] = true;
            }
        }

        return array_values($porAluno);
    }

    /**
     * Relatório detalhado de tentativas: nota objetiva, desempenho por bloco,
     * tempo utilizado, questões sorteadas, status da discursiva e indicação da
     * melhor tentativa de cada inscrição.
     */
    public function detalharTentativasAdmin($quizId, $cursoId, $turmaId = null, array $filtros = array())
    {
        $tentativas = $this->tentativaModel->listForRelatorio((int) $quizId, (int) $cursoId, $turmaId, $filtros);

        // Melhor tentativa por inscrição: aprovada primeiro, depois maior percentual.
        $melhorPorInscricao = array();
        foreach ($tentativas as $tentativa) {
            if ((string) $tentativa['status'] === 'em_andamento') {
                continue;
            }
            $inscricaoId = (int) $tentativa['inscricao_id'];
            $atual       = isset($melhorPorInscricao[$inscricaoId]) ? $melhorPorInscricao[$inscricaoId] : null;

            if ($atual === null) {
                $melhorPorInscricao[$inscricaoId] = $tentativa;
                continue;
            }

            $melhorAprovada = !empty($atual['aprovado']);
            $estaAprovada   = !empty($tentativa['aprovado']);
            if (($estaAprovada && !$melhorAprovada)
                || ($estaAprovada === $melhorAprovada && (float) $tentativa['percentual'] > (float) $atual['percentual'])) {
                $melhorPorInscricao[$inscricaoId] = $tentativa;
            }
        }

        $detalhadas = array();
        foreach ($tentativas as $tentativa) {
            $inscricaoId = (int) $tentativa['inscricao_id'];
            $snapshot    = $this->decodeSnapshot($tentativa['quiz_snapshot_json'] ?? null);

            $tentativa['desempenho']  = (string) $tentativa['status'] === 'em_andamento'
                ? null
                : $this->desempenhoDaTentativa($tentativa);
            $tentativa['blocos_snapshot'] = $snapshot && !empty($snapshot['blocos']) ? $snapshot['blocos'] : array();
            $tentativa['tempo']       = $this->tempoDaTentativa($tentativa);
            $tentativa['e_melhor']    = isset($melhorPorInscricao[$inscricaoId])
                && (int) $melhorPorInscricao[$inscricaoId]['id'] === (int) $tentativa['id'];
            $tentativa['auditoria_sorteio'] = $this->decodeSnapshot($tentativa['sorteio_auditoria_json'] ?? null);

            $detalhadas[] = $tentativa;
        }

        return $detalhadas;
    }

    // ------------------------------------------------------------------
    // RESET ADMIN (individual, com auditoria)
    // ------------------------------------------------------------------

    public function resetarProgressoQuizAluno($itemId, $alunoId, $inscricaoId, $usuarioAdminId, $ip = null, $ua = null)
    {
        $quiz = $this->quizModel->findByItemId((int) $itemId);
        if (!$quiz) {
            return array('ok' => false, 'message' => 'Quiz não encontrado.');
        }

        $item = $this->itemModel->findById((int) $itemId);
        if (!$item) {
            return array('ok' => false, 'message' => 'Item não encontrado.');
        }

        // Não apaga tentativas - apenas redefine o progresso do conteúdo
        $this->progressoModel->upsert(array(
            'curso_evento_id'  => (int) $item['curso_evento_id'],
            'turma_id'         => null,
            'inscricao_id'     => (int) $inscricaoId,
            'aluno_id'         => (int) $alunoId,
            'modulo_id'        => (int) $item['modulo_id'],
            'item_id'          => (int) $itemId,
            'status'           => 'em_andamento',
            'percentual'       => 0.00,
            'obrigatorio'      => (int) ($item['obrigatorio'] ?? 0),
            'concluido_em'     => null,
            'ultimo_acesso_em' => date('Y-m-d H:i:s'),
        ));

        $this->auditService->record(
            'quiz.progresso.resetado',
            'conteudo_itens',
            (int) $itemId,
            array(
                'aluno_id'       => (int) $alunoId,
                'inscricao_id'   => (int) $inscricaoId,
                'admin_id'       => (int) $usuarioAdminId,
            ),
            $usuarioAdminId,
            $ip,
            $ua
        );

        Logger::info('quiz.progresso.resetado', array(
            'item_id'     => $itemId,
            'aluno_id'    => $alunoId,
            'admin_id'    => $usuarioAdminId,
        ));

        return array('ok' => true);
    }

    // ------------------------------------------------------------------
    // INTEGRAÇÃO COM ConteudoCursoService (chamado de lá)
    // ------------------------------------------------------------------

    public function salvarDetalhesQuiz($itemId, array $dados)
    {
        $itemId = (int) $itemId;
        $tentMax = isset($dados['quiz_tentativas_maximas']) && $dados['quiz_tentativas_maximas'] !== '' ? (int) $dados['quiz_tentativas_maximas'] : null;
        $pctMin  = isset($dados['quiz_percentual_minimo']) && $dados['quiz_percentual_minimo'] !== '' ? (float) $dados['quiz_percentual_minimo'] : 0.00;
        $duracao = isset($dados['quiz_duracao_minutos']) && $dados['quiz_duracao_minutos'] !== '' ? (int) $dados['quiz_duracao_minutos'] : null;

        if ($pctMin < 0 || $pctMin > 100) {
            return array('ok' => false, 'message' => 'Percentual mínimo deve ser entre 0 e 100.');
        }
        if ($duracao !== null && $duracao <= 0) {
            return array('ok' => false, 'message' => 'A duração da prova deve ser maior que zero. Deixe em branco para não ter limite de tempo.');
        }

        $modoSelecao = isset($dados['quiz_modo_selecao']) && (string) $dados['quiz_modo_selecao'] === 'blocos' ? 'blocos' : 'todas';
        $acaoExpirar = isset($dados['quiz_acao_ao_expirar']) && (string) $dados['quiz_acao_ao_expirar'] === 'encerrar_sem_envio'
            ? 'encerrar_sem_envio'
            : 'enviar_automatico';

        $limiteDiscursiva = isset($dados['quiz_limite_caracteres_discursiva']) && $dados['quiz_limite_caracteres_discursiva'] !== ''
            ? (int) $dados['quiz_limite_caracteres_discursiva']
            : null;
        if ($limiteDiscursiva !== null && $limiteDiscursiva < 100) {
            return array('ok' => false, 'message' => 'O limite de caracteres da discursiva deve ser de pelo menos 100.');
        }

        $this->quizModel->upsertByItemId($itemId, array(
            'duracao_minutos'              => $duracao,
            'modo_selecao'                 => $modoSelecao,
            'acao_ao_expirar'              => $acaoExpirar,
            'evitar_repeticao_tentativas'  => isset($dados['quiz_evitar_repeticao_tentativas']) ? (int) (bool) $dados['quiz_evitar_repeticao_tentativas'] : 1,
            'permitir_banco_insuficiente'  => !empty($dados['quiz_permitir_banco_insuficiente']) ? 1 : 0,
            'limite_caracteres_discursiva' => $limiteDiscursiva,
            'instrucoes'                    => isset($dados['quiz_instrucoes']) && $dados['quiz_instrucoes'] !== '' ? trim((string) $dados['quiz_instrucoes']) : null,
            'tentativas_maximas'            => $tentMax,
            'percentual_minimo'             => $pctMin,
            'exige_aprovacao'               => !empty($dados['quiz_exige_aprovacao']) ? 1 : 0,
            'exibir_resultado_apos_envio'   => isset($dados['quiz_exibir_resultado_apos_envio']) ? (int) (bool) $dados['quiz_exibir_resultado_apos_envio'] : 1,
            'exibir_gabarito_apos_envio'    => isset($dados['quiz_exibir_gabarito_apos_envio']) ? (int) (bool) $dados['quiz_exibir_gabarito_apos_envio'] : 1,
            'exibir_comentarios_apos_envio' => isset($dados['quiz_exibir_comentarios_apos_envio']) ? (int) (bool) $dados['quiz_exibir_comentarios_apos_envio'] : 1,
            'embaralhar_perguntas'          => !empty($dados['quiz_embaralhar_perguntas']) ? 1 : 0,
            'embaralhar_alternativas'       => !empty($dados['quiz_embaralhar_alternativas']) ? 1 : 0,
        ));

        return array('ok' => true);
    }

    public function duplicarQuiz($itemIdOrigem, $itemIdDestino)
    {
        $origem = $this->quizModel->findByItemId((int) $itemIdOrigem);
        if (!$origem) {
            return;
        }

        $novoQuizId = $this->quizModel->create(array(
            'item_id'                       => (int) $itemIdDestino,
            'instrucoes'                    => $origem['instrucoes'],
            'tentativas_maximas'            => $origem['tentativas_maximas'],
            'percentual_minimo'             => $origem['percentual_minimo'],
            'exige_aprovacao'               => (int) $origem['exige_aprovacao'],
            'exibir_resultado_apos_envio'   => (int) $origem['exibir_resultado_apos_envio'],
            'exibir_gabarito_apos_envio'    => (int) $origem['exibir_gabarito_apos_envio'],
            'exibir_comentarios_apos_envio' => (int) $origem['exibir_comentarios_apos_envio'],
            'embaralhar_perguntas'          => (int) $origem['embaralhar_perguntas'],
            'embaralhar_alternativas'       => (int) $origem['embaralhar_alternativas'],
            'duracao_minutos'               => $origem['duracao_minutos'],
            'modo_selecao'                  => $origem['modo_selecao'],
            'acao_ao_expirar'               => $origem['acao_ao_expirar'],
            'evitar_repeticao_tentativas'   => (int) $origem['evitar_repeticao_tentativas'],
            'permitir_banco_insuficiente'   => (int) $origem['permitir_banco_insuficiente'],
            'limite_caracteres_discursiva'  => $origem['limite_caracteres_discursiva'],
        ));

        // Blocos primeiro, para religar as questoes copiadas.
        $mapaBlocos = array();
        foreach ($this->blocoModel->listForQuiz((int) $origem['id']) as $bloco) {
            $mapaBlocos[(int) $bloco['id']] = $this->blocoModel->create(array(
                'quiz_id'                       => $novoQuizId,
                'codigo'                        => $bloco['codigo'],
                'titulo'                        => $bloco['titulo'],
                'descricao'                     => $bloco['descricao'],
                'tipo_questao'                  => $bloco['tipo_questao'],
                'quantidade_sortear'            => (int) $bloco['quantidade_sortear'],
                'distribuicao_dificuldade_json' => $bloco['distribuicao_dificuldade_json'],
                'conta_para_percentual'         => (int) $bloco['conta_para_percentual'],
                'obrigatorio_para_envio'        => (int) $bloco['obrigatorio_para_envio'],
                'ordem'                         => (int) $bloco['ordem'],
                'status'                        => $bloco['status'],
            ));
        }

        $perguntas = $this->perguntaModel->listForQuiz((int) $origem['id']);
        foreach ($perguntas as $pergunta) {
            $blocoOrigem = !empty($pergunta['bloco_id']) ? (int) $pergunta['bloco_id'] : 0;

            $novaPerguntaId = $this->perguntaModel->create(array(
                'quiz_id'    => $novoQuizId,
                'bloco_id'   => isset($mapaBlocos[$blocoOrigem]) ? $mapaBlocos[$blocoOrigem] : null,
                'enunciado'  => $pergunta['enunciado'],
                'tipo'       => $pergunta['tipo'],
                'dificuldade'=> $pergunta['dificuldade'],
                'tema'       => $pergunta['tema'],
                'status'     => $pergunta['status'],
                'referencia' => $pergunta['referencia'],
                'explicacao' => $pergunta['explicacao'],
                'rubrica'    => $pergunta['rubrica'],
                'nota_maxima'=> $pergunta['nota_maxima'],
                'peso'       => $pergunta['peso'],
                'obrigatoria'=> (int) $pergunta['obrigatoria'],
                'ordem'      => (int) $pergunta['ordem'],
            ));

            $alternativas = $this->alternativaModel->listForPergunta((int) $pergunta['id']);
            foreach ($alternativas as $alt) {
                $this->alternativaModel->create(array(
                    'pergunta_id' => $novaPerguntaId,
                    'texto'       => $alt['texto'],
                    'correta'     => (int) $alt['correta'],
                    'ordem'       => (int) $alt['ordem'],
                ));
            }
        }
    }

    // ------------------------------------------------------------------
    // MÉTODOS PRIVADOS
    // ------------------------------------------------------------------

    private function validarTentativaDoAluno($tentativaId, $alunoId)
    {
        $tentativa = $this->tentativaModel->findById((int) $tentativaId);
        if (!$tentativa) {
            return array('error' => 'Tentativa não encontrada.');
        }
        if ((int) $tentativa['aluno_id'] !== (int) $alunoId) {
            return array('error' => 'Acesso negado a esta tentativa.');
        }
        return $tentativa;
    }

    /**
     * Grava respostas objetivas e discursivas validando tudo contra o snapshot
     * da tentativa (pergunta e alternativa precisam pertencer ao sorteio).
     */
    private function gravarRespostas(array $tentativa, array $quiz, array $respostas, array $discursivas, bool $validarPertenencia)
    {
        $tentativaId   = (int) $tentativa['id'];
        $snapshot      = $this->decodeSnapshot($tentativa['quiz_snapshot_json'] ?? null);
        $perguntasMapa = $this->mapaPerguntasDoSnapshot($tentativa, $snapshot);

        foreach ($respostas as $perguntaId => $alternativaId) {
            $perguntaId    = (int) $perguntaId;
            $alternativaId = $alternativaId !== '' && $alternativaId !== null ? (int) $alternativaId : null;

            if (!isset($perguntasMapa[$perguntaId])) {
                if ($validarPertenencia) {
                    throw new Exception('Questão fora do conjunto sorteado para esta tentativa.');
                }
                continue;
            }

            $pergunta = $perguntasMapa[$perguntaId];
            if ((string) ($pergunta['tipo'] ?? 'multipla_escolha') === 'discursiva') {
                continue; // discursiva chega em $discursivas
            }

            // A alternativa precisa pertencer ao snapshot daquela pergunta.
            if ($alternativaId !== null && !$this->alternativaPertenceAoSnapshot($pergunta, $alternativaId)) {
                if ($validarPertenencia) {
                    throw new Exception('Alternativa inválida para a questão informada.');
                }
                continue;
            }

            $this->respostaModel->upsert(array(
                'tentativa_id'          => $tentativaId,
                'pergunta_id'           => $perguntaId,
                'bloco_id'              => !empty($pergunta['bloco_id']) ? (int) $pergunta['bloco_id'] : null,
                'tipo'                  => 'multipla_escolha',
                'alternativa_id'        => $alternativaId,
                'conta_para_percentual' => !empty($pergunta['conta_para_percentual']) ? 1 : 0,
                'correta'               => 0, // corrigido depois, no servidor
                'pontos_obtidos'        => 0,
            ));
        }

        $limite = $this->limiteCaracteresDiscursiva($quiz);
        foreach ($discursivas as $perguntaId => $texto) {
            $perguntaId = (int) $perguntaId;
            if (!isset($perguntasMapa[$perguntaId])) {
                if ($validarPertenencia) {
                    throw new Exception('Questão fora do conjunto sorteado para esta tentativa.');
                }
                continue;
            }

            $pergunta = $perguntasMapa[$perguntaId];
            if ((string) ($pergunta['tipo'] ?? '') !== 'discursiva') {
                continue;
            }

            $this->respostaModel->upsert(array(
                'tentativa_id'          => $tentativaId,
                'pergunta_id'           => $perguntaId,
                'bloco_id'              => !empty($pergunta['bloco_id']) ? (int) $pergunta['bloco_id'] : null,
                'tipo'                  => 'discursiva',
                'alternativa_id'        => null,
                'texto_resposta'        => $this->sanitizarTextoDiscursivo($texto, $limite),
                // A discursiva nunca entra no percentual de aprovacao.
                'conta_para_percentual' => 0,
                'correta'               => 0,
                'pontos_obtidos'        => 0,
            ));
        }
    }

    /**
     * Sanitiza e limita a resposta discursiva conforme o padrao do projeto
     * (texto puro, sem HTML, com limite de caracteres).
     */
    private function sanitizarTextoDiscursivo($texto, $limite)
    {
        $texto = trim(strip_tags((string) $texto));
        if ($texto === '') {
            return null;
        }
        $tamanho = function_exists('mb_strlen') ? mb_strlen($texto) : strlen($texto);
        if ($tamanho > $limite) {
            $texto = function_exists('mb_substr') ? mb_substr($texto, 0, $limite) : substr($texto, 0, $limite);
        }
        return $texto;
    }

    private function limiteCaracteresDiscursiva(array $quiz)
    {
        $limite = isset($quiz['limite_caracteres_discursiva']) && $quiz['limite_caracteres_discursiva'] !== null
            ? (int) $quiz['limite_caracteres_discursiva']
            : 0;
        return $limite > 0 ? $limite : self::LIMITE_DISCURSIVA_PADRAO;
    }

    private function gravarMarcacoesRevisao(array $tentativa, array $revisoes)
    {
        if (count($revisoes) === 0) {
            return;
        }

        $tentativaId   = (int) $tentativa['id'];
        $snapshot      = $this->decodeSnapshot($tentativa['quiz_snapshot_json'] ?? null);
        $perguntasMapa = $this->mapaPerguntasDoSnapshot($tentativa, $snapshot);

        foreach ($revisoes as $perguntaId => $marcada) {
            $perguntaId = (int) $perguntaId;
            if (!isset($perguntasMapa[$perguntaId])) {
                continue;
            }
            $pergunta  = $perguntasMapa[$perguntaId];
            $atualizou = $this->respostaModel->marcarRevisao($tentativaId, $perguntaId, $marcada);
            if ($atualizou === 0) {
                // Ainda nao ha resposta: cria o registro apenas com a marcacao.
                $this->respostaModel->upsert(array(
                    'tentativa_id'          => $tentativaId,
                    'pergunta_id'           => $perguntaId,
                    'bloco_id'              => !empty($pergunta['bloco_id']) ? (int) $pergunta['bloco_id'] : null,
                    'tipo'                  => (string) ($pergunta['tipo'] ?? 'multipla_escolha'),
                    'marcada_para_revisao'  => !empty($marcada) ? 1 : 0,
                    'conta_para_percentual' => !empty($pergunta['conta_para_percentual']) ? 1 : 0,
                ));
            }
        }
    }

    /**
     * Valida a obrigatoriedade somente das questoes presentes no snapshot.
     *
     * @return string|null Mensagem de erro, ou NULL quando esta tudo respondido.
     */
    private function validarObrigatoriasDoSnapshot($tentativaId, array $perguntas)
    {
        $faltamObjetivas  = 0;
        $faltamDiscursivas = 0;

        foreach ($perguntas as $pergunta) {
            if (empty($pergunta['obrigatoria'])) {
                continue;
            }

            $perguntaId = (int) $pergunta['id'];
            $resposta   = $this->respostaModel->findByTentativaEPergunta($tentativaId, $perguntaId);
            $discursiva = (string) ($pergunta['tipo'] ?? 'multipla_escolha') === 'discursiva';

            if ($discursiva) {
                $texto   = $resposta ? trim((string) ($resposta['texto_resposta'] ?? '')) : '';
                $tamanho = function_exists('mb_strlen') ? mb_strlen($texto) : strlen($texto);
                if ($tamanho < self::MINIMO_DISCURSIVA) {
                    $faltamDiscursivas++;
                }
                continue;
            }

            if (!$resposta || (empty($resposta['alternativa_id']) && empty($resposta['resposta_json']))) {
                $faltamObjetivas++;
            }
        }

        if ($faltamObjetivas > 0 && $faltamDiscursivas > 0) {
            return 'Responda todas as questões objetivas e a questão discursiva antes de enviar.';
        }
        if ($faltamObjetivas > 0) {
            return 'Responda todas as questões objetivas obrigatórias antes de enviar. Faltam ' . $faltamObjetivas . '.';
        }
        if ($faltamDiscursivas > 0) {
            return 'A questão discursiva é obrigatória para o envio da prova.';
        }

        return null;
    }

    /**
     * Correcao no servidor. O percentual usa APENAS as questoes objetivas
     * sorteadas no snapshot; a discursiva e registrada para correcao manual e
     * nao influencia a aprovacao.
     */
    private function calcularResultado($tentativaId, array $quiz, array $perguntas)
    {
        $respostas = $this->respostaModel->listForTentativa($tentativaId);
        $respostasPorPergunta = array();
        foreach ($respostas as $r) {
            $respostasPorPergunta[(int) $r['pergunta_id']] = $r;
        }

        $totalPerguntas   = 0;
        $totalObjetivas   = 0;
        $totalDiscursivas = 0;
        $totalAcertos     = 0;
        $pontosObtidos    = 0.0;
        $pontosTotais     = 0.0;
        $discursivaStatus = 'nao_aplicavel';

        foreach ($perguntas as $pergunta) {
            $pid  = (int) $pergunta['id'];
            $peso = (float) ($pergunta['peso'] ?? 1.0);
            $totalPerguntas++;

            $resposta = isset($respostasPorPergunta[$pid]) ? $respostasPorPergunta[$pid] : null;

            if ((string) ($pergunta['tipo'] ?? 'multipla_escolha') === 'discursiva') {
                $totalDiscursivas++;
                $discursivaStatus = 'pendente';
                $this->prepararCorrecaoDiscursiva($tentativaId, $pergunta, $resposta);
                continue;
            }

            if (!$this->perguntaEObjetiva($pergunta)) {
                continue;
            }

            $totalObjetivas++;
            $pontosTotais += $peso;

            $corretaId = $this->alternativaCorretaDoSnapshot($pergunta);
            $altRespondida = $resposta ? (int) ($resposta['alternativa_id'] ?? 0) : 0;
            $acertou   = $corretaId !== null && $altRespondida === $corretaId;

            if ($acertou) {
                $totalAcertos++;
                $pontosObtidos += $peso;
            }

            $perguntaSnapshot = array(
                'id'           => $pid,
                'enunciado'    => $pergunta['enunciado'] ?? '',
                'explicacao'   => $pergunta['explicacao'] ?? null,
                'correta_id'   => $corretaId,
                'peso'         => $peso,
                'bloco_codigo' => $pergunta['bloco_codigo'] ?? null,
                'tema'         => $pergunta['tema'] ?? null,
            );

            $this->respostaModel->upsert(array(
                'tentativa_id'          => $tentativaId,
                'pergunta_id'           => $pid,
                'bloco_id'              => !empty($pergunta['bloco_id']) ? (int) $pergunta['bloco_id'] : null,
                'tipo'                  => 'multipla_escolha',
                'alternativa_id'        => $altRespondida > 0 ? $altRespondida : null,
                'conta_para_percentual' => 1,
                'correta'               => $acertou ? 1 : 0,
                'pontos_obtidos'        => $acertou ? $peso : 0,
                'pergunta_snapshot_json'=> $perguntaSnapshot,
            ));
        }

        $percentual = $pontosTotais > 0 ? round(($pontosObtidos / $pontosTotais) * 100, 2) : 0.0;

        $pctMinimo = (float) ($quiz['percentual_minimo'] ?? 0);
        $exige     = !empty($quiz['exige_aprovacao']);
        $aprovado  = $exige ? ($percentual >= $pctMinimo) : true;

        return array(
            'total_perguntas'   => $totalPerguntas,
            'total_objetivas'   => $totalObjetivas,
            'total_discursivas' => $totalDiscursivas,
            'total_acertos'     => $totalAcertos,
            'pontos_obtidos'    => round($pontosObtidos, 2),
            'pontos_totais'     => round($pontosTotais, 2),
            'percentual'        => $percentual,
            'aprovado'          => $aprovado,
            'discursiva_status' => $discursivaStatus,
        );
    }

    /**
     * Cria (uma unica vez) a correcao pendente da discursiva enviada.
     */
    private function prepararCorrecaoDiscursiva($tentativaId, array $pergunta, $resposta)
    {
        $perguntaId = (int) $pergunta['id'];

        if (!$resposta) {
            $this->respostaModel->upsert(array(
                'tentativa_id'          => $tentativaId,
                'pergunta_id'           => $perguntaId,
                'bloco_id'              => !empty($pergunta['bloco_id']) ? (int) $pergunta['bloco_id'] : null,
                'tipo'                  => 'discursiva',
                'conta_para_percentual' => 0,
                'correta'               => 0,
                'pontos_obtidos'        => 0,
            ));
            $resposta = $this->respostaModel->findByTentativaEPergunta($tentativaId, $perguntaId);
        }

        $notaMaxima = isset($pergunta['nota_maxima']) && $pergunta['nota_maxima'] !== null
            ? (float) $pergunta['nota_maxima']
            : 10.00;

        $this->correcaoModel->garantirPendente(array(
            'tentativa_id' => $tentativaId,
            'resposta_id'  => $resposta ? (int) $resposta['id'] : null,
            'pergunta_id'  => $perguntaId,
            'bloco_id'     => !empty($pergunta['bloco_id']) ? (int) $pergunta['bloco_id'] : null,
            'nota_maxima'  => $notaMaxima > 0 ? $notaMaxima : 10.00,
            'rubrica'      => isset($pergunta['rubrica']) ? $pergunta['rubrica'] : null,
            'origem'       => 'manual',
        ));
    }

    private function atualizarProgressoConteudo(array $tentativa, array $quiz, array $resultado, array $item)
    {
        $inscricaoId = (int) $tentativa['inscricao_id'];
        $alunoId     = (int) $tentativa['aluno_id'];
        $itemId      = (int) $item['id'];
        $moduloId    = (int) $item['modulo_id'];
        $cursoId     = (int) $item['curso_evento_id'];
        $turmaId     = isset($tentativa['turma_id']) ? (int) $tentativa['turma_id'] : null;
        $obrigatorio = (int) ($item['obrigatorio'] ?? 0);
        $exige       = !empty($quiz['exige_aprovacao']);
        $aprovado    = !empty($resultado['aprovado']);

        if ($exige && !$aprovado) {
            // A melhor tentativa manda: se o aluno ja foi aprovado antes, uma
            // tentativa posterior com nota menor nao reabre o item.
            $jaAprovado = $this->tentativaModel->existeAprovada((int) $quiz['id'], $inscricaoId);
            $status     = $jaAprovado ? 'concluido' : 'reprovado';
        } else {
            $status = 'concluido';
        }

        $this->progressoModel->upsert(array(
            'curso_evento_id'  => $cursoId,
            'turma_id'         => $turmaId,
            'inscricao_id'     => $inscricaoId,
            'aluno_id'         => $alunoId,
            'modulo_id'        => $moduloId,
            'item_id'          => $itemId,
            'status'           => $status,
            'percentual'       => $status === 'concluido' ? 100.00 : (float) $resultado['percentual'],
            'obrigatorio'      => $obrigatorio,
            'concluido_em'     => $status === 'concluido' ? date('Y-m-d H:i:s') : null,
            'ultimo_acesso_em' => date('Y-m-d H:i:s'),
        ));

        // Recalcular progresso geral da inscrição
        if ($status === 'concluido') {
            $conteudoService = new ConteudoCursoService();
            $conteudoService->recalcularProgressoInscricao($inscricaoId);
        }
    }

    /**
     * Monta o snapshot da tentativa — fonte de verdade da prova.
     *
     * Guarda exatamente as questoes sorteadas, a ordem apresentada, as
     * alternativas ja embaralhadas, os blocos, o tempo e a auditoria do
     * sorteio. O sorteio ocorre somente aqui, na criacao da tentativa.
     *
     * @return array ['snapshot' => array]
     */
    private function buildSnapshot(array $quiz, array $contexto = array())
    {
        $base = array(
            'versao'                  => 2,
            'quiz_id'                 => (int) $quiz['id'],
            'modo_selecao'            => $this->usaBancoDeQuestoes($quiz) ? 'blocos' : 'todas',
            'tentativas_maximas'      => $quiz['tentativas_maximas'],
            'percentual_minimo'       => (float) $quiz['percentual_minimo'],
            'exige_aprovacao'         => (int) $quiz['exige_aprovacao'],
            'duracao_minutos'         => isset($quiz['duracao_minutos']) && $quiz['duracao_minutos'] !== null ? (int) $quiz['duracao_minutos'] : null,
            'acao_ao_expirar'         => (string) ($quiz['acao_ao_expirar'] ?? 'enviar_automatico'),
            'limite_caracteres_discursiva' => $this->limiteCaracteresDiscursiva($quiz),
            'embaralhar_perguntas'    => !empty($quiz['embaralhar_perguntas']) ? 1 : 0,
            'embaralhar_alternativas' => !empty($quiz['embaralhar_alternativas']) ? 1 : 0,
            'numero_tentativa'        => (int) ($contexto['numero_tentativa'] ?? 1),
            'gerado_em'               => date('Y-m-d H:i:s'),
            'blocos'                  => array(),
            'perguntas'               => array(),
        );

        $snapshot = $base['modo_selecao'] === 'blocos'
            ? $this->montarSnapshotPorBlocos($quiz, $base, $contexto)
            : $this->montarSnapshotLegado($quiz, $base);

        return array('snapshot' => $snapshot);
    }

    /**
     * Snapshot do comportamento legado: todas as perguntas do quiz.
     */
    private function montarSnapshotLegado(array $quiz, array $snapshot)
    {
        $perguntas = $this->perguntaModel->listForQuiz((int) $quiz['id']);
        if (!empty($quiz['embaralhar_perguntas']) && count($perguntas) > 1) {
            $perguntas = $this->randomizer->embaralhar($perguntas);
        }

        $ordem = 1;
        foreach ($perguntas as $pergunta) {
            $snapshot['perguntas'][] = $this->montarPerguntaDoSnapshot($quiz, $pergunta, null, $ordem++, false);
        }

        return $this->finalizarSnapshot($snapshot, array(), false);
    }

    /**
     * Snapshot com banco de questoes: sorteia a quantidade de cada bloco.
     */
    private function montarSnapshotPorBlocos(array $quiz, array $snapshot, array $contexto)
    {
        $quizId      = (int) $quiz['id'];
        $inscricaoId = (int) ($contexto['inscricao_id'] ?? 0);
        $blocos      = $this->blocoModel->listForQuiz($quizId, true);

        if (count($blocos) === 0) {
            // Sem blocos ativos, mantem o comportamento legado.
            return $this->montarSnapshotLegado($quiz, $snapshot);
        }

        $configuracao = array();
        $pools        = array();
        foreach ($blocos as $bloco) {
            $blocoId = (int) $bloco['id'];
            $configuracao[] = array(
                'id'                       => $blocoId,
                'codigo'                   => (string) $bloco['codigo'],
                'titulo'                   => (string) $bloco['titulo'],
                'tipo_questao'             => (string) $bloco['tipo_questao'],
                'quantidade_sortear'       => (int) $bloco['quantidade_sortear'],
                'distribuicao_dificuldade' => $bloco['distribuicao_dificuldade_json'],
                'conta_para_percentual'    => (int) $bloco['conta_para_percentual'],
                'obrigatorio_para_envio'   => (int) $bloco['obrigatorio_para_envio'],
                'ordem'                    => (int) $bloco['ordem'],
            );
            $pools[$blocoId] = $this->perguntaModel->listDisponiveisParaSorteio(
                $quizId,
                $blocoId,
                (string) $bloco['tipo_questao']
            );
        }

        $jaUtilizados = !empty($quiz['evitar_repeticao_tentativas']) && $inscricaoId > 0
            ? $this->itemUtilizadoModel->listPerguntaIds($quizId, $inscricaoId)
            : array();

        $sorteio = $this->sorteioService->sortear($configuracao, $pools, $jaUtilizados, array(
            'embaralhar_perguntas' => !empty($quiz['embaralhar_perguntas']),
        ));

        $snapshot['blocos'] = $sorteio['blocos'];

        foreach ($sorteio['perguntas'] as $item) {
            $snapshot['perguntas'][] = $this->montarPerguntaDoSnapshot(
                $quiz,
                $item['pergunta'],
                $item['bloco'],
                (int) $item['ordem_apresentacao'],
                !empty($item['reutilizada'])
            );
        }

        return $this->finalizarSnapshot($snapshot, $sorteio['auditoria'], !empty($sorteio['com_repeticao']));
    }

    /**
     * Normaliza uma questao dentro do snapshot, ja com as alternativas na
     * ordem em que serao apresentadas ao aluno.
     */
    private function montarPerguntaDoSnapshot(array $quiz, array $pergunta, $bloco, $ordemApresentacao, $reutilizada)
    {
        $perguntaId = (int) $pergunta['id'];
        $tipo       = (string) ($pergunta['tipo'] ?? 'multipla_escolha');
        $discursiva = $tipo === 'discursiva';

        $alternativas = array();
        if (!$discursiva) {
            $lista = $this->alternativaModel->listForPergunta($perguntaId);
            if (!empty($quiz['embaralhar_alternativas']) && count($lista) > 1) {
                $lista = $this->randomizer->embaralhar($lista);
            }
            foreach ($lista as $alternativa) {
                $alternativas[] = array(
                    'id'      => (int) $alternativa['id'],
                    'texto'   => $alternativa['texto'],
                    'correta' => (int) $alternativa['correta'],
                    'ordem'   => (int) $alternativa['ordem'],
                );
            }
        }

        $contaPercentual = $discursiva ? 0 : 1;
        $obrigatoria     = (int) ($pergunta['obrigatoria'] ?? 1);
        if (is_array($bloco)) {
            $contaPercentual = $discursiva ? 0 : (!empty($bloco['conta_para_percentual']) ? 1 : 0);
            if (!empty($bloco['obrigatorio_para_envio'])) {
                $obrigatoria = 1;
            }
        }

        return array(
            'id'                    => $perguntaId,
            'bloco_id'              => is_array($bloco) ? (int) $bloco['id'] : (!empty($pergunta['bloco_id']) ? (int) $pergunta['bloco_id'] : null),
            'bloco_codigo'          => is_array($bloco) ? (string) $bloco['codigo'] : null,
            'bloco_titulo'          => is_array($bloco) ? (string) $bloco['titulo'] : null,
            'enunciado'             => $pergunta['enunciado'],
            'tipo'                  => $tipo,
            'dificuldade'           => (string) ($pergunta['dificuldade'] ?? 'media'),
            'tema'                  => isset($pergunta['tema']) ? $pergunta['tema'] : null,
            'explicacao'            => isset($pergunta['explicacao']) ? $pergunta['explicacao'] : null,
            'rubrica'               => isset($pergunta['rubrica']) ? $pergunta['rubrica'] : null,
            'nota_maxima'           => isset($pergunta['nota_maxima']) && $pergunta['nota_maxima'] !== null ? (float) $pergunta['nota_maxima'] : null,
            'peso'                  => (float) ($pergunta['peso'] ?? 1),
            'obrigatoria'           => $obrigatoria,
            'conta_para_percentual' => $contaPercentual,
            'ordem'                 => (int) ($pergunta['ordem'] ?? 0),
            'ordem_apresentacao'    => (int) $ordemApresentacao,
            'reutilizada'           => $reutilizada ? 1 : 0,
            'alternativas'          => $alternativas,
        );
    }

    private function finalizarSnapshot(array $snapshot, array $auditoria, $comRepeticao)
    {
        $objetivas   = 0;
        $discursivas = 0;
        foreach ($snapshot['perguntas'] as $pergunta) {
            if ((string) $pergunta['tipo'] === 'discursiva') {
                $discursivas++;
            } elseif (!empty($pergunta['conta_para_percentual'])) {
                $objetivas++;
            }
        }

        $snapshot['sorteio'] = array(
            'gerado_em'         => $snapshot['gerado_em'],
            'com_repeticao'     => $comRepeticao ? 1 : 0,
            'ocorrencias'       => array_values($auditoria),
            'total_perguntas'   => count($snapshot['perguntas']),
            'total_objetivas'   => $objetivas,
            'total_discursivas' => $discursivas,
        );

        return $snapshot;
    }

    /**
     * Registra os itens sorteados para evitar repeticao nas proximas tentativas.
     */
    private function registrarItensUtilizados($tentativaId, $quizId, $inscricaoId, $alunoId, $numero, array $snapshot)
    {
        $itens = array();
        foreach ($snapshot['perguntas'] as $pergunta) {
            $itens[] = array(
                'pergunta_id' => (int) $pergunta['id'],
                'bloco_id'    => !empty($pergunta['bloco_id']) ? (int) $pergunta['bloco_id'] : null,
                'reutilizada' => !empty($pergunta['reutilizada']) ? 1 : 0,
            );
        }

        $this->itemUtilizadoModel->registrarLote(array(
            'quiz_id'          => (int) $quizId,
            'inscricao_id'     => (int) $inscricaoId,
            'aluno_id'         => (int) $alunoId,
            'tentativa_id'     => (int) $tentativaId,
            'numero_tentativa' => (int) $numero,
        ), $itens);
    }

    private function usaBancoDeQuestoes(array $quiz)
    {
        return (string) ($quiz['modo_selecao'] ?? 'todas') === 'blocos';
    }

    /**
     * Questoes da tentativa a partir do snapshot. Se o snapshot for antigo ou
     * estiver ausente, cai para as perguntas do quiz (quizzes legados).
     */
    private function perguntasDoSnapshot(array $tentativa, ?array $snapshot = null)
    {
        if ($snapshot === null) {
            $snapshot = $this->decodeSnapshot($tentativa['quiz_snapshot_json'] ?? null);
        }
        if (!empty($snapshot['perguntas']) && is_array($snapshot['perguntas'])) {
            return $snapshot['perguntas'];
        }

        // Compatibilidade com tentativas legadas sem snapshot gravado.
        $perguntas = array();
        $ordem     = 1;
        foreach ($this->perguntaModel->listForQuiz((int) $tentativa['quiz_id']) as $pergunta) {
            $alternativas = array();
            foreach ($this->alternativaModel->listForPergunta((int) $pergunta['id']) as $alternativa) {
                $alternativas[] = array(
                    'id'      => (int) $alternativa['id'],
                    'texto'   => $alternativa['texto'],
                    'correta' => (int) $alternativa['correta'],
                    'ordem'   => (int) $alternativa['ordem'],
                );
            }
            $perguntas[] = array(
                'id'                    => (int) $pergunta['id'],
                'bloco_id'              => !empty($pergunta['bloco_id']) ? (int) $pergunta['bloco_id'] : null,
                'bloco_codigo'          => null,
                'bloco_titulo'          => null,
                'enunciado'             => $pergunta['enunciado'],
                'tipo'                  => (string) ($pergunta['tipo'] ?? 'multipla_escolha'),
                'dificuldade'           => (string) ($pergunta['dificuldade'] ?? 'media'),
                'tema'                  => isset($pergunta['tema']) ? $pergunta['tema'] : null,
                'explicacao'            => $pergunta['explicacao'] ?? null,
                'rubrica'               => $pergunta['rubrica'] ?? null,
                'nota_maxima'           => isset($pergunta['nota_maxima']) && $pergunta['nota_maxima'] !== null ? (float) $pergunta['nota_maxima'] : null,
                'peso'                  => (float) ($pergunta['peso'] ?? 1),
                'obrigatoria'           => (int) ($pergunta['obrigatoria'] ?? 1),
                'conta_para_percentual' => (string) ($pergunta['tipo'] ?? '') === 'discursiva' ? 0 : 1,
                'ordem'                 => (int) ($pergunta['ordem'] ?? 0),
                'ordem_apresentacao'    => $ordem++,
                'reutilizada'           => 0,
                'alternativas'          => $alternativas,
            );
        }
        return $perguntas;
    }

    private function mapaPerguntasDoSnapshot(array $tentativa, ?array $snapshot = null)
    {
        $mapa = array();
        foreach ($this->perguntasDoSnapshot($tentativa, $snapshot) as $pergunta) {
            $mapa[(int) $pergunta['id']] = $pergunta;
        }
        return $mapa;
    }

    private function perguntaEObjetiva(array $pergunta)
    {
        if ((string) ($pergunta['tipo'] ?? 'multipla_escolha') === 'discursiva') {
            return false;
        }
        return !isset($pergunta['conta_para_percentual']) || !empty($pergunta['conta_para_percentual']);
    }

    private function alternativaPertenceAoSnapshot(array $pergunta, $alternativaId)
    {
        $alternativaId = (int) $alternativaId;
        if (empty($pergunta['alternativas']) || !is_array($pergunta['alternativas'])) {
            // Snapshot legado sem alternativas: valida contra o banco.
            return (bool) $this->alternativaModel->findByPerguntaAndId((int) $pergunta['id'], $alternativaId);
        }
        foreach ($pergunta['alternativas'] as $alternativa) {
            if ((int) ($alternativa['id'] ?? 0) === $alternativaId) {
                return true;
            }
        }
        return false;
    }

    private function alternativaCorretaDoSnapshot(array $pergunta)
    {
        if (!empty($pergunta['alternativas']) && is_array($pergunta['alternativas'])) {
            foreach ($pergunta['alternativas'] as $alternativa) {
                if (array_key_exists('correta', $alternativa) && !empty($alternativa['correta'])) {
                    return (int) $alternativa['id'];
                }
            }
        }
        foreach ($this->alternativaModel->listForPergunta((int) $pergunta['id']) as $alternativa) {
            if (!empty($alternativa['correta'])) {
                return (int) $alternativa['id'];
            }
        }
        return null;
    }

    private function tentativaExpirada(array $tentativa)
    {
        if (empty($tentativa['expira_em'])) {
            return false;
        }
        $expira = strtotime((string) $tentativa['expira_em']);
        return $expira !== false && $expira <= time();
    }

    private function calcularTempoUtilizado(array $tentativa)
    {
        if (empty($tentativa['iniciada_em'])) {
            return null;
        }
        $inicio = strtotime((string) $tentativa['iniciada_em']);
        if ($inicio === false) {
            return null;
        }

        $fim = time();
        if (!empty($tentativa['expira_em'])) {
            $expira = strtotime((string) $tentativa['expira_em']);
            if ($expira !== false && $fim > $expira) {
                $fim = $expira; // nao contabiliza tempo alem do prazo
            }
        }

        return max(0, $fim - $inicio);
    }

    private function decodeSnapshot($snapshot)
    {
        if (is_array($snapshot)) {
            return $snapshot;
        }
        if (!is_string($snapshot) || trim($snapshot) === '') {
            return null;
        }

        $decoded = json_decode($snapshot, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function montarPerguntasParaAluno(array $quiz, ?array $snapshot = null, ?array $tentativa = null, array $respostasPorPergunta = array())
    {
        $perguntasBase = array();
        if (!empty($snapshot['perguntas']) && is_array($snapshot['perguntas'])) {
            $perguntasBase = $snapshot['perguntas'];
        } else {
            $perguntasBase = $this->perguntaModel->listForQuiz((int) $quiz['id']);
        }

        // O gabarito so aparece depois do envio e apenas se o quiz permitir.
        $mostrarCorretas = !empty($tentativa) && in_array((string) ($tentativa['status'] ?? ''), array('corrigida', 'enviada'), true) && !empty($quiz['exibir_gabarito_apos_envio']);

        foreach ($perguntasBase as &$pergunta) {
            $perguntaId = (int) ($pergunta['id'] ?? 0);
            $discursiva = (string) ($pergunta['tipo'] ?? 'multipla_escolha') === 'discursiva';

            if (!isset($pergunta['alternativas']) || !is_array($pergunta['alternativas'])) {
                $pergunta['alternativas'] = $discursiva
                    ? array()
                    : $this->alternativaModel->listForPergunta($perguntaId);
            }

            foreach ($pergunta['alternativas'] as &$alt) {
                if (empty($mostrarCorretas)) {
                    unset($alt['correta']);
                }
            }
            unset($alt);

            // A rubrica de correcao nunca vai para a tela do aluno.
            unset($pergunta['rubrica']);

            if ($tentativa) {
                $resposta = isset($respostasPorPergunta[$perguntaId]) ? $respostasPorPergunta[$perguntaId] : null;
                $pergunta['resposta']   = $resposta;
                $pergunta['texto_resposta'] = $resposta ? (string) ($resposta['texto_resposta'] ?? '') : '';
                $pergunta['marcada_para_revisao'] = $resposta ? !empty($resposta['marcada_para_revisao']) : false;
                $pergunta['respondida'] = $discursiva
                    ? ($resposta !== null && trim((string) ($resposta['texto_resposta'] ?? '')) !== '')
                    : ($resposta !== null && (int) ($resposta['alternativa_id'] ?? 0) > 0);
                $pergunta['alternativa_id_respondida'] = $resposta ? (int) ($resposta['alternativa_id'] ?? 0) : 0;
                if (empty($quiz['exibir_comentarios_apos_envio'])) {
                    $pergunta['explicacao'] = null;
                }
            }
        }
        unset($pergunta);

        return $perguntasBase;
    }

    private function resultadoPersistidoDaTentativa(array $tentativa)
    {
        return array(
            'total_perguntas' => (int) ($tentativa['total_perguntas'] ?? 0),
            'total_acertos'   => (int) ($tentativa['total_acertos'] ?? 0),
            'pontos_obtidos'  => (float) ($tentativa['pontos_obtidos'] ?? 0),
            'pontos_totais'   => (float) ($tentativa['pontos_totais'] ?? 0),
            'percentual'      => (float) ($tentativa['percentual'] ?? 0),
            'aprovado'        => isset($tentativa['aprovado']) ? (bool) $tentativa['aprovado'] : null,
        );
    }
}
