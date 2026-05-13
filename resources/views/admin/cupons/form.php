<?php
$cupom = isset($form_data['cupom']) ? $form_data['cupom'] : null;
$cursosDisponiveis = isset($form_data['cursos_disponiveis']) ? $form_data['cursos_disponiveis'] : array();
$cupomCursos = isset($form_data['cupom_cursos']) ? $form_data['cupom_cursos'] : array();
$relacoes = isset($form_data['relacoes']) ? $form_data['relacoes'] : array();
$escopoSelecionado = isset($cupom['escopo']) ? $cupom['escopo'] : 'todo_site';

$relationValues = array();
foreach ($relacoes as $relacao) {
    $relationValues[$relacao['tipo_relacao']][] = $relacao['valor_relacao'];
}

$cupomCursoIdsSelecionados = array();
foreach ($cupomCursos as $cupomCurso) {
    $cupomCursoIdsSelecionados[(int) $cupomCurso['curso_id']] = true;
}

$relationText = function ($tipo) use ($relationValues) {
    if (empty($relationValues[$tipo])) {
        return '';
    }

    return implode(', ', $relationValues[$tipo]);
};
?>

<div class="admin-page">
<section class="admin-page__header">
    <div>
        <h1 class="admin-page__title"><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h1>
        <p class="admin-page__subtitle">Cadastro e edição de cupons com regras e restrições.</p>
    </div>
</section>

<?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
<?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

