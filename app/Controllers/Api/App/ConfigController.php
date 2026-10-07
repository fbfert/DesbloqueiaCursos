<?php

namespace App\Controllers\Api\App;

use App\Core\Env;
use App\Core\Request;
use App\Support\AppApi\Formato;
use App\Support\AppApi\Versao;

/**
 * `GET /config` (público): versões do app e links do site.
 */
class ConfigController extends AppController
{
    public function show(Request $request)
    {
        $site = Formato::urlSite();

        return $this->ok(array(
            'versao_minima_android' => Versao::minimaAndroid(),
            'versao_atual_android' => Versao::atualAndroid(),
            'url_site' => $site,
            'url_catalogo' => $site . '/cursos',
            'url_suporte' => (string) Env::get('APP_MOBILE_URL_SUPORTE', $site . '/contato'),
            'url_termos' => (string) Env::get('APP_MOBILE_URL_TERMOS', $site . '/v2/termos-de-uso'),
            'url_privacidade' => (string) Env::get('APP_MOBILE_URL_PRIVACIDADE', $site . '/v2/politica-de-privacidade'),
        ));
    }
}
