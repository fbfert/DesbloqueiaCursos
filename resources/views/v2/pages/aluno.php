<?php
use App\Core\Helpers;

$abaAtiva = isset($abaAtiva) ? (string) $abaAtiva : 'cursos';
$abas = isset($abas) && is_array($abas) ? $abas : array();
$cursos = isset($cursos) && is_array($cursos) ? $cursos : array();
$certificados = isset($certificados) && is_array($certificados) ? $certificados : array();
$pedidos = isset($pedidos) && is_array($pedidos) ? $pedidos : array();
$perfil = isset($perfil) && is_array($perfil) ? $perfil : array();
$coursePalette = isset($coursePalette) && is_array($coursePalette) ? $coursePalette : array(array('icon' => 'ti-book', 'g1' => '#fff4ec', 'g2' => '#ffe4d3', 'cor' => '#cc5500'));
$primeiroNome = isset($usuarioPrimeiroNome) && $usuarioPrimeiroNome !== '' ? (string) $usuarioPrimeiroNome : 'aluno';
$inicial = function_exists('mb_substr') ? mb_substr($primeiroNome, 0, 1, 'UTF-8') : substr($primeiroNome, 0, 1);
$errosPagina = isset($errors) && is_array($errors) ? array_values($errors) : array();
?>
<script>window.V2_DISABLE_AUTORENDER_ALUNO = true;</script>

