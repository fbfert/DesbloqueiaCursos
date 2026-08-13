#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Encerra tentativas de quiz cujo prazo venceu no servidor.
 *
 * Necessario porque o prazo e verificado quando o aluno interage com a prova
 * (abrir a tela, salvar rascunho, enviar, cronometro). Se ele fecha o
 * navegador e nao volta, a tentativa ficaria em_andamento indefinidamente.
 *
 * A regra aplicada e a configurada no proprio quiz
 * (conteudo_quizzes.acao_ao_expirar):
 *   - 'enviar_automatico'  : corrige o que estava salvo (padrao do PND);
 *   - 'encerrar_sem_envio' : encerra a tentativa sem corrigir.
 *
 * Uso:
 *   php scripts/cron_quiz_tentativas_expiradas.php [--limit=50] [--dry-run]
 */

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
$dryRun  = array_key_exists('dry-run', $options);
$limit   = isset($options['limit']) ? (int) $options['limit'] : 50;

if (array_key_exists('help', $options)) {
    echo "Uso: php scripts/cron_quiz_tentativas_expiradas.php [--dry-run] [--limit=50]\n";
    exit(0);
}

if ($limit <= 0) {
    $limit = 50;
}

$lockDir = BASE_PATH . '/storage/tmp';
if (!is_dir($lockDir) && !@mkdir($lockDir, 0775, true) && !is_dir($lockDir)) {
    fwrite(STDERR, "Nao foi possivel preparar storage/tmp.\n");
    exit(1);
}

$lockFile = $lockDir . '/quiz_tentativas_expiradas.lock';
$handle   = fopen($lockFile, 'c+');
if ($handle === false) {
    fwrite(STDERR, "Nao foi possivel abrir o lock file.\n");
    exit(1);
}

// Evita que duas execucoes concorrentes encerrem a mesma tentativa.
if (!flock($handle, LOCK_EX | LOCK_NB)) {
    echo "Outra execucao da cron de tentativas expiradas ja esta em andamento.\n";
    fclose($handle);
    exit(0);
}

$tentativaModel = new \App\Models\ConteudoQuizTentativa();
$pendentes      = $tentativaModel->listExpiradas($limit);

echo "Tentativas de quiz com prazo vencido\n";
echo 'Modo: ' . ($dryRun ? 'dry-run' : 'execucao real') . PHP_EOL;
echo 'Encontradas: ' . count($pendentes) . PHP_EOL;

$encerradas = 0;
$falhas     = 0;

if ($dryRun) {
    foreach ($pendentes as $tentativa) {
        // Sem dados pessoais na saida da cron: apenas identificadores.
        echo '  tentativa #' . (int) $tentativa['id']
            . ' quiz=' . (int) $tentativa['quiz_id']
            . ' expirou_em=' . (string) $tentativa['expira_em'] . PHP_EOL;
    }
} else {
    $quizService = new \App\Services\ConteudoQuizService();
    foreach ($pendentes as $tentativa) {
        try {
            $resultado = $quizService->encerrarSeExpirada($tentativa);
            if (!empty($resultado['encerrada'])) {
                $encerradas++;
                echo '  encerrada #' . (int) $tentativa['id']
                    . ' (' . (string) ($resultado['motivo'] ?? '-') . ')' . PHP_EOL;
            }
        } catch (\Throwable $e) {
            $falhas++;
            \App\Core\Logger::error('quiz.cron.expiradas.erro', array(
                'tentativa_id' => (int) $tentativa['id'],
                'excecao'      => get_class($e),
            ));
            fwrite(STDERR, 'Falha ao encerrar a tentativa #' . (int) $tentativa['id'] . PHP_EOL);
        }
    }
}

echo 'Encerradas: ' . $encerradas . PHP_EOL;
echo 'Falhas: ' . $falhas . PHP_EOL;

if (!$dryRun && ($encerradas > 0 || $falhas > 0)) {
    \App\Core\Logger::info('quiz.cron.expiradas', array(
        'encontradas' => count($pendentes),
        'encerradas'  => $encerradas,
        'falhas'      => $falhas,
    ));
}

flock($handle, LOCK_UN);
fclose($handle);

exit($falhas > 0 ? 1 : 0);
