<?php use App\Core\Helpers; ?>

<?php $curso = isset($form_data['curso']) ? $form_data['curso'] : null; ?>
<?php $categorias = isset($form_data['categorias']) ? $form_data['categorias'] : array(); ?>
<?php $professores = isset($form_data['professores']) ? $form_data['professores'] : array(); ?>
<?php $professoresResponsaveisIds = isset($form_data['professores_responsaveis_ids']) ? (array) $form_data['professores_responsaveis_ids'] : array(); ?>
<?php $professoresResponsaveisIdsSelecionados = array_map('intval', $professoresResponsaveisIds); ?>
<?php $thumbnailsDisponiveis = isset($form_data['thumbnails_disponiveis']) ? $form_data['thumbnails_disponiveis'] : array(); ?>
<?php $oldInput = isset($oldInput) && is_array($oldInput) ? $oldInput : array(); ?>
<?php
$inputValue = function ($key, $default = '') use ($oldInput, $curso) {
    if (array_key_exists($key, $oldInput)) {
        return $oldInput[$key];
    }

    return isset($curso[$key]) ? $curso[$key] : $default;
};
?>
<?php
$conteudoProgramaticoTipo = (string) ($curso['conteudo_programatico_tipo'] ?? 'texto');
if ($conteudoProgramaticoTipo === '') $conteudoProgramaticoTipo = 'texto';

$modulosExistentes = array();
$modulosJson = (string) ($curso['conteudo_programatico_modulos'] ?? '');
if (trim($modulosJson) !== '') {
    $decoded = json_decode($modulosJson, true);
    if (is_array($decoded)) {
        foreach ($decoded as $modulo) {
            if (!is_array($modulo)) continue;
            $titulo = trim((string) ($modulo['titulo'] ?? ''));
            $itens = isset($modulo['itens']) && is_array($modulo['itens']) ? $modulo['itens'] : array();
            $itensTxt = array();
            foreach ($itens as $item) {
                $item = trim((string) $item);
                if ($item === '') continue;
                $itensTxt[] = $item;
            }
            if ($titulo === '' && empty($itensTxt)) continue;
            $modulosExistentes[] = array(
                'titulo' => $titulo,
                'itens' => implode("\n", $itensTxt),
            );
        }
    }
}
if (empty($modulosExistentes)) {
    $modulosExistentes[] = array('titulo' => '', 'itens' => '');
}
?>

<div class="admin-page">
<section class="admin-page__header">
    <div>
        <h1 class="admin-page__title"><?php echo Helpers::e($title); ?></h1>
        <p class="admin-page__subtitle">Cadastro e manutenção de cursos e eventos.</p>
    </div>
</section>

<?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
<?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

