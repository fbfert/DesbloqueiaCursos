<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Models\ConteudoItem;
use App\Models\ConteudoModulo;
use App\Models\ConteudoProgressoAluno;
use App\Models\ConteudoQuiz;
use App\Models\ConteudoQuizAlternativa;
use App\Models\ConteudoQuizPergunta;
use App\Models\ConteudoQuizResposta;
use App\Models\ConteudoQuizTentativa;
use App\Models\Inscricao;
use App\Services\AuditService;
use Exception;

class ConteudoQuizService
{
    private $quizModel;
    private $perguntaModel;
    private $alternativaModel;
    private $tentativaModel;
    private $respostaModel;
    private $itemModel;
    private $moduloModel;
    private $progressoModel;
    private $inscricaoModel;
    private $auditService;

    public function __construct()
    {
        $this->quizModel        = new ConteudoQuiz();
        $this->perguntaModel    = new ConteudoQuizPergunta();
        $this->alternativaModel = new ConteudoQuizAlternativa();
        $this->tentativaModel   = new ConteudoQuizTentativa();
        $this->respostaModel    = new ConteudoQuizResposta();
        $this->itemModel        = new ConteudoItem();
        $this->moduloModel      = new ConteudoModulo();
        $this->progressoModel   = new ConteudoProgressoAluno();
        $this->inscricaoModel   = new Inscricao();
        $this->auditService     = new AuditService();
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
        $perguntas = $this->montarPerguntasParaAluno($quiz, $tentativaEmAndamento ? $this->decodeSnapshot($tentativaEmAndamento['quiz_snapshot_json'] ?? null) : null);

        $quiz['perguntas']          = $perguntas;
        $quiz['total_perguntas']    = count($perguntas);
        $quiz['tentativas_usadas']  = $this->tentativaModel->countForInscricao((int) $quiz['id'], (int) $inscricaoId);
        $quiz['pode_nova_tentativa']= $this->podeFazerNovaTentativa((int) $quiz['id'], (int) $inscricaoId);

        return $quiz;
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
        return $quiz;
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
            return array('ok' => true, 'tentativa' => $emAndamento, 'retomada' => true);
        }

        if (!$this->podeFazerNovaTentativa($quizId, $inscricaoId)) {
            return array('ok' => false, 'message' => 'Você atingiu o número máximo de tentativas para este quiz.');
        }

        $numero = $this->tentativaModel->nextNumeroTentativa($quizId, $inscricaoId);
        $snapshot = $this->buildSnapshot($quiz);

        $tentativaId = $this->tentativaModel->create(array(
            'quiz_id'            => $quizId,
            'curso_evento_id'    => $cursoId,
            'turma_id'           => $turmaId,
            'inscricao_id'       => $inscricaoId,
            'aluno_id'           => $alunoId,
            'numero_tentativa'   => $numero,
            'status'             => 'em_andamento',
            'total_perguntas'    => count($snapshot['perguntas'] ?? array()),
            'quiz_snapshot_json' => $snapshot,
            'iniciada_em'        => date('Y-m-d H:i:s'),
        ));

