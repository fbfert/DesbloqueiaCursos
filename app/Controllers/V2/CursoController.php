<?php

namespace App\Controllers\V2;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\CursoEvento;
use App\Services\CursoService;

/**
 * Ficha de Curso V2 integrada com dados reais (Fase 2.3).
 *
 * Rota canônica: GET /v2/curso/?curso_id=ID_REAL
 *
 * Reaproveita exatamente a mesma regra de negócio da ficha pública atual
 * (`CursosController@show` → `CursoService::showPublic`), que já valida curso
 * público/ativo, carrega turmas abertas, calcula valor efetivo/desconto e
 * sanitiza o conteúdo programático. Não há SQL novo, sessão alterada, inscrição
 * criada nem checkout iniciado nesta etapa.
 *
 * A ficha pública original (/cursos/detalhe) permanece intacta.
 */
class CursoController extends Controller
{
    /** @var CursoService */
    private $cursoService;
    /** @var CursoEvento */
    private $cursoModel;

    public function __construct()
    {
        $this->cursoService = new CursoService();
        $this->cursoModel = new CursoEvento();
    }

    public function index(Request $request)
    {
        $cursoId = (int) $request->query('curso_id', 0);
        $turmaId = (int) $request->query('turma_id', 0);
        $idLegado = trim((string) $request->query('id', ''));

        // --- Compatibilidade com a URL demonstrativa antiga (?id=slug) ---
        // Só redireciona quando o slug resolve para um curso público REAL.
        // Slug fictício (ex.: ?id=direito-consumidor sem curso real) cai no
        // estado amigável, sem exibir dados demonstrativos.
        if ($cursoId <= 0 && $idLegado !== '') {
            if (ctype_digit($idLegado)) {
                return Response::redirect($this->montarUrlCanonica((int) $idLegado, $turmaId));
            }

            $resolvido = $this->resolverCursoPublicoPorSlug($idLegado);
            if ($resolvido > 0) {
                return Response::redirect($this->montarUrlCanonica($resolvido, $turmaId));
            }

            return $this->renderEstadoIndisponivel(
                'Curso não encontrado',
                'O endereço utilizado é de uma demonstração antiga e não corresponde a um curso real disponível.',
                404
            );
        }

        // --- Sem parâmetro: estado amigável convidando ao catálogo ---
        if ($cursoId <= 0) {
            return $this->renderEstadoIndisponivel(
                'Selecione um curso',
                'Escolha um curso no catálogo para ver todos os detalhes, turmas abertas e formas de inscrição.',
                200
            );
        }

        // --- Carrega o curso público real (mesma regra da ficha atual) ---
        $contexto = $this->cursoService->showPublic($cursoId, $turmaId > 0 ? $turmaId : null);
        $curso = isset($contexto['curso']) ? $contexto['curso'] : null;

        if (empty($curso)) {
            return $this->renderEstadoIndisponivel(
                'Curso indisponível',
                'Este curso não está disponível publicamente no momento. Ele pode ter sido despublicado ou não existe.',
                404
            );
        }

        $dados = $this->montarDadosCurso($curso);

        $usuarioId = (int) Session::get('usuario_id', 0);
        $usuarioNome = trim((string) Session::get('usuario_nome', ''));
        $sessionPerfis = Session::get('usuario_perfis', array());
        $hasAdminAccess = (bool) Session::get('usuario_admin') || (bool) Session::get('is_admin') || in_array('admin', $sessionPerfis, true);
        $hasProfessorAccess = (bool) Session::get('usuario_professor') || (bool) Session::get('is_professor') || in_array('professor', $sessionPerfis, true);

        $data = array_merge($this->dadosLayout($usuarioId, $usuarioNome, $hasAdminAccess, $hasProfessorAccess), array(
            'title' => $dados['titulo'] . ' — Desbloqueia Cursos',
            'pageTitle' => $dados['titulo'] . ' — Desbloqueia Cursos',
            'pageDescription' => $dados['resumo'] !== '' ? $dados['resumo'] : ('Detalhes do curso ' . $dados['titulo'] . ' na Desbloqueia Cursos.'),
            'estadoIndisponivel' => null,
            'curso' => $dados,
            'success' => Session::pullFlash('success'),
        ));

        return new Response(View::render('v2/curso', $data, false));
    }

