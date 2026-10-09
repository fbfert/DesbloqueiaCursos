<?php
/**
 * Minha conta no tema caderno ("ficha cadastral"). Mesmo formulário de
 * resources/views/v2/pages/conta.php (ContaController V2 → AuthService::updateAccount):
 * POST /v2/minha-conta, id v2-conta-form, campos nome, email, cpf (data-mask-cpf),
 * telefone, estado, cidade, nova_senha e nova_senha_confirmacao; _token injetado
 * pelo View::render. Valores: old (após erro) com a conta como reserva, como a V2;
 * senha nunca é repopulada. Erros chegam por campo (errors[campo]) e aparecem em
 * texto junto do campo (aria-describedby); os que não são de campo vão no aviso.
 *
 * Cidades: o módulo `conta` de caderno-aluno.js carrega a lista do IBGE pela UF
 * (estado/cidade atuais em data-* do form, sem script inline) e, se a busca
 * falhar, troca o select por um campo de texto. Sem JS, a cidade atual já vem
 * como opção selecionada, para não se perder ao salvar.
 */

use App\Core\Helpers;

require_once BASE_PATH . '/resources/views/caderno/partials/checkout-util.php';
require BASE_PATH . '/resources/views/caderno/partials/ver-senha.php';

$conta = isset($conta) && is_array($conta) ? $conta : array();
$old = isset($old) && is_array($old) ? $old : array();
$errors = isset($errors) && is_array($errors) ? $errors : array();
$success = isset($success) ? $success : null;
// login-google: CPF só pode ser informado enquanto vazio; depois, só o atendimento altera.
$cpfPreenchido = !empty($cpfPreenchido);
$vinculoGoogle = isset($vinculoGoogle) && is_array($vinculoGoogle) ? $vinculoGoogle : null;
$temSenha = !empty($temSenha);

$campo = function ($chave) use ($old, $conta) {
    if (isset($old[$chave]) && is_scalar($old[$chave]) && (string) $old[$chave] !== '') {
        return (string) $old[$chave];
    }
    return isset($conta[$chave]) && is_scalar($conta[$chave]) ? (string) $conta[$chave] : '';
};
$estadoAtual = strtoupper($campo('estado'));
$cidadeAtual = $campo('cidade');
$ufs = array('AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO');

