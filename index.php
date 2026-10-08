<?php

define('BASE_PATH', __DIR__);
define('PUBLIC_PATH', __DIR__);

require BASE_PATH . '/app/Core/Autoloader.php';

\App\Core\Autoloader::register(BASE_PATH);
\App\Core\Env::load(BASE_PATH . '/.env');

// API do app do aluno (/api/app/*): autentica por Bearer, sem cookie. Nao inicia
// nem persiste sessao PHP (nenhum Set-Cookie, nenhum arquivo de sessao por
// chamada do app). $_SESSION existe so em memoria e vazio, para que um
// Session::get() esquecido em codigo compartilhado devolva null em vez de quebrar;
// a identidade da requisicao vem de App\Support\AppAuth (middleware auth.app).
$rotaApp = \App\Core\Router::ehRotaApp((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH));

if ($rotaApp) {
    $_SESSION = array();
} else {
    \App\Core\Session::start();

    // Origem de trafego (UTMs + gclid) capturada na PRIMEIRA visita, em qualquer
    // pagina de entrada, e mantida em sessao ate virar pedido. Sem isto nao da
    // para saber qual anuncio gerou qual venda. Nao depende de feature flag:
    // apenas guarda o que ja veio na URL.
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && !empty($_GET)) {
        \App\Support\OrigemTrafego::capturarDeQuery($_GET);
    }
}

\App\Core\ErrorHandler::register((require BASE_PATH . '/config/app.php')['debug']);

if ($rotaApp) {
    // Erro do PHP na API vira JSON 500 sem detalhe (o detalhe vai para o log).
    \App\Core\ErrorHandler::usarRespostaJson(true);
    ini_set('display_errors', '0');
    register_shutdown_function(function () {
        $erro = error_get_last();
        if ($erro && in_array($erro['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR), true)) {
            \App\Core\Logger::error('app_api.erro_fatal', array('message' => $erro['message'], 'file' => $erro['file'], 'line' => $erro['line']));
            if (!headers_sent()) {
                \App\Support\AppApi\Resposta::enviarErroInterno();
            }
        }
    });
}

// Previa do tema publico: ?tema=caderno|v2 so vale para quem tem conteudo.gerenciar.
// So consulta o banco quando o parametro existe. Fica depois do ErrorHandler para
// que uma falha de banco/RBAC aqui passe pelo tratamento de erro padrao.
if (!$rotaApp && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && isset($_GET['tema'])) {
    \App\Support\TemaPublico::aplicarPrevia(
        is_string($_GET['tema']) ? $_GET['tema'] : null,
        \App\Core\Session::get('usuario_id'),
        function ($id) {
            return $id && (new \App\Services\RbacService())->userHasPermission($id, 'conteudo.gerenciar');
        }
    );
}

$app = new \App\Core\App(BASE_PATH);

require BASE_PATH . '/routes/web.php';
require BASE_PATH . '/routes/api.php';
require BASE_PATH . '/routes/app.php';

$app->run();
