#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Confere o banco de questões de um quiz depois de uma importação em massa.
 *
 * Verifica o que o sorteio e a correção exigem: alternativa correta única,
 * dificuldade válida, questão ligada a um bloco do mesmo tipo, e se o banco
 * comporta a composição configurada (inclusive por faixa de dificuldade).
 *
 * Uso:
 *   php scripts/verificar_banco_questoes.php --quiz=24
 *   php scripts/verificar_banco_questoes.php --item=1537
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

$options = getopt('', array('quiz::', 'item::', 'help'));

if (array_key_exists('help', $options)) {
    echo "Uso: php scripts/verificar_banco_questoes.php --quiz=24\n";
    echo "     php scripts/verificar_banco_questoes.php --item=1537\n";
    exit(0);
}

$pdo = \App\Core\Database::connection();

$quizId = isset($options['quiz']) ? (int) $options['quiz'] : 0;
if ($quizId <= 0 && isset($options['item'])) {
    $stmt = $pdo->prepare('SELECT id FROM conteudo_quizzes WHERE item_id = :i AND deleted_at IS NULL LIMIT 1');
    $stmt->execute(array('i' => (int) $options['item']));
    $quizId = (int) $stmt->fetchColumn();
}

if ($quizId <= 0) {
    fwrite(STDERR, "Informe --quiz=<id> ou --item=<id>.\n");
    exit(1);
}

$quizModel = new \App\Models\ConteudoQuiz();
$quiz      = $quizModel->findById($quizId);
if (!$quiz) {
    fwrite(STDERR, "Quiz #{$quizId} nao encontrado.\n");
    exit(1);
}

echo "Banco de questoes do quiz #{$quizId}\n";
echo str_repeat('-', 60) . PHP_EOL;

$problemas = 0;
$avisos    = 0;

function linha($simbolo, $texto) {
    echo "  {$simbolo} {$texto}" . PHP_EOL;
}

// ------------------------------------------------------------------
// 1. Inventario por bloco e dificuldade
// ------------------------------------------------------------------
$stmt = $pdo->prepare(
    'SELECT b.codigo, b.titulo, b.tipo_questao, b.quantidade_sortear, b.status,
            p.dificuldade, COUNT(p.id) AS total
     FROM conteudo_quiz_blocos b
     LEFT JOIN conteudo_quiz_perguntas p
            ON p.bloco_id = b.id AND p.status = \'ativo\' AND p.deleted_at IS NULL
     WHERE b.quiz_id = :q AND b.deleted_at IS NULL
     GROUP BY b.id, p.dificuldade
     ORDER BY b.ordem, p.dificuldade'
);
$stmt->execute(array('q' => $quizId));

$porBloco = array();
foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $linha) {
    $codigo = (string) $linha['codigo'];
    if (!isset($porBloco[$codigo])) {
        $porBloco[$codigo] = array(
            'titulo'     => $linha['titulo'],
            'tipo'       => $linha['tipo_questao'],
            'sorteia'    => (int) $linha['quantidade_sortear'],
            'status'     => $linha['status'],
            'total'      => 0,
            'facil'      => 0,
            'media'      => 0,
            'dificil'    => 0,
        );
    }
    if ($linha['dificuldade'] === null) {
        continue;
    }
    $total = (int) $linha['total'];
    $porBloco[$codigo]['total'] += $total;
    $dificuldade = (string) $linha['dificuldade'];
    if (isset($porBloco[$codigo][$dificuldade])) {
        $porBloco[$codigo][$dificuldade] += $total;
    }
}

echo PHP_EOL . "Inventario por bloco" . PHP_EOL;
foreach ($porBloco as $codigo => $dados) {
    printf("  %-12s sorteia %-3d | banco %-4d (F %-3d M %-3d D %-3d) %s\n",
        $codigo, $dados['sorteia'], $dados['total'],
        $dados['facil'], $dados['media'], $dados['dificil'],
        $dados['status'] === 'ativo' ? '' : '[BLOCO INATIVO]');
}