    /**
     * Resolve um slug para o ID de um curso PÚBLICO real (ativo).
     * Reaproveita métodos existentes do model (sem SQL novo): localiza por slug
     * e revalida pela consulta pública (status/soft-delete). Retorna 0 se não
     * houver curso público correspondente.
     */
    private function resolverCursoPublicoPorSlug($slug)
    {
        $slug = trim((string) $slug);
        if ($slug === '') {
            return 0;
        }

        $curso = $this->cursoModel->findBySlug($slug);
        if (!$curso || empty($curso['id'])) {
            return 0;
        }

        $publico = $this->cursoModel->findPublicById((int) $curso['id']);
        if (!$publico || empty($publico['id'])) {
            return 0;
        }

        return (int) $publico['id'];
    }

    private function montarUrlCanonica($cursoId, $turmaId = 0)
    {
        $url = '/v2/curso/?curso_id=' . (int) $cursoId;
        if ((int) $turmaId > 0) {
            $url .= '&turma_id=' . (int) $turmaId;
        }

        return $url;
    }

    /**
     * Normaliza o curso real para a view, expondo apenas dados públicos.
     */
    private function montarDadosCurso(array $curso)
    {
        $id = isset($curso['id']) ? (int) $curso['id'] : 0;
        $nome = isset($curso['nome']) ? (string) $curso['nome'] : '';
        $categoriaNome = isset($curso['categoria_nome']) ? trim((string) $curso['categoria_nome']) : '';
        $categoriaSlug = isset($curso['categoria_slug']) ? trim((string) $curso['categoria_slug']) : '';
        $descricaoCurta = isset($curso['descricao_curta']) ? trim((string) $curso['descricao_curta']) : '';
        $descricaoCompleta = isset($curso['descricao_completa']) ? trim((string) $curso['descricao_completa']) : '';
        $modalidade = isset($curso['modalidade']) ? (string) $curso['modalidade'] : '';
        $cargaHoraria = isset($curso['carga_horaria']) ? (int) $curso['carga_horaria'] : 0;
        $thumbnail = !empty($curso['thumbnail']) ? (string) $curso['thumbnail'] : null;

        $valor = isset($curso['valor']) ? (float) $curso['valor'] : 0.0;
        $valorEfetivo = isset($curso['valor_efetivo']) ? (float) $curso['valor_efetivo'] : $valor;
        $desconto = isset($curso['desconto_promocional']) && is_array($curso['desconto_promocional']) ? $curso['desconto_promocional'] : null;

        $turmasAbertas = isset($curso['turmas_abertas']) && is_array($curso['turmas_abertas']) ? $curso['turmas_abertas'] : array();
        $turmaSelecionada = isset($curso['turma_selecionada']) && is_array($curso['turma_selecionada']) ? $curso['turma_selecionada'] : null;
        $turmaSelecionadaId = $turmaSelecionada && !empty($turmaSelecionada['id']) ? (int) $turmaSelecionada['id'] : 0;

        $turmas = array();
        foreach ($turmasAbertas as $turma) {
            if (!is_array($turma) || empty($turma['id'])) {
                continue;
            }
            $turmaIdAtual = (int) $turma['id'];
            $turmas[] = array(
                'id' => $turmaIdAtual,
                'nome' => isset($turma['nome']) ? (string) $turma['nome'] : '',
                'codigo' => isset($turma['codigo']) ? (string) $turma['codigo'] : '',
                'data_inicio' => isset($turma['data_inicio']) ? $this->formatarData($turma['data_inicio']) : '',
                'data_fim' => isset($turma['data_fim']) ? $this->formatarData($turma['data_fim']) : '',
                'local' => isset($turma['local']) ? trim((string) $turma['local']) : '',
                'vagas' => isset($turma['vagas']) && $turma['vagas'] !== null && $turma['vagas'] !== '' ? (int) $turma['vagas'] : null,
                'status' => isset($turma['status']) ? (string) $turma['status'] : '',
                'selecionada' => $turmaSelecionadaId === $turmaIdAtual,
                'inscricao_href' => $this->montarHrefInscricao($id, $turmaIdAtual),
                'ficha_href' => $this->montarUrlCanonica($id, $turmaIdAtual),
            );
        }

        // Conteúdo programático já sanitizado pelo service (texto/html/módulos).
        $conteudoProgramatico = isset($curso['conteudo_programatico_view']) && is_array($curso['conteudo_programatico_view'])
            ? $curso['conteudo_programatico_view']
            : array('tipo' => 'texto', 'texto' => null, 'html' => null, 'modulos' => array());

        // CTA principal: rota oficial de inscrição/checkout já usada pela ficha
        // pública. Preserva turma selecionada quando houver.
        $ctaTurmaId = $turmaSelecionadaId > 0 ? $turmaSelecionadaId : (!empty($turmas[0]['id']) ? (int) $turmas[0]['id'] : 0);

        return array(
            'id' => $id,
            'titulo' => $nome,
            'categoria' => $categoriaNome,
            'categoria_slug' => $categoriaSlug,
            'categoria_catalogo_href' => $categoriaSlug !== '' ? ('/v2/catalogo/?categoria=' . rawurlencode($categoriaSlug)) : '/v2/catalogo/',
            'resumo' => $descricaoCurta,
            'descricao' => $descricaoCompleta !== '' ? $descricaoCompleta : $descricaoCurta,
            'modalidade' => $this->cursoService->modalidadeLabel($modalidade),
            'modalidade_codigo' => $modalidade,
            'cargaHoraria' => $cargaHoraria,
            'thumbnail' => $thumbnail,
            'destaque' => !empty($curso['destaque']),
            'tipo' => isset($curso['tipo']) ? (string) $curso['tipo'] : 'curso',
            'valor' => $valor,
            'valorEfetivo' => $valorEfetivo,
            'precoFormatado' => $this->formatarMoeda($valorEfetivo),
            'precoOriginalFormatado' => ($desconto && $valor > 0) ? $this->formatarMoeda($valor) : null,
            'desconto' => $desconto,
            'professores' => $this->extrairProfessores($curso),
            'objetivo_geral' => isset($curso['objetivo_geral']) ? trim((string) $curso['objetivo_geral']) : '',
            'objetivos_especificos' => $this->linhas(isset($curso['objetivos_especificos']) ? $curso['objetivos_especificos'] : ''),
            'publico_alvo' => isset($curso['publico_alvo']) ? trim((string) $curso['publico_alvo']) : '',
            'pre_requisitos_texto' => isset($curso['pre_requisitos_texto']) ? trim((string) $curso['pre_requisitos_texto']) : '',
            'pre_requisitos_itens' => $this->linhas(isset($curso['pre_requisitos_itens']) ? $curso['pre_requisitos_itens'] : ''),
            'ementa' => isset($curso['ementa']) ? trim((string) $curso['ementa']) : '',
            'metodologia' => isset($curso['metodologia']) ? trim((string) $curso['metodologia']) : '',
            'produto_final' => $this->linhas(isset($curso['produto_final']) ? $curso['produto_final'] : ''),
            'avaliacao' => isset($curso['avaliacao']) ? trim((string) $curso['avaliacao']) : '',
            'conteudo_programatico' => $conteudoProgramatico,
            'turmas' => $turmas,
            'turma_selecionada_id' => $turmaSelecionadaId,
            'inscricao_disponivel' => !empty($curso['inscricao_disponivel']),
            'cta_href' => $this->montarHrefInscricao($id, $ctaTurmaId),
            'cta_turma_id' => $ctaTurmaId,
        );
    }

