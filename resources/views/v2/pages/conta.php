<?php
use App\Core\Helpers;

// -----------------------------------------------------------------------------
// Editar cadastro V2 — casca visual sobre AuthService::updateAccount() (mesma
// regra real do /minha-conta legado). Sem dados fictícios, sem lógica nova.
// -----------------------------------------------------------------------------

$conta = isset($conta) && is_array($conta) ? $conta : array();
$old = isset($old) && is_array($old) ? $old : array();
$errors = isset($errors) && is_array($errors) ? $errors : array();
$success = isset($success) ? $success : null;

$campo = function ($chave, $fallback = '') use ($old, $conta) {
    if (isset($old[$chave]) && (string) $old[$chave] !== '') {
        return (string) $old[$chave];
    }
    return isset($conta[$chave]) && (string) $conta[$chave] !== '' ? (string) $conta[$chave] : $fallback;
};
$erro = function ($chave) use ($errors) {
    return isset($errors[$chave]) ? (string) $errors[$chave] : '';
};
$estadoAtual = strtoupper((string) $campo('estado'));
$cidadeAtual = (string) $campo('cidade');
$ufs = array('AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO');
?>
<script>window.V2_DISABLE_AUTORENDER_HOME = true;</script>

<section class="v2-container v2-poslogin-wrap" style="align-items:flex-start;padding-top:32px;">
  <div class="v2-poslogin" style="max-width:640px;">
    <nav class="v2-breadcrumb" aria-label="Caminho">
      <a href="/v2/aluno/?aba=perfil">Minha área</a>
      <i class="ti ti-chevron-right" aria-hidden="true"></i>
      <span aria-current="page">Editar cadastro</span>
    </nav>

    <div class="v2-poslogin-head" style="text-align:left;">
      <h1 class="v2-h1" style="font-size:1.6rem;">Editar cadastro</h1>
      <p class="v2-muted v2-sm" style="margin-top:6px;">Atualize seus dados pessoais e, se quiser, sua senha.</p>
    </div>

    <?php if (!empty($success)): ?>
      <div class="v2-callout v2-callout-success" role="status"><i class="ti ti-circle-check"></i><span><?php echo Helpers::e((string) $success); ?></span></div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
      <div class="v2-callout v2-callout-danger" role="alert"><i class="ti ti-alert-triangle"></i><span><?php foreach ($errors as $msgErro): ?><?php echo Helpers::e((string) $msgErro); ?><br><?php endforeach; ?></span></div>
    <?php endif; ?>

    <form method="post" action="/v2/minha-conta" class="v2-checkout-form" data-native-submit id="v2-conta-form">
      <section class="v2-block">
        <h2 class="v2-h3" style="margin:0 0 10px;">Dados pessoais</h2>

        <div class="v2-field">
          <label for="conta-nome">Nome</label>
          <input class="v2-input" type="text" id="conta-nome" name="nome" value="<?php echo Helpers::e($campo('nome')); ?>" required>
        </div>
        <div class="v2-field">
          <label for="conta-email">E-mail</label>
          <input class="v2-input" type="email" id="conta-email" name="email" value="<?php echo Helpers::e($campo('email')); ?>" required>
        </div>
        <div class="v2-field">
          <label for="conta-cpf">CPF</label>
          <input class="v2-input" type="text" id="conta-cpf" name="cpf" value="<?php echo Helpers::e($campo('cpf')); ?>" placeholder="000.000.000-00" maxlength="14" inputmode="numeric" data-mask-cpf required>
        </div>
        <div class="v2-field">
          <label for="conta-tel">WhatsApp</label>
          <input class="v2-input" type="text" id="conta-tel" name="telefone" value="<?php echo Helpers::e($campo('telefone')); ?>" inputmode="tel">
        </div>
        <div class="v2-field">
          <label for="conta-estado">Estado</label>
          <select class="v2-input" id="conta-estado" name="estado">
            <option value="">Selecione o estado</option>
            <?php foreach ($ufs as $uf): ?>
              <option value="<?php echo Helpers::e($uf); ?>"<?php echo $estadoAtual === $uf ? ' selected' : ''; ?>><?php echo Helpers::e($uf); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="v2-field">
          <label for="conta-cidade">Cidade</label>
          <select class="v2-input" id="conta-cidade" name="cidade">
            <option value="">Selecione o estado primeiro</option>
          </select>
        </div>
      </section>

      <section class="v2-block">
        <h2 class="v2-h3" style="margin:0 0 10px;">Trocar senha <span class="v2-muted v2-sm">(opcional)</span></h2>
        <div class="v2-field">
          <label for="conta-nova-senha">Nova senha</label>
          <input class="v2-input" type="password" id="conta-nova-senha" name="nova_senha" minlength="8" autocomplete="new-password">
        </div>
        <div class="v2-field">
          <label for="conta-nova-senha-conf">Confirmar nova senha</label>
          <input class="v2-input" type="password" id="conta-nova-senha-conf" name="nova_senha_confirmacao" minlength="8" autocomplete="new-password">
        </div>
      </section>

      <div class="v2-quiz-actions">
        <button type="submit" class="v2-btn v2-btn-primary" data-checkout-btn data-loading-label="Salvando…"><i class="ti ti-check"></i> Salvar alterações</button>
        <a class="v2-btn v2-btn-ghost" href="/v2/aluno/?aba=perfil">Cancelar</a>
      </div>
    </form>
  </div>
