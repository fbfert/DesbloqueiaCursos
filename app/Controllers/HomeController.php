<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Services\AvisoService;
use App\Services\ConfiguracaoGlobalService;
use App\Services\CursoService;
use App\Services\FrontendModuloService;

class HomeController extends Controller
{
    private $pageKey = 'home';
    private $cursoService;
    private $avisoService;
    private $configuracaoGlobalService;
    private $frontendModuloService;

    public function __construct()
    {
        $this->cursoService = new CursoService();
        $this->avisoService = new AvisoService();
        $this->configuracaoGlobalService = new ConfiguracaoGlobalService();
        $this->frontendModuloService = new FrontendModuloService();
    }

    public function index(Request $request)
    {
        $limiteDestaques = $this->configuracaoGlobalService->homeDestaquesLimite();
        $cursosDestaque = $this->cursoService->listPublicHome($limiteDestaques);
        $topCursos = $this->cursoService->listPublicTopVendas(5);
        $usuarioId = Session::get('usuario_id');
        $appConfig = require BASE_PATH . '/config/app.php';

        return $this->view('home', array(
            'title' => isset($appConfig['name']) ? $appConfig['name'] : 'Desbloqueia Cursos',
            'success' => Session::pullFlash('success'),
            'page_key' => $this->pageKey,
            'loggedIn' => $usuarioId !== null,
            'usuarioNome' => Session::get('usuario_nome'),
            'postLoginChoiceModal' => Session::pullFlash('post_login_choice_modal'),
            'avisos' => $usuarioId ? $this->avisoService->avisosAtivosParaUsuario((int) $usuarioId) : array(),
            'cursos' => $cursosDestaque,
            'topCursos' => $topCursos,
            'topCursosModulo' => $this->moduloCapaOuPadrao('top_5_cursos_capa', array(
                'titulo' => 'Top 5 Cursos',
                'conteudo' => 'Cursos com mais vendas aprovadas no portal.',
            )),
            'topAvaliacoesModulo' => $this->moduloCapaOuPadrao('top_5_avaliacoes_capa', array(
                'titulo' => 'Top 5 Avaliações',
                'conteudo' => 'Em breve, este espaço mostrará os cursos com melhores avaliações dos participantes.',
            )),
            'depoimentosModulo' => $this->moduloCapaOuPadrao('depoimentos_capa', array(
                'titulo' => 'Depoimentos',
                'conteudo' => 'Relatos de participantes poderão ser exibidos aqui em formato de carrossel.',
            )),
            'depoimentosCapa' => $this->depoimentosCapa(),
            'chamadaPrincipalCapa' => $this->moduloCapaOuPadrao('chamada_principal_capa', array(
                'titulo' => 'Formações com turmas públicas, inscrição guiada e acesso separado por perfil.',
                'conteudo' => 'O portal público consome o catálogo do backoffice sem expor dados administrativos. Aqui entram apenas cursos ativos, turmas abertas e a porta de entrada da inscrição.',
            )),
            'modulosCapaStatus' => $this->modulosCapaStatus(),
        ));
    }

    private function modulosCapaStatus()
    {
        $modulos = array(
            $this->moduloCapaOuPadrao('catalogo_publico_capa', array(
                'titulo' => 'Catálogo público',
                'conteudo' => 'Lista apenas cursos ativos e publicáveis, sem depender de permissão administrativa.',
            )),
            $this->moduloCapaOuPadrao('detalhe_seguro_capa', array(
                'titulo' => 'Detalhe seguro',
                'conteudo' => 'O detalhe do curso exibe somente professor responsável e turmas abertas para inscrição.',
            )),
            $this->moduloCapaOuPadrao('inscricao_inicial_capa', array(
                'titulo' => 'Inscrição inicial',
                'conteudo' => 'O frontend encaminha a inscrição apenas para turma aberta e vinculada ao curso correto.',
            )),
        );

        return array_values(array_filter($modulos));
    }

    private function depoimentosCapa()
    {
        try {
            $depoimentos = $this->frontendModuloService->listarAtivosPorPosicao('depoimentos_capa_item', 12, array(
                'page_key' => $this->pageKey,
                'route' => '/',
                'area' => 'publica',
                'auth_state' => Session::get('usuario_id') !== null ? 'logged' : 'guest',
            ));
            return is_array($depoimentos) ? $depoimentos : array();
        } catch (\Throwable $exception) {
            // Mantém a capa pública disponível mesmo antes da migration dos depoimentos.
        }

        return array();
    }

    private function moduloCapaOuPadrao($codigo, array $padrao)
    {
        try {
            $modulo = $this->frontendModuloService->buscarPorCodigo($codigo, array(
                'page_key' => $this->pageKey,
                'route' => '/',
                'area' => 'publica',
                'auth_state' => Session::get('usuario_id') !== null ? 'logged' : 'guest',
            ));
            if ($modulo) {
                return ((int) $modulo['ativo'] === 1) ? $modulo : null;
            }
        } catch (\Throwable $exception) {
            // Mantém a capa pública disponível mesmo antes da migration dos módulos da capa.
        }

        return array(
            'codigo' => $codigo,
            'titulo' => isset($padrao['titulo']) ? $padrao['titulo'] : null,
            'subtitulo' => isset($padrao['subtitulo']) ? $padrao['subtitulo'] : null,
            'conteudo' => isset($padrao['conteudo']) ? $padrao['conteudo'] : null,
            'imagem_caminho' => isset($padrao['imagem_caminho']) ? $padrao['imagem_caminho'] : null,
            'imagem_alt' => isset($padrao['imagem_alt']) ? $padrao['imagem_alt'] : null,
            'ativo' => 1,
        );
    }
}
