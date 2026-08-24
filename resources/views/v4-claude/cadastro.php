<?php use App\Core\Helpers; ?>
<?php
// Cadastro (template v4-claude). Incluído por auth/register.php.
// Campos reais: nome, email, cpf, telefone, senha, senha_confirmacao, aceite_termos, aceite_privacidade, aceite_marketing.
$errors = isset($errors) && is_array($errors) ? $errors : array();
$old = isset($old) && is_array($old) ? $old : array();
?>

<div class="dc-auth-wrap">
  <div class="dc-auth-card">

    <a href="/" class="dc-auth-logo">Desbloqueia&nbsp;<strong>Cursos</strong></a>

    <h1 class="dc-auth-titulo">Crie sua conta</h1>
    <p class="dc-text-muted dc-text-sm" style="text-align:center;margin-bottom:20px;">É rápido e gratuito. Comece a aprender hoje.</p>

    <?php if (!empty($errors)): ?>
      <div class="dc-callout dc-callout-danger">
        <i class="ti ti-alert-circle"></i>
        <span><?php foreach ($errors as $erro): ?><?php echo Helpers::e((string) $erro); ?><br><?php endforeach; ?></span>
      </div>
    <?php endif; ?>

    <form method="post" action="/cadastro">
      <?php echo $csrfField; ?>

      <div class="dc-field">
        <label for="dc-cad-nome">Nome completo</label>
        <input type="text" id="dc-cad-nome" name="nome" class="dc-input" placeholder="Seu nome completo"
               value="<?php echo Helpers::e(isset($old['nome']) ? (string) $old['nome'] : ''); ?>" autocomplete="name" required>
      </div>

      <div class="dc-field">
        <label for="dc-cad-email">E-mail</label>
        <input type="email" id="dc-cad-email" name="email" class="dc-input" placeholder="seuemail@exemplo.com"
               value="<?php echo Helpers::e(isset($old['email']) ? (string) $old['email'] : ''); ?>" autocomplete="email" required>
      </div>

      <div class="dc-field">
        <label for="dc-cad-cpf">CPF</label>
        <input type="text" id="dc-cad-cpf" name="cpf" class="dc-input" placeholder="000.000.000-00"
               value="<?php echo Helpers::e(isset($old['cpf']) ? (string) $old['cpf'] : ''); ?>"
               maxlength="14" inputmode="numeric" pattern="^\d{3}\.\d{3}\.\d{3}-\d{2}$|^\d{11}$" autocomplete="off" required>
      </div>

      <div class="dc-field">
        <label for="dc-cad-telefone">WhatsApp</label>
        <input type="text" id="dc-cad-telefone" name="telefone" class="dc-input" placeholder="(00) 00000-0000"
               value="<?php echo Helpers::e(isset($old['telefone']) ? (string) $old['telefone'] : ''); ?>" autocomplete="tel">
      </div>

      <div class="dc-field">
        <label for="dc-cad-senha">Senha</label>
        <div class="dc-input-wrap">
          <input type="password" id="dc-cad-senha" name="senha" class="dc-input" placeholder="Crie uma senha segura" autocomplete="new-password" required>
          <button type="button" class="dc-input-eye" data-dc-toggle-pass="#dc-cad-senha" aria-label="Mostrar ou ocultar senha">
            <i class="ti ti-eye"></i>
          </button>
        </div>
      </div>

      <div class="dc-field">
        <label for="dc-cad-senha2">Confirmar senha</label>
        <input type="password" id="dc-cad-senha2" name="senha_confirmacao" class="dc-input" placeholder="Repita a senha" autocomplete="new-password" required>
      </div>

      <label class="dc-check">
        <input type="checkbox" name="aceite_termos" value="1" <?php echo !empty($old['aceite_termos']) ? 'checked' : ''; ?>>
        <span>Aceito os <a href="/termos-de-uso" target="_blank" rel="noopener">termos de uso</a></span>
      </label>
      <label class="dc-check">
        <input type="checkbox" name="aceite_privacidade" value="1" <?php echo !empty($old['aceite_privacidade']) ? 'checked' : ''; ?>>
        <span>Aceito a <a href="/politica-de-privacidade" target="_blank" rel="noopener">política de privacidade</a></span>
      </label>
      <label class="dc-check" style="margin-bottom:16px;">
        <input type="checkbox" name="aceite_marketing" value="1" <?php echo !empty($old['aceite_marketing']) ? 'checked' : ''; ?>>
        <span>Quero receber comunicações</span>
      </label>

      <button type="submit" class="dc-btn dc-btn-primary dc-btn-block">Criar conta</button>
    </form>

    <div class="dc-auth-footer">
      Já tenho conta. <a href="/login">Entrar</a>
    </div>

  </div>
</div>

<script>
(function () {
    var cpfInput = document.querySelector('input[name="cpf"]');
    if (!cpfInput) {
        return;
    }

    function formatCpf(value) {
        var digits = String(value || '').replace(/\D+/g, '').slice(0, 11);

        if (digits.length <= 3) {
            return digits;
        }

        if (digits.length <= 6) {
            return digits.slice(0, 3) + '.' + digits.slice(3);
        }

        if (digits.length <= 9) {
            return digits.slice(0, 3) + '.' + digits.slice(3, 6) + '.' + digits.slice(6);
        }

        return digits.slice(0, 3) + '.' + digits.slice(3, 6) + '.' + digits.slice(6, 9) + '-' + digits.slice(9);
    }

    cpfInput.addEventListener('input', function (event) {
        event.target.value = formatCpf(event.target.value);
    });

    cpfInput.value = formatCpf(cpfInput.value);
})();
</script>
