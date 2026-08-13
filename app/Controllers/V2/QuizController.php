<?php

namespace App\Controllers\V2;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\ConteudoQuizTentativa;
use App\Services\AreaCursoService;
use App\Services\ConteudoCursoService;
use App\Services\ConteudoQuizService;

/**
 * LMS V2 (Fase 2.9) — Quizzes objetivos reais.
 *
 * Permite que o aluno autenticado responda, envie e veja o resultado de quizzes
 * objetivos REAIS dentro da V2, reutilizando INTEGRALMENTE as regras já
 * existentes do sistema. Nada de correção, nota, tentativa, limite ou aprovação
 * é reimplementado aqui:
 *
 * - autorização/posse: `AreaCursoService::carregarAluno(...)` (só retorna a
 *   inscrição quando ela é do usuário da sessão, via `forUsuarioAprovadas`);
 * - item publicado/quiz: `ConteudoCursoService::buscarItemPublicadoParaAluno(...)`;
 * - estado do quiz para o aluno (perguntas SEM gabarito):
 *   `ConteudoQuizService::findQuizParaAluno(...)` / `obterTentativaParaAluno(...)`;
 * - início/retomada de tentativa: `ConteudoQuizService::iniciarOuRetomar(...)`;
 * - envio/correção/nota/aprovação/progresso: `ConteudoQuizService::enviarTentativa(...)`.
 *
 * Os endpoints POST oficiais (`/aluno/cursos/quiz/*`) redirecionam para o LMS
 * antigo (`QuizController::redirectConteudo`), o que tiraria o aluno da V2. Por
 * isso esta fase cria rotas POST V2 dedicadas que delegam ao MESMO service real
 * e devolvem (POST → Redirect → GET) sempre para um caminho interno fixo da V2.
 *
 * IDs de query/formulário são meros localizadores; a autorização é sempre
 * refeita no servidor com o usuário da sessão. Nenhum gabarito é exposto antes
 * do momento permitido pelas regras reais do quiz.
 */
class QuizController extends Controller
{
    /** @var AreaCursoService */
    private $areaCursoService;
    /** @var ConteudoCursoService */
    private $conteudoService;
    /** @var ConteudoQuizService */
    private $quizService;

    public function __construct()
    {
        $this->areaCursoService = new AreaCursoService();
        $this->conteudoService = new ConteudoCursoService();
        $this->quizService = new ConteudoQuizService();
    }