</section>

<script>
(function () {
  var estadoSelect = document.getElementById('conta-estado');
  var cidadeSelect = document.getElementById('conta-cidade');
  if (!estadoSelect || !cidadeSelect) { return; }

  var estadoInicial = <?php echo json_encode($estadoAtual, JSON_UNESCAPED_UNICODE); ?>;
  var cidadeInicial = <?php echo json_encode($cidadeAtual, JSON_UNESCAPED_UNICODE); ?>;

  var cacheCidades = {};
  var ibgeUfs = {
    'AC': 12, 'AL': 27, 'AP': 16, 'AM': 13, 'BA': 29, 'CE': 23, 'DF': 53, 'ES': 32, 'GO': 52,
    'MA': 21, 'MT': 51, 'MS': 50, 'MG': 31, 'PA': 15, 'PB': 25, 'PR': 41, 'PE': 26, 'PI': 22,
    'RJ': 33, 'RN': 24, 'RS': 43, 'RO': 11, 'RR': 14, 'SC': 42, 'SP': 35, 'SE': 28, 'TO': 17
  };

  function popularCidadesComLista(cidades, cidadeSelecionada) {
    cidadeSelect.innerHTML = '';
    if (!Array.isArray(cidades) || cidades.length === 0) {
      cidadeSelect.disabled = true;
      cidadeSelect.appendChild(new Option('Selecione o estado primeiro', ''));
      return;
    }
    cidadeSelect.disabled = false;
    cidadeSelect.appendChild(new Option('Selecione a cidade', ''));
    for (var i = 0; i < cidades.length; i++) {
      var cidade = cidades[i];
      var option = new Option(cidade, cidade);
      if (cidadeSelecionada && cidade === cidadeSelecionada) { option.selected = true; }
      cidadeSelect.appendChild(option);
    }
  }

  function carregarCidades(uf, cidadeSelecionada) {
    if (!uf || !ibgeUfs[uf]) { popularCidadesComLista([], ''); return; }
    if (cacheCidades[uf]) { popularCidadesComLista(cacheCidades[uf], cidadeSelecionada); return; }

    cidadeSelect.disabled = true;
    cidadeSelect.innerHTML = '';
    cidadeSelect.appendChild(new Option('Carregando cidades...', ''));

    var url = 'https://servicodados.ibge.gov.br/api/v1/localidades/estados/' + ibgeUfs[uf] + '/municipios?orderBy=nome';
    fetch(url)
      .then(function (response) { if (!response.ok) { throw new Error('Falha ao carregar cidades'); } return response.json(); })
      .then(function (data) {
        var cidades = [];
        for (var i = 0; i < data.length; i++) { if (data[i] && data[i].nome) { cidades.push(data[i].nome); } }
        cacheCidades[uf] = cidades;
        popularCidadesComLista(cidades, cidadeSelecionada);
      })
      .catch(function () {
        cidadeSelect.disabled = true;
        cidadeSelect.innerHTML = '';
        cidadeSelect.appendChild(new Option('Não foi possível carregar as cidades', ''));
      });
  }

  estadoSelect.addEventListener('change', function () { carregarCidades(estadoSelect.value, ''); });

  if (estadoInicial) {
    estadoSelect.value = estadoInicial;
    carregarCidades(estadoInicial, cidadeInicial);
  } else {
    popularCidadesComLista([], '');
  }
})();
</script>
