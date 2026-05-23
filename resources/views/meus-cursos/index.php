<?php use App\Core\Helpers; ?>
<?php
$nomeUsuarioPagina = trim((string) $usuarioNome);
if ($nomeUsuarioPagina === '') {
    $nomeUsuarioPagina = 'usuário';
}
$pedidosPendentes = isset($pedidosPendentes) && is_array($pedidosPendentes) ? $pedidosPendentes : array();
$avisos = isset($avisos) && is_array($avisos) ? $avisos : array();
$totalPedidosPendentes = count($pedidosPendentes);
?>

<section class="page-header dashboard-header">
    <h1>Página de <?php echo Helpers::e($nomeUsuarioPagina); ?></h1>
</section>

<?php
$pedidoCanceladoFeedback = isset($pedidoCanceladoFeedback) && is_array($pedidoCanceladoFeedback) ? $pedidoCanceladoFeedback : null;
$pedidoCanceladoTitle = $pedidoCanceladoFeedback && !empty($pedidoCanceladoFeedback['title']) ? (string) $pedidoCanceladoFeedback['title'] : '';
$pedidoCanceladoDesc = $pedidoCanceladoFeedback && !empty($pedidoCanceladoFeedback['description']) ? (string) $pedidoCanceladoFeedback['description'] : '';
?>

<?php if ($pedidoCanceladoTitle !== ''): ?>
    <div class="student-feedback-card student-feedback-card--success" role="status" aria-live="polite">
        <div class="student-feedback-card__icon" aria-hidden="true">✓</div>
        <div class="student-feedback-card__content">
            <strong><?php echo Helpers::e($pedidoCanceladoTitle); ?></strong>
            <?php if ($pedidoCanceladoDesc !== ''): ?>
                <p><?php echo Helpers::e($pedidoCanceladoDesc); ?></p>
            <?php endif; ?>
        </div>
    </div>
<?php elseif (is_array($success) && !empty($success['message'])): ?>
    <div class="student-feedback-card student-feedback-card--success" role="status" aria-live="polite">
        <div class="student-feedback-card__icon" aria-hidden="true">✓</div>
        <div class="student-feedback-card__content">
            <strong><?php echo Helpers::e($success['message']); ?></strong>
            <?php if (!empty($success['link']['href']) && !empty($success['link']['label'])): ?>
                <p><a class="card-link" href="<?php echo Helpers::e($success['link']['href']); ?>"><?php echo Helpers::e($success['link']['label']); ?></a></p>
            <?php endif; ?>
        </div>
    </div>
<?php elseif (!empty($success)): ?>
    <section class="auth-message auth-message-success">
        <p><?php echo Helpers::e($success); ?></p>
    </section>
<?php endif; ?>

<?php
function acaoPedidoPendente(array $pedido)
{
    $status = isset($pedido['status']) ? (string) $pedido['status'] : '';
    $pedidoId = isset($pedido['id']) ? (int) $pedido['id'] : 0;

    if ($status === 'rascunho') {
        return array(
            'label' => 'Continuar inscrição',
            'href' => '/checkout/participantes?pedido_id=' . $pedidoId,
        );
    }

    if (in_array($status, array('aguardando_pagamento', 'aguardando_reenvio', 'pendencia'), true)) {
        return array(
            'label' => 'Enviar comprovante',
            'href' => '/checkout/comprovante?pedido_id=' . $pedidoId,
        );
    }

    return array(
        'label' => 'Acompanhar',
        'href' => '/checkout/resumo?pedido_id=' . $pedidoId,
    );
}
?>