    /**
     * GET /v2/quiz — renderiza o quiz real (antes de iniciar / em andamento /
     * resultado), sempre com dados reais permitidos e sem gabarito indevido.
     */
    public function index(Request $request)
    {
        $usuarioId = (int) Session::get('usuario_id', 0);
        if ($usuarioId <= 0) {
            return Response::redirect('/v2/login?origem=v2_aluno');
        }

        $inscricaoId = (int) $request->query('inscricao_id', 0);
        $cursoIdParam = (int) $request->query('curso_id', 0);
        $turmaIdParam = (int) $request->query('turma_id', 0);
        $moduloIdParam = (int) $request->query('modulo_id', 0);
        $itemId = (int) $request->query('conteudo_id', $request->query('item_id', 0));
        $tentativaIdResultado = (int) $request->query('tentativa_id', 0);

        $base = $this->dadosLayout($usuarioId);

        if ($inscricaoId <= 0 || $itemId <= 0) {
            return $this->estado($base, 'Selecione um quiz', 'Abra um quiz a partir da sua aula para começar.', 200);
        }

        // Posse da inscrição (mesma checagem real do LMS atual).
        $contexto = $this->areaCursoService->carregarAluno(
            $usuarioId,
            $inscricaoId,
            0,
            0,
            $cursoIdParam > 0 ? $cursoIdParam : null,
            $turmaIdParam > 0 ? $turmaIdParam : null,
            null
        );
        if (empty($contexto['inscricao'])) {
            return $this->estado($base, 'Conteúdo indisponível', 'Não encontramos uma inscrição válida sua para este quiz.', 404);
        }

        $inscricao = $contexto['inscricao'];
        $cursoId = (int) $inscricao['curso_evento_id'];
        $turmaId = !empty($inscricao['turma_id']) ? (int) $inscricao['turma_id'] : 0;

        // Curso/turma informados precisam corresponder à inscrição real.
        if ($cursoIdParam > 0 && $cursoIdParam !== $cursoId) {
            return $this->estado($base, 'Conteúdo indisponível', 'O curso informado não corresponde à sua inscrição.', 404);
        }
        if ($turmaIdParam > 0 && ($turmaId <= 0 || $turmaIdParam !== $turmaId)) {
            return $this->estado($base, 'Conteúdo indisponível', 'A turma informada não corresponde à sua inscrição.', 404);
        }

        // Item precisa ser publicado, acessível e do tipo quiz (backend decide).
        $detalhe = $this->conteudoService->buscarItemPublicadoParaAluno(
            $itemId,
            $usuarioId,
            (int) $inscricao['id'],
            $cursoId,
            $turmaId > 0 ? $turmaId : null
        );
        if (empty($detalhe['ok']) || (string) ($detalhe['item']['tipo'] ?? '') !== 'quiz') {
            // Não expõe título/perguntas/alternativas/nota de quiz inacessível.
            return $this->estado($base, 'Conteúdo indisponível', 'Este quiz não está disponível para você no momento.', 404);
        }

        $item = $detalhe['item'];
        $moduloId = (int) ($detalhe['modulo']['id'] ?? $moduloIdParam);

        // Estado do quiz para o aluno (perguntas já SEM gabarito no service).
        $quizAluno = $this->quizService->findQuizParaAluno($itemId, $usuarioId, (int) $inscricao['id']);

        $resumo = $this->conteudoService->obterResumoProgressoAluno(
            $cursoId,
            $usuarioId,
            (int) $inscricao['id'],
            $turmaId > 0 ? $turmaId : null
        );

        $cabecalho = array(
            'curso_nome' => isset($contexto['curso']['nome']) ? (string) $contexto['curso']['nome'] : '',
            'quiz_nome' => (string) ($item['titulo'] ?? ''),
            'modulo_nome' => (string) ($detalhe['modulo']['titulo'] ?? ''),
            'progresso' => $this->progressoPercentual($inscricao, !empty($resumo['ok']) ? $resumo : array()),
        );

        $quizView = $this->montarEstadoQuiz($quizAluno, $inscricao, $usuarioId, $item, $tentativaIdResultado);

        $formCtx = array(
            'inscricao_id' => (int) $inscricao['id'],
            'curso_id' => $cursoId,
            'turma_id' => $turmaId,
            'modulo_id' => $moduloId,
            'item_id' => $itemId,
            'iniciar_action' => '/v2/quiz/iniciar',
            'enviar_action' => '/v2/quiz/enviar',
        );

        $data = array_merge($base, array(
            'title' => ($cabecalho['quiz_nome'] !== '' ? $cabecalho['quiz_nome'] : 'Quiz') . ' — Desbloqueia Cursos',
            'pageTitle' => ($cabecalho['quiz_nome'] !== '' ? $cabecalho['quiz_nome'] : 'Quiz') . ' — Desbloqueia Cursos',
            'pageDescription' => 'Responda o quiz do seu curso.',
            'estado' => null,
            'cabecalho' => $cabecalho,
            'quiz' => $quizView,
            'formCtx' => $formCtx,
            'voltarAulaUrl' => $this->urlAulaV2($inscricao, $cursoId, $turmaId, $moduloId, $itemId),
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
        ));

        return new Response(View::render('v2/quiz', $data, false));
    }