    private function extrairProfessores(array $curso)
    {
        $nomes = array();

        if (!empty($curso['professores_responsaveis']) && is_array($curso['professores_responsaveis'])) {
            foreach ($curso['professores_responsaveis'] as $professor) {
                if (is_array($professor) && !empty($professor['nome'])) {
                    $nomes[] = trim((string) $professor['nome']);
                }
            }
        }

        if (empty($nomes) && !empty($curso['professor_responsavel']['nome'])) {
            $nomes[] = trim((string) $curso['professor_responsavel']['nome']);
        }

        return array_values(array_unique(array_filter($nomes, function ($n) {
            return $n !== '';
        })));
    }

    private function montarHrefInscricao($cursoId, $turmaId = 0)
    {
        // Entra no Checkout V2 (Fase 2.12A) quando há curso válido. A própria
        // entrada V2 revalida curso/turma e redireciona com segurança quando a
        // turma é exigida e não foi escolhida. Sem curso válido, mantém a rota
        // oficial atual como fallback.
        if ((int) $cursoId <= 0) {
            return '/inscricao';
        }

        $href = '/v2/checkout/inscricao?curso_id=' . (int) $cursoId;
        if ((int) $turmaId > 0) {
            $href .= '&turma_id=' . (int) $turmaId;
        }

        return $href;
    }

