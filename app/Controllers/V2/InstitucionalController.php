<?php

namespace App\Controllers\V2;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\PaginaService;
use App\Support\V2ErrorPage;
use App\Support\V2InstitucionalContent;
use App\Support\V2Nav;

/**
 * Páginas institucionais editáveis no V2 (Fase 2.13B).
 *
 * Substitui as cascas demonstrativas anteriores (texto fixo) por páginas que
 * consultam o MESMO cadastro do backend (tabela `paginas`, editada em
 * /admin/paginas via PaginaService). Nada é duplicado: qualquer alteração feita
 * pelo administrador aparece automaticamente na V2, sem mudança de código.
 *
 * As rotas V2 canônicas casam com a `rota`/`slug` real de cada página:
 *   /v2/quem-somos                    -> /quem-somos
 *   /v2/como-funciona-a-sala-virtual  -> /como-funciona-a-sala-virtual
 *   /v2/termos-de-uso                 -> /termos-de-uso
 *   /v2/politica-de-privacidade       -> /politica-de-privacidade
 *   /v2/onde-estamos                  -> /onde-estamos
 *   /v2/remova-me                     -> /remova-me
 *
 * O conteúdo HTML é sempre sanitizado antes de exibir (V2InstitucionalContent →
 * Helpers::renderSafeHtml, perfil `full`). Página sem registro publicado devolve
 * 404 no visual V2 (sem inventar conteúdo, sem expor detalhes internos).
 */
class InstitucionalController extends Controller
{
    private $paginaService;

    public function __construct()
    {
        $this->paginaService = new PaginaService();
    }

    public function quemSomos(Request $request)
    {
        return $this->renderBackendPage($request, '/quem-somos', 'Quem somos');
    }

    public function comoFuncionaSalaVirtual(Request $request)
    {
        return $this->renderBackendPage($request, '/como-funciona-a-sala-virtual', 'Como funciona a Sala Virtual');
    }

    public function termosDeUso(Request $request)
    {
        return $this->renderBackendPage($request, '/termos-de-uso', 'Termos de uso');
    }

    public function politicaDePrivacidade(Request $request)
    {
        return $this->renderBackendPage($request, '/politica-de-privacidade', 'Política de privacidade');
    }

    public function ondeEstamos(Request $request)
    {
        return $this->renderBackendPage($request, '/onde-estamos', 'Onde estamos');
    }

    public function removaMe(Request $request)
    {
        return $this->renderBackendPage($request, '/remova-me', 'Remova-me');
    }

    // --- Compatibilidade: rotas demonstrativas antigas viram redirect 301 para a
    // rota V2 canônica equivalente (sem loop, sem retorno ao V1). ------------------

    public function sobre(Request $request)
    {
        return Response::redirect(V2Nav::QUEM_SOMOS, 301);
    }

    public function contato(Request $request)
    {
        return Response::redirect(V2Nav::ONDE_ESTAMOS, 301);
    }

    public function comoFunciona(Request $request)
    {
        return Response::redirect(V2Nav::COMO_FUNCIONA_SALA, 301);
    }

    /**
     * Busca a página publicada pela rota real, sanitiza e renderiza no shell V2.
     * Ausência de registro publicado -> 404 V2 (não exibe versão demonstrativa).
     */
    private function renderBackendPage(Request $request, $rota, $breadcrumbLabel)
    {
        $pagina = $this->paginaService->buscarPublicaPorRota($rota);

        if (!$pagina) {
            // Cadastro real ausente/indisponível: 404 no visual V2, sem inventar
            // conteúdo e sem expor detalhes internos.
            return V2ErrorPage::notFound(
                $request->path(),
                'Página indisponível',
                'Esta página institucional ainda não está publicada. Tente novamente mais tarde.'
            );
        }

        $conteudoBruto = '';

        foreach (array('conteudo_html', 'conteudo', 'html', 'corpo', 'texto') as $campoConteudo) {
            if (!isset($pagina[$campoConteudo])) {
                continue;
            }

            $valorConteudo = trim((string) $pagina[$campoConteudo]);
            if ($valorConteudo === '') {
                continue;
            }

            $conteudoBruto = $valorConteudo;
            break;
        }

        $conteudo = V2InstitucionalContent::build($conteudoBruto);

        $titulo = trim((string) (isset($pagina['titulo']) ? $pagina['titulo'] : $breadcrumbLabel));
        if ($titulo === '') {
            $titulo = (string) $breadcrumbLabel;
        }

        $data = array_merge($this->dadosLayout(), array(
            'pageTitle' => $titulo . ' — Desbloqueia Cursos',
            'pageDescription' => trim((string) (isset($pagina['resumo']) ? $pagina['resumo'] : '')),
            'institTitulo' => $titulo,
            'institResumo' => trim((string) (isset($pagina['resumo']) ? $pagina['resumo'] : '')),
            'institBreadcrumb' => (string) $breadcrumbLabel,
            'institHtml' => $conteudo['html'],
            'institMapEmbedUrl' => $conteudo['mapEmbedUrl'],
            'institMapLinkUrl' => $conteudo['mapLinkUrl'],
        ));

        return new Response(View::render('v2/institucional', $data, false));
    }

    private function dadosLayout()
    {
        $usuarioId = (int) Session::get('usuario_id', 0);
        $usuarioNome = trim((string) Session::get('usuario_nome', ''));
        $primeiro = '';
        if ($usuarioNome !== '') {
            $partes = preg_split('/\s+/', $usuarioNome);
            $primeiro = ($partes && !empty($partes[0])) ? (string) $partes[0] : $usuarioNome;
        }

        return array(
            'loggedIn' => $usuarioId > 0,
            'usuarioPrimeiroNome' => $primeiro,
            'areaHref' => $this->resolveAreaHref(),
            'catalogoHref' => V2Nav::CATALOGO,
            'cadastroHref' => V2Nav::CADASTRO,
            'loginHref' => V2Nav::LOGIN,
            'certificadosHref' => V2Nav::CERTIFICADOS,
            'homeHref' => V2Nav::HOME,
        );
    }

    private function resolveAreaHref()
    {
        $sessionPerfis = Session::get('usuario_perfis', array());
        $hasAdminAccess = (bool) Session::get('usuario_admin') || (bool) Session::get('is_admin') || in_array('admin', $sessionPerfis, true);
        $hasProfessorAccess = (bool) Session::get('usuario_professor') || (bool) Session::get('is_professor') || in_array('professor', $sessionPerfis, true);

        if ($hasAdminAccess) {
            return '/admin';
        }
        if ($hasProfessorAccess) {
            return '/professor/dashboard';
        }

        return V2Nav::ALUNO;
    }
}
