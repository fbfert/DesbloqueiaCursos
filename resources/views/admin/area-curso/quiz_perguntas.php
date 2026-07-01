<?php use App\Core\Helpers; ?>
<?php
$item      = isset($item) && is_array($item) ? $item : array();
$quiz      = isset($quiz) && is_array($quiz) ? $quiz : null;
$validacao = isset($validacao) && is_array($validacao) ? $validacao : array('ok' => false, 'erros' => array());
$cursoId   = isset($curso_id) ? (int) $curso_id : 0;
$turmaId   = isset($turma_id) ? (int) $turma_id : 0;
$moduloId  = isset($modulo_id) ? (int) $modulo_id : 0;
$itemId    = (int) ($item['id'] ?? 0);
$perguntas = isset($quiz['perguntas']) && is_array($quiz['perguntas']) ? $quiz['perguntas'] : array();
$voltarUrl = '/admin/area-curso?curso_id=' . $cursoId . '&aba=conteudo' . ($moduloId > 0 ? '&modulo_id=' . $moduloId : '');
$statusConteudo = (string) ($item['status'] ?? '');
$totalPerguntas = count($perguntas);
$situacaoValidacao = !empty($validacao['ok'])
    ? 'Quiz válido para aplicação aos alunos.'
    : (!empty($perguntas) ? 'Adicione ao menos uma pergunta válida para publicar o quiz.' : 'Este quiz ainda não possui perguntas.');

$alternativaLetra = function ($index) {
    $index = (int) $index;
    if ($index < 0) {
        return '';
    }

    $index += 1;
    $letra = '';
    while ($index > 0) {
        $resto = ($index - 1) % 26;
        $letra = chr(65 + $resto) . $letra;
        $index = (int) floor(($index - 1) / 26);
    }

    return $letra;
};

$renderAlternativa = function ($idx, array $alt = array(), $placeholder = 'Nova alternativa', $checked = false, $showRemove = true) use ($alternativaLetra) {
    ob_start();
    $indiceCampo = (string) $idx;
    $indiceOrdem = is_numeric($idx) ? (int) $idx + 1 : '__ORDEM__';
    $letra = is_numeric($idx) ? $alternativaLetra((int) $idx) : '__LETRA__';
    $classeCorreta = $checked ? ' quiz-alternativa-linha--correta' : '';
    ?>
    <div class="quiz-alternativa-linha<?php echo $classeCorreta; ?>" data-alternativa-row>
        <div class="quiz-alternativa-letra" aria-hidden="true"><?php echo Helpers::e($letra); ?></div>
        <div class="quiz-alternativa-correta">
            <label class="quiz-alternativa-radio">
                <input type="radio" name="alternativa_correta" value="<?php echo Helpers::e($indiceCampo); ?>" <?php echo $checked ? 'checked' : ''; ?> title="Marcar como correta">
                <span>Correta</span>
            </label>
            <span class="quiz-alternativa-correta__badge"<?php echo $checked ? '' : ' hidden'; ?>>Resposta correta</span>
        </div>
        <input type="hidden" name="alternativa_id[<?php echo Helpers::e($indiceCampo); ?>]" value="<?php echo Helpers::e((string) ($alt['id'] ?? 0)); ?>">
        <label class="quiz-alternativa-texto">
            <span>Texto da alternativa</span>
            <textarea name="alternativa_texto[<?php echo Helpers::e($indiceCampo); ?>]" rows="2" <?php echo empty($alt) ? 'placeholder="' . Helpers::e($placeholder) . '"' : 'required'; ?>><?php echo Helpers::e((string) ($alt['texto'] ?? '')); ?></textarea>
        </label>
        <label class="quiz-alternativa-ordem">
            <span>Ordem</span>
            <input type="number" name="alternativa_ordem[<?php echo Helpers::e($indiceCampo); ?>]" value="<?php echo Helpers::e((string) ($alt['ordem'] ?? $indiceOrdem)); ?>" min="1" step="1" title="Ordem">
        </label>
        <button type="button" class="button-link button-link--danger-ghost quiz-alternativa-remover" data-remover-alternativa <?php echo $showRemove ? '' : 'hidden'; ?>>Remover</button>
    </div>
    <?php
    return ob_get_clean();
};
?>

