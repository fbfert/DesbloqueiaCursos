<?php use App\Core\Helpers; ?>
<?php
$item       = isset($item) && is_array($item) ? $item : array();
$quiz       = isset($quiz) && is_array($quiz) ? $quiz : null;
$blocos     = isset($blocos) && is_array($blocos) ? $blocos : array();
$composicao = isset($composicao) && is_array($composicao) ? $composicao : array('ok' => true, 'erros' => array(), 'alertas' => array());
$cursoId    = isset($curso_id) ? (int) $curso_id : 0;
$turmaId    = isset($turma_id) ? (int) $turma_id : 0;
$moduloId   = isset($modulo_id) ? (int) $modulo_id : 0;
$itemId     = (int) ($item['id'] ?? 0);

$sufixoUrl = '&curso_id=' . $cursoId
    . ($turmaId > 0 ? '&turma_id=' . $turmaId : '')
    . ($moduloId > 0 ? '&modulo_id=' . $moduloId : '');

$dificuldades = array('facil' => 'Fácil', 'media' => 'Média', 'dificil' => 'Difícil');
$porBlocos    = $quiz && (string) ($quiz['modo_selecao'] ?? 'todas') === 'blocos';
?>

<div class="admin-page admin-area-curso">
    <section class="admin-page__header">
        <div>
            <h1 class="admin-page__title">Blocos de sorteio</h1>
            <p class="admin-page__subtitle"><?php echo Helpers::e((string) ($item['titulo'] ?? '')); ?></p>
        </div>
        <div class="cta-group">
            <a class="button-link button-link--ghost" href="/admin/area-curso?curso_id=<?php echo $cursoId; ?>&amp;aba=conteudo">Voltar</a>
            <a class="button-link button-link--ghost" href="/admin/area-curso/conteudo/quiz/perguntas?item_id=<?php echo $itemId . $sufixoUrl; ?>">Banco de questões</a>
            <a class="button-link button-link--ghost" href="/admin/area-curso/conteudo/quiz/discursivas?item_id=<?php echo $itemId . $sufixoUrl; ?>">Discursivas</a>
        </div>
    </section>

    <?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
    <?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

    <?php if (!$porBlocos): ?>
        <section class="status-card" style="margin-bottom:16px; border-left:4px solid #f59e0b;">
            <p style="margin:0;">
                <strong>Este quiz ainda não usa banco de questões.</strong>
                Os blocos abaixo só entram em uso quando o modo de seleção do quiz estiver definido como
                <em>“Sortear por blocos”</em>, na edição do conteúdo.
            </p>
        </section>
    <?php endif; ?>

    <?php if (!empty($composicao['erros'])): ?>
        <section class="status-card" style="margin-bottom:16px; border-left:4px solid #dc2626;">
            <p style="margin:0 0 8px;"><strong>A composição atual impede publicar o simulado:</strong></p>
            <ul style="margin:0; padding-left:18px;">
                <?php foreach ($composicao['erros'] as $erro): ?>
                    <li><?php echo Helpers::e((string) $erro); ?></li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <?php if (!empty($composicao['alertas'])): ?>
        <section class="status-card" style="margin-bottom:16px; border-left:4px solid #f59e0b;">
            <p style="margin:0 0 8px;"><strong>Atenção:</strong></p>
            <ul style="margin:0; padding-left:18px;">
                <?php foreach ($composicao['alertas'] as $alerta): ?>
                    <li><?php echo Helpers::e((string) $alerta); ?></li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <section class="status-card" style="margin-bottom:16px;">
        <h2 style="margin-top:0;">Blocos cadastrados</h2>

        <?php if (empty($blocos)): ?>
            <p class="muted">Nenhum bloco cadastrado. Use o formulário abaixo para criar o primeiro.</p>
        <?php else: ?>
            <div style="overflow-x:auto;">
                <table class="admin-table" style="width:100%; border-collapse:collapse; min-width:760px;">
                    <thead>
                        <tr>
                            <th>Ordem</th>
                            <th>Código</th>
                            <th>Título</th>
                            <th>Tipo</th>
                            <th>Sorteia</th>
                            <th>Banco</th>
                            <th>Distribuição</th>
                            <th>No percentual</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($blocos as $bloco): ?>
                            <?php
                            $banco       = isset($bloco['banco']) ? $bloco['banco'] : array('total' => 0, 'facil' => 0, 'media' => 0, 'dificil' => 0);
                            $suficiente  = !empty($bloco['suficiente']);
                            $distribuicao = isset($bloco['distribuicao']) ? $bloco['distribuicao'] : null;
                            ?>
                            <tr>
                                <td style="text-align:center;"><?php echo (int) $bloco['ordem']; ?></td>
                                <td><code><?php echo Helpers::e((string) $bloco['codigo']); ?></code></td>
                                <td><?php echo Helpers::e((string) $bloco['titulo']); ?></td>
                                <td><?php echo (string) $bloco['tipo_questao'] === 'discursiva' ? 'Discursiva' : 'Múltipla escolha'; ?></td>
                                <td style="text-align:center;"><strong><?php echo (int) $bloco['quantidade_sortear']; ?></strong></td>
                                <td style="text-align:center;">
                                    <span class="pill <?php echo $suficiente ? 'pill--success' : 'pill--warning'; ?>">
                                        <?php echo (int) $banco['total']; ?>
                                    </span>
                                    <br>
                                    <small class="muted">
                                        F <?php echo (int) $banco['facil']; ?> ·
                                        M <?php echo (int) $banco['media']; ?> ·
                                        D <?php echo (int) $banco['dificil']; ?>
                                    </small>
                                </td>
                                <td>
                                    <?php if ($distribuicao === null): ?>
                                        <span class="muted">Livre</span>
                                    <?php else: ?>
                                        <?php foreach ($dificuldades as $chave => $rotulo): ?>
                                            <small style="display:block;">
                                                <?php echo Helpers::e($rotulo); ?>:
                                                <?php echo (float) $distribuicao[$chave]; ?>%
                                                <?php if (isset($bloco['cotas'][$chave])): ?>
                                                    (<?php echo (int) $bloco['cotas'][$chave]; ?>)
                                                <?php endif; ?>
                                            </small>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;"><?php echo !empty($bloco['conta_para_percentual']) ? 'Sim' : 'Não'; ?></td>
                                <td>
                                    <span class="pill <?php echo (string) $bloco['status'] === 'ativo' ? 'pill--success' : 'pill--muted'; ?>">
                                        <?php echo (string) $bloco['status'] === 'ativo' ? 'Ativo' : 'Inativo'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display:flex; gap:6px; flex-wrap:wrap;">
                                        <button type="button" class="button-link button-link--ghost" style="font-size:0.8em;"
                                                data-editar-bloco='<?php echo Helpers::e(json_encode(array(
                                                    'id'                     => (int) $bloco['id'],
                                                    'codigo'                 => (string) $bloco['codigo'],
                                                    'titulo'                 => (string) $bloco['titulo'],
                                                    'descricao'              => (string) ($bloco['descricao'] ?? ''),
                                                    'tipo_questao'           => (string) $bloco['tipo_questao'],
                                                    'quantidade_sortear'     => (int) $bloco['quantidade_sortear'],
                                                    'conta_para_percentual'  => (int) $bloco['conta_para_percentual'],
                                                    'obrigatorio_para_envio' => (int) $bloco['obrigatorio_para_envio'],
                                                    'ordem'                  => (int) $bloco['ordem'],
                                                    'status'                 => (string) $bloco['status'],
                                                    'distribuicao'           => $distribuicao,
                                                ), JSON_UNESCAPED_UNICODE)); ?>'>
                                            Editar
                                        </button>

                                        <form method="post" action="/admin/area-curso/conteudo/quiz/bloco/status" style="display:inline;">
                                            <?php echo $csrfField; ?>
                                            <input type="hidden" name="item_id" value="<?php echo $itemId; ?>">
                                            <input type="hidden" name="curso_id" value="<?php echo $cursoId; ?>">
                                            <input type="hidden" name="turma_id" value="<?php echo $turmaId; ?>">
                                            <input type="hidden" name="modulo_id" value="<?php echo $moduloId; ?>">
                                            <input type="hidden" name="bloco_id" value="<?php echo (int) $bloco['id']; ?>">
                                            <button type="submit" class="button-link button-link--ghost" style="font-size:0.8em;">
                                                <?php echo (string) $bloco['status'] === 'ativo' ? 'Desativar' : 'Ativar'; ?>
                                            </button>
                                        </form>

                                        <form method="post" action="/admin/area-curso/conteudo/quiz/bloco/excluir" style="display:inline;"
                                              onsubmit="var j = prompt('Informe a justificativa da exclusão (mínimo 5 caracteres):'); if (!j) { return false; } this.justificativa.value = j; return true;">
                                            <?php echo $csrfField; ?>
                                            <input type="hidden" name="item_id" value="<?php echo $itemId; ?>">
                                            <input type="hidden" name="curso_id" value="<?php echo $cursoId; ?>">
                                            <input type="hidden" name="turma_id" value="<?php echo $turmaId; ?>">
                                            <input type="hidden" name="modulo_id" value="<?php echo $moduloId; ?>">
                                            <input type="hidden" name="bloco_id" value="<?php echo (int) $bloco['id']; ?>">
                                            <input type="hidden" name="justificativa" value="">
                                            <button type="submit" class="button-link button-link--ghost" style="font-size:0.8em; color:#dc2626;">Excluir</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="status-card">
        <h2 id="form-bloco-titulo" style="margin-top:0;">Novo bloco</h2>

        <form method="post" action="/admin/area-curso/conteudo/quiz/bloco/salvar" id="form-bloco" class="form-grid">
            <?php echo $csrfField; ?>
            <input type="hidden" name="item_id" value="<?php echo $itemId; ?>">
            <input type="hidden" name="curso_id" value="<?php echo $cursoId; ?>">
            <input type="hidden" name="turma_id" value="<?php echo $turmaId; ?>">
            <input type="hidden" name="modulo_id" value="<?php echo $moduloId; ?>">
            <input type="hidden" name="bloco_id" id="bloco_id" value="0">

            <label>
                Código *
                <input type="text" name="codigo" id="bloco_codigo" maxlength="40" required
                       placeholder="Ex.: FGD, PEDAGOGIA, DISCURSIVA">
                <small class="muted">Letras maiúsculas, números, hífen ou sublinhado.</small>
            </label>

            <label>
                Título *
                <input type="text" name="titulo" id="bloco_titulo" maxlength="190" required
                       placeholder="Ex.: Formação Geral Docente">
            </label>

            <label style="grid-column: 1 / -1;">
                Descrição
                <textarea name="descricao" id="bloco_descricao" rows="2"></textarea>
            </label>

            <label>
                Tipo de questão
                <select name="tipo_questao" id="bloco_tipo_questao">
                    <option value="multipla_escolha">Múltipla escolha</option>
                    <option value="discursiva">Discursiva</option>
                </select>
            </label>

            <label>
                Quantidade a sortear *
                <input type="number" name="quantidade_sortear" id="bloco_quantidade" min="0" step="1" value="0" required>
                <small class="muted">Quantas questões deste bloco entram em cada tentativa.</small>
            </label>

            <label>
                Ordem
                <input type="number" name="ordem" id="bloco_ordem" min="0" step="1" placeholder="Automática">
            </label>

            <label>
                Status
                <select name="status" id="bloco_status">
                    <option value="ativo">Ativo</option>
                    <option value="inativo">Inativo</option>
                </select>
            </label>

            <label class="form-check">
                <input type="checkbox" name="conta_para_percentual" id="bloco_conta_percentual" value="1" checked>
                Conta para o percentual de aprovação
                <small class="muted">Desmarque para blocos informativos, como a discursiva.</small>
            </label>

            <label class="form-check">
                <input type="checkbox" name="obrigatorio_para_envio" id="bloco_obrigatorio" value="1" checked>
                Obrigatório para o envio da prova
            </label>

            <fieldset style="grid-column: 1 / -1; border:1px solid var(--color-border, #e5e7eb); border-radius:6px; padding:12px;">
                <legend style="padding:0 6px;">Distribuição de dificuldade</legend>

                <label class="form-check">
                    <input type="checkbox" name="usar_distribuicao" id="bloco_usar_distribuicao" value="1">
                    Distribuir o sorteio por dificuldade
                </label>

                <div id="bloco-distribuicao-campos" class="form-grid" style="margin-top:8px;" hidden>
                    <?php foreach ($dificuldades as $chave => $rotulo): ?>
                        <label>
                            <?php echo Helpers::e($rotulo); ?> (%)
                            <input type="number" name="distribuicao_<?php echo $chave; ?>"
                                   id="bloco_distribuicao_<?php echo $chave; ?>"
                                   min="0" max="100" step="0.01"
                                   value="<?php echo $chave === 'media' ? '60' : '20'; ?>">
                        </label>
                    <?php endforeach; ?>
                    <p class="muted" style="grid-column: 1 / -1; margin:0;">
                        A soma precisa ser 100%. O arredondamento é determinístico: com 30 questões em 20/60/20,
                        o sorteio traz 6 fáceis, 18 médias e 6 difíceis.
                    </p>
                </div>
            </fieldset>

            <div class="cta-group" style="grid-column: 1 / -1;">
                <button type="submit" class="button-link button-link--primary">Salvar bloco</button>
                <button type="button" class="button-link button-link--ghost" id="bloco-cancelar" hidden>Cancelar edição</button>
            </div>
        </form>
    </section>
