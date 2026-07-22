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
          <?php if (!empty($ultimaEntrega['nota'])): ?><span>Nota: <?php echo Helpers::e(number_format((float) $ultimaEntrega['nota'], 2, ',', '.')); ?></span><?php endif; ?>
          <?php if (!empty($ultimaEntrega['feedback'])): ?><span><?php echo nl2br(Helpers::e((string) $ultimaEntrega['feedback'])); ?></span><?php endif; ?>
        </div>
      <?php endif; ?>

      <?php if ($avaliacaoPodeEnviar): ?>
        <form method="post" action="/aluno/cursos/conteudo/avaliacao/enviar">
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
          <button type="submit" class="dc-btn dc-btn-primary dc-btn-block">Enviar resposta</button>
        </form>
      <?php else: ?>
        <p class="dc-study-note">O reenvio desta avaliação não está disponível no momento.</p>
      <?php endif; ?>
    </div>

  <?php elseif ($tipo === 'quiz'): ?>
    <?php
    // Carregar dados do quiz para o aluno (lógica preservada do fluxo original)
    $quizService   = new \App\Services\ConteudoQuizService();
    $quizParaAluno = $quizService->findQuizParaAluno($itemId, (int) Session::get('usuario_id'), $inscricaoId);
    $tentativaIdAtiva = Session::pullFlash('quiz_tentativa_id');
    $tentativaIdResultado = Session::pullFlash('quiz_tentativa_id_resultado');

    $tentativaAtiva = null;
    $resultadoTentativa = null;
    if ($quizParaAluno) {
        $quizId = (int) $quizParaAluno['id'];
        $tentativaModel = new \App\Models\ConteudoQuizTentativa();
        $tentativaAtiva = $tentativaModel->findEmAndamento($quizId, $inscricaoId);
        if ($tentativaIdResultado) {
            $resultadoTentativa = $quizService->obterTentativaParaAluno((int) $tentativaIdResultado, (int) Session::get('usuario_id'));
        }
    }
    ?>
    <div class="dc-study-card">
      <div class="dc-study-card__head"><strong>Quiz</strong><span class="dc-badge dc-badge-novo"><?php echo Helpers::e($statusTexto); ?></span></div>

      <?php if (!$quizParaAluno): ?>
        <p class="dc-study-note">Este quiz ainda não está disponível.</p>

      <?php elseif ($resultadoTentativa): ?>
        <?php $tent = $resultadoTentativa['tentativa']; $qDados = $resultadoTentativa['quiz']; ?>
        <?php if (!empty($qDados['exibir_resultado_apos_envio'])): ?>
          <div class="dc-callout <?php echo !empty($tent['aprovado']) ? 'dc-callout-success' : 'dc-callout-warning'; ?>" style="flex-direction:column;align-items:stretch;">
            <strong>Você acertou <?php echo (int) $tent['total_acertos']; ?> de <?php echo (int) $tent['total_perguntas']; ?> questões (<?php echo number_format((float) $tent['percentual'], 1); ?>%)</strong>
            <?php if ($tent['aprovado'] !== null): ?>
              <span><?php echo !empty($tent['aprovado']) ? 'Aprovado!' : 'Não atingiu o percentual mínimo.'; ?></span>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <?php if (!empty($qDados['exibir_gabarito_apos_envio']) || !empty($qDados['exibir_comentarios_apos_envio'])): ?>
          <?php foreach ($resultadoTentativa['perguntas'] as $idxP => $pergunta): ?>
            <div class="dc-quiz-pergunta">
              <div class="dc-quiz-enunciado"><?php echo $idxP + 1; ?>. <?php echo nl2br(Helpers::e((string) ($pergunta['enunciado'] ?? ''))); ?></div>
              <?php $resposta = $pergunta['resposta'] ?? null; ?>
              <?php foreach ($pergunta['alternativas'] as $alt): ?>
                <?php
                $isRespondida = $resposta && (int) ($resposta['alternativa_id'] ?? 0) === (int) $alt['id'];
                $isCorreta = isset($alt['correta']) && !empty($alt['correta']);
                $cls = '';
                if ($isCorreta) { $cls = 'dc-quiz-res-alt--correta'; }
                elseif ($isRespondida && !$isCorreta) { $cls = 'dc-quiz-res-alt--errada'; }
                ?>
                <div class="dc-quiz-res-alt <?php echo $cls; ?>">
                  <?php if ($isRespondida): ?><i class="ti ti-player-play-filled" style="font-size:12px;"></i><?php endif; ?>
                  <?php if ($isCorreta): ?><i class="ti ti-circle-check"></i><?php endif; ?>
                  <span><?php echo Helpers::e((string) ($alt['texto'] ?? '')); ?></span>
                </div>
              <?php endforeach; ?>
              <?php if (!empty($qDados['exibir_comentarios_apos_envio']) && !empty($pergunta['explicacao'])): ?>
                <p class="dc-study-note" style="font-style:italic;margin-top:6px;"><?php echo Helpers::e((string) $pergunta['explicacao']); ?></p>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>

        <?php if ($quizParaAluno['pode_nova_tentativa']): ?>
          <form method="post" action="/aluno/cursos/quiz/iniciar" style="margin-top:12px;">
            <?php echo $csrfField; ?>
            <input type="hidden" name="item_id" value="<?php echo $itemId; ?>">
            <input type="hidden" name="modulo_id" value="<?php echo $moduloId; ?>">
            <input type="hidden" name="inscricao_id" value="<?php echo $inscricaoId; ?>">
            <input type="hidden" name="curso_id" value="<?php echo $cursoId; ?>">
            <input type="hidden" name="turma_id" value="<?php echo $turmaId > 0 ? $turmaId : ''; ?>">
            <button type="submit" class="dc-btn dc-btn-outline dc-btn-block"><i class="ti ti-refresh"></i> Nova tentativa</button>
          </form>
        <?php endif; ?>

      <?php elseif ($tentativaAtiva): ?>
        <?php
        $respostasModel = new \App\Models\ConteudoQuizResposta();
        $respostasSalvas = $respostasModel->listForTentativa((int) $tentativaAtiva['id']);
        $respostasMapa = array();
        foreach ($respostasSalvas as $r) {
            $respostasMapa[(int) $r['pergunta_id']] = (int) ($r['alternativa_id'] ?? 0);
        }
        $perguntasQuiz = $quizParaAluno['perguntas'];
        ?>
        <div class="dc-study-note" style="margin-bottom:10px;">
          Em andamento — tentativa <?php echo (int) ($tentativaAtiva['numero_tentativa'] ?? 1); ?> · <?php echo count($perguntasQuiz); ?> pergunta(s)
          <?php if (!empty($quizParaAluno['tentativas_maximas'])): ?> · usadas <?php echo (int) ($quizParaAluno['tentativas_usadas'] ?? 0); ?>/<?php echo (int) $quizParaAluno['tentativas_maximas']; ?><?php endif; ?>
        </div>
        <?php if (!empty($quizParaAluno['instrucoes'])): ?>
          <div class="dc-callout dc-callout-info"><i class="ti ti-info-circle"></i><span><?php echo nl2br(Helpers::e((string) $quizParaAluno['instrucoes'])); ?></span></div>
        <?php endif; ?>

        <form method="post" action="/aluno/cursos/quiz/enviar" id="quiz-form">
          <?php echo $csrfField; ?>
          <input type="hidden" name="tentativa_id" value="<?php echo (int) $tentativaAtiva['id']; ?>">
          <input type="hidden" name="item_id" value="<?php echo $itemId; ?>">
          <input type="hidden" name="modulo_id" value="<?php echo $moduloId; ?>">
          <input type="hidden" name="inscricao_id" value="<?php echo $inscricaoId; ?>">
          <input type="hidden" name="curso_id" value="<?php echo $cursoId; ?>">
          <input type="hidden" name="turma_id" value="<?php echo $turmaId > 0 ? $turmaId : ''; ?>">

          <?php foreach ($perguntasQuiz as $idxP => $pergunta): ?>
            <?php
            $pid = (int) $pergunta['id'];
            $respostaSalva = isset($respostasMapa[$pid]) ? $respostasMapa[$pid] : 0;
            $alts = $pergunta['alternativas'] ?? array();
            ?>
            <div class="dc-quiz-pergunta" data-pergunta-id="<?php echo $pid; ?>">
              <div class="dc-quiz-enunciado" id="pergunta-<?php echo $pid; ?>">
                <?php echo $idxP + 1; ?>. <?php echo nl2br(Helpers::e((string) ($pergunta['enunciado'] ?? ''))); ?>
                <?php if (!empty($pergunta['obrigatoria'])): ?><span style="color:var(--dc-coral);" title="Obrigatória">*</span><?php endif; ?>
              </div>
              <div role="radiogroup" aria-labelledby="pergunta-<?php echo $pid; ?>">
                <?php foreach ($alts as $alt): ?>
                  <label class="dc-quiz-alt">
                    <input type="radio" name="respostas[<?php echo $pid; ?>]" value="<?php echo (int) $alt['id']; ?>" <?php echo $respostaSalva === (int) $alt['id'] ? 'checked' : ''; ?>>
                    <span><?php echo Helpers::e((string) ($alt['texto'] ?? '')); ?></span>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>

          <div class="dc-quiz-actions">
            <button type="submit" class="dc-btn dc-btn-primary" id="quiz-submit-btn"><i class="ti ti-send"></i> Enviar respostas</button>
            <button type="button" class="dc-btn dc-btn-ghost" id="quiz-save-btn"><i class="ti ti-device-floppy"></i> Salvar e continuar depois</button>
          </div>
          <p class="dc-study-note" style="margin-top:8px;">(*) Perguntas obrigatórias</p>
        </form>

        <script>
        (function () {
            var saveBtn = document.getElementById('quiz-save-btn');
            var submitBtn = document.getElementById('quiz-submit-btn');
            var form = document.getElementById('quiz-form');
            if (!saveBtn || !form) { return; }

            saveBtn.addEventListener('click', function () {
                var formData = new FormData(form);
                var payload = { respostas: {} };
                var csrfToken = formData.get('_token') || formData.get('csrf_token') || '';
                formData.forEach(function (v, k) {
                    var m = k.match(/^respostas\[(\d+)\]$/);
                    if (m) { payload.respostas[m[1]] = v; }
                });

                saveBtn.disabled = true;
                saveBtn.textContent = 'Salvando...';

                fetch('/aluno/cursos/quiz/rascunho', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: JSON.stringify({
                        tentativa_id: <?php echo (int) $tentativaAtiva['id']; ?>,
                        item_id: <?php echo $itemId; ?>,
                        inscricao_id: <?php echo $inscricaoId; ?>,
                        curso_id: <?php echo $cursoId; ?>,
                        turma_id: <?php echo $turmaId > 0 ? $turmaId : 0; ?>,
                        respostas: payload.respostas,
                        _token: csrfToken
                    })
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    saveBtn.disabled = false;
                    saveBtn.textContent = data.ok ? 'Salvo!' : 'Erro ao salvar';
                    setTimeout(function () { saveBtn.textContent = 'Salvar e continuar depois'; }, 2000);
                })
                .catch(function () {
                    saveBtn.disabled = false;
                    saveBtn.textContent = 'Erro ao salvar';
                });
            });

            form.addEventListener('submit', function (event) {
                var perguntas = form.querySelectorAll('[data-pergunta-id]');
                var faltando = [];
                perguntas.forEach(function (el) {
                    var pid = el.getAttribute('data-pergunta-id');
                    var checked = el.querySelector('input[type="radio"]:checked');
                    if (!checked) { faltando.push(pid); }
                });
                if (faltando.length > 0) {
                    event.preventDefault();
                    alert('Por favor, responda todas as perguntas antes de enviar.');
                    return;
                }
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Enviando...';
                }
            });
        })();
        </script>

      <?php else: ?>
        <?php $tentativas = $quizParaAluno['tentativas_usadas'] ?? 0; ?>
        <?php if (!empty($quizParaAluno['instrucoes'])): ?>
          <div class="dc-callout dc-callout-info"><i class="ti ti-info-circle"></i><span><?php echo nl2br(Helpers::e((string) $quizParaAluno['instrucoes'])); ?></span></div>
        <?php endif; ?>
        <p class="dc-study-note" style="margin-bottom:12px;">
          <?php echo (int) $quizParaAluno['total_perguntas']; ?> pergunta(s)
          <?php if ($quizParaAluno['tentativas_maximas'] !== null): ?> | Tentativas: <?php echo $tentativas; ?>/<?php echo (int) $quizParaAluno['tentativas_maximas']; ?>
          <?php elseif ($tentativas > 0): ?> | <?php echo $tentativas; ?> tentativa(s) realizada(s)<?php endif; ?>
        </p>
        <?php if ($quizParaAluno['pode_nova_tentativa']): ?>
          <form method="post" action="/aluno/cursos/quiz/iniciar">
            <?php echo $csrfField; ?>
            <input type="hidden" name="item_id" value="<?php echo $itemId; ?>">
            <input type="hidden" name="modulo_id" value="<?php echo $moduloId; ?>">
            <input type="hidden" name="inscricao_id" value="<?php echo $inscricaoId; ?>">
            <input type="hidden" name="curso_id" value="<?php echo $cursoId; ?>">
            <input type="hidden" name="turma_id" value="<?php echo $turmaId > 0 ? $turmaId : ''; ?>">
            <button type="submit" class="dc-btn dc-btn-primary dc-btn-block"><i class="ti ti-player-play"></i> Iniciar quiz</button>
          </form>
        <?php else: ?>
          <p class="dc-study-note">Você atingiu o número máximo de tentativas para este quiz.</p>
        <?php endif; ?>
      <?php endif; ?>
    </div>

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
