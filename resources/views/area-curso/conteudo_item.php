<?php use App\Core\Helpers; ?>
<?php
$item = isset($conteudo_item) && is_array($conteudo_item) ? $conteudo_item : array();
$modulo = isset($conteudo_modulo) && is_array($conteudo_modulo) ? $conteudo_modulo : array();
$detalhe = isset($conteudo_detalhe) && is_array($conteudo_detalhe) ? $conteudo_detalhe : array();
$progresso = isset($conteudo_progresso) && is_array($conteudo_progresso) ? $conteudo_progresso : array();
$inscricaoAtual = isset($inscricao) && is_array($inscricao) ? $inscricao : array();
$cursoId = !empty($inscricaoAtual['curso_evento_id']) ? (int) $inscricaoAtual['curso_evento_id'] : 0;
$turmaId = !empty($inscricaoAtual['turma_id']) ? (int) $inscricaoAtual['turma_id'] : 0;
$inscricaoId = !empty($inscricaoAtual['id']) ? (int) $inscricaoAtual['id'] : 0;
$tipo = (string) ($item['tipo'] ?? '');
$statusProgresso = !empty($progresso['status']) ? (string) $progresso['status'] : 'nao_iniciado';
$statusLabel = Helpers::statusLms($statusProgresso);
$podeConcluir = in_array($tipo, array('texto', 'arquivo', 'link', 'video'), true);
$entregasAvaliacao = isset($conteudo_avaliacao_entregas) && is_array($conteudo_avaliacao_entregas) ? $conteudo_avaliacao_entregas : array();
$ultimaEntrega = !empty($entregasAvaliacao) ? $entregasAvaliacao[0] : null;
$statusEntrega = !empty($ultimaEntrega['status']) ? (string) $ultimaEntrega['status'] : 'nao_enviada';
$temCorrecao = !empty($ultimaEntrega) && in_array($statusEntrega, array('corrigida', 'aprovada', 'reprovada', 'devolvida'), true);
?>

<section class="status-card" style="margin-bottom: 16px; background: linear-gradient(135deg, #eef6ff 0%, #f7fbff 100%); border-color: #b8d6ff;">
    <strong style="display:block; font-size: 1.1rem; margin-bottom: 4px;">Conteúdo do curso</strong>
    <div class="muted-row"><?php echo Helpers::textoLms($modulo['titulo'] ?? 'Módulo'); ?> · <?php echo Helpers::textoLms($item['titulo'] ?? 'Item'); ?></div>
</section>

<?php if (!empty($success)): ?>
    <section class="auth-message auth-message-success"><p><?php echo Helpers::e($success); ?></p></section>
<?php endif; ?>
<?php if (!empty($errors)): ?>
    <section class="auth-message auth-message-error">
        <?php foreach ($errors as $error): ?><p><?php echo Helpers::e($error); ?></p><?php endforeach; ?>
    </section>
<?php endif; ?>