    /**
     * POST /v2/quiz/iniciar — inicia ou retoma a tentativa real (delegado ao
     * service). CSRF automático no `$app->post`. PRG para a própria V2.
     */
    public function iniciar(Request $request)
    {
        $usuarioId = (int) Session::get('usuario_id', 0);
        if ($usuarioId <= 0) {
            return Response::redirect('/v2/login?origem=v2_aluno');
        }

        $ctx = $this->resolverContextoPost($request, $usuarioId);
        if (empty($ctx['ok'])) {
            return $ctx['response'];
        }

        $inscricao = $ctx['inscricao'];
        $itemId = $ctx['item_id'];
        $detalhe = $ctx['detalhe'];

        if ((string) ($detalhe['item']['tipo'] ?? '') !== 'quiz') {
            Session::flash('errors', array('Quiz não encontrado.'));
            return Response::redirect($this->urlQuizV2($inscricao, $ctx['curso_id'], $ctx['turma_id'], $ctx['modulo_id'], $itemId));
        }

        $quiz = $detalhe['detalhe'];
        if (!$quiz) {
            Session::flash('errors', array('Este quiz ainda não tem perguntas configuradas.'));
            return Response::redirect($this->urlQuizV2($inscricao, $ctx['curso_id'], $ctx['turma_id'], $ctx['modulo_id'], $itemId));
        }

        // Regra real: iniciar/retomar (limite de tentativas, snapshot etc. no service).
        $resultado = $this->quizService->iniciarOuRetomar(array(
            'quiz_id' => (int) $quiz['id'],
            'inscricao_id' => (int) $inscricao['id'],
            'aluno_id' => $usuarioId,
            'curso_evento_id' => (int) $inscricao['curso_evento_id'],
            'turma_id' => $ctx['turma_id'] > 0 ? $ctx['turma_id'] : null,
        ));

        if (empty($resultado['ok'])) {
            Session::flash('errors', array(isset($resultado['message']) ? $resultado['message'] : 'Não foi possível iniciar o quiz.'));
        }

        return Response::redirect($this->urlQuizV2($inscricao, $ctx['curso_id'], $ctx['turma_id'], $ctx['modulo_id'], $itemId));
    }

    /**
     * POST /v2/quiz/enviar — envia a tentativa real (correção/nota/aprovação/
     * progresso 100% no service). CSRF automático. PRG para o resultado na V2.
     */
    public function enviar(Request $request)
    {
        $usuarioId = (int) Session::get('usuario_id', 0);
        if ($usuarioId <= 0) {
            return Response::redirect('/v2/login?origem=v2_aluno');
        }

        $ctx = $this->resolverContextoPost($request, $usuarioId);
        if (empty($ctx['ok'])) {
            return $ctx['response'];
        }

        $inscricao = $ctx['inscricao'];
        $itemId = $ctx['item_id'];
        $tentativaId = (int) $request->input('tentativa_id', 0);
        $respostas = $request->input('respostas', array());
        if (!is_array($respostas)) {
            $respostas = array();
        }
        // Simulado por blocos: a discursiva é obrigatória para o envio.
        $discursivas = $request->input('discursivas', array());
        if (!is_array($discursivas)) {
            $discursivas = array();
        }

        // Correção/nota/aprovação/limite/progresso: integralmente no service real.
        $resultado = $this->quizService->enviarTentativa(array(
            'tentativa_id' => $tentativaId,
            'aluno_id' => $usuarioId,
            'item_id' => $itemId,
            'inscricao_id' => (int) $inscricao['id'],
            'respostas' => $respostas,
            'discursivas' => $discursivas,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ));

        if (empty($resultado['ok'])) {
            Session::flash('errors', array(isset($resultado['message']) ? $resultado['message'] : 'Não foi possível enviar o quiz.'));
            return Response::redirect($this->urlQuizV2($inscricao, $ctx['curso_id'], $ctx['turma_id'], $ctx['modulo_id'], $itemId));
        }

        Session::flash('success', 'Quiz enviado com sucesso.');
        return Response::redirect($this->urlQuizV2($inscricao, $ctx['curso_id'], $ctx['turma_id'], $ctx['modulo_id'], $itemId, $tentativaId));
    }

