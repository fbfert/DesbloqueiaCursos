<?php

use App\Controllers\Api\ClaudeController;
use App\Controllers\Api\HealthController;

$app->get('/api/health', array(HealthController::class, 'show'));
$app->post('/api/claude/teste', array(ClaudeController::class, 'testar'), array('auth', 'permission:configuracoes_globais.gerenciar'));