// Erros por campo (chave = name) e o primeiro campo inválido recebe o foco.
$ctCampos = array('nome', 'email', 'cpf', 'telefone', 'estado', 'cidade', 'nova_senha', 'nova_senha_confirmacao');
$ctErros = array();
$ctGerais = array();
foreach ($errors as $chave => $msg) {
    if (!is_scalar($msg) || trim((string) $msg) === '') {
        continue;
    }
    if (in_array((string) $chave, $ctCampos, true)) {
        $ctErros[(string) $chave] = (string) $msg;
    } else {
        $ctGerais[] = (string) $msg;
    }
}
$ctPrimeiro = '';
foreach ($ctCampos as $c) {
    if (isset($ctErros[$c])) { $ctPrimeiro = $c; break; }
}
$ctIds = array(
    'nome' => 'conta-nome', 'email' => 'conta-email', 'cpf' => 'conta-cpf', 'telefone' => 'conta-tel',
    'estado' => 'conta-estado', 'cidade' => 'conta-cidade', 'nova_senha' => 'conta-nova-senha',
    'nova_senha_confirmacao' => 'conta-nova-senha-conf',
);
$ct = array();
foreach ($ctCampos as $c) {
    $ct[$c] = caderno_ck_campo($ctErros, $c, $ctIds[$c], $c === 'nova_senha' ? 'conta-senha-ajuda' : '');
    if ($c === $ctPrimeiro) {
        $ct[$c]['attrs'] .= ' autofocus';
    }
}
$temErros = !empty($ctErros) || !empty($ctGerais);
?>
<div class="ck ct">
  <nav class="migalha" aria-label="Caminho">
    <a href="/v2/aluno/?aba=perfil">Minha área</a><span aria-hidden="true">›</span><span aria-current="page">Editar cadastro</span>
  </nav>
  <header class="ck-cab">
    <p class="al-ola">Ficha cadastral</p>
    <h1 class="t2">Editar cadastro</h1>
    <p class="lead">Atualize seus dados pessoais e, se quiser, sua senha.</p>
  </header>

  <?php if (!empty($success) || $temErros): ?>
  <div class="ck-avisos ct-avisos">
    <?php if (!empty($success)): ?>
    <div class="postit ok largo" role="status" aria-live="polite"><?= Helpers::e(is_array($success) ? (string) ($success['message'] ?? '') : (string) $success) ?></div>
    <?php endif; ?>
    <?php if ($temErros): ?>
    <div class="postit erro largo" role="alert">
      <b>Não foi possível salvar.</b>
      <?php if (!empty($ctErros)): ?>Revise os campos destacados abaixo.<?php endif; ?>
      <?php foreach ($ctGerais as $msg): ?><br><?= Helpers::e($msg) ?><?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <div class="ck-grade">
    <form method="post" action="/v2/minha-conta" class="ck-form" data-native-submit id="v2-conta-form"
          data-estado="<?= Helpers::e($estadoAtual) ?>" data-cidade="<?= Helpers::e($cidadeAtual) ?>">
      <fieldset class="ck-bloco">
        <legend class="t3">Dados pessoais</legend>
        <div class="campo<?= $ct['nome']['classe'] ?>">
          <label for="conta-nome">Nome</label>
          <input type="text" id="conta-nome" name="nome" value="<?= Helpers::e($campo('nome')) ?>" autocomplete="name" required<?= $ct['nome']['attrs'] ?>>
          <?= $ct['nome']['msg'] ?>
        </div>
        <div class="campo<?= $ct['email']['classe'] ?>">
          <label for="conta-email">E-mail</label>
          <input type="email" id="conta-email" name="email" value="<?= Helpers::e($campo('email')) ?>" autocomplete="email" required<?= $ct['email']['attrs'] ?>>
          <?= $ct['email']['msg'] ?>
        </div>
        <div class="ck-par2">
          <div class="campo<?= $ct['cpf']['classe'] ?>">
            <label for="conta-cpf">CPF</label>
            <?php if ($cpfPreenchido): ?>
            <input type="text" id="conta-cpf" value="<?= Helpers::e($campo('cpf')) ?>" readonly aria-describedby="conta-cpf-ajuda">
            <p class="ajuda" id="conta-cpf-ajuda">Para alterar o CPF, fale com o atendimento.</p>
            <?php else: ?>
            <input type="text" id="conta-cpf" name="cpf" value="<?= Helpers::e($campo('cpf')) ?>" placeholder="000.000.000-00" maxlength="14" inputmode="numeric" data-mask-cpf<?= $ct['cpf']['attrs'] ?>>
            <p class="ajuda" id="cpf">Informe seu CPF para podermos emitir seus certificados. Depois de salvo, só o atendimento altera.</p>
            <?php endif; ?>
            <?= $ct['cpf']['msg'] ?>
          </div>
          <div class="campo<?= $ct['telefone']['classe'] ?>">
            <label for="conta-tel">WhatsApp</label>
            <input type="text" id="conta-tel" name="telefone" value="<?= Helpers::e($campo('telefone')) ?>" inputmode="tel" autocomplete="tel"<?= $ct['telefone']['attrs'] ?>>
            <?= $ct['telefone']['msg'] ?>
          </div>
        </div>
        <div class="ct-local">
          <div class="campo<?= $ct['estado']['classe'] ?>">
            <label for="conta-estado">Estado</label>
            <select id="conta-estado" name="estado"<?= $ct['estado']['attrs'] ?>>
              <option value="">Selecione o estado</option>
              <?php foreach ($ufs as $uf): ?>
              <option value="<?= $uf ?>"<?= $estadoAtual === $uf ? ' selected' : '' ?>><?= $uf ?></option>
              <?php endforeach; ?>
            </select>
            <?= $ct['estado']['msg'] ?>
          </div>
          <div class="campo<?= $ct['cidade']['classe'] ?>">
            <label for="conta-cidade">Cidade</label>
            <select id="conta-cidade" name="cidade" data-cidade-campo<?= $ct['cidade']['attrs'] ?>>
              <?php if ($cidadeAtual !== ''): ?>
              <option value="">Selecione a cidade</option>
              <option value="<?= Helpers::e($cidadeAtual) ?>" selected><?= Helpers::e($cidadeAtual) ?></option>
              <?php else: ?>
              <option value="">Selecione o estado primeiro</option>
              <?php endif; ?>
            </select>
            <p class="ajuda ct-status" id="conta-cidade-status" data-cidade-status aria-live="polite"></p>
            <?= $ct['cidade']['msg'] ?>
          </div>
        </div>
      </fieldset>

      <fieldset class="ck-bloco">
        <legend class="t3">Trocar senha <span class="opcional">(opcional)</span></legend>
        <p class="ajuda ct-ajuda" id="conta-senha-ajuda">Deixe em branco para manter a senha atual. Mínimo de 8 caracteres.</p>
        <div class="campo<?= $ct['nova_senha']['classe'] ?>">
          <label for="conta-nova-senha">Nova senha</label>
          <div class="campo-senha">
            <input type="password" id="conta-nova-senha" name="nova_senha" minlength="8" autocomplete="new-password"<?= $ct['nova_senha']['attrs'] ?>>
            <?= caderno_ver_senha('conta-nova-senha') ?>
          </div>
          <?= $ct['nova_senha']['msg'] ?>
        </div>
        <div class="campo<?= $ct['nova_senha_confirmacao']['classe'] ?>">
          <label for="conta-nova-senha-conf">Confirmar nova senha</label>
          <div class="campo-senha">
            <input type="password" id="conta-nova-senha-conf" name="nova_senha_confirmacao" minlength="8" autocomplete="new-password"<?= $ct['nova_senha_confirmacao']['attrs'] ?>>
            <?= caderno_ver_senha('conta-nova-senha-conf') ?>
          </div>
          <?= $ct['nova_senha_confirmacao']['msg'] ?>
        </div>
      </fieldset>

      <div class="form-acoes">
        <button type="submit" class="btn" data-checkout-btn data-loading-label="Salvando…">Salvar alterações</button>
        <a class="btn-sec" href="/v2/aluno/?aba=perfil">Cancelar<?= caderno_ck_contorno() ?></a>
      </div>
    </form>

    <aside class="ck-lado ct-lado">
      <?php if ($vinculoGoogle): ?>
      <div class="postit ct-google">
        <b>Conta Google vinculada</b>
        <p>Você também entra com o Google<?= !empty($vinculoGoogle['email']) ? ' (' . Helpers::e($vinculoGoogle['email']) . ')' : '' ?>.</p>
        <?php if ($temSenha): ?>
        <form method="post" action="/v2/minha-conta/google/desvincular">
          <button type="submit" class="link">Desvincular conta Google</button>
        </form>
        <?php else: ?>
        <p>Para poder desvincular, defina antes uma senha no formulário ao lado.</p>
        <?php endif; ?>
      </div>
      <?php endif; ?>
      <div class="postit">
        <b>Dica</b>
        <p>Escolha o estado primeiro: a lista de cidades aparece logo em seguida. Para mudar só os dados pessoais, deixe a senha em branco.</p>
      </div>
    </aside>
  </div>
</div>