    /**
     * Posse + item publicado/quiz para os POSTs. Retorna sempre redirect interno
     * em caso de falha (nunca URL vinda do navegador).
     */
    private function resolverContextoPost(Request $request, $usuarioId)
    {
        $inscricaoId = (int) $request->input('inscricao_id', 0);
        $cursoIdParam = (int) $request->input('curso_id', 0);
        $turmaIdParam = (int) $request->input('turma_id', 0);
        $moduloIdParam = (int) $request->input('modulo_id', 0);
        $itemId = (int) $request->input('item_id', $request->input('conteudo_id', 0));

        $contexto = $this->areaCursoService->carregarAluno(
            $usuarioId,
            $inscricaoId,
            0,
            0,
            $cursoIdParam > 0 ? $cursoIdParam : null,
            $turmaIdParam > 0 ? $turmaIdParam : null,
            null
        );
        if (empty($contexto['inscricao'])) {
            Session::flash('errors', array('Acesso negado.'));
            return array('ok' => false, 'response' => Response::redirect('/v2/aluno/'));
        }

        $inscricao = $contexto['inscricao'];
        $cursoId = (int) $inscricao['curso_evento_id'];
        $turmaId = !empty($inscricao['turma_id']) ? (int) $inscricao['turma_id'] : 0;

        if (($cursoIdParam > 0 && $cursoIdParam !== $cursoId)
            || ($turmaIdParam > 0 && ($turmaId <= 0 || $turmaIdParam !== $turmaId))) {
            Session::flash('errors', array('Acesso negado.'));
            return array('ok' => false, 'response' => Response::redirect('/v2/aluno/'));
        }

        if ($itemId <= 0) {
            Session::flash('errors', array('Quiz inválido.'));
            return array('ok' => false, 'response' => Response::redirect('/v2/aluno/'));
        }

        $detalhe = $this->conteudoService->buscarItemPublicadoParaAluno(
            $itemId,
            $usuarioId,
            (int) $inscricao['id'],
            $cursoId,
            $turmaId > 0 ? $turmaId : null
        );
        if (empty($detalhe['ok'])) {
            Session::flash('errors', array('Conteúdo indisponível.'));
            return array('ok' => false, 'response' => Response::redirect($this->urlAulaV2($inscricao, $cursoId, $turmaId, $moduloIdParam, $itemId)));
        }

        return array(
            'ok' => true,
            'inscricao' => $inscricao,
            'curso_id' => $cursoId,
            'turma_id' => $turmaId,
            'modulo_id' => $moduloIdParam > 0 ? $moduloIdParam : (int) ($detalhe['modulo']['id'] ?? 0),
            'item_id' => $itemId,
            'detalhe' => $detalhe,
        );
    }

