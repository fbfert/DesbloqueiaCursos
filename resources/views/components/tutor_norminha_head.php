<?php
/**
 * Cabeçalho da Norminha: assets e preferência de minimizado.
 *
 * Este trecho precisa rodar ANTES da renderização. Ele aplica a classe
 * .norminha-tutor-minimized no <html>, e é isso que impede o painel de piscar
 * aberto por um instante antes do JavaScript decidir que o aluno o havia
 * fechado. Por isso é inline, e não parte do tutor-norminha.js com defer.
 *
 * Compartilhado pelos dois layouts (legado e V2) desde 22/08/2026, quando a
 * Norminha passou a existir também na área do aluno V2. Duplicar o script em
 * cada layout garantiria que um dia eles divergissem.
 *
 * Espera: $tutorNorminhaTtlHoras, $tutorNorminhaCssVersion, $tutorNorminhaJsVersion.
 */

$ttl = isset($tutorNorminhaTtlHoras) ? (int) $tutorNorminhaTtlHoras : 24;
$ttl = $ttl >= 1 && $ttl <= 168 ? $ttl : 24;
$cssVersao = isset($tutorNorminhaCssVersion) && $tutorNorminhaCssVersion ? (int) $tutorNorminhaCssVersion : null;
?>
<script>
(function () {
    var storageKey = 'norminha_tutor_minimized_v1';
    var legacyKeys = ['norminha_tutor_closed_until', 'norminha_tutor_closed_v2', 'norminha_tutor_closed'];
    var ttlHours = <?php echo (int) $ttl; ?>;
    if (!ttlHours || ttlHours < 1 || ttlHours > 168) {
        ttlHours = 24;
    }
    var isMinimized = false;

    try {
        if (window.localStorage) {
            isMinimized = window.localStorage.getItem(storageKey) === '1';
            for (var i = 0; i < legacyKeys.length; i += 1) {
                window.localStorage.removeItem(legacyKeys[i]);
            }
        }

        if (isMinimized) {
            document.documentElement.classList.add('norminha-tutor-minimized');
        } else {
            document.documentElement.classList.remove('norminha-tutor-minimized');
        }
    } catch (error) {
        // Falha de storage não impede o carregamento do site.
    }
})();
</script>
<link rel="stylesheet" href="/assets/css/tutor-norminha.css<?php echo $cssVersao ? '?v=' . $cssVersao : ''; ?>">
