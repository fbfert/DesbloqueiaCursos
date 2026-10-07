<?php
/**
 * Área do aluno no tema caderno ("Meus cadernos"). Mesmos dados, abas, destinos
 * e formulário de resources/views/v2/pages/aluno.php (AlunoController V2):
 *   - abas por GET (?aba=cursos|pedidos|certificados|perfil), role="tab" e
 *     aria-selected como na V2; o servidor já troca aba inválida por cursos;
 *   - cancelamento: POST /v2/aluno/pedidos/cancelar com pedido_id e
 *     motivo_cancelamento (obrigatório, id v2-motivo-<id>) dentro de <details>;
 *     o _token é injetado pelo View::render.
 * Cursos são cadernos em andamento (capa colada + trilha de progresso), pedidos
 * são recibos, certificados levam carimbo e o perfil é uma ficha. Carimbos são
 * ornamento (aria-hidden): a informação está sempre em texto ao lado.
 */

use App\Core\Helpers;

require_once BASE_PATH . '/resources/views/caderno/partials/checkout-util.php';

$abaAtiva = isset($abaAtiva) ? (string) $abaAtiva : 'cursos';
$abas = isset($abas) && is_array($abas) ? $abas : array();
$cursos = isset($cursos) && is_array($cursos) ? $cursos : array();
$certificados = isset($certificados) && is_array($certificados) ? $certificados : array();
$pedidos = isset($pedidos) && is_array($pedidos) ? $pedidos : array();
$perfil = isset($perfil) && is_array($perfil) ? $perfil : array();
$primeiroNome = isset($usuarioPrimeiroNome) && $usuarioPrimeiroNome !== '' ? (string) $usuarioPrimeiroNome : '';
$errosPagina = isset($errors) && is_array($errors) ? array_values(array_filter($errors, 'is_scalar')) : array();

