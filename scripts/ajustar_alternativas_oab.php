<?php
/**
 * Corrige textos de alternativas e enunciados já gravados do curso OAB (125).
 *
 * A trava central: por padrão o script RECUSA gravar qualquer alternativa
 * marcada como correta. Foi assim que a coleção PND corrigiu 842 questões sem
 * risco de mudar um gabarito por acidente — os agentes trabalhavam só com os
 * distratores e o script recusaria escrever se algum id fosse de correta.
 * Ver docs/2026-08-16-vies-comprimento-alternativas.md.
 *
 * O método de correção do viés de comprimento é sempre o mesmo: mexer nos
 * DISTRATORES. Nunca alongar nem encurtar a alternativa correta para ajustar
 * estatística — isso é maquiar a medição, não corrigir o item.
 *
 * A exceção existe e é explícita: --permitir-correta libera a gravação, mas
 * exige que cada entrada traga o campo "motivo_correta", que fica registrado
 * na saída. Serve para ajuste de redação de uma correta específica, revisado
 * um a um, nunca para lote.
 *
 * Formato do arquivo:
 * {
 *   "motivo": "por que este lote de ajustes existe",
 *   "alternativas": [ {"id": 9739, "texto": "...", "motivo_correta": "..."} ],
 *   "perguntas":    [ {"id": 1860, "enunciado": "...", "explicacao": "..."} ]
 * }
 *
 * Uso:
 *   php scripts/ajustar_alternativas_oab.php --arquivo=ajustes.json --dry-run
 *   php scripts/ajustar_alternativas_oab.php --arquivo=ajustes.json
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

$opts = getopt('', ['arquivo:', 'dry-run', 'permitir-correta']);
$caminho = $opts['arquivo'] ?? '';
if ($caminho === '' || !is_file($caminho)) {
    fwrite(STDERR, "Uso: php scripts/ajustar_alternativas_oab.php --arquivo=<ajustes.json> [--dry-run] [--permitir-correta]\n");
    exit(1);
}
$dados = json_decode((string) file_get_contents($caminho), true);
if (!is_array($dados)) {
    fwrite(STDERR, 'JSON invalido: ' . json_last_error_msg() . "\n");
    exit(1);
}

$pdo = \App\Core\Database::connection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$alternativas = $dados['alternativas'] ?? [];
$perguntas = $dados['perguntas'] ?? [];
$erros = [];
$avisosCorreta = [];
$afetadas = [];

// --- alternativas -----------------------------------------------------------
foreach ($alternativas as $i => $a) {
    $id = (int) ($a['id'] ?? 0);
    $texto = trim((string) ($a['texto'] ?? ''));
    if ($id <= 0 || $texto === '') {
        $erros[] = "alternativa #{$i}: id e texto sao obrigatorios";
        continue;
    }
    $st = $pdo->prepare(
        'SELECT alt.id, alt.correta, alt.pergunta_id, alt.texto AS atual
         FROM conteudo_quiz_alternativas alt
         JOIN conteudo_quiz_perguntas p ON p.id = alt.pergunta_id
         JOIN conteudo_quizzes q ON q.id = p.quiz_id
         JOIN conteudo_itens it ON it.id = q.item_id
         WHERE alt.id = ? AND alt.deleted_at IS NULL AND it.curso_evento_id = ?'
    );
    $st->execute([$id, CURSO_OAB]);
    $linha = $st->fetch(PDO::FETCH_ASSOC);
    if (!$linha) {
        $erros[] = "alternativa {$id}: nao existe no curso " . CURSO_OAB;
        continue;
    }
    if ((int) $linha['correta'] === 1) {
        if (!isset($opts['permitir-correta'])) {
            $erros[] = "alternativa {$id} e a CORRETA da pergunta {$linha['pergunta_id']} — recusado. "
                . 'Corrija o viés de comprimento mexendo nos distratores. Se o ajuste for mesmo de '
                . 'redação da correta, rode com --permitir-correta e informe "motivo_correta".';
            continue;
        }
        $motivo = trim((string) ($a['motivo_correta'] ?? ''));
        if ($motivo === '') {
            $erros[] = "alternativa {$id} e a CORRETA e nao trouxe \"motivo_correta\"";
            continue;
        }
        $avisosCorreta[] = "  ! alternativa {$id} (correta da pergunta {$linha['pergunta_id']}): {$motivo}";
    }
    if (preg_match('~<[a-z/!][^>]*>~i', $texto)) {
        $erros[] = "alternativa {$id}: o texto contem HTML";
    }
    if (mb_strlen($texto) < 10) {
        $erros[] = "alternativa {$id}: texto curto demais";
    }
    $afetadas[(int) $linha['pergunta_id']] = true;
}

// --- perguntas --------------------------------------------------------------
foreach ($perguntas as $i => $p) {
    $id = (int) ($p['id'] ?? 0);
    if ($id <= 0) {
        $erros[] = "pergunta #{$i}: id obrigatorio";
        continue;
    }
    $st = $pdo->prepare(
        'SELECT p.id FROM conteudo_quiz_perguntas p
         JOIN conteudo_quizzes q ON q.id = p.quiz_id
         JOIN conteudo_itens it ON it.id = q.item_id
         WHERE p.id = ? AND p.deleted_at IS NULL AND it.curso_evento_id = ?'
    );
    $st->execute([$id, CURSO_OAB]);
    if (!$st->fetch(PDO::FETCH_ASSOC)) {
        $erros[] = "pergunta {$id}: nao existe no curso " . CURSO_OAB;
        continue;
    }
    foreach (['enunciado', 'explicacao'] as $campo) {
        if (isset($p[$campo]) && preg_match('~<[a-z/!][^>]*>~i', (string) $p[$campo])) {
            $erros[] = "pergunta {$id}: {$campo} contem HTML";
        }
    }
    $afetadas[$id] = true;
}

if ($erros) {
    fwrite(STDERR, "REPROVADO\n");
    foreach ($erros as $e) {
        fwrite(STDERR, "  - {$e}\n");
    }
    exit(1);
}

if ($avisosCorreta) {
    echo "ALTERACOES EM ALTERNATIVA CORRETA (revisadas uma a uma):\n";
    foreach ($avisosCorreta as $l) {
        echo $l . "\n";
    }
    echo "\n";
}

$medir = static function (PDO $pdo, array $ids): array {
    if (!$ids) {
        return [];
    }
    $in = implode(',', array_map('intval', $ids));
    $sql = "SELECT p.id, CHAR_LENGTH(ac.texto) corr,
              (SELECT MAX(CHAR_LENGTH(a.texto)) FROM conteudo_quiz_alternativas a
               WHERE a.pergunta_id = p.id AND a.correta = 0 AND a.deleted_at IS NULL) maior,
              (SELECT MIN(CHAR_LENGTH(a.texto)) FROM conteudo_quiz_alternativas a
               WHERE a.pergunta_id = p.id AND a.correta = 0 AND a.deleted_at IS NULL) menor
            FROM conteudo_quiz_perguntas p
            JOIN conteudo_quiz_alternativas ac ON ac.pergunta_id = p.id AND ac.correta = 1 AND ac.deleted_at IS NULL
            WHERE p.id IN ({$in})";
    $r = [];
    foreach ($pdo->query($sql) as $l) {
        $r[(int) $l['id']] = $l;
    }
    return $r;
};

$ids = array_keys($afetadas);
$antes = $medir($pdo, $ids);

if (isset($opts['dry-run'])) {
    printf(
        "OK (dry-run): %d alternativa(s) e %d pergunta(s), em %d questao(oes).\n",
        count($alternativas),
        count($perguntas),
        count($ids)
    );
    exit(0);
}

$agora = date('Y-m-d H:i:s');
$pdo->beginTransaction();
try {
    $uA = $pdo->prepare('UPDATE conteudo_quiz_alternativas SET texto = ?, updated_at = ? WHERE id = ?');
    foreach ($alternativas as $a) {
        $uA->execute([trim((string) $a['texto']), $agora, (int) $a['id']]);
    }
    foreach ($perguntas as $p) {
        $campos = [];
        $vals = [];
        foreach (['enunciado', 'explicacao'] as $campo) {
            if (isset($p[$campo])) {
                $campos[] = "{$campo} = ?";
                $vals[] = trim((string) $p[$campo]);
            }
        }
        if (!$campos) {
            continue;
        }
        $vals[] = $agora;
        $vals[] = (int) $p['id'];
        $pdo->prepare('UPDATE conteudo_quiz_perguntas SET ' . implode(', ', $campos) . ', updated_at = ? WHERE id = ?')
            ->execute($vals);
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, 'Falhou, nada foi gravado: ' . $e->getMessage() . "\n");
    exit(1);
}

$depois = $medir($pdo, $ids);
echo "Comprimento da correta em relacao aos distratores (antes -> depois)\n";
$piorou = 0;
foreach ($ids as $id) {
    if (!isset($antes[$id], $depois[$id])) {
        continue;
    }
    $a = $antes[$id];
    $d = $depois[$id];
    $posA = $a['corr'] <= $a['menor'] ? 'a mais CURTA' : ($a['corr'] >= $a['maior'] ? 'a mais LONGA' : 'no meio');
    $posD = $d['corr'] <= $d['menor'] ? 'a mais CURTA' : ($d['corr'] >= $d['maior'] ? 'a mais LONGA' : 'no meio');
    if ($posD !== 'no meio') {
        $piorou++;
    }
    printf("  #%d  %-13s -> %-13s (correta %d, distratores %d a %d)\n", $id, $posA, $posD, $d['corr'], $d['menor'], $d['maior']);
}
printf("\n%d de %d questoes com a correta em posicao extrema depois do ajuste.\n", $piorou, count($ids));
