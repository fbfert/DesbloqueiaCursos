<?php
use App\Core\Helpers;
$usuario = $form_data['usuario'] ?? null;
$perfilIds = $form_data['perfil_ids'] ?? array();
$perfis = $form_data['perfis'] ?? array();
$oldData = isset($old) && is_array($old) ? $old : array();
$perfilIdsOld = isset($oldData['perfil_ids']) ? array_map('intval', (array) $oldData['perfil_ids']) : $perfilIds;

$value = function ($key, $default = '') use ($oldData, $usuario) {
    if (array_key_exists($key, $oldData)) return $oldData[$key];
    if (is_array($usuario) && array_key_exists($key, $usuario)) return $usuario[$key];
    return $default;
};
$estadoAtual = strtoupper((string) $value('estado'));
$cidadeAtual = (string) $value('cidade');
?>

<section class="admin-page">
    <header class="admin-page__header">
        <div>
            <h1 class="admin-page__title"><?php echo Helpers::e($title); ?></h1>
            <p class="admin-page__subtitle">Dados cadastrais, status e papéis de acesso.</p>
        </div>
    </header>

    <?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>

    <section class="status-card">
        <form method="post" action="<?php echo Helpers::e($action_url); ?>" class="admin-form">
            <input type="hidden" name="id" value="<?php echo (int) $value('id', 0); ?>">

            <label>Nome<input type="text" name="nome" value="<?php echo Helpers::e($value('nome')); ?>" required></label>
            <label>E-mail<input type="email" name="email" value="<?php echo Helpers::e($value('email')); ?>" required></label>
            <label>CPF<input type="text" name="cpf" value="<?php echo Helpers::e($value('cpf')); ?>" required></label>
            <label>Telefone<input type="text" name="telefone" value="<?php echo Helpers::e($value('telefone')); ?>"></label>
            <label>Estado (UF)
                <select name="estado" id="usuario-estado">
                    <option value="">Selecione o estado</option>
                    <?php foreach (array('AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO') as $uf): ?>
                        <option value="<?php echo Helpers::e($uf); ?>" <?php echo $estadoAtual === $uf ? 'selected' : ''; ?>><?php echo Helpers::e($uf); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Cidade
                <select name="cidade" id="usuario-cidade">
                    <option value="">Selecione o estado primeiro</option>
                </select>
            </label>
            <label>Status
                <select name="status">
                    <?php $statusAtual = (string) $value('status', 'ativo'); ?>
                    <option value="ativo" <?php echo $statusAtual === 'ativo' ? 'selected' : ''; ?>>Ativo</option>
                    <option value="inativo" <?php echo $statusAtual === 'inativo' ? 'selected' : ''; ?>>Inativo</option>
                    <option value="bloqueado" <?php echo $statusAtual === 'bloqueado' ? 'selected' : ''; ?>>Bloqueado</option>
                </select>
            </label>
            <label>Senha <?php echo !empty($usuario) ? '(deixe em branco para manter)' : ''; ?>
                <input type="password" name="senha" <?php echo empty($usuario) ? 'required' : ''; ?>>
            </label>

            <div class="permission-grid">
                <?php foreach ($perfis as $perfil): ?>
                    <label class="auth-check">
                        <input type="checkbox" name="perfil_ids[]" value="<?php echo (int) $perfil['id']; ?>" <?php echo in_array((int) $perfil['id'], $perfilIdsOld, true) ? 'checked' : ''; ?>>
                        <?php echo Helpers::e($perfil['nome']); ?> (<?php echo Helpers::e($perfil['slug']); ?>)
                    </label>
                <?php endforeach; ?>
            </div>

            <div class="cta-group">
                <button type="submit"><?php echo Helpers::e($submit_label); ?></button>
                <a class="button-link button-link--ghost" href="/admin/usuarios">Voltar</a>
            </div>
        </form>
    </section>
</section>

<script>
(function () {
    var estadoSelect = document.getElementById('usuario-estado');
    var cidadeSelect = document.getElementById('usuario-cidade');
    if (!estadoSelect || !cidadeSelect) return;

    var estadoInicial = <?php echo json_encode($estadoAtual, JSON_UNESCAPED_UNICODE); ?>;
    var cidadeInicial = <?php echo json_encode($cidadeAtual, JSON_UNESCAPED_UNICODE); ?>;
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
            if (cidadeSelecionada && cidade === cidadeSelecionada) option.selected = true;
            cidadeSelect.appendChild(option);
        }
    }

    function carregar(uf, cidadeSelecionada) {
        if (!uf || !ibgeUfs[uf]) return popular([], '');
        if (cacheCidades[uf]) return popular(cacheCidades[uf], cidadeSelecionada);

        cidadeSelect.disabled = true;
        cidadeSelect.innerHTML = '';
        cidadeSelect.appendChild(new Option('Carregando cidades...', ''));

        fetch('https://servicodados.ibge.gov.br/api/v1/localidades/estados/' + ibgeUfs[uf] + '/municipios?orderBy=nome')
            .then(function (response) {
                if (!response.ok) throw new Error('Falha ao carregar cidades');
                return response.json();
            })
            .then(function (data) {
                var cidades = [];
                for (var i = 0; i < data.length; i++) {
                    if (data[i] && data[i].nome) cidades.push(data[i].nome);
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
})();
</script>
