<?php

namespace App\Controllers\V2;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\CategoriaService;
use App\Services\ConfiguracaoGlobalService;
use App\Services\CursoService;

class HomeController extends Controller
{
    private $cursoService;
    private $categoriaService;
    private $configuracaoGlobalService;

    public function __construct()
    {
        $this->cursoService = new CursoService();
        $this->categoriaService = new CategoriaService();
        $this->configuracaoGlobalService = new ConfiguracaoGlobalService();
    }

    public function index(Request $request)
    {
        $usuarioId = (int) Session::get('usuario_id', 0);
        $usuarioNome = trim((string) Session::get('usuario_nome', ''));
        $sessionPerfis = Session::get('usuario_perfis', array());
        $hasAdminAccess = (bool) Session::get('usuario_admin') || (bool) Session::get('is_admin') || in_array('admin', $sessionPerfis, true);
        $hasProfessorAccess = (bool) Session::get('usuario_professor') || (bool) Session::get('is_professor') || in_array('professor', $sessionPerfis, true);

        $limiteDestaques = (int) $this->configuracaoGlobalService->homeDestaquesLimite();
        $limiteCategorias = (int) $this->configuracaoGlobalService->homeCategoriasLimite();

        $featuredCourses = $this->normalizarCursos($this->cursoService->listPublicHome($limiteDestaques));
        $topCourses = $this->normalizarCursos($this->cursoService->listPublicTopVendas(5), true);
        $categories = $this->normalizarCategorias($this->categoriaService->listPublicHome($limiteCategorias));
        $heroStats = $this->cursoService->homeStats();

        $heroTitulo = 'Quando aprende de verdade, desbloqueia.';
        $heroSubtitulo = 'Cursos práticos com certificado, turmas ao vivo e acompanhamento. Aprenda no seu ritmo, pelo computador ou celular.';
        if ($usuarioId > 0) {
            $primeiroNome = $this->primeiroNome($usuarioNome);
            $heroTitulo = 'Olá, ' . $primeiroNome . '. Sua jornada continua aqui.';
            $heroSubtitulo = '';
        }

        $pageTitle = 'Desbloqueia Cursos — Quando aprende de verdade, desbloqueia.';
        $pageDescription = 'Home V2 com dados reais do catálogo atual: cursos em destaque, top cursos, categorias, preços, modalidades e imagens públicas do sistema.';

        $data = array(
            'title' => $pageTitle,
            'pageTitle' => $pageTitle,
            'pageDescription' => $pageDescription,
            'heroTitulo' => $heroTitulo,
            'heroSubtitulo' => $heroSubtitulo,
            'heroStats' => is_array($heroStats) ? $heroStats : null,
            'featuredCourses' => $featuredCourses,
            'topCourses' => $topCourses,
            'categories' => $categories,
            'loggedIn' => $usuarioId > 0,
            'usuarioNome' => $usuarioNome,
            'usuarioPrimeiroNome' => $usuarioId > 0 ? $this->primeiroNome($usuarioNome) : '',
            'areaHref' => $this->resolveAreaHref($hasAdminAccess, $hasProfessorAccess),
            'loginHref' => '/v2/login',
            'registerHref' => '/v2/cadastro',
            'catalogoHref' => '/cursos',
            'categoriasHref' => '/categorias',
            'certificadosHref' => '/v2/certificados/validar',
            'sobreHref' => '/sobre',
            'contatoHref' => '/contato',
            'homeHref' => '/v2/',
            'disableV2AutoRenderHome' => true,
            'coursePalette' => $this->coursePalette(),
            'success' => Session::pullFlash('success'),
        );

        return new Response(View::render('v2/home', $data, false));
    }

    private function normalizarCursos(array $cursos, $ehTop = false)
    {
        $normalizados = array();
        foreach ($cursos as $indice => $curso) {
            if (!is_array($curso)) {
                continue;
            }

            $categoriaNome = isset($curso['categoria_nome']) ? trim((string) $curso['categoria_nome']) : '';
            $professorNome = $this->resolverProfessorNome($curso);
            $valorEfetivo = $this->valorEfetivo($curso);
            $valorOriginal = isset($curso['valor']) ? (float) $curso['valor'] : 0.0;
            $desconto = $this->descontoPromocional($curso);
            $thumbnail = isset($curso['thumbnail']) ? trim((string) $curso['thumbnail']) : '';
            $modalidade = isset($curso['modalidade']) ? (string) $curso['modalidade'] : '';

            $normalizados[] = array(
                'id' => isset($curso['id']) ? (int) $curso['id'] : 0,
                'slug' => isset($curso['slug']) ? (string) $curso['slug'] : '',
                'titulo' => isset($curso['nome']) ? (string) $curso['nome'] : '',
                'descricaoCurta' => isset($curso['descricao_curta']) ? trim((string) $curso['descricao_curta']) : '',
                'categoria' => $categoriaNome !== '' ? $categoriaNome : 'Sem categoria',
                'categoria_slug' => isset($curso['categoria_slug']) ? (string) $curso['categoria_slug'] : '',
                'modalidade' => $this->modalidadeLabel($modalidade),
                'modalidade_codigo' => $modalidade,
                'cargaHoraria' => isset($curso['carga_horaria']) ? (int) $curso['carga_horaria'] : 0,
                'preco' => $valorEfetivo,
                'precoOriginal' => $valorOriginal,
                'precoFormatado' => $this->formatarMoeda($valorEfetivo),
                'precoOriginalFormatado' => $valorOriginal > 0 ? $this->formatarMoeda($valorOriginal) : null,
                'descontoPromocional' => $desconto,
                'nota' => isset($curso['nota']) ? (float) $curso['nota'] : 0.0,
                'avaliacoes' => isset($curso['avaliacoes']) ? (int) $curso['avaliacoes'] : (isset($curso['total_avaliacoes']) ? (int) $curso['total_avaliacoes'] : 0),
                'alunos' => isset($curso['total_vendas']) ? (int) $curso['total_vendas'] : (isset($curso['total_alunos']) ? (int) $curso['total_alunos'] : (isset($curso['alunos']) ? (int) $curso['alunos'] : 0)),
                'total_turmas_abertas' => isset($curso['total_turmas_abertas']) ? (int) $curso['total_turmas_abertas'] : (isset($curso['total_turmas']) ? (int) $curso['total_turmas'] : 0),
                'destaque' => !empty($curso['destaque']),
                'novo' => !empty($curso['novo']),
                'tipo' => isset($curso['tipo']) ? (string) $curso['tipo'] : 'curso',
                'imagem' => $thumbnail !== '' ? $thumbnail : null,
                'thumbnail' => $thumbnail !== '' ? $thumbnail : null,
                'professor' => $professorNome,
                'url' => '/cursos/detalhe?curso_id=' . (int) ($curso['id'] ?? 0),
                'eh_top' => $ehTop,
            );
        }

        return $normalizados;
    }

