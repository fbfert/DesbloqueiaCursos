<?php

$credibilidade = isset($credibilidade) && is_array($credibilidade) ? $credibilidade : array();
?>
<?php if (!empty($credibilidade)): ?>
<section class="dbc-section dbc-credibilidade">
    <div class="dbc-credibilidade__grid">
        <div class="dbc-credibilidade__item">
            <span class="dbc-credibilidade__numero"><?php echo number_format((int) $credibilidade['alunos'], 0, ',', '.'); ?>+</span>
            <span class="dbc-credibilidade__label">Alunos capacitados</span>
        </div>
        <div class="dbc-credibilidade__item">
            <span class="dbc-credibilidade__numero"><?php echo number_format((int) $credibilidade['cursos'], 0, ',', '.'); ?>+</span>
            <span class="dbc-credibilidade__label">Cursos publicados</span>
        </div>
        <div class="dbc-credibilidade__item">
            <span class="dbc-credibilidade__numero"><?php echo number_format((int) $credibilidade['certificados'], 0, ',', '.'); ?>+</span>
            <span class="dbc-credibilidade__label">Certificados emitidos</span>
        </div>
    </div>
</section>
<?php endif; ?>
