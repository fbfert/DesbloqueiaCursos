#!/usr/bin/env php
<?php

/**
 * Envia as notificações push pendentes do app do aluno e reenvia as que falharam.
 *
 * Por que existe: não há worker de fila no servidor. As notificações de um aluno
 * só (pedido aprovado, avaliação corrigida...) tentam o envio na hora, com
 * timeout curto; as de turma inteira (conteúdo novo) só são gravadas. Este
 * script, rodando por cron, entrega as pendentes e tenta de novo as que
 * falharam (até PushService::MAX_TENTATIVAS), descartando as com mais de 48 h.
 * Também limpa tokens vencidos do app e contadores de limite de taxa antigos.
 *
 * Com FCM_ENABLED=false não envia nada (as notificações seguem gravadas e
 * visíveis no histórico do app).
 *
 * Uso (cron sugerido: a cada 5 minutos):
 *   php scripts/push_reenviar_pendentes.php [--limit=200] [--horas=48]
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

$options = getopt('', array('limit::', 'horas::', 'help'));
if (array_key_exists('help', $options)) {
    echo "Uso: php scripts/push_reenviar_pendentes.php [--limit=200] [--horas=48]\n";
    exit(0);
}
$limite = isset($options['limit']) ? max(1, (int) $options['limit']) : 200;
$horas = isset($options['horas']) ? max(1, (int) $options['horas']) : 48;

$lockDir = BASE_PATH . '/storage/tmp';
if (!is_dir($lockDir) && !@mkdir($lockDir, 0775, true) && !is_dir($lockDir)) {
    fwrite(STDERR, "Não foi possível preparar storage/tmp.\n");
    exit(1);
}

$handle = fopen($lockDir . '/push_reenviar_pendentes.lock', 'c+');
if ($handle === false) {
    fwrite(STDERR, "Não foi possível abrir o lock file.\n");
    exit(1);
}
if (!flock($handle, LOCK_EX | LOCK_NB)) {
    echo "Outra execução do envio de push já está em andamento.\n";
    fclose($handle);
    exit(0);
}

$codigo = 0;
try {
    $resumo = (new \App\Services\PushService())->reenviarPendentes($limite, $horas);

    echo 'FCM habilitado: ' . ($resumo['habilitado'] ? 'sim' : 'não') . PHP_EOL;
    echo 'Processadas: ' . $resumo['processadas'] . PHP_EOL;
    echo 'Enviadas: ' . $resumo['enviadas'] . PHP_EOL;
    echo 'Sem dispositivo: ' . $resumo['sem_dispositivo'] . PHP_EOL;
    echo 'Falhas: ' . $resumo['falhas'] . PHP_EOL;
    echo 'Expiradas (sem envio): ' . $resumo['expiradas'] . PHP_EOL;

    // Limpeza: tokens vencidos/revogados há mais de 30 dias e contadores parados.
    $limiteTokens = \App\Support\AppApi\Tempo::sql(\App\Support\AppApi\Tempo::agoraTs() - (30 * 86400));
    $tokensRemovidos = (new \App\Models\AppToken())->limparAntigos($limiteTokens);
    $contadoresRemovidos = (new \App\Services\AppLimiteTaxaService())->limparAntigos();
    echo 'Tokens antigos removidos: ' . $tokensRemovidos . PHP_EOL;
    echo 'Contadores de limite removidos: ' . $contadoresRemovidos . PHP_EOL;

    if ($resumo['processadas'] > 0 || $resumo['expiradas'] > 0) {
        \App\Core\Logger::info('push.cron', $resumo);
    }
} catch (\Throwable $e) {
    $codigo = 1;
    \App\Core\Logger::error('push.cron.erro', array('exception' => get_class($e), 'message' => $e->getMessage()));
    fwrite(STDERR, 'Falha no envio de push: ' . get_class($e) . PHP_EOL);
}

flock($handle, LOCK_UN);
fclose($handle);

exit($codigo);
