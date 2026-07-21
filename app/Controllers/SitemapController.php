<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Helpers;
use App\Core\Request;
use App\Core\Response;
use App\Services\CategoriaService;
use App\Services\CursoService;

/**
 * Sitemap XML dinâmico (V2): gerado a partir dos mesmos dados públicos reais
 * do catálogo (sem cache/duplicação). Cobre apenas páginas públicas e
 * indexáveis; áreas autenticadas, admin, professor e checkout ficam de fora
 * (já bloqueadas em robots.txt).
 */
class SitemapController extends Controller
{
    private $cursoService;
    private $categoriaService;

    public function __construct()
    {
        $this->cursoService = new CursoService();
        $this->categoriaService = new CategoriaService();
    }

    public function index(Request $request)
    {
        $urls = array();

        $urls[] = array('loc' => '/v2/', 'priority' => '1.0', 'changefreq' => 'daily');
        $urls[] = array('loc' => '/v2/catalogo/', 'priority' => '0.9', 'changefreq' => 'daily');
        $urls[] = array('loc' => '/v2/categorias/', 'priority' => '0.7', 'changefreq' => 'weekly');
        $urls[] = array('loc' => '/v2/quem-somos', 'priority' => '0.4', 'changefreq' => 'monthly');
        $urls[] = array('loc' => '/v2/como-funciona-a-sala-virtual', 'priority' => '0.4', 'changefreq' => 'monthly');
        $urls[] = array('loc' => '/v2/onde-estamos', 'priority' => '0.3', 'changefreq' => 'monthly');
        $urls[] = array('loc' => '/v2/certificados/validar', 'priority' => '0.3', 'changefreq' => 'monthly');
        $urls[] = array('loc' => '/v2/login', 'priority' => '0.2', 'changefreq' => 'yearly');
        $urls[] = array('loc' => '/v2/cadastro', 'priority' => '0.2', 'changefreq' => 'yearly');

        $cursos = $this->cursoService->listPublic();
        foreach (($cursos['cursos'] ?? array()) as $curso) {
            if (empty($curso['id'])) {
                continue;
            }
            $urls[] = array(
                'loc' => '/v2/curso/?curso_id=' . (int) $curso['id'],
                'lastmod' => $this->formatarData($curso['updated_at'] ?? null),
                'priority' => '0.8',
                'changefreq' => 'weekly',
            );
        }

        $categorias = $this->categoriaService->listPublic();
        foreach (($categorias['categorias'] ?? array()) as $categoria) {
            if (empty($categoria['slug'])) {
                continue;
            }
            $urls[] = array(
                'loc' => '/v2/catalogo/?categoria=' . rawurlencode((string) $categoria['slug']),
                'priority' => '0.6',
                'changefreq' => 'weekly',
            );
        }

        $xml = $this->montarXml($urls);

        return new Response($xml, 200, array('Content-Type' => 'application/xml; charset=utf-8'));
    }

    private function montarXml(array $urls)
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($urls as $url) {
            $xml .= '  <url>' . "\n";
            $xml .= '    <loc>' . Helpers::e(Helpers::url(ltrim((string) $url['loc'], '/'))) . '</loc>' . "\n";
            if (!empty($url['lastmod'])) {
                $xml .= '    <lastmod>' . Helpers::e((string) $url['lastmod']) . '</lastmod>' . "\n";
            }
            if (!empty($url['changefreq'])) {
                $xml .= '    <changefreq>' . Helpers::e((string) $url['changefreq']) . '</changefreq>' . "\n";
            }
            if (!empty($url['priority'])) {
                $xml .= '    <priority>' . Helpers::e((string) $url['priority']) . '</priority>' . "\n";
            }
            $xml .= '  </url>' . "\n";
        }

        $xml .= '</urlset>' . "\n";

        return $xml;
    }

    private function formatarData($valor)
    {
        $valor = trim((string) $valor);
        if ($valor === '') {
            return null;
        }
        $timestamp = strtotime($valor);
        return $timestamp ? date('Y-m-d', $timestamp) : null;
    }
}
