<?php

/**
 * API do app do aluno — /api/app/v1 (contrato: openspec/changes/api-app-v1/contrato.md).
 *
 * - JSON sempre; caminho desconhecido sob /api/app/ responde 404 JSON (Router).
 * - Sem sessão PHP nesse prefixo (index.php) e sem CSRF: POST com $app->postApp().
 * - `auth.app`: Bearer opaco + versão mínima do app (426). Rotas públicas: config,
 *   login, refresh, cadastro, recuperar-senha e catálogo.
 */

use App\Controllers\Api\App\AuthController;
use App\Controllers\Api\App\AvaliacaoController;
use App\Controllers\Api\App\CatalogoController;
use App\Controllers\Api\App\CertificadosController;
use App\Controllers\Api\App\ConfigController;
use App\Controllers\Api\App\ConteudoController;
use App\Controllers\Api\App\DispositivosController;
use App\Controllers\Api\App\InscricoesController;
use App\Controllers\Api\App\MeController;
use App\Controllers\Api\App\NotificacoesController;
use App\Controllers\Api\App\PedidosController;
use App\Controllers\Api\App\QuizController as AppQuizController;

$v1 = '/api/app/v1';
$autenticado = array('auth.app');

// Sistema
$app->get($v1 . '/config', array(ConfigController::class, 'show'));

// Autenticação
$app->postApp($v1 . '/auth/login', array(AuthController::class, 'login'));
$app->postApp($v1 . '/auth/refresh', array(AuthController::class, 'refresh'));
$app->postApp($v1 . '/auth/logout', array(AuthController::class, 'logout'), $autenticado);
$app->postApp($v1 . '/auth/cadastro', array(AuthController::class, 'cadastro'));
$app->postApp($v1 . '/auth/recuperar-senha', array(AuthController::class, 'recuperarSenha'));

// Perfil
$app->get($v1 . '/me', array(MeController::class, 'show'), $autenticado);
$app->postApp($v1 . '/me', array(MeController::class, 'atualizar'), $autenticado);
$app->postApp($v1 . '/me/senha', array(MeController::class, 'senha'), $autenticado);

// Meus cursos e conteúdo
$app->get($v1 . '/inscricoes', array(InscricoesController::class, 'index'), $autenticado);
$app->get($v1 . '/inscricoes/{id}', array(InscricoesController::class, 'show'), $autenticado);
$app->get($v1 . '/inscricoes/{id}/itens/{item}', array(ConteudoController::class, 'item'), $autenticado);
$app->postApp($v1 . '/inscricoes/{id}/itens/{item}/concluir', array(ConteudoController::class, 'concluir'), $autenticado);
$app->get($v1 . '/inscricoes/{id}/itens/{item}/arquivo', array(ConteudoController::class, 'arquivo'), $autenticado);

// Quiz
$app->get($v1 . '/inscricoes/{id}/itens/{item}/quiz', array(AppQuizController::class, 'estado'), $autenticado);
$app->postApp($v1 . '/inscricoes/{id}/itens/{item}/quiz/iniciar', array(AppQuizController::class, 'iniciar'), $autenticado);
$app->postApp($v1 . '/inscricoes/{id}/itens/{item}/quiz/rascunho', array(AppQuizController::class, 'rascunho'), $autenticado);
$app->get($v1 . '/inscricoes/{id}/itens/{item}/quiz/tempo', array(AppQuizController::class, 'tempo'), $autenticado);
$app->postApp($v1 . '/inscricoes/{id}/itens/{item}/quiz/enviar', array(AppQuizController::class, 'enviar'), $autenticado);
$app->get($v1 . '/inscricoes/{id}/itens/{item}/quiz/tentativas/{tentativa}', array(AppQuizController::class, 'tentativa'), $autenticado);

// Avaliação textual
$app->get($v1 . '/inscricoes/{id}/itens/{item}/avaliacao', array(AvaliacaoController::class, 'estado'), $autenticado);
$app->postApp($v1 . '/inscricoes/{id}/itens/{item}/avaliacao', array(AvaliacaoController::class, 'enviar'), $autenticado);
$app->get($v1 . '/avaliacoes/imagens/{id}', array(AvaliacaoController::class, 'imagem'), $autenticado);

// Pedidos
$app->get($v1 . '/pedidos', array(PedidosController::class, 'index'), $autenticado);
$app->postApp($v1 . '/pedidos/{id}/cancelar', array(PedidosController::class, 'cancelar'), $autenticado);
$app->postApp($v1 . '/pedidos/{id}/comprovante', array(PedidosController::class, 'comprovante'), $autenticado);
$app->postApp($v1 . '/pedidos/{id}/abacatepay', array(PedidosController::class, 'abacatepay'), $autenticado);

// Certificados
$app->get($v1 . '/certificados', array(CertificadosController::class, 'index'), $autenticado);
$app->get($v1 . '/certificados/{codigo}/pdf', array(CertificadosController::class, 'pdf'), $autenticado);

// Catálogo (público)
$app->get($v1 . '/catalogo', array(CatalogoController::class, 'index'));
$app->get($v1 . '/catalogo/{slug}', array(CatalogoController::class, 'show'));

// Push
$app->postApp($v1 . '/dispositivos', array(DispositivosController::class, 'registrar'), $autenticado);
$app->postApp($v1 . '/dispositivos/remover', array(DispositivosController::class, 'remover'), $autenticado);
$app->get($v1 . '/notificacoes', array(NotificacoesController::class, 'index'), $autenticado);
$app->postApp($v1 . '/notificacoes/{id}/lida', array(NotificacoesController::class, 'lida'), $autenticado);
