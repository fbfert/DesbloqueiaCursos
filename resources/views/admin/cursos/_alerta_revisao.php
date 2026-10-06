<?php
/**
 * Alerta de apontamentos de revisão "erro" em aberto (openspec/changes/fila-revisao-admin).
 *
 * Espera no escopo: $errosRevisaoAbertos (int) e $cursoIdAlerta (int).
 * Com zero, não renderiza nada.
 */
$errosRevisaoAbertos = isset($errosRevisaoAbertos) ? (int) $errosRevisaoAbertos : 0;
if ($errosRevisaoAbertos > 0):
?>
<div class="alert alert-warning">
    <?php if ($errosRevisaoAbertos === 1): ?>
        Há <strong>1 erro</strong> apontado pela revisão aguardando triagem neste curso.
    <?php else: ?>
        Há <strong><?php echo $errosRevisaoAbertos; ?> erros</strong> apontados pela revisão aguardando triagem neste curso.
    <?php endif; ?>
    <a href="/admin/revisoes?curso_id=<?php echo (int) $cursoIdAlerta; ?>&amp;status=aberto&amp;severidade=erro">Ver na fila de revisões</a>
</div>
<?php endif; ?>