// ------------------------------------------------------------------
// 2. Integridade das questoes objetivas
// ------------------------------------------------------------------
echo PHP_EOL . "Integridade das questoes" . PHP_EOL;

$stmt = $pdo->prepare(
    'SELECT p.id, LEFT(p.enunciado, 70) AS enunciado,
            COALESCE(SUM(a.correta), 0) AS corretas, COUNT(a.id) AS alternativas
     FROM conteudo_quiz_perguntas p
     LEFT JOIN conteudo_quiz_alternativas a ON a.pergunta_id = p.id AND a.deleted_at IS NULL
     WHERE p.quiz_id = :q AND p.tipo = \'multipla_escolha\' AND p.deleted_at IS NULL
     GROUP BY p.id
     HAVING corretas <> 1 OR alternativas < 2'
);
$stmt->execute(array('q' => $quizId));
$quebradas = $stmt->fetchAll(\PDO::FETCH_ASSOC);

if (count($quebradas) === 0) {
    linha('OK ', 'toda questao objetiva tem exatamente 1 correta e 2+ alternativas');
} else {
    $problemas += count($quebradas);
    linha('!! ', count($quebradas) . ' questao(oes) com alternativas invalidas:');
    foreach (array_slice($quebradas, 0, 15) as $q) {
        echo "       #{$q['id']} corretas={$q['corretas']} alternativas={$q['alternativas']} :: {$q['enunciado']}\n";
    }
    if (count($quebradas) > 15) {
        echo '       ... e mais ' . (count($quebradas) - 15) . PHP_EOL;
    }
}