<section class="status-card">
    <form method="post" action="<?php echo htmlspecialchars($action_url, ENT_QUOTES, 'UTF-8'); ?>" class="admin-form">
        <?php if (!empty($cupom['id'])): ?>
            <input type="hidden" name="id" value="<?php echo (int) $cupom['id']; ?>">
        <?php endif; ?>

        <label>Código</label>
        <input type="text" name="codigo" value="<?php echo htmlspecialchars((string) ($cupom['codigo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">

        <label>Nome</label>
        <input type="text" name="nome" value="<?php echo htmlspecialchars((string) ($cupom['nome'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">

        <label>Descrição</label>
        <textarea name="descricao" rows="3"><?php echo htmlspecialchars((string) ($cupom['descricao'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>

        <label>Tipo</label>
        <select name="tipo">
            <?php $tipoSelecionado = $cupom['tipo'] ?? 'publico'; ?>
            <option value="publico" <?php echo $tipoSelecionado === 'publico' ? 'selected' : ''; ?>>Público</option>
            <option value="privado" <?php echo $tipoSelecionado === 'privado' ? 'selected' : ''; ?>>Privado</option>
            <option value="usuario" <?php echo $tipoSelecionado === 'usuario' ? 'selected' : ''; ?>>Usuário</option>
            <option value="empresa" <?php echo $tipoSelecionado === 'empresa' ? 'selected' : ''; ?>>Empresa</option>
        </select>

        <label class="full">Validade do cupom</label>
        <div class="full admin-cupons__escopo-opcoes">
            <label class="checkbox">
                <input type="radio" name="escopo" value="todo_site" <?php echo $escopoSelecionado === 'todo_site' ? 'checked' : ''; ?>>
                Todo o site
            </label>
            <label class="checkbox">
                <input type="radio" name="escopo" value="cursos_especificos" <?php echo $escopoSelecionado === 'cursos_especificos' ? 'checked' : ''; ?>>
                Cursos específicos
            </label>
        </div>

        <div class="full admin-cupons__cursos-bloco" data-admin-cupons-cursos <?php echo $escopoSelecionado === 'cursos_especificos' ? '' : 'hidden'; ?>>
            <label>Cursos disponíveis</label>
            <p class="admin-page__subtitle">Selecione um ou mais cursos ativos ou em rascunho para este cupom.</p>
            <?php if (!empty($cursosDisponiveis)): ?>
                <div class="permission-grid admin-cupons__curso-grid">
                    <?php foreach ($cursosDisponiveis as $curso): ?>
                        <label class="checkbox">
                            <input type="checkbox" name="cupom_curso_ids[]" value="<?php echo (int) $curso['id']; ?>" <?php echo !empty($cupomCursoIdsSelecionados[(int) $curso['id']]) ? 'checked' : ''; ?>>
                            <span>
                                <?php echo htmlspecialchars((string) $curso['nome'], ENT_QUOTES, 'UTF-8'); ?><br>
                                <small>#<?php echo (int) $curso['id']; ?> | <?php echo htmlspecialchars((string) $curso['status'], ENT_QUOTES, 'UTF-8'); ?></small>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p>Nenhum curso ativo ou em rascunho disponível para vincular.</p>
            <?php endif; ?>
        </div>

        <label>Tipo de desconto</label>
        <select name="desconto_tipo">
            <?php $descontoTipoSelecionado = $cupom['desconto_tipo'] ?? 'percentual'; ?>
            <option value="percentual" <?php echo $descontoTipoSelecionado === 'percentual' ? 'selected' : ''; ?>>Percentual</option>
            <option value="valor" <?php echo $descontoTipoSelecionado === 'valor' ? 'selected' : ''; ?>>Valor</option>
        </select>

        <label>Valor do desconto</label>
        <input type="number" step="0.01" min="0" name="valor_desconto" value="<?php echo htmlspecialchars((string) ($cupom['valor_desconto'] ?? '0.00'), ENT_QUOTES, 'UTF-8'); ?>">

        <label>Quantidade mínima de vagas</label>
        <input type="number" min="0" name="quantidade_minima_vagas" value="<?php echo htmlspecialchars((string) ($cupom['quantidade_minima_vagas'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">

        <label>Limite total de usos</label>
        <input type="number" min="0" name="limite_total_usos" value="<?php echo htmlspecialchars((string) ($cupom['limite_total_usos'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">

        <label>Limite por usuário</label>
        <input type="number" min="0" name="limite_por_usuario" value="<?php echo htmlspecialchars((string) ($cupom['limite_por_usuario'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">

        <label>Data de início</label>
        <input type="datetime-local" name="data_inicio" value="<?php echo htmlspecialchars(isset($cupom['data_inicio']) && $cupom['data_inicio'] ? date('Y-m-d\TH:i', strtotime($cupom['data_inicio'])) : '', ENT_QUOTES, 'UTF-8'); ?>">

        <label>Data de fim</label>
        <input type="datetime-local" name="data_fim" value="<?php echo htmlspecialchars(isset($cupom['data_fim']) && $cupom['data_fim'] ? date('Y-m-d\TH:i', strtotime($cupom['data_fim'])) : '', ENT_QUOTES, 'UTF-8'); ?>">

        <label>Status</label>
        <select name="status">
            <?php $statusSelecionado = $cupom['status'] ?? 'rascunho'; ?>
            <option value="rascunho" <?php echo $statusSelecionado === 'rascunho' ? 'selected' : ''; ?>>Rascunho</option>
            <option value="ativo" <?php echo $statusSelecionado === 'ativo' ? 'selected' : ''; ?>>Ativo</option>
            <option value="inativo" <?php echo $statusSelecionado === 'inativo' ? 'selected' : ''; ?>>Inativo</option>
            <option value="expirado" <?php echo $statusSelecionado === 'expirado' ? 'selected' : ''; ?>>Expirado</option>
        </select>

        <label>Link promocional</label>
        <input type="text" name="link_promocional" value="<?php echo htmlspecialchars((string) ($cupom['link_promocional'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">

        <label>Usuários permitidos</label>
        <textarea name="relacoes_usuarios" rows="2" placeholder="IDs ou lista separada por vírgula"><?php echo htmlspecialchars($relationText('usuario'), ENT_QUOTES, 'UTF-8'); ?></textarea>

        <label>Empresas permitidas</label>
        <textarea name="relacoes_empresas" rows="2" placeholder="Identificadores separados por vírgula"><?php echo htmlspecialchars($relationText('empresa'), ENT_QUOTES, 'UTF-8'); ?></textarea>

        <label>Perfis permitidos</label>
        <textarea name="relacoes_perfis" rows="2" placeholder="Slugs separados por vírgula"><?php echo htmlspecialchars($relationText('perfil'), ENT_QUOTES, 'UTF-8'); ?></textarea>

        <label>Tipos de curso permitidos</label>
        <textarea name="relacoes_tipos_curso" rows="2" placeholder="curso, evento"><?php echo htmlspecialchars($relationText('tipo_curso'), ENT_QUOTES, 'UTF-8'); ?></textarea>

        <label>Cidades permitidas</label>
        <textarea name="relacoes_cidades" rows="2" placeholder="Nomes separados por vírgula"><?php echo htmlspecialchars($relationText('cidade'), ENT_QUOTES, 'UTF-8'); ?></textarea>

        <label>Estados permitidos</label>
        <textarea name="relacoes_estados" rows="2" placeholder="UFs separados por vírgula"><?php echo htmlspecialchars($relationText('estado'), ENT_QUOTES, 'UTF-8'); ?></textarea>

        <?php $cancelUrl = '/admin/cupons'; ?>
        <?php require BASE_PATH . '/resources/views/admin/partials/form-actions.php'; ?>
    </form>
</section>

</div>

<script>
(function () {
    var radios = document.querySelectorAll('input[name="escopo"]');
    var blocoCursos = document.querySelector('[data-admin-cupons-cursos]');

    if (!radios.length || !blocoCursos) {
        return;
    }

    function atualizarVisibilidade() {
        var escopoSelecionado = document.querySelector('input[name="escopo"]:checked');
        if (!escopoSelecionado) {
            blocoCursos.hidden = true;
            return;
        }

        blocoCursos.hidden = escopoSelecionado.value !== 'cursos_especificos';
    }

    for (var index = 0; index < radios.length; index++) {
        radios[index].addEventListener('change', atualizarVisibilidade);
    }

    atualizarVisibilidade();
})();
</script>

