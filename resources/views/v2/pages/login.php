<?php
use App\Core\Helpers;

$errors = isset($errors) && is_array($errors) ? array_values($errors) : array();
$success = isset($success) ? $success : null;
$old = isset($old) && is_array($old) ? $old : array();
$accountCreated = isset($accountCreated) && is_array($accountCreated) ? $accountCreated : null;
$loginAction = isset($loginAction) ? (string) $loginAction : '/login';
$origemFlag = isset($origemFlag) && in_array($origemFlag, array('v2', 'v2_aluno'), true) ? (string) $origemFlag : 'v2';
$homeHref = isset($homeHref) ? (string) $homeHref : '/v2/';
$registerHref = isset($registerHref) ? (string) $registerHref : '/cadastro';
$forgotHref = isset($forgotHref) ? (string) $forgotHref : '/recuperar-senha';
$jaLogado = !empty($jaLogado);
$areaHref = isset($areaHref) ? (string) $areaHref : '/meus-cursos';
$loginValue = isset($old['login']) ? (string) $old['login'] : '';
$temErros = !empty($errors);
$loginDescribedBy = $temErros ? 'v2-login-erros' : 'v2-login-ajuda';
?>
<div class="v2-auth-split">
  <!-- Painel visual (desktop) -->
  <aside class="v2-auth-aside" aria-hidden="true">
    <h2 class="v2-h1 v2-auth-aside-title">Quando aprende de verdade, <em>desbloqueia.</em></h2>
    <p class="v2-muted">Acesse sua conta para continuar sua jornada de aprendizado.</p>
    <ul class="v2-auth-benes">
      <li><span class="v2-auth-bene-ic"><i class="ti ti-player-play"></i></span> Seus cursos, módulos e progresso</li>
      <li><span class="v2-auth-bene-ic"><i class="ti ti-certificate"></i></span> Certificados e pedidos</li>
      <li><span class="v2-auth-bene-ic"><i class="ti ti-device-mobile"></i></span> Funciona bem no computador e no celular</li>
    </ul>
  </aside>

  <!-- Card de login -->
  <div class="v2-auth-card">
    <a href="<?php echo Helpers::e($homeHref); ?>" class="v2-auth-logo"><img src="/v2/assets/img/logo-v2.svg" alt="Desbloqueia Cursos"></a>
    <h1 class="v2-h2 v2-center">Acesse sua conta</h1>
    <p class="v2-muted v2-center v2-sm" style="margin-bottom:20px;">Entre com seu e-mail ou CPF e sua senha para continuar.</p>

    <?php if ($jaLogado): ?>
      <div class="v2-callout v2-callout-info" role="status">
        <i class="ti ti-user-check"></i>
        <span>Você já está autenticado. <a href="<?php echo Helpers::e($areaHref); ?>">Ir para a sua área</a>.</span>
      </div>
    <?php endif; ?>

    <?php if ($temErros): ?>
      <div class="v2-callout v2-callout-danger" id="v2-login-erros" role="alert" aria-live="assertive">
        <i class="ti ti-alert-triangle"></i>
        <span>
          <?php foreach ($errors as $erro): ?>
            <?php echo Helpers::e((string) $erro); ?><br>
          <?php endforeach; ?>
        </span>
      </div>
    <?php endif; ?>

    <?php if ($accountCreated): ?>
      <div class="v2-callout v2-callout-success" role="status" aria-live="polite">
        <i class="ti ti-circle-check"></i>
        <span>Conta criada com sucesso. Agora acesse com seus dados para começar.</span>
      </div>
    <?php elseif (!empty($success)): ?>
      <div class="v2-callout v2-callout-success" role="status" aria-live="polite">
        <i class="ti ti-circle-check"></i>
        <span><?php echo Helpers::e(is_array($success) && !empty($success['message']) ? (string) $success['message'] : (string) $success); ?></span>
      </div>
    <?php endif; ?>

    <form method="post" action="<?php echo Helpers::e($loginAction); ?>" id="v2-login-real" data-native-submit novalidate>
      <?php // Flag interna em lista branca: permite ao servidor retornar à tela V2 em caso de erro. ?>
      <input type="hidden" name="origem" value="<?php echo Helpers::e($origemFlag); ?>">

      <div class="v2-field<?php echo $temErros ? ' has-error' : ''; ?>" id="v2-f-login">
        <label for="v2-login-login">E-mail ou CPF</label>
        <input type="text" id="v2-login-login" name="login" class="v2-input"
               value="<?php echo Helpers::e($loginValue); ?>"
               placeholder="seuemail@exemplo.com ou 000.000.000-00"
               autocomplete="username" inputmode="text"
               aria-describedby="<?php echo Helpers::e($loginDescribedBy); ?>"
               required autofocus>
        <div class="v2-field-error" id="v2-login-ajuda"></div>
      </div>

      <div class="v2-field" id="v2-f-senha">
        <label for="v2-login-senha">Senha</label>
        <div class="v2-input-wrap">
          <input type="password" id="v2-login-senha" name="senha" class="v2-input"
                 placeholder="Sua senha" autocomplete="current-password" required>
          <button type="button" class="v2-eye" data-toggle-pass="#v2-login-senha" aria-label="Mostrar senha"><i class="ti ti-eye"></i></button>
        </div>
      </div>

      <div class="v2-auth-row">
        <span></span>
        <a href="<?php echo Helpers::e($forgotHref); ?>" class="v2-sm">Esqueci minha senha</a>
      </div>

      <button type="submit" class="v2-btn v2-btn-primary v2-btn-block">Entrar <i class="ti ti-arrow-right"></i></button>
    </form>

    <div class="v2-auth-foot">Ainda não tem conta? <a href="<?php echo Helpers::e($registerHref); ?>">Criar conta</a></div>
  </div>
</div>
