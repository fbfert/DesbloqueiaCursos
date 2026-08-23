<?php

/**
 * Montagem da Norminha nos layouts da V2.
 *
 * Extraido de resources/views/v2/layout.php em 23/08/2026, quando o layout de
 * autenticacao (login, cadastro, recuperacao de senha) precisou montar o mesmo
 * componente. Duplicar as cinquenta linhas nos dois arquivos era garantir que
 * um dia eles divergissem; o comentario original ja avisava que a montagem
 * deve ter um ponto so.
 *
 * Quem inclui este parcial recebe, prontas:
 *   $tutorNorminha            componente resolvido, ou null
 *   $tutorNorminhaTtlHoras    TTL do fechamento
 *   $tutorNorminhaCssVersion  cache busting do CSS, ou null
 *   $tutorNorminhaJsVersion   cache busting do JS, ou null
 *   $norminhaPath             rota normalizada
 *   $norminhaContexto         pistas de contexto (NorminhaHints)
 *
 * Continua valendo a regra: montar SO no layout, nunca na view da pagina, sob
 * pena de o widget aparecer duas vezes (a guarda do smoke reprova isso).
 */

// ---------------------------------------------------------------------------
// Norminha (22/08/2026)
//
// Ate aqui o componente existia SO no layout legado. Como HOME_VERSION=v2 e a
// area do aluno viva e a V2, na pratica a Norminha nao aparecia para ninguem.
// Este e o unico ponto de montagem da V2 — nao replicar em view de pagina, sob
// pena de o widget aparecer duas vezes (a guarda do smoke reprova isso).
// ---------------------------------------------------------------------------
$tutorNorminha = null;
$tutorNorminhaTtlHoras = 24;
$tutorNorminhaCssVersion = null;
$tutorNorminhaJsVersion = null;

$norminhaAssetVersion = function ($relativo) {
    foreach (array(BASE_PATH . '/' . ltrim($relativo, '/'),
                   BASE_PATH . '/public_html/' . ltrim($relativo, '/')) as $caminho) {
        if (is_file($caminho)) {
            return filemtime($caminho);
        }
    }
    return null;
};

try {
    $tutorVirtualService = new App\Services\TutorVirtualService();
    $tutorConfiguracoes = $tutorVirtualService->configuracoes();
    $tutorNorminhaTtlHoras = isset($tutorConfiguracoes['tutor_ttl_fechamento_horas'])
        ? (int) $tutorConfiguracoes['tutor_ttl_fechamento_horas'] : 24;

    $norminhaPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $tutorNorminha = $tutorVirtualService->componenteParaLayout($norminhaPath, $_GET);
} catch (\Throwable $e) {
    // A Norminha nunca pode derrubar a pagina do aluno. Falhou, nao aparece.
    $tutorNorminha = null;
    App\Core\Logger::error('norminha.layout.falha', array('message' => $e->getMessage()));
}

if ($tutorNorminha) {
    $tutorNorminhaCssVersion = $norminhaAssetVersion('assets/css/tutor-norminha.css');
    $tutorNorminhaJsVersion = $norminhaAssetVersion('assets/js/tutor-norminha.js');
}

// Pistas de contexto. O controller publica o que RESOLVEU; onde ele ainda nao
// publica, cai-se na query string, que e mais fraca de proposito. Ver
// App\Support\NorminhaHints.
if (!isset($norminhaContexto) || !is_array($norminhaContexto)) {
    $norminhaContexto = App\Support\NorminhaHints::daQuery($_GET, $norminhaPath ?? null);
}
