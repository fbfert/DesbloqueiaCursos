<?php use App\Core\Helpers; ?>
<?php
// Incluído por checkout/inscricao.php quando template = v4-claude.
// Mantém os formulários, nomes de campos, IDs e o JS do fluxo original (auth-gated).
$curso   = isset($curso) && is_array($curso) ? $curso : array();
$errors  = isset($errors) && is_array($errors) ? $errors : array();
$success = isset($success) ? (string) $success : '';
$loggedIn = !empty($loggedIn);
$usuarioNome = isset($usuarioNome) ? (string) $usuarioNome : '';
$usuarioEmail = isset($usuarioEmail) ? (string) $usuarioEmail : '';
$pagadorPrefill = isset($pagadorPrefill) && is_array($pagadorPrefill) ? $pagadorPrefill : array();
$situacaoInscricao = isset($situacaoInscricao) && is_array($situacaoInscricao) ? $situacaoInscricao : array();
$statusFluxo = isset($situacaoInscricao['status_fluxo']) ? (string) $situacaoInscricao['status_fluxo'] : 'nao_inscrito';
?>

<?php $stepAtual = 1; include BASE_PATH . '/resources/views/partials/dc-stepper.php'; ?>

<div class="dc-checkout-body dc-container">

  <?php if ($success !== ''): ?>
    <div class="dc-callout dc-callout-success"><i class="ti ti-circle-check"></i><span><?php echo Helpers::e($success); ?></span></div>
  <?php endif; ?>
  <?php if (!empty($errors)): ?>
    <div class="dc-callout dc-callout-danger"><i class="ti ti-alert-triangle"></i><span><?php foreach ($errors as $err): ?><?php echo Helpers::e((string) $err); ?><br><?php endforeach; ?></span></div>
  <?php endif; ?>

  <?php if ($statusFluxo === 'matriculado'): ?>
    <div class="dc-section-card">
      <div class="dc-section-card-title">Você já está matriculado neste curso.</div>
      <p class="dc-text-sm dc-text-muted" style="margin-bottom:12px;">Seu acesso já foi liberado. Acompanhe o conteúdo na área do aluno.</p>
      <a href="/minha-pagina" class="dc-btn dc-btn-primary dc-btn-block">Acessar curso</a>
    </div>
  <?php elseif ($statusFluxo === 'pendente_pagamento'): ?>
    <div class="dc-section-card">
      <div class="dc-section-card-title">Você possui uma inscrição pendente.</div>
      <p class="dc-text-sm dc-text-muted" style="margin-bottom:12px;">Continue o pagamento para concluir sua matrícula.</p>
      <a href="<?php echo Helpers::e(!empty($situacaoInscricao['checkout_url']) ? $situacaoInscricao['checkout_url'] : '/checkout/resumo?pedido_id=' . (int) ($situacaoInscricao['pedido_id'] ?? 0)); ?>" class="dc-btn dc-btn-primary dc-btn-block">Continuar pagamento</a>
    </div>
  <?php elseif (in_array($statusFluxo, array('cancelado', 'expirado', 'falhou', 'reprovado'), true)): ?>
    <div class="dc-callout dc-callout-info"><i class="ti ti-info-circle"></i><span>O pedido anterior não está ativo e não bloqueia uma nova inscrição.</span></div>
  <?php endif; ?>

  <?php if (!$loggedIn): ?>
    <div class="dc-section-card">
      <div class="dc-section-card-title">Entre para continuar</div>
      <p class="dc-text-sm dc-text-muted" style="margin-bottom:12px;">Você pode revisar o curso, mas precisa entrar na conta para iniciar a compra.</p>
      <div style="display:flex;gap:8px;">
        <a href="/login" class="dc-btn dc-btn-primary">Entrar</a>
        <a href="/cadastro" class="dc-btn dc-btn-ghost">Criar conta</a>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($loggedIn && !in_array($statusFluxo, array('matriculado', 'pendente_pagamento'), true)): ?>

    <!-- Resumo da inscrição -->
    <div class="dc-checkout-curso">
      <div class="dc-thumb" style="background:#fff4ec;width:52px;height:52px;flex-shrink:0;">
        <?php if (!empty($curso['thumbnail'])): ?>
          <img src="<?php echo Helpers::e((string) $curso['thumbnail']); ?>" alt="" class="dc-thumb-img">
        <?php else: ?>
          <i class="ti ti-certificate" style="color:#FF6A00;"></i>
        <?php endif; ?>
      </div>
      <div>
        <?php if (!empty($curso['categoria_nome'])): ?>
          <div class="dc-card-cat"><?php echo Helpers::e((string) $curso['categoria_nome']); ?></div>
        <?php endif; ?>
        <div class="dc-card-title"><?php echo Helpers::e((string) ($curso['nome'] ?? '')); ?></div>
        <?php if (!empty($curso['turma_selecionada']['nome'])): ?>
          <div class="dc-card-meta" style="margin-top:4px;"><span><i class="ti ti-users"></i><?php echo Helpers::e((string) $curso['turma_selecionada']['nome']); ?></span></div>
        <?php endif; ?>
        <div class="dc-turma-preco" style="margin-top:6px;margin-bottom:0;">
          <?php if (!empty($curso['desconto_promocional'])): ?>
            <span class="dc-text-muted dc-text-sm" style="text-decoration:line-through;margin-right:6px;">R$ <?php echo number_format((float) $curso['valor'], 2, ',', '.'); ?></span>
            R$ <?php echo number_format((float) $curso['valor_efetivo'], 2, ',', '.'); ?>
          <?php else: ?>
            R$ <?php echo number_format((float) ($curso['valor_efetivo'] ?? ($curso['valor'] ?? 0)), 2, ',', '.'); ?>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Formulário de dados do pagamento (campos/IDs preservados do fluxo original) -->
    <form class="admin-form checkout-form" method="post" action="/inscricao">
      <input type="hidden" name="curso_evento_id" value="<?php echo (int) ($curso['id'] ?? 0); ?>">
      <input type="hidden" name="turma_id" value="<?php echo !empty($curso['turma_selecionada']['id']) ? (int) $curso['turma_selecionada']['id'] : ''; ?>">

      <div class="dc-section-card">
        <div class="dc-section-card-title">Dados do pagamento</div>

        <div class="dc-field">
          <label>Nome do pagador</label>
          <input class="dc-input" type="text" name="pagador_nome" value="<?php echo Helpers::e(isset($pagadorPrefill['nome']) && $pagadorPrefill['nome'] !== '' ? $pagadorPrefill['nome'] : $usuarioNome); ?>">
        </div>
        <div class="dc-field">
          <label>CPF do pagador</label>
          <input class="dc-input" type="text" name="pagador_cpf" value="<?php echo Helpers::e(isset($pagadorPrefill['cpf']) ? $pagadorPrefill['cpf'] : ''); ?>" placeholder="000.000.000-00" data-skip-old-input="1">
        </div>
        <div class="dc-field">
          <label>E-mail do pagador</label>
          <input class="dc-input" type="email" name="pagador_email" value="<?php echo Helpers::e(isset($pagadorPrefill['email']) && $pagadorPrefill['email'] !== '' ? $pagadorPrefill['email'] : $usuarioEmail); ?>">
        </div>
        <div class="dc-field">
          <label>Telefone</label>
          <input class="dc-input" type="text" name="pagador_telefone" value="<?php echo Helpers::e(isset($pagadorPrefill['telefone']) ? $pagadorPrefill['telefone'] : ''); ?>" data-skip-old-input="1">
        </div>
        <div class="dc-field">
          <label>Estado</label>
          <?php $estadoAtual = strtoupper((string) (isset($pagadorPrefill['estado']) ? $pagadorPrefill['estado'] : '')); ?>
          <select class="dc-select" name="pagador_estado" id="inscricao-estado">
            <option value="">Selecione o estado</option>
            <?php foreach (array('AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO') as $uf): ?>
              <option value="<?php echo Helpers::e($uf); ?>" <?php echo $estadoAtual === $uf ? 'selected' : ''; ?>><?php echo Helpers::e($uf); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="dc-field">
          <label>Cidade</label>
          <select class="dc-select" name="pagador_cidade" id="inscricao-cidade">
            <option value="">Selecione o estado primeiro</option>
          </select>
        </div>
        <div class="dc-field">
          <label>Tipo do pedido</label>
          <select class="dc-select" name="tipo_pedido" id="tipo-pedido">
            <option value="propria">Compra própria</option>
            <option value="terceiros">Compra para terceiros</option>
            <option value="lote">Compra em lote</option>
          </select>
        </div>
        <div class="dc-field" id="quantidade-vagas-wrap">
          <label>Quantidade de vagas</label>
          <input class="dc-input" type="number" id="quantidade-vagas" name="quantidade" min="1" value="1">
        </div>
        <div class="dc-field">
          <label>Comentários sobre a inscrição</label>
          <textarea class="dc-input" name="observacoes_publicas" rows="4"></textarea>
        </div>
      </div>

      <div class="dc-checkout-footer">
        <button type="submit" id="checkout-avancar-btn" class="dc-btn dc-btn-primary dc-btn-block">Avançar para pagamento <i class="ti ti-arrow-right"></i></button>
      </div>
    </form>
  <?php endif; ?>
