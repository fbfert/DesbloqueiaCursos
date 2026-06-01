<?php
$totalApuracoes = is_array($apuracoes) ? count($apuracoes) : 0;
$totalPerfisFiscais = is_array($professores_fiscal) ? count($professores_fiscal) : 0;
$filtrosEntradas = isset($filtros_entradas) && is_array($filtros_entradas) ? $filtros_entradas : array();
$financeiroOptions = isset($financeiro_options) && is_array($financeiro_options) ? $financeiro_options : array();
$entradasResumo = isset($entradas_resumo) && is_array($entradas_resumo) ? $entradas_resumo : array();
$entradasMensais = isset($entradas_mensais) && is_array($entradas_mensais) ? $entradas_mensais : array();
$entradasPorCurso = isset($entradas_por_curso) && is_array($entradas_por_curso) ? $entradas_por_curso : array();
$entradasPedidos = isset($entradas_pedidos) && is_array($entradas_pedidos) ? $entradas_pedidos : array();

$e = function ($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};

$filtroAnoSelecionado = isset($filtrosEntradas['ano']) && preg_match('/^[0-9]{4}$/', (string) $filtrosEntradas['ano'])
    ? (string) $filtrosEntradas['ano']
    : date('Y');
$filtroMesSelecionado = isset($filtrosEntradas['mes']) ? (string) $filtrosEntradas['mes'] : '';
$filtroCursoSelecionado = isset($filtrosEntradas['curso_evento_id']) ? (int) $filtrosEntradas['curso_evento_id'] : 0;
$filtroTurmaSelecionado = isset($filtrosEntradas['turma_id']) ? (int) $filtrosEntradas['turma_id'] : 0;
$filtroCategoriaSelecionada = isset($filtrosEntradas['categoria_id']) ? (int) $filtrosEntradas['categoria_id'] : 0;
$periodoLabel = isset($entradasResumo['periodo']) ? (string) $entradasResumo['periodo'] : 'Período selecionado';
$entradasConfirmadas = isset($entradasResumo['total']) ? (float) $entradasResumo['total'] : 0.00;
$pedidosConfirmados = isset($entradasResumo['total_pedidos']) ? (int) $entradasResumo['total_pedidos'] : 0;
$ticketMedio = isset($entradasResumo['ticket_medio']) ? (float) $entradasResumo['ticket_medio'] : 0.00;
$descontosConcedidos = isset($entradasResumo['desconto_total']) ? (float) $entradasResumo['desconto_total'] : 0.00;
$repassesCalculados = isset($entradasResumo['repasses_calculados']) ? (float) $entradasResumo['repasses_calculados'] : 0.00;
$liquidoEstimado = isset($entradasResumo['liquido_estimado']) ? (float) $entradasResumo['liquido_estimado'] : 0.00;
$pendenciasFinanceiras = (int) ($entradasResumo['pedidos_pendentes_confirmacao'] ?? 0) + (int) ($entradasResumo['comprovantes_pix_analise'] ?? 0);
$comprovantesEmAnalise = (int) ($entradasResumo['comprovantes_pix_analise'] ?? 0);
$apuracoesAbertas = 0;

foreach ((array) $apuracoes as $apuracaoResumo) {
    if (isset($apuracaoResumo['status']) && (string) $apuracaoResumo['status'] !== 'fechada') {
        $apuracoesAbertas++;
    }
}

$queryExportar = array('ano' => $filtroAnoSelecionado);
if ($filtroMesSelecionado !== '') {
    $queryExportar['mes'] = $filtroMesSelecionado;
}
if ($filtroCursoSelecionado > 0) {
    $queryExportar['curso_evento_id'] = $filtroCursoSelecionado;
}
if ($filtroTurmaSelecionado > 0) {
    $queryExportar['turma_id'] = $filtroTurmaSelecionado;
}
if ($filtroCategoriaSelecionada > 0) {
    $queryExportar['categoria_id'] = $filtroCategoriaSelecionada;
}
$exportUrl = '/admin/financeiro/entradas/exportar?' . http_build_query($queryExportar);

