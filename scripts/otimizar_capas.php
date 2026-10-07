#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Otimiza as capas já existentes de cursos e categorias (openspec capas-otimizadas).
 *
 *   php scripts/otimizar_capas.php                       simulação (nada é alterado)
 *   php scripts/otimizar_capas.php --aplicar             cria <nome>-otm.<ext> ao lado do original,
 *                                                        aponta o banco para ela e grava o manifesto
 *   php scripts/otimizar_capas.php --reverter=<arquivo>  restaura as referências originais do manifesto
 *   opção extra: --largura=1280
 *
 * Os arquivos originais nunca são apagados nem movidos.
 */

$root = realpath(__DIR__ . '/..');
if ($root === false) {
    fwrite(STDERR, "Não foi possível localizar a raiz do projeto.\n");
    exit(1);
}

define('BASE_PATH', $root);
define('PUBLIC_PATH', BASE_PATH);

require BASE_PATH . '/app/Core/Autoloader.php';

\App\Core\Autoloader::register(BASE_PATH);
\App\Core\Env::load(BASE_PATH . '/.env');

use App\Core\Database;
use App\Support\OtimizadorImagem;

const LIMITE_BYTES = 300 * 1024;
const PREFIXOS_PUBLICOS = array('/assets/uploads/thumbnails/', '/assets/uploads/categorias/');
const TABELAS = array('cursos_eventos', 'categorias');

$opcoes = getopt('', array('aplicar', 'reverter:', 'largura:', 'help'));

if (array_key_exists('help', $opcoes)) {
    echo "Uso: php scripts/otimizar_capas.php [--aplicar] [--reverter=<manifesto>] [--largura=1280]\n";
    exit(0);
}

$larguraMax = isset($opcoes['largura']) ? max(100, (int) $opcoes['largura']) : 1280;
$pdo = Database::connection();

