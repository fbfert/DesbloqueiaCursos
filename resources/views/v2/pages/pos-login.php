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

<section class="v2-container v2-poslogin-wrap">
  <div class="v2-poslogin">
    <div class="v2-poslogin-head">
      <span class="v2-badge v2-badge-novo">Bem-vindo<?php echo $primeiro !== '' ? ', ' . Helpers::e($primeiro) : ''; ?></span>
      <h1 class="v2-h1">Para onde você deseja ir?</h1>
    </div>

    <div class="v2-poslogin-grid">
      <a class="v2-block v2-poslogin-card" href="<?php echo Helpers::e($alunoHref); ?>">
        <span class="v2-poslogin-ic" aria-hidden="true" style="background:#f0e8ff;color:#4B008E;"><i class="ti ti-user-circle"></i></span>
        <h2 class="v2-h3">Minha Área</h2>
        <p class="v2-muted v2-sm">Seus cursos, pedidos e certificados em um só lugar.</p>
        <span class="v2-btn v2-btn-primary v2-btn-sm"><i class="ti ti-arrow-right"></i> Ir para Minha Área</span>
      </a>

      <a class="v2-block v2-poslogin-card" href="<?php echo Helpers::e($catalogoHref); ?>">
        <span class="v2-poslogin-ic" aria-hidden="true" style="background:#fff4ec;color:#cc5500;"><i class="ti ti-compass"></i></span>
        <h2 class="v2-h3">Catálogo de Cursos</h2>
        <p class="v2-muted v2-sm">Explore os cursos disponíveis e faça novas inscrições.</p>
        <span class="v2-btn v2-btn-outline v2-btn-sm"><i class="ti ti-arrow-right"></i> Ver catálogo</span>
      </a>
    </div>
  </div>
</section>
