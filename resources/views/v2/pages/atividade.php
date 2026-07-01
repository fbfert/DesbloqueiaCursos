<?php
use App\Core\Helpers;

$estado = isset($estado) && is_array($estado) ? $estado : null;
$cabecalho = isset($cabecalho) && is_array($cabecalho) ? $cabecalho : null;
$atividade = isset($atividade) && is_array($atividade) ? $atividade : null;
$formCtx = isset($formCtx) && is_array($formCtx) ? $formCtx : array();
$alunoHref = isset($alunoHref) ? (string) $alunoHref : '/v2/aluno/';
$voltarAulaUrl = isset($voltarAulaUrl) ? (string) $voltarAulaUrl : '/v2/aluno/';
$errors = isset($errors) && is_array($errors) ? array_values($errors) : array();
$success = isset($success) ? $success : null;
$enviarAction = isset($formCtx['enviar_action']) ? (string) $formCtx['enviar_action'] : '/v2/atividade/enviar';

$hidden = ''
    . '<input type="hidden" name="inscricao_id" value="' . (int) ($formCtx['inscricao_id'] ?? 0) . '">'
    . '<input type="hidden" name="curso_id" value="' . (int) ($formCtx['curso_id'] ?? 0) . '">'
    . '<input type="hidden" name="turma_id" value="' . (int) ($formCtx['turma_id'] ?? 0) . '">'
    . '<input type="hidden" name="modulo_id" value="' . (int) ($formCtx['modulo_id'] ?? 0) . '">'
    . '<input type="hidden" name="item_id" value="' . (int) ($formCtx['item_id'] ?? 0) . '">';
?>
<script>window.V2_DISABLE_AUTORENDER_ALUNO = true;</script>

<?php if ($estado): ?>
  <div class="v2-container v2-lms-area">
    <div class="v2-empty">
      <i class="ti ti-clipboard-text"></i>
      <p><strong><?php echo Helpers::e((string) $estado['titulo']); ?></strong></p>
      <p><?php echo Helpers::e((string) $estado['mensagem']); ?></p>
      <a href="<?php echo Helpers::e($alunoHref); ?>" class="v2-btn v2-btn-primary"><i class="ti ti-arrow-left"></i> Voltar à minha área</a>
    </div>
  </div>
  <?php return; ?>
<?php endif; ?>

