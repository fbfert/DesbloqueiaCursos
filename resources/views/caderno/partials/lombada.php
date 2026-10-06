<?php
/**
 * Lombada de caderno na estante: uma categoria.
 *
 * Espera:
 *   $categoria  item no formato de `categories` do HomeController V2
 *               (id, nome, total_cursos, url);
 *   $indice     opcional: posição na estante (varia cor, altura e elástico).
 *               Sem ele, usa o id da categoria.
 *
 * Emite um <li> (a estante é uma <ul class="estante">). Cores das lombadas
 * escolhidas para texto branco com contraste AA.
 */

use App\Core\Helpers;

if (!function_exists('caderno_etiqueta')) {
    /** Sigla curta para a etiqueta da lombada: iniciais das palavras ou começo da palavra única. */
    function caderno_etiqueta(string $nome): string
    {
        $pequenas = array('a', 'à', 'as', 'ao', 'aos', 'da', 'das', 'de', 'do', 'dos', 'e', 'em', 'na', 'nas', 'no', 'nos', 'o', 'os', 'para', 'por', 'com');
        $palavras = preg_split('/[\s\-–—·,\/]+/u', trim($nome), -1, PREG_SPLIT_NO_EMPTY);
        $palavras = is_array($palavras) ? $palavras : array();
        $fortes = array();
        foreach ($palavras as $palavra) {
            if (!in_array(mb_strtolower($palavra, 'UTF-8'), $pequenas, true)) {
                $fortes[] = $palavra;
            }
        }
        if (empty($fortes)) {
            return '';
        }
        if (count($fortes) === 1) {
            return mb_strtoupper(mb_substr($fortes[0], 0, 5, 'UTF-8'), 'UTF-8');
        }
        $sigla = '';
        foreach (array_slice($fortes, 0, 4) as $palavra) {
            $sigla .= mb_substr($palavra, 0, 1, 'UTF-8');
        }

        return mb_strtoupper($sigla, 'UTF-8');
    }
}

$lombCat = isset($categoria) && is_array($categoria) ? $categoria : array();
$lombNome = isset($lombCat['nome']) ? trim((string) $lombCat['nome']) : '';
$lombUrl = isset($lombCat['url']) && (string) $lombCat['url'] !== '' ? (string) $lombCat['url'] : '/categorias';
$lombTotal = isset($lombCat['total_cursos']) ? (int) $lombCat['total_cursos'] : 0;
$lombPos = isset($indice) ? (int) $indice : (isset($lombCat['id']) ? (int) $lombCat['id'] : 0);
$lombPos = abs($lombPos);

$lombCores = array('#22104A', '#2F5D50', '#A33B2B', '#1F3FA8', '#A8641A', '#3B3550', '#6B4E2E');
$lombAlturas = array(300, 270, 288, 258, 292, 266, 280);
$lombCor = $lombCores[$lombPos % count($lombCores)];
$lombAltura = $lombAlturas[$lombPos % count($lombAlturas)];
$lombElastico = in_array($lombPos % 7, array(0, 2, 5), true);
$lombSigla = caderno_etiqueta($lombNome);
?>
<li><a class="lomb<?= $lombElastico ? ' elastico' : '' ?>" href="<?= Helpers::e($lombUrl) ?>" style="--cor:<?= Helpers::e($lombCor) ?>;--h:<?= (int) $lombAltura ?>px"><?php if ($lombSigla !== ''): ?><span class="etq" aria-hidden="true"><?= Helpers::e($lombSigla) ?></span><?php endif; ?><span class="tit"><?= Helpers::e($lombNome) ?></span><small><?= number_format($lombTotal, 0, ',', '.') ?> <?= $lombTotal === 1 ? 'curso' : 'cursos' ?></small></a></li>
