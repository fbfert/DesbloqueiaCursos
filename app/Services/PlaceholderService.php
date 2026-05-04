<?php

namespace App\Services;

class PlaceholderService
{
    private $configuracaoGlobalService;

    public function __construct()
    {
        $this->configuracaoGlobalService = new ConfiguracaoGlobalService();
    }

    public function render($texto)
    {
        $texto = (string) $texto;
        $institucional = $this->configuracaoGlobalService->institucional();
        $nomePortal = !empty($institucional['nome_fantasia']) ? (string) $institucional['nome_fantasia'] : 'Polo Rainbow';

        $siteUrl = rtrim((string) (require BASE_PATH . '/config/app.php')['url'], '/');
        if ($siteUrl === '') {
            $siteUrl = '/';
        }

        $map = array(
            '{ano}' => date('Y'),
            '{nome_portal}' => $nomePortal,
            '{url_site}' => $siteUrl,
        );

        return strtr($texto, $map);
    }
}
