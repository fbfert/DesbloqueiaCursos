<?php use App\Core\Helpers; ?>

<?php
$rateio = isset($form_data['rateio']) ? $form_data['rateio'] : null;
$apuracoes = isset($form_data['apuracoes']) ? $form_data['apuracoes'] : array();
$cursos = isset($form_data['cursos']) ? $form_data['cursos'] : array();
$turmas = isset($form_data['turmas']) ? $form_data['turmas'] : array();
$professoresFiscal = isset($form_data['professores_fiscal']) ? $form_data['professores_fiscal'] : array();
$participantes = isset($form_data['participantes']) ? $form_data['participantes'] : array();
$percentualMaximo = isset($form_data['percentual_maximo']) ? (float) $form_data['percentual_maximo'] : 75.00;

if (empty($participantes)) {
    $participantes = array(
        array(),
        array(),
        array(),
    );
}
?>

<section class="hero">
    <h1><?php echo Helpers::e($title); ?></h1>
    <p>Cadastro e manutencao de rateios do portal.</p>
</section>

<?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
<?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

<section class="status-card">
    <strong>Regra operacional</strong>
    <p>O percentual total dos participantes não pode ultrapassar <?php echo number_format($percentualMaximo, 2, ',', '.'); ?>% da base liquida.</p>
</section>

<section class="status-card">
    <form method="post" action="<?php echo Helpers::e($action_url); ?>" class="admin-form">
        <input type="hidden" name="id" value="<?php echo !empty($rateio['id']) ? (int) $rateio['id'] : 0; ?>">
        <label>
            Apuracao
            <select name="apuracao_id" required>
                <option value="">Selecione</option>
                <?php foreach ($apuracoes as $apuracao): ?>
                    <option value="<?php echo (int) $apuracao['id']; ?>" <?php echo (int) ($rateio['apuracao_id'] ?? ($form_data['apuracao_selecionada'] ?? 0)) === (int) $apuracao['id'] ? 'selected' : ''; ?>>
                        <?php echo Helpers::e($apuracao['competencia']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Curso/evento
            <select name="curso_evento_id" required>
                <option value="">Selecione</option>
                <?php foreach ($cursos as $curso): ?>
                    <option value="<?php echo (int) $curso['id']; ?>" <?php echo (int) ($rateio['curso_evento_id'] ?? 0) === (int) $curso['id'] ? 'selected' : ''; ?>>
                        <?php echo Helpers::e($curso['nome']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Turma
            <select name="turma_id">
                <option value="">Sem turma</option>
                <?php foreach ($turmas as $turma): ?>
                    <option value="<?php echo (int) $turma['id']; ?>" <?php echo !empty($rateio['turma_id']) && (int) $rateio['turma_id'] === (int) $turma['id'] ? 'selected' : ''; ?>>
                        <?php echo Helpers::e($turma['nome'] . ' - ' . $turma['codigo']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Base bruta
            <input type="number" step="0.01" min="0" name="base_bruta" value="<?php echo Helpers::e((string) ($rateio['base_bruta'] ?? '0.00')); ?>">
        </label>
        <label>
            Desconto cupons
            <input type="number" step="0.01" min="0" name="desconto_cupons" value="<?php echo Helpers::e((string) ($rateio['desconto_cupons'] ?? '0.00')); ?>">
        </label>
        <label>
            Base liquida
            <input type="number" step="0.01" min="0" name="base_liquida" value="<?php echo Helpers::e((string) ($rateio['base_liquida'] ?? '0.00')); ?>">
        </label>
        <label>
            Status
            <select name="status">
                <?php foreach (array('calculado', 'fechado', 'cancelado') as $status): ?>
                    <option value="<?php echo Helpers::e($status); ?>" <?php echo (($rateio['status'] ?? 'calculado') === $status) ? 'selected' : ''; ?>>
                        <?php echo Helpers::e($status); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="full">
            Observacoes
            <textarea name="observacoes" rows="4"><?php echo Helpers::e($rateio['observacoes'] ?? ''); ?></textarea>
        </label>

        <div class="full">
            <strong>Participantes</strong>
            <p class="muted">Cadastre apenas professores com perfil fiscal ativo.</p>
        </div>

        <div class="full rateio-participants">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Professor</th>
                        <th>Tipo fiscal</th>
                        <th>Percentual</th>
                        <th>Retenção (%)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($participantes as $indice => $participante): ?>
                        <tr>
                            <td>
                                <select name="participantes[<?php echo (int) $indice; ?>][usuario_id]">
                                    <option value="">Selecione</option>
                                    <?php foreach ($professoresFiscal as $professor): ?>
                                        <option value="<?php echo (int) $professor['usuario_id']; ?>" <?php echo !empty($participante['usuario_id']) && (int) $participante['usuario_id'] === (int) $professor['usuario_id'] ? 'selected' : ''; ?>>
                                            <?php echo Helpers::e($professor['usuario_nome'] . ' - ' . $professor['tipo_pessoa']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <select name="participantes[<?php echo (int) $indice; ?>][tipo_fiscal]">
                                    <option value="pf" <?php echo (($participante['tipo_fiscal'] ?? 'pf') === 'pf') ? 'selected' : ''; ?>>PF</option>
                                    <option value="pj" <?php echo (($participante['tipo_fiscal'] ?? '') === 'pj') ? 'selected' : ''; ?>>PJ</option>
                                </select>
                            </td>
                            <td>
                                <input type="number" step="0.01" min="0" max="<?php echo Helpers::e((string) $percentualMaximo); ?>" name="participantes[<?php echo (int) $indice; ?>][percentual]" value="<?php echo Helpers::e((string) ($participante['percentual'] ?? '0.00')); ?>">
                            </td>
                            <td>
                                <input type="number" step="0.01" min="0" max="100" name="participantes[<?php echo (int) $indice; ?>][retencao_percentual]" value="<?php echo Helpers::e((string) ($participante['retencao_percentual'] ?? '0.00')); ?>">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <button type="submit"><?php echo Helpers::e($submit_label); ?></button>
    </form>
</section>

