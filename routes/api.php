<?php

use App\Controllers\Api\ClaudeController;
use App\Controllers\Api\HealthController;
use App\Controllers\Api\NorminhaController;

$app->get('/api/health', array(HealthController::class, 'show'));
$app->post('/api/claude/teste', array(ClaudeController::class, 'testar'), array('auth', 'permission:configuracoes_globais.gerenciar'));

// Norminha IA V1 — área do aluno.
//
// `auth.api` responde 401 em JSON; o `auth` comum redirecionaria para o login e
// um fetch() leria o HTML de resposta como sucesso (ver
// ApiAuthenticateMiddleware e docs/norminha/CONTEXTO-EXECUCAO.md § C3).
//
// O `csrf` é declarado EXPLICITAMENTE e DEPOIS do `auth.api`. Router::post()
// só injeta o csrf quando ele não está na lista, então declará-lo aqui preserva
// esta ordem — e a ordem importa: com o csrf primeiro, um visitante anônimo
// recebia 403 "csrf_invalido" em vez de 401 "nao_autenticado". Sem sessão não
// existe token válido, então a resposta honesta é a de autenticação.
// Não se usa postWithoutCsrf: ele é reservado a webhook externo.
//
// O cliente envia `_token` dentro do próprio JSON — App\Core\Request decodifica
// application/json antes da validação, então não é preciso cabeçalho customizado.
$app->post('/api/norminha/chat', array(NorminhaController::class, 'chat'), array('auth.api', 'csrf'));
$app->get('/api/norminha/historico', array(NorminhaController::class, 'historico'), array('auth.api'));
$app->post('/api/norminha/feedback', array(NorminhaController::class, 'feedback'), array('auth.api', 'csrf'));
