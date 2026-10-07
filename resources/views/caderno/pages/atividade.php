<?php
/**
 * Atividade no tema caderno ("Folha de resposta"). Mesmos dados, ramos e
 * formulário de resources/views/v2/pages/atividade.php (AtividadeController V2):
 *   - estado() genérico; atividade indisponível; externo (link para a rota
 *     oficial); avaliação com status_code nao_enviada | em_correcao | devolvida |
 *     corrigida | aprovada | reprovada | cancelada;
 *   - envio: POST /v2/atividade/enviar multipart, id v2-atividade-form, os 5
 *     campos ocultos, textarea name=resposta (id v2-atv-resposta, 3 a 50.000
 *     caracteres) e input file name="imagens[]" (id v2-atv-imagens, até 5);
 *     _token injetado pelo View::render;
 *   - enunciado 'full' e orientações 'basic' via Helpers::renderSafeHtml;
 *     resposta e devolutiva com nl2br(e()); imagens pela rota autenticada.
 * O módulo `atividade` de caderno-aluno.js conta os caracteres, barra mais de 5
 * imagens com mensagem em texto e foca o aviso após o redirect.
 */

use App\Core\Helpers;

require_once BASE_PATH . '/resources/views/caderno/partials/checkout-util.php';

$estado = isset($estado) && is_array($estado) ? $estado : null;
$cabecalho = isset($cabecalho) && is_array($cabecalho) ? $cabecalho : array();
$atividade = isset($atividade) && is_array($atividade) ? $atividade : null;
$formCtx = isset($formCtx) && is_array($formCtx) ? $formCtx : array();
$alunoHref = isset($alunoHref) ? (string) $alunoHref : '/v2/aluno/';
$voltarAulaUrl = isset($voltarAulaUrl) ? (string) $voltarAulaUrl : '/v2/aluno/';
$errors = isset($errors) && is_array($errors) ? array_values(array_filter($errors, 'is_scalar')) : array();
$success = isset($success) ? $success : null;
$enviarAction = isset($formCtx['enviar_action']) ? (string) $formCtx['enviar_action'] : '/v2/atividade/enviar';

if ($estado): ?>
<div class="atv">
  <div class="vazio aula-estado">
    <p class="mao"><?= Helpers::e((string) $estado['titulo']) ?></p>
    <p class="lead"><?= Helpers::e((string) $estado['mensagem']) ?></p>
    <a class="btn" href="<?= Helpers::e($alunoHref) ?>"><?= caderno_icone('seta-esq') ?> Voltar à minha área</a>
  </div>
</div>
<?php return; endif;

$hidden = ''
    . '<input type="hidden" name="inscricao_id" value="' . (int) ($formCtx['inscricao_id'] ?? 0) . '">'
    . '<input type="hidden" name="curso_id" value="' . (int) ($formCtx['curso_id'] ?? 0) . '">'
    . '<input type="hidden" name="turma_id" value="' . (int) ($formCtx['turma_id'] ?? 0) . '">'
    . '<input type="hidden" name="modulo_id" value="' . (int) ($formCtx['modulo_id'] ?? 0) . '">'
    . '<input type="hidden" name="item_id" value="' . (int) ($formCtx['item_id'] ?? 0) . '">';