    /**
     * Determina o estado real do quiz (antes / em andamento / resultado) usando
     * apenas os métodos reais do service. Nunca expõe gabarito indevido nem dados
     * de outra tentativa/aluno (a posse já foi validada; aqui só leitura própria).
     */
    private function montarEstadoQuiz($quizAluno, array $inscricao, $usuarioId, array $item, $tentativaIdResultado)
    {
        if (empty($quizAluno)) {
            return array('estado' => 'indisponivel', 'titulo' => (string) ($item['titulo'] ?? 'Quiz'));
        }

        $quizId = (int) $quizAluno['id'];
        $inscricaoId = (int) $inscricao['id'];

        $tentativaModel = new ConteudoQuizTentativa();
        $tentativaAtiva = $tentativaModel->findEmAndamento($quizId, $inscricaoId);

        $comum = array(
            'titulo' => (string) ($item['titulo'] ?? ($quizAluno['titulo'] ?? 'Quiz')),
            'instrucoes' => (string) ($quizAluno['instrucoes'] ?? ''),
            'total_perguntas' => (int) ($quizAluno['total_perguntas'] ?? 0),
            'tentativas_usadas' => (int) ($quizAluno['tentativas_usadas'] ?? 0),
            'tentativas_maximas' => isset($quizAluno['tentativas_maximas']) && $quizAluno['tentativas_maximas'] !== null
                ? (int) $quizAluno['tentativas_maximas'] : null,
            'pode_nova_tentativa' => !empty($quizAluno['pode_nova_tentativa']),
            // Estrutura da prova (duração, blocos, regra de aprovação) para o
            // painel exibido antes de começar e para o cabeçalho do simulado.
            'estrutura' => isset($quizAluno['estrutura']) && is_array($quizAluno['estrutura'])
                ? $quizAluno['estrutura'] : array(),
            'limite_caracteres_discursiva' => isset($quizAluno['limite_caracteres_discursiva']) && $quizAluno['limite_caracteres_discursiva'] !== null
                ? (int) $quizAluno['limite_caracteres_discursiva'] : 50000,
        );

        // RESULTADO — só quando o aluno acabou de enviar (tentativa_id na URL) e a
        // tentativa é DELE e DESTE quiz, já corrigida/enviada.
        if ($tentativaIdResultado > 0) {
            $dados = $this->quizService->obterTentativaParaAluno($tentativaIdResultado, $usuarioId);
            if ($dados
                && (int) ($dados['tentativa']['quiz_id'] ?? 0) === $quizId
                && (int) ($dados['tentativa']['inscricao_id'] ?? 0) === $inscricaoId
                && in_array((string) ($dados['tentativa']['status'] ?? ''), array('corrigida', 'enviada'), true)) {
                $tent = $dados['tentativa'];
                $qd = $dados['quiz'];
                return array_merge($comum, array(
                    'estado' => 'resultado',
                    'perguntas' => $this->perguntasParaView($dados['perguntas'], true),
                    'resultado' => array(
                        'mostrar_resultado' => !empty($qd['exibir_resultado_apos_envio']),
                        'mostrar_gabarito' => !empty($qd['exibir_gabarito_apos_envio']),
                        'mostrar_comentarios' => !empty($qd['exibir_comentarios_apos_envio']),
                        'total_acertos' => (int) ($tent['total_acertos'] ?? 0),
                        'total_perguntas' => (int) ($tent['total_perguntas'] ?? 0),
                        'percentual' => (float) ($tent['percentual'] ?? 0),
                        'aprovado' => isset($tent['aprovado']) && $tent['aprovado'] !== null ? (bool) $tent['aprovado'] : null,
                    ),
                ));
            }
        }

        // EM ANDAMENTO — formulário de respostas com as respostas já salvas.
        if ($tentativaAtiva) {
            $dados = $this->quizService->obterTentativaParaAluno((int) $tentativaAtiva['id'], $usuarioId);
            $perguntas = $dados && !empty($dados['perguntas']) ? $dados['perguntas'] : ($quizAluno['perguntas'] ?? array());
            return array_merge($comum, array(
                'estado' => 'andamento',
                'tentativa_ativa_id' => (int) $tentativaAtiva['id'],
                'numero_tentativa' => (int) ($tentativaAtiva['numero_tentativa'] ?? 1),
                'perguntas' => $this->perguntasParaView($perguntas, false),
                // Prazo conferido no servidor; o cronômetro da tela é só visual.
                'tempo' => $dados && !empty($dados['tempo']) ? $dados['tempo'] : null,
            ));
        }

        // ANTES DE INICIAR.
        return array_merge($comum, array('estado' => 'antes'));
    }

    /**
     * Normaliza perguntas para a view, garantindo que NENHUMA informação de
     * gabarito ("correta") seja enviada ao HTML quando não permitido.
     *
     * @param bool $resultado Quando true, mantém o marcador "correta" SOMENTE se o
     *   service já o tiver liberado (ele remove "correta" quando o gabarito não
     *   pode ser exibido). Quando false (quiz em andamento), remove sempre.
     */
    private function perguntasParaView($perguntas, $resultado)
    {
        $perguntas = is_array($perguntas) ? $perguntas : array();
        $saida = array();
        foreach ($perguntas as $p) {
            $alts = array();
            foreach ((isset($p['alternativas']) && is_array($p['alternativas']) ? $p['alternativas'] : array()) as $a) {
                $alt = array(
                    'id' => (int) ($a['id'] ?? 0),
                    'texto' => (string) ($a['texto'] ?? ''),
                );
                // Só no resultado e apenas se o service liberou o gabarito.
                if ($resultado && array_key_exists('correta', $a)) {
                    $alt['correta'] = !empty($a['correta']);
                }
                $alts[] = $alt;
            }
            $saida[] = array(
                'id' => (int) ($p['id'] ?? 0),
                'enunciado' => (string) ($p['enunciado'] ?? ''),
                'obrigatoria' => !empty($p['obrigatoria']),
                'alternativas' => $alts,
                'alternativa_id_respondida' => (int) ($p['alternativa_id_respondida'] ?? 0),
                'explicacao' => $resultado && isset($p['explicacao']) ? (string) $p['explicacao'] : '',
                // Simulado por blocos: tipo define se renderiza alternativas ou
                // campo de texto; a rubrica de correção nunca vem para a view.
                'tipo' => (string) ($p['tipo'] ?? 'multipla_escolha'),
                'bloco_codigo' => (string) ($p['bloco_codigo'] ?? ''),
                'bloco_titulo' => (string) ($p['bloco_titulo'] ?? ''),
                'texto_resposta' => (string) ($p['texto_resposta'] ?? ''),
                'marcada_para_revisao' => !empty($p['marcada_para_revisao']),
            );
        }
        return $saida;
    }

