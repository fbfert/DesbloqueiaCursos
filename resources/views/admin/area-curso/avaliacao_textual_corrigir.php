<?php use App\Core\Helpers; ?>
<?php $entrega = isset($entrega) && is_array($entrega) ? $entrega : array(); ?>
<section class="page-header">
    <h1>Correção de avaliação textual</h1>
    <p>Entrega #<?php echo (int) ($entrega['id'] ?? 0); ?></p>
</section>
<?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
<?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>
<section class="status-card">
    <p><strong>Curso:</strong> <?php echo Helpers::e($entrega['curso_nome'] ?? ''); ?></p>
    <p><strong>Turma:</strong> <?php echo Helpers::e($entrega['turma_nome'] ?? 'Sem turma'); ?></p>
    <p><strong>Módulo:</strong> <?php echo Helpers::e($entrega['modulo_titulo'] ?? ''); ?></p>
    <p><strong>Avaliação:</strong> <?php echo Helpers::e($entrega['item_titulo'] ?? ''); ?></p>
    <p><strong>Aluno:</strong> <?php echo Helpers::e($entrega['aluno_nome'] ?? ''); ?></p>
    <p><strong>Enunciado:</strong> <?php echo $entrega['enunciado'] ?? ''; ?></p>
    <p><strong>Orientações:</strong> <?php echo $entrega['orientacoes'] ?? ''; ?></p>
    <p><strong>Resposta:</strong><br><?php echo nl2br(Helpers::e((string) ($entrega['resposta'] ?? ''))); ?></p>

    <?php if (!empty($entrega['imagens'])): ?>
        <p><strong>Imagens anexadas:</strong></p>
        <div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:14px;">
            <?php foreach ($entrega['imagens'] as $img): ?>
                <a href="/admin/area-curso/conteudo/avaliacao/imagem?id=<?php echo (int) $img['id']; ?>" target="_blank" rel="noopener noreferrer" style="display:block;width:110px;height:110px;border-radius:10px;overflow:hidden;border:1px solid #d8dde6;">
                    <img src="/admin/area-curso/conteudo/avaliacao/imagem?id=<?php echo (int) $img['id']; ?>" alt="<?php echo Helpers::e((string) ($img['nome_original'] ?? 'Imagem enviada')); ?>" loading="lazy" style="width:100%;height:100%;object-fit:cover;display:block;">
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post" action="/admin/area-curso/conteudo/avaliacao/corrigir" class="form-grid">
        <?php echo $csrfField; ?>
        <input type="hidden" name="entrega_id" value="<?php echo (int) ($entrega['id'] ?? 0); ?>">
        <label>Nota <input type="number" step="0.01" name="nota" value="<?php echo Helpers::e((string) ($entrega['nota'] ?? '')); ?>"></label>
        <label>Status
            <select name="status">
                <option value="">Automático</option>
                <option value="corrigida">Corrigida</option>
                <option value="aprovada">Aprovada</option>
                <option value="reprovada">Reprovada</option>
                <option value="devolvida">Devolvida</option>
            </select>
        </label>
        <label class="full">Feedback <textarea name="feedback" rows="6"><?php echo Helpers::e((string) ($entrega['feedback'] ?? '')); ?></textarea></label>
        <div class="full"><button type="submit">Salvar correção</button></div>
    </form>

    <form method="post" action="/admin/area-curso/conteudo/avaliacao/liberar-reenvio" class="form-grid" style="margin-top:12px;">
        <?php echo $csrfField; ?>
        <input type="hidden" name="entrega_id" value="<?php echo (int) ($entrega['id'] ?? 0); ?>">
        <label>Novo prazo para reenvio <input type="datetime-local" name="novo_prazo" required></label>
        <div><button type="submit">Liberar novo prazo</button></div>
    </form>
</section>
