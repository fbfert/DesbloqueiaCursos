<?php

namespace App\Controllers\V2;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\AreaCursoService;
use App\Services\ConteudoCursoService;
use App\Services\ConteudoAvaliacaoTextualService;

/**
 * LMS V2 (Fase 2.10) — Atividades avaliativas discursivas reais (resposta
 * textual digitada + até 5 imagens anexadas).
 *
 * Reaproveita INTEGRALMENTE as regras já existentes do sistema:
 * - posse: `AreaCursoService::carregarAluno(...)`;
 * - item publicado/avaliação: `ConteudoCursoService::buscarItemPublicadoParaAluno(...)`;
 * - entregas do próprio aluno: `ConteudoAvaliacaoTextualService::listarEntregasAluno(...)`;
 * - permissão de envio/reenvio: `ConteudoAvaliacaoTextualService::podeReenviar(...)`;
 * - envio (validação mínima, status, reenvio, log, progresso, notificação,
 *   validação/gravação das imagens): `ConteudoAvaliacaoTextualService::enviarResposta(...)`.
 *
 * O endpoint oficial (`/aluno/cursos/conteudo/avaliacao/enviar`) redireciona ao
 * LMS antigo; por isso esta fase cria uma rota POST V2 dedicada que delega ao
 * MESMO service real e devolve (POST → Redirect → GET) para a própria V2.
 *
 * NADA de correção, nota, aprovação, devolução ou progresso é calculado aqui.
 * IDs de URL/formulário são meros localizadores; a autorização é sempre refeita
 * no servidor com o usuário da sessão. Não se expõe resposta/nota/feedback de
 * outro aluno (consulta sempre pela inscrição do usuário autenticado). O
 * download das imagens usa a mesma rota autenticada do LMS antigo
 * (`/aluno/cursos/conteudo/avaliacao/imagem`), que valida posse da entrega.
 */
class AtividadeController extends Controller
{
    /** @var AreaCursoService */
    private $areaCursoService;
    /** @var ConteudoCursoService */
    private $conteudoService;
    /** @var ConteudoAvaliacaoTextualService */
    private $avaliacaoService;

    public function __construct()
    {
        $this->areaCursoService = new AreaCursoService();
        $this->conteudoService = new ConteudoCursoService();
        $this->avaliacaoService = new ConteudoAvaliacaoTextualService();
    }

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

        $base = $this->dadosLayout($usuarioId);