    private function normalizarCategorias(array $categorias)
    {
        $normalizadas = array();
        $paleta = $this->coursePalette();

        foreach ($categorias as $indice => $categoria) {
            if (!is_array($categoria)) {
                continue;
            }

            $tema = $paleta[$indice % count($paleta)];
            $normalizadas[] = array(
                'id' => isset($categoria['id']) ? (int) $categoria['id'] : 0,
                'slug' => isset($categoria['slug']) ? (string) $categoria['slug'] : '',
                'nome' => isset($categoria['nome']) ? (string) $categoria['nome'] : '',
                'descricao' => isset($categoria['descricao']) ? trim((string) $categoria['descricao']) : '',
                'total_cursos' => isset($categoria['total_cursos']) ? (int) $categoria['total_cursos'] : 0,
                'thumbnail' => !empty($categoria['thumbnail']) ? (string) $categoria['thumbnail'] : null,
                'url' => !empty($categoria['slug'])
                    ? '/categorias/' . rawurlencode((string) $categoria['slug']) . '/cursos'
                    : '/categorias',
                'icon' => $tema['icon'],
                'g1' => $tema['g1'],
                'g2' => $tema['g2'],
                'cor' => $tema['cor'],
            );
        }

        return $normalizadas;
    }

    private function resolverProfessorNome(array $curso)
    {
        if (!empty($curso['professor_responsavel']) && is_array($curso['professor_responsavel']) && !empty($curso['professor_responsavel']['nome'])) {
            return trim((string) $curso['professor_responsavel']['nome']);
        }

        if (!empty($curso['professores_responsaveis']) && is_array($curso['professores_responsaveis'])) {
            foreach ($curso['professores_responsaveis'] as $professor) {
                if (is_array($professor) && !empty($professor['nome'])) {
                    return trim((string) $professor['nome']);
                }
            }
        }

        if (!empty($curso['pessoas_vinculadas']) && is_array($curso['pessoas_vinculadas'])) {
            foreach ($curso['pessoas_vinculadas'] as $pessoa) {
                if (!is_array($pessoa)) {
                    continue;
                }
                if (!empty($pessoa['tipo_pessoa']) && (string) $pessoa['tipo_pessoa'] !== 'professor') {
                    continue;
                }
                if (!empty($pessoa['nome'])) {
                    return trim((string) $pessoa['nome']);
                }
            }
        }

        return '';
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

    private function modalidadeLabel($modalidade)
    {
        $map = array(
            'presencial' => 'Presencial',
            'online_ao_vivo' => 'Online ao vivo',
            'hibrido' => 'Híbrido',
            'sob_demanda' => 'Sob demanda',
        );

        return isset($map[$modalidade]) ? $map[$modalidade] : ucfirst(str_replace('_', ' ', (string) $modalidade));
    }

    private function valorEfetivo(array $curso)
    {
        $valor = isset($curso['valor']) ? (float) $curso['valor'] : 0.0;
        $valorPromocional = isset($curso['valor_promocional']) && $curso['valor_promocional'] !== '' ? (float) $curso['valor_promocional'] : null;
        $emPromocao = !empty($curso['em_promocao']);

        if ($emPromocao && $valorPromocional !== null && $valorPromocional >= 0 && $valorPromocional < $valor) {
            return $valorPromocional;
        }

        return $valor;
    }

    private function descontoPromocional(array $curso)
    {
        $valor = isset($curso['valor']) ? (float) $curso['valor'] : 0.0;
        $valorEfetivo = $this->valorEfetivo($curso);

        if ($valor <= 0 || $valorEfetivo >= $valor) {
            return null;
        }

        return array(
            'valor_original' => $valor,
            'valor_promocional' => $valorEfetivo,
            'desconto_valor' => $valor - $valorEfetivo,
            'desconto_percentual' => (($valor - $valorEfetivo) / $valor) * 100,
        );
    }

    private function formatarMoeda($valor)
    {
        $valor = (float) $valor;
        if ($valor <= 0) {
            return 'Grátis';
        }

        return 'R$ ' . number_format($valor, 2, ',', '.');
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
