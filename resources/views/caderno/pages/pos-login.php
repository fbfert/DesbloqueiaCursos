<?php
/**
 * Escolha pós-login no tema caderno (Minha Área × Catálogo), como
 * resources/views/v2/pages/pos-login.php: duas decisões reais, sem dados fictícios.
 */

use App\Core\Helpers;
use App\Support\V2Nav;

$alunoHref = $authLinks['aluno'] !== '' ? $authLinks['aluno'] : V2Nav::ALUNO;
$catalogoDestino = $authLinks['catalogo'] !== '' ? $authLinks['catalogo'] : V2Nav::CATALOGO;
$primeiro = isset($usuarioPrimeiroNome) && $usuarioPrimeiroNome !== '' ? (string) $usuarioPrimeiroNome : '';
?>
<section class="poslogin">
  <p class="poslogin-ola">Bem-vindo<?= $primeiro !== '' ? ', ' . Helpers::e($primeiro) : '' ?>!</p>
  <h1 class="t2">Para onde você deseja ir?</h1>

  <ul class="poslogin-escolhas">
    <li>
      <a class="escolha" href="<?= Helpers::e($alunoHref) ?>">
        <div class="escolha-topo"><?= caderno_icone('usuario') ?><h2 class="escolha-nome">Minha Área</h2></div>
        <p class="escolha-texto">Seus cursos, pedidos e certificados em um só lugar.</p>
        <span class="btn escolha-acao">Ir para Minha Área <?= caderno_icone('seta-dir') ?></span>
      </a>
    </li>
    <li>
      <a class="escolha" href="<?= Helpers::e($catalogoDestino) ?>">
        <div class="escolha-topo"><?= caderno_icone('cursos') ?><h2 class="escolha-nome">Catálogo de Cursos</h2></div>
        <p class="escolha-texto">Explore os cursos disponíveis e faça novas inscrições.</p>
        <span class="btn btn-laranja escolha-acao">Ver catálogo <?= caderno_icone('seta-dir') ?></span>
      </a>
    </li>
  </ul>
</section>