        $tentativa = $this->tentativaModel->findById($tentativaId);
        return array('ok' => true, 'tentativa' => $tentativa, 'retomada' => false);
    }

    public function salvarRascunho(array $dados)
    {
        $tentativaId = (int) ($dados['tentativa_id'] ?? 0);
        $alunoId     = (int) ($dados['aluno_id'] ?? 0);
        $itemId      = (int) ($dados['item_id'] ?? 0);
        $respostas   = isset($dados['respostas']) && is_array($dados['respostas']) ? $dados['respostas'] : array();

        $tentativa = $this->validarTentativaDoAluno($tentativaId, $alunoId);
        if (is_array($tentativa) && isset($tentativa['error'])) {
            return array('ok' => false, 'message' => $tentativa['error']);
        }

        if ($itemId > 0) {
            $quiz = $this->quizModel->findById((int) $tentativa['quiz_id']);
            $item = $this->itemModel->findById($itemId);
            if (!$quiz || !$item || (int) $quiz['item_id'] !== (int) $item['id']) {
                return array('ok' => false, 'message' => 'O quiz não pertence ao conteúdo informado.');
            }
        }

        if ((string) ($tentativa['status'] ?? '') !== 'em_andamento') {
            return array('ok' => false, 'message' => 'Esta tentativa já foi enviada ou encerrada.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $this->gravarRespostas($tentativa, $respostas, false);
            $pdo->commit();
            return array('ok' => true);
        } catch (Exception $e) {
            $pdo->rollBack();
            Logger::error('quiz.rascunho.erro', array('message' => $e->getMessage()));
            return array('ok' => false, 'message' => 'Não foi possível salvar o rascunho.');
        }
    }

    public function enviarTentativa(array $dados)
    {
        $tentativaId = (int) ($dados['tentativa_id'] ?? 0);
        $alunoId     = (int) ($dados['aluno_id'] ?? 0);
        $respostas   = isset($dados['respostas']) && is_array($dados['respostas']) ? $dados['respostas'] : array();
        $itemId      = (int) ($dados['item_id'] ?? 0);
        $inscricaoId = (int) ($dados['inscricao_id'] ?? 0);
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

            // Validar perguntas obrigatórias somente para tentativa ativa.
            $perguntas = $this->perguntaModel->listForQuiz((int) $quiz['id']);
            foreach ($perguntas as $pergunta) {
                if (!empty($pergunta['obrigatoria'])) {
                    $perguntaId = (int) $pergunta['id'];
                    $respondida = isset($respostas[$perguntaId]) && $respostas[$perguntaId] !== '' && $respostas[$perguntaId] !== null;
                    if (!$respondida) {
                        $existente = $this->respostaModel->findByTentativaEPergunta($tentativaId, $perguntaId);
                        if (!$existente || (!$existente['alternativa_id'] && !$existente['resposta_json'])) {
                            $pdo->rollBack();
                            return array('ok' => false, 'message' => 'Responda todas as perguntas obrigatórias antes de enviar.');
                        }
                    }
                }
            }

            $this->gravarRespostas($tentativa, $respostas, true);
            $resultado = $this->calcularResultado((int) $tentativa['id'], $quiz, $perguntas);

            $this->tentativaModel->update(array(
                'status'          => 'corrigida',
                'total_perguntas' => $resultado['total_perguntas'],
                'total_acertos'   => $resultado['total_acertos'],
                'pontos_obtidos'  => $resultado['pontos_obtidos'],
                'pontos_totais'   => $resultado['pontos_totais'],
                'percentual'      => $resultado['percentual'],
                'aprovado'        => $resultado['aprovado'],
                'quiz_snapshot_json' => $tentativa['quiz_snapshot_json'],
                'enviada_em'      => date('Y-m-d H:i:s'),
                'corrigida_em'    => date('Y-m-d H:i:s'),
            ), (int) $tentativa['id']);

            $item = $itemId > 0 ? $this->itemModel->findById($itemId) : $this->itemModel->findById((int) $quiz['item_id']);
            if ($item) {
                $this->atualizarProgressoConteudo($tentativa, $quiz, $resultado, $item);
            }

            Logger::info('quiz.envio', array(
                'tentativa_id' => $tentativaId,
                'aluno_id'     => $alunoId,
                'quiz_id'      => $quiz['id'],
                'percentual'   => $resultado['percentual'],
                'aprovado'     => $resultado['aprovado'],
            ));

            $pdo->commit();

            $tentativaAtualizada = $this->tentativaModel->findById($tentativaId);
            return array('ok' => true, 'resultado' => $resultado, 'tentativa' => $tentativaAtualizada);
        } catch (Exception $e) {
            $pdo->rollBack();
            Logger::error('quiz.envio.erro', array('message' => $e->getMessage()));
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
            'tentativa' => $tentativa,
            'quiz'      => $quiz,
            'perguntas' => $perguntas,
        );
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
                $this->perguntaModel->update(array(
                    'enunciado'   => $enunciado,
                    'explicacao'  => isset($dados['explicacao']) ? trim((string) $dados['explicacao']) : null,
                    'peso'        => isset($dados['peso']) && $dados['peso'] !== '' ? (float) $dados['peso'] : 1.00,
                    'obrigatoria' => !empty($dados['obrigatoria']) ? 1 : 0,
                    'ordem'       => isset($dados['ordem']) ? (int) $dados['ordem'] : (int) ($pergunta['ordem'] ?? 0),
                ), $id);
                $perguntaId = $id;
            } else {
                $perguntaId = $this->perguntaModel->create(array(
                    'quiz_id'    => $quizId,
                    'enunciado'  => $enunciado,
                    'explicacao' => isset($dados['explicacao']) ? trim((string) $dados['explicacao']) : null,
                    'peso'       => isset($dados['peso']) && $dados['peso'] !== '' ? (float) $dados['peso'] : 1.00,
                    'obrigatoria'=> !empty($dados['obrigatoria']) ? 1 : 0,
                    'ordem'      => $this->perguntaModel->nextOrderForQuiz($quizId),
                ));
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
            Logger::error('quiz.pergunta.salvar.erro', array('message' => $e->getMessage()));
            throw $e;
        }
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
            $alternativas = $this->alternativaModel->listForPergunta($pid);

            if (count($alternativas) < 2) {
                $erros[] = 'Pergunta "' . mb_substr((string) $pergunta['enunciado'], 0, 40) . '..." tem menos de 2 alternativas.';
            }
            $corretas = array_filter($alternativas, function ($a) { return !empty($a['correta']); });
            if (count($corretas) !== 1) {
                $erros[] = 'Pergunta "' . mb_substr((string) $pergunta['enunciado'], 0, 40) . '..." deve ter exatamente 1 alternativa correta (tem ' . count($corretas) . ').';
            }
        }

        return array('ok' => count($erros) === 0, 'erros' => $erros);
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

        if ($pctMin < 0 || $pctMin > 100) {
            return array('ok' => false, 'message' => 'Percentual mínimo deve ser entre 0 e 100.');
        }

        $this->quizModel->upsertByItemId($itemId, array(
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
        ));

        $perguntas = $this->perguntaModel->listForQuiz((int) $origem['id']);
        foreach ($perguntas as $pergunta) {
            $novaPerguntaId = $this->perguntaModel->create(array(
                'quiz_id'    => $novoQuizId,
                'enunciado'  => $pergunta['enunciado'],
                'explicacao' => $pergunta['explicacao'],
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

    private function gravarRespostas(array $tentativa, array $respostas, bool $validarPertenencia)
    {
        $tentativaId = (int) $tentativa['id'];
        $quizId      = (int) $tentativa['quiz_id'];

        // Mapa de perguntas do quiz para validação
        $perguntas = $this->perguntaModel->listForQuiz($quizId);
        $perguntasMapa = array();
        foreach ($perguntas as $p) {
            $perguntasMapa[(int) $p['id']] = $p;
        }

        foreach ($respostas as $perguntaId => $alternativaId) {
            $perguntaId    = (int) $perguntaId;
            $alternativaId = $alternativaId !== '' && $alternativaId !== null ? (int) $alternativaId : null;

            if (!isset($perguntasMapa[$perguntaId])) {
                continue; // pergunta não pertence ao quiz
            }

            // Validar que a alternativa pertence à pergunta
            if ($alternativaId !== null) {
                $alt = $this->alternativaModel->findByPerguntaAndId($perguntaId, $alternativaId);
                if (!$alt) {
                    if ($validarPertenencia) {
                        throw new Exception('Alternativa inválida para a pergunta informada.');
                    }
                    continue;
                }
            }

            $this->respostaModel->upsert(array(
                'tentativa_id' => $tentativaId,
                'pergunta_id'  => $perguntaId,
                'alternativa_id' => $alternativaId,
                'resposta_json'  => null,
                'correta'        => 0, // corrigido depois
                'pontos_obtidos' => 0,
                'pergunta_snapshot_json' => null,
            ));
        }
    }

    private function calcularResultado($tentativaId, array $quiz, array $perguntas)
    {
        $respostas = $this->respostaModel->listForTentativa($tentativaId);
        $respostasPorPergunta = array();
        foreach ($respostas as $r) {
            $respostasPorPergunta[(int) $r['pergunta_id']] = $r;
        }

        $totalPerguntas  = 0;
        $totalAcertos    = 0;
        $pontosObtidos   = 0.0;
        $pontosTotais    = 0.0;

        foreach ($perguntas as $pergunta) {
            $pid   = (int) $pergunta['id'];
            $peso  = (float) ($pergunta['peso'] ?? 1.0);
            $pontosTotais += $peso;
            $totalPerguntas++;

            $alternativas = $this->alternativaModel->listForPergunta($pid);
            $corretaId    = null;
            foreach ($alternativas as $alt) {
                if (!empty($alt['correta'])) {
                    $corretaId = (int) $alt['id'];
                    break;
                }
            }

            $resposta    = isset($respostasPorPergunta[$pid]) ? $respostasPorPergunta[$pid] : null;
            $altRespondida = $resposta ? (int) ($resposta['alternativa_id'] ?? 0) : 0;
            $acertou     = $corretaId !== null && $altRespondida === $corretaId;

            $pontosResposta = $acertou ? $peso : 0.0;
            if ($acertou) {
                $totalAcertos++;
                $pontosObtidos += $peso;
            }

            // Atualizar resposta com resultado e snapshot
            $perguntaSnapshot = array(
                'id'         => $pid,
                'enunciado'  => $pergunta['enunciado'],
                'explicacao' => $pergunta['explicacao'],
                'correta_id' => $corretaId,
                'peso'       => $peso,
            );

            if ($resposta) {
                $this->respostaModel->upsert(array(
                    'tentativa_id'          => $tentativaId,
                    'pergunta_id'           => $pid,
                    'alternativa_id'        => $altRespondida > 0 ? $altRespondida : null,
                    'correta'               => $acertou ? 1 : 0,
                    'pontos_obtidos'        => $pontosResposta,
                    'pergunta_snapshot_json'=> $perguntaSnapshot,
                ));
            } else {
                $this->respostaModel->upsert(array(
                    'tentativa_id'          => $tentativaId,
                    'pergunta_id'           => $pid,
                    'alternativa_id'        => null,
                    'correta'               => 0,
                    'pontos_obtidos'        => 0,
                    'pergunta_snapshot_json'=> $perguntaSnapshot,
                ));
            }
        }

        $percentual = $pontosTotais > 0 ? round(($pontosObtidos / $pontosTotais) * 100, 2) : 0.0;

        $pctMinimo  = (float) ($quiz['percentual_minimo'] ?? 0);
        $exige      = !empty($quiz['exige_aprovacao']);
        $aprovado   = $exige ? ($percentual >= $pctMinimo) : true;

        return array(
            'total_perguntas' => $totalPerguntas,
            'total_acertos'   => $totalAcertos,
            'pontos_obtidos'  => round($pontosObtidos, 2),
            'pontos_totais'   => round($pontosTotais, 2),
            'percentual'      => $percentual,
            'aprovado'        => $aprovado,
        );
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
            $status = 'reprovado';
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

    private function buildSnapshot(array $quiz)
    {
        $perguntas = $this->perguntaModel->listForQuiz((int) $quiz['id']);
        if (!empty($quiz['embaralhar_perguntas']) && count($perguntas) > 1) {
            shuffle($perguntas);
        }
        $snap = array(
            'quiz_id'           => (int) $quiz['id'],
            'tentativas_maximas'=> $quiz['tentativas_maximas'],
            'percentual_minimo' => (float) $quiz['percentual_minimo'],
            'exige_aprovacao'   => (int) $quiz['exige_aprovacao'],
            'embaralhar_perguntas' => !empty($quiz['embaralhar_perguntas']) ? 1 : 0,
            'embaralhar_alternativas' => !empty($quiz['embaralhar_alternativas']) ? 1 : 0,
            'perguntas'         => array(),
        );

        foreach ($perguntas as $p) {
            $alternativas = $this->alternativaModel->listForPergunta((int) $p['id']);
            if (!empty($quiz['embaralhar_alternativas']) && count($alternativas) > 1) {
                shuffle($alternativas);
            }
            $snap['perguntas'][] = array(
                'id'          => (int) $p['id'],
                'enunciado'   => $p['enunciado'],
                'tipo'        => $p['tipo'],
                'peso'        => (float) $p['peso'],
                'obrigatoria' => (int) $p['obrigatoria'],
                'ordem'       => (int) $p['ordem'],
                'alternativas'=> array_map(function ($a) {
                    return array(
                        'id'     => (int) $a['id'],
                        'texto'  => $a['texto'],
                        'correta'=> (int) $a['correta'],
                        'ordem'  => (int) $a['ordem'],
                    );
                }, $alternativas),
            );
        }

        return $snap;
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

        $mostrarCorretas = !empty($tentativa) && in_array((string) ($tentativa['status'] ?? ''), array('corrigida', 'enviada'), true) && !empty($quiz['exibir_gabarito_apos_envio']);

        foreach ($perguntasBase as &$pergunta) {
            $perguntaId = (int) ($pergunta['id'] ?? 0);
            if (!isset($pergunta['alternativas']) || !is_array($pergunta['alternativas'])) {
                $alternativas = $this->alternativaModel->listForPergunta($perguntaId);
                $pergunta['alternativas'] = $alternativas;
            }

            foreach ($pergunta['alternativas'] as &$alt) {
                if (empty($mostrarCorretas)) {
                    unset($alt['correta']);
                }
            }
            unset($alt);

            if ($tentativa) {
                $resposta = isset($respostasPorPergunta[$perguntaId]) ? $respostasPorPergunta[$perguntaId] : null;
                $pergunta['resposta'] = $resposta;
                $pergunta['respondida'] = $resposta !== null && (int) ($resposta['alternativa_id'] ?? 0) > 0;
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
