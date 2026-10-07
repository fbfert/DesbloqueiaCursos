<?php
/**
 * Uma questão do quiz em andamento (folha de prova). Mesmo contrato da V2:
 * fieldset .v2-quiz-pergunta com data-pergunta-id/-tipo/-bloco/-bloco-titulo/
 * -revisao; radio respostas[<pid>] = id da alternativa; textarea
 * discursivas[<pid>] (id v2-discursiva-<pid>, maxlength do quiz). O fieldset
 * nunca é `disabled` e fica sempre dentro do form: escondido pela navegação, ele
 * continua indo no envio. Nada de gabarito/rubrica/explicação aqui.
 *
 * Espera: $pergunta, $idxP, $modoProva, $quiz.
 */

use App\Core\Helpers;

$qqPid = (int) ($pergunta['id'] ?? 0);
$qqMarcada = (int) ($pergunta['alternativa_id_respondida'] ?? 0);
$qqDisc = (string) ($pergunta['tipo'] ?? 'multipla_escolha') === 'discursiva';
$qqRevisao = !empty($pergunta['marcada_para_revisao']);
$qqLimite = (int) ($quiz['limite_caracteres_discursiva'] ?? 50000);
?>
<fieldset class="v2-quiz-pergunta questao" id="questao-<?= $qqPid ?>" tabindex="-1" data-pergunta-id="<?= $qqPid ?>"
          data-tipo="<?= $qqDisc ? 'discursiva' : 'objetiva' ?>"
          data-bloco="<?= Helpers::e((string) ($pergunta['bloco_codigo'] ?? '')) ?>"
          data-bloco-titulo="<?= Helpers::e((string) ($pergunta['bloco_titulo'] ?? '')) ?>"
          data-revisao="<?= $qqRevisao ? '1' : '0' ?>">
  <legend class="questao-enun">
    <span class="questao-n" aria-hidden="true"><?= (int) $idxP + 1 ?></span>
    <span class="questao-txt"><span class="vh">Questão <?= (int) $idxP + 1 ?>: </span><?= nl2br(Helpers::e((string) ($pergunta['enunciado'] ?? ''))) ?><?php if (!empty($pergunta['obrigatoria'])): ?><span class="questao-obrig" aria-hidden="true">*</span><span class="vh"> (obrigatória)</span><?php endif; ?></span>
  </legend>

  <?php if ($modoProva): ?>
  <button type="button" class="questao-flag" data-flag-pergunta="<?= $qqPid ?>" aria-pressed="<?= $qqRevisao ? 'true' : 'false' ?>" hidden>
    <svg class="ico" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6.5 21c-.2-5.8-.2-11.6 0-17.2m.1 1.3c3.9-2 7.3 1.6 11.4-.2.1 2.8.1 5.5 0 8.2-4 1.8-7.4-1.8-11.3.2"/></svg>
    <span class="v2-quiz-flag__texto"><?= $qqRevisao ? 'Marcada para revisão' : 'Marcar para revisão' ?></span>
  </button>
  <?php endif; ?>

  <?php if ($qqDisc):
      $qqTexto = (string) ($pergunta['texto_resposta'] ?? ''); ?>
  <div class="campo questao-disc">
    <label for="v2-discursiva-<?= $qqPid ?>">Sua resposta</label>
    <textarea id="v2-discursiva-<?= $qqPid ?>" name="discursivas[<?= $qqPid ?>]" rows="10" maxlength="<?= $qqLimite ?>"
              aria-describedby="v2-discursiva-<?= $qqPid ?>-nota" placeholder="Escreva sua resposta."><?= Helpers::e($qqTexto) ?></textarea>
    <p class="ajuda" aria-hidden="true"><span data-quiz-conta><?= number_format(function_exists('mb_strlen') ? mb_strlen($qqTexto, 'UTF-8') : strlen($qqTexto), 0, ',', '.') ?></span> / <?= number_format($qqLimite, 0, ',', '.') ?> caracteres</p>
    <p class="ajuda" id="v2-discursiva-<?= $qqPid ?>-nota">A nota desta questão é informativa: não altera a aprovação nem o certificado.</p>
  </div>
  <?php else: ?>
  <div class="alts">
    <?php foreach (array_values(isset($pergunta['alternativas']) && is_array($pergunta['alternativas']) ? $pergunta['alternativas'] : array()) as $qqK => $alt):
        $qqAid = (int) ($alt['id'] ?? 0); ?>
    <label class="alt">
      <input type="radio" name="respostas[<?= $qqPid ?>]" value="<?= $qqAid ?>"<?= $qqMarcada === $qqAid ? ' checked' : '' ?>>
      <span class="alt-letra" aria-hidden="true"><svg class="alt-circ" focusable="false"><use href="#q-circ"/></svg><?= $qqK < 26 ? chr(65 + $qqK) : $qqK + 1 ?></span>
      <span class="alt-txt"><span class="vh">Alternativa <?= $qqK < 26 ? chr(65 + $qqK) : $qqK + 1 ?>: </span><?= Helpers::e((string) ($alt['texto'] ?? '')) ?></span>
    </label>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</fieldset>
