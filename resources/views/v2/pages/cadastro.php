<?php
use App\Core\Helpers;

$errors = isset($errors) && is_array($errors) ? $errors : array();
$success = isset($success) ? $success : null;
$old = isset($old) && is_array($old) ? $old : array();
$cadastroAction = isset($cadastroAction) ? (string) $cadastroAction : '/cadastro';
$homeHref = isset($homeHref) ? (string) $homeHref : '/v2/';
$loginHref = isset($loginHref) ? (string) $loginHref : '/v2/login';
$termosHref = isset($termosHref) ? (string) $termosHref : '/termos-de-uso';
$privacidadeHref = isset($privacidadeHref) ? (string) $privacidadeHref : '/politica-de-privacidade';
$jaLogado = !empty($jaLogado);
$areaHref = isset($areaHref) ? (string) $areaHref : '/meus-cursos';

// Valores antigos permitidos (NUNCA senha/confirmação).
$oldNome = isset($old['nome']) ? (string) $old['nome'] : '';
$oldEmail = isset($old['email']) ? (string) $old['email'] : '';
$oldCpf = isset($old['cpf']) ? (string) $old['cpf'] : '';
$oldTelefone = isset($old['telefone']) ? (string) $old['telefone'] : '';
$ckTermos = !empty($old['aceite_termos']);
$ckPrivacidade = !empty($old['aceite_privacidade']);
$ckMarketing = !empty($old['aceite_marketing']);

$temErros = !empty($errors);
// Primeiro campo inválido recebe o foco (ordem do formulário).
$ordemCampos = array('nome', 'email', 'cpf', 'telefone', 'senha', 'senha_confirmacao', 'aceite_termos', 'aceite_privacidade');
$primeiroInvalido = '';
foreach ($ordemCampos as $campo) {
    if (isset($errors[$campo])) { $primeiroInvalido = $campo; break; }
}
$autofocusCampo = $primeiroInvalido !== '' ? $primeiroInvalido : 'nome';