</div>

<script>
(function () {
    var form         = document.getElementById('form-bloco');
    var titulo       = document.getElementById('form-bloco-titulo');
    var cancelar     = document.getElementById('bloco-cancelar');
    var usarDist     = document.getElementById('bloco_usar_distribuicao');
    var camposDist   = document.getElementById('bloco-distribuicao-campos');
    var tipoQuestao  = document.getElementById('bloco_tipo_questao');
    var contaPercent = document.getElementById('bloco_conta_percentual');
    if (!form) { return; }

    function alternarDistribuicao() {
        camposDist.hidden = !usarDist.checked;
    }
    usarDist.addEventListener('change', alternarDistribuicao);

    // Bloco discursivo nunca entra no percentual objetivo.
    tipoQuestao.addEventListener('change', function () {
        if (tipoQuestao.value === 'discursiva') {
            contaPercent.checked = false;
            contaPercent.disabled = true;
        } else {
            contaPercent.disabled = false;
            contaPercent.checked = true;
        }
    });

    function preencher(dados) {
        document.getElementById('bloco_id').value           = dados.id;
        document.getElementById('bloco_codigo').value       = dados.codigo;
        document.getElementById('bloco_titulo').value       = dados.titulo;
        document.getElementById('bloco_descricao').value    = dados.descricao || '';
        document.getElementById('bloco_quantidade').value   = dados.quantidade_sortear;
        document.getElementById('bloco_ordem').value        = dados.ordem;
        document.getElementById('bloco_status').value       = dados.status;
        tipoQuestao.value                                   = dados.tipo_questao;
        contaPercent.checked                                = !!dados.conta_para_percentual;
        contaPercent.disabled                               = dados.tipo_questao === 'discursiva';
        document.getElementById('bloco_obrigatorio').checked = !!dados.obrigatorio_para_envio;

        usarDist.checked = !!dados.distribuicao;
        if (dados.distribuicao) {
            ['facil', 'media', 'dificil'].forEach(function (chave) {
                document.getElementById('bloco_distribuicao_' + chave).value = dados.distribuicao[chave];
            });
        }
        alternarDistribuicao();

        titulo.textContent = 'Editar bloco ' + dados.codigo;
        cancelar.hidden = false;
        form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    Array.prototype.forEach.call(document.querySelectorAll('[data-editar-bloco]'), function (botao) {
        botao.addEventListener('click', function () {
            preencher(JSON.parse(botao.getAttribute('data-editar-bloco')));
        });
    });

    cancelar.addEventListener('click', function () {
        form.reset();
        document.getElementById('bloco_id').value = '0';
        contaPercent.disabled = false;
        titulo.textContent = 'Novo bloco';
        cancelar.hidden = true;
        alternarDistribuicao();
    });

    alternarDistribuicao();
})();
</script>
