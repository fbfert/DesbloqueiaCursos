<?php use App\Core\Helpers; ?>
<?php use App\Support\HtmlSanitizer; ?>
<?php
$item = isset($conteudo_item) && is_array($conteudo_item) ? $conteudo_item : array();
$modulo = isset($conteudo_modulo) && is_array($conteudo_modulo) ? $conteudo_modulo : array();
$detalhe = isset($conteudo_detalhe) && is_array($conteudo_detalhe) ? $conteudo_detalhe : array();
$progresso = isset($conteudo_progresso) && is_array($conteudo_progresso) ? $conteudo_progresso : array();
$inscricaoAtual = isset($inscricao) && is_array($inscricao) ? $inscricao : array();
$resumo = isset($conteudo_resumo) && is_array($conteudo_resumo) ? $conteudo_resumo : array();
$entregasAvaliacao = isset($conteudo_avaliacao_entregas) && is_array($conteudo_avaliacao_entregas) ? $conteudo_avaliacao_entregas : array();
$avaliacaoPodeEnviar = isset($conteudo_avaliacao_pode_enviar) ? (bool) $conteudo_avaliacao_pode_enviar : null;

$cursoId = !empty($inscricaoAtual['curso_evento_id']) ? (int) $inscricaoAtual['curso_evento_id'] : 0;
$turmaId = !empty($inscricaoAtual['turma_id']) ? (int) $inscricaoAtual['turma_id'] : 0;
$inscricaoId = !empty($inscricaoAtual['id']) ? (int) $inscricaoAtual['id'] : 0;
$tipo = (string) ($item['tipo'] ?? '');

$valorPrimeiro = function (array $dados, array $chaves, $padrao = '') {
    foreach ($chaves as $chave) {
        if (!isset($dados[$chave])) {
            continue;
        }
        $valor = trim((string) $dados[$chave]);
        if ($valor !== '') {
            return $valor;
        }
    }

    return $padrao;
};

$formatarBytes = function ($bytes) {
    $bytes = (int) $bytes;
    if ($bytes <= 0) {
        return '-';
    }

    $unidades = array('B', 'KB', 'MB', 'GB');
    $indice = 0;
    $valor = (float) $bytes;
    while ($valor >= 1024 && $indice < count($unidades) - 1) {
        $valor = $valor / 1024;
        $indice++;
    }

    return number_format($valor, $indice === 0 ? 0 : 2, ',', '.') . ' ' . $unidades[$indice];
};

$formatarDataHora = function ($valor) {
    $valor = trim((string) $valor);
    if ($valor === '') {
        return '-';
    }

    $timestamp = strtotime($valor);
    if ($timestamp === false) {
        return Helpers::textoLms($valor);
    }

    return Helpers::e(date('d/m/Y H:i', $timestamp));
};

$renderRich = function ($valor, $perfil = 'basic') {
    $html = HtmlSanitizer::clean(html_entity_decode((string) $valor, ENT_QUOTES | ENT_HTML5, 'UTF-8'), $perfil);
    return trim((string) $html);
};

$statusProgresso = !empty($progresso['status']) ? (string) $progresso['status'] : 'nao_iniciado';
$statusEntregaAtual = !empty($entregasAvaliacao) && !empty($entregasAvaliacao[0]['status']) ? (string) $entregasAvaliacao[0]['status'] : '';
$statusExibicao = $tipo === 'avaliacao_textual' && $statusEntregaAtual !== '' ? $statusEntregaAtual : $statusProgresso;

$statusClasseMap = array(
    'concluido' => 'pill--success',
    'aprovada' => 'pill--success',
    'corrigida' => 'pill--success',
    'corrigido' => 'pill--success',
    'em_andamento' => 'pill--info',
    'acessado' => 'pill--info',
    'enviada' => 'pill--warning',
    'reenviada' => 'pill--warning',
    'aguardando_envio' => 'pill--warning',
    'aguardando_correcao' => 'pill--warning',
    'pendente_correcao' => 'pill--warning',
    'devolvida' => 'pill--warning',
    'reprovada' => 'pill--danger',
    'cancelada' => 'pill--danger',
);
$statusClasse = isset($statusClasseMap[$statusExibicao]) ? $statusClasseMap[$statusExibicao] : 'pill--neutral';

