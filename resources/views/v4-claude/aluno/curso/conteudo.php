<?php use App\Core\Helpers; use App\Core\Session; use App\Support\HtmlEmbedRenderer; use App\Support\VideoEmbedResolver; ?>
<?php
// Visualizador de conteúdo (modo de estudo) — template v4-claude.
// LÓGICA preservada integralmente do fluxo original; apenas a apresentação muda para dc-.
if (!function_exists('aluno_conteudo_valor')) {
    function aluno_conteudo_valor(array $array, array $keys, $default = '')
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $array) && $array[$key] !== null && $array[$key] !== '') {
                return $array[$key];
            }
        }
        return $default;
    }
}

$inscricaoId = isset($inscricao['id']) ? (int) $inscricao['id'] : 0;
$cursoId = isset($inscricao['curso_evento_id']) ? (int) $inscricao['curso_evento_id'] : 0;
$turmaId = isset($inscricao['turma_id']) ? (int) $inscricao['turma_id'] : 0;
$cursoNome = Helpers::normalizarTextoLms(isset($curso['nome']) ? $curso['nome'] : '');
$turmaNome = Helpers::normalizarTextoLms(!empty($turma['nome']) ? $turma['nome'] : '');
$cursoTitulo = $cursoNome !== '' ? $cursoNome : 'Curso';
$modulo = isset($conteudo_modulo) && is_array($conteudo_modulo) ? $conteudo_modulo : array();
$item = isset($conteudo_item) && is_array($conteudo_item) ? $conteudo_item : array();
$detalhe = isset($conteudo_detalhe) && is_array($conteudo_detalhe) ? $conteudo_detalhe : array();
$resumo = isset($conteudo_resumo) && is_array($conteudo_resumo) ? $conteudo_resumo : array();
$moduloId = (int) ($modulo['id'] ?? 0);
$itemId = (int) ($item['id'] ?? 0);
$itemTitulo = Helpers::normalizarTextoLms((string) ($item['titulo'] ?? 'Conteúdo'));
$moduloTitulo = Helpers::normalizarTextoLms((string) ($modulo['titulo'] ?? 'Módulo'));
$tipoLabel = isset($item['tipo_label']) ? (string) $item['tipo_label'] : 'Conteúdo';
$tipo = isset($item['tipo']) ? (string) $item['tipo'] : '';
$statusTexto = isset($item['status_label']) ? (string) $item['status_label'] : 'Pendente';
$statusProgressoItem = isset($conteudo_progresso['status']) ? (string) $conteudo_progresso['status'] : '';
$itemConcluido = !empty($item['concluido_aluno']) || in_array($statusProgressoItem, array('concluido', 'aprovada', 'corrigida'), true);
$cursoProgressPercent = max(0, min(100, (float) ($resumo['percentual'] ?? ($resumo['percentual_progresso'] ?? 0))));
$cursoProgressText = isset($resumo['concluidos_itens'], $resumo['total_itens']) ? (int) $resumo['concluidos_itens'] . '/' . (int) $resumo['total_itens'] . ' conteúdos concluídos' : 'Progresso indisponível';
$voltarModuloUrl = isset($conteudo_voltar_modulo_url) && $conteudo_voltar_modulo_url !== '' ? (string) $conteudo_voltar_modulo_url : '/aluno/curso/' . $inscricaoId . '/' . $cursoId . '/' . $turmaId . '/modulo/' . $moduloId;
$anteriorUrl = isset($conteudo_anterior_url) ? (string) $conteudo_anterior_url : null;
$proximoUrl = isset($conteudo_proximo_url) ? (string) $conteudo_proximo_url : null;
$arquivoUrl = isset($item['acao_url']) ? (string) $item['acao_url'] : '';
$arquivoDisponivel = $arquivoUrl !== '';
$conteudoTexto = aluno_conteudo_valor($detalhe, array('conteudo', 'texto', 'descricao', 'corpo', 'html'), '');
$videoUrl = aluno_conteudo_valor($detalhe, array('url', 'video_url', 'link'), '');
$videoEmbed = aluno_conteudo_valor($detalhe, array('embed_html', 'embed', 'html'), '');
$avaliacoes = isset($conteudo_avaliacao_entregas) && is_array($conteudo_avaliacao_entregas) ? $conteudo_avaliacao_entregas : array();
$ultimaEntrega = !empty($avaliacoes) ? $avaliacoes[0] : null;
$avaliacaoPodeEnviar = !empty($conteudo_avaliacao_pode_enviar);
$descricaoCurta = trim((string) ($item['descricao_curta'] ?? ''));
$detalheProfessor = trim((string) aluno_conteudo_valor($detalhe, array('observacao', 'orientacao', 'descricao', 'conteudo'), ''));
$renderRich = static function ($html, $mode = 'basic') {
    return Helpers::renderSafeHtml((string) $html, $mode);
};

