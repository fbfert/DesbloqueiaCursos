<?php
/**
 * Importa um lote de questões objetivas do curso OAB 1ª Fase (125) a partir de
 * um arquivo JSON, validando antes de gravar.
 *
 * As regras aqui são as que a coleção PND aprendeu na marra (ver
 * docs/2026-08-16-vies-comprimento-alternativas.md e
 * docs/2026-08-13-quiz-simulado-banco-questoes.md), adaptadas para a OAB:
 * quatro alternativas em vez de cinco.
 *
 * Formato do arquivo:
 * {
 *   "quiz_id": 31,
 *   "bloco": "ETICA",
 *   "questoes": [
 *     {
 *       "enunciado": "...",
 *       "dificuldade": "facil|media|dificil",
 *       "tema": "...",
 *       "explicacao": "comentário exibido ao aluno depois do envio",
 *       "referencia": "opcional",
 *       "alternativas": [
 *         {"texto": "...", "correta": false},
 *         {"texto": "...", "correta": true},
 *         {"texto": "...", "correta": false},
 *         {"texto": "...", "correta": false}
 *       ]
 *     }
 *   ]
 * }
 *
 * Uso:
 *   php scripts/importar_questoes_oab.php --arquivo=lote.json --dry-run
 *   php scripts/importar_questoes_oab.php --arquivo=lote.json
 */

declare(strict_types=1);

$raiz = realpath(__DIR__ . '/..');
if ($raiz === false) {
    fwrite(STDERR, "Nao foi possivel localizar a raiz do projeto.\n");
    exit(1);
}
define('BASE_PATH', $raiz);
define('PUBLIC_PATH', $raiz);
require BASE_PATH . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register(BASE_PATH);
\App\Core\Env::load(BASE_PATH . '/.env');

const CURSO_OAB = 125;
const ALTERNATIVAS_OAB = 4;          // A, B, C e D — a OAB nao tem cinco
const MARGEM_PERCEPTIVEL = 15;       // caracteres, mesmo corte do verificador
const TETO_VIES_COMPRIMENTO = 0.25;  // no maximo 1 em 4, que e o acaso com 4 opcoes
const TETO_POSICAO_PURA = 0.60;      // 'correta e a mais curta' sem exigir margem perceptivel

$opts = getopt('', ['arquivo:', 'dry-run']);
$caminho = $opts['arquivo'] ?? '';
if ($caminho === '' || !is_file($caminho)) {
    fwrite(STDERR, "Uso: php scripts/importar_questoes_oab.php --arquivo=<lote.json> [--dry-run]\n");
    exit(1);
}

$dados = json_decode((string) file_get_contents($caminho), true);
if (!is_array($dados)) {
    fwrite(STDERR, "JSON invalido: " . json_last_error_msg() . "\n");
    exit(1);
}

$pdo = \App\Core\Database::connection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$quizId = (int) ($dados['quiz_id'] ?? 0);
$codigoBloco = (string) ($dados['bloco'] ?? '');
$questoes = $dados['questoes'] ?? null;

$erros = [];

// --- quiz e bloco -----------------------------------------------------------
$bloco = null;
if ($quizId <= 0 || $codigoBloco === '') {
    $erros[] = 'quiz_id e bloco sao obrigatorios';
} else {
    $st = $pdo->prepare(
        'SELECT b.id, b.codigo, b.tipo_questao, b.quantidade_sortear
         FROM conteudo_quiz_blocos b
         JOIN conteudo_quizzes q ON q.id = b.quiz_id
         JOIN conteudo_itens i ON i.id = q.item_id
         WHERE b.quiz_id = ? AND b.codigo = ? AND b.deleted_at IS NULL
           AND i.curso_evento_id = ?'
    );
    $st->execute([$quizId, $codigoBloco, CURSO_OAB]);
    $bloco = $st->fetch(PDO::FETCH_ASSOC);
    if (!$bloco) {
        $erros[] = "bloco '{$codigoBloco}' nao existe no quiz {$quizId} do curso " . CURSO_OAB;
    } elseif ($bloco['tipo_questao'] !== 'multipla_escolha') {
        $erros[] = "o bloco '{$codigoBloco}' nao e de multipla escolha";
    }
}

if (!is_array($questoes) || !$questoes) {
    $erros[] = 'a lista de questoes esta vazia';
}

// --- enunciados ja existentes no CURSO INTEIRO -------------------------------
// Nao basta conferir dentro do quiz. O curso tem dois bancos por disciplina:
// o do quiz de treino e o do simulado, deliberadamente estanques — se uma
// questao do treino reaparecer no simulado, as tres tentativas do simulado
// deixam de ser ineditas e o simulado passa a medir memoria do treino.
// Por isso a checagem varre todos os quizzes do curso, e nao so o de destino.
$jaExistem = [];
$st = $pdo->prepare(
    'SELECT p.enunciado, p.quiz_id
     FROM conteudo_quiz_perguntas p
     JOIN conteudo_quizzes q ON q.id = p.quiz_id
     JOIN conteudo_itens i ON i.id = q.item_id
     WHERE i.curso_evento_id = ? AND p.deleted_at IS NULL'
);
$st->execute([CURSO_OAB]);
foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $linha) {
    $jaExistem[chave((string) $linha['enunciado'])] = (int) $linha['quiz_id'];
}