<div class="v2-lms-area">
  <div class="v2-lms-top">
    <div class="v2-container v2-lms-top-inner">
      <a href="<?php echo Helpers::e($voltarAulaUrl); ?>" class="v2-iconbtn" aria-label="Voltar à aula"><i class="ti ti-arrow-left"></i></a>
      <div class="v2-lms-top-body">
        <div class="v2-lms-curso"><?php echo Helpers::e((string) ($cabecalho['curso_nome'] ?? '')); ?></div>
        <?php if (!empty($cabecalho['modulo_nome'])): ?>
          <div class="v2-lms-aula"><?php echo Helpers::e((string) $cabecalho['modulo_nome']); ?></div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="v2-container">
    <div class="v2-quiz">
      <span class="v2-badge v2-badge-novo">Atividade</span>
      <h1 class="v2-h2" style="margin:8px 0;"><?php echo Helpers::e((string) ($cabecalho['atividade_nome'] ?? 'Atividade')); ?></h1>

      <div id="v2-atividade-feedback" tabindex="-1" aria-live="assertive">
        <?php if (!empty($success)): ?>
          <div class="v2-callout v2-callout-success" role="status"><i class="ti ti-circle-check"></i><span><?php echo Helpers::e(is_array($success) && !empty($success['message']) ? (string) $success['message'] : (string) $success); ?></span></div>
        <?php endif; ?>
        <?php if (!empty($errors)): ?>
          <div class="v2-callout v2-callout-danger" role="alert"><i class="ti ti-alert-triangle"></i><span><?php foreach ($errors as $erro): ?><?php echo Helpers::e((string) $erro); ?><br><?php endforeach; ?></span></div>
        <?php endif; ?>
      </div>

      <?php if (!$atividade || ($atividade['estado'] ?? '') === 'indisponivel'): ?>
        <div class="v2-block">
          <p class="v2-muted">Esta atividade ainda não está disponível.</p>
          <a class="v2-btn v2-btn-outline" href="<?php echo Helpers::e($voltarAulaUrl); ?>"><i class="ti ti-arrow-left"></i> Voltar à aula</a>
        </div>

      <?php elseif ($atividade['estado'] === 'externo'): ?>
        <div class="v2-block">
          <p class="v2-muted">Esta atividade depende de um envio que ainda não é feito por aqui (por exemplo, anexo de arquivo). Abra a atividade no ambiente de aprendizagem para concluí-la.</p>
          <div class="v2-quiz-actions">
            <a class="v2-btn v2-btn-primary" href="<?php echo Helpers::e((string) ($atividade['oficial_url'] ?? '#')); ?>"><i class="ti ti-arrow-right"></i> Abrir atividade</a>
            <a class="v2-btn v2-btn-ghost v2-btn-sm" href="<?php echo Helpers::e($voltarAulaUrl); ?>">Voltar à aula</a>
          </div>
        </div>

      <?php else: ?>
        <?php
        $st = (string) ($atividade['status_code'] ?? 'nao_enviada');
        $ultima = isset($atividade['ultima']) && is_array($atividade['ultima']) ? $atividade['ultima'] : null;
        ?>
        <p class="v2-atv-status v2-atv-status--<?php echo Helpers::e($st); ?>">
          <i class="ti <?php
            echo $st === 'aprovada' ? 'ti-circle-check-filled'
               : ($st === 'reprovada' ? 'ti-circle-x-filled'
               : ($st === 'devolvida' ? 'ti-arrow-back-up'
               : ($st === 'em_correcao' ? 'ti-clock-hour-4'
               : ($st === 'corrigida' ? 'ti-checks' : 'ti-circle-dashed'))));
          ?>" aria-hidden="true"></i>
          Situação: <strong><?php echo Helpers::e((string) ($atividade['status_label'] ?? 'Não enviada')); ?></strong>
        </p>

        <?php if (trim((string) ($atividade['enunciado_html'] ?? '')) !== '' || trim((string) ($atividade['orientacoes_html'] ?? '')) !== '' || trim((string) ($atividade['prazo'] ?? '')) !== ''): ?>
          <section class="v2-block" id="v2-atv-enunciado" aria-label="Enunciado da atividade">
            <?php if (trim((string) ($atividade['enunciado_html'] ?? '')) !== ''): ?>
              <div class="v2-lms-prose"><?php echo Helpers::renderSafeHtml((string) $atividade['enunciado_html'], 'full'); ?></div>
            <?php endif; ?>
            <?php if (trim((string) ($atividade['orientacoes_html'] ?? '')) !== ''): ?>
              <div class="v2-lms-prose v2-sm"><?php echo Helpers::renderSafeHtml((string) $atividade['orientacoes_html'], 'basic'); ?></div>
            <?php endif; ?>
            <?php if (trim((string) ($atividade['prazo'] ?? '')) !== ''): ?>
              <p class="v2-muted v2-sm" style="margin:8px 0 0;"><i class="ti ti-calendar-event"></i> Prazo: <?php echo Helpers::e((string) $atividade['prazo']); ?></p>
            <?php endif; ?>
          </section>
        <?php endif; ?>

        <?php if ($ultima): ?>
          <section class="v2-block" aria-label="Sua última entrega">
            <h2 class="v2-h3" style="margin:0 0 8px;">Sua resposta enviada<?php if (!empty($ultima['tentativa'])): ?> (tentativa <?php echo (int) $ultima['tentativa']; ?>)<?php endif; ?></h2>
            <?php if (trim((string) ($ultima['resposta'] ?? '')) !== ''): ?>
              <div class="v2-atv-resposta"><?php echo nl2br(Helpers::e((string) $ultima['resposta'])); ?></div>
            <?php endif; ?>
            <?php if (trim((string) ($ultima['nota'] ?? '')) !== ''): ?>
              <p class="v2-atv-nota"><strong>Nota:</strong> <?php echo Helpers::e((string) $ultima['nota']); ?></p>
            <?php endif; ?>
            <?php if (trim((string) ($ultima['feedback'] ?? '')) !== ''): ?>
              <div class="v2-atv-feedback">
                <strong>Feedback do professor</strong>
                <p><?php echo nl2br(Helpers::e((string) $ultima['feedback'])); ?></p>
              </div>
            <?php endif; ?>
          </section>
        <?php endif; ?>

        <?php if (!empty($atividade['pode_enviar'])): ?>
          <form method="post" action="<?php echo Helpers::e($enviarAction); ?>" data-native-submit class="v2-atv-form" id="v2-atividade-form">
            <?php echo $hidden; ?>
            <div class="v2-field">
              <label for="v2-atv-resposta"><?php echo $ultima ? 'Nova resposta' : 'Sua resposta'; ?></label>
              <textarea id="v2-atv-resposta" name="resposta" class="v2-textarea" rows="8"
                        required minlength="3" maxlength="50000"
                        aria-describedby="v2-atv-enunciado v2-atv-contador"
                        placeholder="Digite sua resposta aqui" data-char-counter></textarea>
              <p class="v2-muted v2-sm" id="v2-atv-contador" aria-hidden="true"><span data-char-count>0</span>/50000 caracteres</p>
            </div>
            <div class="v2-quiz-actions">
              <button type="submit" class="v2-btn v2-btn-primary" data-atv-btn data-loading-label="Enviando…"><i class="ti ti-send"></i> <?php echo $ultima ? 'Reenviar resposta' : 'Enviar resposta'; ?></button>
              <a class="v2-btn v2-btn-ghost v2-btn-sm" href="<?php echo Helpers::e($voltarAulaUrl); ?>">Voltar à aula</a>
            </div>
          </form>
        <?php else: ?>
          <div class="v2-block">
            <p class="v2-muted"><?php echo $ultima ? 'O reenvio desta atividade não está disponível no momento.' : 'O envio desta atividade não está disponível no momento.'; ?></p>
            <a class="v2-btn v2-btn-outline v2-btn-sm" href="<?php echo Helpers::e($voltarAulaUrl); ?>"><i class="ti ti-arrow-left"></i> Voltar à aula</a>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