$statusEntregaAmigavel = array(
    'enviada' => 'Enviada',
    'reenviada' => 'Reenviada',
    'corrigida' => 'Corrigida',
    'corrigido' => 'Corrigido',
    'devolvida' => 'Devolvida para ajuste',
    'aprovada' => 'Aprovada',
    'reprovada' => 'Reprovada',
    'cancelada' => 'Cancelada',
);

$itemTitulo = $valorPrimeiro($item, array('titulo', 'nome'), 'Item');
$itemDescricao = $valorPrimeiro($item, array('descricao_curta', 'descricao'), '');
$moduloTitulo = $valorPrimeiro($modulo, array('titulo', 'nome'), 'Módulo');
$cursoTitulo = $valorPrimeiro($inscricaoAtual, array('curso_nome', 'nome_curso', 'titulo_curso'), 'Curso');
$progressoPercentual = isset($progresso['percentual']) ? (float) $progresso['percentual'] : null;
$progressoItemTexto = $progressoPercentual !== null ? number_format($progressoPercentual, 2, ',', '.') . '%' : null;
$statusTexto = Helpers::statusLms($statusExibicao);
$tipoLabel = Helpers::tipoConteudoLms($tipo);
$obrigatorioLabel = !empty($item['obrigatorio']) ? 'Obrigatório' : 'Opcional';
$concluidoEm = !empty($progresso['concluido_em']) ? (string) $progresso['concluido_em'] : '';
$cursoProgressoTexto = array_key_exists('percentual', $resumo) && $resumo['percentual'] !== null
    ? number_format((float) $resumo['percentual'], 2, ',', '.') . '%'
    : null;
$backUrl = '/aluno/cursos?inscricao_id=' . (int) $inscricaoId . '&curso_id=' . (int) $cursoId . ($turmaId > 0 ? '&turma_id=' . (int) $turmaId : '');
$itemConcluido = in_array($statusExibicao, array('concluido', 'aprovada', 'corrigida'), true);
$podeConcluir = in_array($tipo, array('texto', 'arquivo', 'link', 'video'), true) && !$itemConcluido;
$ultimoRegistroEntrega = !empty($entregasAvaliacao) ? $entregasAvaliacao[0] : null;
$ultimaEntregaStatus = !empty($ultimoRegistroEntrega['status']) ? (string) $ultimoRegistroEntrega['status'] : '';
$ultimaEntregaStatusLabel = $ultimaEntregaStatus !== '' && isset($statusEntregaAmigavel[$ultimaEntregaStatus])
    ? $statusEntregaAmigavel[$ultimaEntregaStatus]
    : ($ultimaEntregaStatus !== '' ? Helpers::statusLms($ultimaEntregaStatus) : 'Aguardando envio');