function chave(string $texto): string
{
    $t = preg_replace('~\s+~u', ' ', mb_strtolower(trim($texto)));
    return md5((string) $t);
}

function temHtml(string $t): bool
{
    return (bool) preg_match('~<[a-z/!][^>]*>~i', $t);
}

// --- validação questão a questão --------------------------------------------
$dificuldadesValidas = ['facil', 'media', 'dificil'];
$vistos = [];
$viesados = 0;
$viesadosCurto = 0;
$maisCurta = 0;
$porDificuldade = ['facil' => 0, 'media' => 0, 'dificil' => 0];

if (is_array($questoes)) {
    foreach ($questoes as $i => $q) {
        $n = $i + 1;
        $enunciado = trim((string) ($q['enunciado'] ?? ''));
        $dificuldade = (string) ($q['dificuldade'] ?? '');
        $tema = trim((string) ($q['tema'] ?? ''));
        $explicacao = trim((string) ($q['explicacao'] ?? ''));
        $alts = $q['alternativas'] ?? null;

        if (mb_strlen($enunciado) < 80) {
            $erros[] = "questao {$n}: enunciado curto demais (" . mb_strlen($enunciado) . ' caracteres, minimo 80)';
        }
        if (temHtml($enunciado)) {
            $erros[] = "questao {$n}: o enunciado contem HTML, que seria exibido literal ao aluno";
        }
        if (!in_array($dificuldade, $dificuldadesValidas, true)) {
            $erros[] = "questao {$n}: dificuldade '{$dificuldade}' invalida (use facil, media ou dificil)";
        } else {
            $porDificuldade[$dificuldade]++;
        }
        if ($tema === '') {
            $erros[] = "questao {$n}: tema e obrigatorio (alimenta o desempenho por tema do aluno)";
        }
        if (mb_strlen($explicacao) < 80) {
            $erros[] = "questao {$n}: explicacao curta demais; ela e exibida ao aluno depois do envio e precisa ensinar";
        }
        if (temHtml($explicacao)) {
            $erros[] = "questao {$n}: a explicacao contem HTML";
        }

        $k = chave($enunciado);
        if (isset($vistos[$k])) {
            $erros[] = "questao {$n}: enunciado duplicado dentro do proprio lote";
        }
        if (isset($jaExistem[$k])) {
            $onde = $jaExistem[$k] === $quizId
                ? "neste mesmo quiz ({$quizId})"
                : "no quiz {$jaExistem[$k]} do curso — os bancos de treino e de simulado precisam ser estanques";
            $erros[] = "questao {$n}: enunciado ja existe {$onde}";
        }
        $vistos[$k] = true;

        if (!is_array($alts) || count($alts) !== ALTERNATIVAS_OAB) {
            $erros[] = "questao {$n}: sao exigidas exatamente " . ALTERNATIVAS_OAB
                . ' alternativas (A a D), e vieram ' . (is_array($alts) ? count($alts) : 0);
            continue;
        }

        $corretas = 0;
        $tamCorreta = 0;
        $maiorDistrator = 0;
        $menorDistrator = PHP_INT_MAX;
        foreach ($alts as $j => $a) {
            $texto = trim((string) ($a['texto'] ?? ''));
            $correta = !empty($a['correta']);
            if (mb_strlen($texto) < 10) {
                $erros[] = "questao {$n}, alternativa " . chr(65 + $j) . ': texto curto demais';
            }
            if (temHtml($texto)) {
                $erros[] = "questao {$n}, alternativa " . chr(65 + $j) . ': contem HTML';
            }
            if ($correta) {
                $corretas++;
                $tamCorreta = mb_strlen($texto);
            } else {
                $maiorDistrator = max($maiorDistrator, mb_strlen($texto));
                $menorDistrator = min($menorDistrator, mb_strlen($texto));
            }
        }
        if ($corretas !== 1) {
            $erros[] = "questao {$n}: sao exigidas exatamente 1 alternativa correta, e vieram {$corretas}";
        }
        if ($corretas === 1 && $tamCorreta - $maiorDistrator > MARGEM_PERCEPTIVEL) {
            $viesados++;
        }
        if ($corretas === 1 && $menorDistrator - $tamCorreta > MARGEM_PERCEPTIVEL) {
            $viesadosCurto++;
        }
        if ($corretas === 1 && $tamCorreta <= $menorDistrator) {
            $maisCurta++;
        }
    }

    // Vies de comprimento no lote, nas DUAS direcoes. A regra nao impoe posicao:
    // a correta PODE ser a mais longa ou a mais curta, so nao pode ser sempre.
    // O acaso com quatro opcoes e 1 em 4.
    //
    // O curso 123 da colecao PND aprendeu isso do jeito caro: a regra de la
    // exigia "pelo menos um distrator igual ou mais longo que a correta", os
    // agentes cumpriram deixando TODOS mais longos, e a correta virou
    // sistematicamente a mais curta. Trocar um vies por outro nao resolve nada.
    // Ver docs/2026-08-16-vies-comprimento-alternativas.md.
    $total = count($questoes);
    if ($total > 0 && $viesados > 1 && ($viesados / $total) > TETO_VIES_COMPRIMENTO) {
        $pct = round(100 * $viesados / $total);
        $erros[] = "vies de comprimento (correta LONGA demais): em {$viesados} de {$total} questoes ({$pct}%) a "
            . 'correta excede o maior distrator por mais de ' . MARGEM_PERCEPTIVEL . ' caracteres. O teto e '
            . round(100 * TETO_VIES_COMPRIMENTO) . '%. Corrija alongando DISTRATORES, nunca encurtando a correta.';
    }
    if ($total > 0 && $viesadosCurto > 1 && ($viesadosCurto / $total) > TETO_VIES_COMPRIMENTO) {
        $pct = round(100 * $viesadosCurto / $total);
        $erros[] = "vies de comprimento (correta CURTA demais): em {$viesadosCurto} de {$total} questoes ({$pct}%) a "
            . 'correta e mais curta que TODOS os distratores por mais de ' . MARGEM_PERCEPTIVEL . ' caracteres. '
            . 'O teto e ' . round(100 * TETO_VIES_COMPRIMENTO) . '%. Corrija encurtando algum DISTRATOR, '
            . 'nunca alongando a correta.';
    }
    // Posicao pura, sem margem: mesmo diferencas pequenas, se sistematicas,
    // treinam o aluno numa heuristica que a prova real nao recompensa.
    if ($total >= 4 && ($maisCurta / $total) > TETO_POSICAO_PURA) {
        $pct = round(100 * $maisCurta / $total);
        $erros[] = "a correta e a mais curta das quatro em {$maisCurta} de {$total} questoes ({$pct}%), e o acaso "
            . 'e 25%. Em pelo menos uma questao do lote, escreva um distrator MAIS CURTO que a correta.';
    }
}

