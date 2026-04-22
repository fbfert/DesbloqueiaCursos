<?php

use App\Controllers\Api\HealthController;

$app->get('/api/health', array(HealthController::class, 'show'));
