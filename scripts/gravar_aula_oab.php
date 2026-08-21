<?php
/**
 * Grava uma aula tipo=html do curso OAB 1ª Fase (125).
 *
 * O autor da aula escreve APENAS o corpo — de <div class="licao"> ao
 * </script> final. Este script monta a página completa, colando o sistema de
 * design compartilhado a partir de resources/templates/oab/. Isso garante
 * que as 100 aulas tenham CSS byte a byte idêntico e que ninguém precise
 * reescrever 14 KB de estilo por aula.
 *
 * Valida antes de gravar e recusa o que estiver fora do padrão.
 *
 * Uso:
 *   php scripts/gravar_aula_oab.php --item=1924 --corpo=/caminho/corpo.html
 *   php scripts/gravar_aula_oab.php --item=1924 --corpo=... --dry-run
 *   php scripts/gravar_aula_oab.php --listar-pendentes
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
const TEMPLATES = BASE_PATH . '/resources/templates/oab';
const CORPO_MINIMO = 30000; // bytes; as aulas da coleção ficam entre 45 e 70 KB

$opts = getopt('', ['item:', 'corpo:', 'dry-run', 'listar-pendentes']);
$pdo = \App\Core\Database::connection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if (isset($opts['listar-pendentes'])) {
    $sql = "SELECT i.id, i.titulo, i.ordem, m.ordem AS modulo_ordem, m.titulo AS modulo,
                   LENGTH(h.conteudo) AS bytes
            FROM conteudo_itens i
            JOIN conteudo_modulos m ON m.id = i.modulo_id
            LEFT JOIN conteudo_htmls h ON h.item_id = i.id
            WHERE i.curso_evento_id = " . CURSO_OAB . " AND i.tipo = 'html' AND i.deleted_at IS NULL
            ORDER BY m.ordem, i.ordem";
    $linhas = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    $pendentes = array_values(array_filter($linhas, static fn ($l) => (int) $l['bytes'] < CORPO_MINIMO));
    echo json_encode($pendentes, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
    fwrite(STDERR, sprintf("%d de %d aulas pendentes\n", count($pendentes), count($linhas)));
    exit(0);
}

$itemId = isset($opts['item']) ? (int) $opts['item'] : 0;
$caminhoCorpo = $opts['corpo'] ?? '';
if ($itemId <= 0 || $caminhoCorpo === '') {
    fwrite(STDERR, "Uso: php scripts/gravar_aula_oab.php --item=<id> --corpo=<arquivo> [--dry-run]\n");
    exit(1);
}
if (!is_file($caminhoCorpo)) {
    fwrite(STDERR, "Arquivo do corpo nao encontrado: {$caminhoCorpo}\n");
    exit(1);
}

$item = $pdo->prepare('SELECT id, titulo, tipo, curso_evento_id FROM conteudo_itens WHERE id = ? AND deleted_at IS NULL');
$item->execute([$itemId]);
$item = $item->fetch(PDO::FETCH_ASSOC);
if (!$item) {
    fwrite(STDERR, "Item {$itemId} nao existe.\n");
    exit(1);
}
if ((int) $item['curso_evento_id'] !== CURSO_OAB || $item['tipo'] !== 'html') {
    fwrite(STDERR, "Item {$itemId} nao e uma aula html do curso " . CURSO_OAB . ".\n");
    exit(1);
}

$corpo = trim(file_get_contents($caminhoCorpo));
$erros = [];

// --- validações do corpo -----------------------------------------------------
if (strlen($corpo) < CORPO_MINIMO) {
    $erros[] = sprintf('corpo curto demais: %d bytes, minimo %d', strlen($corpo), CORPO_MINIMO);
}
if (strpos($corpo, '<div class="licao">') !== 0) {
    $erros[] = 'o corpo deve comecar exatamente com <div class="licao">';
}
if (strpos($corpo, 'licao-cabecalho') === false || strpos($corpo, 'licao-titulo') === false) {
    $erros[] = 'falta o cabecalho da licao (licao-cabecalho / licao-titulo)';
}
if (strpos($corpo, 'card-resumo') === false) {
    $erros[] = 'falta o card-resumo, obrigatorio ao final de toda licao';
}
if (stripos($corpo, '<style') !== false || stripos($corpo, '</sty' . 'le') !== false) {
    $erros[] = 'o corpo nao pode conter tag de estilo: o CSS vem do template compartilhado';
}
if (stripos($corpo, '<iframe') !== false || stripos($corpo, '<object') !== false || stripos($corpo, '<embed') !== false) {
    $erros[] = 'o corpo nao pode conter iframe, object nem embed';
}
if (preg_match('~<script[^>]*\ssrc\s*=~i', $corpo)) {
    $erros[] = 'script externo nao e permitido: a pagina precisa ser autocontida';
}
if (preg_match('~(?:src|href)\s*=\s*["\']https?://~i', $corpo)) {
    $erros[] = 'recurso externo (http/https) nao e permitido: a pagina precisa ser autocontida';
}
if (!preg_match('~<h1 class="licao-titulo">~', $corpo)) {
    $erros[] = 'falta o <h1 class="licao-titulo"> no cabecalho';
}

// --- montagem ----------------------------------------------------------------
$css = @file_get_contents(TEMPLATES . '/design-system.css');
$ext = @file_get_contents(TEMPLATES . '/oab-extensao.css');
if ($css === false || $ext === false) {
    fwrite(STDERR, 'Templates de CSS nao encontrados em ' . TEMPLATES . "\n");
    exit(1);
}

$titulo = htmlspecialchars($item['titulo'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
$fecha = '</sty' . 'le>';
$pagina = "<!doctype html>\n<html lang=\"pt-BR\">\n<head>\n<meta charset=\"UTF-8\">\n"
    . "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n"
    . "<title>{$titulo}</title>\n<style>" . $css . $ext . $fecha . "\n</head>\n<body>\n"
    . $corpo . "\n</body>\n</html>\n";

if (substr_count($pagina, $fecha) !== 1) {
    $erros[] = 'a pagina montada ficou com numero errado de fechamentos de estilo';
}

// --- validação estrutural do HTML montado ------------------------------------
libxml_use_internal_errors(true);
libxml_clear_errors();
$dom = new DOMDocument();
$dom->loadHTML('<?xml encoding="UTF-8">' . $pagina);
foreach (libxml_get_errors() as $e) {
    $msg = trim($e->message);
    // "Tag X invalid" e ruido do parser HTML4 do libxml para tags HTML5.
    if (strpos($msg, 'Tag ') === 0 && strpos($msg, 'invalid') !== false) {
        continue;
    }
    $erros[] = "html malformado (linha {$e->line}): {$msg}";
}
libxml_clear_errors();

if ($erros) {
    fwrite(STDERR, "REPROVADO item {$itemId} — {$item['titulo']}\n");
    foreach ($erros as $e) {
        fwrite(STDERR, "  - {$e}\n");
    }
    exit(1);
}

if (isset($opts['dry-run'])) {
    printf("OK (dry-run) item %d — corpo %d bytes, pagina %d bytes\n", $itemId, strlen($corpo), strlen($pagina));
    exit(0);
}

$agora = date('Y-m-d H:i:s');
$pdo->beginTransaction();
try {
    $existe = $pdo->prepare('SELECT id FROM conteudo_htmls WHERE item_id = ?');
    $existe->execute([$itemId]);
    if ($existe->fetch(PDO::FETCH_ASSOC)) {
        $pdo->prepare('UPDATE conteudo_htmls SET conteudo = ?, updated_at = ? WHERE item_id = ?')
            ->execute([$pagina, $agora, $itemId]);
    } else {
        $pdo->prepare('INSERT INTO conteudo_htmls (item_id, conteudo, created_at, updated_at) VALUES (?,?,?,?)')
            ->execute([$itemId, $pagina, $agora, $agora]);
    }
    $pdo->prepare('UPDATE conteudo_itens SET status = ?, updated_at = ? WHERE id = ?')
        ->execute(['publicado', $agora, $itemId]);
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, 'Falhou, nada foi gravado: ' . $e->getMessage() . "\n");
    exit(1);
}

printf("OK item %d — %s (%d bytes)\n", $itemId, $item['titulo'], strlen($pagina));
