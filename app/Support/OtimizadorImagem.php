<?php

namespace App\Support;

use App\Core\Logger;

/**
 * Redimensiona e recodifica imagens de capa (cursos e categorias).
 *
 * Nunca lança exceção nem emite warning: o ErrorHandler do projeto transforma
 * qualquer aviso em HTTP 500, e a otimização nunca pode derrubar um upload.
 * Em caso de falha devolve ok=false com o motivo; o chamador usa o original.
 */
class OtimizadorImagem
{
    public static function suportaWebp()
    {
        if (!function_exists('imagewebp') || !function_exists('imagecreatefromwebp') || !function_exists('gd_info')) {
            return false;
        }

        $info = gd_info();

        return !empty($info['WebP Support']);
    }

    /**
     * @param string $origem             Arquivo de imagem existente.
     * @param string $destinoSemExtensao Caminho de destino sem extensão.
     * @param array  $opcoes             largura_max, qualidade_webp, qualidade_jpeg,
     *                                   max_megapixels, exigir_ganho.
     * @return array ok, caminho, extensao, motivo
     */
    public static function otimizar($origem, $destinoSemExtensao, array $opcoes = array())
    {
        $opcoes = array_merge(array(
            'largura_max' => 1280,
            'qualidade_webp' => 80,
            'qualidade_jpeg' => 82,
            'max_megapixels' => 40,
            'exigir_ganho' => false,
        ), $opcoes);

        // Avisos do GD/EXIF são engolidos durante a operação; o handler anterior é restaurado ao final.
        set_error_handler(function () {
            return true;
        });

        $estado = array('imagem' => null, 'saida' => null, 'temporario' => null);

        try {
            $resultado = self::processar($origem, (string) $destinoSemExtensao, $opcoes, $estado);
        } catch (\Throwable $e) {
            $resultado = self::falha('erro inesperado: ' . $e->getMessage());
        }

        foreach (array($estado['imagem'], $estado['saida']) as $recurso) {
            if ($recurso instanceof \GdImage) {
                imagedestroy($recurso);
            }
        }
        if ($estado['temporario'] !== null && is_file($estado['temporario'])) {
            unlink($estado['temporario']);
        }

        restore_error_handler();

        return $resultado;
    }

    /**
     * Otimiza uma capa recém-gravada (ainda não referenciada por ninguém).
     * Devolve o novo nome de arquivo (basename) quando otimizou — e remove o
     * arquivo enviado — ou null para manter o original, registrando o motivo.
     */
    public static function otimizarCapaGravada($arquivoAbsoluto)
    {
        $arquivoAbsoluto = (string) $arquivoAbsoluto;
        $semExtensao = dirname($arquivoAbsoluto) . '/' . pathinfo($arquivoAbsoluto, PATHINFO_FILENAME);
        $resultado = self::otimizar($arquivoAbsoluto, $semExtensao);

        if (!$resultado['ok']) {
            Logger::warning('midia.capa.otimizacao_ignorada', array(
                'arquivo' => basename((string) $arquivoAbsoluto),
                'motivo' => $resultado['motivo'],
            ));

            return null;
        }

        if ($resultado['caminho'] !== $arquivoAbsoluto && is_file($arquivoAbsoluto)) {
            unlink($arquivoAbsoluto);
        }

        return basename($resultado['caminho']);
    }

    private static function falha($motivo)
    {
        return array('ok' => false, 'caminho' => '', 'extensao' => '', 'motivo' => $motivo);
    }

