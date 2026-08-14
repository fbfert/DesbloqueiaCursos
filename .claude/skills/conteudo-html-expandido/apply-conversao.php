<?php
/**
 * Script generico de aplicacao da conversao texto -> html expandido.
 *
 * Nao rode direto sem adaptar: este arquivo e um TEMPLATE de referencia.
 * Copie para o scratchpad da sessao, ajuste o caminho do JSON de entrada
 * e o CURSO_ID, e so entao execute. Ver SKILL.md para o processo completo
 * (como gerar o JSON de entrada via Workflow ou via agentes individuais).
 *
 * Uso:
 *   DRY_RUN=1 php apply-conversao.php <curso_id> <caminho-json-resultados>
 *   php apply-conversao.php <curso_id> <caminho-json-resultados>
 *
 * O JSON de entrada deve ter o formato:
 *   {"resultados": [{"item_id": 123, "icone": "target", "corpo_html": "..."}]}
 * (ou, se voce salvou o output cru de um Workflow, {"result": {"resultados": [...]}} -
 * o script tenta os dois formatos automaticamente)
 */

define('BASE_PATH', '/home/desbloqueiacursos/public_html');
require BASE_PATH . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register(BASE_PATH);
\App\Core\Env::load(BASE_PATH . '/.env');
require __DIR__ . '/icons.php';
$CSS = file_get_contents(__DIR__ . '/design-system.css');

$ICONES_PERMITIDOS = array_keys($ICONES);

$cursoId = isset($argv[1]) ? (int) $argv[1] : 0;
$jsonPath = isset($argv[2]) ? $argv[2] : '';
if ($cursoId <= 0 || $jsonPath === '' || !is_file($jsonPath)) {
    fwrite(STDERR, "Uso: php apply-conversao.php <curso_id> <caminho-json-resultados>\n");
    exit(1);
}

$raw = file_get_contents($jsonPath);
$data = json_decode($raw, true);
if ($data === null) {
    fwrite(STDERR, "ERRO: JSON invalido: " . json_last_error_msg() . "\n");
    exit(1);
}
$resultados = $data['resultados'] ?? ($data['result']['resultados'] ?? null);
if (!is_array($resultados)) {
    fwrite(STDERR, "ERRO: nao encontrei 'resultados' nem 'result.resultados' no JSON\n");
    exit(1);
}
echo "Total de resultados recebidos: " . count($resultados) . "\n";

$db = App\Core\Database::connection();

$stmt = $db->prepare("SELECT id, titulo FROM conteudo_itens WHERE curso_evento_id = :id AND tipo = 'texto' AND deleted_at IS NULL ORDER BY id");
$stmt->execute(['id' => $cursoId]);
$itensDb = $stmt->fetchAll(PDO::FETCH_ASSOC);
$titulosPorId = array();
foreach ($itensDb as $row) {
    $titulosPorId[(int) $row['id']] = trim((string) $row['titulo']);
}
echo "Total de itens tipo=texto no banco (curso {$cursoId}): " . count($titulosPorId) . "\n";

$erros = array();
$avisos = array();
$paginas = array();
$idsRecebidos = array();

