<?php

/**
 * Otimizador de imagens de capa (openspec capas-otimizadas).
 * Sem banco. Gera imagens com GD em diretorio temporario.
 *
 * Execução: php tests/Unit/otimizador_imagem.php
 */

require_once __DIR__ . '/_bootstrap.php';
require_once BASE_PATH . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register(BASE_PATH);

use App\Support\OtimizadorImagem;

$falhas = 0;
$total = 0;

function verifica($condicao, $descricao)
{
    global $falhas, $total;
    $total++;
    if ($condicao) {
        echo "ok   - $descricao\n";
    } else {
        $falhas++;
        echo "FALHA - $descricao\n";
    }
}

// Qualquer warning/deprecation vira falha do teste (como o ErrorHandler do app).
set_error_handler(function ($nivel, $mensagem, $arquivo, $linha) {
    throw new ErrorException($mensagem, 0, $nivel, $arquivo, $linha);
});

$dir = sys_get_temp_dir() . '/otm_teste_' . bin2hex(random_bytes(4));
mkdir($dir, 0775, true);

function criarRuido($w, $h, $alpha = false, $formas = 4000)
{
    $im = imagecreatetruecolor($w, $h);
    if ($alpha) {
        imagealphablending($im, false);
        imagesavealpha($im, true);
        imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
    }
    mt_srand(7);
    for ($i = 0; $i < $formas; $i++) {
        $cor = imagecolorallocate($im, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255));
        imagefilledellipse($im, mt_rand(0, $w), mt_rand(0, $h), mt_rand(10, 80), mt_rand(10, 80), $cor);
    }
    return $im;
}

$webp = OtimizadorImagem::suportaWebp();
$extEsperada = $webp ? 'webp' : 'jpg';
echo 'WebP suportado: ' . ($webp ? 'sim' : 'nao') . "\n";

try {
    // 1. PNG grande opaco
    $im = criarRuido(1672, 941);
    $grande = $dir . '/grande.png';
    imagepng($im, $grande, 1);
    imagedestroy($im);
    $r = OtimizadorImagem::otimizar($grande, $dir . '/grande-otm');
    verifica($r['ok'] === true, 'PNG grande: ok');
    verifica($r['extensao'] === $extEsperada, 'PNG grande: formato ' . $extEsperada);
    $info = getimagesize($r['caminho']);
    verifica($info !== false && $info[0] === 1280, 'PNG grande: largura 1280');
    verifica($info !== false && abs($info[1] - 720) <= 1, 'PNG grande: altura 720 (+-1)');
    verifica(filesize($r['caminho']) < filesize($grande), 'PNG grande: menor que o original');
    verifica(substr($r['caminho'], -strlen($extEsperada)) === $extEsperada, 'PNG grande: caminho com extensao correta');

    // 2. PNG pequeno nao amplia
    $im = criarRuido(800, 450);
    $peq = $dir . '/pequeno.png';
    imagepng($im, $peq);
    imagedestroy($im);
    $r = OtimizadorImagem::otimizar($peq, $dir . '/pequeno-otm');
    $info = $r['ok'] ? getimagesize($r['caminho']) : false;
    verifica($r['ok'] === true && $info !== false && $info[0] === 800, 'PNG pequeno: largura continua 800');

    // 3. Transparencia
    $im = criarRuido(600, 300, true, 10);
    $alfa = $dir . '/alfa.png';
    imagepng($im, $alfa);
    imagedestroy($im);
    $r = OtimizadorImagem::otimizar($alfa, $dir . '/alfa-otm');
    verifica($r['ok'] === true, 'PNG com transparencia: ok');
    if ($webp) {
        verifica($r['extensao'] === 'webp', 'PNG com transparencia: webp');
    } else {
        verifica($r['extensao'] === 'png', 'PNG com transparencia sem WebP: png');
        $out = imagecreatefrompng($r['caminho']);
        $px = imagecolorat($out, 1, 1);
        verifica((($px >> 24) & 0x7F) > 0, 'PNG com transparencia: alpha preservado');
        imagedestroy($out);
    }

    // 4. GIF animado (2 frames) montado a mao
    $gif = $dir . '/anim.gif';
    $f1 = imagecreate(10, 10);
    imagecolorallocate($f1, 255, 0, 0);
    ob_start();
    imagegif($f1);
    $base = ob_get_clean();
    imagedestroy($f1);
    // Reaproveita o bloco de imagem do frame unico para duplicar o frame.
    $cabecalho = substr($base, 0, 13);
    $tamTabela = 3 * (1 << ((ord($base[10]) & 7) + 1));
    $ini = 13 + (ord($base[10]) & 0x80 ? $tamTabela : 0);
    $corpo = substr($base, $ini, -1);
    file_put_contents($gif, $cabecalho . substr($base, 13, $ini - 13) . $corpo . $corpo . ';');
    $r = OtimizadorImagem::otimizar($gif, $dir . '/anim-otm');
    verifica($r['ok'] === false && $r['motivo'] !== '', 'GIF animado: ok=false com motivo');
    verifica(!is_file($dir . '/anim-otm.jpg') && !is_file($dir . '/anim-otm.webp') && !is_file($dir . '/anim-otm.png'), 'GIF animado: nada gravado');

    // 5. Arquivo corrompido
    $lixo = $dir . '/lixo.png';
    file_put_contents($lixo, "\x89PNG\r\n\x1a\n" . random_bytes(300));
    $r = OtimizadorImagem::otimizar($lixo, $dir . '/lixo-otm');
    verifica($r['ok'] === false && $r['motivo'] !== '', 'Corrompido: ok=false sem warning nem excecao');
    $texto = $dir . '/texto.png';
    file_put_contents($texto, 'isto nao e imagem');
    $r = OtimizadorImagem::otimizar($texto, $dir . '/texto-otm');
    verifica($r['ok'] === false, 'Nao imagem: ok=false');
    $r = OtimizadorImagem::otimizar($dir . '/inexistente.png', $dir . '/x-otm');
    verifica($r['ok'] === false, 'Inexistente: ok=false');

    // 6. Megapixels
    $r = OtimizadorImagem::otimizar($grande, $dir . '/mp-otm', array('max_megapixels' => 1));
    verifica($r['ok'] === false && $r['motivo'] !== '', 'Acima do limite de megapixels: ok=false');
    verifica(count(glob($dir . '/mp-otm.*')) === 0, 'Megapixels: nada gravado');

    // 7. Nao sobra temporario
    verifica(count(glob($dir . '/*.tmp*')) === 0, 'Sem arquivos temporarios remanescentes');
} catch (Throwable $e) {
    $falhas++;
    echo 'FALHA - excecao: ' . get_class($e) . ': ' . $e->getMessage() . "\n";
}

foreach (glob($dir . '/*') ?: array() as $f) {
    unlink($f);
}
rmdir($dir);

echo "\n$total verificacoes, $falhas falha(s).\n";
exit($falhas === 0 ? 0 : 1);
