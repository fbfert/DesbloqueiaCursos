<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Services\ConfiguracaoGlobalService;
use App\Services\CursoService;
use App\Services\FrontendModuloService;

class HomeController extends Controller
{
    private $cursoService;
    private $configuracaoGlobalService;
    private $frontendModuloService;

    public function __construct()
    {
        $this->cursoService = new CursoService();
        $this->configuracaoGlobalService = new ConfiguracaoGlobalService();
        $this->frontendModuloService = new FrontendModuloService();
    }

    public function index(Request $request)
    {
        $limiteDestaques = $this->configuracaoGlobalService->homeDestaquesLimite();
        $cursosDestaque = $this->cursoService->listPublicHome($limiteDestaques);

        return $this->view('home', array(
            'title' => 'Polo Rainbow',
            'success' => Session::pullFlash('success'),
            'usuarioNome' => Session::get('usuario_nome'),
            'cursos' => $cursosDestaque,
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

    private function moduloCapaOuPadrao($codigo, array $padrao)
    {
        try {
            $modulo = $this->frontendModuloService->buscarPorCodigo($codigo);
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