// Cor de cada divisória (texto branco AA, mesma família das divisórias do catálogo).
$alCoresAba = array('cursos' => '#22104A', 'pedidos' => '#2F5D50', 'certificados' => '#A33B2B', 'perfil' => '#1F3FA8');
// Classe de situação da V2 → selo do tema.
$alSelo = function ($classe, $padrao) {
    $mapa = array('v2-badge-novo' => 'verde', 'v2-badge-gratis' => $padrao, 'v2-badge-destaque' => 'apagado');
    return isset($mapa[$classe]) ? $mapa[$classe] : 'apagado';
};
$alPainel = 'al-painel-' . preg_replace('/[^a-z]/', '', $abaAtiva);
?>
<div class="al">
  <header class="al-cab">
    <p class="al-ola"><?= $primeiroNome !== '' ? 'Olá, ' . Helpers::e($primeiroNome) . '!' : 'Olá!' ?></p>
    <h1 class="t2">Meus cadernos.</h1>
    <p class="lead">Continue sua jornada de aprendizado.</p>
  </header>

  <?php if (!empty($success) || !empty($errosPagina)): ?>
  <div class="al-avisos">
    <?php if (!empty($success)): ?>
    <div class="postit ok largo" role="status" aria-live="polite"><?= Helpers::e(is_array($success) ? (string) ($success['message'] ?? '') : (string) $success) ?></div>
    <?php endif; ?>
    <?php if (!empty($errosPagina)): ?>
    <div class="postit erro largo" role="alert">
      <?php foreach ($errosPagina as $alI => $alErro): ?><?= $alI > 0 ? '<br>' : '' ?><?= Helpers::e((string) $alErro) ?><?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <div class="abas al-abas" role="tablist" aria-label="Seções da área do aluno">
    <?php foreach ($abas as $aba):
        $alChave = (string) ($aba['chave'] ?? '');
        $alAtiva = $alChave === $abaAtiva;
        $alTotal = isset($aba['total']) && $aba['total'] !== null ? (int) $aba['total'] : null;
    ?>
    <a class="aba" role="tab" aria-selected="<?= $alAtiva ? 'true' : 'false' ?>"<?= $alAtiva ? ' aria-controls="' . $alPainel . '"' : '' ?>
       href="<?= Helpers::e((string) $aba['href']) ?>" style="--cor:<?= Helpers::e($alCoresAba[$alChave] ?? '#22104A') ?>"><?= Helpers::e((string) $aba['label']) ?><?php if ($alTotal !== null): ?><span class="qt"><span class="vh"> (</span><?= $alTotal ?><span class="vh">)</span></span><?php endif; ?></a>
    <?php endforeach; ?>
  </div>
  <div class="abas-base" aria-hidden="true"></div>

  <?php if ($abaAtiva === 'cursos'): ?>
  <section class="al-painel" role="tabpanel" id="<?= $alPainel ?>" aria-label="Meus cursos">
    <?php if (empty($cursos)): ?>
    <div class="postit al-vazio">
      <b>Nenhum caderno aberto ainda.</b>
      <p>Você ainda não tem cursos com acesso liberado.</p>
      <a class="btn" href="/v2/catalogo/"><?= caderno_icone('busca') ?> Explorar cursos</a>
    </div>
    <?php else: ?>
    <ul class="grade al-cursos">
      <?php foreach ($cursos as $curso):
          $alProg = max(0, min(100, (int) ($curso['progresso'] ?? 0)));
          $alCert = !empty($curso['tem_certificado']);
          $alMeta = array_filter(array((string) ($curso['turma'] ?? ''), (string) ($curso['modalidade'] ?? '')), 'strlen');
      ?>
      <li>
        <article class="foto al-curso"<?= $alCert ? ' data-carimbar' : '' ?>>
          <?php if (!empty($curso['thumbnail'])): ?>
          <img src="<?= Helpers::e((string) $curso['thumbnail']) ?>" alt="" width="640" height="360" loading="lazy" decoding="async">
          <?php else: ?>
          <span class="capa-vazia"><?= caderno_icone('cursos') ?></span>
          <?php endif; ?>
          <?php if ($alCert): ?>
          <div class="carimbo ret verde" aria-hidden="true">CERTIFICADO</div>
          <?php endif; ?>
          <p class="al-situacao">
            <span class="selo <?= $alSelo((string) ($curso['status_classe'] ?? ''), 'tinta') ?>"><?= Helpers::e((string) ($curso['status_label'] ?? '')) ?></span>
            <?php if ($alCert): ?><span class="selo verde">Certificado</span><?php endif; ?>
          </p>
          <h2 class="al-curso-nome"><?= Helpers::e((string) ($curso['nome'] ?? '')) ?></h2>
          <?php if (!empty($alMeta)): ?>
          <p class="meta"><?= Helpers::e(implode(' · ', $alMeta)) ?></p>
          <?php endif; ?>
          <div class="al-prog" data-progresso style="--p:<?= $alProg ?>">
            <svg viewBox="0 0 200 14" preserveAspectRatio="none" aria-hidden="true" focusable="false">
              <path class="al-prog-base" pathLength="100" d="M2 8C40 5 70 10.5 102 7.5S162 5 198 8"/>
              <?php if ($alProg > 0): ?><path class="al-prog-tinta" pathLength="100" stroke-dasharray="<?= $alProg ?> 100" d="M2 8C40 5 70 10.5 102 7.5S162 5 198 8"/><?php endif; ?>
            </svg>
            <p><span>Progresso</span> <b><?= $alProg ?>%</b></p>
          </div>
          <a class="btn btn-bloco" href="<?= Helpers::e((string) ($curso['lms_href'] ?? '/v2/aluno/')) ?>"><?= !empty($curso['concluida']) ? 'Revisar curso' : 'Continuar estudando' ?> <?= caderno_icone('seta-dir') ?></a>
        </article>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </section>

  <?php elseif ($abaAtiva === 'pedidos'): ?>
  <section class="al-painel" role="tabpanel" id="<?= $alPainel ?>" aria-label="Pedidos">
    <?php if (empty($pedidos)): ?>
    <div class="postit al-vazio">
      <b>Nenhum recibo por aqui.</b>
      <p>Você ainda não tem pedidos.</p>
      <a class="btn" href="/v2/catalogo/">Explorar cursos</a>
    </div>
    <?php else: ?>
    <ul class="al-recibos">
      <?php foreach ($pedidos as $pedido):
          $alPid = (int) ($pedido['id'] ?? 0);
          $alResumo = (string) ($pedido['resumo_v2_href'] ?? '');
          $alStatus = (string) ($pedido['status'] ?? '');
          $alItens = (int) ($pedido['total_itens'] ?? 0);
          $alNomes = !empty($pedido['multiplos_cursos']) && !empty($pedido['cursos_nomes']) && is_array($pedido['cursos_nomes']) ? $pedido['cursos_nomes'] : array();
          $alCarimbo = in_array($alStatus, array('aprovado', 'pago'), true) ? array('PAGO', 'verde') : (in_array($alStatus, array('cancelado', 'expirado'), true) ? array(mb_strtoupper((string) $pedido['status_label'], 'UTF-8'), 'vermelho') : null);
      ?>
      <li>
        <article class="recibo al-recibo" aria-labelledby="al-ped-<?= $alPid ?>">
          <?php if ($alCarimbo): ?>
          <div class="carimbo ret <?= $alCarimbo[1] ?>" aria-hidden="true"><?= Helpers::e($alCarimbo[0]) ?></div>
          <?php endif; ?>
          <h2 id="al-ped-<?= $alPid ?>" class="al-recibo-tit">
            <?php if ($alResumo !== ''): ?>
            <a href="<?= Helpers::e($alResumo) ?>"><?= Helpers::e((string) ($pedido['titulo_curso'] ?? '')) ?></a>
            <?php else: ?>
            <?= Helpers::e((string) ($pedido['titulo_curso'] ?? '')) ?>
            <?php endif; ?>
          </h2>
          <?php if (!empty($alNomes)): ?>
          <ul class="al-recibo-cursos">
            <?php foreach ($alNomes as $alNome): ?><li><?= Helpers::e((string) $alNome) ?></li><?php endforeach; ?>
          </ul>
          <?php endif; ?>
          <p class="recibo-cod">Pedido <b>#<?= Helpers::e((string) ($pedido['codigo'] ?? '')) ?></b><?php if (($pedido['data'] ?? '') !== ''): ?> · <?= Helpers::e((string) $pedido['data']) ?><?php endif; ?></p>
          <dl>
            <div><dt>Situação</dt><dd><span class="selo <?= $alSelo((string) ($pedido['status_classe'] ?? ''), 'laranja') ?>"><?= Helpers::e((string) ($pedido['status_label'] ?? '')) ?></span></dd></div>
            <?php if ($alItens > 0): ?>
            <div><dt>Itens</dt><dd><?= $alItens ?> <?= $alItens === 1 ? 'item' : 'itens' ?></dd></div>
            <?php endif; ?>
          </dl>
          <div class="total"><span>Total</span><strong><?= Helpers::e((string) ($pedido['total_formatado'] ?? '')) ?></strong></div>
          <?php if ($alResumo !== '' || !empty($pedido['pode_cancelar'])): ?>
          <div class="al-recibo-acoes">
            <?php if ($alResumo !== ''): ?>
            <a class="btn" href="<?= Helpers::e($alResumo) ?>">Continuar pedido <?= caderno_icone('seta-dir') ?></a>
            <?php endif; ?>
            <?php if (!empty($pedido['pode_cancelar'])): ?>
            <details class="al-cancelar">
              <summary>Cancelar pedido</summary>
              <form method="post" action="/v2/aluno/pedidos/cancelar" data-native-submit>
                <input type="hidden" name="pedido_id" value="<?= $alPid ?>">
                <div class="campo">
                  <label for="v2-motivo-<?= $alPid ?>">Motivo do cancelamento</label>
                  <input type="text" id="v2-motivo-<?= $alPid ?>" name="motivo_cancelamento" required>
                </div>
                <div class="form-acoes">
                  <button type="submit" class="btn al-btn-cancelar" data-loading-label="Cancelando…">Confirmar cancelamento</button>
                </div>
              </form>
            </details>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        </article>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </section>

  <?php elseif ($abaAtiva === 'certificados'): ?>
  <section class="al-painel" role="tabpanel" id="<?= $alPainel ?>" aria-label="Certificados">
    <?php if (empty($certificados)): ?>
    <div class="postit al-vazio">
      <b>Ainda sem carimbo.</b>
      <p>Você ainda não tem certificados emitidos.</p>
      <a class="btn" href="/v2/aluno/?aba=cursos">Ver meus cursos</a>
    </div>
    <?php else: ?>
    <ul class="al-certs">
      <?php foreach ($certificados as $cert): ?>
      <li>
        <article class="al-cert" data-carimbar>
          <?php
          $linhas = array('CERTIFICADO', 'EMITIDO', (string) ($cert['emitido_em'] ?? ''));
          $cor = 'laranja';
          require BASE_PATH . '/resources/views/caderno/partials/carimbo.php';
          ?>
          <p class="ficha-tit">Certificado</p>
          <h2 class="al-cert-nome"><?= Helpers::e((string) ($cert['curso'] ?? '')) ?></h2>
          <dl class="ficha-linhas">
            <div><dt>Código</dt><dd class="al-codigo"><?= Helpers::e((string) ($cert['codigo'] ?? '')) ?></dd></div>
            <?php if (($cert['emitido_em'] ?? '') !== ''): ?>
            <div><dt>Emitido em</dt><dd><?= Helpers::e((string) $cert['emitido_em']) ?></dd></div>
            <?php endif; ?>
          </dl>
          <div class="al-cert-acoes">
            <a class="btn" href="<?= Helpers::e((string) ($cert['online_href'] ?? '')) ?>"><?= caderno_icone('olho') ?> Ver online</a>
            <a class="btn-sec" href="<?= Helpers::e((string) ($cert['pdf_href'] ?? '')) ?>">Baixar PDF<?= caderno_ck_contorno() ?></a>
            <a class="link" href="<?= Helpers::e((string) ($cert['validar_href'] ?? '')) ?>">Validar</a>
          </div>
        </article>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </section>

  <?php else: ?>
  <section class="al-painel" role="tabpanel" id="<?= $alPainel ?>" aria-label="Perfil">
    <div class="ficha al-ficha">
      <h2 class="ficha-tit">Meus dados</h2>
      <dl class="ficha-linhas">
        <div><dt>Nome</dt><dd><?= Helpers::e((string) ($perfil['nome'] ?? '')) ?></dd></div>
        <div><dt>E-mail</dt><dd><?= Helpers::e((string) ($perfil['email'] ?? '')) ?></dd></div>
        <?php if (!empty($perfil['cpf_mascarado'])): ?>
        <div><dt>CPF</dt><dd><?= Helpers::e((string) $perfil['cpf_mascarado']) ?></dd></div>
        <?php endif; ?>
        <?php if (!empty($perfil['telefone_mascarado'])): ?>
        <div><dt>WhatsApp</dt><dd><?= Helpers::e((string) $perfil['telefone_mascarado']) ?></dd></div>
        <?php endif; ?>
      </dl>
      <?php if (!empty($perfil['editar_href'])): ?>
      <a class="btn btn-bloco al-ficha-btn" href="<?= Helpers::e((string) $perfil['editar_href']) ?>">Editar meus dados</a>
      <?php endif; ?>
      <p class="al-ficha-nota">CPF e WhatsApp são exibidos mascarados por segurança.</p>
    </div>
  </section>
  <?php endif; ?>
</div>