$erroDe = function ($campo) use ($errors) {
    return isset($errors[$campo]) ? (string) $errors[$campo] : '';
};
?>
<div class="v2-auth-split">
  <aside class="v2-auth-aside" aria-hidden="true">
    <h2 class="v2-h1 v2-auth-aside-title">Comece sua jornada na <em>Desbloqueia.</em></h2>
    <p class="v2-muted">Crie sua conta para acessar cursos, inscrições e certificados.</p>
    <ul class="v2-auth-benes">
      <li><span class="v2-auth-bene-ic"><i class="ti ti-player-play"></i></span> Acesse seus cursos quando quiser</li>
      <li><span class="v2-auth-bene-ic"><i class="ti ti-certificate"></i></span> Certificados de conclusão</li>
      <li><span class="v2-auth-bene-ic"><i class="ti ti-device-mobile"></i></span> Computador e celular</li>
    </ul>
  </aside>

  <div class="v2-auth-card">
    <a href="<?php echo Helpers::e($homeHref); ?>" class="v2-auth-logo"><img src="/v2/assets/img/logo-v2.svg" alt="Desbloqueia Cursos"></a>
    <h1 class="v2-h2 v2-center">Criar conta</h1>
    <p class="v2-muted v2-center v2-sm" style="margin-bottom:20px;">Preencha seus dados para criar sua conta.</p>

    <?php if ($jaLogado): ?>
      <div class="v2-callout v2-callout-info" role="status">
        <i class="ti ti-user-check"></i>
        <span>Você já está autenticado. <a href="<?php echo Helpers::e($areaHref); ?>">Ir para a sua área</a>.</span>
      </div>
    <?php endif; ?>

    <?php if ($temErros): ?>
      <div class="v2-callout v2-callout-danger" id="v2-cad-erros" role="alert" aria-live="assertive">
        <i class="ti ti-alert-triangle"></i>
        <span>Não foi possível concluir o cadastro. Revise os campos destacados abaixo.</span>
      </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
      <div class="v2-callout v2-callout-success" role="status" aria-live="polite">
        <i class="ti ti-circle-check"></i>
        <span><?php echo Helpers::e(is_array($success) && !empty($success['message']) ? (string) $success['message'] : (string) $success); ?></span>
      </div>
    <?php endif; ?>

    <form method="post" action="<?php echo Helpers::e($cadastroAction); ?>" id="v2-cadastro-real" data-native-submit novalidate>
      <input type="hidden" name="origem" value="v2">
      <?php // Retorno V2 seguro (validado no backend como caminho interno /v2/...). ?>
      <?php $redirectSeguro = isset($redirectSeguro) ? (string) $redirectSeguro : ''; ?>
      <?php if ($redirectSeguro !== ''): ?>
        <input type="hidden" name="redirect" value="<?php echo Helpers::e($redirectSeguro); ?>">
      <?php endif; ?>

      <div class="v2-field<?php echo $erroDe('nome') !== '' ? ' has-error' : ''; ?>">
        <label for="v2-cad-nome">Nome</label>
        <input type="text" id="v2-cad-nome" name="nome" class="v2-input" value="<?php echo Helpers::e($oldNome); ?>"
               autocomplete="name" required<?php echo $autofocusCampo === 'nome' ? ' autofocus' : ''; ?>
               <?php echo $erroDe('nome') !== '' ? 'aria-describedby="e-v2-cad-nome"' : ''; ?>>
        <div class="v2-field-error" id="e-v2-cad-nome"><?php echo Helpers::e($erroDe('nome')); ?></div>
      </div>

      <div class="v2-field<?php echo $erroDe('email') !== '' ? ' has-error' : ''; ?>">
        <label for="v2-cad-email">E-mail</label>
        <input type="email" id="v2-cad-email" name="email" class="v2-input" value="<?php echo Helpers::e($oldEmail); ?>"
               autocomplete="email" required<?php echo $autofocusCampo === 'email' ? ' autofocus' : ''; ?>
               <?php echo $erroDe('email') !== '' ? 'aria-describedby="e-v2-cad-email"' : ''; ?>>
        <div class="v2-field-error" id="e-v2-cad-email"><?php echo Helpers::e($erroDe('email')); ?></div>
      </div>

      <div class="v2-field<?php echo $erroDe('cpf') !== '' ? ' has-error' : ''; ?>">
        <label for="v2-cad-cpf">CPF</label>
        <input type="text" id="v2-cad-cpf" name="cpf" class="v2-input" value="<?php echo Helpers::e($oldCpf); ?>"
               placeholder="000.000.000-00" maxlength="14" inputmode="numeric" data-mask-cpf
               pattern="^\d{3}\.\d{3}\.\d{3}-\d{2}$|^\d{11}$" required<?php echo $autofocusCampo === 'cpf' ? ' autofocus' : ''; ?>
               <?php echo $erroDe('cpf') !== '' ? 'aria-describedby="e-v2-cad-cpf"' : ''; ?>>
        <div class="v2-field-error" id="e-v2-cad-cpf"><?php echo Helpers::e($erroDe('cpf')); ?></div>
      </div>

      <div class="v2-field<?php echo $erroDe('telefone') !== '' ? ' has-error' : ''; ?>">
        <label for="v2-cad-telefone">WhatsApp <span class="v2-muted v2-sm">(opcional)</span></label>
        <input type="tel" id="v2-cad-telefone" name="telefone" class="v2-input" value="<?php echo Helpers::e($oldTelefone); ?>"
               autocomplete="tel"<?php echo $autofocusCampo === 'telefone' ? ' autofocus' : ''; ?>
               <?php echo $erroDe('telefone') !== '' ? 'aria-describedby="e-v2-cad-telefone"' : ''; ?>>
        <div class="v2-field-error" id="e-v2-cad-telefone"><?php echo Helpers::e($erroDe('telefone')); ?></div>
      </div>

      <div class="v2-field<?php echo $erroDe('senha') !== '' ? ' has-error' : ''; ?>">
        <label for="v2-cad-senha">Senha <span class="v2-muted v2-sm">(mínimo 8 caracteres)</span></label>
        <div class="v2-input-wrap">
          <input type="password" id="v2-cad-senha" name="senha" class="v2-input" placeholder="Crie uma senha"
                 autocomplete="new-password" required<?php echo $autofocusCampo === 'senha' ? ' autofocus' : ''; ?>
                 <?php echo $erroDe('senha') !== '' ? 'aria-describedby="e-v2-cad-senha"' : ''; ?>>
          <button type="button" class="v2-eye" data-toggle-pass="#v2-cad-senha" aria-label="Mostrar senha"><i class="ti ti-eye"></i></button>
        </div>
        <div class="v2-field-error" id="e-v2-cad-senha"><?php echo Helpers::e($erroDe('senha')); ?></div>
      </div>

      <div class="v2-field<?php echo $erroDe('senha_confirmacao') !== '' ? ' has-error' : ''; ?>">
        <label for="v2-cad-senha2">Confirmar senha</label>
        <div class="v2-input-wrap">
          <input type="password" id="v2-cad-senha2" name="senha_confirmacao" class="v2-input" placeholder="Repita a senha"
                 autocomplete="new-password" required<?php echo $autofocusCampo === 'senha_confirmacao' ? ' autofocus' : ''; ?>
                 <?php echo $erroDe('senha_confirmacao') !== '' ? 'aria-describedby="e-v2-cad-senha2"' : ''; ?>>
          <button type="button" class="v2-eye" data-toggle-pass="#v2-cad-senha2" aria-label="Mostrar senha"><i class="ti ti-eye"></i></button>
        </div>
        <div class="v2-field-error" id="e-v2-cad-senha2"><?php echo Helpers::e($erroDe('senha_confirmacao')); ?></div>
      </div>

      <div class="v2-field<?php echo $erroDe('aceite_termos') !== '' ? ' has-error' : ''; ?>">
        <label class="v2-check v2-check-inline">
          <input type="checkbox" name="aceite_termos" value="1"<?php echo $ckTermos ? ' checked' : ''; ?>>
          <span>Aceito os <a href="<?php echo Helpers::e($termosHref); ?>" target="_blank" rel="noopener">termos de uso</a></span>
        </label>
        <div class="v2-field-error"><?php echo Helpers::e($erroDe('aceite_termos')); ?></div>
      </div>

      <div class="v2-field<?php echo $erroDe('aceite_privacidade') !== '' ? ' has-error' : ''; ?>">
        <label class="v2-check v2-check-inline">
          <input type="checkbox" name="aceite_privacidade" value="1"<?php echo $ckPrivacidade ? ' checked' : ''; ?>>
          <span>Aceito a <a href="<?php echo Helpers::e($privacidadeHref); ?>" target="_blank" rel="noopener">política de privacidade</a></span>
        </label>
        <div class="v2-field-error"><?php echo Helpers::e($erroDe('aceite_privacidade')); ?></div>
      </div>

      <div class="v2-field">
        <label class="v2-check v2-check-inline">
          <input type="checkbox" name="aceite_marketing" value="1"<?php echo $ckMarketing ? ' checked' : ''; ?>>
          <span>Quero receber comunicações <span class="v2-muted v2-sm">(opcional)</span></span>
        </label>
      </div>

      <button type="submit" class="v2-btn v2-btn-primary v2-btn-block">Criar conta <i class="ti ti-arrow-right"></i></button>
    </form>

    <div class="v2-auth-foot">Já tem conta? <a href="<?php echo Helpers::e($loginHref); ?>">Entrar</a></div>
  </div>
</div>
