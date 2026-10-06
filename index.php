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

// Previa do tema publico: ?tema=caderno|v2 so vale para quem tem conteudo.gerenciar.
// So consulta o banco quando o parametro existe. Fica depois do ErrorHandler para
// que uma falha de banco/RBAC aqui passe pelo tratamento de erro padrao.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && isset($_GET['tema'])) {
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

$app->run();