        if ($inscricaoId <= 0 || $itemId <= 0) {
            return $this->estado($base, 'Selecione uma atividade', 'Abra uma atividade a partir da sua aula para começar.', 200);
        }

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
            return $this->estado($base, 'Conteúdo indisponível', 'Não encontramos uma inscrição válida sua para esta atividade.', 404);
        }

        $inscricao = $contexto['inscricao'];
        $cursoId = (int) $inscricao['curso_evento_id'];
        $turmaId = !empty($inscricao['turma_id']) ? (int) $inscricao['turma_id'] : 0;

        if ($cursoIdParam > 0 && $cursoIdParam !== $cursoId) {
            return $this->estado($base, 'Conteúdo indisponível', 'O curso informado não corresponde à sua inscrição.', 404);
        }
        if ($turmaIdParam > 0 && ($turmaId <= 0 || $turmaIdParam !== $turmaId)) {
            return $this->estado($base, 'Conteúdo indisponível', 'A turma informada não corresponde à sua inscrição.', 404);
        }

        $detalhe = $this->conteudoService->buscarItemPublicadoParaAluno(
            $itemId,
            $usuarioId,
            (int) $inscricao['id'],
            $cursoId,
            $turmaId > 0 ? $turmaId : null
        );
        if (empty($detalhe['ok'])) {
            return $this->estado($base, 'Conteúdo indisponível', 'Esta atividade não está disponível para você no momento.', 404);
        }

        $item = $detalhe['item'];
        $tipo = (string) ($item['tipo'] ?? '');
        $moduloId = (int) ($detalhe['modulo']['id'] ?? $moduloIdParam);
        $cabecalho = array(
            'curso_nome' => isset($contexto['curso']['nome']) ? (string) $contexto['curso']['nome'] : '',
            'atividade_nome' => (string) ($item['titulo'] ?? ''),
            'modulo_nome' => (string) ($detalhe['modulo']['titulo'] ?? ''),
        );
        $voltarAulaUrl = $this->urlAulaV2($inscricao, $cursoId, $turmaId, $moduloId, $itemId);

        // Esta fase trata apenas de atividade discursiva textual. Qualquer outro
        // tipo interativo (ex.: que dependa de upload) recebe estado informativo
        // + link para a rota oficial — sem adaptar parcialmente.
        if ($tipo !== 'avaliacao_textual') {
            $atividade = array(
                'estado' => 'externo',
                'titulo' => (string) ($item['titulo'] ?? ''),
                'oficial_url' => $this->urlOficialItem($inscricao, $cursoId, $turmaId, $moduloId, $itemId),
            );
            return $this->render($base, $cabecalho, $atividade, array(), $voltarAulaUrl, 200);
        }

        $avaliacao = is_array($detalhe['detalhe']) ? $detalhe['detalhe'] : array();
        $avaliacaoId = (int) ($avaliacao['id'] ?? 0);

        $entregas = $avaliacaoId > 0
            ? $this->avaliacaoService->listarEntregasAluno($avaliacaoId, $usuarioId, (int) $inscricao['id'])
            : array();
        $ultima = !empty($entregas) ? $entregas[0] : null;
        $podeEnviar = $avaliacaoId > 0
            ? (bool) $this->avaliacaoService->podeReenviar($avaliacaoId, $usuarioId, (int) $inscricao['id'])
            : false;

        $statusCode = $this->mapaStatus($ultima ? (string) ($ultima['status'] ?? '') : '');

        $atividade = array(
            'estado' => 'avaliacao',
            'titulo' => (string) ($item['titulo'] ?? ''),
            'enunciado_html' => $this->valorDetalhe($avaliacao, array('enunciado', 'conteudo', 'texto', 'descricao', 'corpo', 'html')),
            'orientacoes_html' => $this->valorDetalhe($avaliacao, array('orientacoes', 'orientacao')),
            'prazo' => (string) ($avaliacao['prazo'] ?? ''),
            'status_code' => $statusCode,
            'status_label' => $this->rotuloStatus($statusCode),
            'pode_enviar' => $podeEnviar,
            'ja_enviada' => $ultima !== null,
            'ultima' => $ultima ? array(
                'resposta' => (string) ($ultima['resposta'] ?? ''),
                'tentativa' => (int) ($ultima['tentativa'] ?? 0),
                'enviado_em' => (string) ($ultima['enviado_em'] ?? ''),
                // Nota/feedback só aparecem quando o backend já os preencheu.
                'nota' => (isset($ultima['nota']) && $ultima['nota'] !== null && $ultima['nota'] !== '')
                    ? number_format((float) $ultima['nota'], 2, ',', '.') : '',
                'feedback' => (string) ($ultima['feedback'] ?? ''),
                'imagens' => $this->avaliacaoService->imagensEntrega((int) ($ultima['id'] ?? 0)),
            ) : null,
        );

        $formCtx = array(
            'inscricao_id' => (int) $inscricao['id'],
            'curso_id' => $cursoId,
            'turma_id' => $turmaId,
            'modulo_id' => $moduloId,
            'item_id' => $itemId,
            'enviar_action' => '/v2/atividade/enviar',
        );

        return $this->render($base, $cabecalho, $atividade, $formCtx, $voltarAulaUrl, 200);
    }

    /**
     * POST /v2/atividade/enviar — envia a resposta textual real (delegado ao
     * service). CSRF automático no `$app->post`. PRG para a própria V2.
     */
    public function enviar(Request $request)
    {
        $usuarioId = (int) Session::get('usuario_id', 0);
        if ($usuarioId <= 0) {
            return Response::redirect('/v2/login?origem=v2_aluno');
        }

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
            return Response::redirect('/v2/aluno/');
        }

        $inscricao = $contexto['inscricao'];
        $cursoId = (int) $inscricao['curso_evento_id'];
        $turmaId = !empty($inscricao['turma_id']) ? (int) $inscricao['turma_id'] : 0;

        if (($cursoIdParam > 0 && $cursoIdParam !== $cursoId)
            || ($turmaIdParam > 0 && ($turmaId <= 0 || $turmaIdParam !== $turmaId))) {
            Session::flash('errors', array('Acesso negado.'));
            return Response::redirect('/v2/aluno/');
        }

        if ($itemId <= 0) {
            Session::flash('errors', array('Atividade inválida.'));
            return Response::redirect('/v2/aluno/');
        }

        $detalhe = $this->conteudoService->buscarItemPublicadoParaAluno(
            $itemId,
            $usuarioId,
            (int) $inscricao['id'],
            $cursoId,
            $turmaId > 0 ? $turmaId : null
        );
        if (empty($detalhe['ok']) || (string) ($detalhe['item']['tipo'] ?? '') !== 'avaliacao_textual') {
            Session::flash('errors', array('Atividade não encontrada.'));
            return Response::redirect($this->urlAtividadeV2($inscricao, $cursoId, $turmaId, $moduloIdParam, $itemId));
        }

        $moduloId = (int) ($detalhe['modulo']['id'] ?? $moduloIdParam);

        // Regra de negócio 100% no service real (validação mínima, reenvio,
        // status, log, progresso, notificação). Nada é reimplementado aqui.
        $resultado = $this->avaliacaoService->enviarResposta(array(
            'item_id' => (int) $detalhe['item']['id'],
            'avaliacao_id' => (int) ($detalhe['detalhe']['id'] ?? 0),
            'curso_evento_id' => $cursoId,
            'turma_id' => $turmaId > 0 ? $turmaId : null,
            'inscricao_id' => (int) $inscricao['id'],
            'aluno_id' => $usuarioId,
            'resposta' => (string) $request->input('resposta', ''),
            'imagens' => \App\Core\Helpers::normalizarUploadMultiplo(isset($_FILES['imagens']) ? $_FILES['imagens'] : null),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ));

        if (empty($resultado['ok'])) {
            Session::flash('errors', array(isset($resultado['message']) ? $resultado['message'] : 'Não foi possível enviar a resposta.'));
        } else {
            Session::flash('success', (string) ($resultado['status'] ?? '') === 'reenviada' ? 'Resposta reenviada com sucesso.' : 'Resposta enviada com sucesso.');
        }

        return Response::redirect($this->urlAtividadeV2($inscricao, $cursoId, $turmaId, $moduloId, $itemId));
    }

    private function mapaStatus($status)
    {
        switch ($status) {
            case 'enviada':
            case 'reenviada':
                return 'em_correcao';
            case 'devolvida':
                return 'devolvida';
            case 'corrigida':
                return 'corrigida';
            case 'aprovada':
                return 'aprovada';
            case 'reprovada':
                return 'reprovada';
            case 'cancelada':
                return 'cancelada';
            default:
                return 'nao_enviada';
        }
    }

    private function rotuloStatus($code)
    {
        $mapa = array(
            'nao_enviada' => 'Não enviada',
            'em_correcao' => 'Em correção',
            'devolvida' => 'Devolvida para ajustes',
            'corrigida' => 'Corrigida',
            'aprovada' => 'Aprovada',
            'reprovada' => 'Reprovada',
            'cancelada' => 'Cancelada',
        );
        return isset($mapa[$code]) ? $mapa[$code] : 'Não enviada';
    }

    private function render(array $base, array $cabecalho, array $atividade, array $formCtx, $voltarAulaUrl, $status)
    {
        $titulo = $cabecalho['atividade_nome'] !== '' ? $cabecalho['atividade_nome'] : 'Atividade';
        $data = array_merge($base, array(
            'title' => $titulo . ' — Desbloqueia Cursos',
            'pageTitle' => $titulo . ' — Desbloqueia Cursos',
            'pageDescription' => 'Responda a atividade do seu curso.',
            'estado' => null,
            'cabecalho' => $cabecalho,
            'atividade' => $atividade,
            'formCtx' => $formCtx,
            'voltarAulaUrl' => $voltarAulaUrl,
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
        ));
        return new Response(View::render('v2/atividade', $data, false), (int) $status);
    }

    private function urlAtividadeV2(array $inscricao, $cursoId, $turmaId, $moduloId, $itemId)
    {
        return '/v2/atividade?inscricao_id=' . (int) $inscricao['id']
            . '&curso_id=' . (int) $cursoId
            . '&turma_id=' . (int) $turmaId
            . '&modulo_id=' . (int) $moduloId
            . '&conteudo_id=' . (int) $itemId;
    }

    private function urlAulaV2(array $inscricao, $cursoId, $turmaId, $moduloId, $itemId)
    {
        return '/v2/aula/?inscricao_id=' . (int) $inscricao['id']
            . '&curso_id=' . (int) $cursoId
            . '&turma_id=' . (int) $turmaId
            . '&modulo_id=' . (int) $moduloId
            . '&conteudo_id=' . (int) $itemId;
    }

    private function urlOficialItem(array $inscricao, $cursoId, $turmaId, $moduloId, $itemId)
    {
        return '/aluno/curso/' . (int) $inscricao['id'] . '/' . (int) $cursoId . '/' . (int) $turmaId
            . '/modulo/' . (int) $moduloId . '/conteudo/' . (int) $itemId;
    }

    private function valorDetalhe($detalhe, array $chaves)
    {
        if (!is_array($detalhe)) {
            return '';
        }
        foreach ($chaves as $chave) {
            if (isset($detalhe[$chave]) && trim((string) $detalhe[$chave]) !== '') {
                return (string) $detalhe[$chave];
            }
        }
        return '';
    }

    private function estado(array $base, $titulo, $mensagem, $status)
    {
        $data = array_merge($base, array(
            'title' => $titulo . ' — Desbloqueia Cursos',
            'pageTitle' => $titulo . ' — Desbloqueia Cursos',
            'pageDescription' => $mensagem,
            'estado' => array('titulo' => $titulo, 'mensagem' => $mensagem),
            'cabecalho' => null,
            'atividade' => null,
            'formCtx' => array(),
            'voltarAulaUrl' => '/v2/aluno/',
        ));
        return new Response(View::render('v2/atividade', $data, false), (int) $status);
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