$mesesOptions = isset($financeiroOptions['meses']) && is_array($financeiroOptions['meses']) ? $financeiroOptions['meses'] : array();
$anosOptions = isset($financeiroOptions['anos']) && is_array($financeiroOptions['anos']) ? $financeiroOptions['anos'] : array((int) date('Y'));
$cursosOptions = isset($financeiroOptions['cursos']) && is_array($financeiroOptions['cursos']) ? $financeiroOptions['cursos'] : array();
$turmasOptions = isset($financeiroOptions['turmas']) && is_array($financeiroOptions['turmas']) ? $financeiroOptions['turmas'] : array();
$categoriasOptions = isset($financeiroOptions['categorias']) && is_array($financeiroOptions['categorias']) ? $financeiroOptions['categorias'] : array();
?>

<div class="admin-page">
    <section class="admin-page__header admin-page__header--with-metrics">
        <div class="admin-page__header-content">
            <h1 class="admin-page__title">Financeiro</h1>
            <p class="admin-page__subtitle">Apurações mensais, repasses, perfil fiscal dos professores e entradas confirmadas por período.</p>
        </div>
        <div class="admin-page__metrics">
            <article class="admin-page__metric">
                <span class="admin-page__metric-label">Entradas confirmadas</span>
                <strong class="admin-page__metric-value">R$ <?php echo number_format($entradasConfirmadas, 2, ',', '.'); ?></strong>
                <small class="admin-page__metric-help"><?php echo $e($periodoLabel); ?></small>
            </article>
            <article class="admin-page__metric">
                <span class="admin-page__metric-label">Pedidos confirmados</span>
                <strong class="admin-page__metric-value"><?php echo (int) $pedidosConfirmados; ?></strong>
                <small class="admin-page__metric-help">pagos ou aprovados</small>
            </article>
            <article class="admin-page__metric">
                <span class="admin-page__metric-label">Ticket médio</span>
                <strong class="admin-page__metric-value">R$ <?php echo number_format($ticketMedio, 2, ',', '.'); ?></strong>
                <small class="admin-page__metric-help">receita por pedido</small>
            </article>
            <article class="admin-page__metric">
                <span class="admin-page__metric-label">Descontos concedidos</span>
                <strong class="admin-page__metric-value">R$ <?php echo number_format($descontosConcedidos, 2, ',', '.'); ?></strong>
                <small class="admin-page__metric-help">cupom e ajustes</small>
            </article>
            <article class="admin-page__metric">
                <span class="admin-page__metric-label">Repasses calculados</span>
                <strong class="admin-page__metric-value">R$ <?php echo number_format($repassesCalculados, 2, ',', '.'); ?></strong>
                <small class="admin-page__metric-help">valor estimado</small>
            </article>
            <article class="admin-page__metric">
                <span class="admin-page__metric-label">Líquido estimado</span>
                <strong class="admin-page__metric-value">R$ <?php echo number_format($liquidoEstimado, 2, ',', '.'); ?></strong>
                <small class="admin-page__metric-help">entradas menos repasses</small>
            </article>
            <article class="admin-page__metric">
                <span class="admin-page__metric-label">Pendências financeiras</span>
                <strong class="admin-page__metric-value"><?php echo (int) $pendenciasFinanceiras; ?></strong>
                <small class="admin-page__metric-help">pedidos e comprovantes</small>
            </article>
            <article class="admin-page__metric">
                <span class="admin-page__metric-label">Comprovantes em análise</span>
                <strong class="admin-page__metric-value"><?php echo (int) $comprovantesEmAnalise; ?></strong>
                <small class="admin-page__metric-help">fila do financeiro</small>
            </article>
        </div>
    </section>

    <section class="admin-section">
        <div class="admin-section__header">
            <h2 class="admin-section__title">Atalhos financeiros</h2>
        </div>
        <div class="quick-actions quick-actions--dashboard">
            <a class="card-link admin-shortcut" href="/admin/pedidos"><span>Pedidos</span><small>Entradas, cancelamentos e comprovantes</small></a>
            <a class="card-link admin-shortcut" href="/admin/comprovantes-pix"><span>Comprovantes PIX</span><small>Fila de análise e aprovação</small></a>
            <a class="card-link admin-shortcut" href="/admin/financeiro/repasses"><span>Repasses</span><small>Geração e pagamento por competência</small></a>
            <a class="card-link admin-shortcut" href="/admin/configuracoes-globais/financeiro"><span>Configurações financeiras</span><small>Corte e percentual de rateio</small></a>
            <a class="card-link admin-shortcut" href="/admin/professores-fiscais"><span>Professores fiscais</span><small>Gestão detalhada de perfis</small></a>
            <a class="card-link admin-shortcut" href="/admin/rateios"><span>Rateios</span><small>Acompanhamento por curso e turma</small></a>
            <a class="card-link admin-shortcut" href="<?php echo $e($exportUrl); ?>"><span>Exportar entradas CSV</span><small>Baixar levantamento filtrado</small></a>
            <a class="card-link admin-shortcut" href="/admin/dashboard"><span>Dashboard</span><small>Voltar ao painel executivo</small></a>
        </div>
    </section>

    <?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
    <?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

    <section class="admin-section">
        <div class="admin-section__header">
            <h2 class="admin-section__title">Filtros de entradas</h2>
        </div>
        <form method="get" action="/admin/financeiro" class="form-grid">
            <label>
                Ano
                <select name="ano" required>
                    <?php foreach ($anosOptions as $anoOption): ?>
                        <option value="<?php echo (int) $anoOption; ?>" <?php echo (string) $anoOption === (string) $filtroAnoSelecionado ? 'selected' : ''; ?>>
                            <?php echo (int) $anoOption; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                Mês
                <select name="mes">
                    <option value="">Todos os meses</option>
                    <?php foreach ($mesesOptions as $valor => $rotulo): ?>
                        <option value="<?php echo $e($valor); ?>" <?php echo $valor === $filtroMesSelecionado ? 'selected' : ''; ?>>
                            <?php echo $e($rotulo); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                Curso / evento
                <select name="curso_evento_id">
                    <option value="">Todos os cursos</option>
                    <?php foreach ($cursosOptions as $curso): ?>
                        <option value="<?php echo (int) $curso['id']; ?>" <?php echo (int) $curso['id'] === $filtroCursoSelecionado ? 'selected' : ''; ?>>
                            <?php echo $e($curso['nome']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                Turma
                <select name="turma_id">
                    <option value="">Todas as turmas</option>
                    <?php foreach ($turmasOptions as $turma): ?>
                        <option value="<?php echo (int) $turma['id']; ?>" <?php echo (int) $turma['id'] === $filtroTurmaSelecionado ? 'selected' : ''; ?>>
                            <?php echo $e($turma['nome'] . (!empty($turma['curso_nome']) ? ' - ' . $turma['curso_nome'] : '')); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                Categoria
                <select name="categoria_id">
                    <option value="">Todas as categorias</option>
                    <?php foreach ($categoriasOptions as $categoria): ?>
                        <option value="<?php echo (int) $categoria['id']; ?>" <?php echo (int) $categoria['id'] === $filtroCategoriaSelecionada ? 'selected' : ''; ?>>
                            <?php echo $e($categoria['nome']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <div class="full cta-group">
                <button type="submit" class="button-link button-link--primary">Aplicar filtros</button>
                <a class="button-link button-link--ghost" href="/admin/financeiro">Limpar filtros</a>
                <a class="button-link button-link--ghost" href="<?php echo $e($exportUrl); ?>">Exportar CSV</a>
            </div>
        </form>
        <p class="muted" style="margin-top: 12px;">Entradas confirmadas consideram pedidos aprovados ou pagos, excluindo presentes e pedidos de valor zero.</p>
    </section>

    <section class="admin-section">
        <div class="admin-section__header">
            <h2 class="admin-section__title">Entradas confirmadas por mês</h2>
        </div>
        <div class="table-wrap">
            <table class="admin-table admin-table--financeiro-apuracoes">
                <thead>
                    <tr>
                        <th>Competência</th>
                        <th>Pedidos confirmados</th>
                        <th>Receita bruta</th>
                        <th>Descontos</th>
                        <th>Acréscimos</th>
                        <th>Receita confirmada</th>
                        <th>Ticket médio</th>
                        <th>Repasses</th>
                        <th>Líquido estimado</th>
                        <th>Situação da apuração</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($entradasMensais)): ?>
                        <tr>
                            <td colspan="11">Nenhuma entrada confirmada encontrada para os filtros aplicados.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($entradasMensais as $linhaMensal): ?>
                        <?php
                        $situacao = (string) $linhaMensal['situacao_apuracao'];
                        $badgeClasse = 'badge badge--status badge--status-pendente';
                        if (stripos($situacao, 'fechada') !== false || stripos($situacao, 'pagos') !== false) {
                            $badgeClasse = 'badge badge--status badge--status-aprovado';
                        } elseif (stripos($situacao, 'sem apuração') !== false) {
                            $badgeClasse = 'badge badge--status badge--status-reprovado';
                        }
                        ?>
                        <tr <?php echo !empty($linhaMensal['is_mes_atual']) ? 'style="font-weight: 600;"' : ''; ?>>
                            <td>
                                <?php echo $e($linhaMensal['competencia_label']); ?>
                                <?php if (!empty($linhaMensal['is_mes_atual'])): ?>
                                    <br><span class="badge badge--status badge--status-aprovado">Mês atual</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo (int) $linhaMensal['total_pedidos']; ?></td>
                            <td>R$ <?php echo number_format((float) $linhaMensal['subtotal'], 2, ',', '.'); ?></td>
                            <td>R$ <?php echo number_format((float) $linhaMensal['desconto_total'], 2, ',', '.'); ?></td>
                            <td>R$ <?php echo number_format((float) $linhaMensal['acrescimo_total'], 2, ',', '.'); ?></td>
                            <td>R$ <?php echo number_format((float) $linhaMensal['total'], 2, ',', '.'); ?></td>
                            <td>R$ <?php echo number_format((float) $linhaMensal['ticket_medio'], 2, ',', '.'); ?></td>
                            <td>R$ <?php echo number_format((float) $linhaMensal['repasses_calculados'], 2, ',', '.'); ?></td>
                            <td>R$ <?php echo number_format((float) $linhaMensal['liquido_estimado'], 2, ',', '.'); ?></td>
                            <td>
                                <span class="<?php echo $badgeClasse; ?>"><?php echo $e($situacao); ?></span>
                                <?php if (!empty($linhaMensal['repasses_situacao']) && $linhaMensal['repasses_situacao'] !== 'Sem repasses'): ?>
                                    <br><small class="muted"><?php echo $e($linhaMensal['repasses_situacao']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="grid-actions-inline">
                                    <a class="button-link button-link--ghost" href="/admin/pedidos?de=<?php echo $e($linhaMensal['data_inicio']); ?>&ate=<?php echo $e($linhaMensal['data_fim']); ?>">Ver pedidos do mês</a>
                                    <?php if (!empty($linhaMensal['apuracao_id'])): ?>
                                        <a class="button-link button-link--ghost" href="/admin/financeiro/repasses?apuracao_id=<?php echo (int) $linhaMensal['apuracao_id']; ?>">Ver apuração</a>
                                    <?php else: ?>
                                        <form method="post" action="/admin/financeiro/apurar" class="admin-form" style="margin: 0;">
                                            <input type="hidden" name="competencia" value="<?php echo $e($linhaMensal['competencia']); ?>">
                                            <button type="submit" class="button-link button-link--primary">Apurar competência</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="admin-section">
        <div class="admin-section__header">
            <h2 class="admin-section__title">Entradas por curso/turma</h2>
        </div>
        <div class="table-wrap">
            <table class="admin-table admin-table--financeiro-apuracoes">
                <thead>
                    <tr>
                        <th>Curso</th>
                        <th>Turma</th>
                        <th>Pedidos</th>
                        <th>Itens vendidos</th>
                        <th>Receita proporcional</th>
                        <th>Desconto proporcional</th>
                        <th>Ticket médio</th>
                        <th>Percentual sobre o total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($entradasPorCurso)): ?>
                        <tr>
                            <td colspan="8">Nenhuma entrada por curso ou turma encontrada para os filtros aplicados.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($entradasPorCurso as $linhaCurso): ?>
                        <tr>
                            <td><?php echo $e($linhaCurso['curso_nome']); ?></td>
                            <td><?php echo $e(!empty($linhaCurso['turma_nome']) ? $linhaCurso['turma_nome'] : '-'); ?></td>
                            <td><?php echo (int) $linhaCurso['total_pedidos']; ?></td>
                            <td><?php echo (int) $linhaCurso['itens_vendidos']; ?></td>
                            <td>R$ <?php echo number_format((float) $linhaCurso['receita_proporcional'], 2, ',', '.'); ?></td>
                            <td>R$ <?php echo number_format((float) $linhaCurso['desconto_proporcional'], 2, ',', '.'); ?></td>
                            <td>R$ <?php echo number_format((float) $linhaCurso['ticket_medio'], 2, ',', '.'); ?></td>
                            <td><?php echo number_format((float) $linhaCurso['percentual_periodo'], 2, ',', '.'); ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="admin-section">
        <div class="admin-section__header">
            <h2 class="admin-section__title">Últimos pedidos confirmados</h2>
        </div>
        <div class="table-wrap">
            <table class="admin-table admin-table--financeiro-apuracoes">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Pagador</th>
                        <th>E-mail</th>
                        <th>Status</th>
                        <th>Data de confirmação</th>
                        <th>Origem da confirmação</th>
                        <th>Total</th>
                        <th>Cursos</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($entradasPedidos)): ?>
                        <tr>
                            <td colspan="8">Nenhum pedido confirmado encontrado para os filtros aplicados.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($entradasPedidos as $pedidoConfirmado): ?>
                        <tr>
                            <td><a href="/admin/pedidos/show?id=<?php echo (int) $pedidoConfirmado['id']; ?>"><?php echo $e($pedidoConfirmado['codigo']); ?></a></td>
                            <td><?php echo $e($pedidoConfirmado['pagador_nome']); ?></td>
                            <td><?php echo $e($pedidoConfirmado['pagador_email']); ?></td>
                            <td><span class="badge badge--status badge--status-aprovado"><?php echo $e($pedidoConfirmado['status']); ?></span></td>
                            <td><?php echo $e($pedidoConfirmado['data_confirmacao']); ?></td>
                            <td><?php echo $e($pedidoConfirmado['origem_confirmacao']); ?></td>
                            <td>R$ <?php echo number_format((float) $pedidoConfirmado['total'], 2, ',', '.'); ?></td>
                            <td><?php echo $e(!empty($pedidoConfirmado['cursos_nome']) ? $pedidoConfirmado['cursos_nome'] : '-'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="grid-2">
        <article class="status-card">
            <strong>Parâmetro atual</strong>
            <p>Rateio máximo: <?php echo number_format((float) $configuracao_financeira['percentual_rateio_maximo'], 2, ',', '.'); ?>%</p>
            <p>Fechamento por competência: <?php echo $e($configuracao_financeira['data_corte_financeiro']); ?></p>
            <p><?php echo $e($configuracao_financeira['observacao_repasse']); ?></p>
        </article>

        <article class="status-card">
            <strong>Nova apuração</strong>
            <form method="post" action="/admin/financeiro/apurar" class="form-grid">
                <label>
                    Competência
                    <input type="month" name="competencia" required value="<?php echo $e($filtroAnoSelecionado . '-' . ($filtroMesSelecionado !== '' ? $filtroMesSelecionado : date('m'))); ?>">
                </label>
                <div class="full cta-group">
                    <button type="submit" class="button-link button-link--primary">Apurar</button>
                    <a class="button-link button-link--ghost" href="/admin/financeiro">Cancelar</a>
                </div>
            </form>
        </article>
    </section>

    <section class="admin-section">
        <div class="admin-section__header">
            <h2 class="admin-section__title">Apurações</h2>
        </div>
        <div class="table-wrap">
            <table class="admin-table admin-table--financeiro-apuracoes">
                <thead>
                    <tr>
                        <th>Competência</th>
                        <th>Base bruta</th>
                        <th>Líquida</th>
                        <th>Rateio</th>
                        <th>Retido</th>
                        <th>Status</th>
                        <th>Fechada em</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($apuracoes)): ?>
                        <tr>
                            <td colspan="7">Nenhuma apuração encontrada.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($apuracoes as $apuracao): ?>
                        <tr>
                            <td><?php echo $e($apuracao['competencia']); ?></td>
                            <td>R$ <?php echo number_format((float) $apuracao['base_bruta'], 2, ',', '.'); ?></td>
                            <td>R$ <?php echo number_format((float) $apuracao['base_liquida'], 2, ',', '.'); ?></td>
                            <td>R$ <?php echo number_format((float) $apuracao['valor_rateio_total'], 2, ',', '.'); ?></td>
                            <td>R$ <?php echo number_format((float) $apuracao['valor_retenido_total'], 2, ',', '.'); ?></td>
                            <td><?php echo $e($apuracao['status']); ?></td>
                            <td><?php echo $e((string) $apuracao['fechada_em']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="admin-section">
        <div class="admin-section__header">
            <h2 class="admin-section__title">Perfil fiscal dos professores</h2>
        </div>
        <form method="post" action="/admin/financeiro/professor-fiscal" class="form-grid">
            <label>
                Professor
                <select name="usuario_id" required>
                    <option value="">Selecione</option>
                    <?php foreach ($professores as $professor): ?>
                        <option value="<?php echo (int) $professor['id']; ?>">
                            <?php echo $e($professor['nome'] . ' (' . $professor['email'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                Tipo fiscal
                <select name="tipo_pessoa">
                    <option value="pf">PF</option>
                    <option value="pj">PJ</option>
                </select>
            </label>

            <label>
                CPF
                <input type="text" name="cpf" maxlength="14">
            </label>

            <label>
                CNPJ
                <input type="text" name="cnpj" maxlength="20">
            </label>

            <label>
                Razão social
                <input type="text" name="razao_social" maxlength="191">
            </label>

            <label>
                Nome fantasia
                <input type="text" name="nome_fantasia" maxlength="191">
            </label>

            <label>
                Inscrição municipal
                <input type="text" name="inscricao_municipal" maxlength="100">
            </label>

            <label>
                Alíquota de retenção (%)
                <input type="number" step="0.01" min="0" max="100" name="aliquota_retencao" value="0.00">
            </label>

            <label>
                E-mail financeiro
                <input type="email" name="email_financeiro" maxlength="191">
            </label>

            <label>
                Status
                <select name="status">
                    <option value="ativo">Ativo</option>
                    <option value="inativo">Inativo</option>
                </select>
            </label>

            <label class="full">
                Observação
                <textarea name="observacao" rows="3"></textarea>
            </label>

            <label class="full">
                <input type="checkbox" name="exige_nota_fiscal" value="1">
                Exigir nota fiscal
            </label>

            <div class="full">
                <?php
                $cancel_url = '/admin/financeiro';
                $show_save_as_copy = false;
                $save_label = 'Salvar perfil fiscal';
                require BASE_PATH . '/resources/views/admin/partials/form-actions.php';
                ?>
            </div>
        </form>
    </section>

    <section class="admin-section">
        <div class="admin-section__header">
            <h2 class="admin-section__title">Perfis cadastrados</h2>
        </div>
        <div class="table-wrap">
            <table class="admin-table admin-table--financeiro-perfis">
                <thead>
                    <tr>
                        <th>Professor</th>
                        <th>Tipo</th>
                        <th>Documento</th>
                        <th>Alíquota</th>
                        <th>Exige NF</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($professores_fiscal)): ?>
                        <tr>
                            <td colspan="6">Nenhum perfil fiscal cadastrado.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($professores_fiscal as $perfil): ?>
                        <tr>
                            <td><?php echo $e($perfil['usuario_nome']); ?></td>
                            <td><?php echo $e($perfil['tipo_pessoa']); ?></td>
                            <td><?php echo $e(!empty($perfil['cpf']) ? $perfil['cpf'] : $perfil['cnpj']); ?></td>
                            <td><?php echo number_format((float) $perfil['aliquota_retencao'], 2, ',', '.'); ?>%</td>
                            <td><?php echo !empty($perfil['exige_nota_fiscal']) ? 'Sim' : 'Não'; ?></td>
                            <td><?php echo $e($perfil['status']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