$renderNav = static function ($voltarModuloUrl, $anteriorUrl, $proximoUrl) {
    ?>
    <nav class="dc-study-navicons" aria-label="Navegação do conteúdo">
        <a class="dc-study-navbtn" href="<?php echo Helpers::e($voltarModuloUrl); ?>" title="Voltar ao módulo" aria-label="Voltar ao módulo"><i class="ti ti-arrow-up"></i></a>
        <?php if (!empty($anteriorUrl)): ?>
            <a class="dc-study-navbtn" href="<?php echo Helpers::e($anteriorUrl); ?>" title="Conteúdo anterior" aria-label="Conteúdo anterior"><i class="ti ti-arrow-left"></i></a>
        <?php else: ?>
            <span class="dc-study-navbtn dc-study-navbtn--disabled" aria-disabled="true"><i class="ti ti-arrow-left"></i></span>
        <?php endif; ?>
        <?php if (!empty($proximoUrl)): ?>
            <a class="dc-study-navbtn dc-study-navbtn--primary" href="<?php echo Helpers::e($proximoUrl); ?>" title="Próximo conteúdo" aria-label="Próximo conteúdo"><i class="ti ti-arrow-right"></i></a>
        <?php else: ?>
            <span class="dc-study-navbtn dc-study-navbtn--disabled" aria-disabled="true"><i class="ti ti-arrow-right"></i></span>
        <?php endif; ?>
    </nav>
    <?php
};
?>

