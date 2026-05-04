<section class="auth-shell">
    <h1>Minha conta</h1>
    <?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
    <?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

    <form method="post" action="/minha-conta" class="auth-form">
        <label>
            Nome
            <input type="text" name="nome" value="<?php echo htmlspecialchars(isset($old['nome']) ? $old['nome'] : (isset($conta['nome']) ? $conta['nome'] : ''), ENT_QUOTES, 'UTF-8'); ?>" required>
        </label>

        <label>
            E-mail
            <input type="email" name="email" value="<?php echo htmlspecialchars(isset($old['email']) ? $old['email'] : (isset($conta['email']) ? $conta['email'] : ''), ENT_QUOTES, 'UTF-8'); ?>" required>
        </label>

        <label>
            CPF
            <input type="text" name="cpf" value="<?php echo htmlspecialchars(isset($old['cpf']) ? $old['cpf'] : (isset($conta['cpf']) ? $conta['cpf'] : ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="000.000.000-00" maxlength="14" inputmode="numeric" pattern="^\d{3}\.\d{3}\.\d{3}-\d{2}$|^\d{11}$" required>
        </label>

        <label>
            Telefone
            <input type="text" name="telefone" value="<?php echo htmlspecialchars(isset($old['telefone']) ? $old['telefone'] : (isset($conta['telefone']) ? $conta['telefone'] : ''), ENT_QUOTES, 'UTF-8'); ?>">
        </label>

        <label>
            Estado
            <select name="estado" id="conta-estado">
                <option value="">Selecione o estado</option>
                <?php
                $estadoAtual = strtoupper((string) (isset($old['estado']) ? $old['estado'] : (isset($conta['estado']) ? $conta['estado'] : '')));
                $ufs = array('AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO');
                foreach ($ufs as $uf):
                ?>
                    <option value="<?php echo htmlspecialchars($uf, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $estadoAtual === $uf ? 'selected' : ''; ?>><?php echo htmlspecialchars($uf, ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            Cidade
            <select name="cidade" id="conta-cidade">
                <option value="">Selecione o estado primeiro</option>
            </select>
        </label>

        <label>
            Nova senha
            <input type="password" name="nova_senha" minlength="8" data-skip-old-input="1" autocomplete="new-password">
        </label>

        <label>
            Confirmar nova senha
            <input type="password" name="nova_senha_confirmacao" minlength="8" data-skip-old-input="1" autocomplete="new-password">
        </label>

        <button type="submit">Salvar alterações</button>
    </form>
</section>

<script>
(function () {
    var estadoSelect = document.getElementById('conta-estado');
    var cidadeSelect = document.getElementById('conta-cidade');
    if (!estadoSelect || !cidadeSelect) {
        return;
    }

    var estadoInicial = <?php echo json_encode(strtoupper((string) (isset($old['estado']) ? $old['estado'] : (isset($conta['estado']) ? $conta['estado'] : ''))), JSON_UNESCAPED_UNICODE); ?>;
    var cidadeInicial = <?php echo json_encode((string) (isset($old['cidade']) ? $old['cidade'] : (isset($conta['cidade']) ? $conta['cidade'] : '')), JSON_UNESCAPED_UNICODE); ?>;

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
            if (cidadeSelecionada && cidade === cidadeSelecionada) {
                option.selected = true;
            }
            cidadeSelect.appendChild(option);
        }
    }

    function carregarCidades(uf, cidadeSelecionada) {
        if (!uf || !ibgeUfs[uf]) {
            popularCidadesComLista([], '');
            return;
        }

        if (cacheCidades[uf]) {
            popularCidadesComLista(cacheCidades[uf], cidadeSelecionada);
            return;
        }

        cidadeSelect.disabled = true;
        cidadeSelect.innerHTML = '';
        cidadeSelect.appendChild(new Option('Carregando cidades...', ''));

        var url = 'https://servicodados.ibge.gov.br/api/v1/localidades/estados/' + ibgeUfs[uf] + '/municipios?orderBy=nome';
        fetch(url)
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
                popularCidadesComLista(cidades, cidadeSelecionada);
            })
            .catch(function () {
                cidadeSelect.disabled = true;
                cidadeSelect.innerHTML = '';
                cidadeSelect.appendChild(new Option('Não foi possível carregar as cidades', ''));
            });
    }

    estadoSelect.addEventListener('change', function () {
        carregarCidades(estadoSelect.value, '');
    });

    if (estadoInicial) {
        estadoSelect.value = estadoInicial;
        carregarCidades(estadoInicial, cidadeInicial);
    } else {
        popularCidadesComLista([], '');
    }
})();
</script>