// HTML no texto exibido ao aluno. As views do quiz imprimem enunciado,
// alternativa, explicacao e rubrica com Helpers::e(), ou seja, escapados:
// qualquer tag apareceria literal na tela.
$stmt = $pdo->prepare(
    'SELECT p.id, LEFT(p.enunciado, 60) AS enunciado
     FROM conteudo_quiz_perguntas p
     WHERE p.quiz_id = :q AND p.deleted_at IS NULL
       AND (p.enunciado REGEXP \'<[a-z/][a-z0-9]*[[:space:]>/]\'
            OR p.explicacao REGEXP \'<[a-z/][a-z0-9]*[[:space:]>/]\'
            OR p.rubrica REGEXP \'<[a-z/][a-z0-9]*[[:space:]>/]\')'
);
$stmt->execute(array('q' => $quizId));
$comHtml = $stmt->fetchAll(\PDO::FETCH_ASSOC);
if (count($comHtml) === 0) {
    linha('OK ', 'nenhum texto de questao contem HTML (seria exibido literal)');
} else {
    $problemas += count($comHtml);
    linha('!! ', count($comHtml) . ' questao(oes) com HTML no texto, que apareceria literal para o aluno:');
    foreach (array_slice($comHtml, 0, 10) as $q) {
        echo "       #{$q['id']} :: {$q['enunciado']}\n";
    }
}

// Tabela de dados sem aviso de que os dados sao ficticios. E aviso, nao erro:
// tabela que lista tarefas ou atividades pedagogicas nao carrega estatistica e
// nao precisa da marcacao. Em Geografia, Historia e afins um numero sem essa
// marca e lido pelo aluno como dado real.
$stmt = $pdo->prepare(
    'SELECT p.id, LEFT(p.enunciado, 60) AS enunciado
     FROM conteudo_quiz_perguntas p
     WHERE p.quiz_id = :q AND p.deleted_at IS NULL
       AND p.enunciado LIKE \'% | %\'
       AND p.enunciado REGEXP \'[0-9]{1,3}[.][0-9]{3}|[0-9]+ ?(%|por cento)\'
       AND p.enunciado NOT REGEXP \'hipot|fict\''
);
$stmt->execute(array('q' => $quizId));
$semAviso = $stmt->fetchAll(\PDO::FETCH_ASSOC);
if (count($semAviso) === 0) {
    linha('OK ', 'toda tabela com numeros avisa que os dados sao ficticios');
} else {
    $avisos += count($semAviso);
    linha('.. ', count($semAviso) . ' questao(oes) com tabela numerica sem avisar que o dado e ficticio:');
    foreach (array_slice($semAviso, 0, 10) as $q) {
        echo "       #{$q['id']} :: {$q['enunciado']}\n";
    }
}

// Gabarito identificavel pelo COMPRIMENTO. A correta tende a ser mais longa
// porque precisa ser precisa e completa, enquanto o distrator sai como negacao
// curta. Nos cinco primeiros simulados isso chegou a 91% das questoes, com a
// correta excedendo as outras em ate 155 caracteres: dava para acertar quase
// tudo marcando sempre a mais comprida. O embaralhamento nao protege, porque
// embaralha a ordem e o comprimento viaja junto com o texto.
// O corte e a margem PERCEPTIVEL (mais de 15 caracteres), e nao "ser a mais
// longa": diferenca de poucos caracteres entre alternativas longas ninguem ve.
$stmt = $pdo->prepare(
    'SELECT p.id, b.codigo, LEFT(p.enunciado, 55) AS enunciado,
            CHAR_LENGTH(ac.texto) AS correta,
            (SELECT MAX(CHAR_LENGTH(ad.texto)) FROM conteudo_quiz_alternativas ad
             WHERE ad.pergunta_id = p.id AND ad.correta = 0 AND ad.deleted_at IS NULL) AS maior_distrator
     FROM conteudo_quiz_perguntas p
     JOIN conteudo_quiz_blocos b ON b.id = p.bloco_id
     JOIN conteudo_quiz_alternativas ac ON ac.pergunta_id = p.id AND ac.correta = 1 AND ac.deleted_at IS NULL
     WHERE p.quiz_id = :q AND p.tipo = \'multipla_escolha\' AND p.deleted_at IS NULL
     HAVING maior_distrator IS NOT NULL AND correta - maior_distrator > 15'
);
$stmt->execute(array('q' => $quizId));
$longas = $stmt->fetchAll(\PDO::FETCH_ASSOC);

$stmt = $pdo->prepare(
    'SELECT COUNT(*) FROM conteudo_quiz_perguntas
     WHERE quiz_id = :q AND tipo = \'multipla_escolha\' AND deleted_at IS NULL'
);
$stmt->execute(array('q' => $quizId));
$totalObjetivas = (int) $stmt->fetchColumn();

if ($totalObjetivas > 0) {
    $pct = 100 * count($longas) / $totalObjetivas;
    if (count($longas) === 0) {
        linha('OK ', 'o gabarito nao se destaca pelo comprimento');
    } elseif ($pct <= 25) {
        $avisos += count($longas);
        linha('.. ', count($longas) . ' de ' . $totalObjetivas . ' questoes (' . round($pct) . '%) com a correta mais longa por >15 caracteres');
    } else {
        $problemas += count($longas);
        linha('!! ', count($longas) . ' de ' . $totalObjetivas . ' questoes (' . round($pct) . '%) entregam o gabarito pelo comprimento:');
        foreach (array_slice($longas, 0, 8) as $l) {
            echo "       #{$l['id']} [{$l['codigo']}] correta {$l['correta']} vs maior distrator {$l['maior_distrator']} :: {$l['enunciado']}\n";
        }
        echo "       Corrija reescrevendo os DISTRATORES, nunca a correta.\n";
        echo "       Ver docs/2026-08-16-vies-comprimento-alternativas.md\n";
    }
}

// Disciplina carimbada no bloco de Formacao Geral Docente. O FGD e o tronco
// comum: as MESMAS questoes aparecem em todos os simulados da PND. Uma cena de
// "erro em matematica" ali faz o professor de Historia abrir a prova dele e
// encontrar matematica na questao 1.
// A regra e a mesma que vale para as AULAS de Formacao Geral: a teoria e geral
// e deve continuar geral; o exemplo e que nao pode vir carimbado.
// Nao acusa mencao a outra area em si - interdisciplinaridade e BNCC sao
// legitimas. Procura CENA de aula: "aula de X", "professor de X", "em X".
$stmt = $pdo->prepare(
    'SELECT p.id, p.tema, LEFT(p.enunciado, 60) AS enunciado
     FROM conteudo_quiz_perguntas p
     JOIN conteudo_quiz_blocos b ON b.id = p.bloco_id
     WHERE p.quiz_id = :q AND b.codigo = \'FGD\' AND p.deleted_at IS NULL
       AND (p.enunciado REGEXP \'aula de (Matem|Portug|Ci[êe]ncias|Hist[óo]ria|Geografia|Biolog|F[íi]sica|Qu[íi]mica)\'
            OR p.enunciado REGEXP \'professora? de (Matem|Portug|Ci[êe]ncias|Hist[óo]ria|Geografia|Biolog)\'
            OR p.enunciado REGEXP \'em (matem[áa]tica|hist[óo]ria|geografia|l[íi]ngua portuguesa)\')'
);
$stmt->execute(array('q' => $quizId));
$carimbadas = $stmt->fetchAll(\PDO::FETCH_ASSOC);
if (count($carimbadas) === 0) {
    linha('OK ', 'nenhuma questao de Formacao Geral presa a uma disciplina');
} else {
    $problemas += count($carimbadas);
    linha('!! ', count($carimbadas) . ' questao(oes) de Formacao Geral com cena de disciplina especifica:');
    foreach (array_slice($carimbadas, 0, 8) as $c) {
        echo "       #{$c['id']} [{$c['tema']}] :: {$c['enunciado']}\n";
    }
    echo "       O FGD e identico em todos os simulados da PND: neutralize o exemplo.\n";
}

// Dificuldade invalida
$stmt = $pdo->prepare(
    'SELECT id, dificuldade FROM conteudo_quiz_perguntas
     WHERE quiz_id = :q AND deleted_at IS NULL
       AND dificuldade NOT IN (\'facil\', \'media\', \'dificil\')'
);
$stmt->execute(array('q' => $quizId));
$difInvalida = $stmt->fetchAll(\PDO::FETCH_ASSOC);
if (count($difInvalida) === 0) {
    linha('OK ', 'todas as dificuldades sao validas');
} else {
    $problemas += count($difInvalida);
    linha('!! ', count($difInvalida) . ' questao(oes) com dificuldade invalida');
}

// Sem bloco ou tipo divergente
$stmt = $pdo->prepare(
    'SELECT p.id, p.tipo, b.codigo, b.tipo_questao
     FROM conteudo_quiz_perguntas p
     LEFT JOIN conteudo_quiz_blocos b ON b.id = p.bloco_id AND b.deleted_at IS NULL
     WHERE p.quiz_id = :q AND p.deleted_at IS NULL
       AND (p.bloco_id IS NULL OR b.id IS NULL OR p.tipo <> b.tipo_questao)'
);
$stmt->execute(array('q' => $quizId));
$semBloco = $stmt->fetchAll(\PDO::FETCH_ASSOC);
if (count($semBloco) === 0) {
    linha('OK ', 'toda questao esta ligada a um bloco do mesmo tipo');
} else {
    $problemas += count($semBloco);
    linha('!! ', count($semBloco) . ' questao(oes) sem bloco ou com tipo divergente:');
    foreach (array_slice($semBloco, 0, 15) as $q) {
        echo "       #{$q['id']} tipo={$q['tipo']} bloco=" . ($q['codigo'] ?? 'NENHUM')
            . ' tipo_bloco=' . ($q['tipo_questao'] ?? '-') . PHP_EOL;
    }
}

// Enunciados duplicados (aviso)
$stmt = $pdo->prepare(
    'SELECT COUNT(*) FROM (
        SELECT enunciado FROM conteudo_quiz_perguntas
        WHERE quiz_id = :q AND deleted_at IS NULL
        GROUP BY enunciado HAVING COUNT(*) > 1
     ) AS d'
);
$stmt->execute(array('q' => $quizId));
$duplicados = (int) $stmt->fetchColumn();
if ($duplicados > 0) {
    $avisos++;
    linha('~~ ', "{$duplicados} enunciado(s) repetido(s) no banco - confira se nao houve importacao duplicada");
}

// ------------------------------------------------------------------
// 3. Composicao configurada
// ------------------------------------------------------------------
echo PHP_EOL . "Composicao do simulado" . PHP_EOL;

$quizService = new \App\Services\ConteudoQuizService();
$composicao  = $quizService->validarComposicao($quizId);

if (!empty($composicao['ok']) && count($composicao['erros']) === 0) {
    linha('OK ', 'o banco comporta a composicao configurada');
} else {
    $problemas += count($composicao['erros']);
    foreach ($composicao['erros'] as $erro) {
        linha('!! ', $erro);
    }
}
foreach ($composicao['alertas'] as $alerta) {
    $avisos++;
    linha('~~ ', $alerta);
}

// ------------------------------------------------------------------
// 4. Simulacao de sorteio
// ------------------------------------------------------------------
echo PHP_EOL . "Simulacao de uma tentativa" . PHP_EOL;

$blocoModel    = new \App\Models\ConteudoQuizBloco();
$perguntaModel = new \App\Models\ConteudoQuizPergunta();
$sorteio       = new \App\Services\Quiz\QuizSorteioService();

$config = array();
$pools  = array();
foreach ($blocoModel->listForQuiz($quizId, true) as $bloco) {
    $config[] = array(
        'id'                       => (int) $bloco['id'],
        'codigo'                   => (string) $bloco['codigo'],
        'titulo'                   => (string) $bloco['titulo'],
        'tipo_questao'             => (string) $bloco['tipo_questao'],
        'quantidade_sortear'       => (int) $bloco['quantidade_sortear'],
        'distribuicao_dificuldade' => $bloco['distribuicao_dificuldade_json'],
        'conta_para_percentual'    => (int) $bloco['conta_para_percentual'],
        'obrigatorio_para_envio'   => (int) $bloco['obrigatorio_para_envio'],
        'ordem'                    => (int) $bloco['ordem'],
    );
    $pools[(int) $bloco['id']] = $perguntaModel->listDisponiveisParaSorteio(
        $quizId, (int) $bloco['id'], (string) $bloco['tipo_questao']
    );
}

$resultado = $sorteio->sortear($config, $pools);
$objetivas = 0;
$discursivas = 0;
foreach ($resultado['perguntas'] as $item) {
    if ((string) $item['pergunta']['tipo'] === 'discursiva') {
        $discursivas++;
    } else {
        $objetivas++;
    }
}

printf("  questoes sorteadas: %d (objetivas %d + discursivas %d)\n",
    count($resultado['perguntas']), $objetivas, $discursivas);
foreach ($resultado['blocos'] as $bloco) {
    printf("    %-12s %d/%d", $bloco['codigo'], $bloco['quantidade_sorteada'], $bloco['quantidade_solicitada']);
    if (!empty($bloco['distribuicao_efetiva'])) {
        printf("  (F %d M %d D %d)",
            $bloco['distribuicao_efetiva']['facil'],
            $bloco['distribuicao_efetiva']['media'],
            $bloco['distribuicao_efetiva']['dificil']);
    }
    echo PHP_EOL;
}

if (empty($resultado['completo'])) {
    $problemas++;
    linha('!! ', 'o sorteio nao conseguiu completar a composicao com o banco atual');
}
foreach ($resultado['auditoria'] as $ocorrencia) {
    $avisos++;
    linha('~~ ', 'sorteio: ' . json_encode($ocorrencia, JSON_UNESCAPED_UNICODE));
}

// ------------------------------------------------------------------
echo PHP_EOL . str_repeat('-', 60) . PHP_EOL;
if ($problemas === 0) {
    echo "Banco pronto para uso. Avisos: {$avisos}\n";
} else {
    echo "Problemas encontrados: {$problemas} | Avisos: {$avisos}\n";
}

exit($problemas > 0 ? 1 : 0);
