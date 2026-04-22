<?php

use App\Controllers\HomeController;
use App\Controllers\AuthController;

$app->get('/', array(HomeController::class, 'index'));
$app->get('/cadastro', array(AuthController::class, 'showRegister'));
$app->post('/cadastro', array(AuthController::class, 'register'));
$app->get('/login', array(AuthController::class, 'showLogin'));
$app->post('/login', array(AuthController::class, 'login'));
$app->post('/logout', array(AuthController::class, 'logout'));
$app->get('/recuperar-senha', array(AuthController::class, 'showForgotPassword'));
$app->post('/recuperar-senha', array(AuthController::class, 'requestPasswordReset'));
$app->get('/recuperar-senha/redefinir', array(AuthController::class, 'showResetPassword'));
$app->post('/recuperar-senha/redefinir', array(AuthController::class, 'resetPassword'));
$app->get('/esqueci-minha-senha', array(AuthController::class, 'showForgotPassword'));
$app->post('/esqueci-minha-senha', array(AuthController::class, 'requestPasswordReset'));
$app->get('/esqueci-minha-senha/redefinir', array(AuthController::class, 'showResetPassword'));
$app->post('/esqueci-minha-senha/redefinir', array(AuthController::class, 'resetPassword'));
