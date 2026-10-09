<?php
/**
 * Cadastro no tema caderno. Mesmo formulário da V2 (resources/views/v2/pages/cadastro.php):
 * POST em $cadastroAction com nome, email, cpf, telefone, senha, senha_confirmacao,
 * aceite_termos, aceite_privacidade, aceite_marketing, `origem=v2` e `redirect`
 * (quando houver); _token injetado pelo View::render. Senha nunca é repopulada.
 */

use App\Core\Helpers;

require BASE_PATH . '/resources/views/caderno/partials/ver-senha.php';

$errors = isset($errors) && is_array($errors) ? $errors : array();
$success = isset($success) ? $success : null;
$old = isset($old) && is_array($old) ? $old : array();
$cadastroAction = isset($cadastroAction) ? (string) $cadastroAction : '/cadastro';
$redirectSeguro = isset($redirectSeguro) ? (string) $redirectSeguro : '';
$loginHref = $authLinks['login'];
$termosHref = $authLinks['termos'];
$privacidadeHref = $authLinks['privacidade'];
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
$ordemCampos = array('nome', 'email', 'cpf', 'telefone', 'senha', 'senha_confirmacao', 'aceite_termos', 'aceite_privacidade');
$primeiroInvalido = '';
foreach ($ordemCampos as $campo) {
    if (isset($errors[$campo])) { $primeiroInvalido = $campo; break; }
}
$autofocusCampo = $primeiroInvalido !== '' ? $primeiroInvalido : 'nome';
// Erros que não pertencem a um campo do formulário (ex.: sessão expirada) vão no aviso do topo.
$errosGerais = array();
foreach ($errors as $chave => $msg) {
    if (!in_array((string) $chave, $ordemCampos, true)) {
        $errosGerais[] = (string) $msg;
    }
}

$erroDe = function ($campo) use ($errors) {
    return isset($errors[$campo]) ? (string) $errors[$campo] : '';
};
// Atributos de foco/erro de um campo: autofocus, aria-invalid e aria-describedby.
$attrs = function ($campo, $erroId) use ($erroDe, $autofocusCampo) {
    $a = $autofocusCampo === $campo ? ' autofocus' : '';
    if ($erroDe($campo) !== '') {
        $a .= ' aria-invalid="true" aria-describedby="' . $erroId . '"';
    }
    return $a;
};
$msgErro = function ($campo, $erroId) use ($erroDe) {
    $m = $erroDe($campo);
    return $m !== '' ? '<p class="erro-msg" id="' . $erroId . '">' . Helpers::e($m) . '</p>' : '';
};
?>
<div class="auth-cab">
  <h1 class="t2">Criar conta</h1>
  <p class="lead">Preencha seus dados para criar sua conta.</p>
</div>

<?php if ($jaLogado): ?>
<div class="postit auth-aviso" role="status">
  <b>Você já está autenticado.</b>
  <a class="link" href="<?= Helpers::e($areaHref) ?>">Ir para a sua área</a>
</div>
<?php endif; ?>

<?php if ($temErros): ?>
<div class="postit erro auth-aviso" role="alert">
  <b>Não foi possível concluir o cadastro.</b>
  Revise os campos destacados abaixo.
  <?php foreach ($errosGerais as $msg): ?><br><?= Helpers::e($msg) ?><?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!empty($success)): ?>
<div class="postit ok auth-aviso" role="status" aria-live="polite"><?= Helpers::e(is_array($success) && !empty($success['message']) ? (string) $success['message'] : (string) $success) ?></div>
<?php endif; ?>