<section class="meus-cursos-layout dashboard-layout">
    <aside class="meus-cursos-side dashboard-side">
        <article class="status-card dashboard-card dashboard-card--side">
            <header class="avisos-feed__header">
                <h2>Avisos</h2>
                <p>Mensagens importantes para sua conta, pedidos e cursos.</p>
            </header>

            <?php if ($totalPedidosPendentes > 0): ?>
                <p class="dashboard-course-card__text" style="margin-bottom:12px;">
                    Você possui <?php echo (int) $totalPedidosPendentes; ?> pedido<?php echo $totalPedidosPendentes === 1 ? '' : 's'; ?> pendente<?php echo $totalPedidosPendentes === 1 ? '' : 's'; ?>.
                </p>
                <div class="pedidos-pendentes-lista">
                    <?php foreach ($pedidosPendentes as $pedidoPendente): ?>
                        <?php $acao = acaoPedidoPendente($pedidoPendente); ?>
                        <article class="pedido-pendente-item dashboard-pedido-card">
                            <strong><?php echo Helpers::e(isset($pedidoPendente['curso_nome']) && $pedidoPendente['curso_nome'] !== '' ? $pedidoPendente['curso_nome'] : 'Curso não identificado'); ?></strong>
                            <span><?php echo Helpers::e(isset($pedidoPendente['turma_nome']) && $pedidoPendente['turma_nome'] !== '' ? $pedidoPendente['turma_nome'] : 'Turma a definir'); ?></span>
                            <span>Pedido <?php echo Helpers::e($pedidoPendente['codigo']); ?> · <?php echo Helpers::e($pedidoPendente['status']); ?></span>
                            <div class="cta-group" style="margin-top:8px;flex-wrap:wrap;">
                                <a class="button-link" href="<?php echo Helpers::e($acao['href']); ?>"><?php echo Helpers::e($acao['label']); ?></a>
                                <button type="button" class="button-link button-link--ghost js-cancelar-pedido" data-pedido-id="<?php echo (int) $pedidoPendente['id']; ?>">
                                    Cancelar pedido
                                </button>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($avisos)): ?>
                <div class="avisos-list" style="margin-top:12px;">
                    <?php foreach ($avisos as $aviso): ?>
                        <article class="aviso-card<?php echo !empty($aviso['destaque']) ? ' aviso-card--destaque' : ''; ?>">
                            <div class="aviso-card__top">
                                <div>
                                    <strong><?php echo Helpers::e(!empty($aviso['titulo']) ? $aviso['titulo'] : 'Aviso'); ?></strong>
                                    <?php if (!empty($aviso['mostrar_inicio']) || !empty($aviso['mostrar_fim'])): ?>
                                        <small>
                                            <?php if (!empty($aviso['mostrar_inicio'])): ?>
                                                Início <?php echo Helpers::e(date('d/m/Y H:i', strtotime((string) $aviso['mostrar_inicio']))); ?>
                                            <?php endif; ?>
                                            <?php if (!empty($aviso['mostrar_fim'])): ?>
                                                <?php echo !empty($aviso['mostrar_inicio']) ? ' · ' : ''; ?>
                                                Fim <?php echo Helpers::e(date('d/m/Y H:i', strtotime((string) $aviso['mostrar_fim']))); ?>
                                            <?php endif; ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($aviso['destaque'])): ?>
                                    <span class="pill pill--alert">Destaque</span>
                                <?php endif; ?>
                            </div>
                            <p class="aviso-card__message"><?php echo nl2br(Helpers::e($aviso['mensagem'])); ?></p>
                            <div class="aviso-card__actions">
                                <?php if (!empty($aviso['link_url'])): ?>
                                    <a class="button-link" href="<?php echo Helpers::e($aviso['link_url']); ?>" target="_blank" rel="noopener noreferrer">
                                        <?php echo Helpers::e(!empty($aviso['link_rotulo']) ? $aviso['link_rotulo'] : 'Abrir aviso'); ?>
                                    </a>
                                <?php endif; ?>
                                <?php if (!empty($aviso['permitir_ocultar'])): ?>
                                    <form method="post" action="/avisos/ocultar" class="admin-form" style="display:inline;">
                                        <?php echo $csrfField; ?>
                                        <input type="hidden" name="aviso_id" value="<?php echo (int) $aviso['id']; ?>">
                                        <input type="hidden" name="redirect_to" value="/meus-cursos">
                                        <button type="submit">Ocultar</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($totalPedidosPendentes <= 0 && empty($avisos)): ?>
                <span>Nenhum aviso no momento.</span>
            <?php endif; ?>
        </article>

        <form id="cancelar-pedido-form" method="post" action="/aluno/meus-cursos/pedidos/cancelar" style="display:none;">
            <input type="hidden" name="pedido_id" id="cancelar-pedido-id" value="">
            <input type="hidden" name="motivo_cancelamento" id="cancelar-pedido-motivo" value="">
        </form>
    </aside>

    <div class="meus-cursos-main dashboard-main">
        <section class="card-grid">
            <?php if (empty($inscricoes)): ?>
                <article class="status-card dashboard-card">
                    <strong>Você ainda não possui cursos disponíveis.</strong>
                    <span>Quando sua inscrição for aprovada, o acesso à sala virtual aparecerá aqui.</span>
                    <p style="margin-top: 12px;">
                        <a class="button-link" href="/cursos">Ver cursos disponíveis</a>
                    </p>
                </article>
            <?php else: ?>
                <?php foreach ($inscricoes as $inscricao): ?>
                    <?php
                    $statusInscricao = isset($inscricao['status']) ? (string) $inscricao['status'] : '';
                    $statusPedido = isset($inscricao['pedido_status']) ? (string) $inscricao['pedido_status'] : '';
                    $statusComprovante = isset($inscricao['comprovante_status']) ? (string) $inscricao['comprovante_status'] : '';
                    $progressoValor = isset($inscricao['percentual_progresso']) ? (float) $inscricao['percentual_progresso'] : null;
                    $cursoModalidade = !empty($inscricao['curso_modalidade']) ? (string) $inscricao['curso_modalidade'] : '';

                    $statusPrincipal = $statusInscricao !== '' ? $statusInscricao : $statusPedido;
                    if (in_array($statusPedido, array('aprovado', 'pago'), true)) {
                        $statusPrincipal = $statusPedido;
                    }
                    $mostrarStatusPedido = $statusPedido !== '' && $statusPedido !== $statusPrincipal;
                    $mostrarComprovante = $statusComprovante !== '' && !in_array($statusPedido, array('aprovado', 'pago'), true);
                    $classeStatusPrincipal = in_array($statusPrincipal, array('aprovado', 'ativa', 'concluida', 'concluida_sem_certificado', 'certificado_emitido', 'pago'), true) ? ' pill--success' : '';
                    $classeStatusPedido = in_array($statusPedido, array('aprovado', 'pago'), true) ? ' pill--success' : '';
                    $statusComAcesso = in_array($statusInscricao, array('ativa', 'em_andamento', 'concluida', 'concluida_sem_certificado', 'certificado_emitido'), true);
                    $podeAcessarAreaInterna = $statusComAcesso;
                    $progressoTexto = null;
                    if ($progressoValor !== null) {
                        $progressoTexto = $progressoValor <= 0 ? 'Não iniciado' : number_format($progressoValor, 2, ',', '.') . '%';
                    }
                    $salaHref = '/aluno/cursos?inscricao_id=' . (int) $inscricao['id'] . '&curso_id=' . (int) $inscricao['curso_evento_id'] . '&turma_id=' . (int) $inscricao['turma_id'];
                    ?>
                    <article class="course-card dashboard-course-card">
                        <div class="course-card__media">
                            <strong><?php echo Helpers::e($inscricao['curso_nome']); ?></strong>
                            <span><?php echo Helpers::e($inscricao['turma_nome']); ?></span>
                        </div>
                        <div class="course-card__body">
                            <div class="pill-row">
                                <span class="pill<?php echo $classeStatusPrincipal; ?>"><?php echo Helpers::e($statusPrincipal); ?></span>
                                <?php if ($mostrarStatusPedido): ?>
                                    <span class="pill<?php echo $classeStatusPedido; ?>"><?php echo Helpers::e($statusPedido); ?></span>
                                <?php endif; ?>
                                <?php if ($progressoTexto !== null): ?>
                                    <span class="pill"><?php echo Helpers::e($progressoTexto); ?></span>
                                <?php endif; ?>
                                <?php if ($mostrarComprovante): ?>
                                    <span class="pill pill--alert"><?php echo Helpers::e($statusComprovante); ?></span>
                                <?php endif; ?>
                                <?php if ($cursoModalidade !== ''): ?>
                                    <span class="pill"><?php echo Helpers::e($cursoModalidade); ?></span>
                                <?php endif; ?>
                            </div>
                            <p class="dashboard-course-card__text"><?php echo Helpers::e($inscricao['participante_nome']); ?> - <?php echo Helpers::e($inscricao['participante_cpf']); ?></p>
                            <div class="course-card__meta">
                                <span>Pedido <?php echo Helpers::e($inscricao['pedido_codigo']); ?></span>
                                <span><?php echo Helpers::e($cursoModalidade !== '' ? ucfirst($cursoModalidade) : 'Curso'); ?></span>
                            </div>
                            <?php if ($podeAcessarAreaInterna): ?>
                                <div class="pill-row" style="margin-top:12px;">
                                    <a class="button-link" href="<?php echo Helpers::e($salaHref); ?>">Acessar sala virtual</a>
                                </div>
                            <?php else: ?>
                                <div class="pill-row" style="margin-top:12px;">
                                    <span class="pill">Sala virtual disponível após a liberação da inscrição.</span>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($inscricao['certificado_codigo'])): ?>
                                <div class="pill-row" style="margin-top:12px;">
                                    <a class="pill" href="/certificados/show?codigo=<?php echo urlencode($inscricao['certificado_codigo']); ?>">Certificado online</a>
                                    <a class="pill" href="/certificados/validar?codigo=<?php echo urlencode($inscricao['certificado_codigo']); ?>">Validar</a>
                                    <a class="pill" href="/certificados/pdf?codigo=<?php echo urlencode($inscricao['certificado_codigo']); ?>">PDF</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
        <div class="cta-group" style="margin-top:16px;">
            <a class="button-link" href="/cursos">Outros Cursos</a>
        </div>
    </div>
</section>

<script>
(function () {
    var botoes = document.querySelectorAll('.js-cancelar-pedido');
    var form = document.getElementById('cancelar-pedido-form');
    var pedidoIdInput = document.getElementById('cancelar-pedido-id');
    var motivoInput = document.getElementById('cancelar-pedido-motivo');

    if (!botoes.length || !form || !pedidoIdInput || !motivoInput) {
        return;
    }

    function solicitarMotivo() {
        var motivo = window.prompt('Informe o motivo do cancelamento:');
        if (motivo === null) {
            return null;
        }

        var texto = String(motivo || '').trim();
        if (!texto) {
            window.alert('Você precisa informar o motivo do cancelamento.');
            return null;
        }

        return texto;
    }

    for (var i = 0; i < botoes.length; i++) {
        botoes[i].addEventListener('click', function () {
            var pedidoId = this.getAttribute('data-pedido-id');
            if (!pedidoId) {
                return;
            }

            if (!window.confirm('Confirma o cancelamento deste pedido?')) {
                return;
            }

            var motivo = solicitarMotivo();
            if (motivo === null) {
                return;
            }

            pedidoIdInput.value = pedidoId;
            motivoInput.value = motivo;
            form.submit();
        });
    }
})();
</script>
