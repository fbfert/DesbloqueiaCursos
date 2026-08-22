<?php

use App\Core\Helpers;

$configuracao = isset($configuracao) && is_array($configuracao) ? $configuracao : array();
$resumo = isset($resumo) && is_array($resumo) ? $resumo : array();
$serie = isset($serie) && is_array($serie) ? $serie : array();
$campanhas = isset($campanhas) && is_array($campanhas) ? $campanhas : array();
$pedidos = isset($pedidos) && is_array($pedidos) ? $pedidos : array();
$historico = isset($historico) && is_array($historico) ? $historico : array();
$cursos = isset($cursos) && is_array($cursos) ? $cursos : array();
$dias = (int) ($dias ?? 30);
$ligado = !empty($configuracao['ativo']);
$cursosHabilitados = trim((string) ($configuracao['cursos_habilitados'] ?? ''));
$escopo = $cursosHabilitados === '' ? 'todos os cursos' : 'apenas os cursos ' . $cursosHabilitados;

$iniciado = (int) ($resumo['checkout_iniciado'] ?? 0);
$gerado = (int) ($resumo['pix_gerado'] ?? 0);
$pago = (int) ($resumo['pix_pago'] ?? 0);

$pct = function ($valor) {
    return $valor === null ? '—' : number_format((float) $valor, 1, ',', '.') . '%';
};
$dataHora = function ($valor) {
    return $valor ? date('d/m/Y H:i', strtotime((string) $valor)) : '—';
};
$dinheiro = function ($valor) {
    return 'R$ ' . number_format((float) $valor, 2, ',', '.');
};

// Escala do gráfico: o maior valor de qualquer série no período.
$maximo = 1;
foreach ($serie as $ponto) {
    $maximo = max($maximo, (int) $ponto['iniciado'], (int) $ponto['gerado'], (int) $ponto['pago']);
}
$totalPontos = max(1, count($serie));
$larguraGrafico = 720;
$alturaGrafico = 180;
$passo = $totalPontos > 1 ? $larguraGrafico / ($totalPontos - 1) : $larguraGrafico;