    private static function processar($origem, $destinoSemExtensao, array $opcoes, array &$estado)
    {
        if (!extension_loaded('gd')) {
            return self::falha('extensão GD indisponível');
        }
        if (!is_string($origem) || !is_file($origem) || !is_readable($origem)) {
            return self::falha('arquivo de origem inexistente ou ilegível');
        }

        $info = getimagesize($origem);
        if ($info === false || empty($info[0]) || empty($info[1])) {
            return self::falha('arquivo não é uma imagem válida');
        }

        $largura = (int) $info[0];
        $altura = (int) $info[1];
        $tipo = (int) $info[2];

        if (($largura * $altura) > ((float) $opcoes['max_megapixels'] * 1000000)) {
            return self::falha('imagem acima do limite de ' . $opcoes['max_megapixels'] . ' megapixels');
        }

        $decodificadores = array(
            IMAGETYPE_JPEG => 'imagecreatefromjpeg',
            IMAGETYPE_PNG => 'imagecreatefrompng',
            IMAGETYPE_GIF => 'imagecreatefromgif',
            IMAGETYPE_WEBP => 'imagecreatefromwebp',
        );
        if (!isset($decodificadores[$tipo]) || !function_exists($decodificadores[$tipo])) {
            return self::falha('formato sem decodificador disponível');
        }

        if ($tipo === IMAGETYPE_GIF && self::gifAnimado($origem)) {
            return self::falha('GIF animado não é otimizado');
        }

        if (!self::memoriaSuficiente($largura, $altura)) {
            return self::falha('memória insuficiente para decodificar a imagem');
        }

        $estado['imagem'] = $decodificadores[$tipo]($origem);
        if (!($estado['imagem'] instanceof \GdImage)) {
            $estado['imagem'] = null;
            return self::falha('falha ao decodificar a imagem (arquivo corrompido?)');
        }

        if ($tipo === IMAGETYPE_JPEG) {
            $estado['imagem'] = self::corrigirOrientacao($estado['imagem'], $origem);
            $largura = imagesx($estado['imagem']);
            $altura = imagesy($estado['imagem']);
        }

        $larguraMax = max(1, (int) $opcoes['largura_max']);
        $novaLargura = $largura > $larguraMax ? $larguraMax : $largura;
        $novaAltura = $largura > $larguraMax ? max(1, (int) round($altura * $larguraMax / $largura)) : $altura;

        $estado['saida'] = imagecreatetruecolor($novaLargura, $novaAltura);
        if (!($estado['saida'] instanceof \GdImage)) {
            $estado['saida'] = null;
            return self::falha('falha ao criar a imagem de destino');
        }
        imagealphablending($estado['saida'], false);
        imagesavealpha($estado['saida'], true);
        imagefill($estado['saida'], 0, 0, imagecolorallocatealpha($estado['saida'], 255, 255, 255, 127));

        if (!imagecopyresampled($estado['saida'], $estado['imagem'], 0, 0, 0, 0, $novaLargura, $novaAltura, $largura, $altura)) {
            return self::falha('falha ao redimensionar a imagem');
        }
        imagedestroy($estado['imagem']);
        $estado['imagem'] = null;

        $usaWebp = self::suportaWebp();
        $transparente = ($tipo !== IMAGETYPE_JPEG) && self::temTransparencia($estado['saida']);

        if ($usaWebp) {
            $extensao = 'webp';
        } elseif ($transparente) {
            $extensao = 'png';
        } else {
            $extensao = 'jpg';
            // JPEG não tem alpha: achata sobre fundo branco.
            $plano = imagecreatetruecolor($novaLargura, $novaAltura);
            if (!($plano instanceof \GdImage)) {
                return self::falha('falha ao criar a imagem de destino');
            }
            imagefill($plano, 0, 0, imagecolorallocate($plano, 255, 255, 255));
            imagealphablending($plano, true);
            imagecopy($plano, $estado['saida'], 0, 0, 0, 0, $novaLargura, $novaAltura);
            imagedestroy($estado['saida']);
            $estado['saida'] = $plano;
        }

        $destino = $destinoSemExtensao . '.' . $extensao;
        $dirDestino = dirname($destino);
        if (!is_dir($dirDestino) || !is_writable($dirDestino)) {
            return self::falha('pasta de destino inexistente ou sem permissão de escrita');
        }

        $estado['temporario'] = $dirDestino . '/.otm-' . bin2hex(random_bytes(6)) . '.tmp';
        $temporario = $estado['temporario'];
        if ($extensao === 'webp') {
            $gravou = imagewebp($estado['saida'], $temporario, (int) $opcoes['qualidade_webp']);
        } elseif ($extensao === 'png') {
            $gravou = imagepng($estado['saida'], $temporario, 9);
        } else {
            imageinterlace($estado['saida'], true);
            $gravou = imagejpeg($estado['saida'], $temporario, (int) $opcoes['qualidade_jpeg']);
        }

        clearstatcache(true, $temporario);
        if (!$gravou || !is_file($temporario) || filesize($temporario) <= 0) {
            return self::falha('falha ao gravar a imagem otimizada');
        }

        if (!empty($opcoes['exigir_ganho']) && $novaLargura === $largura && filesize($temporario) >= filesize($origem)) {
            return self::falha('sem ganho de tamanho');
        }

        if (!rename($temporario, $destino)) {
            return self::falha('falha ao mover a imagem otimizada para o destino');
        }
        $estado['temporario'] = null;

        return array('ok' => true, 'caminho' => $destino, 'extensao' => $extensao, 'motivo' => '');
    }

