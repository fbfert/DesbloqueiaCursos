<?php use App\Core\Helpers; ?>

<div class="admin-page">
<section class="admin-page__header">
    <div>
        <h1 class="admin-page__title">Área de revisão</h1>
        <p class="admin-page__subtitle">Cursos atribuídos a você para revisão técnica. Você lê o material e registra apontamentos; nada do que você escreve altera o conteúdo diretamente.</p>
    </div>
</section>

<?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
<?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

<?php if (empty($cursos)): ?>
    <section class="status-card">
        <strong>Nenhum curso atribuído</strong>
        <p class="muted">Assim que a coordenação vincular você a um curso como revisor, ele aparece aqui.</p>
    </section>
<?php else: ?>
    <section class="status-card">
        <strong>Cursos para revisar</strong>
        <div class="table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Curso</th>
                        <th>Situação do curso</th>
                        <th>Em aberto</th>
                        <th>Erros em aberto</th>
                        <th>Já tratados</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cursos as $curso): ?>
                        <?php $r = $curso['resumo']; $tratados = $r['aceito'] + $r['recusado'] + $r['resolvido']; ?>
                        <tr>
                            <td><strong><?php echo Helpers::e($curso['nome']); ?></strong></td>
                            <td><span class="badge"><?php echo Helpers::e($curso['status']); ?></span></td>
                            <td><?php echo (int) $r['aberto']; ?></td>
                            <td>
                                <?php if ($r['erros_abertos'] > 0): ?>
                                    <strong><?php echo (int) $r['erros_abertos']; ?></strong>
                                <?php else: ?>
                                    <span class="muted">0</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo (int) $tratados; ?></td>
                            <td>
                                <a class="button-link" href="/revisor/curso?curso_id=<?php echo (int) $curso['id']; ?>">Abrir</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="status-card">
        <strong>Como classificar um apontamento</strong>
        <div class="table-wrap">
            <table class="admin-table">
                <tbody>
                    <tr><td><strong>Erro</strong></td><td>O material está incorreto: gabarito que não se sustenta, legislação revogada, tese superada. É o que trava a publicação.</td></tr>
                    <tr><td><strong>Impreciso</strong></td><td>Não está errado, mas está mal formulado, ambíguo ou incompleto a ponto de induzir o aluno ao erro.</td></tr>
                    <tr><td><strong>Sugestão</strong></td><td>Melhoria de clareza, exemplo ou ordem. Não impede a publicação.</td></tr>
                    <tr><td><strong>Dúvida</strong></td><td>Você não tem certeza e quer que o autor confirme.</td></tr>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>
</div>