$linha = function ($chave) use ($serie, $maximo, $passo, $alturaGrafico) {
    $pontos = array();
    foreach ($serie as $i => $ponto) {
        $x = round($i * $passo, 2);
        $y = round($alturaGrafico - (((int) $ponto[$chave] / $maximo) * $alturaGrafico), 2);
        $pontos[] = $x . ',' . $y;
    }
    return implode(' ', $pontos);
};
?>
<div class="admin-page">

    <section class="admin-page__header">
        <div>
            <h1 class="admin-page__title">Checkout rápido</h1>
            <p class="admin-page__subtitle">Uma tela, três campos, sem senha. Ligue, desligue e acompanhe onde as pessoas desistem.</p>
        </div>
        <div class="cta-group">
            <form method="post" action="/admin/checkout-rapido/alternar">
                <?php echo $csrfField ?? ''; ?>
                <button type="submit" class="button-link <?php echo $ligado ? 'button-link--danger' : 'button-link--primary'; ?>">
                    <?php echo $ligado ? 'Desligar agora' : 'Ligar agora'; ?>
                </button>
            </form>
        </div>
    </section>

    <?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
    <?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

    <!-- ESTADO ATUAL -->
    <section class="status-card">
        <div class="panel-header">
            <div>
                <h2><?php echo $ligado ? 'Ligado' : 'Desligado'; ?></h2>
                <p class="muted">
                    <?php if ($ligado): ?>
                        A tela <code>/comprar?curso_id=N</code> está no ar para <?php echo Helpers::e($escopo); ?>.
                        O checkout antigo continua funcionando em paralelo.
                    <?php else: ?>
                        A rota <code>/comprar</code> responde 404. O checkout antigo é o único caminho de compra.
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <?php if ($ligado && empty($gatewayAtivo)): ?>
            <p class="admin-alerta admin-alerta--atencao">
                <strong>O gateway de pagamento está desligado.</strong>
                Quem preencher o formulário abre um pedido, mas não consegue pagar — a tela não exibe QR Code.
                Os pedidos ficam em <em>aguardando pagamento</em> e podem ser resgatados em
                <a href="/admin/pedidos/recuperacao">recuperação de pedidos</a>.
            </p>
        <?php endif; ?>
    </section>

    <!-- FUNIL -->
    <section class="status-card">
        <div class="panel-header">
            <div>
                <h2>Funil dos últimos <?php echo $dias; ?> dias</h2>
                <p class="muted">Onde as pessoas param entre abrir a tela e pagar.</p>
            </div>
            <div class="cta-group">
                <?php foreach (array(7, 30, 90) as $opcao): ?>
                    <a class="button-link button-link--ghost<?php echo $dias === $opcao ? ' is-active' : ''; ?>"
                       href="/admin/checkout-rapido?dias=<?php echo $opcao; ?>"><?php echo $opcao; ?> dias</a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="cr-funil">
            <div class="cr-etapa-card">
                <span class="cr-etapa-rotulo">Abriram a tela</span>
                <strong class="cr-etapa-numero"><?php echo $iniciado; ?></strong>
                <span class="cr-etapa-nota">checkout_iniciado</span>
            </div>
            <div class="cr-seta">
                <span class="cr-queda<?php echo ($resumo['queda_formulario'] ?? 0) > 40 ? ' cr-queda--alta' : ''; ?>">
                    −<?php echo $pct($resumo['queda_formulario'] ?? null); ?>
                </span>
                <span class="cr-seta-nota">desistem no formulário</span>
            </div>
            <div class="cr-etapa-card">
                <span class="cr-etapa-rotulo">Geraram o Pix</span>
                <strong class="cr-etapa-numero"><?php echo $gerado; ?></strong>
                <span class="cr-etapa-nota">pix_gerado</span>
            </div>
            <div class="cr-seta">
                <span class="cr-queda<?php echo ($resumo['queda_pagamento'] ?? 0) > 40 ? ' cr-queda--alta' : ''; ?>">
                    −<?php echo $pct($resumo['queda_pagamento'] ?? null); ?>
                </span>
                <span class="cr-seta-nota">não pagam</span>
            </div>
            <div class="cr-etapa-card cr-etapa-card--fim">
                <span class="cr-etapa-rotulo">Pagaram</span>
                <strong class="cr-etapa-numero"><?php echo $pago; ?></strong>
                <span class="cr-etapa-nota">pix_pago</span>
            </div>
        </div>

        <p class="cr-conversao">
            Conversão de ponta a ponta: <strong><?php echo $pct($resumo['conversao_total'] ?? null); ?></strong>
        </p>
    </section>

    <!-- HISTÓRICO -->
    <section class="status-card">
        <div class="panel-header">
            <div>
                <h2>Histórico diário</h2>
                <p class="muted">Cada linha é uma etapa do funil, dia a dia.</p>
            </div>
        </div>

        <?php if ($iniciado + $gerado + $pago === 0): ?>
            <p class="muted">Nenhum evento registrado neste período.</p>
        <?php else: ?>
            <div class="cr-legenda">
                <span><i class="cr-ponto cr-ponto--iniciado"></i> Abriram a tela</span>
                <span><i class="cr-ponto cr-ponto--gerado"></i> Geraram o Pix</span>
                <span><i class="cr-ponto cr-ponto--pago"></i> Pagaram</span>
                <span class="muted">pico: <?php echo $maximo; ?>/dia</span>
            </div>

            <div class="cr-grafico-wrap">
                <svg class="cr-grafico" viewBox="-4 -10 <?php echo $larguraGrafico + 8; ?> <?php echo $alturaGrafico + 34; ?>"
                     role="img" aria-label="Histórico diário do funil do checkout rápido"
                     preserveAspectRatio="none">
                    <line x1="0" y1="<?php echo $alturaGrafico; ?>" x2="<?php echo $larguraGrafico; ?>" y2="<?php echo $alturaGrafico; ?>" class="cr-eixo"></line>
                    <polyline class="cr-linha cr-linha--iniciado" points="<?php echo $linha('iniciado'); ?>"></polyline>
                    <polyline class="cr-linha cr-linha--gerado" points="<?php echo $linha('gerado'); ?>"></polyline>
                    <polyline class="cr-linha cr-linha--pago" points="<?php echo $linha('pago'); ?>"></polyline>
                </svg>
                <div class="cr-grafico-datas">
                    <span><?php echo date('d/m', strtotime($serie[0]['dia'])); ?></span>
                    <span><?php echo date('d/m', strtotime($serie[count($serie) - 1]['dia'])); ?></span>
                </div>
            </div>

            <details class="cr-detalhe">
                <summary>Ver os números dia a dia</summary>
                <div class="table-wrap">
                    <table class="admin-table">
                        <thead><tr><th>Dia</th><th>Abriram</th><th>Geraram Pix</th><th>Pagaram</th></tr></thead>
                        <tbody>
                        <?php foreach (array_reverse($serie) as $ponto): ?>
                            <?php if ($ponto['iniciado'] + $ponto['gerado'] + $ponto['pago'] === 0) { continue; } ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($ponto['dia'])); ?></td>
                                <td><?php echo (int) $ponto['iniciado']; ?></td>
                                <td><?php echo (int) $ponto['gerado']; ?></td>
                                <td><?php echo (int) $ponto['pago']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </details>
        <?php endif; ?>
    </section>

    <!-- CAMPANHAS -->
    <section class="status-card">
        <div class="panel-header">
            <div>
                <h2>Por campanha</h2>
                <p class="muted">Qual anúncio trouxe pedido e qual trouxe dinheiro.</p>
            </div>
        </div>

        <?php if (empty($campanhas)): ?>
            <p class="muted">Nenhum pedido pelo checkout rápido neste período.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Campanha</th><th>Origem</th><th>Pedidos</th>
                            <th>Pagos</th><th>Conversão</th><th>Receita</th><th>Com gclid</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($campanhas as $c): ?>
                        <?php $conv = (int) $c['pedidos'] > 0 ? ((int) $c['pagos'] / (int) $c['pedidos']) * 100 : null; ?>
                        <tr>
                            <td><strong><?php echo Helpers::e((string) $c['campanha']); ?></strong></td>
                            <td><?php echo Helpers::e((string) $c['origem']); ?></td>
                            <td><?php echo (int) $c['pedidos']; ?></td>
                            <td><?php echo (int) $c['pagos']; ?></td>
                            <td><?php echo $pct($conv); ?></td>
                            <td><?php echo $dinheiro($c['receita']); ?></td>
                            <td><?php echo (int) $c['com_gclid']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <!-- PEDIDOS -->
    <section class="status-card">
        <div class="panel-header">
            <div>
                <h2>Últimos pedidos</h2>
                <p class="muted">Quem abriu Pix e não pagou é candidato a resgate por WhatsApp.</p>
            </div>
            <div class="cta-group">
                <a class="button-link button-link--ghost" href="/admin/pedidos/recuperacao">Recuperação de pedidos</a>
            </div>
        </div>

        <?php if (empty($pedidos)): ?>
            <p class="muted">Nenhum pedido pelo checkout rápido ainda.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr><th>Pedido</th><th>Curso</th><th>Contato</th><th>Origem</th><th>Valor</th><th>Situação</th><th>Aberto em</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($pedidos as $p): ?>
                        <?php
                        $pago = in_array((string) $p['status'], array('pago', 'aprovado'), true);
                        $zap = preg_replace('/\D+/', '', (string) $p['pagador_telefone']);
                        ?>
                        <tr>
                            <td>
                                <a href="/admin/pedidos/<?php echo (int) $p['id']; ?>"><?php echo Helpers::e((string) $p['codigo']); ?></a>
                                <?php if (($p['cadastro_status'] ?? '') === 'pendente'): ?>
                                    <span class="cr-tag">cadastro pendente</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo Helpers::e((string) ($p['curso_nome'] ?? '—')); ?></td>
                            <td class="cr-contato">
                                <span><?php echo Helpers::e((string) $p['pagador_email']); ?></span>
                                <?php if ($zap !== ''): ?>
                                    <a href="https://wa.me/<?php echo Helpers::e($zap); ?>" target="_blank" rel="noopener">
                                        <?php echo Helpers::e((string) $p['pagador_telefone']); ?>
                                    </a>
                                <?php endif; ?>
                            </td>
                            <td class="cr-origem">
                                <?php if (!empty($p['utm_campaign'])): ?>
                                    <span><?php echo Helpers::e((string) $p['utm_campaign']); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($p['utm_source'])): ?>
                                    <span class="muted"><?php echo Helpers::e((string) $p['utm_source']); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($p['gclid'])): ?><span class="cr-tag cr-tag--ads">gclid</span><?php endif; ?>
                                <?php if (empty($p['utm_campaign']) && empty($p['utm_source'])): ?>
                                    <span class="muted">direto</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $dinheiro($p['total']); ?></td>
                            <td>
                                <span class="cr-status <?php echo $pago ? 'cr-status--pago' : 'cr-status--aguardando'; ?>">
                                    <?php echo Helpers::e(str_replace('_', ' ', (string) $p['status'])); ?>
                                </span>
                            </td>
                            <td><?php echo $dataHora($p['created_at']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <!-- CONFIGURAÇÃO -->
    <section class="status-card">
        <div class="panel-header">
            <div>
                <h2>Configuração</h2>
                <p class="muted">Vale imediatamente, sem publicar nada.</p>
            </div>
        </div>

        <form method="post" action="/admin/checkout-rapido/salvar" class="admin-form">
            <?php echo $csrfField ?? ''; ?>

            <label class="cr-switch">
                <input type="checkbox" name="ativo" value="1" <?php echo $ligado ? 'checked' : ''; ?>>
                <span><strong>Checkout rápido ligado</strong><br>
                <span class="muted">Desligado, <code>/comprar</code> responde 404 e nada muda para quem compra hoje.</span></span>
            </label>

            <div class="cr-campo-admin">
                <label for="cursos_habilitados">Cursos habilitados</label>
                <input type="text" id="cursos_habilitados" name="cursos_habilitados"
                       value="<?php echo Helpers::e($cursosHabilitados); ?>"
                       placeholder="ex.: 124,120,118">
                <p class="muted">IDs separados por vírgula. <strong>Vazio = todos os cursos.</strong>
                   Use para testar em um curso só antes de abrir para o resto.</p>
            </div>

            <details class="cr-detalhe">
                <summary>Cursos com turma aberta (<?php echo count($cursos); ?>)</summary>
                <div class="table-wrap">
                    <table class="admin-table">
                        <thead><tr><th>ID</th><th>Curso</th><th>Link do checkout</th></tr></thead>
                        <tbody>
                        <?php foreach ($cursos as $c): ?>
                            <tr>
                                <td><?php echo (int) $c['id']; ?></td>
                                <td><?php echo Helpers::e((string) $c['nome']); ?></td>
                                <td><code>/comprar?curso_id=<?php echo (int) $c['id']; ?></code></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </details>

            <div class="cr-grade">
                <div class="cr-campo-admin">
                    <label for="pix_expira_minutos">Validade do Pix (minutos)</label>
                    <input type="number" id="pix_expira_minutos" name="pix_expira_minutos" min="5" max="1440"
                           value="<?php echo (int) ($configuracao['pix_expira_minutos'] ?? 30); ?>">
                </div>
                <div class="cr-campo-admin">
                    <label for="polling_intervalo_segundos">Consulta de pagamento (segundos)</label>
                    <input type="number" id="polling_intervalo_segundos" name="polling_intervalo_segundos" min="2" max="60"
                           value="<?php echo (int) ($configuracao['polling_intervalo_segundos'] ?? 3); ?>">
                </div>
                <div class="cr-campo-admin">
                    <label for="token_acesso_validade_horas">Validade do link de acesso (horas)</label>
                    <input type="number" id="token_acesso_validade_horas" name="token_acesso_validade_horas" min="1" max="8760"
                           value="<?php echo (int) ($configuracao['token_acesso_validade_horas'] ?? 168); ?>">
                </div>
            </div>

            <div class="cr-campo-admin">
                <label for="texto_lgpd">Aviso de privacidade exibido no formulário</label>
                <textarea id="texto_lgpd" name="texto_lgpd" rows="2"><?php echo Helpers::e((string) ($configuracao['texto_lgpd'] ?? '')); ?></textarea>
            </div>

            <h3 class="cr-subtitulo">Google Ads</h3>
            <p class="muted">A conversão dispara no servidor, quando o pagamento é confirmado — nunca no envio do formulário.</p>
            <label class="cr-switch">
                <input type="checkbox" name="google_ads_ativo" value="1" <?php echo !empty($configuracao['google_ads_ativo']) ? 'checked' : ''; ?>>
                <span>Enviar conversões para o Google Ads</span>
            </label>
            <div class="cr-grade">
                <div class="cr-campo-admin">
                    <label for="google_ads_conversion_id">ID de conversão</label>
                    <input type="text" id="google_ads_conversion_id" name="google_ads_conversion_id"
                           value="<?php echo Helpers::e((string) ($configuracao['google_ads_conversion_id'] ?? '')); ?>"
                           placeholder="AW-123456789">
                </div>
                <div class="cr-campo-admin">
                    <label for="google_ads_conversion_label">Rótulo de conversão</label>
                    <input type="text" id="google_ads_conversion_label" name="google_ads_conversion_label"
                           value="<?php echo Helpers::e((string) ($configuracao['google_ads_conversion_label'] ?? '')); ?>">
                </div>
            </div>

            <h3 class="cr-subtitulo">WhatsApp</h3>
            <label class="cr-switch">
                <input type="checkbox" name="whatsapp_ativo" value="1" <?php echo !empty($configuracao['whatsapp_ativo']) ? 'checked' : ''; ?>>
                <span>Enviar o link de acesso também por WhatsApp</span>
            </label>
            <div class="cr-campo-admin">
                <label for="whatsapp_provider">Provedor</label>
                <input type="text" id="whatsapp_provider" name="whatsapp_provider"
                       value="<?php echo Helpers::e((string) ($configuracao['whatsapp_provider'] ?? '')); ?>"
                       placeholder="ainda não contratado">
            </div>

            <div class="cta-group">
                <button type="submit" class="button-link button-link--primary">Salvar</button>
            </div>
        </form>
    </section>

    <!-- HISTÓRICO DA CHAVE -->
    <section class="status-card">
        <div class="panel-header">
            <div>
                <h2>Quem ligou e desligou</h2>
                <p class="muted">Toda mudança fica registrada na auditoria.</p>
            </div>
        </div>

        <?php if (empty($historico)): ?>
            <p class="muted">Nenhuma alteração registrada ainda.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="admin-table">
                    <thead><tr><th>Quando</th><th>Quem</th><th>Mudança</th><th>Escopo</th><th>IP</th></tr></thead>
                    <tbody>
                    <?php foreach ($historico as $h): ?>
                        <?php
                        $meta = json_decode((string) $h['metadados'], true);
                        $antes = (int) ($meta['ativo_antes'] ?? 0);
                        $depois = (int) ($meta['ativo_depois'] ?? 0);
                        $cursosDepois = trim((string) ($meta['cursos_depois'] ?? ''));
                        ?>
                        <tr>
                            <td><?php echo $dataHora($h['created_at']); ?></td>
                            <td>
                                <?php echo Helpers::e((string) ($h['usuario_nome'] ?: $h['usuario_email'] ?: 'sistema')); ?>
                                <?php if (!empty($meta['via'])): ?><span class="cr-tag">atalho</span><?php endif; ?>
                            </td>
                            <td>
                                <?php if ($antes !== $depois): ?>
                                    <span class="cr-status <?php echo $depois ? 'cr-status--pago' : 'cr-status--aguardando'; ?>">
                                        <?php echo $depois ? 'ligou' : 'desligou'; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="muted">ajustou parâmetros</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $cursosDepois === '' ? 'todos os cursos' : Helpers::e('cursos ' . $cursosDepois); ?></td>
                            <td class="muted"><?php echo Helpers::e((string) $h['ip_address']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

</div>