<section class="status-card">
    <form method="post" action="<?php echo Helpers::e($action_url); ?>" class="admin-form" enctype="multipart/form-data">
        <input type="hidden" name="id" value="<?php echo !empty($curso['id']) ? (int) $curso['id'] : 0; ?>">
        <h2 style="margin:0 0 8px;">Identificação</h2>
        <label>Nome<input type="text" name="nome" value="<?php echo Helpers::e($curso['nome'] ?? ''); ?>" required></label>
        <label>Slug<input type="text" name="slug" value="<?php echo Helpers::e($curso['slug'] ?? ''); ?>"></label>
        <label>
            Categoria
            <select name="categoria_id">
                <option value="">Sem categoria</option>
                <?php foreach ($categorias as $categoria): ?>
                    <option value="<?php echo (int) $categoria['id']; ?>" <?php echo !empty($curso['categoria_id']) && (int) $curso['categoria_id'] === (int) $categoria['id'] ? 'selected' : ''; ?>>
                        <?php echo Helpers::e($categoria['nome']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Professores responsáveis
            <select name="professores_responsaveis_usuario_ids[]" multiple size="6">
                <?php foreach ($professores as $professor): ?>
                    <option value="<?php echo (int) $professor['id']; ?>" <?php echo in_array((int) $professor['id'], $professoresResponsaveisIdsSelecionados, true) ? 'selected' : ''; ?>>
                        <?php echo Helpers::e($professor['nome']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small class="muted" style="display:block;margin-top:6px;">Segure Ctrl ou Cmd para selecionar mais de um professor.</small>
        </label>
        <label>
            Tipo
            <select name="tipo">
                <option value="curso" <?php echo (($curso['tipo'] ?? '') === 'curso') ? 'selected' : ''; ?>>Curso</option>
                <option value="evento" <?php echo (($curso['tipo'] ?? '') === 'evento') ? 'selected' : ''; ?>>Evento</option>
            </select>
        </label>
        <label>
            Modalidade
            <select name="modalidade">
                <?php foreach (($form_data['modalidades'] ?? array()) as $modalidade): ?>
                    <option value="<?php echo Helpers::e($modalidade); ?>" <?php echo (($curso['modalidade'] ?? 'presencial') === $modalidade) ? 'selected' : ''; ?>>
                        <?php echo Helpers::e($modalidade); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <h2 class="full" style="margin:16px 0 8px;">Imagem</h2>
        <label>
            Thumbnail (URL ou caminho)
            <input type="text" name="thumbnail" value="<?php echo Helpers::e($curso['thumbnail'] ?? ''); ?>">
        </label>
        <label>
            Escolher thumbnail da pasta
            <select name="thumbnail_existente">
                <option value="">Manter ou usar o campo acima</option>
                <?php foreach ($thumbnailsDisponiveis as $thumbnailPath): ?>
                    <option value="<?php echo Helpers::e($thumbnailPath); ?>" <?php echo (($curso['thumbnail'] ?? '') === $thumbnailPath) ? 'selected' : ''; ?>>
                        <?php echo Helpers::e($thumbnailPath); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Enviar nova thumbnail
            <input type="file" name="thumbnail_upload" accept=".jpg,.jpeg,.png,.webp,.gif,image/jpeg,image/png,image/webp,image/gif">
        </label>

        <h2 class="full" style="margin:16px 0 8px;">Valores</h2>
        <label>
            Valor
            <input id="curso-valor" type="number" step="0.01" min="0" name="valor" value="<?php echo Helpers::e((string) $inputValue('valor', '0.00')); ?>">
        </label>
        <label>
            Valor promocional (opcional)
            <input id="curso-valor-promocional" type="number" step="0.01" min="0" name="valor_promocional" value="<?php echo Helpers::e((string) $inputValue('valor_promocional', '')); ?>">
            <small class="muted" id="curso-preview-desconto" style="display:block;margin-top:6px;"></small>
            <small class="muted" style="display:block;margin-top:6px;">Deixe em branco para manter o valor atual. Se informar um valor promocional válido, a promoção será aplicada automaticamente.</small>
        </label>
        <label>
            Carga horária
            <input type="number" name="carga_horaria" min="0" value="<?php echo Helpers::e((string) ($curso['carga_horaria'] ?? '')); ?>">
        </label>

        <h2 class="full" style="margin:16px 0 8px;">Descritivo do curso</h2>
        <label class="full">
            Descrição curta
            <textarea name="descricao_curta" rows="3"><?php echo Helpers::e($curso['descricao_curta'] ?? ''); ?></textarea>
        </label>
        <label class="full">
            Descritivo do curso
            <textarea name="descricao_completa" rows="6"><?php echo Helpers::e($curso['descricao_completa'] ?? ''); ?></textarea>
        </label>

        <h2 class="full" style="margin:16px 0 8px;">Objetivos</h2>
        <label class="full">
            Objetivo geral (opcional)
            <textarea name="objetivo_geral" rows="3"><?php echo Helpers::e($curso['objetivo_geral'] ?? ''); ?></textarea>
        </label>
        <label class="full">
            Objetivos específicos (opcional)
            <small class="muted" style="display:block;margin-bottom:6px;">Informe um objetivo por linha. Cada linha poderá virar um item na página pública.</small>
            <textarea name="objetivos_especificos" rows="4"><?php echo Helpers::e($curso['objetivos_especificos'] ?? ''); ?></textarea>
        </label>

        <h2 class="full" style="margin:16px 0 8px;">Público e pré-requisitos</h2>
        <label class="full">
            Público-alvo (opcional)
            <textarea name="publico_alvo" rows="3"><?php echo Helpers::e($curso['publico_alvo'] ?? ''); ?></textarea>
        </label>
        <label class="full">
            Pré-requisitos — texto base (opcional)
            <textarea name="pre_requisitos_texto" rows="3"><?php echo Helpers::e($curso['pre_requisitos_texto'] ?? ''); ?></textarea>
        </label>
        <label class="full">
            Pré-requisitos — itens (opcional)
            <small class="muted" style="display:block;margin-bottom:6px;">Informe um item por linha. Cada linha poderá virar um item na página pública.</small>
            <textarea name="pre_requisitos_itens" rows="4"><?php echo Helpers::e($curso['pre_requisitos_itens'] ?? ''); ?></textarea>
        </label>

        <h2 class="full" style="margin:16px 0 8px;">Ementa</h2>
        <label class="full">
            Ementa (opcional)
            <textarea name="ementa" rows="4"><?php echo Helpers::e($curso['ementa'] ?? ''); ?></textarea>
        </label>

        <h2 class="full" style="margin:16px 0 8px;">Conteúdo programático</h2>
        <label>
            Formato do conteúdo programático
            <select name="conteudo_programatico_tipo" id="conteudo-programatico-tipo">
                <option value="texto" <?php echo $conteudoProgramaticoTipo === 'texto' ? 'selected' : ''; ?>>Texto simples</option>
                <option value="html" <?php echo $conteudoProgramaticoTipo === 'html' ? 'selected' : ''; ?>>HTML controlado</option>
                <option value="modulos" <?php echo $conteudoProgramaticoTipo === 'modulos' ? 'selected' : ''; ?>>Ordenado por módulos</option>
            </select>
        </label>

        <div class="full" id="conteudo-programatico-texto-wrap">
            <label class="full">
                Conteúdo programático (texto/HTML)
                <small class="muted" style="display:block;margin-bottom:6px;">No modo HTML, use apenas tags seguras (sem scripts/iframes).</small>
                <textarea name="conteudo_programatico_texto" rows="6"><?php echo Helpers::e($curso['conteudo_programatico_texto'] ?? ''); ?></textarea>
            </label>
        </div>

        <div class="full" id="conteudo-programatico-modulos-wrap">
            <div class="muted" style="margin-bottom:8px;">Cadastre módulos com título e itens (um item por linha). Se o JavaScript falhar, você ainda pode preencher os módulos já exibidos abaixo.</div>
            <div id="conteudo-programatico-modulos-lista">
                <?php foreach ($modulosExistentes as $idx => $modulo): ?>
                    <div class="status-card" style="margin:12px 0;padding:12px;">
                        <label class="full">
                            Título do módulo
                            <input type="text" name="conteudo_programatico_modulos_titulo[]" value="<?php echo Helpers::e($modulo['titulo']); ?>">
                        </label>
                        <label class="full">
                            Itens do módulo (um por linha)
                            <textarea name="conteudo_programatico_modulos_itens[]" rows="4"><?php echo Helpers::e($modulo['itens']); ?></textarea>
                        </label>
                        <button type="button" class="button-link button-link--ghost" data-remover-modulo="1">Remover módulo</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="button-link" id="conteudo-programatico-add-modulo">Adicionar módulo</button>
        </div>

        <h2 class="full" style="margin:16px 0 8px;">Metodologia, produto final e avaliação</h2>
        <label class="full">
            Metodologia (opcional)
            <textarea name="metodologia" rows="4"><?php echo Helpers::e($curso['metodologia'] ?? ''); ?></textarea>
        </label>
        <label class="full">
            Produto final (opcional)
            <small class="muted" style="display:block;margin-bottom:6px;">Informe uma competência por linha para virar itens na página pública (opcional).</small>
            <textarea name="produto_final" rows="4"><?php echo Helpers::e($curso['produto_final'] ?? ''); ?></textarea>
        </label>
        <label class="full">
            Avaliação (opcional)
            <textarea name="avaliacao" rows="4"><?php echo Helpers::e($curso['avaliacao'] ?? ''); ?></textarea>
        </label>

        <h2 class="full" style="margin:16px 0 8px;">Publicação</h2>
        <label>
            Ordem
            <input type="number" name="ordem" min="0" value="<?php echo Helpers::e((string) ($curso['ordem'] ?? 0)); ?>">
        </label>
        <label>
            Status
            <select name="status">
                <?php foreach (array('rascunho', 'ativo', 'inativo', 'arquivado') as $status): ?>
                    <option value="<?php echo Helpers::e($status); ?>" <?php echo (($curso['status'] ?? '') === $status) ? 'selected' : ''; ?>>
                        <?php echo Helpers::e($status); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="checkbox">
            <input type="checkbox" name="em_promocao" value="1" <?php echo !empty(array_key_exists('em_promocao', $oldInput) ? $oldInput['em_promocao'] : $curso['em_promocao']) ? 'checked' : ''; ?>>
            Em promoção
        </label>
        <label class="checkbox">
            <input type="checkbox" name="destaque" value="1" <?php echo !empty($curso['destaque']) ? 'checked' : ''; ?>>
            Destaque
        </label>
        <?php $cancelUrl = '/admin/cursos'; ?>
        <?php $showSaveAsCopy = !empty($curso['id']); ?>
        <?php require BASE_PATH . '/resources/views/admin/partials/form-actions.php'; ?>
    </form>
</section>
</div>

<script>
(function () {
    var campoValor = document.getElementById('curso-valor');
    var campoValorPromocional = document.getElementById('curso-valor-promocional');
    var preview = document.getElementById('curso-preview-desconto');

    function formatarBR(valor) {
        try {
            return valor.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        } catch (e) {
            return String(valor);
        }
    }

    function atualizarPreviewDesconto() {
        if (!campoValor || !campoValorPromocional || !preview) return;

        var v = parseFloat(campoValor.value || '0');
        var vp = campoValorPromocional.value === '' ? NaN : parseFloat(campoValorPromocional.value || '0');

        if (!isFinite(v) || !isFinite(vp) || v <= 0 || vp < 0 || vp >= v) {
            preview.textContent = '';
            return;
        }

        var descontoValor = v - vp;
        var descontoPerc = (descontoValor / v) * 100;
        preview.textContent = 'De R$ ' + formatarBR(v) + ' por R$ ' + formatarBR(vp) + ' — desconto de R$ ' + formatarBR(descontoValor) + ' (' + Math.round(descontoPerc) + '%).';
    }

    if (campoValor) campoValor.addEventListener('input', atualizarPreviewDesconto);
    if (campoValorPromocional) campoValorPromocional.addEventListener('input', atualizarPreviewDesconto);
    atualizarPreviewDesconto();

    var tipoSelect = document.getElementById('conteudo-programatico-tipo');
    var wrapTexto = document.getElementById('conteudo-programatico-texto-wrap');
    var wrapModulos = document.getElementById('conteudo-programatico-modulos-wrap');

    function syncConteudoProgramatico() {
        if (!tipoSelect || !wrapTexto || !wrapModulos) return;
        if (tipoSelect.value === 'modulos') {
            wrapTexto.style.display = 'none';
            wrapModulos.style.display = '';
            return;
        }
        wrapTexto.style.display = '';
        wrapModulos.style.display = 'none';
    }

    if (tipoSelect) {
        tipoSelect.addEventListener('change', syncConteudoProgramatico);
        syncConteudoProgramatico();
    }

    var addBtn = document.getElementById('conteudo-programatico-add-modulo');
    var lista = document.getElementById('conteudo-programatico-modulos-lista');

    function bindRemover(btn) {
        btn.addEventListener('click', function () {
            var card = btn.closest ? btn.closest('.status-card') : null;
            if (!card) return;
            if (lista && lista.children.length <= 1) {
                var titulo = card.querySelector('input[name=\"conteudo_programatico_modulos_titulo[]\"]');
                var itens = card.querySelector('textarea[name=\"conteudo_programatico_modulos_itens[]\"]');
                if (titulo) titulo.value = '';
                if (itens) itens.value = '';
                return;
            }
            card.parentNode.removeChild(card);
        });
    }

    if (lista) {
        var removerBtns = lista.querySelectorAll('button[data-remover-modulo=\"1\"]');
        for (var i = 0; i < removerBtns.length; i++) bindRemover(removerBtns[i]);
    }

    if (addBtn && lista) {
        addBtn.addEventListener('click', function () {
            var wrapper = document.createElement('div');
            wrapper.className = 'status-card';
            wrapper.style.margin = '12px 0';
            wrapper.style.padding = '12px';
            wrapper.innerHTML =
                '<label class=\"full\">Título do módulo' +
                '<input type=\"text\" name=\"conteudo_programatico_modulos_titulo[]\" value=\"\">' +
                '</label>' +
                '<label class=\"full\">Itens do módulo (um por linha)' +
                '<textarea name=\"conteudo_programatico_modulos_itens[]\" rows=\"4\"></textarea>' +
                '</label>' +
                '<button type=\"button\" class=\"button-link button-link--ghost\" data-remover-modulo=\"1\">Remover módulo</button>';
            lista.appendChild(wrapper);
            var btn = wrapper.querySelector('button[data-remover-modulo=\"1\"]');
            if (btn) bindRemover(btn);
        });
    }
})();
</script>
