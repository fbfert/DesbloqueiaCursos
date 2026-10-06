<?php

use App\Core\Helpers;
use App\Support\TemaPublico;

if (!TemaPublico::emPrevia()) {
    return;
}

// Link de saída: só o caminho interno da requisição atual, com tema=v2.
$caminhoAtual = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
if ($caminhoAtual === '' || $caminhoAtual[0] !== '/' || strpos($caminhoAtual, '//') === 0) {
    $caminhoAtual = '/';
}
$consulta = array();
parse_str((string) ($_SERVER['QUERY_STRING'] ?? ''), $consulta);
$consulta['tema'] = 'v2';
$hrefSair = $caminhoAtual . '?' . http_build_query($consulta);
?>
<div class="aviso-previa" role="status">
    Prévia do tema caderno &middot;
    <a href="<?= Helpers::e($hrefSair) ?>">Sair da prévia</a>
</div>
