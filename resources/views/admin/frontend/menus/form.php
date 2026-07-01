<?php
use App\Core\Helpers;
$menu = isset($form_data['menu']) ? $form_data['menu'] : null;
$old = isset($old) && is_array($old) ? $old : array();
$value = function ($field, $default = '') use ($old, $menu) {
    if (array_key_exists($field, $old)) { return $old[$field]; }
    if ($menu && array_key_exists($field, $menu)) { return $menu[$field]; }
    return $default;
};
$regrasFonte = array();
if (array_key_exists('regras_exibicao', $old) && is_array($old['regras_exibicao'])) {
    $regrasFonte = $old['regras_exibicao'];
} elseif (isset($form_data['regras_exibicao']) && is_array($form_data['regras_exibicao'])) {
    $regrasFonte = $form_data['regras_exibicao'];
}

$regrasExibicao = array();
if (!empty($regrasFonte)) {
    if (isset($regrasFonte[0]) && is_array($regrasFonte[0])) {
        $regrasExibicao = $regrasFonte;
    } else {
        $quantidade = max(
            isset($regrasFonte['tipo_regra']) && is_array($regrasFonte['tipo_regra']) ? count($regrasFonte['tipo_regra']) : 0,
            isset($regrasFonte['alvo_tipo']) && is_array($regrasFonte['alvo_tipo']) ? count($regrasFonte['alvo_tipo']) : 0,
            isset($regrasFonte['alvo_valor']) && is_array($regrasFonte['alvo_valor']) ? count($regrasFonte['alvo_valor']) : 0,
            isset($regrasFonte['ativo']) && is_array($regrasFonte['ativo']) ? count($regrasFonte['ativo']) : 0,
            isset($regrasFonte['ordem']) && is_array($regrasFonte['ordem']) ? count($regrasFonte['ordem']) : 0
        );

        for ($i = 0; $i < $quantidade; $i++) {
            $regrasExibicao[] = array(
                'tipo_regra' => isset($regrasFonte['tipo_regra'][$i]) ? $regrasFonte['tipo_regra'][$i] : 'include',
                'alvo_tipo' => isset($regrasFonte['alvo_tipo'][$i]) ? $regrasFonte['alvo_tipo'][$i] : 'route',
                'alvo_valor' => isset($regrasFonte['alvo_valor'][$i]) ? $regrasFonte['alvo_valor'][$i] : '',
                'ativo' => isset($regrasFonte['ativo'][$i]) ? (int) $regrasFonte['ativo'][$i] : 1,
                'ordem' => isset($regrasFonte['ordem'][$i]) ? (int) $regrasFonte['ordem'][$i] : 0,
            );
        }
    }
}