<div class="dc-study">

  <?php if (!empty($success)): ?>
    <div class="dc-callout dc-callout-success" style="margin-top:12px;"><i class="ti ti-circle-check"></i><span><?php echo Helpers::e($success); ?></span></div>
  <?php endif; ?>
  <?php if (!empty($errors)): ?>
    <div class="dc-callout dc-callout-danger" style="margin-top:12px;"><i class="ti ti-alert-triangle"></i><span><?php foreach ((array) $errors as $error): ?><?php echo Helpers::e((string) $error); ?><br><?php endforeach; ?></span></div>
  <?php endif; ?>

  <!-- Topo: navegação + título -->
  <div class="dc-study-top">
    <?php $renderNav($voltarModuloUrl, $anteriorUrl, $proximoUrl); ?>
    <div class="dc-study-top-body">
      <p class="dc-study-eyebrow">Modo de estudo</p>
      <h1 class="dc-study-titulo"><?php echo Helpers::e($itemTitulo); ?></h1>
      <p class="dc-study-sub"><?php echo Helpers::e($cursoProgressText); ?></p>
    </div>
  </div>
  <div class="dc-progress" style="margin-bottom:14px;">
    <div class="dc-progress-track"><div class="dc-progress-fill" style="width:<?php echo $cursoProgressPercent; ?>%"></div></div>
  </div>

  <?php if ($descricaoCurta !== ''): ?>
    <p class="dc-study-note" style="margin-bottom:12px;"><?php echo Helpers::e($descricaoCurta); ?></p>
  <?php endif; ?>

  <!-- Conteúdo por tipo -->
  <?php if ($tipo === 'texto'): ?>
    <div class="dc-study-card">
      <div class="dc-study-rich js-conteudo-texto-audio" data-audio-texto="1"><?php echo $renderRich($conteudoTexto !== '' ? $conteudoTexto : $detalheProfessor, 'full'); ?></div>
    </div>

  <?php elseif ($tipo === 'html'): ?>
    <div class="dc-study-card dc-study-card--html">
      <iframe
        id="conteudo-html-frame-<?php echo $itemId; ?>"
        class="js-conteudo-html-frame conteudo-item-html__frame"
        sandbox="allow-scripts allow-popups"
        title="<?php echo Helpers::e($itemTitulo); ?>"
        loading="lazy"
        srcdoc="<?php echo Helpers::e(HtmlEmbedRenderer::wrap($conteudoTexto, 'conteudo-html-frame-' . $itemId)); ?>"
      ></iframe>
    </div>

  <?php elseif ($tipo === 'arquivo'): ?>
    <?php
    $arquivoExtensao = trim((string) aluno_conteudo_valor($detalhe, array('extensao'), ''), '. ');
    $arquivoNome = trim((string) aluno_conteudo_valor($detalhe, array('nome_original', 'nome_arquivo'), ''));
    $arquivoTamanho = Helpers::formatarTamanhoArquivo(aluno_conteudo_valor($detalhe, array('tamanho_bytes'), 0));
    $arquivoIcone = Helpers::iconeArquivo($arquivoExtensao);
    ?>
    <div class="dc-study-card">
      <div class="dc-study-card__head"><strong>Arquivo do conteúdo</strong><span class="dc-badge dc-badge-novo"><?php echo Helpers::e($statusTexto); ?></span></div>
      <?php if ($conteudoTexto !== ''): ?><div class="dc-study-rich" style="margin-bottom:12px;"><?php echo $renderRich($conteudoTexto, 'basic'); ?></div><?php endif; ?>
      <?php if ($arquivoDisponivel): ?>
        <div style="display:flex;align-items:center;gap:14px;padding:12px 14px;border:1px solid rgba(0,0,0,.08);border-radius:12px;margin-bottom:10px;">
          <span style="font-size:28px;line-height:1;" aria-hidden="true"><?php echo $arquivoIcone; ?></span>
          <div style="flex:1;min-width:0;">
            <strong style="display:block;"><?php echo Helpers::e($arquivoNome !== '' ? $arquivoNome : $itemTitulo); ?></strong>
            <span style="font-size:.85em;opacity:.75;">
              <?php echo Helpers::e($arquivoExtensao !== '' ? strtoupper($arquivoExtensao) : ''); ?>
              <?php echo $arquivoTamanho !== '' ? ' · ' . Helpers::e($arquivoTamanho) : ''; ?>
            </span>
          </div>
        </div>
        <a class="dc-btn dc-btn-primary" href="<?php echo Helpers::e($arquivoUrl); ?>"><i class="ti ti-download"></i> Baixar arquivo</a>
      <?php else: ?>
        <p class="dc-study-note">O arquivo não está disponível no momento.</p>
      <?php endif; ?>
    </div>

  <?php elseif ($tipo === 'link'): ?>
    <?php
    $linkUrlRaw = (string) aluno_conteudo_valor($detalhe, array('url'), '');
    $linkHost = $linkUrlRaw !== '' ? (string) preg_replace('/^www\./', '', (string) parse_url($linkUrlRaw, PHP_URL_HOST)) : '';
    $linkDescricao = $conteudoTexto !== '' ? $conteudoTexto : $descricaoCurta;
    ?>
    <div class="dc-study-card">
      <div class="dc-study-card__head"><strong>Link de acesso</strong><span class="dc-badge dc-badge-novo"><?php echo Helpers::e($statusTexto); ?></span></div>
      <?php if ($linkDescricao !== ''): ?><div class="dc-study-rich" style="margin-bottom:12px;"><?php echo $renderRich($linkDescricao, 'basic'); ?></div><?php endif; ?>
      <?php if ($arquivoDisponivel): ?>
        <div style="display:flex;align-items:center;gap:14px;padding:12px 14px;border:1px solid rgba(0,0,0,.08);border-radius:12px;margin-bottom:10px;">
          <span style="font-size:24px;line-height:1;" aria-hidden="true">🔗</span>
          <div style="flex:1;min-width:0;">
            <strong style="display:block;"><?php echo Helpers::e($itemTitulo); ?></strong>
            <?php if ($linkHost !== ''): ?><span style="font-size:.85em;opacity:.75;"><?php echo Helpers::e($linkHost); ?></span><?php endif; ?>
          </div>
        </div>
        <a class="dc-btn dc-btn-primary" href="<?php echo Helpers::e($arquivoUrl); ?>"><i class="ti ti-external-link"></i> Abrir link</a>
      <?php else: ?>
        <p class="dc-study-note">O link não está disponível no momento.</p>
      <?php endif; ?>
    </div>

  <?php elseif ($tipo === 'video'): ?>
    <?php $videoEmbedInfo = $videoEmbed !== '' ? null : VideoEmbedResolver::resolve($videoUrl); ?>
    <div class="dc-study-card">
      <div class="dc-study-card__head"><strong>Vídeo do conteúdo</strong><span class="dc-badge dc-badge-novo"><?php echo Helpers::e($statusTexto); ?></span></div>
      <?php if ($videoEmbed !== ''): ?>
        <div class="dc-study-embed"><?php echo $renderRich($videoEmbed, 'full'); ?></div>
      <?php elseif ($videoEmbedInfo !== null): ?>
        <div style="position:relative;width:calc(100% + 32px);margin-left:-16px;margin-right:-16px;padding-top:56.25%;border-radius:12px;overflow:hidden;background:#000;">
          <iframe
            src="<?php echo Helpers::e($videoEmbedInfo['embedUrl']); ?>"
            title="<?php echo Helpers::e($itemTitulo); ?>"
            loading="lazy"
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
            referrerpolicy="strict-origin-when-cross-origin"
            allowfullscreen
            style="position:absolute;inset:0;width:100%;height:100%;border:0;"
          ></iframe>
        </div>
        <?php if ($videoUrl !== ''): ?>
          <p class="dc-study-note" style="margin-top:8px;"><a href="<?php echo Helpers::e($videoUrl); ?>" target="_blank" rel="noopener noreferrer">Abrir no site original ↗</a></p>
        <?php endif; ?>
      <?php elseif ($videoUrl !== ''): ?>
        <a class="dc-btn dc-btn-primary" href="<?php echo Helpers::e($videoUrl); ?>" target="_blank" rel="noopener noreferrer"><i class="ti ti-player-play"></i> Assistir vídeo</a>
      <?php else: ?>
        <p class="dc-study-note">Não há vídeo disponível para este conteúdo.</p>
      <?php endif; ?>
      <?php if ($conteudoTexto !== ''): ?><div class="dc-study-note" style="margin-top:10px;"><?php echo $renderRich($conteudoTexto, 'basic'); ?></div><?php endif; ?>
    </div>

  <?php elseif ($tipo === 'video_incorporado'): ?>
    <?php
    $videoIncorporadoConteudo = aluno_conteudo_valor($detalhe, array('conteudo'), '');
    $videoIncorporadoSrcdoc = $videoIncorporadoConteudo !== ''
        ? '<style>html,body{margin:0;padding:0;height:100%;overflow:hidden}iframe,video,embed,object{width:100%;height:100%;border:0;display:block}</style>' . $videoIncorporadoConteudo
        : '';
    ?>
    <div class="dc-study-card">
      <div class="dc-study-card__head"><strong>Vídeo do conteúdo</strong><span class="dc-badge dc-badge-novo"><?php echo Helpers::e($statusTexto); ?></span></div>
      <?php if ($videoIncorporadoConteudo !== ''): ?>
        <div style="position:relative;width:calc(100% + 32px);margin-left:-16px;margin-right:-16px;padding-top:56.25%;border-radius:12px;overflow:hidden;background:#000;">
          <iframe
            id="conteudo-video-incorporado-frame-<?php echo $itemId; ?>"
            sandbox="allow-scripts allow-popups"
            title="<?php echo Helpers::e($itemTitulo); ?>"
            loading="lazy"
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
            allowfullscreen
            srcdoc="<?php echo Helpers::e($videoIncorporadoSrcdoc); ?>"
            style="position:absolute;inset:0;width:100%;height:100%;border:0;"
          ></iframe>
        </div>
      <?php else: ?>
        <p class="dc-study-note">Não há vídeo disponível para este conteúdo.</p>
      <?php endif; ?>
      <?php if ($conteudoTexto !== ''): ?><div class="dc-study-note" style="margin-top:10px;"><?php echo $renderRich($conteudoTexto, 'basic'); ?></div><?php endif; ?>
    </div>

  <?php elseif ($tipo === 'avaliacao_textual'): ?>
    <div class="dc-study-card">
      <div class="dc-study-card__head"><strong>Avaliação textual</strong><span class="dc-badge dc-badge-novo"><?php echo Helpers::e($statusTexto); ?></span></div>
      <?php if ($conteudoTexto !== ''): ?><div class="dc-study-rich" style="margin-bottom:12px;"><?php echo $renderRich($conteudoTexto, 'full'); ?></div><?php endif; ?>

      <?php if (!empty($ultimaEntrega)): ?>
        <div class="dc-callout dc-callout-info" style="flex-direction:column;align-items:stretch;">
          <strong>Última entrega · <?php echo Helpers::e(Helpers::statusLms((string) ($ultimaEntrega['status'] ?? ''))); ?></strong>
          <?php if (!empty($ultimaEntrega['imagens'])): ?>
            <div style="display:flex;flex-wrap:wrap;gap:8px;margin:6px 0;">
              <?php foreach ($ultimaEntrega['imagens'] as $img): ?>
                <a href="/aluno/cursos/conteudo/avaliacao/imagem?id=<?php echo (int) $img['id']; ?>" target="_blank" rel="noopener noreferrer" style="display:block;width:72px;height:72px;border-radius:8px;overflow:hidden;border:1px solid var(--dc-border);">
                  <img src="/aluno/cursos/conteudo/avaliacao/imagem?id=<?php echo (int) $img['id']; ?>" alt="<?php echo Helpers::e((string) ($img['nome_original'] ?? 'Imagem enviada')); ?>" loading="lazy" style="width:100%;height:100%;object-fit:cover;display:block;">
                </a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
          <?php if (!empty($ultimaEntrega['nota'])): ?><span>Nota: <?php echo Helpers::e(number_format((float) $ultimaEntrega['nota'], 2, ',', '.')); ?></span><?php endif; ?>
          <?php if (!empty($ultimaEntrega['feedback'])): ?><span><?php echo nl2br(Helpers::e((string) $ultimaEntrega['feedback'])); ?></span><?php endif; ?>
        </div>
      <?php endif; ?>

      <?php if ($avaliacaoPodeEnviar): ?>
        <form method="post" action="/aluno/cursos/conteudo/avaliacao/enviar" enctype="multipart/form-data">
          <?php echo $csrfField; ?>
          <input type="hidden" name="item_id" value="<?php echo (int) $itemId; ?>">
          <input type="hidden" name="modulo_id" value="<?php echo (int) $moduloId; ?>">
          <input type="hidden" name="inscricao_id" value="<?php echo (int) $inscricaoId; ?>">
          <input type="hidden" name="curso_id" value="<?php echo (int) $cursoId; ?>">
          <input type="hidden" name="turma_id" value="<?php echo $turmaId > 0 ? (int) $turmaId : ''; ?>">
          <div class="dc-field">
            <label>Resposta</label>
            <textarea class="dc-input" name="resposta" rows="8" required placeholder="Digite sua resposta aqui"></textarea>
          </div>
          <div class="dc-field">
            <label>Imagens (opcional)</label>
            <input class="dc-input" type="file" name="imagens[]" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" multiple id="dc-avaliacao-imagens">
            <span class="dc-study-note">Até 5 imagens (JPG, PNG ou WEBP), no máximo 1,5 MB cada.</span>
            <span class="dc-study-note" data-imagens-erro hidden></span>
          </div>
          <button type="submit" class="dc-btn dc-btn-primary dc-btn-block">Enviar resposta</button>
        </form>
        <script>
        (function () {
            var input = document.getElementById('dc-avaliacao-imagens');
            if (!input) { return; }
            var erro = input.form.querySelector('[data-imagens-erro]');
            input.addEventListener('change', function () {
                if (input.files && input.files.length > 5) {
                    if (erro) { erro.textContent = 'Selecione no máximo 5 imagens.'; erro.hidden = false; }
                    input.value = '';
                } else if (erro) {
                    erro.hidden = true;
                }
            });
        })();
        </script>
      <?php else: ?>
        <p class="dc-study-note">O reenvio desta avaliação não está disponível no momento.</p>
      <?php endif; ?>
    </div>

  <?php elseif ($tipo === 'quiz'): ?>
    <?php require BASE_PATH . '/resources/views/v4-claude/aluno/curso/_quiz.php'; ?>

  <?php else: ?>
    <div class="dc-study-card"><p class="dc-study-note">Este conteúdo ainda não possui uma visualização específica.</p></div>
  <?php endif; ?>

  <!-- Conclusão -->
  <?php if ($tipo === 'texto' || $tipo === 'html'): ?>
    <div class="dc-conclusao dc-conclusao--done">
      <div class="dc-conclusao__estado"><i class="ti ti-circle-check"></i> Concluído automaticamente</div>
    </div>
  <?php elseif ($tipo !== 'avaliacao_textual' && $tipo !== 'quiz'): ?>
    <form method="post" action="/aluno/cursos/conteudo/item/concluir">
      <?php echo $csrfField; ?>
      <input type="hidden" name="item_id" value="<?php echo (int) $itemId; ?>">
      <input type="hidden" name="modulo_id" value="<?php echo (int) $moduloId; ?>">
      <input type="hidden" name="inscricao_id" value="<?php echo (int) $inscricaoId; ?>">
      <input type="hidden" name="curso_id" value="<?php echo (int) $cursoId; ?>">
      <input type="hidden" name="turma_id" value="<?php echo $turmaId > 0 ? (int) $turmaId : ''; ?>">
      <input type="hidden" name="acao" value="<?php echo $itemConcluido ? 'desmarcar' : 'marcar'; ?>">
      <div class="dc-conclusao <?php echo $itemConcluido ? 'dc-conclusao--done' : ''; ?>">
        <div class="dc-conclusao__estado">
          <i class="ti <?php echo $itemConcluido ? 'ti-circle-check' : 'ti-circle'; ?>"></i>
          <?php echo $itemConcluido ? 'Concluído' : 'Não concluído'; ?>
        </div>
        <button type="submit" class="dc-btn <?php echo $itemConcluido ? 'dc-btn-ghost' : 'dc-btn-primary'; ?>">
          <?php echo $itemConcluido ? 'Reverter' : 'Marcar como concluído'; ?>
        </button>
      </div>
    </form>
  <?php endif; ?>

  <!-- Navegação inferior -->
  <div class="dc-study-top" style="justify-content:center;">
    <?php $renderNav($voltarModuloUrl, $anteriorUrl, $proximoUrl); ?>
  </div>

</div>
