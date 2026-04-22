<?php

use App\Controllers\HomeController;
use App\Controllers\AuthController;
use App\Controllers\Admin\RbacController;
use App\Controllers\Admin\CatalogoController as AdminCatalogoController;
use App\Controllers\Admin\CursosController;
use App\Controllers\Admin\TurmasController;
use App\Controllers\Professor\CatalogoController as ProfessorCatalogoController;

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
$app->get('/admin/rbac', array(RbacController::class, 'index'), array('auth', 'permission:rbac.dashboard.ver'));
$app->post('/admin/rbac/perfis/permissoes', array(RbacController::class, 'syncProfilePermissions'), array('auth', 'permission:rbac.perfis.gerenciar'));
$app->post('/admin/rbac/usuarios/perfis', array(RbacController::class, 'syncUserProfiles'), array('auth', 'permission:rbac.perfis.gerenciar'));
$app->get('/admin/catalogo', array(AdminCatalogoController::class, 'index'), array('auth', 'permission:catalogo.ver'));
$app->get('/admin/cursos', array(CursosController::class, 'index'), array('auth', 'permission:catalogo.ver'));
$app->get('/admin/turmas', array(TurmasController::class, 'index'), array('auth', 'permission:catalogo.ver'));
$app->get('/professor/catalogo', array(ProfessorCatalogoController::class, 'index'), array('auth', 'permission:catalogo.professor.ver'));
