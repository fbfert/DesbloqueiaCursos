<?php

/**
 * Script de migração dos quizzes textuais do curso 102 (Direito do Consumidor na Prática)
 * para o sistema nativo de quizzes.
 *
 * Uso: php sql/062_quiz_migracao_legado_102.php [--dry-run] [--force]
 *   --dry-run : simula sem alterar o banco
 *   --force   : reaplica mesmo itens já marcados como migrados
 *
 * IDEMPOTENTE: pode ser executado múltiplas vezes com segurança.
 * Itens já migrados são identificados pelo hash e ignorados.
 */

$dryRun = in_array('--dry-run', $argv ?? array(), true);
$force  = in_array('--force', $argv ?? array(), true);

// Bootstrap mínimo para acesso ao banco
define('BASE_PATH', dirname(__DIR__));

// Carregar .env manualmente
$envFile = BASE_PATH . '/.env';
if (!file_exists($envFile)) {
    fwrite(STDERR, "ERRO: .env não encontrado em " . BASE_PATH . "\n");
    exit(1);
}

$env = parse_ini_file($envFile, false, INI_SCANNER_RAW);
if (!$env) {
    fwrite(STDERR, "ERRO: Falha ao carregar .env\n");
    exit(1);
}

$dbHost = $env['DB_HOST'] ?? 'localhost';
$dbPort = $env['DB_PORT'] ?? '3306';
$dbName = $env['DB_DATABASE'] ?? '';
$dbUser = $env['DB_USERNAME'] ?? '';
$dbPass = $env['DB_PASSWORD'] ?? '';
$dbCharset = $env['DB_CHARSET'] ?? 'utf8mb4';

try {
    $pdo = new PDO(
        "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset={$dbCharset}",
        $dbUser, $dbPass,
        array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC)
    );
} catch (PDOException $e) {
    fwrite(STDERR, "ERRO de conexão: " . $e->getMessage() . "\n");
    exit(1);
}

// Verificar se tabelas existem
foreach (array('conteudo_quizzes', 'conteudo_quiz_perguntas', 'conteudo_quiz_alternativas', 'conteudo_quiz_migracoes_legacy') as $tabela) {
    $existe = $pdo->query("SHOW TABLES LIKE '{$tabela}'")->rowCount() > 0;
    if (!$existe) {
        fwrite(STDERR, "ERRO: Tabela {$tabela} não existe. Execute sql/061_quiz.sql primeiro.\n");
        exit(1);
    }
}

echo "=== Migração de Quizzes Legados — Curso 102 ===\n";
echo "Modo: " . ($dryRun ? "DRY-RUN (sem alterações)" : "EXECUÇÃO REAL") . "\n";
echo str_repeat("-", 50) . "\n";

// Itens a migrar
$itens = array(
    1223 => array('modulo_id' => 267, 'titulo' => 'Quiz do módulo 1'),
    1231 => array('modulo_id' => 268, 'titulo' => 'Quiz do módulo 2'),
    1241 => array('modulo_id' => 269, 'titulo' => 'Quiz do módulo 3'),
    1252 => array('modulo_id' => 270, 'titulo' => 'Quiz do módulo 4'),
    1264 => array('modulo_id' => 271, 'titulo' => 'Quiz do módulo 5'),
    1274 => array('modulo_id' => 272, 'titulo' => 'Quiz do módulo 6'),
    1287 => array('modulo_id' => 273, 'titulo' => 'Quiz do módulo 7'),
    1298 => array('modulo_id' => 274, 'titulo' => 'Quiz final'),
);

$configMigracao = array(
    'tentativas_maximas'            => null,
    'exige_aprovacao'               => 0,
    'percentual_minimo'             => 0.00,
    'exibir_resultado_apos_envio'   => 1,
    'exibir_gabarito_apos_envio'    => 1,
    'exibir_comentarios_apos_envio' => 1,
    'embaralhar_perguntas'          => 0,
    'embaralhar_alternativas'       => 0,
);

$totalMigrados = 0;
$totalErros    = 0;
$totalIgnorados= 0;
$totalDivergentes = 0;
$relatório     = array();

