<?php

/**
 * Paridade do site HTML antes x depois das extrações da API do app.
 *
 * Sobe DOIS servidores contra o MESMO banco de teste:
 *   - "antes": o código do commit base (padrão fb7b504, anterior à mudança api-app-v1),
 *     num git worktree temporário;
 *   - "depois": o código atual.
 * Para cada um: reaplica as fixtures, entra como a aluna de teste pela tela de
 * login do site e percorre as páginas e ações que passaram a usar os Services
 * extraídos (área do curso, abrir cada tipo de item, concluir/desmarcar,
 * baixar material, cancelar pedido nas áreas V1 e V2, pagar online). Compara
 * status, Location, corpo normalizado (sem token CSRF) e o efeito no banco
 * (progresso, logs do aluno, status de pedido).
 *
 *   php tests/Api/paridade_html.php [--base=fb7b504]
 *
 * Requer o container desbloqueia-test-db (ver tests/Api/README.md). Usa as
 * portas 8097 (antes) e 8096 (depois).
 */

define('BASE_PATH', dirname(__DIR__, 2));

$opcoes = getopt('', array('base::'));
$commitBase = isset($opcoes['base']) ? (string) $opcoes['base'] : 'fb7b504';
$raiz = BASE_PATH;
$tmp = sys_get_temp_dir() . '/paridade-html-' . getmypid();
@mkdir($tmp, 0775, true);
$worktree = $tmp . '/base';

putenv('DB_HOST=127.0.0.1');
putenv('DB_PORT=33061');
putenv('DB_DATABASE=desbloqueia_app_teste');
putenv('DB_USERNAME=root');
putenv('DB_PASSWORD=teste_root_123');
require_once BASE_PATH . '/tests/Unit/_bootstrap.php';
$pdo = testes_conectar_banco();

function rodar($cmd)
{
    exec($cmd . ' 2>&1', $saida, $codigo);
    return array($codigo, implode("\n", $saida));
}

list($c, $s) = rodar('git -C ' . escapeshellarg($raiz) . ' worktree add --detach ' . escapeshellarg($worktree) . ' ' . escapeshellarg($commitBase));
if ($c !== 0) {
    fwrite(STDERR, "Não foi possível criar o worktree do commit base: {$s}\n");
    exit(1);
}
@mkdir($worktree . '/storage/logs', 0775, true);
$pdfRel = '/storage/private_uploads/private_uploads/fixtures/fixture-caderno-material.pdf';

// Router com o ambiente de teste para cada árvore.
function router_para($arvore, $arquivo)
{
    $conteudo = "<?php\n"
        . "foreach (array('APP_ENV'=>'local','APP_DEBUG'=>'false','APP_URL'=>'http://127.0.0.1','APP_KEY'=>'chave-local-somente-para-testes-da-api-do-app-000000',"
        . "'DB_HOST'=>'127.0.0.1','DB_PORT'=>'33061','DB_DATABASE'=>'desbloqueia_app_teste','DB_USERNAME'=>'root','DB_PASSWORD'=>'teste_root_123',"
        . "'MAIL_ENABLED'=>'false','ABACATEPAY_ENABLED'=>'false','FCM_ENABLED'=>'false','SESSION_SECURE'=>'false') as \$k => \$v) { putenv(\$k . '=' . \$v); }\n"
        . "return require " . var_export($arvore . '/tests/Smoke/router.php', true) . ";\n";
    file_put_contents($arquivo, $conteudo);
}
router_para($worktree, $tmp . '/router-antes.php');
router_para($raiz, $tmp . '/router-depois.php');

function subir($porta, $docroot, $router)
{
    $cmd = escapeshellarg(PHP_BINARY) . ' -S 127.0.0.1:' . $porta . ' -t ' . escapeshellarg($docroot) . ' ' . escapeshellarg($router);
    $proc = proc_open($cmd, array(0 => array('pipe', 'r'), 1 => array('file', sys_get_temp_dir() . '/paridade-' . $porta . '.log', 'w'), 2 => array('file', sys_get_temp_dir() . '/paridade-' . $porta . '.err', 'w')), $pipes);
    for ($i = 0; $i < 50; $i++) {
        $fp = @fsockopen('127.0.0.1', $porta, $e1, $e2, 0.2);
        if ($fp) {
            fclose($fp);
            return $proc;
        }
        usleep(100000);
    }
    return $proc;
}

