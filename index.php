<?php

define('BASE_PATH', __DIR__);
define('PUBLIC_PATH', __DIR__);

require BASE_PATH . '/app/Core/Autoloader.php';

\App\Core\Autoloader::register(BASE_PATH);
\App\Core\Env::load(BASE_PATH . '/.env');
\App\Core\Session::start();

// Origem de trafego (UTMs + gclid) capturada na PRIMEIRA visita, em qualquer
// pagina de entrada, e mantida em sessao ate virar pedido. Sem isto nao da
// para saber qual anuncio gerou qual venda. Nao depende de feature flag:
// apenas guarda o que ja veio na URL.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && !empty($_GET)) {
    \App\Support\OrigemTrafego::capturarDeQuery($_GET);
}
\App\Core\ErrorHandler::register((require BASE_PATH . '/config/app.php')['debug']);

$app = new \App\Core\App(BASE_PATH);

require BASE_PATH . '/routes/web.php';
require BASE_PATH . '/routes/api.php';

$app->run();
