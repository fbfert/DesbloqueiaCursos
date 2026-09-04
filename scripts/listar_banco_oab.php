<?php
/**
 * Lista, de forma compacta, TUDO o que o curso 125 ja pergunta numa
 * disciplina — treino e simulado —, com o tema e a proposicao juridica de
 * cada questao.
 *
 * Serve para quem vai escrever questoes novas: os bancos precisam ser
 * estanques entre si, e "estanque" aqui nao e so texto diferente — e ponto
 * juridico diferente. O importador barra enunciado repetido; so a leitura
 * desta lista evita repetir a tese com outro nome proprio.
 *
 *   php scripts/listar_banco_oab.php --bloco=PENAL
 */

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/Core/Env.php';
App\Core\Env::load(BASE_PATH . '/.env');
require BASE_PATH . '/app/Core/Database.php';

$opts = getopt('', array('bloco:'));
if (empty($opts['bloco'])) {
    fwrite(STDERR, "uso: php scripts/listar_banco_oab.php --bloco=CODIGO\n");
    exit(1);
}
$codigo = strtoupper(trim($opts['bloco']));

$pdo = App\Core\Database::connection();

// quiz 31 a 50 sao os de TREINO; o 51 e o simulado. Os dois entram na lista:
// quem escreve questao nova nao pode repetir nem um nem outro.
$st = $pdo->prepare(
    'SELECT b.id, b.quiz_id FROM conteudo_quiz_blocos b
      WHERE b.codigo = ? AND b.quiz_id BETWEEN 31 AND 51 AND b.deleted_at IS NULL
      ORDER BY b.quiz_id'
);
$st->execute(array($codigo));
$blocos = $st->fetchAll(PDO::FETCH_ASSOC);

if (!$blocos) {
    fwrite(STDERR, "bloco '{$codigo}' nao encontrado\n");
    exit(1);
}

$total = 0;
$secoes = array();
foreach ($blocos as $b) {
    $st = $pdo->prepare(
        'SELECT p.id, p.tema, p.dificuldade,
                (SELECT a.texto FROM conteudo_quiz_alternativas a
                  WHERE a.pergunta_id = p.id AND a.correta = 1 AND a.deleted_at IS NULL LIMIT 1) AS correta
           FROM conteudo_quiz_perguntas p
          WHERE p.bloco_id = ? AND p.deleted_at IS NULL
          ORDER BY p.tema, p.id'
    );
    $st->execute(array($b['id']));
    $linhas = $st->fetchAll(PDO::FETCH_ASSOC);
    $total += count($linhas);
    $rotulo = ((int) $b['quiz_id'] === 51) ? 'SIMULADO' : 'TREINO';
    $secoes[] = array('rotulo' => $rotulo, 'linhas' => $linhas);
}

echo "O QUE O CURSO JA PERGUNTA EM {$codigo} — {$total} questoes\n";
echo "Nao repita nenhuma destas proposicoes, em NENHUM dos dois bancos.\n";
echo "Fato diferente com a mesma tese CONTA como repeticao.\n";

foreach ($secoes as $s) {
    echo "\n============ {$s['rotulo']} (" . count($s['linhas']) . " questoes) ============\n";
    $temaAtual = null;
    foreach ($s['linhas'] as $l) {
        if ($l['tema'] !== $temaAtual) {
            $temaAtual = $l['tema'];
            echo "\n## {$temaAtual}\n";
        }
        $p = preg_replace('/\s+/u', ' ', trim((string) $l['correta']));
        echo '  - ' . mb_substr($p, 0, 150) . "\n";
    }
}
