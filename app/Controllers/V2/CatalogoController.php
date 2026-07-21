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

/**
 * Catálogo V2 integrado com dados reais (Fase 2.2).
 *
 * Reaproveita exatamente os mesmos serviços públicos do catálogo atual
 * (CursoService::listPublic / CategoriaService::listPublic), aplicando os
 * mesmos critérios de publicação e visibilidade. Busca, categoria e destaque
 * são filtrados no backend (SQL existente); modalidade, faixa de preço, carga
 * horária, ordenação e paginação são aplicados no servidor sobre os dados reais
 * já carregados — sem SQL novo, sem API e sem filtro client-side.
 *
 * A rota original /cursos e todos os demais fluxos permanecem intactos.
 */
class CatalogoController extends Controller
{
    /** @var CursoService */
    private $cursoService;
    /** @var CategoriaService */
    private $categoriaService;
    /** @var ConfiguracaoGlobalService */
    private $configuracaoGlobalService;

    const POR_PAGINA = 9;

    private $modalidadesPermitidas = array(
        'presencial',
        'online_ao_vivo',
        'hibrido',
        'sob_demanda',
    );

    private $precosPermitidos = array('gratis', 'ate100', '100a150', 'acima150');
    private $cargasPermitidas = array('ate10', '11a30', 'acima30');
    private $ordensPermitidas = array('relevancia', 'preco-asc', 'preco-desc', 'alfabetica');

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

        // --- Entrada (GET, normalizada e validada) ---
        $busca = trim((string) $request->query('busca', ''));
        $busca = trim(strip_tags(mb_substr($busca, 0, 80)));

        $categoriaSlug = trim((string) $request->query('categoria', ''));
        $categoriaSlug = mb_substr($categoriaSlug, 0, 120);

        $modalidade = (string) $request->query('modalidade', '');
        if (!in_array($modalidade, $this->modalidadesPermitidas, true)) {
            $modalidade = '';
        }

        $preco = (string) $request->query('preco', '');
        if (!in_array($preco, $this->precosPermitidos, true)) {
            $preco = '';
        }

        $carga = (string) $request->query('carga', '');
        if (!in_array($carga, $this->cargasPermitidas, true)) {
            $carga = '';
        }

        $destaque = (string) $request->query('destaque', '') === '1';

        $ordem = (string) $request->query('ordem', 'relevancia');
        if (!in_array($ordem, $this->ordensPermitidas, true)) {
            $ordem = 'relevancia';
        }

        $pagina = (int) $request->query('pagina', 1);
        if ($pagina < 1) {
            $pagina = 1;
        }

        // --- Categorias reais (mesma fonte do catálogo atual) ---
        $categoriasPublicas = $this->categoriaService->listPublic();
        $categoriasPublicas = isset($categoriasPublicas['categorias']) && is_array($categoriasPublicas['categorias'])
            ? $categoriasPublicas['categorias']
            : array();

        // --- Filtros aplicados no backend (SQL existente) ---
        $filters = array();
        $categoriaSelecionada = null;
        if ($categoriaSlug !== '') {
            $categoria = $this->categoriaService->findPublicBySlug($categoriaSlug);
            if ($categoria) {
                $categoriaSelecionada = $categoria;
                $filters['categoria_id'] = (int) $categoria['id'];
            } else {
                // slug inexistente: não força filtro inválido, mas registra estado
                $categoriaSlug = '';
            }
        }
        if ($busca !== '') {
            $filters['busca'] = $busca;
        }
        if ($destaque) {
            $filters['destaque'] = 1;
        }

        $contexto = $this->cursoService->listPublic($filters);
        $cursosBrutos = isset($contexto['cursos']) && is_array($contexto['cursos']) ? $contexto['cursos'] : array();

        // --- Filtros refinados no servidor sobre dados reais já carregados ---
        $cursosFiltrados = array();
        foreach ($cursosBrutos as $curso) {
            if (!is_array($curso)) {
                continue;
            }
            if ($modalidade !== '' && (string) ($curso['modalidade'] ?? '') !== $modalidade) {
                continue;
            }
            if ($preco !== '' && !$this->precoNaFaixa($this->valorEfetivo($curso), $preco)) {
                continue;
            }
            if ($carga !== '' && !$this->cargaNaFaixa((int) ($curso['carga_horaria'] ?? 0), $carga)) {
                continue;
            }
            $cursosFiltrados[] = $curso;
        }

