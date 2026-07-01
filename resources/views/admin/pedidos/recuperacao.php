<?php use App\Core\Helpers; ?>
<?php
$filters = isset($filters) && is_array($filters) ? $filters : array();
$pagination = isset($pagination) && is_array($pagination) ? $pagination : array('total' => 0, 'page' => 1, 'per_page' => 20, 'pages' => 1);
$modeloAtual = isset($filters['modelo_chave']) ? (string) $filters['modelo_chave'] : 'pedido_recuperacao_primeiro_lembrete';
$perPageAtual = isset($filters['per_page']) ? (int) $filters['per_page'] : 20;
$page = isset($pagination['page']) ? (int) $pagination['page'] : 1;
$pages = isset($pagination['pages']) ? (int) $pagination['pages'] : 1;
$total = isset($pagination['total']) ? (int) $pagination['total'] : 0;
$from = $total > 0 ? (($page - 1) * $perPageAtual) + 1 : 0;
$to = $total > 0 ? min($page * $perPageAtual, $total) : 0;
$automacao = isset($automacao) && is_array($automacao) ? $automacao : array();
$ultimaExecucao = isset($automacao['ultima_execucao']) && is_array($automacao['ultima_execucao']) ? $automacao['ultima_execucao'] : null;
$resumo = isset($resumo) && is_array($resumo) ? $resumo : array();
$automacaoAtiva = !empty($automacao['ativa']);
$recuperaveis = (int) ($resumo['recuperaveis'] ?? 0);
$enviadosHoje = (int) ($resumo['enviados_hoje'] ?? 0);
$bloqueados = (int) ($resumo['bloqueados'] ?? 0);
$erros = (int) ($resumo['erros'] ?? 0);
$optouts = (int) ($resumo['optouts'] ?? 0);
$ultimaExecucaoTexto = '-';
if ($ultimaExecucao) {
    $ultimaExecucaoTexto = (string) ($ultimaExecucao['finished_at'] ?? $ultimaExecucao['started_at'] ?? '-');
}

$summaryCards = array(
    array(
        'title' => 'Recuperáveis',
        'value' => $recuperaveis,
        'note' => 'prontos para contato',
        'tone' => 'info',
    ),
    array(
        'title' => 'Enviados hoje',
        'value' => $enviadosHoje,
        'note' => 'recuperações disparadas',
        'tone' => 'success',
    ),
    array(
        'title' => 'Bloqueados',
        'value' => $bloqueados,
        'note' => 'por regra de segurança',
        'tone' => 'warning',
    ),
    array(
        'title' => 'Com erro',
        'value' => $erros,
        'note' => 'verificar histórico',
        'tone' => 'danger',
    ),
    array(
        'title' => 'Opt-out',
        'value' => $optouts,
        'note' => 'não recebem lembretes',
        'tone' => 'neutral',
    ),
    array(
        'title' => 'Automação',
        'value' => $automacaoAtiva ? 'Ativa' : 'Inativa',
        'note' => $automacaoAtiva ? 'régua automática' : 'régua automática pausada',
        'tone' => $automacaoAtiva ? 'success' : 'neutral',
    ),
);

$statusMap = array(
    'pedido_incompleto' => array('label' => 'Pedido incompleto', 'tone' => 'warning'),
    'rascunho' => array('label' => 'Rascunho', 'tone' => 'neutral'),
    'aguardando_pagamento' => array('label' => 'Aguardando pagamento', 'tone' => 'warning'),
    'comprovante_enviado' => array('label' => 'Comprovante enviado', 'tone' => 'info'),
    'em_analise' => array('label' => 'Em análise', 'tone' => 'info'),
    'pendencia' => array('label' => 'Pendência', 'tone' => 'warning'),
    'aguardando_reenvio' => array('label' => 'Aguardando reenvio', 'tone' => 'warning'),
    'aprovado' => array('label' => 'Aprovado', 'tone' => 'success'),
    'cancelado' => array('label' => 'Cancelado', 'tone' => 'danger'),
);

$gatewayMap = array(
    'abacatepay' => array('label' => 'AbacatePay', 'tone' => 'info'),
    'manual' => array('label' => 'Manual', 'tone' => 'neutral'),
    '' => array('label' => 'Manual', 'tone' => 'neutral'),
);

function pedidosRecuperacaoBadgeTone($value)
{
    $value = strtolower(trim((string) $value));

    switch ($value) {
        case 'success':
        case 'ativa':
        case 'enviado':
        case 'aprovado':
        case 'sim':
            return 'success';
        case 'warning':
        case 'pendente':
        case 'bloqueado':
        case 'pedido_incompleto':
            return 'warning';
        case 'danger':
        case 'erro':
        case 'cancelado':
            return 'danger';
        case 'info':
            return 'info';
        default:
            return 'neutral';
    }
}
?>

