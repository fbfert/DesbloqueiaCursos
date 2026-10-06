<?php
/**
 * Turma aberta como ficha pautada. É o mesmo link GET da V2
 * (ficha_href + #turmas): escolher a turma recarrega a página com
 * ?turma_id= e a ficha de inscrição passa a usar essa turma. Com JS, o
 * módulo `curso` faz a troca sem recarregar (data-cta-href, como na V2).
 *
 * Espera: $turma (item de curso['turmas']), $curso e $turmaEscolhidaId (a
 * mesma turma da ficha de inscrição: a selecionada ou, sem seleção, a primeira).
 */

use App\Core\Helpers;

$tfSel = isset($turmaEscolhidaId) ? (int) $turma['id'] === (int) $turmaEscolhidaId : !empty($turma['selecionada']);
$tfNome = (string) $turma['nome'];
$tfVagas = $turma['vagas'];
?>
<a class="turma-ficha<?= $tfSel ? ' escolhida' : '' ?>" href="<?= Helpers::e((string) $turma['ficha_href']) ?>#turmas"<?= $tfSel ? ' aria-current="true"' : '' ?>
   data-turma-id="<?= (int) $turma['id'] ?>"
   data-turma-nome="<?= Helpers::e($tfNome) ?>"
   data-turma-inicio="<?= Helpers::e((string) $turma['data_inicio']) ?>"
   data-cta-href="<?= Helpers::e((string) $turma['inscricao_href']) ?>">
  <span class="turma-topo">
    <span class="turma-nome"><?= Helpers::e($tfNome) ?></span>
    <?php if ($tfVagas !== null): ?>
    <span class="selo verde"><?= (int) $tfVagas ?> <?= (int) $tfVagas === 1 ? 'vaga' : 'vagas' ?></span>
    <?php else: ?>
    <span class="selo verde">Vagas abertas</span>
    <?php endif; ?>
  </span>
  <span class="turma-linhas">
    <?php if ((string) $turma['codigo'] !== ''): ?><span><span class="rot">Código</span><?= Helpers::e((string) $turma['codigo']) ?></span><?php endif; ?>
    <?php if ((string) $turma['data_inicio'] !== ''): ?><span><?= caderno_icone('calendario') ?><span class="rot">Início</span><?= Helpers::e((string) $turma['data_inicio']) ?></span><?php endif; ?>
    <?php if ((string) $turma['data_fim'] !== ''): ?><span><?= caderno_icone('calendario') ?><span class="rot">Fim</span><?= Helpers::e((string) $turma['data_fim']) ?></span><?php endif; ?>
    <?php if ((string) $turma['local'] !== ''): ?><span><?= caderno_icone('presencial') ?><span class="rot vh">Local</span><?= Helpers::e((string) $turma['local']) ?></span><?php endif; ?>
  </span>
  <span class="turma-pe">
    <b><?= Helpers::e((string) $curso['precoFormatado']) ?></b>
    <span class="turma-marca"><?= caderno_icone('check') ?><span data-rotulo-escolha><?= $tfSel ? 'Turma escolhida' : 'Escolher esta turma' ?></span></span>
  </span>
</a>