    private function progressoPercentual(array $inscricao, array $resumo)
    {
        if (isset($resumo['percentual']) && is_numeric($resumo['percentual'])) {
            $p = (float) $resumo['percentual'];
        } elseif (isset($inscricao['percentual_progresso']) && is_numeric($inscricao['percentual_progresso'])) {
            $p = (float) $inscricao['percentual_progresso'];
        } else {
            $p = 0.0;
        }
        if ($p < 0) { $p = 0.0; }
        if ($p > 100) { $p = 100.0; }
        return (int) round($p);
    }

    private function urlQuizV2(array $inscricao, $cursoId, $turmaId, $moduloId, $itemId, $tentativaId = 0)
    {
        $url = '/v2/quiz?inscricao_id=' . (int) $inscricao['id']
            . '&curso_id=' . (int) $cursoId
            . '&turma_id=' . (int) $turmaId
            . '&modulo_id=' . (int) $moduloId
            . '&conteudo_id=' . (int) $itemId;
        if ((int) $tentativaId > 0) {
            $url .= '&tentativa_id=' . (int) $tentativaId;
        }
        return $url;
    }

    private function urlAulaV2(array $inscricao, $cursoId, $turmaId, $moduloId, $itemId)
    {
        return '/v2/aula/?inscricao_id=' . (int) $inscricao['id']
            . '&curso_id=' . (int) $cursoId
            . '&turma_id=' . (int) $turmaId
            . '&modulo_id=' . (int) $moduloId
            . '&conteudo_id=' . (int) $itemId;
    }

    private function estado(array $base, $titulo, $mensagem, $status)
    {
        $data = array_merge($base, array(
            'title' => $titulo . ' — Desbloqueia Cursos',
            'pageTitle' => $titulo . ' — Desbloqueia Cursos',
            'pageDescription' => $mensagem,
            'estado' => array('titulo' => $titulo, 'mensagem' => $mensagem),
            'cabecalho' => null,
            'quiz' => null,
            'formCtx' => array(),
            'voltarAulaUrl' => '/v2/aluno/',
        ));

        return new Response(View::render('v2/quiz', $data, false), (int) $status);
    }

    private function dadosLayout($usuarioId)
    {
        $usuarioNome = trim((string) Session::get('usuario_nome', ''));
        $sessionPerfis = Session::get('usuario_perfis', array());
        $hasAdminAccess = (bool) Session::get('usuario_admin') || (bool) Session::get('is_admin') || in_array('admin', $sessionPerfis, true);
        $hasProfessorAccess = (bool) Session::get('usuario_professor') || (bool) Session::get('is_professor') || in_array('professor', $sessionPerfis, true);

        $areaHref = '/v2/aluno';
        if ($hasAdminAccess) {
            $areaHref = '/admin';
        } elseif ($hasProfessorAccess) {
            $areaHref = '/professor/dashboard';
        }

        return array(
            'loggedIn' => true,
            'usuarioNome' => $usuarioNome,
            'usuarioPrimeiroNome' => $this->primeiroNome($usuarioNome),
            'areaHref' => $areaHref,
            'alunoHref' => '/v2/aluno/',
            'loginHref' => '/v2/login',
            'registerHref' => '/v2/cadastro',
            'catalogoHref' => '/v2/catalogo/',
            'categoriasHref' => '/categorias',
            'certificadosHref' => '/v2/certificados/validar/',
            'sobreHref' => '/sobre',
            'contatoHref' => '/contato',
            'homeHref' => '/v2/',
        );
    }

    private function primeiroNome($nome)
    {
        $nome = trim((string) $nome);
        if ($nome === '') {
            return 'aluno';
        }
        $partes = preg_split('/\s+/', $nome);
        return ($partes && !empty($partes[0])) ? (string) $partes[0] : $nome;
    }
}