$successTexto = !empty($success) ? (is_array($success) ? (string) ($success['message'] ?? '') : (string) $success) : '';
// Situação → cor do selo (o texto da situação vem sempre do servidor).
$atvSelos = array('em_correcao' => 'tinta', 'devolvida' => 'laranja', 'corrigida' => 'tinta', 'aprovada' => 'verde', 'reprovada' => 'vermelho');
$atvData = function ($valor) {
    $ts = strtotime((string) $valor);
    return $ts ? date('d/m/Y', $ts) . ' às ' . date('H:i', $ts) : '';
};
?>
<div class="atv">
  <header class="aula-cab">
    <nav class="migalha" aria-label="Caminho">
      <a href="<?= Helpers::e($alunoHref) ?>">Minha área</a><span aria-hidden="true">›</span>
      <a href="<?= Helpers::e($voltarAulaUrl) ?>"><?= Helpers::e((string) ($cabecalho['curso_nome'] ?? '') !== '' ? (string) $cabecalho['curso_nome'] : 'Voltar à aula') ?></a>
    </nav>
  </header>

  <article class="aula-pag atv-pag" aria-labelledby="atv-titulo">
    <p class="aula-kicker"><span class="fita">Atividade</span><?php if (!empty($cabecalho['modulo_nome'])): ?> <span><?= Helpers::e((string) $cabecalho['modulo_nome']) ?></span><?php endif; ?></p>
    <h1 class="aula-tit" id="atv-titulo"><?= Helpers::e((string) ($cabecalho['atividade_nome'] ?? '') !== '' ? (string) $cabecalho['atividade_nome'] : 'Atividade') ?></h1>

    <div id="v2-atividade-feedback" class="aula-aviso" tabindex="-1" aria-live="assertive" data-aviso-foco>
      <?php if ($successTexto !== ''): ?>
      <div class="postit ok largo" role="status"><?= Helpers::e($successTexto) ?></div>
      <?php endif; ?>
      <?php if (!empty($errors)): ?>
      <div class="postit erro largo" role="alert"><?php foreach ($errors as $atI => $atErro): ?><?= $atI > 0 ? '<br>' : '' ?><?= Helpers::e((string) $atErro) ?><?php endforeach; ?></div>
      <?php endif; ?>
    </div>

    <?php if (!$atividade || ($atividade['estado'] ?? '') === 'indisponivel'): ?>
    <div class="vazio aula-estado">
      <p class="lead">Esta atividade ainda não está disponível.</p>
      <a class="btn-sec" href="<?= Helpers::e($voltarAulaUrl) ?>"><?= caderno_icone('seta-esq') ?> Voltar à aula<?= caderno_ck_contorno() ?></a>
    </div>

    <?php elseif ($atividade['estado'] === 'externo'): ?>
    <div class="postit largo aula-tarefa">
      <p>Esta atividade depende de um envio que ainda não é feito por aqui (por exemplo, anexo de arquivo). Abra a atividade no ambiente de aprendizagem para concluí-la.</p>
      <a class="btn" href="<?= Helpers::e((string) ($atividade['oficial_url'] ?? '#')) ?>">Abrir atividade <?= caderno_icone('seta-dir') ?></a>
    </div>
    <p class="atv-voltar"><a class="link" href="<?= Helpers::e($voltarAulaUrl) ?>">Voltar à aula</a></p>

    <?php else:
        $st = (string) ($atividade['status_code'] ?? 'nao_enviada');
        $ultima = isset($atividade['ultima']) && is_array($atividade['ultima']) ? $atividade['ultima'] : null;
    ?>
    <p class="atv-situacao atv-st-<?= Helpers::e($st) ?>">
      <span>Situação:</span> <b class="selo <?= $atvSelos[$st] ?? 'apagado' ?>"><?= Helpers::e((string) ($atividade['status_label'] ?? 'Não enviada')) ?></b>
      <?php if (trim((string) ($atividade['prazo'] ?? '')) !== ''): ?>
      <span class="atv-prazo"><?= caderno_icone('calendario') ?> Prazo: <?= Helpers::e($atvData($atividade['prazo']) !== '' ? $atvData($atividade['prazo']) : (string) $atividade['prazo']) ?></span>
      <?php endif; ?>
    </p>

    <?php if (trim((string) ($atividade['enunciado_html'] ?? '')) !== '' || trim((string) ($atividade['orientacoes_html'] ?? '')) !== ''): ?>
    <section id="v2-atv-enunciado" aria-label="Enunciado da atividade">
      <?php if (trim((string) ($atividade['enunciado_html'] ?? '')) !== ''): ?>
      <div class="aula-texto"><?= Helpers::renderSafeHtml((string) $atividade['enunciado_html'], 'full') ?></div>
      <?php endif; ?>
      <?php if (trim((string) ($atividade['orientacoes_html'] ?? '')) !== ''): ?>
      <div class="atv-orient">
        <p class="atv-rot">Orientações</p>
        <div class="aula-texto"><?= Helpers::renderSafeHtml((string) $atividade['orientacoes_html'], 'basic') ?></div>
      </div>
      <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if ($ultima): ?>
    <section class="atv-entrega" aria-labelledby="atv-entrega-tit">
      <h2 class="sec-tit" id="atv-entrega-tit">Sua resposta enviada<?php if (!empty($ultima['tentativa'])): ?> <small>(tentativa <?= (int) $ultima['tentativa'] ?>)</small><?php endif; ?></h2>
      <?php $atEnviada = $atvData($ultima['enviado_em'] ?? ''); ?>
      <?php if ($atEnviada !== ''): ?><p class="atv-quando">Enviada em <?= Helpers::e($atEnviada) ?></p><?php endif; ?>
      <?php if (trim((string) ($ultima['nota'] ?? '')) !== ''): ?>
      <p class="atv-nota"><span>Nota</span> <b><?= Helpers::e((string) $ultima['nota']) ?></b></p>
      <?php endif; ?>
      <?php if (trim((string) ($ultima['resposta'] ?? '')) !== ''): ?>
      <div class="atv-resposta"><?= nl2br(Helpers::e((string) $ultima['resposta'])) ?></div>
      <?php endif; ?>
      <?php if (!empty($ultima['imagens'])): ?>
      <ul class="atv-imagens">
        <?php foreach ($ultima['imagens'] as $img): ?>
        <li><a class="foto" href="/aluno/cursos/conteudo/avaliacao/imagem?id=<?= (int) $img['id'] ?>" target="_blank" rel="noopener noreferrer">
          <img src="/aluno/cursos/conteudo/avaliacao/imagem?id=<?= (int) $img['id'] ?>" alt="<?= Helpers::e((string) ($img['nome_original'] ?? 'Imagem enviada')) ?>" loading="lazy">
        </a></li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
      <?php if (trim((string) ($ultima['feedback'] ?? '')) !== ''): ?>
      <div class="postit atv-devolutiva">
        <b>Devolutiva do professor</b>
        <p><?= nl2br(Helpers::e((string) $ultima['feedback'])) ?></p>
      </div>
      <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if (!empty($atividade['pode_enviar'])): ?>
    <form method="post" action="<?= Helpers::e($enviarAction) ?>" enctype="multipart/form-data" data-native-submit class="atv-form" id="v2-atividade-form">
      <?= $hidden ?>
      <div class="campo atv-campo">
        <label for="v2-atv-resposta"><?= $ultima ? 'Nova resposta' : 'Sua resposta' ?></label>
        <textarea id="v2-atv-resposta" name="resposta" rows="8" required minlength="3" maxlength="50000"
                  aria-describedby="v2-atv-enunciado v2-atv-contador"
                  placeholder="Digite sua resposta aqui" data-char-counter></textarea>
        <p class="ajuda" id="v2-atv-contador" aria-hidden="true"><span data-char-count>0</span> / 50.000 caracteres</p>
      </div>
      <div class="campo atv-campo">
        <label for="v2-atv-imagens">Imagens <span class="opcional">(opcional)</span></label>
        <input class="atv-arquivos" type="file" id="v2-atv-imagens" name="imagens[]" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" multiple aria-describedby="v2-atv-imagens-ajuda v2-atv-imagens-erro">
        <p class="ajuda" id="v2-atv-imagens-ajuda">Até 5 imagens (JPG, PNG ou WEBP), no máximo 1,5 MB cada.</p>
        <p class="erro-msg" id="v2-atv-imagens-erro" data-imagens-erro role="alert" hidden></p>
      </div>
      <div class="form-acoes">
        <button type="submit" class="btn" data-atv-btn data-loading-label="Enviando…"><?= $ultima ? 'Reenviar resposta' : 'Enviar resposta' ?> <?= caderno_icone('seta-dir') ?></button>
        <a class="link" href="<?= Helpers::e($voltarAulaUrl) ?>">Voltar à aula</a>
      </div>
    </form>
    <?php else: ?>
    <div class="atv-fechada">
      <p class="aula-nota"><?= $ultima ? 'O reenvio desta atividade não está disponível no momento.' : 'O envio desta atividade não está disponível no momento.' ?></p>
      <a class="link" href="<?= Helpers::e($voltarAulaUrl) ?>"><?= caderno_icone('seta-esq') ?> Voltar à aula</a>
    </div>
    <?php endif; ?>
    <?php endif; ?>
  </article>
</div>