if ($erros) {
    fwrite(STDERR, "REPROVADO — {$caminho}\n");
    foreach ($erros as $e) {
        fwrite(STDERR, "  - {$e}\n");
    }
    exit(1);
}

$resumo = sprintf(
    '%d questoes (%d faceis, %d medias, %d dificeis); correta perceptivelmente mais longa em %d, mais curta em %d, e a mais curta das quatro em %d',
    count($questoes), $porDificuldade['facil'], $porDificuldade['media'], $porDificuldade['dificil'], $viesados, $viesadosCurto, $maisCurta
);

if (isset($opts['dry-run'])) {
    echo "OK (dry-run) {$codigoBloco}: {$resumo}\n";
    exit(0);
}

// --- gravação ---------------------------------------------------------------
$agora = date('Y-m-d H:i:s');
$pdo->beginTransaction();
try {
    $ordem = (int) $pdo->query(
        'SELECT COALESCE(MAX(ordem), 0) FROM conteudo_quiz_perguntas WHERE quiz_id = ' . $quizId
    )->fetchColumn();

    $insQ = $pdo->prepare(
        'INSERT INTO conteudo_quiz_perguntas
            (quiz_id, bloco_id, enunciado, tipo, dificuldade, tema, status, explicacao,
             referencia, peso, obrigatoria, ordem, created_at, updated_at)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
    );
    $insA = $pdo->prepare(
        'INSERT INTO conteudo_quiz_alternativas (pergunta_id, texto, correta, ordem, created_at, updated_at)
         VALUES (?,?,?,?,?,?)'
    );

    foreach ($questoes as $q) {
        $ordem++;
        $insQ->execute([
            $quizId,
            (int) $bloco['id'],
            trim((string) $q['enunciado']),
            'multipla_escolha',
            (string) $q['dificuldade'],
            trim((string) $q['tema']),
            'ativo',
            trim((string) $q['explicacao']),
            isset($q['referencia']) ? trim((string) $q['referencia']) : null,
            1.00,
            1,
            $ordem,
            $agora,
            $agora,
        ]);
        $perguntaId = (int) $pdo->lastInsertId();
        $o = 0;
        foreach ($q['alternativas'] as $a) {
            $o++;
            $insA->execute([$perguntaId, trim((string) $a['texto']), !empty($a['correta']) ? 1 : 0, $o, $agora, $agora]);
        }
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, 'Falhou, nada foi gravado: ' . $e->getMessage() . "\n");
    exit(1);
}

echo "OK {$codigoBloco}: {$resumo}\n";
