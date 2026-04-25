<?php
$cupom = isset($form_data['cupom']) ? $form_data['cupom'] : null;
$relacoes = isset($form_data['relacoes']) ? $form_data['relacoes'] : array();

$relationValues = array();
foreach ($relacoes as $relacao) {
    $relationValues[$relacao['tipo_relacao']][] = $relacao['valor_relacao'];
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

        <label>Cursos permitidos</label>
        <textarea name="relacoes_cursos_eventos" rows="2" placeholder="IDs separados por vírgula"><?php echo htmlspecialchars($relationText('curso_evento'), ENT_QUOTES, 'UTF-8'); ?></textarea>

        <label>Tipos de curso permitidos</label>
        <textarea name="relacoes_tipos_curso" rows="2" placeholder="curso, evento"><?php echo htmlspecialchars($relationText('tipo_curso'), ENT_QUOTES, 'UTF-8'); ?></textarea>

        <label>Cidades permitidas</label>
        <textarea name="relacoes_cidades" rows="2" placeholder="Nomes separados por vírgula"><?php echo htmlspecialchars($relationText('cidade'), ENT_QUOTES, 'UTF-8'); ?></textarea>

        <label>Estados permitidos</label>
        <textarea name="relacoes_estados" rows="2" placeholder="UFs separados por vírgula"><?php echo htmlspecialchars($relationText('estado'), ENT_QUOTES, 'UTF-8'); ?></textarea>

        <button type="submit"><?php echo htmlspecialchars($submit_label, ENT_QUOTES, 'UTF-8'); ?></button>
    </form>
</section>

<?php if (!empty($cupom['id'])): ?>
    <section class="status-card">
        <strong>Exclusão</strong>
        <form method="post" action="/admin/cupons/excluir" class="admin-form">
            <input type="hidden" name="id" value="<?php echo (int) $cupom['id']; ?>">
            <label>Justificativa</label>
            <textarea name="justificativa" rows="3"></textarea>
            <button type="submit">Excluir e enviar para a lixeira</button>
        </form>
    </section>
<?php endif; ?>
</div>

