<?php use App\Core\Helpers; ?>
<?php
if (!isset($frontend_template)) { try { $frontend_template = (new \App\Services\ConfiguracaoGlobalService())->templateVisualPortal(); } catch (\Throwable $e) { $frontend_template = 'v1'; } }
if ((string) $frontend_template === 'v4-claude') { require BASE_PATH . '/resources/views/v4-claude/checkout/inscricao.php'; return; }
?>

<div class="front-section-stack">
    <section class="page-header front-section">
        <h1>Inscrição</h1>
        <p><?php echo Helpers::e($curso['nome']); ?></p>
    </section>

    <?php if (!empty($success)): ?>
        <section class="auth-message auth-message-success front-section">
            <p><?php echo Helpers::e($success); ?></p>
        </section>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <section class="auth-message auth-message-error front-section">
            <?php foreach ($errors as $error): ?>
                <p><?php echo Helpers::e($error); ?></p>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <div class="front-card-section front-section">
    <?php $statusFluxo = isset($situacaoInscricao['status_fluxo']) ? (string) $situacaoInscricao['status_fluxo'] : 'nao_inscrito'; ?>
    <?php if ($statusFluxo === 'matriculado'): ?>
        <section class="checkout-panel checkout-status-alert checkout-status-alert--success front-card">
            <strong class="checkout-status-alert__title">Você já está matriculado neste curso.</strong>
            <p class="checkout-status-alert__text">Seu acesso já foi liberado. Acompanhe o conteúdo na área do aluno.</p>
            <div class="cta-group">
                <a class="button-link" href="/minha-pagina">Acessar curso</a>
            </div>
        </section>
    <?php elseif ($statusFluxo === 'pendente_pagamento'): ?>
        <section class="checkout-panel checkout-status-alert checkout-status-alert--warning front-card">
            <strong class="checkout-status-alert__title">Você possui uma inscrição pendente para este curso.</strong>
            <p class="checkout-status-alert__text">Continue o pagamento para concluir sua matrícula.</p>
            <div class="cta-group">
                <a class="button-link" href="<?php echo Helpers::e(!empty($situacaoInscricao['checkout_url']) ? $situacaoInscricao['checkout_url'] : '/checkout/resumo?pedido_id=' . (int) ($situacaoInscricao['pedido_id'] ?? 0)); ?>">Continuar pagamento</a>
            </div>
        </section>
    <?php elseif (in_array($statusFluxo, array('cancelado', 'expirado', 'falhou', 'reprovado'), true)): ?>
        <section class="checkout-panel checkout-status-alert checkout-status-alert--info front-card">
            <strong class="checkout-status-alert__title">Você pode se inscrever novamente neste curso.</strong>
            <p class="checkout-status-alert__text">O pedido anterior não está ativo e não bloqueia uma nova inscrição.</p>
        </section>
    <?php endif; ?>

    <?php if (empty($loggedIn)): ?>
        <section class="notice front-card">
            <strong>Entre para continuar</strong>
            <p>Você pode revisar o curso, mas precisa entrar na conta para iniciar a compra.</p>
            <div class="cta-group">
                <a class="button-link" href="/login">Entrar</a>
                <a class="button-link button-link--ghost" href="/cadastro">Criar conta</a>
            </div>
        </section>
    <?php endif; ?>

    <?php if (!empty($loggedIn) && !in_array($statusFluxo, array('matriculado', 'pendente_pagamento'), true)): ?>
        <section class="checkout-panel front-card">
            <h2>Resumo da inscrição</h2>
            <dl class="summary-list">
                <dt>Curso</dt>
                <dd><?php echo Helpers::e($curso['nome']); ?></dd>
                <?php if (!empty($curso['turma_selecionada']['nome'])): ?>
                    <dt>Turma</dt>
                    <dd><?php echo Helpers::e($curso['turma_selecionada']['nome']); ?></dd>
                    <dt>Status</dt>
                    <dd><?php echo Helpers::e($curso['turma_selecionada']['status']); ?></dd>
                <?php endif; ?>
                <dt>Valor</dt>
                <dd>
                    <?php if (!empty($curso['desconto_promocional'])): ?>
                        <div class="muted" style="text-decoration:line-through;">R$ <?php echo number_format((float) $curso['valor'], 2, ',', '.'); ?></div>
                        <div><strong>R$ <?php echo number_format((float) $curso['valor_efetivo'], 2, ',', '.'); ?></strong></div>
                    <?php else: ?>
                        R$ <?php echo number_format((float) ($curso['valor_efetivo'] ?? $curso['valor']), 2, ',', '.'); ?>
                    <?php endif; ?>
                </dd>
            </dl>
        </section>

        <form class="admin-form checkout-form front-card-list" method="post" action="/inscricao">
            <input type="hidden" name="curso_evento_id" value="<?php echo (int) $curso['id']; ?>">
            <input type="hidden" name="turma_id" value="<?php echo !empty($curso['turma_selecionada']['id']) ? (int) $curso['turma_selecionada']['id'] : ''; ?>">

            <section class="checkout-panel front-card">
                <h2>Dados do pagamento</h2>
                <label>
                    Nome do pagador
                    <input type="text" name="pagador_nome" value="<?php echo Helpers::e(isset($pagadorPrefill['nome']) && $pagadorPrefill['nome'] !== '' ? $pagadorPrefill['nome'] : $usuarioNome); ?>">
                </label>
                <label>
                    CPF do pagador
                    <input type="text" name="pagador_cpf" value="<?php echo Helpers::e(isset($pagadorPrefill['cpf']) ? $pagadorPrefill['cpf'] : ''); ?>" placeholder="000.000.000-00" data-skip-old-input="1">
                </label>
                <label>
                    E-mail do pagador
                    <input type="email" name="pagador_email" value="<?php echo Helpers::e(isset($pagadorPrefill['email']) && $pagadorPrefill['email'] !== '' ? $pagadorPrefill['email'] : (isset($usuarioEmail) ? $usuarioEmail : '')); ?>">
                </label>
                <label>
                    Telefone
                    <input type="text" name="pagador_telefone" value="<?php echo Helpers::e(isset($pagadorPrefill['telefone']) ? $pagadorPrefill['telefone'] : ''); ?>" data-skip-old-input="1">
                </label>
                <label>
                    Estado
                    <?php $estadoAtual = strtoupper((string) (isset($pagadorPrefill['estado']) ? $pagadorPrefill['estado'] : '')); ?>
                    <select name="pagador_estado" id="inscricao-estado">
                        <option value="">Selecione o estado</option>
                        <?php foreach (array('AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO') as $uf): ?>
                            <option value="<?php echo Helpers::e($uf); ?>" <?php echo $estadoAtual === $uf ? 'selected' : ''; ?>><?php echo Helpers::e($uf); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    Cidade
                    <select name="pagador_cidade" id="inscricao-cidade">
                        <option value="">Selecione o estado primeiro</option>
                    </select>
                </label>
                <label>
                    Tipo do pedido
                    <select name="tipo_pedido" id="tipo-pedido">
                        <option value="propria">Compra própria</option>
                        <option value="terceiros">Compra para terceiros</option>
                        <option value="lote">Compra em lote</option>
                    </select>
                </label>
                <label id="quantidade-vagas-wrap">
                    Quantidade de vagas
                    <input type="number" id="quantidade-vagas" name="quantidade" min="1" value="1">
                </label>
                <label>
                    Comentários sobre a inscrição
                    <textarea name="observacoes_publicas" rows="4"></textarea>
                </label>
            </section>

            <button type="submit" id="checkout-avancar-btn">Avançar para pagamento</button>
        </form>
    <?php endif; ?>
</div>
</div>

<?php if (!empty($loggedIn) && !in_array($statusFluxo, array('matriculado', 'pendente_pagamento'), true)): ?>
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
                var campoCpf = form.querySelector('input[name=\"pagador_cpf\"]');
                var campoTelefone = form.querySelector('input[name=\"pagador_telefone\"]');
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