        // --- Ordenação no servidor ---
        $cursosFiltrados = $this->ordenar($cursosFiltrados, $ordem);

        // --- Paginação no servidor ---
        $totalCursos = count($cursosFiltrados);
        $totalPaginas = (int) max(1, ceil($totalCursos / self::POR_PAGINA));
        if ($pagina > $totalPaginas) {
            $pagina = $totalPaginas;
        }
        $offset = ($pagina - 1) * self::POR_PAGINA;
        $cursosPagina = array_slice($cursosFiltrados, $offset, self::POR_PAGINA);

        $cursos = $this->normalizarCursos($cursosPagina);

        // Estado de filtros usado para preservar parâmetros nos links/forms
        $estado = array(
            'busca' => $busca,
            'categoria' => $categoriaSlug,
            'modalidade' => $modalidade,
            'preco' => $preco,
            'carga' => $carga,
            'destaque' => $destaque,
            'ordem' => $ordem,
        );

        $chips = $this->montarChips($categoriasPublicas, $categoriaSlug, $estado);
        $modalidadesView = $this->montarModalidades($modalidade);
        $paginacao = $this->montarPaginacao($pagina, $totalPaginas, $estado);

        $tituloCategoria = $categoriaSelecionada && !empty($categoriaSelecionada['nome'])
            ? (string) $categoriaSelecionada['nome']
            : '';

        $data = array(
            'title' => $tituloCategoria !== '' ? ('Cursos de ' . $tituloCategoria . ' — Desbloqueia Cursos') : 'Todos os cursos — Desbloqueia Cursos',
            'pageTitle' => $tituloCategoria !== '' ? ('Cursos de ' . $tituloCategoria . ' — Desbloqueia Cursos') : 'Todos os cursos — Desbloqueia Cursos',
            'pageDescription' => 'Catálogo V2 com dados reais: busque cursos, filtre por categoria, modalidade, preço e carga horária, ordene e encontre o curso ideal.',

            'cursos' => $cursos,
            'totalCursos' => $totalCursos,
            'categoriaSelecionada' => $categoriaSelecionada,
            'tituloCategoria' => $tituloCategoria,
            'chips' => $chips,
            'modalidadesView' => $modalidadesView,
            'paginacao' => $paginacao,
            'estado' => $estado,
            'coursePalette' => $this->coursePalette(),

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
            'success' => Session::pullFlash('success'),
        );