$ultimaEntregaTentativa = !empty($ultimoRegistroEntrega['tentativa']) ? (int) $ultimoRegistroEntrega['tentativa'] : 0;
$avaliacao = $tipo === 'avaliacao_textual' ? $detalhe : array();
$avaliacaoId = !empty($avaliacao['id']) ? (int) $avaliacao['id'] : 0;
$avaliacaoEnunciado = $valorPrimeiro($avaliacao, array('enunciado'), '');
$avaliacaoOrientacoes = $valorPrimeiro($avaliacao, array('orientacoes'), '');
$avaliacaoPrazo = $valorPrimeiro($avaliacao, array('prazo'), '');
$avaliacaoNotaMaxima = isset($avaliacao['nota_maxima']) && $avaliacao['nota_maxima'] !== '' ? (string) $avaliacao['nota_maxima'] : '-';
$avaliacaoNotaMinima = isset($avaliacao['nota_minima']) && $avaliacao['nota_minima'] !== '' ? (string) $avaliacao['nota_minima'] : '-';
$avaliacaoPeso = isset($avaliacao['peso']) && $avaliacao['peso'] !== '' ? (string) $avaliacao['peso'] : '-';
$avaliacaoPermiteReenvio = !empty($avaliacao['permite_reenvio']);
$avaliacaoReenvioLivre = !empty($avaliacao['reenvio_livre_ate_prazo']);
$avaliacaoBotaoLabel = empty($entregasAvaliacao) ? 'Enviar resposta' : 'Reenviar resposta';
$avaliacaoPodeManipular = $avaliacaoPodeEnviar === null ? true : $avaliacaoPodeEnviar;
$arquivoNome = $valorPrimeiro($detalhe, array('nome_original', 'nome_arquivo'), '');
$arquivoExtensao = $valorPrimeiro($detalhe, array('extensao'), '');
$arquivoTamanho = isset($detalhe['tamanho_bytes']) ? (int) $detalhe['tamanho_bytes'] : 0;
$arquivoDisponivel = $arquivoNome !== '' && !empty($detalhe['caminho']);
$linkUrl = $valorPrimeiro($detalhe, array('url'), '');
$linkModo = $valorPrimeiro($detalhe, array('modo_abertura'), 'nova_aba');
$linkProvedor = $valorPrimeiro($detalhe, array('provedor'), '');
$linkDomino = '';
if ($linkUrl !== '') {
    $parsedLink = parse_url($linkUrl);
    if (!empty($parsedLink['host'])) {
        $linkDomino = preg_replace('/^www\./i', '', (string) $parsedLink['host']);
    }
}
$videoUrl = $valorPrimeiro($detalhe, array('url'), '');
$videoProvedor = $valorPrimeiro($detalhe, array('provedor'), '');
$videoEmbedHtml = $valorPrimeiro($detalhe, array('embed_html'), '');

$avaliacaoStatusPodeReenviar = $avaliacaoPodeManipular ? 'Disponível' : 'Indisponível no momento';
?>