<section class="admin-page admin-pedidos-page admin-pedidos-recuperacao-page">
    <header class="admin-page__header admin-page__header--with-metrics pedidos-recuperacao-hero">
        <div class="admin-page__header-content">
            <h1 class="admin-page__title">Recuperação de pedidos incompletos</h1>
            <p class="admin-page__subtitle">Pedidos elegíveis agora. Envie manualmente, use cupom e acompanhe a régua automática sem mudar as regras.</p>
            <p class="admin-page__subtitle">Cron pronta para executar a régua automática.</p>
        </div>
        <div class="admin-page__actions cta-group pedidos-recuperacao-hero__actions">
            <a class="button-link button-link--ghost" href="/admin/pedidos">Pedidos</a>
            <a class="button-link button-link--ghost" href="/admin/emails/modelos">Modelos de e-mail</a>
        </div>
    </header>

    <section class="status-card pedidos-recuperacao-summary">
        <div class="pedidos-recuperacao-section-head">
            <div>
                <span class="pedidos-recuperacao-section-kicker">Resumo rápido</span>
                <h2 class="pedidos-recuperacao-section-title">Cards informacionais</h2>
            </div>
            <small class="muted">Contagem resumida do painel e da automação.</small>
        </div>

        <div class="pedidos-recuperacao-kpis">
            <?php foreach ($summaryCards as $card): ?>
                <article class="pedidos-recuperacao-kpi pedidos-recuperacao-kpi--<?php echo Helpers::e((string) $card['tone']); ?>">
                    <div class="pedidos-recuperacao-kpi__header">
                        <span class="pedidos-recuperacao-kpi__label"><?php echo Helpers::e((string) $card['title']); ?></span>
                        <span class="pedidos-recuperacao-kpi__dot pedidos-recuperacao-kpi__dot--<?php echo Helpers::e((string) $card['tone']); ?>" aria-hidden="true"></span>
                    </div>
                    <strong class="pedidos-recuperacao-kpi__value<?php echo !is_numeric($card['value']) ? ' pedidos-recuperacao-kpi__value--text' : ''; ?>"><?php echo Helpers::e((string) $card['value']); ?></strong>
                    <small class="pedidos-recuperacao-kpi__note"><?php echo Helpers::e((string) $card['note']); ?></small>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="status-card pedidos-recuperacao-guides">
        <div class="pedidos-recuperacao-section-head">
            <div>
                <span class="pedidos-recuperacao-section-kicker">Operação</span>
                <h2 class="pedidos-recuperacao-section-title">Cron, régua e bloqueios</h2>
            </div>
            <small class="muted">Textos curtos, leitura rápida e sem expor dados sensíveis.</small>
        </div>

        <div class="pedidos-recuperacao-guide-grid">
            <article class="pedidos-recuperacao-guide">
                <strong>Como funciona a régua</strong>
                <p>A recuperação usa etapas internas em 24 horas, 3 dias, 7 dias e 20 dias.</p>
                <p>A mesma etapa não é repetida e o intervalo de 24 horas evita disparos duplicados.</p>
            </article>

            <article class="pedidos-recuperacao-guide">
                <strong>Instruções da Cron</strong>
                <p><span class="pedidos-recuperacao-guide__label">Frequência recomendada</span> <?php echo Helpers::e((string) ($automacao['frequencia_recomendada'] ?? 'A cada 30 minutos ou a cada 1 hora.')); ?></p>
                <pre class="pedidos-recuperacao-command"><?php echo Helpers::e(!empty($automacao['comando_recomendado']) ? $automacao['comando_recomendado'] : '/usr/bin/php ' . BASE_PATH . '/scripts/cron_recuperacao_pedidos.php --limit=50 >> ' . BASE_PATH . '/storage/logs/cron-recuperacao-pedidos.log 2>&1'); ?></pre>
                <p>A régua interna evita envios duplicados mesmo com a Cron rodando com frequência.</p>
            </article>

            <article class="pedidos-recuperacao-guide">
                <strong>Regras de bloqueio</strong>
                <ul>
                    <li>pedido pago, aprovado, cancelado, expirado ou em análise;</li>
                    <li>inscrição ativa no mesmo curso;</li>
                    <li>opt-out confirmado;</li>
                    <li>pedido com mais de 30 dias;</li>
                    <li>pagamento já aprovado no gateway.</li>
                </ul>
            </article>
        </div>
    </section>

    <?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
    <?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

    <section class="status-card pedidos-recuperacao-filters-card">
        <div class="pedidos-recuperacao-section-head">
            <div>
                <span class="pedidos-recuperacao-section-kicker">Filtros</span>
                <h2 class="pedidos-recuperacao-section-title">Refinar a busca</h2>
            </div>
            <small class="muted">Busca compacta, filtros claros e sem excesso de espaço.</small>
        </div>

        <form method="get" action="/admin/pedidos/recuperacao" class="admin-filters pedidos-recuperacao-filters">
            <div class="admin-filters__row pedidos-recuperacao-filters__row">
                <label>Busca
                    <input type="text" name="q" value="<?php echo Helpers::e((string) ($filters['q'] ?? '')); ?>" placeholder="Código, aluno, e-mail ou curso">
                </label>
                <label>Curso
                    <input type="text" name="curso" value="<?php echo Helpers::e((string) ($filters['curso'] ?? '')); ?>" placeholder="Nome do curso">
                </label>
                <label>Modelo
                    <select name="modelo_chave">
                        <option value="pedido_recuperacao_primeiro_lembrete" <?php echo $modeloAtual === 'pedido_recuperacao_primeiro_lembrete' ? 'selected' : ''; ?>>1º lembrete</option>
                        <option value="pedido_recuperacao_segundo_lembrete" <?php echo $modeloAtual === 'pedido_recuperacao_segundo_lembrete' ? 'selected' : ''; ?>>2º lembrete</option>
                        <option value="pedido_recuperacao_terceiro_lembrete" <?php echo $modeloAtual === 'pedido_recuperacao_terceiro_lembrete' ? 'selected' : ''; ?>>3º lembrete</option>
                        <option value="pedido_recuperacao_ultimo_lembrete" <?php echo $modeloAtual === 'pedido_recuperacao_ultimo_lembrete' ? 'selected' : ''; ?>>Último lembrete</option>
                        <option value="pedido_recuperacao_quase_expirando" <?php echo $modeloAtual === 'pedido_recuperacao_quase_expirando' ? 'selected' : ''; ?>>Quase expirando</option>
                        <option value="pedido_recuperacao_com_cupom" <?php echo $modeloAtual === 'pedido_recuperacao_com_cupom' ? 'selected' : ''; ?>>Com cupom</option>
                    </select>
                </label>
            </div>
            <div class="admin-filters__row pedidos-recuperacao-filters__row">
                <label>De
                    <input type="date" name="de" value="<?php echo Helpers::e((string) ($filters['de'] ?? '')); ?>">
                </label>
                <label>Até
                    <input type="date" name="ate" value="<?php echo Helpers::e((string) ($filters['ate'] ?? '')); ?>">
                </label>
                <label>Por página
                    <select name="per_page">
                        <?php foreach (array(20, 50, 100) as $valor): ?>
                            <option value="<?php echo (int) $valor; ?>" <?php echo $perPageAtual === (int) $valor ? 'selected' : ''; ?>><?php echo (int) $valor; ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <div class="cta-group admin-pedidos__filter-actions pedidos-recuperacao-filters__actions">
                <button type="submit" class="button-link button-link--primary">Filtrar</button>
                <a class="button-link button-link--ghost" href="/admin/pedidos/recuperacao">Limpar</a>
                <a class="button-link button-link--ghost" href="/admin/pedidos">Voltar para pedidos</a>
            </div>
        </form>
    </section>

    <section class="status-card pedidos-recuperacao-send-card">
        <div class="pedidos-recuperacao-section-head">
            <div>
                <span class="pedidos-recuperacao-section-kicker">Envio manual</span>
                <h2 class="pedidos-recuperacao-section-title">Recuperação em lote ou individual</h2>
            </div>
            <small class="muted">Mantém envio com cupom e confirmação para lembretes recentes.</small>
        </div>

        <form method="post" action="/admin/pedidos/recuperacao/enviar" class="pedidos-recuperacao-send-form">
            <?php echo $csrfField; ?>
            <div class="admin-filters__row pedidos-recuperacao-send-row">
                <label>Modelo padrão
                    <select name="modelo_chave">
                        <option value="pedido_recuperacao_primeiro_lembrete" <?php echo $modeloAtual === 'pedido_recuperacao_primeiro_lembrete' ? 'selected' : ''; ?>>1º lembrete</option>
                        <option value="pedido_recuperacao_segundo_lembrete" <?php echo $modeloAtual === 'pedido_recuperacao_segundo_lembrete' ? 'selected' : ''; ?>>2º lembrete</option>
                        <option value="pedido_recuperacao_terceiro_lembrete" <?php echo $modeloAtual === 'pedido_recuperacao_terceiro_lembrete' ? 'selected' : ''; ?>>3º lembrete</option>
                        <option value="pedido_recuperacao_ultimo_lembrete" <?php echo $modeloAtual === 'pedido_recuperacao_ultimo_lembrete' ? 'selected' : ''; ?>>Último lembrete</option>
                        <option value="pedido_recuperacao_quase_expirando" <?php echo $modeloAtual === 'pedido_recuperacao_quase_expirando' ? 'selected' : ''; ?>>Quase expirando</option>
                        <option value="pedido_recuperacao_com_cupom" <?php echo $modeloAtual === 'pedido_recuperacao_com_cupom' ? 'selected' : ''; ?>>Com cupom</option>
                    </select>
                </label>
                <label>Cupom manual
                    <input type="text" name="cupom_codigo" placeholder="Opcional">
                </label>
                <label class="checkbox pedidos-recuperacao-send-checkbox">
                    <input type="checkbox" name="confirmar_envio" value="1">
                    Confirmar envio se houver lembrete nas últimas 24 horas
                </label>
            </div>

            <div class="table-wrap pedidos-recuperacao-table-wrap">
                <table class="admin-table admin-table--pedidos-lista pedidos-recuperacao-table">
                    <thead>
                        <tr>
                            <th>Selecionar</th>
                            <th>Pedido</th>
                            <th>Aluno</th>
                            <th>E-mail</th>
                            <th>Curso</th>
                            <th>Total</th>
                            <th>Pendente</th>
                            <th>Status</th>
                            <th>Pagamento</th>
                            <th>Data</th>
                            <th>Dias</th>
                            <th>Último envio</th>
                            <th>Envios</th>
                            <th>Opt-out</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pedidos)): ?>
                            <tr>
                                <td colspan="15">Nenhum pedido incompleto encontrado.</td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ((array) $pedidos as $pedido): ?>
                            <?php
                            $pedidoStatusRaw = strtolower(trim((string) ($pedido['status'] ?? '')));
                            $pedidoStatusInfo = isset($statusMap[$pedidoStatusRaw]) ? $statusMap[$pedidoStatusRaw] : array(
                                'label' => $pedidoStatusRaw !== '' ? ucwords(str_replace('_', ' ', $pedidoStatusRaw)) : '-',
                                'tone' => 'neutral',
                            );
                            $gatewayRaw = strtolower(trim((string) ($pedido['payment_gateway'] ?? 'manual')));
                            $gatewayInfo = isset($gatewayMap[$gatewayRaw]) ? $gatewayMap[$gatewayRaw] : array(
                                'label' => $gatewayRaw !== '' ? ucwords(str_replace('_', ' ', $gatewayRaw)) : 'Manual',
                                'tone' => 'neutral',
                            );
                            $optoutAtivo = !empty($pedido['optout_ativo']);
                            ?>
                            <tr>
                                <td class="pedidos-recuperacao-checkbox-cell">
                                    <input type="checkbox" name="pedido_ids[]" value="<?php echo (int) $pedido['id']; ?>">
                                </td>
                                <td>
                                    <div class="pedidos-recuperacao-cell">
                                        <strong class="pedidos-recuperacao-code"><?php echo Helpers::e((string) $pedido['codigo']); ?></strong>
                                        <small class="pedidos-recuperacao-meta">#<?php echo (int) $pedido['id']; ?></small>
                                    </div>
                                </td>
                                <td>
                                    <div class="pedidos-recuperacao-cell">
                                        <strong><?php echo Helpers::e((string) ($pedido['aluno_nome'] ?? $pedido['pagador_nome'] ?? '-')); ?></strong>
                                        <small class="pedidos-recuperacao-meta"><?php echo Helpers::e((string) ($pedido['pagador_cpf'] ?? '-')); ?></small>
                                    </div>
                                </td>
                                <td>
                                    <div class="pedidos-recuperacao-cell">
                                        <strong><?php echo Helpers::e((string) ($pedido['aluno_email'] ?? $pedido['pagador_email'] ?? '-')); ?></strong>
                                    </div>
                                </td>
                                <td>
                                    <div class="pedidos-recuperacao-cell">
                                        <strong><?php echo Helpers::e((string) ($pedido['curso_nome'] ?? 'Curso não informado')); ?></strong>
                                        <small class="pedidos-recuperacao-meta"><?php echo !empty($pedido['curso_id']) ? '#' . (int) $pedido['curso_id'] : '-'; ?></small>
                                    </div>
                                </td>
                                <td>
                                    <div class="pedidos-recuperacao-cell">
                                        <strong><?php echo Helpers::e((string) ($pedido['valor_total_formatado'] ?? 'R$ 0,00')); ?></strong>
                                    </div>
                                </td>
                                <td>
                                    <div class="pedidos-recuperacao-cell">
                                        <strong class="pedidos-recuperacao-money--due"><?php echo Helpers::e((string) ($pedido['valor_pendente_formatado'] ?? 'R$ 0,00')); ?></strong>
                                        <small class="pedidos-recuperacao-meta">Total: <?php echo Helpers::e((string) ($pedido['valor_total_formatado'] ?? 'R$ 0,00')); ?></small>
                                    </div>
                                </td>
                                <td>
                                    <span class="pedidos-recuperacao-badge pedidos-recuperacao-badge--<?php echo Helpers::e((string) pedidosRecuperacaoBadgeTone($pedidoStatusInfo['tone'])); ?>">
                                        <?php echo Helpers::e((string) $pedidoStatusInfo['label']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="pedidos-recuperacao-cell">
                                        <span class="pedidos-recuperacao-badge pedidos-recuperacao-badge--<?php echo Helpers::e((string) pedidosRecuperacaoBadgeTone($gatewayInfo['tone'])); ?>">
                                            <?php echo Helpers::e((string) $gatewayInfo['label']); ?>
                                        </span>
                                        <?php if (!empty($pedido['payment_provider_status'])): ?>
                                            <small class="pedidos-recuperacao-meta"><?php echo Helpers::e((string) $pedido['payment_provider_status']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="pedidos-recuperacao-cell">
                                        <strong><?php echo Helpers::e((string) ($pedido['created_at'] ?? '-')); ?></strong>
                                    </div>
                                </td>
                                <td>
                                    <strong><?php echo (int) ($pedido['dias_desde_criacao'] ?? 0); ?></strong>
                                </td>
                                <td>
                                    <div class="pedidos-recuperacao-cell">
                                        <strong><?php echo !empty($pedido['ultimo_envio_em']) ? Helpers::e((string) $pedido['ultimo_envio_em']) : '-'; ?></strong>
                                    </div>
                                </td>
                                <td>
                                    <div class="pedidos-recuperacao-cell">
                                        <strong><?php echo (int) ($pedido['total_envios'] ?? 0); ?></strong>
                                        <small class="pedidos-recuperacao-meta">M: <?php echo (int) ($pedido['envios_manuais'] ?? 0); ?> · A: <?php echo (int) ($pedido['envios_automaticos'] ?? 0); ?></small>
                                    </div>
                                </td>
                                <td>
                                    <span class="pedidos-recuperacao-badge pedidos-recuperacao-badge--<?php echo $optoutAtivo ? 'danger' : 'success'; ?>">
                                        <?php echo $optoutAtivo ? 'Sim' : 'Não'; ?>
                                    </span>
                                </td>
                                <td>
                                    <button type="submit" name="single_pedido_id" value="<?php echo (int) $pedido['id']; ?>" class="button-link button-link--primary">Enviar recuperação</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="cta-group pedidos-recuperacao-bulk-actions">
                <button type="submit" class="button-link button-link--primary">Enviar selecionados</button>
                <a class="button-link button-link--ghost" href="/admin/emails/modelos">Editar modelos</a>
            </div>
        </form>
    </section>

    <?php if ($pages > 1): ?>
        <section class="status-card admin-pedidos-card admin-pedidos-card--pagination pedidos-recuperacao-pagination">
            <div class="cta-group admin-pedidos__pagination-row pedidos-recuperacao-pagination__row">
                <small>Página <?php echo (int) $page; ?> de <?php echo (int) $pages; ?> — mostrando <?php echo (int) $from; ?> a <?php echo (int) $to; ?> de <?php echo (int) $total; ?> pedidos.</small>
                <div class="cta-group">
                    <?php if ($page > 1): ?>
                        <a class="button-link button-link--ghost" href="<?php echo Helpers::e('/admin/pedidos/recuperacao?' . http_build_query(array_merge($filters, array('page' => $page - 1)))); ?>">Anterior</a>
                    <?php endif; ?>
                    <?php if ($page < $pages): ?>
                        <a class="button-link button-link--ghost" href="<?php echo Helpers::e('/admin/pedidos/recuperacao?' . http_build_query(array_merge($filters, array('page' => $page + 1)))); ?>">Próxima</a>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>
</section>
