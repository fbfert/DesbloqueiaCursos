<?php
use App\Core\Helpers;
use App\Services\RevisaoComentarioService;

$retorno = '/revisor/questoes?item_id=' . (int) $item['id'];

// Apontamentos ja registrados, agrupados por pergunta.
$porPergunta = array();
foreach ($comentariosDoCurso as $c) {
    $porPergunta[(int) $c['alvo_id']][] = $c;
}

$blocosPorId = array();
foreach ($blocos as $b) {
    $blocosPorId[(int) $b['id']] = $b;
}
?>

<div class="admin-page">
<section class="admin-page__header">
    <div>
        <h1 class="admin-page__title"><?php echo Helpers::e($item['titulo']); ?></h1>
        <p class="admin-page__subtitle">
            <?php echo Helpers::e($curso['nome']); ?> ·
            <?php echo count($perguntas); ?> questões no banco ·
            gabarito e explicação visíveis para revisão
        </p>
    </div>
    <div class="admin-page__actions">
        <a class="button-link button-link--ghost" href="/revisor/curso?curso_id=<?php echo (int) $curso['id']; ?>">Voltar ao curso</a>
    </div>
</section>

<?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
<?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

<section class="status-card">
    <strong>Como o sorteio usa este banco</strong>
    <div class="table-wrap">
        <table class="admin-table">
            <thead><tr><th>Bloco</th><th>Sorteia por tentativa</th><th>No banco</th><th>Tentativas</th></tr></thead>
            <tbody>
                <?php foreach ($blocos as $bloco): ?>
                    <?php
                    $noBanco = 0;
                    foreach ($perguntas as $p) {
                        if ((int) $p['bloco_id'] === (int) $bloco['id']) {
                            $noBanco++;
                        }
                    }
                    ?>
                    <tr>
                        <td><strong><?php echo Helpers::e($bloco['titulo']); ?></strong></td>
                        <td><?php echo (int) $bloco['quantidade_sortear']; ?></td>
                        <td><?php echo $noBanco; ?></td>
                        <td><?php echo (int) $quiz['tentativas_maximas']; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="muted">
        O aluno recebe questões diferentes a cada tentativa. Ao revisar, considere que qualquer questão do banco
        pode cair — não existe questão de reserva.
    </p>
</section>

<?php if (empty($perguntas)): ?>
    <section class="status-card"><strong>Este banco ainda está vazio.</strong></section>
<?php endif; ?>

<?php foreach ($perguntas as $indice => $pergunta): ?>
    <?php
    $perguntaId = (int) $pergunta['id'];
    $desta = isset($porPergunta[$perguntaId]) ? $porPergunta[$perguntaId] : array();
    $bloco = isset($blocosPorId[(int) $pergunta['bloco_id']]) ? $blocosPorId[(int) $pergunta['bloco_id']] : null;
    ?>
    <section class="status-card">
        <strong>
            Questão <?php echo $indice + 1; ?>
            <span class="badge"><?php echo Helpers::e($pergunta['dificuldade']); ?></span>
            <?php if ($bloco): ?><span class="badge"><?php echo Helpers::e($bloco['codigo']); ?></span><?php endif; ?>
            <?php if ($desta): ?><span class="badge badge--danger"><?php echo count($desta); ?> apontamento(s)</span><?php endif; ?>
        </strong>

        <?php if (!empty($pergunta['tema'])): ?>
            <p class="muted">Tema: <?php echo Helpers::e($pergunta['tema']); ?></p>
        <?php endif; ?>

        <p><?php echo nl2br(Helpers::e($pergunta['enunciado'])); ?></p>

        <div class="table-wrap">
            <table class="admin-table">
                <tbody>
                    <?php foreach ($pergunta['alternativas'] as $ordem => $alt): ?>
                        <tr>
                            <td style="width:3rem;"><strong><?php echo chr(65 + $ordem); ?>)</strong></td>
                            <td>
                                <?php echo Helpers::e($alt['texto']); ?>
                                <?php if ((int) $alt['correta'] === 1): ?>
                                    <strong> — gabarito</strong>
                                <?php endif; ?>
                            </td>
                            <td class="muted" style="width:6rem;"><?php echo mb_strlen($alt['texto']); ?> car.</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if (!empty($pergunta['explicacao'])): ?>
            <p class="muted"><strong>Explicação exibida ao aluno:</strong> <?php echo nl2br(Helpers::e($pergunta['explicacao'])); ?></p>
        <?php endif; ?>

        <?php if ($desta): ?>
            <div class="table-wrap">
                <table class="admin-table">
                    <thead><tr><th>Severidade</th><th>Apontamento</th><th>Situação</th></tr></thead>
                    <tbody>
                        <?php foreach ($desta as $c): ?>
                            <tr>
                                <td><span class="badge<?php echo $c['severidade'] === 'erro' ? ' badge--danger' : ''; ?>"><?php echo Helpers::e(RevisaoComentarioService::rotuloSeveridade($c['severidade'])); ?></span></td>
                                <td>
                                    <?php echo nl2br(Helpers::e($c['comentario'])); ?>
                                    <?php if (!empty($c['resposta'])): ?>
                                        <p class="muted"><strong>Resposta:</strong> <?php echo nl2br(Helpers::e($c['resposta'])); ?></p>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo Helpers::e(RevisaoComentarioService::rotuloStatus($c['status'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <form method="post" action="/revisor/comentario" class="admin-form">
            <?php echo $csrfField; ?>
            <input type="hidden" name="curso_evento_id" value="<?php echo (int) $curso['id']; ?>">
            <input type="hidden" name="alvo_tipo" value="quiz_pergunta">
            <input type="hidden" name="alvo_id" value="<?php echo $perguntaId; ?>">
            <input type="hidden" name="retorno" value="<?php echo Helpers::e($retorno); ?>">
            <label>
                Severidade
                <select name="severidade" required>
                    <option value="erro">Erro — o gabarito não se sustenta</option>
                    <option value="impreciso">Impreciso — ambíguo ou mal formulado</option>
                    <option value="sugestao" selected>Sugestão</option>
                    <option value="duvida">Dúvida</option>
                </select>
            </label>
            <label>
                Apontamento
                <textarea name="comentario" rows="3" required
                          placeholder="Ex.: a alternativa C também é defensável, porque..."></textarea>
            </label>
            <button type="submit">Registrar nesta questão</button>
        </form>
    </section>
<?php endforeach; ?>
</div>