    private static function gifAnimado($arquivo)
    {
        $c = file_get_contents($arquivo);
        if ($c === false || strlen($c) < 14) {
            return false;
        }

        $tam = strlen($c);
        $pos = 13;
        if ((ord($c[10]) & 0x80) !== 0) {
            $pos += 3 * (1 << ((ord($c[10]) & 7) + 1));
        }

        $quadros = 0;
        while ($pos < $tam) {
            $marca = ord($c[$pos]);
            if ($marca === 0x3B) {
                break;
            }
            if ($marca === 0x21) {
                $pos += 2;
            } elseif ($marca === 0x2C) {
                $quadros++;
                if ($quadros > 1) {
                    return true;
                }
                if ($pos + 10 > $tam) {
                    break;
                }
                $flags = ord($c[$pos + 9]);
                $pos += 10;
                if (($flags & 0x80) !== 0) {
                    $pos += 3 * (1 << (($flags & 7) + 1));
                }
                $pos += 1;
            } else {
                break;
            }
            // Sub-blocos de dados terminados por bloco de tamanho zero.
            while ($pos < $tam) {
                $n = ord($c[$pos]);
                $pos += 1 + $n;
                if ($n === 0) {
                    break;
                }
            }
        }

        return false;
    }

    private static function memoriaSuficiente($largura, $altura)
    {
        $limite = (string) ini_get('memory_limit');
        if ($limite === '' || (int) $limite === -1) {
            return true;
        }

        $bytes = (int) $limite;
        $unidade = strtolower(substr(trim($limite), -1));
        if ($unidade === 'g') {
            $bytes *= 1024 * 1024 * 1024;
        } elseif ($unidade === 'm') {
            $bytes *= 1024 * 1024;
        } elseif ($unidade === 'k') {
            $bytes *= 1024;
        }

        // Origem + destino em RGBA (4 bytes/pixel) com folga.
        $necessario = ($largura * $altura * 4 * 2) + (8 * 1024 * 1024);

        return ($bytes - memory_get_usage(true)) > $necessario;
    }

    private static function corrigirOrientacao(\GdImage $imagem, $arquivo)
    {
        if (!function_exists('exif_read_data') || !function_exists('imagerotate')) {
            return $imagem;
        }

        $exif = exif_read_data($arquivo);
        $orientacao = is_array($exif) && isset($exif['Orientation']) ? (int) $exif['Orientation'] : 1;
        $angulos = array(3 => 180, 6 => -90, 8 => 90);
        if (!isset($angulos[$orientacao])) {
            return $imagem;
        }

        $girada = imagerotate($imagem, $angulos[$orientacao], 0);
        if ($girada instanceof \GdImage) {
            imagedestroy($imagem);
            return $girada;
        }

        return $imagem;
    }

    private static function temTransparencia(\GdImage $imagem)
    {
        $w = imagesx($imagem);
        $h = imagesy($imagem);
        $passo = max(1, (int) floor(min($w, $h) / 200));
        for ($y = 0; $y < $h; $y += $passo) {
            for ($x = 0; $x < $w; $x += $passo) {
                if (((imagecolorat($imagem, $x, $y) >> 24) & 0x7F) > 0) {
                    return true;
                }
            }
        }

        return false;
    }
}
