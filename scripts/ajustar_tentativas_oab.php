<?php
/**
 * Ajusta o numero de tentativas dos quizzes do curso 125.
 *
 * Nao muda nada sem antes conferir, quiz por quiz, se o banco aguenta o
 * numero pedido de tentativas INEDITAS. A conta nao sai do tamanho do banco:
 * sai do tamanho do SORTEIO, repartido entre as dificuldades pelo mesmo
 * metodo do maior resto que o QuizSorteioService usa. Foi errar essa conta
 * que quase publicou quatro quizzes de treino repetindo questao na 3a rodada.
 *
 *   php scripts/ajustar_tentativas_oab.php --tentativas=5 [--quiz=51] [--aplicar]
 */
define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/Core/Env.php';
App\Core\Env::load(BASE_PATH . '/.env');
require BASE_PATH . '/app/Core/Database.php';

const CURSO_OAB = 125;

$opts = getopt('', array('tentativas:', 'quiz:', 'aplicar'));
$alvo = (int) ($opts['tentativas'] ?? 0);
if ($alvo < 1) { fwrite(STDERR, "uso: --tentativas=N [--quiz=ID] [--aplicar]\n"); exit(1); }
$aplicar = isset($opts['aplicar']);
$filtro = isset($opts['quiz']) ? ' AND q.id = ' . (int) $opts['quiz'] : '';

$pdo = App\Core\Database::connection();

/** cota por tentativa: maior resto sobre a quantidade sorteada */
function cotas($total, array $pct)
{
    $bruto = array();
    foreach (array('facil', 'media', 'dificil') as $d) { $bruto[$d] = $total * ($pct[$d] / 100); }
    $c = array_map('floor', $bruto);
    $resto = $total - array_sum($c);
    $rem = array();
    foreach ($bruto as $d => $v) { $rem[$d] = $v - floor($v); }
    arsort($rem);
    foreach (array_keys($rem) as $d) { if ($resto <= 0) { break; } $c[$d]++; $resto--; }
    return $c;
}

$quizzes = $pdo->query(
    'SELECT q.id, q.tentativas_maximas t, i.titulo, i.status
       FROM conteudo_quizzes q
       JOIN conteudo_itens i ON i.id = q.item_id
       JOIN conteudo_modulos m ON m.id = i.modulo_id
      WHERE m.curso_evento_id = ' . CURSO_OAB . ' AND i.deleted_at IS NULL' . $filtro . '
      ORDER BY q.id'
)->fetchAll(PDO::FETCH_ASSOC);

$aptos = array();
$barrados = array();

foreach ($quizzes as $q) {
    $faltas = array();
    foreach ($pdo->query("SELECT b.codigo, b.quantidade_sortear qs, b.distribuicao_dificuldade_json dj,
                COUNT(p.id) n, SUM(p.dificuldade='facil') f, SUM(p.dificuldade='media') m,
                SUM(p.dificuldade='dificil') d
           FROM conteudo_quiz_blocos b
           LEFT JOIN conteudo_quiz_perguntas p ON p.bloco_id = b.id AND p.deleted_at IS NULL
          WHERE b.quiz_id = {$q['id']} AND b.deleted_at IS NULL GROUP BY b.id") as $b) {
        $pct = json_decode((string) $b['dj'], true);
        if (!is_array($pct)) { $pct = array('facil' => 20, 'media' => 60, 'dificil' => 20); }
        $c = cotas((int) $b['qs'], $pct);
        $tem = array('facil' => (int) $b['f'], 'media' => (int) $b['m'], 'dificil' => (int) $b['d']);
        foreach ($c as $dif => $porTentativa) {
            $precisa = $alvo * $porTentativa;
            if ($tem[$dif] < $precisa) {
                $faltas[] = "{$b['codigo']}: {$dif} tem {$tem[$dif]}, precisa de {$precisa}";
            }
        }
    }
    if ($faltas) { $barrados[] = array($q, $faltas); } else { $aptos[] = $q; }
}

echo "Alvo: {$alvo} tentativas ineditas por quiz\n\n";
if ($barrados) {
    echo "BARRADOS — o banco nao aguenta {$alvo} rodadas sem repetir:\n";
    foreach ($barrados as $b) {
        printf("  quiz %-3d %s\n", $b[0]['id'], mb_substr($b[0]['titulo'], 0, 46));
        foreach ($b[1] as $f) { echo "      {$f}\n"; }
    }
    echo "\n";
}
echo 'APTOS: ' . count($aptos) . " quiz(zes)\n";
$mudam = 0;
$up = $pdo->prepare('UPDATE conteudo_quizzes SET tentativas_maximas = ?, updated_at = ? WHERE id = ?');
$agora = date('Y-m-d H:i:s');
$pdo->beginTransaction();
foreach ($aptos as $q) {
    if ((int) $q['t'] === $alvo) { continue; }
    printf("  quiz %-3d %d -> %d  %s\n", $q['id'], $q['t'], $alvo, mb_substr($q['titulo'], 0, 46));
    if ($aplicar) { $up->execute(array($alvo, $agora, $q['id'])); }
    $mudam++;
}
if ($aplicar) { $pdo->commit(); echo "\nAPLICADO — {$mudam} quiz(zes) alterado(s)\n"; }
else { $pdo->rollBack(); echo "\nDRY-RUN — {$mudam} quiz(zes) seriam alterados\n"; }
if ($barrados) { exit(2); }
