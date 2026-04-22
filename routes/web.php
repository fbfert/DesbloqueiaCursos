<?php

use App\Controllers\HomeController;

$app->get('/', array(HomeController::class, 'index'));