function http_site($base, $metodo, $caminho, $dados, $cookies)
{
    $ch = curl_init($base . $caminho);
    $opts = array(CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 60,
        CURLOPT_COOKIEJAR => $cookies, CURLOPT_COOKIEFILE => $cookies);
    if ($metodo === 'POST') {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = http_build_query((array) $dados);
    }
    curl_setopt_array($ch, $opts);
    $bruto = (string) curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $tam = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $cab = substr($bruto, 0, $tam);
    return array(
        'status' => $status,
        'location' => preg_match('/^Location:\s*(.+?)\s*$/mi', $cab, $m) ? $m[1] : null,
        'tipo' => preg_match('/^Content-Type:\s*(.+?)\s*$/mi', $cab, $m) ? $m[1] : null,
        'disposicao' => preg_match('/^Content-Disposition:\s*(.+?)\s*$/mi', $cab, $m) ? $m[1] : null,
        'corpo' => substr($bruto, $tam),
    );
}

function token_csrf($html)
{
    return preg_match('/name="_token"\s+value="([^"]+)"/i', (string) $html, $m) ? $m[1] : '';
}

function normalizar($html)
{
    $html = preg_replace('/name="_token"\s+value="[^"]*"/i', 'name="_token" value="X"', (string) $html);
    $html = preg_replace('/<meta name="csrf-token" content="[^"]*"/i', '<meta name="csrf-token" content="X"', $html);
    $html = preg_replace('/"csrfToken"\s*:\s*"[^"]*"/', '"csrfToken":"X"', $html);
    $html = preg_replace('/data-csrf(-token)?="[^"]*"/', 'data-csrf="X"', $html);
    $html = preg_replace('/\?v=[0-9A-Za-z._-]+/', '?v=X', $html);
    $html = preg_replace('/\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}(:\d{2})?/', 'DATAHORA', $html);
    $html = preg_replace('/\d{2}\/\d{2}\/\d{4}( às)? \d{2}:\d{2}/u', 'DATAHORA', $html);
    return $html;
}