<section class="conteudo-item-shell">
    <?php if (!empty($success)): ?>
        <section class="auth-message auth-message-success" aria-live="polite">
            <p><?php echo Helpers::e($success); ?></p>
        </section>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <section class="auth-message auth-message-error" aria-live="polite">
            <?php foreach ($errors as $error): ?>
                <p><?php echo Helpers::e($error); ?></p>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <section class="status-card conteudo-item-hero">
        <div class="conteudo-item-hero__top">
            <a class="button-link button-link--ghost conteudo-item-hero__back" href="<?php echo Helpers::e($backUrl); ?>">Voltar para o curso</a>
            <div class="pill-row conteudo-item-hero__pills">
                <span class="pill"><?php echo Helpers::textoLms($cursoTitulo); ?></span>
                <?php if ($cursoProgressoTexto !== null): ?>
                    <span class="pill pill--info">Progresso do curso: <?php echo Helpers::e($cursoProgressoTexto); ?></span>
                <?php endif; ?>
            </div>
        </div>

        <div class="conteudo-item-hero__breadcrumbs">
            <span class="pill pill--neutral"><?php echo Helpers::textoLms($moduloTitulo); ?></span>
            <span class="pill pill--neutral"><?php echo Helpers::e($tipoLabel); ?></span>
            <span class="pill <?php echo Helpers::e($statusClasse); ?>"><?php echo Helpers::e($statusTexto); ?></span>
        </div>

        <h1 class="conteudo-item-hero__title"><?php echo Helpers::textoLms($itemTitulo); ?></h1>

        <?php if ($itemDescricao !== ''): ?>
            <p class="conteudo-item-hero__description"><?php echo nl2br(Helpers::textoLmsMultilinha($itemDescricao)); ?></p>
        <?php endif; ?>

        <div class="conteudo-item-hero__meta">
            <span><?php echo Helpers::e($obrigatorioLabel); ?></span>
            <span>Status: <?php echo Helpers::e($statusTexto); ?></span>
            <?php if ($progressoItemTexto !== null): ?>
                <span>Progresso do item: <?php echo Helpers::e($progressoItemTexto); ?></span>
            <?php endif; ?>
            <?php if ($itemConcluido && $concluidoEm !== ''): ?>
                <span>Concluído em <?php echo $formatarDataHora($concluidoEm); ?></span>
            <?php endif; ?>
        </div>
    </section>

    <section class="status-card conteudo-item-body">
        <?php if ($tipo === 'etiqueta'): ?>
            <article class="conteudo-item-bloco conteudo-item-bloco--destaque">
                <div class="conteudo-item-bloco__header">
                    <strong>Orientação visual</strong>
                    <span class="pill <?php echo Helpers::e($statusClasse); ?>"><?php echo Helpers::e($statusTexto); ?></span>
                </div>
                <div class="conteudo-item-rich">
                    <?php echo $renderRich($valorPrimeiro($detalhe, array('conteudo'), ''), 'full'); ?>
                </div>
                <p class="conteudo-item-note">Esta orientação faz parte do módulo e ajuda você a seguir a trilha do curso.</p>
            </article>
            <?php if ($itemConcluido && $concluidoEm !== ''): ?>
                <div class="conteudo-item-action-summary" id="conteudo-item-acao">
                    <strong>Orientação visualizada</strong>
                    <span>Concluída em <?php echo $formatarDataHora($concluidoEm); ?></span>
                </div>
            <?php endif; ?>
        <?php elseif ($tipo === 'texto'): ?>
            <article class="conteudo-item-bloco">
                <div class="conteudo-item-rich conteudo-item-rich--reading">
                    <?php echo $renderRich($valorPrimeiro($detalhe, array('conteudo'), ''), 'full'); ?>
                </div>
                <div class="conteudo-item-action-summary" id="conteudo-item-acao">
                    <?php if ($itemConcluido): ?>
                        <strong>Conteúdo concluído</strong>
                        <span><?php echo $concluidoEm !== '' ? 'Concluído em ' . $formatarDataHora($concluidoEm) : 'Concluído'; ?></span>
                    <?php else: ?>
                        <strong>Próximo passo</strong>
                        <span>Leia com atenção e conclua o item quando terminar.</span>
                    <?php endif; ?>
                </div>
            </article>
        <?php elseif ($tipo === 'arquivo'): ?>
            <article class="conteudo-item-file">
                <div class="conteudo-item-file__header">
                    <strong><?php echo $arquivoNome !== '' ? Helpers::textoLms($arquivoNome) : 'Arquivo indisponível'; ?></strong>
                    <span class="pill pill--neutral"><?php echo $arquivoExtensao !== '' ? Helpers::e(strtoupper($arquivoExtensao)) : 'ARQUIVO'; ?></span>
                </div>
                <div class="conteudo-item-file__meta">
                    <span>Tamanho: <?php echo $formatarBytes($arquivoTamanho); ?></span>
                    <?php if ($itemDescricao !== ''): ?><span><?php echo nl2br(Helpers::textoLmsMultilinha($itemDescricao)); ?></span><?php endif; ?>
                </div>
                <?php if ($arquivoDisponivel): ?>
                    <div class="conteudo-item-actions" id="conteudo-item-acao">
                        <a class="button-link" href="/aluno/cursos/conteudo/arquivo/download?id=<?php echo (int) $item['id']; ?>&inscricao_id=<?php echo (int) $inscricaoId; ?>&curso_id=<?php echo (int) $cursoId; ?><?php echo $turmaId > 0 ? '&turma_id=' . (int) $turmaId : ''; ?>">Baixar arquivo</a>
                        <p class="conteudo-item-note">O download registra acesso e continua seguindo a regra de progresso já existente.</p>
                    </div>
                <?php else: ?>
                    <div class="conteudo-item-action-summary" id="conteudo-item-acao">
                        <strong>Arquivo indisponível no momento.</strong>
                        <span>Não é possível exibir o caminho físico do arquivo para o aluno.</span>
                    </div>
                <?php endif; ?>
            </article>
        <?php elseif ($tipo === 'link'): ?>
            <article class="conteudo-item-link">
                <div class="conteudo-item-link__meta">
                    <strong><?php echo Helpers::textoLms($itemTitulo); ?></strong>
                    <?php if ($linkDomino !== ''): ?>
                        <span>Domínio: <?php echo Helpers::e($linkDomino); ?></span>
                    <?php endif; ?>
                    <span>Modo de abertura: <?php echo Helpers::e($linkModo === 'embed' ? 'Área incorporada' : ($linkModo === 'botao' ? 'Botão' : 'Nova aba')); ?></span>
                    <?php if ($linkProvedor !== ''): ?>
                        <span>Provedor: <?php echo Helpers::textoLms($linkProvedor); ?></span>
                    <?php endif; ?>
                </div>
                <div class="conteudo-item-rich">
                    <?php if ($itemDescricao !== ''): ?>
                        <?php echo nl2br(Helpers::textoLmsMultilinha($itemDescricao)); ?>
                    <?php else: ?>
                        <p>Use o botão abaixo para acessar o conteúdo por meio da rota intermediária, sem pular o registro de acesso.</p>
                    <?php endif; ?>
                </div>
                <div class="conteudo-item-actions" id="conteudo-item-acao">
                    <a class="button-link" href="/aluno/cursos/conteudo/link/acessar?id=<?php echo (int) $item['id']; ?>&inscricao_id=<?php echo (int) $inscricaoId; ?>&curso_id=<?php echo (int) $cursoId; ?><?php echo $turmaId > 0 ? '&turma_id=' . (int) $turmaId : ''; ?>">Acessar link</a>
                    <p class="conteudo-item-note">O acesso é registrado antes do redirecionamento externo.</p>
                </div>
            </article>
        <?php elseif ($tipo === 'video'): ?>
            <article class="conteudo-item-video">
                <div class="conteudo-item-video__meta">
                    <strong><?php echo Helpers::textoLms($itemTitulo); ?></strong>
                    <?php if ($videoProvedor !== ''): ?>
                        <span>Provedor: <?php echo Helpers::textoLms($videoProvedor); ?></span>
                    <?php endif; ?>
                    <?php if ($itemDescricao !== ''): ?>
                        <span><?php echo nl2br(Helpers::textoLmsMultilinha($itemDescricao)); ?></span>
                    <?php endif; ?>
                </div>
                <?php if ($videoEmbedHtml !== '' && false): ?>
                    <div class="conteudo-item-embed">
                        <?php echo $renderRich($videoEmbedHtml, 'full'); ?>
                    </div>
                <?php endif; ?>
                <div class="conteudo-item-actions" id="conteudo-item-acao">
                    <?php if ($videoUrl !== ''): ?>
                        <a class="button-link" href="<?php echo Helpers::e($videoUrl); ?>" target="_blank" rel="noopener noreferrer">Assistir vídeo</a>
                    <?php endif; ?>
                    <p class="conteudo-item-note">Se não houver embed seguro, o acesso externo é a opção exibida nesta tela.</p>
                </div>
            </article>
        <?php elseif ($tipo === 'avaliacao_textual'): ?>
            <article class="conteudo-item-eval">
                <header class="conteudo-item-eval__header">
                    <strong>Detalhes da avaliação</strong>
                    <span class="pill <?php echo Helpers::e($statusClasse); ?>"><?php echo Helpers::e($statusTexto); ?></span>
                </header>

                <div class="conteudo-item-eval__grid">
                    <div><strong>Prazo</strong><span><?php echo $avaliacaoPrazo !== '' ? $formatarDataHora($avaliacaoPrazo) : 'Sem prazo definido'; ?></span></div>
                    <div><strong>Nota máxima</strong><span><?php echo Helpers::e($avaliacaoNotaMaxima); ?></span></div>
                    <div><strong>Nota mínima</strong><span><?php echo Helpers::e($avaliacaoNotaMinima); ?></span></div>
                    <div><strong>Peso</strong><span><?php echo Helpers::e($avaliacaoPeso); ?></span></div>
                    <div><strong>Envio atual</strong><span><?php echo Helpers::e($ultimaEntregaStatus !== '' ? $ultimaEntregaStatusLabel : 'Aguardando envio'); ?></span></div>
                    <div><strong>Tentativa</strong><span><?php echo $ultimaEntregaTentativa > 0 ? (int) $ultimaEntregaTentativa : '-'; ?></span></div>
                </div>

                <?php if ($avaliacaoEnunciado !== ''): ?>
                    <div class="conteudo-item-rich conteudo-item-rich--reading">
                        <?php echo $renderRich($avaliacaoEnunciado, 'full'); ?>
                    </div>
                <?php endif; ?>
                <?php if ($avaliacaoOrientacoes !== ''): ?>
                    <div class="conteudo-item-note conteudo-item-note--box">
                        <?php echo $renderRich($avaliacaoOrientacoes, 'basic'); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($ultimoRegistroEntrega)): ?>
                    <section class="conteudo-item-feedback" id="feedback">
                        <header class="conteudo-item-feedback__header">
                            <strong>Status da entrega</strong>
                            <span class="pill <?php echo Helpers::e(isset($statusClasseMap[$ultimaEntregaStatus]) ? $statusClasseMap[$ultimaEntregaStatus] : 'pill--neutral'); ?>"><?php echo Helpers::e($ultimaEntregaStatusLabel); ?></span>
                        </header>
                        <div class="conteudo-item-feedback__meta">
                            <span>Enviada em <?php echo $formatarDataHora($ultimoRegistroEntrega['enviado_em'] ?? ''); ?></span>
                            <?php if (!empty($ultimoRegistroEntrega['corrigido_em'])): ?>
                                <span>Corrigida em <?php echo $formatarDataHora($ultimoRegistroEntrega['corrigido_em']); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($ultimoRegistroEntrega['nota']) || (isset($ultimoRegistroEntrega['nota']) && $ultimoRegistroEntrega['nota'] === '0')): ?>
                                <span>Nota: <?php echo Helpers::e((string) $ultimoRegistroEntrega['nota']); ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($ultimoRegistroEntrega['feedback'])): ?>
                            <div class="conteudo-item-feedback__body">
                                <?php echo $renderRich($ultimoRegistroEntrega['feedback'], 'basic'); ?>
                            </div>
                        <?php else: ?>
                            <p class="conteudo-item-note">Aguardando feedback do professor.</p>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>

                <?php if (!empty($entregasAvaliacao)): ?>
                    <section class="conteudo-item-history">
                        <header class="conteudo-item-history__header">
                            <strong>Histórico resumido de tentativas</strong>
                            <span><?php echo count($entregasAvaliacao); ?> tentativa<?php echo count($entregasAvaliacao) === 1 ? '' : 's'; ?></span>
                        </header>
                        <div class="conteudo-item-history__list">
                            <?php foreach ($entregasAvaliacao as $entrega): ?>
                                <?php
                                $entregaStatus = (string) ($entrega['status'] ?? '');
                                $entregaStatusLabel = isset($statusEntregaAmigavel[$entregaStatus]) ? $statusEntregaAmigavel[$entregaStatus] : Helpers::statusLms($entregaStatus);
                                $entregaStatusClass = isset($statusClasseMap[$entregaStatus]) ? $statusClasseMap[$entregaStatus] : 'pill--neutral';
                                ?>
                                <article class="conteudo-item-history__card">
                                    <div class="conteudo-item-history__top">
                                        <strong>Tentativa <?php echo (int) ($entrega['tentativa'] ?? 0); ?></strong>
                                        <span class="pill <?php echo Helpers::e($entregaStatusClass); ?>"><?php echo Helpers::e($entregaStatusLabel); ?></span>
                                    </div>
                                    <div class="conteudo-item-history__meta">
                                        <span>Enviada em <?php echo $formatarDataHora($entrega['enviado_em'] ?? ''); ?></span>
                                        <span>Nota: <?php echo Helpers::e(($entrega['nota'] ?? '') !== '' ? (string) $entrega['nota'] : '-'); ?></span>
                                    </div>
                                    <?php if (!empty($entrega['feedback'])): ?>
                                        <p class="conteudo-item-history__feedback"><?php echo $renderRich($entrega['feedback'], 'basic'); ?></p>
                                    <?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <section class="conteudo-item-actions" id="conteudo-item-acao">
                    <?php if ($avaliacaoPodeManipular): ?>
                        <form method="post" action="/aluno/cursos/conteudo/avaliacao/enviar" class="conteudo-item-form">
                            <?php echo $csrfField; ?>
                            <input type="hidden" name="item_id" value="<?php echo (int) $item['id']; ?>">
                            <input type="hidden" name="inscricao_id" value="<?php echo (int) $inscricaoId; ?>">
                            <input type="hidden" name="curso_id" value="<?php echo (int) $cursoId; ?>">
                            <input type="hidden" name="turma_id" value="<?php echo $turmaId > 0 ? (int) $turmaId : ''; ?>">
                            <label class="conteudo-item-form__field">
                                <span>Resposta textual</span>
                                <textarea name="resposta" rows="8" required placeholder="Digite sua resposta aqui"></textarea>
                            </label>
                            <button type="submit"><?php echo Helpers::e($avaliacaoBotaoLabel); ?></button>
                        </form>
                    <?php else: ?>
                        <div class="conteudo-item-action-summary">
                            <strong><?php echo empty($entregasAvaliacao) ? 'Prazo encerrado.' : 'Reenvio depende de liberação do professor.'; ?></strong>
                            <span><?php echo empty($entregasAvaliacao) ? 'A entrega não pode mais ser enviada neste momento.' : 'A avaliação permanece aguardando a liberação necessária para novo envio.'; ?></span>
                        </div>
                    <?php endif; ?>
                </section>
            </article>
        <?php endif; ?>

        <?php if ($podeConcluir): ?>
            <form method="post" action="/aluno/cursos/conteudo/item/concluir" class="conteudo-item-concluir-form">
                <?php echo $csrfField; ?>
                <input type="hidden" name="item_id" value="<?php echo (int) ($item['id'] ?? 0); ?>">
                <input type="hidden" name="inscricao_id" value="<?php echo (int) $inscricaoId; ?>">
                <input type="hidden" name="curso_id" value="<?php echo (int) $cursoId; ?>">
                <input type="hidden" name="turma_id" value="<?php echo $turmaId > 0 ? (int) $turmaId : ''; ?>">
                <button type="submit">Marcar como concluído</button>
            </form>
        <?php endif; ?>

        <div class="conteudo-item-orientacao">
            <?php if ($tipo === 'etiqueta'): ?>
                <p>Esta orientação faz parte do módulo e ajuda você a seguir a trilha do curso.</p>
            <?php elseif ($tipo === 'texto'): ?>
                <p>Leia o conteúdo com atenção e conclua quando terminar.</p>
            <?php elseif ($tipo === 'arquivo'): ?>
                <p>Baixe o arquivo, consulte o material e conclua ao finalizar a leitura.</p>
            <?php elseif ($tipo === 'link'): ?>
                <p>O link é aberto pela rota intermediária para registrar o acesso com segurança.</p>
            <?php elseif ($tipo === 'video'): ?>
                <p>Assista ao vídeo pelo acesso externo ou pelo embed seguro, quando disponível.</p>
            <?php elseif ($tipo === 'avaliacao_textual'): ?>
                <p>Envie sua resposta dentro do prazo e acompanhe o retorno da correção nesta mesma tela.</p>
            <?php endif; ?>
        </div>

        <a class="button-link button-link--ghost conteudo-item-return" href="<?php echo Helpers::e($backUrl); ?>">Voltar para o curso</a>
    </section>
</section>