    private function linhas($valor)
    {
        if ($valor === null) {
            return array();
        }

        $partes = preg_split('/\r\n|\r|\n/', (string) $valor);
        $limpas = array();
        foreach ($partes as $linha) {
            $linha = trim((string) $linha);
            if ($linha !== '') {
                $limpas[] = $linha;
            }
        }

        return $limpas;
    }

    private function formatarData($valor)
    {
        $valor = trim((string) $valor);
        if ($valor === '') {
            return '';
        }

        // Aceita YYYY-MM-DD (e variações com hora) sem alterar fuso/dados.
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $valor, $m)) {
            return $m[3] . '/' . $m[2] . '/' . $m[1];
        }

        return $valor;
    }

    private function formatarMoeda($valor)
    {
        $valor = (float) $valor;
        if ($valor <= 0) {
            return 'Gratuito';
        }

        return 'R$ ' . number_format($valor, 2, ',', '.');
    }

    private function renderEstadoIndisponivel($titulo, $mensagem, $status = 200)
    {
        $usuarioId = (int) Session::get('usuario_id', 0);
        $usuarioNome = trim((string) Session::get('usuario_nome', ''));
        $sessionPerfis = Session::get('usuario_perfis', array());
        $hasAdminAccess = (bool) Session::get('usuario_admin') || (bool) Session::get('is_admin') || in_array('admin', $sessionPerfis, true);
        $hasProfessorAccess = (bool) Session::get('usuario_professor') || (bool) Session::get('is_professor') || in_array('professor', $sessionPerfis, true);

        $data = array_merge($this->dadosLayout($usuarioId, $usuarioNome, $hasAdminAccess, $hasProfessorAccess), array(
            'title' => $titulo . ' — Desbloqueia Cursos',
            'pageTitle' => $titulo . ' — Desbloqueia Cursos',
            'pageDescription' => $mensagem,
            'estadoIndisponivel' => array(
                'titulo' => $titulo,
                'mensagem' => $mensagem,
            ),
            'curso' => null,
            'success' => null,
        ));

        return new Response(View::render('v2/curso', $data, false), (int) $status);
    }

    private function dadosLayout($usuarioId, $usuarioNome, $hasAdminAccess, $hasProfessorAccess)
    {
        return array(
            'loggedIn' => $usuarioId > 0,
            'usuarioNome' => $usuarioNome,
            'usuarioPrimeiroNome' => $usuarioId > 0 ? $this->primeiroNome($usuarioNome) : '',
            'areaHref' => $this->resolveAreaHref($hasAdminAccess, $hasProfessorAccess),
            'loginHref' => '/v2/login',
            'registerHref' => '/v2/cadastro',
            'catalogoHref' => '/v2/catalogo/',
            'categoriasHref' => '/categorias',
            'certificadosHref' => '/v2/certificados/validar/',
            'sobreHref' => '/sobre',
            'contatoHref' => '/contato',
            'homeHref' => '/v2/',
            'coursePalette' => $this->coursePalette(),
        );
    }

    private function primeiroNome($nome)
    {
        $nome = trim((string) $nome);
        if ($nome === '') {
            return 'visitante';
        }

        $partes = preg_split('/\s+/', $nome);
        if (!$partes || empty($partes[0])) {
            return $nome;
        }

        return (string) $partes[0];
    }

    private function resolveAreaHref($hasAdminAccess, $hasProfessorAccess)
    {
        if ($hasAdminAccess) {
            return '/admin';
        }

        if ($hasProfessorAccess) {
            return '/professor/dashboard';
        }

        return '/v2/aluno';
    }

    private function coursePalette()
    {
        return array(
            array('icon' => 'ti-scale', 'g1' => '#fff4ec', 'g2' => '#ffe4d3', 'cor' => '#cc5500'),
            array('icon' => 'ti-speakerphone', 'g1' => '#f0e8ff', 'g2' => '#e4d6ff', 'cor' => '#4B008E'),
            array('icon' => 'ti-chart-bar', 'g1' => '#d1faf5', 'g2' => '#b8f2ea', 'cor' => '#007a6a'),
            array('icon' => 'ti-device-laptop', 'g1' => '#e3f0ff', 'g2' => '#cfe4ff', 'cor' => '#1d4ed8'),
            array('icon' => 'ti-microphone', 'g1' => '#ffe9f0', 'g2' => '#ffd6e3', 'cor' => '#c00057'),
            array('icon' => 'ti-briefcase', 'g1' => '#eef7df', 'g2' => '#dcefc0', 'cor' => '#3b6d11'),
        );
    }
}
