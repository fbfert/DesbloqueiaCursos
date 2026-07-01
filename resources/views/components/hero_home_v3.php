<?php

use App\Core\Helpers;

$chamadaPrincipalCapa = isset($chamadaPrincipalCapa) && is_array($chamadaPrincipalCapa) ? $chamadaPrincipalCapa : array();

$heroTitulo = !empty($chamadaPrincipalCapa['titulo'])
    ? (string) $chamadaPrincipalCapa['titulo']
    : 'Capacite sua equipe com cursos práticos e certificados';

$heroSubtitulo = !empty($chamadaPrincipalCapa['conteudo'])
    ? (string) $chamadaPrincipalCapa['conteudo']
    : 'Modalidades presencial e online. Inscrição simples e rápida.';
?>
<section class="dbc-section dbc-hero">
    <div class="dbc-hero__inner">
        <div class="dbc-hero__content">
            <p class="dbc-hero__eyebrow">Desbloqueia Cursos</p>
            <h1 class="dbc-hero__headline"><?php echo Helpers::e($heroTitulo); ?></h1>
            <p class="dbc-hero__subheadline"><?php echo Helpers::e($heroSubtitulo); ?></p>
            <div class="dbc-hero__actions">
                <a class="dbc-btn dbc-btn--primary" href="/cursos">Ver cursos</a>
                <a class="dbc-btn dbc-btn--secondary" href="/como-funciona">Como funciona</a>
            </div>
        </div>
        <div class="dbc-hero__image" aria-hidden="true">
            <svg class="dbc-hero__image-icon" viewBox="0 0 24 24" fill="currentColor" focusable="false">
                <path d="M12 3 1 9l4 2.18v6L12 21l7-3.82v-6l2-1.09V17h2V9L12 3zm6.82 6L12 12.72 5.18 9 12 5.28 18.82 9zM17 15.99l-5 2.73-5-2.73v-3.72L12 15l5-2.73v3.72z"/>
            </svg>
        </div>
    </div>
</section>
