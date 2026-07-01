#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = realpath(__DIR__ . '/..');
if ($root === false) {
    fwrite(STDERR, "Nao foi possivel localizar a raiz do projeto.\n");
    exit(1);
}

define('BASE_PATH', $root);
define('PUBLIC_PATH', BASE_PATH);

require BASE_PATH . '/app/Core/Autoloader.php';

\App\Core\Autoloader::register(BASE_PATH);
\App\Core\Env::load(BASE_PATH . '/.env');

$options = getopt('', array('dry-run', 'limit::', 'help'));
$dryRun = array_key_exists('dry-run', $options);
$limit = isset($options['limit']) ? (int) $options['limit'] : null;

if (array_key_exists('help', $options)) {
    echo "Uso: php scripts/cron_recuperacao_pedidos.php [--dry-run] [--limit=50]\n";
    exit(0);
}

$lockDir = BASE_PATH . '/storage/tmp';
if (!is_dir($lockDir) && !@mkdir($lockDir, 0775, true) && !is_dir($lockDir)) {
    fwrite(STDERR, "Nao foi possivel preparar storage/tmp.\n");
    exit(1);
}

$lockFile = $lockDir . '/pedido_recuperacao_cron.lock';
$handle = fopen($lockFile, 'c+');
if ($handle === false) {
    fwrite(STDERR, "Nao foi possivel abrir o lock file.\n");
    exit(1);
}

if (!flock($handle, LOCK_EX | LOCK_NB)) {
    echo "Outra execucao da cron de recuperacao ja esta em andamento.\n";
    fclose($handle);
    exit(0);
}

$service = new \App\Services\PedidoRecuperacaoService();
$resultado = $service->executarAutomacao($limit, $dryRun, array(
    'ip_address' => '127.0.0.1',
    'user_agent' => 'cron-recuperacao-pedidos',
));

$stats = isset($resultado['stats']) && is_array($resultado['stats']) ? $resultado['stats'] : array();

echo "Recuperacao de pedidos incompletos\n";
echo 'Modo: ' . ($dryRun ? 'dry-run' : 'execucao real') . PHP_EOL;
echo 'Status: ' . (isset($resultado['status']) ? $resultado['status'] : 'desconhecido') . PHP_EOL;
echo 'Execucao ID: ' . (int) ($resultado['execucao_id'] ?? 0) . PHP_EOL;
echo 'Analisados: ' . (int) ($stats['analisados'] ?? 0) . PHP_EOL;
echo 'Processados: ' . (int) ($stats['processados'] ?? 0) . PHP_EOL;
echo 'Enviados: ' . (int) ($stats['enviados'] ?? 0) . PHP_EOL;
echo 'Ignorados: ' . (int) ($stats['ignorados'] ?? 0) . PHP_EOL;
echo 'Bloqueados: ' . (int) ($stats['bloqueados'] ?? 0) . PHP_EOL;
echo 'Erros: ' . (int) ($stats['erros'] ?? 0) . PHP_EOL;

flock($handle, LOCK_UN);
fclose($handle);

if (empty($resultado['ok']) && (($resultado['status'] ?? '') !== 'inativa')) {
    fwrite(STDERR, (string) ($resultado['message'] ?? 'Falha na execucao da cron.').PHP_EOL);
    exit(1);
}

exit(0);