function estado_banco(PDO $pdo)
{
    $estado = array();
    $estado['progresso'] = $pdo->query('SELECT item_id, status, percentual, obrigatorio FROM conteudo_progresso_aluno WHERE inscricao_id = 9001 AND deleted_at IS NULL ORDER BY item_id')->fetchAll(PDO::FETCH_ASSOC);
    $estado['logs'] = $pdo->query('SELECT item_id, modulo_id, acao FROM conteudo_logs_aluno WHERE inscricao_id = 9001 ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $estado['pedidos'] = $pdo->query('SELECT id, status FROM pedidos WHERE id BETWEEN 9001 AND 9010 ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $estado['historico'] = $pdo->query('SELECT pedido_id, status_anterior, status_novo, observacao FROM status_pedidos_historico WHERE pedido_id BETWEEN 9001 AND 9010 ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $estado['inscricao'] = $pdo->query('SELECT percentual_progresso, apto_certificado FROM inscricoes WHERE id = 9001')->fetch(PDO::FETCH_ASSOC);
    return $estado;
}

/** Roteiro: o mesmo para os dois servidores. */
function percorrer($base, PDO $pdo, $raizArvore)
{
    global $pdfRel;
    passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(BASE_PATH . '/tests/Api/montar_banco.php') . ' --so-fixtures > ' . (stripos(PHP_OS, 'WIN') === 0 ? 'NUL' : '/dev/null'));
    @mkdir(dirname($raizArvore . $pdfRel), 0775, true);
    copy(BASE_PATH . $pdfRel, $raizArvore . $pdfRel);

    $cookies = tempnam(sys_get_temp_dir(), 'par_');
    $r = array();
    $login = http_site($base, 'GET', '/login', null, $cookies);
    $r['login_post'] = http_site($base, 'POST', '/login', array('_token' => token_csrf($login['corpo']), 'login' => 'aluno.caderno@teste.local', 'senha' => 'Local@12345'), $cookies);

    $ctx = '/aluno/curso/9001/9001/9001';
    $paginas = array(
        'curso' => $ctx,
        'curso_divergente' => '/aluno/curso/9001/9002/9001',
        'turma_divergente' => '/aluno/curso/9001/9001/9002',
        'modulo1' => $ctx . '/modulo/9001',
        'modulo_inexistente' => $ctx . '/modulo/123',
    );
    foreach (array(9001, 9002, 9003, 9004, 9005, 9006, 9007, 9008, 9009, 9010, 9019) as $item) {
        $paginas['item_' . $item] = $ctx . '/modulo/9001/conteudo/' . $item;
    }
    $paginas['item_modulo_errado'] = $ctx . '/modulo/9002/conteudo/9002';
    $paginas['quiz_9011'] = $ctx . '/modulo/9002/conteudo/9011';
    foreach (array(9021, 9023, 9024, 9025, 9026) as $item) {
        $paginas['avaliacao_' . $item] = $ctx . '/modulo/9003/conteudo/' . $item;
    }
    $paginas['legado_query'] = '/aluno/cursos/conteudo/item?inscricao_id=9001&curso_id=9001&turma_id=9001&modulo_id=9001&conteudo_id=9004';
    $paginas['download'] = '/aluno/cursos/conteudo/arquivo/download?id=9008&inscricao_id=9001&curso_id=9001&turma_id=9001';
    $paginas['download_nao_arquivo'] = '/aluno/cursos/conteudo/arquivo/download?id=9004&inscricao_id=9001&curso_id=9001&turma_id=9001';
    $paginas['download_sem_inscricao'] = '/aluno/cursos/conteudo/arquivo/download?id=9008&inscricao_id=9001&curso_id=9999&turma_id=9001';

    foreach ($paginas as $nome => $caminho) {
        $resp = http_site($base, 'GET', $caminho, null, $cookies);
        $resp['corpo'] = $nome === 'download' ? md5($resp['corpo']) : normalizar($resp['corpo']);
        $r[$nome] = $resp;
    }

    // Ações (POST com CSRF)
    $pagina = http_site($base, 'GET', $ctx . '/modulo/9001/conteudo/9005', null, $cookies);
    $t = token_csrf($pagina['corpo']);
    $r['concluir_video'] = http_site($base, 'POST', '/aluno/cursos/conteudo/item/concluir', array('_token' => $t, 'item_id' => 9005, 'modulo_id' => 9001, 'inscricao_id' => 9001, 'curso_id' => 9001, 'turma_id' => 9001, 'acao' => 'marcar'), $cookies);
    $r['desmarcar_video'] = http_site($base, 'POST', '/aluno/cursos/conteudo/item/concluir', array('_token' => $t, 'item_id' => 9005, 'modulo_id' => 9001, 'inscricao_id' => 9001, 'curso_id' => 9001, 'turma_id' => 9001, 'acao' => 'desmarcar'), $cookies);
    $r['concluir_quiz'] = http_site($base, 'POST', '/aluno/cursos/conteudo/item/concluir', array('_token' => $t, 'item_id' => 9011, 'modulo_id' => 9002, 'inscricao_id' => 9001, 'curso_id' => 9001, 'turma_id' => 9001), $cookies);
    $r['pos_concluir'] = http_site($base, 'GET', $ctx . '/modulo/9001/conteudo/9005', null, $cookies);
    $r['pos_concluir']['corpo'] = normalizar($r['pos_concluir']['corpo']);

    $meus = http_site($base, 'GET', '/aluno/meus-cursos', null, $cookies);
    $t2 = token_csrf($meus['corpo']);
    $r['cancelar_sem_motivo'] = http_site($base, 'POST', '/aluno/meus-cursos/pedidos/cancelar', array('_token' => $t2, 'pedido_id' => 9003, 'motivo_cancelamento' => ''), $cookies);
    $r['cancelar_pago'] = http_site($base, 'POST', '/aluno/meus-cursos/pedidos/cancelar', array('_token' => $t2, 'pedido_id' => 9008, 'motivo_cancelamento' => 'x'), $cookies);
    $r['cancelar_inexistente'] = http_site($base, 'POST', '/aluno/meus-cursos/pedidos/cancelar', array('_token' => $t2, 'pedido_id' => 99999, 'motivo_cancelamento' => 'x'), $cookies);
    $r['cancelar_ok_v1'] = http_site($base, 'POST', '/aluno/meus-cursos/pedidos/cancelar', array('_token' => $t2, 'pedido_id' => 9003, 'motivo_cancelamento' => 'Teste de paridade'), $cookies);
    $r['meus_cursos_flash'] = http_site($base, 'GET', '/aluno/meus-cursos', null, $cookies);
    $r['meus_cursos_flash']['corpo'] = normalizar($r['meus_cursos_flash']['corpo']);

    $v2 = http_site($base, 'GET', '/v2/aluno/?aba=pedidos', null, $cookies);
    $t3 = token_csrf($v2['corpo']);
    $r['cancelar_ok_v2'] = http_site($base, 'POST', '/v2/aluno/pedidos/cancelar', array('_token' => $t3, 'pedido_id' => 9006, 'motivo_cancelamento' => 'Teste V2'), $cookies);
    $r['cancelar_v2_ja_cancelado'] = http_site($base, 'POST', '/v2/aluno/pedidos/cancelar', array('_token' => $t3, 'pedido_id' => 9006, 'motivo_cancelamento' => 'De novo'), $cookies);
    $r['v2_pedidos'] = http_site($base, 'GET', '/v2/aluno/?aba=pedidos', null, $cookies);
    $r['v2_pedidos']['corpo'] = normalizar($r['v2_pedidos']['corpo']);

    $r['pagar_desativado'] = http_site($base, 'POST', '/aluno/pedidos/pagar/abacatepay', array('_token' => $t2, 'pedido_id' => 9004), $cookies);
    $r['pagar_invalido'] = http_site($base, 'POST', '/aluno/pedidos/pagar/abacatepay', array('_token' => $t2, 'pedido_id' => 0, 'origem_v2' => 1), $cookies);
    $r['pagar_flash'] = http_site($base, 'GET', '/v2/aluno/', null, $cookies);
    $r['pagar_flash']['corpo'] = normalizar($r['pagar_flash']['corpo']);

    $r['_banco'] = estado_banco($pdo);
    @unlink($cookies);
    return $r;
}

$procAntes = subir(8097, $worktree, $tmp . '/router-antes.php');
$procDepois = subir(8096, $raiz, $tmp . '/router-depois.php');

$falhas = 0;
$total = 0;
try {
    $antes = percorrer('http://127.0.0.1:8097', $pdo, $worktree);
    $depois = percorrer('http://127.0.0.1:8096', $pdo, $raiz);

    echo "\nParidade do site HTML: {$commitBase} (antes) x código atual (depois)\n\n";
    foreach ($antes as $nome => $respAntes) {
        $total++;
        $respDepois = $depois[$nome] ?? null;
        if ($respAntes === $respDepois) {
            $resumo = $nome === '_banco' ? 'efeitos no banco idênticos' : ('HTTP ' . $respAntes['status'] . ($respAntes['location'] ? ' → ' . $respAntes['location'] : ''));
            echo "    ✓ {$nome}: {$resumo}\n";
            continue;
        }
        $falhas++;
        echo "    ✗ {$nome}: DIVERGE\n";
        if ($nome === '_banco') {
            echo '      antes:  ' . json_encode($respAntes) . "\n      depois: " . json_encode($respDepois) . "\n";
            continue;
        }
        foreach (array('status', 'location', 'tipo', 'disposicao') as $campo) {
            if ($respAntes[$campo] !== $respDepois[$campo]) {
                echo "      {$campo}: " . json_encode($respAntes[$campo]) . ' x ' . json_encode($respDepois[$campo]) . "\n";
            }
        }
        if ($respAntes['corpo'] !== $respDepois['corpo']) {
            $a = explode("\n", (string) $respAntes['corpo']);
            $b = explode("\n", (string) $respDepois['corpo']);
            foreach ($a as $i => $linha) {
                if (!isset($b[$i]) || $b[$i] !== $linha) {
                    echo "      corpo difere na linha " . ($i + 1) . ":\n        - " . substr(trim($linha), 0, 200) . "\n        + " . substr(trim($b[$i] ?? ''), 0, 200) . "\n";
                    break;
                }
            }
        }
    }
} finally {
    foreach (array($procAntes, $procDepois) as $proc) {
        if (is_resource($proc)) {
            $status = proc_get_status($proc);
            if (stripos(PHP_OS, 'WIN') === 0) {
                exec('taskkill /F /T /PID ' . (int) $status['pid'] . ' 2>NUL');
            } else {
                proc_terminate($proc);
            }
            proc_close($proc);
        }
    }
    rodar('git -C ' . escapeshellarg($raiz) . ' worktree remove --force ' . escapeshellarg($worktree));
    // deixa o banco como as fixtures (o e2e da API também reaplica)
    passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(BASE_PATH . '/tests/Api/montar_banco.php') . ' --so-fixtures > ' . (stripos(PHP_OS, 'WIN') === 0 ? 'NUL' : '/dev/null'));
}

echo "\nResultado: " . ($total - $falhas) . " idênticos | {$falhas} divergentes\n";
exit($falhas > 0 ? 1 : 0);