if (empty($regrasExibicao)) {
    $regrasExibicao[] = array(
        'tipo_regra' => 'include',
        'alvo_tipo' => 'route',
        'alvo_valor' => '',
        'ativo' => 1,
        'ordem' => 0,
    );
}
?>
<section class="admin-page">
    <header class="admin-page__header">
        <div><h1 class="admin-page__title"><?php echo Helpers::e($title); ?></h1></div>
        <div class="admin-page__actions"><a class="button-link button-link--ghost" href="/admin/frontend/menus">Voltar</a></div>
    </header>
    <?php if (!empty($errors)): ?><section class="auth-message auth-message-error"><?php foreach ($errors as $error): ?><p><?php echo Helpers::e($error); ?></p><?php endforeach; ?></section><?php endif; ?>
    <section class="status-card">
        <form method="post" action="<?php echo Helpers::e($action_url); ?>" class="admin-form">
            <input type="hidden" name="id" value="<?php echo (int) $value('id', 0); ?>">
            <label>Nome administrativo<input type="text" name="nome_admin" value="<?php echo Helpers::e($value('nome_admin')); ?>" required></label>
            <label>Código<input type="text" name="codigo" value="<?php echo Helpers::e($value('codigo')); ?>" required></label>
            <label>Posição<input type="text" name="posicao" value="<?php echo Helpers::e($value('posicao')); ?>" required></label>
            <label>Ordem<input type="number" name="ordem" value="<?php echo Helpers::e((string) $value('ordem', 0)); ?>"></label>
            <label>Observações administrativas<textarea name="observacoes_admin" rows="4"><?php echo Helpers::e($value('observacoes_admin')); ?></textarea></label>
            <label><input type="checkbox" name="ativo" value="1" <?php echo (int) $value('ativo', 1) === 1 ? 'checked' : ''; ?>> Ativo</label>

            <section class="status-card" style="margin-top:16px;">
                <h2 style="margin-top:0;">Exibição</h2>
                <p class="muted">Cadastre regras para incluir ou excluir o menu por rota, page_key, área ou estado de autenticação.</p>
                <p class="muted">Exemplos: <code>/cursos/*</code>, <code>home</code>, <code>publica</code>, <code>guest</code>.</p>
                <div id="frontend-menu-regras-lista">
                    <?php foreach ($regrasExibicao as $regra): ?>
                        <div class="status-card" style="margin:12px 0;padding:12px;" data-regra-card="1">
                            <div class="admin-form-grid">
                                <label>
                                    Tipo
                                    <select name="regras_exibicao[tipo_regra][]">
                                        <option value="include" <?php echo (string) ($regra['tipo_regra'] ?? 'include') === 'include' ? 'selected' : ''; ?>>Incluir</option>
                                        <option value="exclude" <?php echo (string) ($regra['tipo_regra'] ?? '') === 'exclude' ? 'selected' : ''; ?>>Excluir</option>
                                    </select>
                                </label>
                                <label>
                                    Alvo
                                    <select name="regras_exibicao[alvo_tipo][]">
                                        <option value="route" <?php echo (string) ($regra['alvo_tipo'] ?? 'route') === 'route' ? 'selected' : ''; ?>>Rota</option>
                                        <option value="page_key" <?php echo (string) ($regra['alvo_tipo'] ?? '') === 'page_key' ? 'selected' : ''; ?>>page_key</option>
                                        <option value="area" <?php echo (string) ($regra['alvo_tipo'] ?? '') === 'area' ? 'selected' : ''; ?>>Área</option>
                                        <option value="auth_state" <?php echo (string) ($regra['alvo_tipo'] ?? '') === 'auth_state' ? 'selected' : ''; ?>>Estado do usuário</option>
                                    </select>
                                </label>
                                <label>
                                    Valor
                                    <input type="text" name="regras_exibicao[alvo_valor][]" value="<?php echo Helpers::e((string) ($regra['alvo_valor'] ?? '')); ?>" placeholder="/cursos/*">
                                    <small class="muted">Use rota completa, page_key, área ou guest/logged.</small>
                                </label>
                                <label>
                                    Status
                                    <select name="regras_exibicao[ativo][]">
                                        <option value="1" <?php echo (int) ($regra['ativo'] ?? 1) === 1 ? 'selected' : ''; ?>>Ativa</option>
                                        <option value="0" <?php echo (int) ($regra['ativo'] ?? 1) === 0 ? 'selected' : ''; ?>>Inativa</option>
                                    </select>
                                </label>
                                <label>
                                    Ordem
                                    <input type="number" name="regras_exibicao[ordem][]" value="<?php echo Helpers::e((string) ($regra['ordem'] ?? 0)); ?>">
                                </label>
                            </div>
                            <button type="button" class="button-link button-link--ghost" data-remover-regra="1">Remover regra</button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="button-link" id="frontend-menu-adicionar-regra">Adicionar regra</button>
            </section>
            <?php $cancel_url = '/admin/frontend/menus'; ?>
            <?php $showSaveAsCopy = !empty($menu['id']); ?>
            <?php require BASE_PATH . '/resources/views/admin/partials/form-actions.php'; ?>
        </form>
    </section>
</section>

<template id="frontend-menu-regra-template">
    <div class="status-card" style="margin:12px 0;padding:12px;" data-regra-card="1">
        <div class="admin-form-grid">
            <label>
                Tipo
                <select name="regras_exibicao[tipo_regra][]">
                    <option value="include">Incluir</option>
                    <option value="exclude">Excluir</option>
                </select>
            </label>
            <label>
                Alvo
                <select name="regras_exibicao[alvo_tipo][]">
                    <option value="route">Rota</option>
                    <option value="page_key">page_key</option>
                    <option value="area">Área</option>
                    <option value="auth_state">Estado do usuário</option>
                </select>
            </label>
            <label>
                Valor
                <input type="text" name="regras_exibicao[alvo_valor][]" value="" placeholder="/cursos/*">
                <small class="muted">Use rota completa, page_key, área ou guest/logged.</small>
            </label>
            <label>
                Status
                <select name="regras_exibicao[ativo][]">
                    <option value="1" selected>Ativa</option>
                    <option value="0">Inativa</option>
                </select>
            </label>
            <label>
                Ordem
                <input type="number" name="regras_exibicao[ordem][]" value="0">
            </label>
        </div>
        <button type="button" class="button-link button-link--ghost" data-remover-regra="1">Remover regra</button>
    </div>
</template>

<script>
(function () {
    var lista = document.getElementById('frontend-menu-regras-lista');
    var addBtn = document.getElementById('frontend-menu-adicionar-regra');
    var template = document.getElementById('frontend-menu-regra-template');

    function bindRemover(botao) {
        botao.addEventListener('click', function () {
            var card = botao.closest ? botao.closest('[data-regra-card=\"1\"]') : null;
            if (!card || !lista) return;
            if (lista.querySelectorAll('[data-regra-card=\"1\"]').length <= 1) {
                var selects = card.querySelectorAll('select');
                var inputs = card.querySelectorAll('input');
                for (var i = 0; i < selects.length; i++) selects[i].selectedIndex = 0;
                for (var j = 0; j < inputs.length; j++) {
                    if (inputs[j].type === 'number') {
                        inputs[j].value = '0';
                    } else {
                        inputs[j].value = '';
                    }
                }
                return;
            }
            card.parentNode.removeChild(card);
        });
    }

    if (lista) {
        var botoes = lista.querySelectorAll('[data-remover-regra=\"1\"]');
        for (var i = 0; i < botoes.length; i++) {
            bindRemover(botoes[i]);
        }
    }

    if (addBtn && lista && template) {
        addBtn.addEventListener('click', function () {
            var clone = template.content ? template.content.cloneNode(true) : null;
            if (!clone) {
                return;
            }

            lista.appendChild(clone);
            var cards = lista.querySelectorAll('[data-regra-card=\"1\"]');
            var card = cards[cards.length - 1];
            if (!card) return;
            var botao = card.querySelector('[data-remover-regra=\"1\"]');
            if (botao) bindRemover(botao);
        });
    }
})();
</script>