<div class="v2-aluno-area">
  <div class="v2-aluno-head">
    <div class="v2-container">
      <div class="v2-aluno-greet">
        <div class="v2-aluno-greet-text">
          <h1 class="v2-h2">Olá, <?php echo Helpers::e($primeiroNome); ?>! 👋</h1>
          <p class="v2-muted v2-sm">Continue sua jornada de aprendizado.</p>
        </div>
        <div class="v2-avatar" aria-hidden="true" style="width:52px;height:52px;font-size:1.2rem;"><?php echo Helpers::e($inicial); ?></div>
      </div>

      <?php if (!empty($success)): ?>
        <div class="v2-callout v2-callout-success" role="status" aria-live="polite" style="margin:12px 0 0;">
          <i class="ti ti-circle-check"></i><span><?php echo Helpers::e((string) $success); ?></span>
        </div>
      <?php endif; ?>
      <?php if (!empty($errosPagina)): ?>
        <div class="v2-callout v2-callout-danger" role="alert" aria-live="assertive" style="margin:12px 0 0;">
          <i class="ti ti-alert-triangle"></i><span><?php foreach ($errosPagina as $msgErro): ?><?php echo Helpers::e((string) $msgErro); ?><br><?php endforeach; ?></span>
        </div>
      <?php endif; ?>

      <!-- Abas (navegação por GET; funcionam sem JavaScript) -->
      <div class="v2-tabs v2-tabs-aluno" role="tablist" aria-label="Seções da área do aluno">
        <?php foreach ($abas as $aba): ?>
          <?php $ativa = $aba['chave'] === $abaAtiva; ?>
          <a class="v2-tab<?php echo $ativa ? ' is-on' : ''; ?>"
             role="tab" aria-selected="<?php echo $ativa ? 'true' : 'false'; ?>"
             href="<?php echo Helpers::e((string) $aba['href']); ?>">
            <?php echo Helpers::e((string) $aba['label']); ?><?php if ($aba['total'] !== null): ?> <span class="v2-muted v2-sm">(<?php echo (int) $aba['total']; ?>)</span><?php endif; ?>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="v2-container" style="padding-top:18px;">
    <?php if ($abaAtiva === 'cursos'): ?>
      <section role="tabpanel" aria-label="Meus cursos">
        <?php if (empty($cursos)): ?>
          <div class="v2-empty">
            <i class="ti ti-book-2"></i>
            <p>Você ainda não tem cursos com acesso liberado.</p>
            <a href="/v2/catalogo/" class="v2-btn v2-btn-primary"><i class="ti ti-search"></i> Explorar cursos</a>
          </div>
        <?php else: ?>
          <div class="v2-grid">
            <?php foreach ($cursos as $index => $curso): ?>
              <?php $tema = $coursePalette[$index % count($coursePalette)]; ?>
              <article class="v2-aluno-card">
                <a href="<?php echo Helpers::e((string) $curso['lms_href']); ?>" class="v2-thumb" aria-label="Acessar curso: <?php echo Helpers::e((string) $curso['nome']); ?>" style="background:linear-gradient(135deg,<?php echo Helpers::e($tema['g1']); ?>,<?php echo Helpers::e($tema['g2']); ?>);">
                  <?php if (!empty($curso['thumbnail'])): ?>
                    <img src="<?php echo Helpers::e((string) $curso['thumbnail']); ?>" alt="<?php echo Helpers::e((string) $curso['nome']); ?>" style="width:100%;height:auto;display:block;">
                  <?php else: ?>
                    <i class="ti <?php echo Helpers::e($tema['icon']); ?>" style="color:<?php echo Helpers::e($tema['cor']); ?>;"></i>
                  <?php endif; ?>
                </a>
                <div class="v2-card-body">
                  <div class="v2-aluno-card-status">
                    <span class="v2-badge <?php echo Helpers::e((string) $curso['status_classe']); ?>"><?php echo Helpers::e((string) $curso['status_label']); ?></span>
                    <?php if (!empty($curso['tem_certificado'])): ?>
                      <span class="v2-badge v2-badge-gratis"><i class="ti ti-certificate"></i> Certificado</span>
                    <?php endif; ?>
                  </div>
                  <h3 class="v2-card-title"><?php echo Helpers::e((string) $curso['nome']); ?></h3>
                  <div class="v2-card-meta">
                    <?php if ($curso['turma'] !== ''): ?><span><i class="ti ti-users"></i><?php echo Helpers::e((string) $curso['turma']); ?></span><?php endif; ?>
                    <?php if ($curso['modalidade'] !== ''): ?><span><i class="ti ti-device-desktop"></i><?php echo Helpers::e((string) $curso['modalidade']); ?></span><?php endif; ?>
                  </div>
                  <div class="v2-progress">
                    <div class="v2-progress-track"><div class="v2-progress-fill" style="width:<?php echo (int) $curso['progresso']; ?>%;"></div></div>
                    <div class="v2-progress-row"><span class="v2-muted">Progresso</span><span class="v2-progress-pct"><?php echo (int) $curso['progresso']; ?>%</span></div>
                  </div>
                  <div class="v2-aluno-card-actions">
                    <a href="<?php echo Helpers::e((string) $curso['lms_href']); ?>" class="v2-btn v2-btn-primary v2-btn-sm">
                      <i class="ti ti-player-play"></i> <?php echo $curso['concluida'] ? 'Revisar curso' : 'Continuar estudando'; ?>
                    </a>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>

    <?php elseif ($abaAtiva === 'pedidos'): ?>
      <section role="tabpanel" aria-label="Pedidos">
        <?php if (empty($pedidos)): ?>
          <div class="v2-empty">
            <i class="ti ti-receipt"></i>
            <p>Você ainda não tem pedidos.</p>
            <a href="/v2/catalogo/" class="v2-btn v2-btn-primary">Explorar cursos</a>
          </div>
        <?php else: ?>
          <?php foreach ($pedidos as $pedido): ?>
            <?php
              $temResumo = isset($pedido['resumo_v2_href']) && $pedido['resumo_v2_href'] !== '';
              $multiplos = !empty($pedido['multiplos_cursos']);
              $tituloCurso = (string) ($pedido['titulo_curso'] ?? '');
            ?>
            <div class="v2-pedido">
              <div class="v2-pedido-top">
                <div style="min-width:0;">
                  <!-- Curso é a informação dominante do card -->
                  <h3 class="v2-pedido-curso">
                    <?php if ($temResumo): ?>
                      <a class="v2-pedido-curso-link" href="<?php echo Helpers::e((string) $pedido['resumo_v2_href']); ?>">
                        <?php echo Helpers::e($tituloCurso); ?>
                        <i class="ti ti-arrow-right" aria-hidden="true"></i>
                      </a>
                    <?php else: ?>
                      <?php echo Helpers::e($tituloCurso); ?>
                    <?php endif; ?>
                  </h3>
                  <?php if ($multiplos && !empty($pedido['cursos_nomes'])): ?>
                    <ul class="v2-pedido-cursos-lista">
                      <?php foreach ($pedido['cursos_nomes'] as $nomeCurso): ?>
                        <li><?php echo Helpers::e((string) $nomeCurso); ?></li>
                      <?php endforeach; ?>
                    </ul>
                  <?php endif; ?>
                  <!-- Código público e data como informação secundária -->
                  <span class="v2-pedido-cod">Pedido #<?php echo Helpers::e((string) $pedido['codigo']); ?></span>
                  <?php if ($pedido['data'] !== ''): ?><span class="v2-muted v2-sm"> · <?php echo Helpers::e((string) $pedido['data']); ?></span><?php endif; ?>
                </div>
                <span class="v2-badge <?php echo Helpers::e((string) $pedido['status_classe']); ?>"><?php echo Helpers::e((string) $pedido['status_label']); ?></span>
              </div>
              <div class="v2-card-meta">
                <?php if ($pedido['total_itens'] > 0): ?><span><i class="ti ti-package"></i><?php echo (int) $pedido['total_itens']; ?> <?php echo $pedido['total_itens'] === 1 ? 'item' : 'itens'; ?></span><?php endif; ?>
                <span><i class="ti ti-cash"></i><?php echo Helpers::e((string) $pedido['total_formatado']); ?></span>
              </div>
              <?php if ($temResumo || !empty($pedido['pode_cancelar'])): ?>
                <div class="v2-aluno-card-actions" style="margin-top:10px;">
                  <?php if ($temResumo): ?>
                    <a href="<?php echo Helpers::e((string) $pedido['resumo_v2_href']); ?>" class="v2-btn v2-btn-primary v2-btn-sm">
                      <i class="ti ti-arrow-right"></i> Continuar pedido
                    </a>
                  <?php endif; ?>
                  <?php if (!empty($pedido['pode_cancelar'])): ?>
                    <details class="v2-pedido-cancelar">
                      <summary class="v2-btn v2-btn-ghost v2-btn-sm"><i class="ti ti-x"></i> Cancelar pedido</summary>
                      <form method="post" action="/v2/aluno/pedidos/cancelar" data-native-submit class="v2-pedido-cancelar-form">
                        <input type="hidden" name="pedido_id" value="<?php echo (int) $pedido['id']; ?>">
                        <div class="v2-field">
                          <label for="v2-motivo-<?php echo (int) $pedido['id']; ?>">Motivo do cancelamento</label>
                          <input class="v2-input" type="text" id="v2-motivo-<?php echo (int) $pedido['id']; ?>" name="motivo_cancelamento" required>
                        </div>
                        <button type="submit" class="v2-btn v2-btn-outline v2-btn-sm"><i class="ti ti-check"></i> Confirmar cancelamento</button>
                      </form>
                    </details>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </section>

    <?php elseif ($abaAtiva === 'certificados'): ?>
      <section role="tabpanel" aria-label="Certificados">
        <?php if (empty($certificados)): ?>
          <div class="v2-empty">
            <i class="ti ti-certificate"></i>
            <p>Você ainda não tem certificados emitidos.</p>
            <a href="/v2/aluno/?aba=cursos" class="v2-btn v2-btn-outline">Ver meus cursos</a>
          </div>
        <?php else: ?>
          <?php foreach ($certificados as $cert): ?>
            <div class="v2-cert">
              <span class="v2-cert-ic" aria-hidden="true"><i class="ti ti-certificate"></i></span>
              <div class="v2-turma-body" style="min-width:0;">
                <strong><?php echo Helpers::e((string) $cert['curso']); ?></strong>
                <div class="v2-card-meta">
                  <span><i class="ti ti-hash"></i><?php echo Helpers::e((string) $cert['codigo']); ?></span>
                  <?php if ($cert['emitido_em'] !== ''): ?><span><i class="ti ti-calendar"></i><?php echo Helpers::e((string) $cert['emitido_em']); ?></span><?php endif; ?>
                </div>
                <div class="v2-aluno-card-actions">
                  <a href="<?php echo Helpers::e((string) $cert['online_href']); ?>" class="v2-btn v2-btn-outline v2-btn-sm"><i class="ti ti-eye"></i> Ver online</a>
                  <a href="<?php echo Helpers::e((string) $cert['pdf_href']); ?>" class="v2-btn v2-btn-outline v2-btn-sm"><i class="ti ti-file-type-pdf"></i> PDF</a>
                  <a href="<?php echo Helpers::e((string) $cert['validar_href']); ?>" class="v2-btn v2-btn-ghost v2-btn-sm"><i class="ti ti-shield-check"></i> Validar</a>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </section>

    <?php else: ?>
      <section role="tabpanel" aria-label="Perfil">
        <div class="v2-block">
          <h2 class="v2-h3" style="margin-bottom:12px;">Meus dados</h2>
          <div class="v2-cert-result-grid">
            <div><span class="v2-muted v2-sm">Nome</span><b><?php echo Helpers::e((string) ($perfil['nome'] ?? '')); ?></b></div>
            <div><span class="v2-muted v2-sm">E-mail</span><b><?php echo Helpers::e((string) ($perfil['email'] ?? '')); ?></b></div>
            <?php if (!empty($perfil['cpf_mascarado'])): ?>
              <div><span class="v2-muted v2-sm">CPF</span><b><?php echo Helpers::e((string) $perfil['cpf_mascarado']); ?></b></div>
            <?php endif; ?>
            <?php if (!empty($perfil['telefone_mascarado'])): ?>
              <div><span class="v2-muted v2-sm">Telefone</span><b><?php echo Helpers::e((string) $perfil['telefone_mascarado']); ?></b></div>
            <?php endif; ?>
          </div>
          <?php if (!empty($perfil['editar_href'])): ?>
            <div class="v2-aluno-card-actions" style="margin-top:16px;">
              <a href="<?php echo Helpers::e((string) $perfil['editar_href']); ?>" class="v2-btn v2-btn-primary v2-btn-sm"><i class="ti ti-edit"></i> Editar perfil</a>
            </div>
          <?php endif; ?>
          <p class="v2-muted v2-sm" style="margin-top:12px;">CPF e telefone são exibidos mascarados por segurança.</p>
        </div>
      </section>
    <?php endif; ?>
  </div>
</div>