if (isset($opcoes['reverter'])) {
    if (!is_string($opcoes['reverter']) || trim($opcoes['reverter']) === '') {
        fwrite(STDERR, "Informe o manifesto: --reverter=<arquivo>.
");
        exit(1);
    }
    exit(reverter($pdo, $opcoes['reverter']));
}

exit(otimizar($pdo, array_key_exists('aplicar', $opcoes), $larguraMax));

function formatarBytes(int $bytes): string
{
    if ($bytes >= 1024 * 1024) {
        return number_format($bytes / (1024 * 1024), 2, ',', '.') . ' MB';
    }

    return number_format($bytes / 1024, 0, ',', '.') . ' KB';
}

function caminhoPublicoValido(string $valor): bool
{
    foreach (PREFIXOS_PUBLICOS as $prefixo) {
        if (strpos($valor, $prefixo) === 0 && strpos($valor, '..') === false) {
            return true;
        }
    }

    return false;
}

function otimizar(PDO $pdo, bool $aplicar, int $larguraMax): int
{
    $caminhos = array();
    foreach (TABELAS as $tabela) {
        $stmt = $pdo->query('SELECT DISTINCT thumbnail FROM ' . $tabela . ' WHERE deleted_at IS NULL AND thumbnail IS NOT NULL AND thumbnail <> ""');
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $valor) {
            $valor = (string) $valor;
            if (caminhoPublicoValido($valor)) {
                $caminhos[$valor] = true;
            }
        }
    }
    $caminhos = array_keys($caminhos);
    sort($caminhos);

    echo ($aplicar ? 'APLICAÇÃO' : 'SIMULAÇÃO (nada será alterado; use --aplicar para gravar)') . "\n";
    echo 'Largura máxima: ' . $larguraMax . " px\n\n";

    $ausentes = array();
    $ignoradas = array();
    $manifesto = array();
    $totalAntes = 0;
    $totalDepois = 0;
    $candidatas = 0;
    $arquivoManifesto = null;

    if ($aplicar) {
        $dirManifesto = BASE_PATH . '/storage/app/otimizar_capas';
        if (!is_dir($dirManifesto) && !mkdir($dirManifesto, 0775, true) && !is_dir($dirManifesto)) {
            fwrite(STDERR, "Não foi possível criar {$dirManifesto}.\n");
            return 1;
        }
        if (!is_writable($dirManifesto)) {
            fwrite(STDERR, "A pasta do manifesto não tem permissão de escrita: {$dirManifesto}. Nada foi alterado.
");
            return 1;
        }
        $arquivoManifesto = $dirManifesto . '/' . date('Ymd-His') . '.json';
        if (file_put_contents($arquivoManifesto, '[]') === false) {
            fwrite(STDERR, "Não foi possível gravar o manifesto em {$arquivoManifesto}. Nada foi alterado.
");
            return 1;
        }
    }

    foreach ($caminhos as $publico) {
        $absoluto = BASE_PATH . $publico;

        if (!is_file($absoluto)) {
            $ausentes[] = $publico;
            continue;
        }

        if (preg_match('/-otm\.[a-z]+$/i', $publico)) {
            continue;
        }

        $tamanho = (int) filesize($absoluto);
        $info = @getimagesize($absoluto);
        $largura = is_array($info) ? (int) $info[0] : 0;

        if ($largura <= $larguraMax && $tamanho <= LIMITE_BYTES) {
            continue;
        }

        $candidatas++;
        $semExtensao = dirname($absoluto) . '/' . pathinfo($absoluto, PATHINFO_FILENAME) . '-otm';
        $destinoTemp = $aplicar ? $semExtensao : sys_get_temp_dir() . '/otm-sim-' . bin2hex(random_bytes(6));

        $resultado = OtimizadorImagem::otimizar($absoluto, $destinoTemp, array(
            'largura_max' => $larguraMax,
            'exigir_ganho' => true,
        ));

        if (!$resultado['ok']) {
            $ignoradas[] = $publico . ' (' . $resultado['motivo'] . ')';
            continue;
        }

        $novoTamanho = (int) filesize($resultado['caminho']);
        $novoPublico = dirname($publico) . '/' . basename($resultado['caminho']);

        if (!$aplicar) {
            unlink($resultado['caminho']);
        }

        $totalAntes += $tamanho;
        $totalDepois += $novoTamanho;
        printf(
            "%s\n    %s (%d px) -> %s%s\n",
            $publico,
            formatarBytes($tamanho),
            $largura,
            formatarBytes($novoTamanho),
            $aplicar ? ' em ' . $novoPublico : ' (estimado)'
        );

        if ($aplicar) {
            try {
                $trocas = atualizarReferencias($pdo, $publico, $novoPublico, $manifesto, $arquivoManifesto);
            } catch (RuntimeException $e) {
                fwrite(STDERR, "
Falha ao gravar o manifesto ({$e->getMessage()}). A troca de {$publico} foi desfeita; interrompendo.
");
                if ($manifesto) {
                    fwrite(STDERR, "Trocas já aplicadas antes da falha (guarde para --reverter):
" . json_encode($manifesto, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "
");
                    echo json_encode($manifesto, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "
";
                }
                return 1;
            }
            foreach ($trocas as $troca) {
                $manifesto[] = $troca;
            }
            echo '    referências atualizadas: ' . count($trocas) . "\n";
        }
    }

    echo "\nCapas candidatas: {$candidatas}\n";
    if ($candidatas > 0) {
        echo 'Tamanho total: ' . formatarBytes($totalAntes) . ' -> ' . formatarBytes($totalDepois)
            . ' (economia de ' . formatarBytes(max(0, $totalAntes - $totalDepois)) . ")\n";
    }
    if ($ignoradas) {
        echo "\nNão otimizadas (mantidas como estão):\n";
        foreach ($ignoradas as $linha) {
            echo '  - ' . $linha . "\n";
        }
    }
    if ($ausentes) {
        echo "\nReferenciadas no banco, mas ausentes no disco (ignoradas, registro não alterado):\n";
        foreach ($ausentes as $linha) {
            echo '  - ' . $linha . "\n";
        }
    }
    if ($aplicar && !$manifesto && $arquivoManifesto !== null && is_file($arquivoManifesto)) {
        unlink($arquivoManifesto);
    }
    if ($aplicar && $manifesto) {
        echo "\nManifesto: {$arquivoManifesto}\n";
        echo "Para desfazer: php scripts/otimizar_capas.php --reverter={$arquivoManifesto}\n";
    }

    return 0;
}

/**
 * Aponta para a versão otimizada todas as linhas que usam o caminho antigo, em uma transação.
 *
 * @return array<int, array{tabela:string,id:int,de:string,para:string}>
 */
function atualizarReferencias(PDO $pdo, string $de, string $para, array $manifestoAnterior, string $arquivoManifesto): array
{
    $trocas = array();
    $pdo->beginTransaction();

    try {
        foreach (TABELAS as $tabela) {
            $sel = $pdo->prepare('SELECT id FROM ' . $tabela . ' WHERE thumbnail = :de');
            $sel->execute(array('de' => $de));
            $ids = $sel->fetchAll(PDO::FETCH_COLUMN);

            $upd = $pdo->prepare('UPDATE ' . $tabela . ' SET thumbnail = :para WHERE id = :id AND thumbnail = :de');
            foreach ($ids as $id) {
                $upd->execute(array('para' => $para, 'id' => (int) $id, 'de' => $de));
                $trocas[] = array('tabela' => $tabela, 'id' => (int) $id, 'de' => $de, 'para' => $para);
            }
        }
        // Manifesto gravado ANTES do commit: se não der para gravar, a troca é desfeita.
        $json = json_encode(array_merge($manifestoAnterior, $trocas), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false || file_put_contents($arquivoManifesto, $json) === false) {
            throw new RuntimeException('escrita recusada em ' . $arquivoManifesto);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return $trocas;
}

function reverter(PDO $pdo, string $arquivo): int
{
    if (!is_file($arquivo)) {
        fwrite(STDERR, "Manifesto não encontrado: {$arquivo}\n");
        return 1;
    }

    $manifesto = json_decode((string) file_get_contents($arquivo), true);
    if (!is_array($manifesto)) {
        fwrite(STDERR, "Manifesto inválido: {$arquivo}\n");
        return 1;
    }

    $restauradas = 0;
    $puladas = 0;

    $pdo->beginTransaction();
    try {
        foreach ($manifesto as $item) {
            if (!is_array($item) || !isset($item['tabela'], $item['id'], $item['de'], $item['para']) || !in_array($item['tabela'], TABELAS, true)
                || !is_string($item['de']) || !is_string($item['para'])
                || !caminhoPublicoValido($item['de']) || !caminhoPublicoValido($item['para'])) {
                $puladas++;
                continue;
            }

            // Só restaura se o registro ainda aponta para a versão otimizada.
            $upd = $pdo->prepare('UPDATE ' . $item['tabela'] . ' SET thumbnail = :de WHERE id = :id AND thumbnail = :para');
            $upd->execute(array('de' => $item['de'], 'id' => (int) $item['id'], 'para' => $item['para']));
            if ($upd->rowCount() > 0) {
                $restauradas++;
            } else {
                $puladas++;
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    echo "Reversão concluída: {$restauradas} referência(s) restaurada(s), {$puladas} ignorada(s) (já alteradas depois da aplicação).\n";

    return 0;
}