foreach ($itens as $itemId => $meta) {
    $linha = "Item {$itemId} ({$meta['titulo']}): ";

    // Buscar item
    $item = $pdo->prepare('SELECT * FROM conteudo_itens WHERE id = :id AND deleted_at IS NULL LIMIT 1');
    $item->execute(array('id' => $itemId));
    $item = $item->fetch();

    if (!$item) {
        echo $linha . "IGNORADO (item não encontrado no banco)\n";
        $totalIgnorados++;
        registrarMigracao($pdo, $itemId, null, null, '', hash('sha256', "not_found_{$itemId}"), 'ignorado', "Item não encontrado", null, $dryRun);
        continue;
    }

    // Buscar conteúdo textual
    $textoRow = $pdo->prepare('SELECT * FROM conteudo_textos WHERE item_id = :id LIMIT 1');
    $textoRow->execute(array('id' => $itemId));
    $textoRow = $textoRow->fetch();

    $html = $textoRow ? (string) ($textoRow['conteudo'] ?? '') : '';
    $hash = hash('sha256', $html);

    // Verificar se já foi migrado
    $migRowStmt = $pdo->prepare('SELECT * FROM conteudo_quiz_migracoes_legacy WHERE item_id = :item_id LIMIT 1');
    $migRowStmt->execute(array('item_id' => $itemId));
    $migRow = $migRowStmt->fetch();

    if ($migRow && $migRow['status'] === 'migrado' && !$force) {
        if (!empty($migRow['hash_origem']) && !hash_equals((string) $migRow['hash_origem'], $hash)) {
            echo $linha . "DIVERGENTE (HTML de origem alterado após migração)\n";
            $totalDivergentes++;
            registrarMigracao($pdo, $itemId, (int) $migRow['quiz_id'], $textoRow ? $textoRow['id'] : null, $html, $hash, 'divergente', 'HTML de origem alterado após migração', null, $dryRun);
            continue;
        }
        echo $linha . "IGNORADO (já migrado em {$migRow['migrado_em']})\n";
        $totalIgnorados++;
        continue;
    }

    if ($migRow && $migRow['status'] === 'divergente' && !$force) {
        if (!empty($migRow['hash_origem']) && hash_equals((string) $migRow['hash_origem'], $hash)) {
            echo $linha . "IGNORADO (convergência com hash já registrado)\n";
            $totalIgnorados++;
            continue;
        }
    }

    // Tentar extrair perguntas do HTML
    try {
        $perguntas = extrairPerguntasDoHtml($html);
    } catch (Exception $e) {
        echo $linha . "ERRO ao extrair perguntas: " . $e->getMessage() . "\n";
        registrarMigracao($pdo, $itemId, null, $textoRow ? $textoRow['id'] : null, $html, $hash, 'erro', $e->getMessage(), null, $dryRun);
        $totalErros++;
        continue;
    }

    if (empty($perguntas)) {
        echo $linha . "ERRO: nenhuma pergunta extraída do HTML\n";
        registrarMigracao($pdo, $itemId, null, $textoRow ? $textoRow['id'] : null, $html, $hash, 'erro', 'Nenhuma pergunta extraída', null, $dryRun);
        $totalErros++;
        continue;
    }

    $qtdEsperada = ($itemId === 1298) ? 10 : 5;
    if (count($perguntas) !== $qtdEsperada) {
        $msg = "Extraídas " . count($perguntas) . " perguntas, esperadas {$qtdEsperada}";
        echo $linha . "AVISO: {$msg}\n";
    }

    if ($dryRun) {
        echo $linha . "DRY-RUN: " . count($perguntas) . " pergunta(s) extraída(s)\n";
        foreach ($perguntas as $i => $p) {
            echo "   P" . ($i+1) . ": " . mb_substr($p['enunciado'], 0, 60) . "...\n";
            echo "      Alts: " . count($p['alternativas']) . " | Correta: A" . ($p['correta_idx']+1) . "\n";
        }
        $totalMigrados++;
        continue;
    }

    // Executar migração
    try {
        $pdo->beginTransaction();

        // 1. Alterar tipo do item para 'quiz'
        $pdo->prepare('UPDATE conteudo_itens SET tipo = \'quiz\', updated_at = NOW() WHERE id = :id')
            ->execute(array('id' => $itemId));

        // 2. Criar conteudo_quizzes (ou substituir se já existe)
        $quizExiste = $pdo->prepare('SELECT id FROM conteudo_quizzes WHERE item_id = :item_id AND deleted_at IS NULL LIMIT 1');
        $quizExiste->execute(array('item_id' => $itemId));
        $quizExisteRow = $quizExiste->fetch();

        if ($quizExisteRow) {
            $quizId = (int) $quizExisteRow['id'];
            $pdo->prepare('UPDATE conteudo_quizzes SET
                instrucoes = NULL, tentativas_maximas = NULL, percentual_minimo = 0,
                exige_aprovacao = 0, exibir_resultado_apos_envio = 1,
                exibir_gabarito_apos_envio = 1, exibir_comentarios_apos_envio = 1,
                embaralhar_perguntas = 0, embaralhar_alternativas = 0,
                updated_at = NOW(), deleted_at = NULL
               WHERE id = :id')->execute(array('id' => $quizId));
            // Soft delete perguntas antigas (apenas se não há tentativas)
            $pdo->prepare('UPDATE conteudo_quiz_perguntas SET deleted_at = NOW() WHERE quiz_id = :qid AND deleted_at IS NULL')
                ->execute(array('qid' => $quizId));
        } else {
            $stmt = $pdo->prepare('INSERT INTO conteudo_quizzes
                (item_id, instrucoes, tentativas_maximas, percentual_minimo, exige_aprovacao,
                 exibir_resultado_apos_envio, exibir_gabarito_apos_envio, exibir_comentarios_apos_envio,
                 embaralhar_perguntas, embaralhar_alternativas, created_at, updated_at)
               VALUES (:item_id, NULL, NULL, 0, 0, 1, 1, 1, 0, 0, NOW(), NOW())');
            $stmt->execute(array('item_id' => $itemId));
            $quizId = (int) $pdo->lastInsertId();
        }

        // 3. Inserir perguntas e alternativas
        foreach ($perguntas as $ordem => $p) {
            $stmtP = $pdo->prepare('INSERT INTO conteudo_quiz_perguntas
                (quiz_id, enunciado, tipo, explicacao, peso, obrigatoria, ordem, created_at, updated_at)
               VALUES (:quiz_id, :enunciado, \'multipla_escolha\', :explicacao, 1, 1, :ordem, NOW(), NOW())');
            $stmtP->execute(array(
                'quiz_id'    => $quizId,
                'enunciado'  => $p['enunciado'],
                'explicacao' => $p['comentario'] ?? null,
                'ordem'      => $ordem + 1,
            ));
            $perguntaId = (int) $pdo->lastInsertId();

            foreach ($p['alternativas'] as $altOrdem => $altTexto) {
                $correta = ($altOrdem === $p['correta_idx']) ? 1 : 0;
                $stmtA = $pdo->prepare('INSERT INTO conteudo_quiz_alternativas
                    (pergunta_id, texto, correta, ordem, created_at, updated_at)
                   VALUES (:pergunta_id, :texto, :correta, :ordem, NOW(), NOW())');
                $stmtA->execute(array(
                    'pergunta_id' => $perguntaId,
                    'texto'       => $altTexto,
                    'correta'     => $correta,
                    'ordem'       => $altOrdem + 1,
                ));
            }
        }

        // 4. Registrar migração
        registrarMigracao($pdo, $itemId, $quizId, $textoRow ? $textoRow['id'] : null, $html, $hash, 'migrado', null, date('Y-m-d H:i:s'), false);

        $pdo->commit();

        echo $linha . "OK — " . count($perguntas) . " pergunta(s) migrada(s) (quiz_id={$quizId})\n";
        $totalMigrados++;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo $linha . "ERRO: " . $e->getMessage() . "\n";
        registrarMigracao($pdo, $itemId, null, $textoRow ? $textoRow['id'] : null, $html, $hash, 'erro', $e->getMessage(), null, $dryRun);
        $totalErros++;
    }
}

echo str_repeat("-", 50) . "\n";
echo "Resultado: {$totalMigrados} migrado(s) | {$totalErros} erro(s) | {$totalIgnorados} ignorado(s) | {$totalDivergentes} divergente(s)\n";
if ($dryRun) {
    echo "(Dry-run: nenhuma alteração foi feita)\n";
}

// --- Validação pós-migração ---
if (!$dryRun && $totalErros === 0) {
    echo "\n=== Validação pós-migração ===\n";
    $quizzesMigrados = $pdo->query(
        'SELECT cq.id, ci.titulo, COUNT(DISTINCT cqp.id) AS total_perguntas
         FROM conteudo_quizzes cq
         INNER JOIN conteudo_itens ci ON ci.id = cq.item_id
         LEFT JOIN conteudo_quiz_perguntas cqp ON cqp.quiz_id = cq.id AND cqp.deleted_at IS NULL
         WHERE cq.item_id IN (' . implode(',', array_keys($itens)) . ')
           AND cq.deleted_at IS NULL
         GROUP BY cq.id, ci.titulo'
    )->fetchAll();

    $totalPerguntas = 0;
    foreach ($quizzesMigrados as $q) {
        echo "Quiz: {$q['titulo']} — {$q['total_perguntas']} pergunta(s)\n";
        $totalPerguntas += (int) $q['total_perguntas'];
    }
    echo "Total: " . count($quizzesMigrados) . " quizzes | {$totalPerguntas} perguntas\n";
    if (count($quizzesMigrados) === 8 && $totalPerguntas === 45) {
        echo "✓ Validação OK: 8 quizzes, 45 perguntas\n";
    } else {
        echo "✗ Validação FALHOU: esperado 8 quizzes e 45 perguntas\n";
    }
}

exit(($totalErros > 0) ? 1 : 0);

// ------------------------------------------------------------------
// Funções auxiliares
// ------------------------------------------------------------------

function extrairPerguntasDoHtml(string $html): array
{
    if (trim($html) === '') {
        throw new Exception('HTML vazio');
    }

    $perguntas = array();

    // Carrega o HTML com DOMDocument
    libxml_use_internal_errors(true);
    $doc = new DOMDocument('1.0', 'UTF-8');
    $doc->loadHTML('<?xml encoding="utf-8"?><html><body>' . $html . '</body></html>');
    libxml_clear_errors();
    $xpath = new DOMXPath($doc);

    // Heurística 1: blocos <strong>1.</strong> ou <b>1.</b> seguidos de alternativas A) B) C) D)
    $resultados = tentativaExtrairComStrong($xpath, $html);
    if (!empty($resultados)) {
        return $resultados;
    }

    // Heurística 2: parágrafos com numeração seguidos de alternativas
    $resultados = tentativaExtrairComParagrafos($xpath, $html);
    if (!empty($resultados)) {
        return $resultados;
    }

    throw new Exception('Não foi possível extrair perguntas do HTML com nenhuma heurística');
}

function tentativaExtrairComStrong(DOMXPath $xpath, string $html): array
{
    $perguntas = array();

    // Encontra elementos com texto que começa com número seguido de ponto
    $nodes = $xpath->query('//p | //div | //li');
    if (!$nodes) {
        return array();
    }

    $buffer = array();
    foreach ($nodes as $node) {
        $texto = trim($node->textContent);
        if (preg_match('/^(\d+)\s*[.)]\s*(.+)/s', $texto, $m)) {
            $buffer[] = array('num' => (int) $m[1], 'texto' => trim($m[2]));
        }
    }

    if (empty($buffer)) {
        return array();
    }

    // Agrupar em blocos: enunciado + alternativas
    $perguntas = parsearBlocos($buffer);
    return $perguntas;
}

function tentativaExtrairComParagrafos(DOMXPath $xpath, string $html): array
{
    // Fallback: trabalhar com texto puro
    $texto = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $linhas = array_filter(array_map('trim', preg_split('/\n|\r\n|\r/', $texto)));
    $linhas = array_values($linhas);

    $blocos = array();
    foreach ($linhas as $linha) {
        if (preg_match('/^(\d+)\s*[.)]\s*(.+)/s', $linha, $m)) {
            $blocos[] = array('num' => (int) $m[1], 'texto' => trim($m[2]));
        }
    }

    if (empty($blocos)) {
        return array();
    }

    return parsearBlocos($blocos);
}

function parsearBlocos(array $blocos): array
{
    // Cada "bloco" é uma linha com número. O padrão é:
    // 1. Enunciado
    // 2. Alternativa A
    // 3. Alternativa B
    // ...
    // Resposta: X (ou Gabarito: X ou "A resposta correta é X")

    $perguntas    = array();
    $i            = 0;
    $totalBlocos  = count($blocos);

    while ($i < $totalBlocos) {
        $bloco = $blocos[$i];
        $num   = $bloco['num'];
        $texto = $bloco['texto'];

        // Detectar se é alternativa (começa com A) B) C) D) a) b)...)
        if (preg_match('/^[a-eA-E]\s*[.)]\s*/u', $texto)) {
            $i++;
            continue; // pular alternativas soltas
        }

        // É um enunciado. Coletar alternativas e resposta nos blocos seguintes
        $enunciado    = $texto;
        $alternativas = array();
        $corretaIdx   = 0;
        $comentario   = null;
        $i++;

        while ($i < $totalBlocos) {
            $proximo = $blocos[$i];
            $t = $proximo['texto'];

            // Alternativa
            if (preg_match('/^([a-eA-E])\s*[.)]\s*(.+)/su', $t, $mAlt)) {
                $alternativas[] = trim($mAlt[2]);
                $i++;
                continue;
            }

            // Resposta / Gabarito
            if (preg_match('/^(?:resposta|gabarito|correta)[:\s]+([a-eA-E])/iu', $t, $mResp)) {
                $letraCorreta = strtoupper($mResp[1]);
                $corretaIdx   = ord($letraCorreta) - ord('A');
                $i++;
                // Pegar comentário se existir
                if ($i < $totalBlocos && !preg_match('/^\d+\s*[.)]/', $blocos[$i]['texto'])
                    && !preg_match('/^[a-eA-E]\s*[.)]/', $blocos[$i]['texto'])) {
                    $comentario = $blocos[$i]['texto'];
                    $i++;
                }
                break;
            }

            // Próxima pergunta (número maior)
            if ((int) $proximo['num'] > $num) {
                break;
            }

            $i++;
        }

        if (count($alternativas) >= 2) {
            if ($corretaIdx >= count($alternativas)) {
                $corretaIdx = 0;
            }
            $perguntas[] = array(
                'enunciado'    => $enunciado,
                'alternativas' => $alternativas,
                'correta_idx'  => $corretaIdx,
                'comentario'   => $comentario,
            );
        }
    }

    return $perguntas;
}

function registrarMigracao(PDO $pdo, int $itemId, ?int $quizId, ?int $textoId, string $html, string $hash, string $status, ?string $erro, ?string $migradoEm, bool $dryRun): void
{
    if ($dryRun) {
        return;
    }

    $existe = $pdo->prepare('SELECT id FROM conteudo_quiz_migracoes_legacy WHERE item_id = :item_id LIMIT 1');
    $existe->execute(array('item_id' => $itemId));
    $existeRow = $existe->fetch();

    if ($existeRow) {
        $pdo->prepare('UPDATE conteudo_quiz_migracoes_legacy SET
            quiz_id = :quiz_id, status = :status, detalhes_erro = :detalhes_erro,
            migrado_em = :migrado_em, updated_at = NOW()
          WHERE id = :id')
          ->execute(array(
              'quiz_id'      => $quizId,
              'status'       => $status,
              'detalhes_erro'=> $erro,
              'migrado_em'   => $migradoEm,
              'id'           => (int) $existeRow['id'],
          ));
    } else {
        $pdo->prepare('INSERT INTO conteudo_quiz_migracoes_legacy
            (item_id, quiz_id, conteudo_texto_id, html_original, hash_origem, status, detalhes_erro, migrado_em, created_at, updated_at)
           VALUES (:item_id, :quiz_id, :conteudo_texto_id, :html_original, :hash_origem, :status, :detalhes_erro, :migrado_em, NOW(), NOW())')
           ->execute(array(
               'item_id'          => $itemId,
               'quiz_id'          => $quizId,
               'conteudo_texto_id'=> $textoId,
               'html_original'    => $html,
               'hash_origem'      => $hash,
               'status'           => $status,
               'detalhes_erro'    => $erro,
               'migrado_em'       => $migradoEm,
           ));
    }
}