foreach ($resultados as $i => $r) {
    if (!is_array($r) || !isset($r['item_id'])) {
        $erros[] = "resultado #$i sem item_id";
        continue;
    }
    $itemId = (int) $r['item_id'];
    $idsRecebidos[$itemId] = true;

    if (!isset($titulosPorId[$itemId])) {
        $erros[] = "item {$itemId}: nao existe em conteudo_itens (tipo=texto, curso {$cursoId})";
        continue;
    }

    $icone = isset($r['icone']) ? (string) $r['icone'] : '';
    if (!in_array($icone, $ICONES_PERMITIDOS, true)) {
        $avisos[] = "item {$itemId}: icone '{$icone}' fora da lista permitida, usando 'sparkles'";
        $icone = 'sparkles';
    }

    $corpo = isset($r['corpo_html']) ? (string) $r['corpo_html'] : '';
    if (trim($corpo) === '') {
        $erros[] = "item {$itemId}: corpo_html vazio";
        continue;
    }

    if (strpos($corpo, 'card-resumo') === false) {
        $avisos[] = "item {$itemId}: sem card-resumo (recomendado ao final de toda licao)";
    }

    $corpoComIcones = preg_replace_callback('/\{\{ICON:([a-z]+)\}\}/', function ($m) use ($ICONES_PERMITIDOS) {
        return '<span class="h-icone">' . icone(in_array($m[1], $ICONES_PERMITIDOS, true) ? $m[1] : 'sparkles') . '</span>';
    }, $corpo);
    // Caso o agente ja tenha envolvido o placeholder em <span class="h-icone">,
    // evita duplicar o wrapper (regex acima cobre so o {{ICON:x}} cru).
    $corpoComIcones = preg_replace('/<span class="h-icone"><span class="h-icone">/', '<span class="h-icone">', $corpoComIcones);
    $corpoComIcones = preg_replace('/<\/span><\/span>/', '</span>', $corpoComIcones);

    if (strpos($corpoComIcones, '{{ICON:') !== false) {
        $erros[] = "item {$itemId}: sobrou placeholder de icone malformado/incompleto (provavel truncamento) - regenerar este item isoladamente";
        continue;
    }

    $titulo = $titulosPorId[$itemId];

    $paginaHtml = '<!doctype html>' . "\n"
        . '<html lang="pt-BR">' . "\n"
        . '<head>' . "\n"
        . '<meta charset="UTF-8">' . "\n"
        . '<meta name="viewport" content="width=device-width, initial-scale=1.0">' . "\n"
        . '<title>' . htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') . '</title>' . "\n"
        . '<style>' . $CSS . '</style>' . "\n"
        . '</head>' . "\n"
        . '<body>' . "\n"
        . '<div class="licao">' . "\n"
        . '<div class="licao-cabecalho"><div class="licao-icone">' . icone($icone) . '</div><h1 class="licao-titulo">' . htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') . '</h1></div>' . "\n"
        . $corpoComIcones . "\n"
        . '</div>' . "\n"
        . '</body>' . "\n"
        . '</html>';

    // Validacao DOM (ignora falsos positivos de tag svg/rect/path/circle/line
    // no parser HTML4 legado do libxml - nao sao erros reais de markup)
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML($paginaHtml);
    $domErros = libxml_get_errors();
    libxml_clear_errors();
    $domErrosReais = array_filter($domErros, function ($e) {
        return $e->level >= 2 && !preg_match('/Tag (svg|rect|path|circle|line) invalid/', trim($e->message));
    });
    if (!empty($domErrosReais)) {
        foreach ($domErrosReais as $e) {
            $erros[] = "item {$itemId}: erro de HTML - " . trim($e->message);
        }
        continue;
    }

    // O on[a-z]+= precisa estar DENTRO de uma tag para ser atributo de evento.
    // Sem o "<[^>]*\s" antes, texto legitimo em portugues era barrado por engano
    // ("Atividade concreta = aprendizagem" casa com "oncreta =").
    if (preg_match('/<script|<iframe|<[^>]*\son[a-z]+\s*=/i', $corpoComIcones)) {
        $erros[] = "item {$itemId}: corpo contem tag/atributo potencialmente perigoso (script/iframe/on*)";
        continue;
    }

    $paginas[$itemId] = $paginaHtml;
}

foreach ($titulosPorId as $itemId => $titulo) {
    if (!isset($idsRecebidos[$itemId])) {
        $erros[] = "item {$itemId} ({$titulo}): NENHUM resultado recebido";
    }
}

echo "\n=== VALIDACAO ===\n";
echo "Paginas validas prontas para gravar: " . count($paginas) . "\n";
echo "Avisos: " . count($avisos) . "\n";
foreach ($avisos as $a) { echo "  AVISO: $a\n"; }
echo "Erros: " . count($erros) . "\n";
foreach ($erros as $e) { echo "  ERRO: $e\n"; }

if (!empty($erros)) {
    fwrite(STDERR, "\nAbortando: existem erros de validacao acima. Nada foi gravado no banco.\n");
    fwrite(STDERR, "Corrija os itens com erro (normalmente regenerando so aquele item isolado) e rode de novo.\n");
    exit(1);
}

if (count($paginas) !== count($titulosPorId)) {
    fwrite(STDERR, "\nAbortando: esperado " . count($titulosPorId) . " paginas validas, obtido " . count($paginas) . "\n");
    exit(1);
}

echo "\nTodas as " . count($paginas) . " paginas passaram na validacao.\n";

if (getenv('DRY_RUN')) {
    echo "DRY RUN: nada sera gravado no banco.\n";
    foreach ($paginas as $itemId => $html) {
        echo "item {$itemId}: " . strlen($html) . " bytes\n";
    }
    exit(0);
}

$htmlModel = new App\Models\ConteudoHtml();
$db->beginTransaction();
try {
    foreach ($paginas as $itemId => $html) {
        $htmlModel->upsertByItemId($itemId, $html);
        $upd = $db->prepare("UPDATE conteudo_itens SET tipo = 'html' WHERE id = :id");
        $upd->execute(['id' => $itemId]);
        $del = $db->prepare("DELETE FROM conteudo_textos WHERE item_id = :id");
        $del->execute(['id' => $itemId]);
    }
    $db->commit();
    echo "\nGravado com sucesso: " . count($paginas) . " itens convertidos para tipo=html no curso {$cursoId}.\n";
} catch (\Throwable $e) {
    $db->rollBack();
    fwrite(STDERR, "ERRO NA GRAVACAO (rollback aplicado): " . $e->getMessage() . "\n");
    exit(1);
}