<div class="admin-page admin-area-curso quiz-editor-page">
    <section class="admin-page__header quiz-editor-header-card">
        <div class="quiz-editor-header-card__context">
            <div class="quiz-editor-breadcrumb">Admin / Área do curso / Conteúdo / Quiz</div>
            <h1 class="admin-page__title quiz-editor-title">Perguntas do Quiz</h1>
            <p class="admin-page__subtitle quiz-editor-subtitle"><?php echo Helpers::e((string) ($item['titulo'] ?? '')); ?></p>
            <div class="quiz-editor-meta">
                <span class="pill pill--soft">Perguntas: <?php echo $totalPerguntas; ?></span>
                <span class="pill <?php echo $statusConteudo === 'publicado' ? 'pill--success' : 'pill--warning'; ?>">Conteúdo: <?php echo Helpers::e($statusConteudo !== '' ? ucfirst($statusConteudo) : 'Sem status'); ?></span>
                <span class="pill <?php echo !empty($validacao['ok']) ? 'pill--success' : ($totalPerguntas > 0 ? 'pill--warning' : 'pill--danger'); ?>"><?php echo Helpers::e($situacaoValidacao); ?></span>
            </div>
        </div>
        <div class="quiz-editor-actions quiz-editor-actions--header">
            <a class="button-link button-link--ghost" href="<?php echo Helpers::e($voltarUrl); ?>">Voltar ao conteúdo</a>
            <?php if ($quiz): ?>
                <a class="button-link button-link--ghost" href="/admin/area-curso/conteudo/quiz/preview?item_id=<?php echo $itemId; ?>&curso_id=<?php echo $cursoId; ?>">Pré-visualizar</a>
                <a class="button-link button-link--ghost" href="/admin/area-curso/conteudo/quiz/resultados?item_id=<?php echo $itemId; ?>&curso_id=<?php echo $cursoId; ?><?php echo $turmaId > 0 ? '&turma_id=' . $turmaId : ''; ?>">Resultados</a>
            <?php endif; ?>
        </div>
    </section>

    <?php require BASE_PATH . '/resources/views/auth/_errors.php'; ?>
    <?php require BASE_PATH . '/resources/views/auth/_success.php'; ?>

    <?php if (!$quiz): ?>
        <section class="status-card quiz-editor-status-card quiz-editor-status-card--warning">
            <strong>Este item ainda não tem configuração de quiz salva.</strong>
            <p>Edite o item e salve as configurações do quiz primeiro.</p>
        </section>
    <?php elseif (!empty($validacao['erros'])): ?>
        <section class="status-card quiz-editor-status-card quiz-editor-status-card--warning">
            <strong>Quiz com pendências.</strong>
            <p><?php echo $totalPerguntas === 0 ? 'Este quiz ainda não possui perguntas.' : 'Revise as perguntas e alternativas antes de publicar.'; ?></p>
            <ul class="quiz-editor-status-card__list">
                <?php foreach ($validacao['erros'] as $erro): ?>
                    <li><?php echo Helpers::e((string) $erro); ?></li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php else: ?>
        <section class="status-card quiz-editor-status-card quiz-editor-status-card--success">
            <strong>Quiz válido para aplicação aos alunos.</strong>
            <p><?php echo Helpers::e($situacaoValidacao); ?></p>
        </section>
    <?php endif; ?>

    <section class="status-card quiz-editor-questions-card">
        <div class="quiz-editor-card__header">
            <div class="quiz-editor-card__header-copy">
                <h2 class="quiz-editor-card__title">Perguntas cadastradas</h2>
                <p class="quiz-editor-card__subtitle">Visualize, mova e edite as perguntas já salvas no quiz.</p>
            </div>
            <div class="quiz-editor-card__header-actions">
                <span class="pill pill--soft">Total: <?php echo $totalPerguntas; ?></span>
                <span class="pill <?php echo !empty($validacao['ok']) ? 'pill--success' : ($totalPerguntas > 0 ? 'pill--warning' : 'pill--danger'); ?>">Validação: <?php echo Helpers::e(!empty($validacao['ok']) ? 'OK' : ($totalPerguntas > 0 ? 'Pendente' : 'Sem perguntas')); ?></span>
                <a class="button-link button-link--primary" href="#nova-pergunta">Adicionar pergunta</a>
            </div>
        </div>

        <?php if (!empty($perguntas)): ?>
            <div class="quiz-question-list" data-question-list>
                <?php foreach ($perguntas as $idx => $pergunta): ?>
                        <?php
                        $alts = isset($pergunta['alternativas']) && is_array($pergunta['alternativas']) ? $pergunta['alternativas'] : array();
                        $alternativasCount = count($alts);
                        $corretasCount = count(array_filter($alts, function ($a) { return !empty($a['correta']); }));
                        $enunciadoResumo = mb_substr((string) ($pergunta['enunciado'] ?? ''), 0, 110);
                        ?>
                        <details class="quiz-question-card" data-question-card data-question-id="<?php echo (int) $pergunta['id']; ?>">
                            <summary class="quiz-question-card__summary quiz-editor-question-summary">
                                <div class="quiz-question-card__summary-main">
                                    <span class="quiz-question-card__index">#<?php echo $idx + 1; ?></span>
                                    <div class="quiz-question-card__summary-text">
                                        <strong class="quiz-question-card__title"><?php echo Helpers::e($enunciadoResumo !== '' ? $enunciadoResumo : 'Pergunta sem enunciado'); ?></strong>
                                        <div class="quiz-question-card__summary-meta">
                                            <span class="pill pill--soft"><?php echo $alternativasCount; ?> alternativas</span>
                                            <span class="pill <?php echo $corretasCount > 0 ? 'pill--success' : 'pill--warning'; ?>"><?php echo $corretasCount; ?> correta(s)</span>
                                            <span class="pill pill--soft">Peso <?php echo Helpers::e((string) ($pergunta['peso'] ?? '1')); ?></span>
                                            <span class="pill <?php echo !empty($pergunta['obrigatoria']) ? 'pill--success' : 'pill--warning'; ?>"><?php echo !empty($pergunta['obrigatoria']) ? 'Obrigatória' : 'Opcional'; ?></span>
                                            <span class="pill pill--soft">Ordem <?php echo (int) ($pergunta['ordem'] ?? ($idx + 1)); ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="quiz-question-card__summary-actions">
                                    <button type="button" class="button-link button-link--ghost quiz-question-card__action" data-question-toggle>Editar</button>
                                    <button type="button" class="button-link button-link--ghost quiz-question-card__action" data-question-move="up">Mover para cima</button>
                                    <button type="button" class="button-link button-link--ghost quiz-question-card__action" data-question-move="down">Mover para baixo</button>
                                </div>
                            </summary>
                            <div class="quiz-question-card__body">
                                <form method="post" action="/admin/area-curso/conteudo/quiz/pergunta/salvar" class="quiz-question-form" data-quiz-form>
                                    <?php echo $csrfField; ?>
                                    <input type="hidden" name="item_id" value="<?php echo $itemId; ?>">
                                    <input type="hidden" name="curso_id" value="<?php echo $cursoId; ?>">
                                    <input type="hidden" name="turma_id" value="<?php echo $turmaId; ?>">
                                    <input type="hidden" name="modulo_id" value="<?php echo $moduloId; ?>">
                                    <input type="hidden" name="pergunta_id" value="<?php echo (int) $pergunta['id']; ?>">

                                    <section class="quiz-question-form__section-card">
                                        <div class="quiz-question-form__section-card__head">
                                            <div>
                                                <strong>Enunciado</strong>
                                                <p>Escreva a pergunta de forma objetiva.</p>
                                            </div>
                                        </div>
                                        <label class="quiz-question-form__full">
                                            <span>Enunciado</span>
                                            <textarea name="enunciado" rows="4" required><?php echo Helpers::e((string) ($pergunta['enunciado'] ?? '')); ?></textarea>
                                        </label>
                                    </section>

                                    <section class="quiz-question-form__section-card">
                                        <div class="quiz-question-form__section-card__head">
                                            <div>
                                                <strong>Configurações da pergunta</strong>
                                                <p>Defina peso, ordem e obrigatoriedade.</p>
                                            </div>
                                        </div>
                                        <div class="quiz-question-form__grid">
                                            <label>
                                                <span>Peso</span>
                                                <input type="number" name="peso" min="0.01" step="0.01" value="<?php echo Helpers::e((string) ($pergunta['peso'] ?? '1')); ?>">
                                            </label>
                                            <label>
                                                <span>Ordem</span>
                                                <input type="number" name="ordem" min="1" step="1" value="<?php echo Helpers::e((string) ($pergunta['ordem'] ?? ($idx + 1))); ?>">
                                            </label>
                                            <label class="quiz-question-form__checkbox">
                                                <input type="checkbox" name="obrigatoria" value="1" <?php echo !empty($pergunta['obrigatoria']) ? 'checked' : ''; ?>>
                                                <span>Pergunta obrigatória</span>
                                            </label>
                                        </div>

                                        <label class="quiz-question-form__full quiz-question-form__explicacao">
                                            <span>Explicação / Comentário</span>
                                            <textarea name="explicacao" rows="3"><?php echo Helpers::e((string) ($pergunta['explicacao'] ?? '')); ?></textarea>
                                        </label>
                                    </section>

                                    <section class="quiz-question-form__section-card">
                                        <div class="quiz-question-form__section-card__head quiz-question-form__section-card__head--split">
                                            <div>
                                                <strong>Alternativas</strong>
                                                <p>Marque uma única alternativa correta.</p>
                                            </div>
                                            <div class="quiz-question-form__section-card__meta">
                                                <span class="pill pill--soft" data-alternativas-count><?php echo $alternativasCount; ?> alternativas</span>
                                                <span class="quiz-question-form__section-card__note">Mínimo de 2 alternativas.</span>
                                            </div>
                                        </div>

                                        <div class="quiz-alternativas" data-alternativas-list data-next-alternativa-index="<?php echo count($alts) + 2; ?>">
                                            <?php foreach ($alts as $altIdx => $alt): ?>
                                                <?php echo $renderAlternativa($altIdx, $alt, 'Nova alternativa', !empty($alt['correta']), true); ?>
                                            <?php endforeach; ?>
                                            <?php for ($n = count($alts); $n < count($alts) + 2; $n++): ?>
                                                <?php echo $renderAlternativa($n, array(), 'Nova alternativa (opcional)', false, true); ?>
                                            <?php endfor; ?>
                                        </div>

                                        <template data-alternativa-template>
                                            <?php echo $renderAlternativa('__INDEX__', array(), 'Nova alternativa', false, true); ?>
                                        </template>
                                    </section>

                                    <div class="quiz-question-form__actions">
                                        <button type="button" class="button-link button-link--ghost" data-adicionar-alternativa>Adicionar alternativa</button>
                                        <button type="submit" class="button-link button-link--primary">Salvar pergunta</button>
                                    </div>
                                </form>

                                <form method="post" action="/admin/area-curso/conteudo/quiz/pergunta/excluir" class="quiz-question-form__delete" onsubmit="return confirm('Excluir esta pergunta? A ação não pode ser desfeita se não houver respostas.');">
                                    <?php echo $csrfField; ?>
                                    <input type="hidden" name="item_id" value="<?php echo $itemId; ?>">
                                    <input type="hidden" name="curso_id" value="<?php echo $cursoId; ?>">
                                    <input type="hidden" name="turma_id" value="<?php echo $turmaId; ?>">
                                    <input type="hidden" name="modulo_id" value="<?php echo $moduloId; ?>">
                                    <input type="hidden" name="pergunta_id" value="<?php echo (int) $pergunta['id']; ?>">
                                    <button type="submit" class="button-link button-link--danger-ghost">Excluir pergunta</button>
                                </form>
                            </div>
                        </details>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <article class="quiz-editor-empty">
                <strong>Nenhuma pergunta cadastrada ainda.</strong>
                <p>Utilize o formulário abaixo para criar a primeira pergunta.</p>
            </article>
        <?php endif; ?>
    </section>

    <section class="status-card quiz-editor-form-card" id="nova-pergunta" tabindex="-1">
        <div class="quiz-editor-card__header">
            <div class="quiz-editor-card__header-copy">
                <h2 class="quiz-editor-card__title">Nova pergunta</h2>
                <p class="quiz-editor-card__subtitle">Informe o enunciado, as alternativas e marque uma única resposta correta.</p>
            </div>
        </div>

        <form method="post" action="/admin/area-curso/conteudo/quiz/pergunta/salvar" class="quiz-question-form quiz-question-form--create" data-quiz-form>
                <?php echo $csrfField; ?>
                <input type="hidden" name="item_id" value="<?php echo $itemId; ?>">
                <input type="hidden" name="curso_id" value="<?php echo $cursoId; ?>">
                <input type="hidden" name="turma_id" value="<?php echo $turmaId; ?>">
                <input type="hidden" name="modulo_id" value="<?php echo $moduloId; ?>">
                <input type="hidden" name="pergunta_id" value="0">

                <section class="quiz-question-form__section-card">
                    <div class="quiz-question-form__section-card__head">
                        <div>
                            <strong>Enunciado</strong>
                            <p>Escreva a pergunta com clareza para o aluno.</p>
                        </div>
                    </div>
                    <label class="quiz-question-form__full">
                        <span>Enunciado</span>
                        <textarea name="enunciado" rows="4" required placeholder="Digite o enunciado da pergunta..."></textarea>
                    </label>
                </section>

                <section class="quiz-question-form__section-card">
                    <div class="quiz-question-form__section-card__head">
                        <div>
                            <strong>Configurações da pergunta</strong>
                            <p>Defina peso, ordem e obrigatoriedade.</p>
                        </div>
                    </div>
                    <div class="quiz-question-form__grid">
                        <label>
                            <span>Peso</span>
                            <input type="number" name="peso" min="0.01" step="0.01" value="1">
                        </label>
                        <label>
                            <span>Ordem</span>
                            <input type="number" name="ordem" min="1" step="1" value="<?php echo $totalPerguntas + 1; ?>">
                        </label>
                        <label class="quiz-question-form__checkbox">
                            <input type="checkbox" name="obrigatoria" value="1" checked>
                            <span>Pergunta obrigatória</span>
                        </label>
                    </div>

                    <label class="quiz-question-form__full quiz-question-form__explicacao">
                        <span>Explicação / Comentário</span>
                        <textarea name="explicacao" rows="3" placeholder="Explicação sobre a resposta correta..."></textarea>
                    </label>
                </section>

                <section class="quiz-question-form__section-card">
                    <div class="quiz-question-form__section-card__head quiz-question-form__section-card__head--split">
                        <div>
                            <strong>Alternativas</strong>
                            <p>Marque uma única alternativa correta.</p>
                        </div>
                        <div class="quiz-question-form__section-card__meta">
                            <span class="pill pill--soft" data-alternativas-count>4 alternativas</span>
                            <span class="quiz-question-form__section-card__note">Mínimo de 2 alternativas.</span>
                        </div>
                    </div>

                    <div class="quiz-alternativas" data-alternativas-list data-next-alternativa-index="4">
                        <?php for ($i = 0; $i < 4; $i++): ?>
                            <?php echo $renderAlternativa($i, array(), 'Alternativa ' . chr(65 + $i) . ($i < 2 ? ' *' : ' (opcional)'), $i === 0, true); ?>
                        <?php endfor; ?>
                    </div>

                    <template data-alternativa-template>
                        <?php echo $renderAlternativa('__INDEX__', array(), 'Nova alternativa', false, true); ?>
                    </template>
                </section>

                <div class="quiz-question-form__actions">
                    <button type="button" class="button-link button-link--ghost" data-adicionar-alternativa>Adicionar alternativa</button>
                    <button type="submit" class="button-link button-link--primary">Salvar pergunta</button>
                </div>
        </form>
    </section>
