<?php
use App\Core\Helpers;
use App\Support\HtmlEmbedRenderer;

$estado = isset($estado) && is_array($estado) ? $estado : null;
$cabecalho = isset($cabecalho) && is_array($cabecalho) ? $cabecalho : null;
$arvore = isset($arvore) && is_array($arvore) ? $arvore : array();
$navegacao = isset($navegacao) && is_array($navegacao) ? $navegacao : array('anterior' => null, 'proximo' => null);
$item = isset($item) && is_array($item) ? $item : null;
$itemInacessivel = !empty($itemInacessivel);
$temConteudo = !empty($temConteudo);
$alunoHref = isset($alunoHref) ? (string) $alunoHref : '/v2/aluno/';
$formCtx = isset($formCtx) && is_array($formCtx) ? $formCtx : array();
$errors = isset($errors) && is_array($errors) ? array_values($errors) : array();
$success = isset($success) ? $success : null;
$concluirAction = isset($formCtx['concluir_action']) ? (string) $formCtx['concluir_action'] : '/v2/aula/concluir';
$overviewUrl = isset($overviewUrl) ? (string) $overviewUrl : $alunoHref;
// Sem item escolhido, mas com conteúdo publicado: mostra a visão geral (lista
// de módulos e conteúdos) em vez do estado vazio "conteúdo a caminho".
$mostrarVisaoGeral = !$itemInacessivel && !$item && $temConteudo;
?>
<script>window.V2_DISABLE_AUTORENDER_ALUNO = true;</script>

<?php if ($estado): ?>
  <div class="v2-container v2-lms-area">
    <div class="v2-empty">
      <i class="ti ti-player-track-next"></i>
      <p><strong><?php echo Helpers::e((string) $estado['titulo']); ?></strong></p>
      <p><?php echo Helpers::e((string) $estado['mensagem']); ?></p>
      <a href="<?php echo Helpers::e($alunoHref); ?>" class="v2-btn v2-btn-primary"><i class="ti ti-arrow-left"></i> Voltar à minha área</a>
    </div>
  </div>
  <?php return; ?>
<?php endif; ?>

