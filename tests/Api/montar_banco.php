<?php

/**
 * Monta o banco de teste da API do app do zero, em um container MariaDB 10.5.
 *
 * 1. Recria a base (DROP + CREATE) — só no container de teste, nunca em outro lugar.
 * 2. Aplica TODAS as migrações de sql/*.sql em ordem de nome (como em produção).
 * 3. Aplica as fixtures de tests/Api/fixtures.sql (usuários, curso, turma, pedidos...).
 *
 * Uso:
 *   php tests/Api/montar_banco.php            # migrações + fixtures
 *   php tests/Api/montar_banco.php --so-fixtures
 *
 * Variáveis (padrões entre parênteses):
 *   APP_TESTE_CONTAINER (desbloqueia-test-db)  APP_TESTE_DB (desbloqueia_app_teste)
 *   APP_TESTE_DB_ROOT_PASS (teste_root_123)
 *
 * GUARDA: o nome da base precisa terminar em "_teste". O script fala com o
 * container via `docker exec`, nunca com host remoto.
 */

$raiz = dirname(__DIR__, 2);
$container = getenv('APP_TESTE_CONTAINER') ?: 'desbloqueia-test-db';
$banco = getenv('APP_TESTE_DB') ?: 'desbloqueia_app_teste';
$senha = getenv('APP_TESTE_DB_ROOT_PASS') ?: 'teste_root_123';
$soFixtures = in_array('--so-fixtures', $argv, true);

if (!preg_match('/^[a-z0-9_]+_teste$/', $banco)) {
    fwrite(STDERR, "RECUSADO: a base precisa terminar em _teste (recebido: {$banco}).\n");
    exit(1);
}

function mb_exec_sql($container, $senha, $banco, $sql)
{
    $cmd = sprintf(
        'docker exec -i %s mysql -uroot -p%s --default-character-set=utf8mb4 %s',
        escapeshellarg($container),
        escapeshellarg($senha),
        $banco === '' ? '' : escapeshellarg($banco)
    );
    $proc = proc_open($cmd, array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
    if (!is_resource($proc)) {
        return array(1, 'não foi possível executar docker');
    }
    fwrite($pipes[0], $sql);
    fclose($pipes[0]);
    $saida = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $codigo = proc_close($proc);
    $saida = trim(preg_replace('/^.*Using a password.*$/m', '', $saida));
    return array($codigo, $saida);
}

// Espera o servidor aceitar conexão (o container pode estar subindo).
$pronto = false;
for ($i = 0; $i < 60; $i++) {
    list($c) = mb_exec_sql($container, $senha, '', 'SELECT 1;');
    if ($c === 0) {
        $pronto = true;
        break;
    }
    sleep(1);
}
if (!$pronto) {
    fwrite(STDERR, "ERRO: MariaDB do container {$container} não respondeu.\n");
    exit(1);
}

$falhas = 0;
if (!$soFixtures) {
    list($c, $s) = mb_exec_sql($container, $senha, '', "DROP DATABASE IF EXISTS `{$banco}`; CREATE DATABASE `{$banco}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
    if ($c !== 0) {
        fwrite(STDERR, "ERRO ao recriar a base: {$s}\n");
        exit(1);
    }

    $arquivos = glob($raiz . '/sql/*.sql');
    sort($arquivos, SORT_STRING);
    $aplicadas = 0;
    foreach ($arquivos as $arquivo) {
        $nome = basename($arquivo);
        if (!preg_match('/^\d{3}_/', $nome)) {
            continue; // ex.: modelo_questoes_simulado_pnd.sql (modelo, não migração)
        }
        list($c, $s) = mb_exec_sql($container, $senha, $banco, file_get_contents($arquivo));
        if ($c !== 0 || stripos($s, 'ERROR') !== false) {
            $falhas++;
            echo "  ✗ {$nome}\n    {$s}\n";
            continue;
        }
        $aplicadas++;
    }
    echo "Migrações: {$aplicadas} aplicadas, {$falhas} com erro.\n";
}

// 1) Fixture da área do aluno do tema caderno (aluna 9001 com todos os tipos de
//    conteúdo). Ela se recusa a rodar fora de `desbloqueia_local`; aqui a guarda
//    é trocada, só em memória, para o banco de teste deste container.
// 2) Fixture própria da API (outro aluno, inativo, bloqueio, certificado alheio).
$aplicar = array(
    'tema_caderno_aluno.sql' => str_replace(
        "IF(DATABASE() = 'desbloqueia_local'",
        "IF(DATABASE() = '" . $banco . "'",
        (string) file_get_contents($raiz . '/tests/Fixtures/tema_caderno_aluno.sql')
    ),
    'fixtures.sql' => str_replace(
        "IF(DATABASE() = 'desbloqueia_app_teste'",
        "IF(DATABASE() = '" . $banco . "'",
        (string) file_get_contents(__DIR__ . '/fixtures.sql')
    ),
);
foreach ($aplicar as $nome => $sql) {
    list($c, $s) = mb_exec_sql($container, $senha, $banco, $sql);
    if ($c !== 0 || stripos($s, 'ERROR') !== false || stripos($s, 'ABORTADO') !== false) {
        echo "  ✗ {$nome}\n    {$s}\n";
        $falhas++;
    } else {
        echo "Fixture aplicada: {$nome}\n";
    }
}

// Arquivo físico do item 9008 (a fixture do tema aponta para um caminho fictício).
$pdfMaterial = $raiz . '/storage/private_uploads/private_uploads/fixtures/fixture-caderno-material.pdf';
if (!is_dir(dirname($pdfMaterial))) {
    @mkdir(dirname($pdfMaterial), 0775, true);
}
file_put_contents($pdfMaterial, "%PDF-1.4\n% material de apoio da fixture (teste da API do app)\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n");

exit($falhas > 0 ? 1 : 0);
