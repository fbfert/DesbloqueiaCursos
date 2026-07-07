<?php
use App\Core\Helpers;
use App\Support\V2Nav;

// -----------------------------------------------------------------------------
// Fase 2.13.1 — Escolha de destino pós-login V2 (Minha Área × Catálogo).
// Página V2 (layout/design system V2), sem qualquer dependência de HTML/CSS/JS
// ou controller/modal da Home V1. Somente decisões reais, sem dados fictícios.
// -----------------------------------------------------------------------------

$alunoHref = isset($alunoHref) ? (string) $alunoHref : V2Nav::ALUNO;
$catalogoHref = isset($catalogoHref) ? (string) $catalogoHref : V2Nav::CATALOGO;
$primeiro = isset($usuarioPrimeiroNome) && $usuarioPrimeiroNome !== '' ? (string) $usuarioPrimeiroNome : '';
?>
<script>window.V2_DISABLE_AUTORENDER_HOME = true;</script>

<section class="v2-container" style="padding-top:40px;padding-bottom:48px;">
  <div style="max-width:640px;margin:0 auto;text-align:center;">
    <span class="v2-badge v2-badge-novo">Bem-vindo<?php echo $primeiro !== '' ? ', ' . Helpers::e($primeiro) : ''; ?></span>
    <h1 class="v2-h1" style="margin:10px 0 6px;">Para onde você deseja ir?</h1>
    <p class="v2-muted" style="margin-bottom:22px;">Escolha o que deseja fazer agora.</p>

    <div class="v2-grid" style="text-align:left;">
      <a class="v2-block v2-poslogin-card" href="<?php echo Helpers::e($alunoHref); ?>" style="display:block;text-decoration:none;color:inherit;">
        <span class="v2-poslogin-ic" aria-hidden="true" style="display:inline-flex;width:44px;height:44px;border-radius:12px;align-items:center;justify-content:center;background:#f0e8ff;color:#4B008E;font-size:22px;margin-bottom:10px;"><i class="ti ti-user-circle"></i></span>
        <h2 class="v2-h3" style="margin:0 0 4px;">Minha Área</h2>
        <p class="v2-muted v2-sm" style="margin:0 0 12px;">Seus cursos, pedidos e certificados em um só lugar.</p>
        <span class="v2-btn v2-btn-primary v2-btn-sm"><i class="ti ti-arrow-right"></i> Ir para Minha Área</span>
      </a>

      <a class="v2-block v2-poslogin-card" href="<?php echo Helpers::e($catalogoHref); ?>" style="display:block;text-decoration:none;color:inherit;">
        <span class="v2-poslogin-ic" aria-hidden="true" style="display:inline-flex;width:44px;height:44px;border-radius:12px;align-items:center;justify-content:center;background:#fff4ec;color:#cc5500;font-size:22px;margin-bottom:10px;"><i class="ti ti-compass"></i></span>
        <h2 class="v2-h3" style="margin:0 0 4px;">Catálogo de Cursos</h2>
        <p class="v2-muted v2-sm" style="margin:0 0 12px;">Explore os cursos disponíveis e faça novas inscrições.</p>
        <span class="v2-btn v2-btn-outline v2-btn-sm"><i class="ti ti-arrow-right"></i> Ver catálogo</span>
      </a>
    </div>
  </div>
</section>