</div>

<script>
(function () {
    function getCsrfToken() {
        var tokenField = document.querySelector('input[name="_token"]');
        return tokenField ? tokenField.value : '';
    }

    function indexToLetter(index) {
        var value = parseInt(index, 10);
        if (!value && value !== 0) {
            return '';
        }

        value += 1;
        var result = '';

        while (value > 0) {
            var resto = (value - 1) % 26;
            result = String.fromCharCode(65 + resto) + result;
            value = Math.floor((value - 1) / 26);
        }

        return result;
    }

    function updateAlternativeMeta(form) {
        var count = form.querySelectorAll('[data-alternativa-row]').length;
        var countLabel = form.querySelector('[data-alternativas-count]');
        if (countLabel) {
            countLabel.textContent = count + ' alternativas';
        }
    }

    function syncCorrectState(form) {
        var rows = form.querySelectorAll('[data-alternativa-row]');
        rows.forEach(function (row) {
            var radio = row.querySelector('input[type="radio"]');
            var badge = row.querySelector('.quiz-alternativa-correta__badge');
            if (!radio) {
                return;
            }
            if (radio.checked) {
                row.classList.add('quiz-alternativa-linha--correta');
                if (badge) {
                    badge.hidden = false;
                }
            } else {
                row.classList.remove('quiz-alternativa-linha--correta');
                if (badge) {
                    badge.hidden = true;
                }
            }
        });
    }

    function updateRemoveButtons(form) {
        var rows = form.querySelectorAll('[data-alternativa-row]');
        var allowRemoval = rows.length > 2;
        form.querySelectorAll('[data-remover-alternativa]').forEach(function (button) {
            button.hidden = !allowRemoval;
        });
        updateAlternativeMeta(form);
    }

    function addAlternativeRow(form, triggerButton) {
        var list = form.querySelector('[data-alternativas-list]');
        var template = form.querySelector('[data-alternativa-template]');
        if (!list || !template || !template.content) {
            return;
        }

        var nextIndex = parseInt(list.getAttribute('data-next-alternativa-index') || '0', 10);
        if (!nextIndex || nextIndex < 0) {
            nextIndex = list.querySelectorAll('[data-alternativa-row]').length;
        }

        var order = list.querySelectorAll('[data-alternativa-row]').length + 1;
        var fragment = template.content.cloneNode(true);
        var row = fragment.firstElementChild;
        if (!row) {
            return;
        }

        row.innerHTML = row.innerHTML
            .replace(/__INDEX__/g, String(nextIndex))
            .replace(/__ORDEM__/g, String(order))
            .replace(/__LETRA__/g, indexToLetter(order - 1));

        list.appendChild(row);
        list.setAttribute('data-next-alternativa-index', String(nextIndex + 1));
        updateRemoveButtons(form);
        syncCorrectState(form);

        var firstText = row.querySelector('textarea[name^="alternativa_texto"]');
        if (firstText) {
            firstText.focus();
        } else if (triggerButton && triggerButton.blur) {
            triggerButton.blur();
        }
    }

    function bindQuizForm(form) {
        if (!form || form.getAttribute('data-quiz-editor-bound') === '1') {
            return;
        }
        form.setAttribute('data-quiz-editor-bound', '1');
        updateRemoveButtons(form);
        syncCorrectState(form);

        form.addEventListener('change', function (event) {
            if (event.target && event.target.matches('input[type="radio"][name="alternativa_correta"]')) {
                syncCorrectState(form);
            }
        });

        form.addEventListener('click', function (event) {
            var addButton = event.target.closest('[data-adicionar-alternativa]');
            if (addButton) {
                event.preventDefault();
                addAlternativeRow(form, addButton);
                return;
            }

            var removeButton = event.target.closest('[data-remover-alternativa]');
            if (removeButton) {
                event.preventDefault();
                var row = removeButton.closest('[data-alternativa-row]');
                var list = form.querySelector('[data-alternativas-list]');
                if (!row || !list) {
                    return;
                }

                if (list.querySelectorAll('[data-alternativa-row]').length <= 2) {
                    return;
                }

                var wasChecked = !!row.querySelector('input[type="radio"]:checked');
                row.remove();

                if (wasChecked) {
                    var fallback = list.querySelector('input[type="radio"]');
                    if (fallback) {
                        fallback.checked = true;
                    }
                }

                updateRemoveButtons(form);
                syncCorrectState(form);
            }
        });
    }

    function collectOrders(list) {
        var ordens = {};
        var cards = list.querySelectorAll('[data-question-card]');
        cards.forEach(function (card, index) {
            var questionId = card.getAttribute('data-question-id');
            if (questionId) {
                ordens[questionId] = index + 1;
            }
        });
        return ordens;
    }

    function reorderQuestions(card, direction) {
        var list = card.closest('[data-question-list]');
        if (!list) {
            return;
        }

        var cards = Array.prototype.slice.call(list.querySelectorAll('[data-question-card]'));
        var index = cards.indexOf(card);
        if (index < 0) {
            return;
        }

        if (direction === 'up' && index > 0) {
            list.insertBefore(card, cards[index - 1]);
        } else if (direction === 'down' && index < cards.length - 1) {
            list.insertBefore(cards[index + 1], card);
        } else {
            return;
        }

        var formData = new FormData();
        formData.append('_token', getCsrfToken());
        formData.append('item_id', <?php echo (int) $itemId; ?>);
        formData.append('curso_id', <?php echo (int) $cursoId; ?>);

        var ordens = collectOrders(list);
        Object.keys(ordens).forEach(function (questionId) {
            formData.append('ordens[' + questionId + ']', String(ordens[questionId]));
        });

        fetch('/admin/area-curso/conteudo/quiz/perguntas/reordenar', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(function (response) {
            return response.json();
        })
        .then(function (payload) {
            if (!payload || !payload.ok) {
                throw new Error((payload && payload.message) ? payload.message : 'Não foi possível reordenar as perguntas.');
            }
            window.location.reload();
        })
        .catch(function (error) {
            window.alert(error.message || 'Não foi possível reordenar as perguntas.');
            window.location.reload();
        });
    }

    document.querySelectorAll('[data-quiz-form]').forEach(bindQuizForm);

    document.addEventListener('click', function (event) {
        var toggleButton = event.target.closest('[data-question-toggle]');
        if (toggleButton) {
            event.preventDefault();
            event.stopPropagation();
            var card = toggleButton.closest('details[data-question-card]');
            if (card) {
                card.open = !card.open;
            }
            return;
        }

        var moveButton = event.target.closest('[data-question-move]');
        if (moveButton) {
            event.preventDefault();
            event.stopPropagation();
            var moveCard = moveButton.closest('details[data-question-card]');
            if (!moveCard) {
                return;
            }
            reorderQuestions(moveCard, moveButton.getAttribute('data-question-move'));
        }
    });

    var tipo = document.getElementById('conteudo-item-tipo');
    function toggleTipo() {
        if (!tipo) {
            return;
        }

        var value = tipo.value || 'texto';
        var blocks = {
            etiqueta: document.getElementById('conteudo-tipo-etiqueta'),
            texto: document.getElementById('conteudo-tipo-texto'),
            link: document.getElementById('conteudo-tipo-link'),
            video: document.getElementById('conteudo-tipo-video'),
            avaliacao_textual: document.getElementById('conteudo-tipo-avaliacao'),
            arquivo: document.getElementById('conteudo-tipo-arquivo'),
            quiz: document.getElementById('conteudo-tipo-quiz')
        };

        Object.keys(blocks).forEach(function (key) {
            if (!blocks[key]) {
                return;
            }
            blocks[key].style.display = key === value ? '' : 'none';
        });
    }

    function boot() {
        if (tipo) {
            toggleTipo();
            tipo.addEventListener('change', toggleTipo);
        }
    }

    document.addEventListener('DOMContentLoaded', boot);
    window.addEventListener('load', boot);
})();
</script>