<div class="v2-lms-area">
  <!-- Topbar do curso -->
  <div class="v2-lms-top">
    <div class="v2-container v2-lms-top-inner">
      <a href="<?php echo Helpers::e($alunoHref); ?>" class="v2-iconbtn" aria-label="Voltar à minha área"><i class="ti ti-arrow-left"></i></a>
      <div class="v2-lms-top-body">
        <div class="v2-lms-curso"><?php echo Helpers::e((string) ($cabecalho['curso_nome'] ?? '')); ?></div>
        <?php if (!empty($cabecalho['turma_nome'])): ?>
          <div class="v2-lms-aula"><?php echo Helpers::e((string) $cabecalho['turma_nome']); ?></div>
        <?php endif; ?>
      </div>
      <div class="v2-lms-top-prog"><span class="v2-lms-top-pct"><?php echo (int) ($cabecalho['progresso'] ?? 0); ?>%</span></div>
    </div>
    <div class="v2-container">
      <div class="v2-progress-track v2-lms-topbar-track"><div class="v2-progress-fill" style="width:<?php echo (int) ($cabecalho['progresso'] ?? 0); ?>%"></div></div>
    </div>
  </div>

  <div class="v2-container">
    <div class="v2-lms-layout">
      <!-- Coluna principal -->
      <div class="v2-lms-main">
        <?php if ($itemInacessivel): ?>
          <div class="v2-empty">
            <i class="ti ti-lock"></i>
            <p><strong>Conteúdo indisponível</strong></p>
            <p>Este conteúdo não está disponível para você no momento.</p>
            <a href="<?php echo Helpers::e($alunoHref); ?>" class="v2-btn v2-btn-outline"><i class="ti ti-arrow-left"></i> Voltar à minha área</a>
          </div>
        <?php elseif ($mostrarVisaoGeral): ?>
          <div class="v2-lms-overview">
            <h1 class="v2-h2" style="margin:4px 0 2px;"><?php echo Helpers::e((string) ($cabecalho['curso_nome'] ?? 'Conteúdo do curso')); ?></h1>
            <p class="v2-muted v2-sm" style="margin:0 0 16px;">Escolha um módulo para continuar seus estudos.</p>
            <?php require BASE_PATH . '/resources/views/v2/partials/lms-arvore.php'; ?>
          </div>
        <?php elseif (!$item): ?>
          <div class="v2-empty">
            <i class="ti ti-book-2"></i>
            <p><strong>Conteúdo a caminho</strong></p>
            <p>Este curso ainda não tem conteúdo publicado disponível para você.</p>
            <a href="<?php echo Helpers::e($alunoHref); ?>" class="v2-btn v2-btn-outline"><i class="ti ti-arrow-left"></i> Voltar à minha área</a>
          </div>
        <?php else: ?>
          <article class="v2-lms-stage">
            <a href="<?php echo Helpers::e($overviewUrl); ?>" class="v2-lms-back-overview"><i class="ti ti-list"></i> Ver todos os módulos</a>

            <?php $tipo = (string) $item['tipo']; ?>

            <?php if ($tipo === 'video'): ?>
              <?php if (trim((string) $item['video_embed']) !== ''): ?>
                <div class="v2-lms-video-embed"><?php echo Helpers::renderSafeHtml((string) $item['video_embed'], 'full'); ?></div>
              <?php elseif (!empty($item['video_embed_resolvido'])): ?>
                <div class="v2-lms-video-player">
                  <iframe
                    src="<?php echo Helpers::e($item['video_embed_resolvido']['embedUrl']); ?>"
                    title="<?php echo Helpers::e((string) $item['titulo']); ?>"
                    loading="lazy"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                    referrerpolicy="strict-origin-when-cross-origin"
                    allowfullscreen
                  ></iframe>
                </div>
                <?php if (trim((string) $item['video_url']) !== ''): ?>
                  <p class="v2-muted v2-sm" style="margin-top:8px;"><a href="<?php echo Helpers::e((string) $item['video_url']); ?>" target="_blank" rel="noopener noreferrer">Abrir no site original ↗</a></p>
                <?php endif; ?>
              <?php elseif (trim((string) $item['video_url']) !== ''): ?>
                <div class="v2-lms-material">
                  <a class="v2-btn v2-btn-primary" href="<?php echo Helpers::e((string) $item['video_url']); ?>" target="_blank" rel="noopener noreferrer"><i class="ti ti-player-play"></i> Assistir vídeo</a>
                </div>
              <?php else: ?>
                <p class="v2-muted">Não há vídeo disponível para este conteúdo.</p>
              <?php endif; ?>
            <?php endif; ?>

            <?php if ($tipo === 'video_incorporado'): ?>
              <?php if (trim((string) $item['video_incorporado_conteudo']) !== ''): ?>
                <?php
                $videoIncorporadoSrcdoc = '<style>html,body{margin:0;padding:0;height:100%;overflow:hidden}iframe,video,embed,object{width:100%;height:100%;border:0;display:block}</style>' . $item['video_incorporado_conteudo'];
                ?>
                <div class="v2-lms-video-player">
                  <iframe
                    id="conteudo-video-incorporado-frame-<?php echo (int) $item['id']; ?>"
                    sandbox="allow-scripts allow-popups"
                    title="<?php echo Helpers::e((string) $item['titulo']); ?>"
                    loading="lazy"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                    allowfullscreen
                    srcdoc="<?php echo Helpers::e($videoIncorporadoSrcdoc); ?>"
                  ></iframe>
                </div>
              <?php else: ?>
                <p class="v2-muted">Não há vídeo disponível para este conteúdo.</p>
              <?php endif; ?>
            <?php endif; ?>

            <h1 class="v2-h2" style="margin:8px 0;"><?php echo Helpers::e((string) $item['titulo']); ?></h1>

            <?php if ($tipo === 'texto' && trim((string) $item['texto_html']) !== ''): ?>
              <div class="v2-lms-prose"><?php echo Helpers::renderSafeHtml((string) $item['texto_html'], 'reading'); ?></div>

            <?php elseif ($tipo === 'html' && trim((string) $item['texto_html']) !== ''): ?>
              <?php $htmlFrameId = 'conteudo-html-frame-' . (int) $item['id']; ?>
              <article class="conteudo-item-html">
                <iframe
                  id="<?php echo Helpers::e($htmlFrameId); ?>"
                  class="js-conteudo-html-frame conteudo-item-html__frame"
                  sandbox="allow-scripts allow-popups"
                  title="<?php echo Helpers::e((string) $item['titulo']); ?>"
                  loading="lazy"
                  srcdoc="<?php echo Helpers::e(HtmlEmbedRenderer::wrap((string) $item['texto_html'], $htmlFrameId)); ?>"
                ></iframe>
              </article>

            <?php elseif ($tipo === 'arquivo'): ?>
              <?php if (trim((string) $item['texto_html']) !== ''): ?>
                <div class="v2-lms-prose"><?php echo Helpers::renderSafeHtml((string) $item['texto_html'], 'basic'); ?></div>
              <?php endif; ?>
              <?php if (trim((string) $item['acao_url']) !== ''): ?>
                <div class="v2-lms-file-card">
                  <span class="v2-lms-file-card__icon" aria-hidden="true"><?php echo $item['arquivo_icone']; ?></span>
                  <div class="v2-lms-file-card__info">
                    <strong><?php echo Helpers::e($item['arquivo_nome'] !== '' ? $item['arquivo_nome'] : (string) $item['titulo']); ?></strong>
                    <span class="v2-muted v2-sm">
                      <?php echo $item['arquivo_extensao'] !== '' ? Helpers::e(strtoupper($item['arquivo_extensao'])) : ''; ?>
                      <?php echo $item['arquivo_tamanho'] !== '' ? ' · ' . Helpers::e($item['arquivo_tamanho']) : ''; ?>
                    </span>
                  </div>
                  <a class="v2-btn v2-btn-primary" href="<?php echo Helpers::e((string) $item['acao_url']); ?>"><i class="ti ti-download"></i> Baixar material</a>
                </div>
              <?php else: ?>
                <p class="v2-muted">O material não está disponível no momento.</p>
              <?php endif; ?>

            <?php elseif ($tipo === 'link'): ?>
              <?php $linkDescricao = trim((string) $item['texto_html']) !== '' ? (string) $item['texto_html'] : (string) $item['descricao_curta']; ?>
              <?php if (trim($linkDescricao) !== ''): ?>
                <div class="v2-lms-prose"><?php echo Helpers::renderSafeHtml($linkDescricao, 'basic'); ?></div>
              <?php endif; ?>
              <?php if (trim((string) $item['acao_url']) !== ''): ?>
                <div class="v2-lms-file-card">
                  <span class="v2-lms-file-card__icon" aria-hidden="true">🔗</span>
                  <div class="v2-lms-file-card__info">
                    <strong><?php echo Helpers::e((string) $item['titulo']); ?></strong>
                    <?php if ($item['link_host'] !== ''): ?><span class="v2-muted v2-sm"><?php echo Helpers::e($item['link_host']); ?></span><?php endif; ?>
                  </div>
                  <a class="v2-btn v2-btn-primary" href="<?php echo Helpers::e((string) $item['acao_url']); ?>"><i class="ti ti-external-link"></i> Abrir link</a>
                </div>
              <?php else: ?>
                <p class="v2-muted">O link não está disponível no momento.</p>
              <?php endif; ?>

            <?php elseif ($tipo === 'quiz' && trim((string) ($item['quiz_url'] ?? '')) !== ''): ?>
              <div class="v2-block">
                <p class="v2-muted">Este é um quiz objetivo. Responda diretamente aqui na sua área.</p>
                <div class="v2-aula-actions">
                  <a class="v2-btn v2-btn-primary" href="<?php echo Helpers::e((string) $item['quiz_url']); ?>"><i class="ti ti-help-circle"></i> Responder quiz</a>
                </div>
              </div>

            <?php elseif ($tipo === 'avaliacao_textual' && trim((string) ($item['atividade_url'] ?? '')) !== ''): ?>
              <div class="v2-block">
                <p class="v2-muted">Esta é uma atividade avaliativa discursiva. Responda diretamente aqui na sua área.</p>
                <div class="v2-aula-actions">
                  <a class="v2-btn v2-btn-primary" href="<?php echo Helpers::e((string) $item['atividade_url']); ?>"><i class="ti ti-clipboard-text"></i> Responder atividade</a>
                </div>
              </div>

            <?php elseif (!empty($item['eh_interativo'])): ?>
              <div class="v2-block">
                <p class="v2-muted">Esta é uma atividade <?php echo Helpers::e((string) $item['tipo_label']); ?>. Para responder, abra a atividade no ambiente de aprendizagem.</p>
                <div class="v2-aula-actions">
                  <a class="v2-btn v2-btn-primary" href="<?php echo Helpers::e((string) $item['oficial_url']); ?>"><i class="ti ti-arrow-right"></i> Abrir atividade</a>
                </div>
              </div>

            <?php elseif ($tipo === 'texto' || $tipo === 'html'): ?>
              <p class="v2-muted">Conteúdo sem texto disponível.</p>
            <?php endif; ?>

            <!-- Mensagens reais (sucesso/erro) -->
            <div id="v2-aula-feedback" tabindex="-1" aria-live="assertive">
              <?php if (!empty($success)): ?>
                <div class="v2-callout v2-callout-success" role="status"><i class="ti ti-circle-check"></i><span><?php echo Helpers::e(is_array($success) && !empty($success['message']) ? (string) $success['message'] : (string) $success); ?></span></div>
              <?php endif; ?>
              <?php if (!empty($errors)): ?>
                <div class="v2-callout v2-callout-danger" role="alert"><i class="ti ti-alert-triangle"></i><span><?php foreach ($errors as $erro): ?><?php echo Helpers::e((string) $erro); ?><br><?php endforeach; ?></span></div>
              <?php endif; ?>
            </div>

            <?php
            // Campos ocultos comuns do formulário de conclusão (localizadores;
            // a autorização é refeita no servidor). CSRF é injetado automaticamente.
            $hidden = ''
              . '<input type="hidden" name="inscricao_id" value="' . (int) ($formCtx['inscricao_id'] ?? 0) . '">'
              . '<input type="hidden" name="curso_id" value="' . (int) ($formCtx['curso_id'] ?? 0) . '">'
              . '<input type="hidden" name="turma_id" value="' . (int) ($formCtx['turma_id'] ?? 0) . '">'
              . '<input type="hidden" name="modulo_id" value="' . (int) ($formCtx['modulo_id'] ?? 0) . '">'
              . '<input type="hidden" name="item_id" value="' . (int) $item['id'] . '">';
            ?>

            <!-- Conclusão (apenas tipos que o LMS atual permite concluir) -->
            <?php if (!empty($item['concluido'])): ?>
              <div class="v2-lms-complete is-done">
                <span class="v2-badge v2-badge-novo"><i class="ti ti-circle-check-filled"></i> Concluído</span>
                <?php if (!empty($item['pode_concluir'])): ?>
                  <form method="post" action="<?php echo Helpers::e($concluirAction); ?>" data-native-submit class="v2-lms-complete-form">
                    <?php echo $hidden; ?>
                    <input type="hidden" name="acao" value="desmarcar">
                    <button type="submit" class="v2-btn v2-btn-ghost v2-btn-badge-sm" data-complete-btn>Desmarcar conclusão</button>
                  </form>
                <?php endif; ?>
              </div>
            <?php elseif (!empty($item['pode_concluir'])): ?>
              <?php if (!empty($item['auto_leitura'])): ?>
                <!-- Texto: leitura automática (POST protegido por CSRF, vinculado ao item aberto).
                     Com JS, é enviado automaticamente; sem JS, o botão é o fallback acessível. -->
                <form method="post" action="<?php echo Helpers::e($concluirAction); ?>" data-native-submit data-v2-autocomplete class="v2-lms-complete">
                  <?php echo $hidden; ?>
                  <input type="hidden" name="acao" value="marcar">
                  <button type="submit" class="v2-btn v2-btn-primary v2-btn-sm" data-complete-btn data-loading-label="Concluindo…">
                    <i class="ti ti-check"></i> Concluir leitura
                  </button>
                  <noscript><span class="v2-muted v2-sm" style="display:block;margin-top:6px;">Clique para registrar a leitura deste conteúdo.</span></noscript>
                </form>
              <?php else: ?>
                <form method="post" action="<?php echo Helpers::e($concluirAction); ?>" data-native-submit class="v2-lms-complete">
                  <?php echo $hidden; ?>
                  <input type="hidden" name="acao" value="marcar">
                  <button type="submit" class="v2-btn v2-btn-primary v2-btn-sm" data-complete-btn data-loading-label="Concluindo…">
                    <i class="ti ti-check"></i> Marcar como concluído
                  </button>
                </form>
              <?php endif; ?>
            <?php endif; ?>

            <!-- Navegação anterior/próxima (gerada no servidor); some no mobile, onde é
                 substituída pela barra fixa abaixo. -->
            <div class="v2-lms-nav">
              <?php if (!empty($navegacao['anterior'])): ?>
                <a class="v2-btn v2-btn-ghost v2-btn-sm" href="<?php echo Helpers::e((string) $navegacao['anterior']['url']); ?>"><i class="ti ti-chevron-left"></i> Anterior</a>
              <?php else: ?><span></span><?php endif; ?>
              <?php if (!empty($navegacao['proximo'])): ?>
                <a class="v2-btn v2-btn-primary v2-btn-sm" href="<?php echo Helpers::e((string) $navegacao['proximo']['url']); ?>">Próxima <i class="ti ti-chevron-right"></i></a>
              <?php endif; ?>
            </div>
          </article>

          <!-- Mobile: substitui o menu inferior padrão do site por navegação do curso -->
          <nav class="v2-lms-bnav" aria-label="Navegação do curso">
            <?php if (!empty($navegacao['anterior'])): ?>
              <a href="<?php echo Helpers::e((string) $navegacao['anterior']['url']); ?>" class="v2-lms-bnav-item"><i class="ti ti-chevron-left"></i><span>Anterior</span></a>
            <?php else: ?>
              <span class="v2-lms-bnav-item is-disabled" aria-hidden="true"><i class="ti ti-chevron-left"></i><span>Anterior</span></span>
            <?php endif; ?>
            <a href="<?php echo Helpers::e($overviewUrl); ?>" class="v2-lms-bnav-item v2-lms-bnav-item--central"><i class="ti ti-list"></i><span>Módulos</span></a>
            <?php if (!empty($navegacao['proximo'])): ?>
              <a href="<?php echo Helpers::e((string) $navegacao['proximo']['url']); ?>" class="v2-lms-bnav-item"><i class="ti ti-chevron-right"></i><span>Próximo</span></a>
            <?php else: ?>
              <span class="v2-lms-bnav-item is-disabled" aria-hidden="true"><i class="ti ti-chevron-right"></i><span>Próximo</span></span>
            <?php endif; ?>
          </nav>
        <?php endif; ?>
      </div>

      <!-- Sidebar: estrutura do curso (somente desktop; no mobile a visão geral já cobre isso) -->
      <aside class="v2-lms-aside" aria-label="Estrutura do curso">
        <div class="v2-lms-aside-head">Conteúdo do curso</div>
        <?php require BASE_PATH . '/resources/views/v2/partials/lms-arvore.php'; ?>
      </aside>
    </div>
  </div>
</div>