<section class="status-card">
    <article class="status-card" style="margin-bottom: 16px; background: linear-gradient(135deg, #eef6ff 0%, #f7fbff 100%); border-color: #b8d6ff;">
        <strong style="display:block; font-size: 1.05rem; margin-bottom: 4px;">[<?php echo Helpers::textoLms(Helpers::tipoConteudoLms($tipo)); ?>] <?php echo Helpers::textoLms($item['titulo'] ?? ''); ?></strong>
        <div class="muted-row">
            <?php echo !empty($item['obrigatorio']) ? 'Obrigatório' : 'Opcional'; ?> ·
            Status: <?php echo Helpers::textoLms($statusLabel); ?>
            <?php if (!empty($item['descricao_curta'])): ?> · <?php echo Helpers::textoLms($item['descricao_curta']); ?><?php endif; ?>
        </div>
    </article>

    <?php if ($tipo === 'texto' || $tipo === 'etiqueta'): ?>
        <div class="muted-row"><?php echo isset($detalhe['conteudo']) ? nl2br(Helpers::textoLmsMultilinha($detalhe['conteudo'])) : ''; ?></div>
    <?php elseif ($tipo === 'arquivo'): ?>
        <p class="muted-row">
            Arquivo: <?php echo Helpers::textoLms($detalhe['nome_original'] ?? 'Não enviado'); ?>
            <?php if (!empty($detalhe['extensao'])): ?> · Extensão: <?php echo Helpers::textoLms($detalhe['extensao']); ?><?php endif; ?>
            <?php if (!empty($detalhe['tamanho_bytes'])): ?> · Tamanho: <?php echo Helpers::e(number_format(((int) $detalhe['tamanho_bytes']) / 1024, 2, ',', '.')); ?> KB<?php endif; ?>
        </p>
        <?php if (!empty($detalhe['caminho'])): ?>
            <p><a class="button-link" href="/aluno/cursos/conteudo/arquivo/download?id=<?php echo (int) $item['id']; ?>&inscricao_id=<?php echo (int) $inscricaoId; ?>&curso_id=<?php echo (int) $cursoId; ?><?php echo $turmaId > 0 ? '&turma_id=' . (int) $turmaId : ''; ?>">Baixar arquivo</a></p>
        <?php endif; ?>
    <?php elseif ($tipo === 'link'): ?>
        <p class="muted-row">Modo de abertura: <?php echo Helpers::textoLms($detalhe['modo_abertura'] ?? 'nova_aba'); ?></p>
        <p><a class="button-link" href="/aluno/cursos/conteudo/link/acessar?id=<?php echo (int) $item['id']; ?>&inscricao_id=<?php echo (int) $inscricaoId; ?>&curso_id=<?php echo (int) $cursoId; ?><?php echo $turmaId > 0 ? '&turma_id=' . (int) $turmaId : ''; ?>">Acessar link</a></p>
    <?php elseif ($tipo === 'video'): ?>
        <p class="muted-row">Vídeo externo: <?php echo Helpers::textoLms($detalhe['provedor'] ?? 'provedor não identificado'); ?></p>
        <?php if (!empty($detalhe['url'])): ?>
            <p><a class="button-link" href="<?php echo Helpers::e($detalhe['url']); ?>" target="_blank" rel="noopener">Abrir vídeo</a></p>
        <?php endif; ?>
    <?php elseif ($tipo === 'avaliacao_textual'): ?>
        <div class="muted-row"><?php echo isset($detalhe['enunciado']) ? nl2br(Helpers::textoLmsMultilinha($detalhe['enunciado'])) : ''; ?></div>
        <?php if (!empty($detalhe['orientacoes'])): ?><div class="muted-row"><?php echo nl2br(Helpers::textoLmsMultilinha($detalhe['orientacoes'])); ?></div><?php endif; ?>
        <p class="muted-row">
            Nota máxima: <?php echo Helpers::e($detalhe['nota_maxima'] ?? '-'); ?> ·
            Nota mínima: <?php echo Helpers::e($detalhe['nota_minima'] ?? '-'); ?> ·
            Peso: <?php echo Helpers::e($detalhe['peso'] ?? '-'); ?> ·
            Prazo: <?php echo Helpers::e($detalhe['prazo'] ?? '-'); ?>
        </p>
        <p class="muted-row">Status da última entrega: <?php echo Helpers::textoLms(Helpers::statusLms($statusEntrega)); ?></p>
        <?php if ($temCorrecao): ?>
            <p class="muted-row">Nota: <?php echo Helpers::e($ultimaEntrega['nota'] !== null && $ultimaEntrega['nota'] !== '' ? $ultimaEntrega['nota'] : '-'); ?></p>
            <?php if (!empty($ultimaEntrega['feedback'])): ?><p class="muted-row">Feedback: <?php echo nl2br(Helpers::textoLmsMultilinha($ultimaEntrega['feedback'])); ?></p><?php endif; ?>
            <?php if (!empty($ultimaEntrega['corrigido_em'])): ?><p class="muted-row">Corrigido em: <?php echo Helpers::textoLms($ultimaEntrega['corrigido_em']); ?></p><?php endif; ?>
        <?php endif; ?>

        <?php if (!empty($entregasAvaliacao)): ?>
            <div class="table-wrap" style="margin:12px 0;">
                <table class="admin-table">
                    <thead><tr><th>Tentativa</th><th>Status</th><th>Enviado em</th><th>Nota</th></tr></thead>
                    <tbody>
                    <?php foreach ($entregasAvaliacao as $ent): ?>
                        <tr>
                            <td><?php echo (int) ($ent['tentativa'] ?? 0); ?></td>
                            <td><?php echo Helpers::textoLms(Helpers::statusLms((string) ($ent['status'] ?? ''))); ?></td>
                            <td><?php echo Helpers::textoLms((string) ($ent['enviado_em'] ?? '-')); ?></td>
                            <td><?php echo Helpers::e(($ent['nota'] ?? '') !== '' ? (string) $ent['nota'] : '-'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <form method="post" action="/aluno/cursos/conteudo/avaliacao/enviar" class="form-grid" style="margin-top:12px;">
            <?php echo $csrfField; ?>
            <input type="hidden" name="item_id" value="<?php echo (int) ($item['id'] ?? 0); ?>">
            <input type="hidden" name="inscricao_id" value="<?php echo (int) $inscricaoId; ?>">
            <input type="hidden" name="curso_id" value="<?php echo (int) $cursoId; ?>">
            <input type="hidden" name="turma_id" value="<?php echo $turmaId > 0 ? (int) $turmaId : ''; ?>">
            <label class="full">
                Resposta textual
                <textarea name="resposta" rows="8" required placeholder="Digite sua resposta"></textarea>
            </label>
            <div class="full">
                <?php if (empty($ultimaEntrega)): ?>
                    <button type="submit">Enviar resposta</button>
                <?php else: ?>
                    <button type="submit">Reenviar resposta</button>
                <?php endif; ?>
            </div>
        </form>
    <?php endif; ?>

    <?php if ($podeConcluir): ?>
        <form method="post" action="/aluno/cursos/conteudo/item/concluir" class="form-grid" style="margin-top:12px;">
            <?php echo $csrfField; ?>
            <input type="hidden" name="item_id" value="<?php echo (int) ($item['id'] ?? 0); ?>">
            <input type="hidden" name="inscricao_id" value="<?php echo (int) $inscricaoId; ?>">
            <input type="hidden" name="curso_id" value="<?php echo (int) $cursoId; ?>">
            <input type="hidden" name="turma_id" value="<?php echo $turmaId > 0 ? (int) $turmaId : ''; ?>">
            <button type="submit">Marcar como concluído</button>
        </form>
    <?php endif; ?>

        <p style="margin-top:12px;">
        <a class="button-link button-link--ghost" href="/aluno/cursos?inscricao_id=<?php echo (int) $inscricaoId; ?>&curso_id=<?php echo (int) $cursoId; ?><?php echo $turmaId > 0 ? '&turma_id=' . (int) $turmaId : ''; ?>">Voltar para a sala virtual</a>
    </p>
</section>