<form class="auth-form" method="post" action="<?= Helpers::e($cadastroAction) ?>" novalidate>
  <input type="hidden" name="origem" value="v2">
  <?php if ($redirectSeguro !== ''): ?>
  <input type="hidden" name="redirect" value="<?= Helpers::e($redirectSeguro) ?>">
  <?php endif; ?>

  <div class="campo<?= $erroDe('nome') !== '' ? ' erro' : '' ?>">
    <label for="cad-nome">Nome</label>
    <input type="text" id="cad-nome" name="nome" value="<?= Helpers::e($oldNome) ?>" autocomplete="name" required<?= $attrs('nome', 'e-cad-nome') ?>>
    <?= $msgErro('nome', 'e-cad-nome') ?>
  </div>

  <div class="campo<?= $erroDe('email') !== '' ? ' erro' : '' ?>">
    <label for="cad-email">E-mail</label>
    <input type="email" id="cad-email" name="email" value="<?= Helpers::e($oldEmail) ?>" autocomplete="email" required<?= $attrs('email', 'e-cad-email') ?>>
    <?= $msgErro('email', 'e-cad-email') ?>
  </div>

  <div class="campo<?= $erroDe('cpf') !== '' ? ' erro' : '' ?>">
    <label for="cad-cpf">CPF</label>
    <input type="text" id="cad-cpf" name="cpf" value="<?= Helpers::e($oldCpf) ?>"
           placeholder="000.000.000-00" maxlength="14" inputmode="numeric" data-mask-cpf
           pattern="^\d{3}\.\d{3}\.\d{3}-\d{2}$|^\d{11}$" required<?= $attrs('cpf', 'e-cad-cpf') ?>>
    <?= $msgErro('cpf', 'e-cad-cpf') ?>
  </div>

  <div class="campo<?= $erroDe('telefone') !== '' ? ' erro' : '' ?>">
    <label for="cad-telefone">WhatsApp <span class="opcional">(opcional)</span></label>
    <input type="tel" id="cad-telefone" name="telefone" value="<?= Helpers::e($oldTelefone) ?>" autocomplete="tel"<?= $attrs('telefone', 'e-cad-telefone') ?>>
    <?= $msgErro('telefone', 'e-cad-telefone') ?>
  </div>

  <div class="campo<?= $erroDe('senha') !== '' ? ' erro' : '' ?>">
    <label for="cad-senha">Senha <span class="opcional">(mínimo 8 caracteres)</span></label>
    <div class="campo-senha">
      <input type="password" id="cad-senha" name="senha" placeholder="Crie uma senha" autocomplete="new-password" required<?= $attrs('senha', 'e-cad-senha') ?>>
      <?= caderno_ver_senha('cad-senha') ?>
    </div>
    <?= $msgErro('senha', 'e-cad-senha') ?>
    <div class="forca" data-forca="cad-senha" data-nivel="0">
      <span class="forca-trilho" aria-hidden="true"><i></i></span>
      <span class="forca-texto" aria-live="polite"></span>
    </div>
  </div>

  <div class="campo<?= $erroDe('senha_confirmacao') !== '' ? ' erro' : '' ?>">
    <label for="cad-senha2">Confirmar senha</label>
    <div class="campo-senha">
      <input type="password" id="cad-senha2" name="senha_confirmacao" placeholder="Repita a senha" autocomplete="new-password" required<?= $attrs('senha_confirmacao', 'e-cad-senha2') ?>>
      <?= caderno_ver_senha('cad-senha2') ?>
    </div>
    <?= $msgErro('senha_confirmacao', 'e-cad-senha2') ?>
  </div>

  <div class="auth-aceites">
    <div class="aceite<?= $erroDe('aceite_termos') !== '' ? ' erro' : '' ?>">
      <label class="marcar">
        <input type="checkbox" name="aceite_termos" value="1"<?= $ckTermos ? ' checked' : '' ?><?= $attrs('aceite_termos', 'e-cad-termos') ?>>
        <span>Aceito os <a class="link" href="<?= Helpers::e($termosHref) ?>" target="_blank" rel="noopener">termos de uso</a></span>
      </label>
      <?= $msgErro('aceite_termos', 'e-cad-termos') ?>
    </div>

    <div class="aceite<?= $erroDe('aceite_privacidade') !== '' ? ' erro' : '' ?>">
      <label class="marcar">
        <input type="checkbox" name="aceite_privacidade" value="1"<?= $ckPrivacidade ? ' checked' : '' ?><?= $attrs('aceite_privacidade', 'e-cad-privacidade') ?>>
        <span>Aceito a <a class="link" href="<?= Helpers::e($privacidadeHref) ?>" target="_blank" rel="noopener">política de privacidade</a></span>
      </label>
      <?= $msgErro('aceite_privacidade', 'e-cad-privacidade') ?>
    </div>

    <div class="aceite">
      <label class="marcar">
        <input type="checkbox" name="aceite_marketing" value="1"<?= $ckMarketing ? ' checked' : '' ?>>
        <span>Quero receber comunicações <span class="opcional">(opcional)</span></span>
      </label>
    </div>
  </div>

  <button type="submit" class="btn btn-bloco">Criar conta <?= caderno_icone('seta-dir') ?></button>
</form>

<?php require_once BASE_PATH . '/resources/views/partials/botao-google.php'; ?>
<?= botao_google(array('origem' => 'v2', 'redirect' => $redirectSeguro, 'termos' => $termosHref, 'privacidade' => $privacidadeHref)) ?>

<p class="auth-pe">Já tem conta? <a class="link" href="<?= Helpers::e($loginHref) ?>">Entrar</a></p>