        return new Response(View::render('v2/catalogo', $data, false));
    }

    private function valorEfetivo(array $curso)
    {
        if (isset($curso['valor_efetivo'])) {
            return (float) $curso['valor_efetivo'];
        }

        $valor = isset($curso['valor']) ? (float) $curso['valor'] : 0.0;
        $valorPromocional = isset($curso['valor_promocional']) && $curso['valor_promocional'] !== '' ? (float) $curso['valor_promocional'] : null;
        $emPromocao = !empty($curso['em_promocao']);

        if ($emPromocao && $valorPromocional !== null && $valorPromocional >= 0 && $valorPromocional < $valor) {
            return $valorPromocional;
        }

        return $valor;
    }

    private function precoNaFaixa($valor, $faixa)
    {
        $valor = (float) $valor;
        switch ($faixa) {
            case 'gratis':
                return $valor <= 0;
            case 'ate100':
                return $valor > 0 && $valor <= 100;
            case '100a150':
                return $valor > 100 && $valor <= 150;
            case 'acima150':
                return $valor > 150;
        }

        return true;
    }

    private function cargaNaFaixa($carga, $faixa)
    {
        $carga = (int) $carga;
        switch ($faixa) {
            case 'ate10':
                return $carga > 0 && $carga <= 10;
            case '11a30':
                return $carga >= 11 && $carga <= 30;
            case 'acima30':
                return $carga > 30;
        }

        return true;
    }

    private function ordenar(array $cursos, $ordem)
    {
        if ($ordem === 'relevancia') {
            // Mantém a ordem do backend (destaque DESC, ordem ASC, nome ASC).
            return $cursos;
        }

        usort($cursos, function ($a, $b) use ($ordem) {
            switch ($ordem) {
                case 'preco-asc':
                    return $this->compararFloat($this->valorEfetivo($a), $this->valorEfetivo($b));
                case 'preco-desc':
                    return $this->compararFloat($this->valorEfetivo($b), $this->valorEfetivo($a));
                case 'alfabetica':
                    return $this->compararTexto((string) ($a['nome'] ?? ''), (string) ($b['nome'] ?? ''));
            }

            return 0;
        });

        return $cursos;
    }

    private function compararFloat($a, $b)
    {
        if ($a == $b) {
            return 0;
        }
        return ($a < $b) ? -1 : 1;
    }

    private function compararTexto($a, $b)
    {
        if (function_exists('strcoll')) {
            $resultado = strcoll($a, $b);
            if ($resultado !== 0) {
                return $resultado < 0 ? -1 : 1;
            }
        }

        return strcasecmp($a, $b);
    }

    private function montarChips(array $categorias, $categoriaSlugAtual, array $estado)
    {
        $chips = array();
        $chips[] = array(
            'nome' => 'Todos',
            'slug' => '',
            'url' => $this->montarUrl(array_merge($estado, array('categoria' => '', 'pagina' => 1))),
            'ativo' => $categoriaSlugAtual === '',
        );

        foreach ($categorias as $categoria) {
            if (!is_array($categoria)) {
                continue;
            }
            $slug = isset($categoria['slug']) ? (string) $categoria['slug'] : '';
            $nome = isset($categoria['nome']) ? (string) $categoria['nome'] : '';
            if ($slug === '' || $nome === '') {
                continue;
            }
            // Só exibe categorias com cursos públicos disponíveis.
            if (isset($categoria['total_cursos']) && (int) $categoria['total_cursos'] <= 0) {
                continue;
            }

            $chips[] = array(
                'nome' => $nome,
                'slug' => $slug,
                'url' => $this->montarUrl(array_merge($estado, array('categoria' => $slug, 'pagina' => 1))),
                'ativo' => $slug === $categoriaSlugAtual,
            );
        }

        return $chips;
    }

    private function montarModalidades($modalidadeAtual)
    {
        $labels = array(
            'presencial' => 'Presencial',
            'online_ao_vivo' => 'Online ao vivo',
            'hibrido' => 'Híbrido',
            'sob_demanda' => 'Sob demanda',
        );

        $itens = array();
        foreach ($this->modalidadesPermitidas as $codigo) {
            $itens[] = array(
                'codigo' => $codigo,
                'label' => isset($labels[$codigo]) ? $labels[$codigo] : ucfirst(str_replace('_', ' ', $codigo)),
                'ativo' => $codigo === $modalidadeAtual,
            );
        }

        return $itens;
    }

    private function montarPaginacao($paginaAtual, $totalPaginas, array $estado)
    {
        if ($totalPaginas <= 1) {
            return array(
                'paginaAtual' => 1,
                'totalPaginas' => 1,
                'anterior' => null,
                'proxima' => null,
                'paginas' => array(),
            );
        }

        $paginas = array();
        for ($i = 1; $i <= $totalPaginas; $i++) {
            $paginas[] = array(
                'numero' => $i,
                'url' => $this->montarUrl(array_merge($estado, array('pagina' => $i))),
                'ativa' => $i === $paginaAtual,
            );
        }

        return array(
            'paginaAtual' => $paginaAtual,
            'totalPaginas' => $totalPaginas,
            'anterior' => $paginaAtual > 1 ? $this->montarUrl(array_merge($estado, array('pagina' => $paginaAtual - 1))) : null,
            'proxima' => $paginaAtual < $totalPaginas ? $this->montarUrl(array_merge($estado, array('pagina' => $paginaAtual + 1))) : null,
            'paginas' => $paginas,
        );
    }

    /**
     * Monta uma URL canônica /v2/catalogo/ preservando apenas parâmetros ativos.
     */
    private function montarUrl(array $params)
    {
        $query = array();

        $busca = isset($params['busca']) ? trim((string) $params['busca']) : '';
        if ($busca !== '') {
            $query['busca'] = $busca;
        }

        $categoria = isset($params['categoria']) ? trim((string) $params['categoria']) : '';
        if ($categoria !== '') {
            $query['categoria'] = $categoria;
        }

        $modalidade = isset($params['modalidade']) ? (string) $params['modalidade'] : '';
        if ($modalidade !== '' && in_array($modalidade, $this->modalidadesPermitidas, true)) {
            $query['modalidade'] = $modalidade;
        }

        $preco = isset($params['preco']) ? (string) $params['preco'] : '';
        if ($preco !== '' && in_array($preco, $this->precosPermitidos, true)) {
            $query['preco'] = $preco;
        }

        $carga = isset($params['carga']) ? (string) $params['carga'] : '';
        if ($carga !== '' && in_array($carga, $this->cargasPermitidas, true)) {
            $query['carga'] = $carga;
        }

        if (!empty($params['destaque'])) {
            $query['destaque'] = '1';
        }

        $ordem = isset($params['ordem']) ? (string) $params['ordem'] : 'relevancia';
        if ($ordem !== '' && $ordem !== 'relevancia' && in_array($ordem, $this->ordensPermitidas, true)) {
            $query['ordem'] = $ordem;
        }

        $pagina = isset($params['pagina']) ? (int) $params['pagina'] : 1;
        if ($pagina > 1) {
            $query['pagina'] = $pagina;
        }

        $base = '/v2/catalogo/';
        if (empty($query)) {
            return $base;
        }

        return $base . '?' . http_build_query($query);
    }

    private function normalizarCursos(array $cursos)
    {
        $normalizados = array();
        foreach ($cursos as $curso) {
            if (!is_array($curso)) {
                continue;
            }

            $categoriaNome = isset($curso['categoria_nome']) ? trim((string) $curso['categoria_nome']) : '';
            $professorNome = $this->resolverProfessorNome($curso);
            $valorEfetivo = $this->valorEfetivo($curso);
            $valorOriginal = isset($curso['valor']) ? (float) $curso['valor'] : 0.0;
            $desconto = isset($curso['desconto_promocional']) && is_array($curso['desconto_promocional'])
                ? $curso['desconto_promocional']
                : null;
            $thumbnail = isset($curso['thumbnail']) ? trim((string) $curso['thumbnail']) : '';
            $modalidade = isset($curso['modalidade']) ? (string) $curso['modalidade'] : '';
            $cursoId = isset($curso['id']) ? (int) $curso['id'] : 0;

            $normalizados[] = array(
                'id' => $cursoId,
                'slug' => isset($curso['slug']) ? (string) $curso['slug'] : '',
                'titulo' => isset($curso['nome']) ? (string) $curso['nome'] : '',
                'descricaoCurta' => isset($curso['descricao_curta']) ? trim((string) $curso['descricao_curta']) : '',
                'categoria' => $categoriaNome,
                'modalidade' => $this->modalidadeLabel($modalidade),
                'cargaHoraria' => isset($curso['carga_horaria']) ? (int) $curso['carga_horaria'] : 0,
                'preco' => $valorEfetivo,
                'precoOriginal' => $valorOriginal,
                'descontoPromocional' => $desconto,
                'total_turmas_abertas' => isset($curso['total_turmas_abertas']) ? (int) $curso['total_turmas_abertas'] : 0,
                'destaque' => !empty($curso['destaque']),
                'tipo' => isset($curso['tipo']) ? (string) $curso['tipo'] : 'curso',
                'thumbnail' => $thumbnail !== '' ? $thumbnail : null,
                'professor' => $professorNome,
                // Link para a ficha pública canônica atual (a ficha V2 ainda é demonstrativa).
                'url' => '/cursos/detalhe?curso_id=' . $cursoId,
            );
        }

        return $normalizados;
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

        return '';
    }

    private function modalidadeLabel($modalidade)
    {
        $map = array(
            'presencial' => 'Presencial',
            'online_ao_vivo' => 'Online ao vivo',
            'hibrido' => 'Híbrido',
            'sob_demanda' => 'Sob demanda',
        );

        return isset($map[$modalidade]) ? $map[$modalidade] : ($modalidade !== '' ? ucfirst(str_replace('_', ' ', (string) $modalidade)) : '');
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
