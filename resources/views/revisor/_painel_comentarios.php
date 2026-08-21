<?php
use App\Core\Helpers;
use App\Core\Session;
use App\Services\RevisaoComentarioService;

/**
 * Painel de apontamentos, reutilizado pela leitura de aula e pela de questoes.
 *
 * Espera no escopo:
 *   $curso, $alvoTipo, $alvoId, $comentarios (lista), $retorno (url),
 *   $painelTitulo (opcional), $usuarioAtualId
 */
$usuarioAtualId = (int) Session::get('usuario_id');
$painelTitulo = isset($painelTitulo) ? $painelTitulo : 'Apontamentos deste conteúdo';
$comentarios = isset($comentarios) ? $comentarios : array();
?>
<section class="status-card">
    <strong><?php echo Helpers::e($painelTitulo); ?></strong>

    <?php if (empty($comentarios)): ?>
        <p class="muted">Nenhum apontamento registrado aqui ainda.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Severidade</th>
                        <th>Apontamento</th>
                        <th>Situação</th>
                        <th>Autor</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($comentarios as $c): ?>
                        <tr>
                            <td>
                                <span class="badge<?php echo $c['severidade'] === 'erro' ? ' badge--danger' : ''; ?>">
                                    <?php echo Helpers::e(RevisaoComentarioService::rotuloSeveridade($c['severidade'])); ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($c['trecho'])): ?>
                                    <blockquote class="muted"><?php echo Helpers::e($c['trecho']); ?></blockquote>
                                <?php endif; ?>
                                <?php echo nl2br(Helpers::e($c['comentario'])); ?>
                                <?php if (!empty($c['resposta'])): ?>
                                    <p class="muted"><strong>Resposta:</strong> <?php echo nl2br(Helpers::e($c['resposta'])); ?></p>
                                <?php endif; ?>
                            </td>
                            <td><?php echo Helpers::e(RevisaoComentarioService::rotuloStatus($c['status'])); ?></td>
                            <td class="muted"><?php echo Helpers::e($c['autor_nome'] ?? '—'); ?></td>
                            <td>
                                <?php if ($c['status'] === 'aberto' && (int) $c['autor_id'] === $usuarioAtualId): ?>
                                    <form method="post" action="/revisor/comentario/excluir" class="admin-form"
                                          onsubmit="return confirm('Excluir este apontamento?');">
                                        <?php echo $csrfField; ?>
                                        <input type="hidden" name="id" value="<?php echo (int) $c['id']; ?>">
                                        <input type="hidden" name="retorno" value="<?php echo Helpers::e($retorno); ?>">
                                        <input type="text" name="justificativa" placeholder="Motivo da exclusão" required>
                                        <button type="submit" class="button-link button-link--ghost">Excluir</button>
                                    </form>
                                <?php else: ?>
                                    <span class="muted">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <form method="post" action="/revisor/comentario" class="admin-form">
        <?php echo $csrfField; ?>
        <input type="hidden" name="curso_evento_id" value="<?php echo (int) $curso['id']; ?>">
        <input type="hidden" name="alvo_tipo" value="<?php echo Helpers::e($alvoTipo); ?>">
        <input type="hidden" name="alvo_id" value="<?php echo (int) $alvoId; ?>">
        <input type="hidden" name="retorno" value="<?php echo Helpers::e($retorno); ?>">

        <label>
            Severidade
            <select name="severidade" required>
                <option value="erro">Erro — o material está incorreto</option>
                <option value="impreciso">Impreciso — ambíguo ou mal formulado</option>
                <option value="sugestao" selected>Sugestão — melhoria de clareza</option>
                <option value="duvida">Dúvida — quero que o autor confirme</option>
            </select>
        </label>

        <label>
            Trecho comentado (opcional)
            <input type="text" name="trecho" placeholder="Cole aqui a frase exata a que o apontamento se refere">
        </label>

        <label>
            Apontamento
            <textarea name="comentario" rows="4" required
                      placeholder="Descreva o problema e, se possível, indique a correção."></textarea>
        </label>

        <button type="submit">Registrar apontamento</button>
    </form>
</section>
