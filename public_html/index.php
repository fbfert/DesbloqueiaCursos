<?php

define('BASE_PATH', dirname(__DIR__));
define('PUBLIC_PATH', __DIR__);

require BASE_PATH . '/app/Core/Autoloader.php';

\App\Core\Autoloader::register(BASE_PATH);
\App\Core\Env::load(BASE_PATH . '/.env');
\App\Core\Session::start();
\App\Core\ErrorHandler::register((require BASE_PATH . '/config/app.php')['debug']);

$app = new \App\Core\App(BASE_PATH);

require BASE_PATH . '/routes/web.php';
require BASE_PATH . '/routes/api.php';

$app->run();
