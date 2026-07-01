<?php use App\Core\Helpers; ?>
<?php
$cards = isset($cards) && is_array($cards) ? $cards : array();
$listaLabel = isset($lista_label) && $lista_label !== '' ? (string) $lista_label : 'Lista de conteúdos';
$emptyMessage = isset($empty_message) && $empty_message !== '' ? (string) $empty_message : 'Nenhum conteúdo publicado foi encontrado.';
$emptyDescription = isset($empty_description) && $empty_description !== '' ? (string) $empty_description : 'Quando houver conteúdos publicados, eles aparecerão aqui em cartões compactos.';
$emptyActionUrl = isset($empty_action_url) && $empty_action_url !== '' ? (string) $empty_action_url : '';
$emptyActionLabel = isset($empty_action_label) && $empty_action_label !== '' ? (string) $empty_action_label : 'Voltar';

if (empty($cards)): ?>
    <section class="status-card aluno-empty-state">
        <strong><?php echo Helpers::e($emptyMessage); ?></strong>
        <span><?php echo Helpers::e($emptyDescription); ?></span>
        <?php if ($emptyActionUrl !== ''): ?>
            <p><a class="button-link button-link--ghost" href="<?php echo Helpers::e($emptyActionUrl); ?>"><?php echo Helpers::e($emptyActionLabel); ?></a></p>
        <?php endif; ?>
    </section>
<?php else: ?>
    <section class="aluno-modulos-grid" aria-label="<?php echo Helpers::e($listaLabel); ?>">
        <?php foreach ($cards as $card): ?>
            <?php
            $titulo = Helpers::normalizarTextoLms((string) ($card['titulo'] ?? 'Conteúdo'));
            $url = isset($card['url']) ? (string) $card['url'] : '';
            $meta = isset($card['meta']) && is_array($card['meta']) ? $card['meta'] : array();
            $percentual = isset($card['percentual']) ? (float) $card['percentual'] : 0;
            $percentual = max(0, min(100, $percentual));
            $progressoTexto = isset($card['progresso_texto']) && $card['progresso_texto'] !== '' ? (string) $card['progresso_texto'] : '';
            $progressoLabel = isset($card['progresso_label']) && $card['progresso_label'] !== '' ? (string) $card['progresso_label'] : 'Progresso';
            $acaoLabel = isset($card['acao_label']) && $card['acao_label'] !== '' ? (string) $card['acao_label'] : 'Abrir conteúdo';
            $acaoClass = isset($card['acao_class']) && $card['acao_class'] !== '' ? (string) $card['acao_class'] : 'button-link--ghost';
            ?>
            <article class="status-card aluno-modulo-card">
                <a class="aluno-modulo-card__title" href="<?php echo Helpers::e($url); ?>">
                    <?php echo Helpers::e($titulo); ?>
                </a>

                <?php if (!empty($meta)): ?>
                    <div class="aluno-modulo-card__meta">
                        <?php foreach ($meta as $chip): ?>
                            <?php
                            $chipLabel = isset($chip['label']) ? (string) $chip['label'] : '';
                            $chipClass = isset($chip['class']) && $chip['class'] !== '' ? (string) $chip['class'] : 'pill--neutral';
                            ?>
                            <?php if ($chipLabel !== ''): ?>
                                <span class="pill <?php echo Helpers::e($chipClass); ?>"><?php echo Helpers::e($chipLabel); ?></span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="aluno-modulo-card__footer">
                    <div class="aluno-progress-chip aluno-progress-chip--compact" aria-label="<?php echo Helpers::e($progressoLabel); ?>">
                        <strong><?php echo Helpers::e(number_format($percentual, 2, ',', '.')); ?>%</strong>
                        <span><?php echo Helpers::e($progressoTexto !== '' ? $progressoTexto : $progressoLabel); ?></span>
                    </div>

                    <?php if ($url !== ''): ?>
                        <a class="button-link <?php echo Helpers::e($acaoClass); ?>" href="<?php echo Helpers::e($url); ?>"><?php echo Helpers::e($acaoLabel); ?></a>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
<?php endif; ?>
