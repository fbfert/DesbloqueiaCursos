<?php

/**
 * Executa todos os testes da Norminha em sequência.
 *
 * O projeto não tem runner: cada arquivo em tests/Unit/ é chamado à mão. Com
 * quinze arquivos, "rodar tudo" virou uma linha de comando comprida que alguém
 * um dia digita errado e conclui que passou.
 *
 * Isto é um script simples — sem PHPUnit, sem composer, como o resto do
 * projeto. Cada teste roda em processo próprio, para que uma falha fatal em um
 * não derrube os outros.
 *
 * Uso:
 *   php tests/Unit/norminha_todos.php
 *   NORMINHA_API_CREDS=/caminho/creds.json php tests/Unit/norminha_todos.php
 *
 * Sai com 0 se todos passaram, 1 se qualquer um falhou.
 */

$dir = __DIR__;
$arquivos = glob($dir . '/norminha_*.php') ?: array();
sort($arquivos);

$php = PHP_BINARY !== '' ? PHP_BINARY : 'php';
$totalPassou = 0;
$totalFalhou = 0;
$comProblema = array();
$pulados = array();
$inicio = microtime(true);

echo "\nTestes da Norminha\n" . str_repeat('=', 62) . "\n";

foreach ($arquivos as $arquivo) {
    $nome = basename($arquivo, '.php');
    if ($nome === 'norminha_todos') {
        continue;
    }

    // O teste de API precisa de servidor e credenciais; sem elas ele mesmo
    // decide pular. Aqui só se registra o motivo.
    $saida = array();
    $codigo = 0;
    exec(escapeshellarg($php) . ' ' . escapeshellarg($arquivo) . ' 2>&1', $saida, $codigo);
    $texto = implode("\n", $saida);

    if (preg_match('/Resultado:\s*(\d+)\s*passou\s*\|\s*(\d+)\s*falhou/u', $texto, $m)) {
        $passou = (int) $m[1];
        $falhou = (int) $m[2];
        $totalPassou += $passou;
        $totalFalhou += $falhou;

        printf("  %-26s %3d passou   %d falhou%s\n", $nome, $passou, $falhou, $falhou > 0 ? '   <-- FALHA' : '');

        if ($falhou > 0) {
            $comProblema[$nome] = array();
            foreach ($saida as $linha) {
                if (strpos($linha, '✗') !== false || strpos($linha, '→') !== false) {
                    $comProblema[$nome][] = trim($linha);
                }
            }
        }
        continue;
    }

    if (strpos($texto, 'SKIP') !== false) {
        $pulados[] = $nome;
        printf("  %-26s pulado\n", $nome);
        continue;
    }

    $totalFalhou++;
    $comProblema[$nome] = array('nao produziu resultado legivel (exit ' . $codigo . ')');
    printf("  %-26s SEM RESULTADO   <-- FALHA\n", $nome);
}

echo str_repeat('=', 62) . "\n";

if ($comProblema) {
    echo "\nDetalhe das falhas:\n";
    foreach ($comProblema as $nome => $linhas) {
        echo "\n  {$nome}\n";
        foreach (array_slice($linhas, 0, 8) as $l) {
            echo "    {$l}\n";
        }
    }
    echo "\n";
}

if ($pulados) {
    echo "\nPulados: " . implode(', ', $pulados) . "\n";
    echo "(o teste de API exige servidor local e NORMINHA_API_CREDS)\n";
}

printf("\n%d casos · %d passou · %d falhou · %.1fs\n",
    $totalPassou + $totalFalhou, $totalPassou, $totalFalhou, microtime(true) - $inicio);

exit($totalFalhou > 0 ? 1 : 0);