</div>

<?php if ($loggedIn && !in_array($statusFluxo, array('matriculado', 'pendente_pagamento'), true)): ?>
<script>
(function () {
    var estadoSelect = document.getElementById('inscricao-estado');
    var cidadeSelect = document.getElementById('inscricao-cidade');
    if (!estadoSelect || !cidadeSelect) {
        return;
    }
    var tipoPedidoSelect = document.getElementById('tipo-pedido');
    var quantidadeWrap = document.getElementById('quantidade-vagas-wrap');
    var quantidadeInput = document.getElementById('quantidade-vagas');
    var avancarButton = document.getElementById('checkout-avancar-btn');

    var estadoInicial = <?php echo json_encode(strtoupper((string) (isset($pagadorPrefill['estado']) ? $pagadorPrefill['estado'] : '')), JSON_UNESCAPED_UNICODE); ?>;
    var cidadeInicial = <?php echo json_encode((string) (isset($pagadorPrefill['cidade']) ? $pagadorPrefill['cidade'] : ''), JSON_UNESCAPED_UNICODE); ?>;

    var cacheCidades = {};
    var ibgeUfs = {
        'AC': 12, 'AL': 27, 'AP': 16, 'AM': 13, 'BA': 29, 'CE': 23, 'DF': 53, 'ES': 32, 'GO': 52,
        'MA': 21, 'MT': 51, 'MS': 50, 'MG': 31, 'PA': 15, 'PB': 25, 'PR': 41, 'PE': 26, 'PI': 22,
        'RJ': 33, 'RN': 24, 'RS': 43, 'RO': 11, 'RR': 14, 'SC': 42, 'SP': 35, 'SE': 28, 'TO': 17
    };

    function popular(cidades, cidadeSelecionada) {
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
            if (cidadeSelecionada && cidade === cidadeSelecionada) {
                option.selected = true;
            }
            cidadeSelect.appendChild(option);
        }
    }

    function carregar(uf, cidadeSelecionada) {
        if (!uf || !ibgeUfs[uf]) {
            popular([], '');
            return;
        }
        if (cacheCidades[uf]) {
            popular(cacheCidades[uf], cidadeSelecionada);
            return;
        }

        cidadeSelect.disabled = true;
        cidadeSelect.innerHTML = '';
        cidadeSelect.appendChild(new Option('Carregando cidades...', ''));

        fetch('https://servicodados.ibge.gov.br/api/v1/localidades/estados/' + ibgeUfs[uf] + '/municipios?orderBy=nome')
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Falha ao carregar cidades');
                }
                return response.json();
            })
            .then(function (data) {
                var cidades = [];
                for (var i = 0; i < data.length; i++) {
                    if (data[i] && data[i].nome) {
                        cidades.push(data[i].nome);
                    }
                }
                cacheCidades[uf] = cidades;
                popular(cidades, cidadeSelecionada);
            })
            .catch(function () {
                cidadeSelect.disabled = true;
                cidadeSelect.innerHTML = '';
                cidadeSelect.appendChild(new Option('Não foi possível carregar as cidades', ''));
            });
    }

    estadoSelect.addEventListener('change', function () {
        carregar(estadoSelect.value, '');
    });

    if (estadoInicial) {
        estadoSelect.value = estadoInicial;
        carregar(estadoInicial, cidadeInicial);
    } else {
        popular([], '');
    }

    function syncQuantidadePorTipoPedido() {
        if (!tipoPedidoSelect || !quantidadeWrap || !quantidadeInput) {
            return;
        }

        if (tipoPedidoSelect.value === 'propria') {
            quantidadeWrap.style.display = 'none';
            quantidadeInput.value = '1';
        } else {
            quantidadeWrap.style.display = '';
            if (!quantidadeInput.value || parseInt(quantidadeInput.value, 10) < 1) {
                quantidadeInput.value = '1';
            }
        }
    }

    function syncTextoBotao() {
        if (!tipoPedidoSelect || !avancarButton) {
            return;
        }

        if (tipoPedidoSelect.value === 'propria') {
            avancarButton.textContent = 'Avançar para pagamento';
            return;
        }

        avancarButton.textContent = 'Avançar para participantes';
    }

    if (tipoPedidoSelect) {
        tipoPedidoSelect.addEventListener('change', syncQuantidadePorTipoPedido);
        tipoPedidoSelect.addEventListener('change', syncTextoBotao);
        syncQuantidadePorTipoPedido();
        syncTextoBotao();
    }

    var form = document.querySelector('form.checkout-form');
    if (form) {
        form.addEventListener('submit', function () {
            try {
                var campoCpf = form.querySelector('input[name="pagador_cpf"]');
                var campoTelefone = form.querySelector('input[name="pagador_telefone"]');
                if (campoCpf) {
                    sessionStorage.setItem('checkout_pagador_cpf', campoCpf.value || '');
                }
                if (campoTelefone) {
                    sessionStorage.setItem('checkout_pagador_telefone', campoTelefone.value || '');
                }
            } catch (e) {}
        });
    }
})();
</script>
<?php endif; ?>
