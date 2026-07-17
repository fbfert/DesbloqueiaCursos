<?php use App\Core\Helpers; ?>
<?php $isSuperAdmin = !empty($is_superadmin); ?>
<style>
.email-modelos-nome-link { color: inherit; text-decoration: none; }
.email-modelos-nome-link:hover,
.email-modelos-nome-link:focus { color: var(--cor-laranja, #FF6A00); text-decoration: underline; }
</style>
<div class="admin-page">
    <section class="admin-page__header">
        <div>
            <h1 class="admin-page__title">Modelos de e-mail</h1>
            <p class="admin-page__subtitle">Edite assunto, corpo, gatilho e status dos e-mails transacionais.</p>
        </div>
        <div class="admin-page__actions">
            <a class="button-link button-link--ghost" href="/admin/emails">Voltar para e-mails</a>
            <a class="button-link button-link--primary" href="/admin/emails/modelos/criar">Novo modelo</a>
        </div>
    </section>

    <?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
    <?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

    <section class="admin-section">
        <div class="admin-section__header">
            <h2 class="admin-section__title">Modelos cadastrados</h2>
            <p class="muted">Os gatilhos automáticos continuam ligados ao fluxo do sistema.</p>
        </div>

        <form method="get" action="/admin/emails/modelos" class="admin-form" style="margin-bottom:12px;">
            <div class="split-actions">
                <label style="flex:1;min-width:260px;">
                    Buscar
                    <input type="text" name="q" value="<?php echo Helpers::e($busca ?? ''); ?>" placeholder="Buscar por nome, assunto, código ou conteúdo">
                </label>
            </div>
            <div class="cta-group">
                <button type="submit" class="button-link button-link--primary">Buscar</button>
                <?php if (!empty($busca)): ?>
                    <a class="button-link button-link--ghost" href="/admin/emails/modelos">Limpar</a>
                <?php endif; ?>
            </div>
        </form>

        <?php if (!empty($busca)): ?>
            <p class="muted" style="margin:0 0 12px 0;">
                Exibindo <?php echo (int) count($modelos); ?> resultado(s) para
                &ldquo;<?php echo Helpers::e($busca); ?>&rdquo;.
            </p>
        <?php endif; ?>

        <div class="table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Evento</th>
                        <th>Template</th>
                        <th>Assunto</th>
                        <th>Variáveis</th>
                        <th>Status</th>
                        <th>Atualizado</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($modelos)): ?>
                        <tr>
                            <td colspan="8">
                                <?php if (!empty($busca)): ?>
                                    Nenhum modelo de e-mail foi encontrado para a busca informada.
                                <?php else: ?>
                                    Nenhum modelo de e-mail encontrado.
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($modelos as $modelo): ?>
                        <?php
                        $variaveis = array();
                        if (!empty($modelo['variaveis_json'])) {
                            $decodificado = json_decode((string) $modelo['variaveis_json'], true);
                            if (is_array($decodificado)) {
                                $variaveis = $decodificado;
                            }
                        }
                        $ativo = !empty($modelo['ativo']);

                        // Reutiliza exatamente a rota/parâmetros da ação "Editar" existente.
                        $editUrl = null;
                        if (!empty($modelo['id'])) {
                            $editUrl = '/admin/emails/modelos/editar?modelo_id=' . (int) $modelo['id'];
                        } elseif (!empty($modelo['is_default_event'])) {
                            $editUrl = '/admin/emails/modelos/editar?evento=' . urlencode((string) ($modelo['evento'] ?? ''));
                        }
                        ?>
                        <tr>
                            <td>
                                <?php if ($editUrl !== null): ?>
                                    <a class="email-modelos-nome-link" href="<?php echo Helpers::e($editUrl); ?>">
                                        <strong><?php echo Helpers::e($modelo['nome'] ?? ''); ?></strong>
                                    </a><br>
                                <?php else: ?>
                                    <strong><?php echo Helpers::e($modelo['nome'] ?? ''); ?></strong><br>
                                <?php endif; ?>
                                <span class="muted"><?php echo Helpers::e($modelo['gatilho_descricao'] ?? ''); ?></span>
                            </td>
                            <td><code><?php echo Helpers::e($modelo['evento'] ?? ''); ?></code></td>
                            <td><code><?php echo Helpers::e($modelo['template'] ?? ''); ?></code></td>
                            <td><?php echo Helpers::e($modelo['assunto'] ?? ''); ?></td>
                            <td>
                                <?php if (empty($variaveis)): ?>
                                    <span class="badge badge--soft">Sem variáveis</span>
                                <?php else: ?>
                                    <span class="badge badge--soft"><?php echo Helpers::e(implode(', ', array_slice($variaveis, 0, 3))); ?></span>
                                    <?php if (count($variaveis) > 3): ?>
                                        <span class="badge badge--soft">+<?php echo (int) (count($variaveis) - 3); ?></span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?php echo $ativo ? 'badge--success' : 'badge--warning'; ?>">
                                    <?php echo $ativo ? 'Ativo' : 'Inativo'; ?>
                                </span>
                            </td>
                            <td><?php echo Helpers::e((string) ($modelo['updated_at'] ?? $modelo['created_at'] ?? '')); ?></td>
                            <td>
                                <div class="cta-group">
                                    <?php if (!empty($modelo['id'])): ?>
                                        <a class="button-link button-link--ghost" href="/admin/emails/modelos/editar?modelo_id=<?php echo (int) $modelo['id']; ?>">Editar</a>
                                        <?php if (empty($modelo['is_default_event']) || $isSuperAdmin): ?>
                                            <form method="post" action="/admin/emails/modelos/status" style="display:inline-block;">
                                                <?php echo $csrfField; ?>
                                                <input type="hidden" name="id" value="<?php echo (int) $modelo['id']; ?>">
                                                <input type="hidden" name="ativo" value="<?php echo $ativo ? 0 : 1; ?>">
                                                <button type="submit" class="button-link"><?php echo $ativo ? 'Desativar' : 'Ativar'; ?></button>
                                            </form>
                                            <?php if (!empty($modelo['is_default_event'])): ?>
                                                <form method="post" action="/admin/emails/modelos/restaurar-padrao" style="display:inline-block;">
                                                    <?php echo $csrfField; ?>
                                                    <input type="hidden" name="id" value="<?php echo (int) $modelo['id']; ?>">
                                                    <button type="submit" class="button-link">Restaurar padrão</button>
                                                </form>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <?php if (!empty($modelo['is_default_event'])): ?>
                                            <a class="button-link button-link--ghost" href="/admin/emails/modelos/editar?evento=<?php echo urlencode((string) $modelo['evento']); ?>">Editar</a>
                                            <span class="muted">Modelo padrão do sistema.</span>
                                        <?php else: ?>
                                            <span class="muted">Modelo indisponível para edição.</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
