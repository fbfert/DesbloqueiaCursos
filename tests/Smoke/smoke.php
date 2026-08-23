<?php

/**
 * Smoke test do portal Desbloqueia Cursos.
 *
 * Rede de regressão mínima: percorre as rotas declaradas em tests/Smoke/rotas.php
 * e afirma que o portal continua respondendo como deveria. Não substitui teste
 * unitário — responde a uma pergunta só: "a alteração derrubou alguma página?".
 *
 * Uso:
 *   php tests/Smoke/smoke.php https://exemplo.com.br
 *   php tests/Smoke/smoke.php https://exemplo.com.br --modo=todos
 *   php tests/Smoke/smoke.php http://127.0.0.1:8000 --json=/tmp/smoke.json
 *   php tests/Smoke/smoke.php https://exemplo.com.br --gravar-baseline
 *
 * Modos:
 *   --modo=anonimo      (padrão) rotas públicas + rotas protegidas sem sessão
 *   --modo=autenticado  faz login e percorre a área do aluno
 *   --modo=todos        os dois
 *
 * Opções:
 *   --json=ARQUIVO       grava o resultado completo em JSON
 *   --baseline=ARQUIVO   compara com um baseline (padrão tests/Smoke/baseline.json)
 *   --gravar-baseline    regrava o baseline com o resultado desta execução
 *   --timeout=N          timeout por requisição, em segundos (padrão 15)
 *   --limite-total=N     tempo máximo da suíte inteira, em segundos (padrão 300)
 *   --max-redirects=N    limite da cadeia de redirects (padrão 5)
 *   --tempo-estrito      degradação de tempo vira FALHA (padrão: apenas AVISO)
 *   --norminha=MODO      guarda do componente: no-maximo-um (padrão) | exatamente-um
 *                        Use exatamente-um só depois de ligar tutor_ativo=1.
 *   --rotas=ARQUIVO      usa outro arquivo de rotas (usado para testar o próprio runner)
 *   --sem-cor            desliga as cores ANSI
 *
 * Credenciais do modo autenticado (NUNCA hard-coded):
 *   SMOKE_USER=... SMOKE_PASS=... php tests/Smoke/smoke.php <url> --modo=todos
 *   Ausentes, o modo autenticado é PULADO com aviso, sem falhar a suíte.
 *   Use sempre um usuário de teste dedicado, jamais a conta de um aluno real.
 *
 * Saída: 0 se tudo passou, 1 se qualquer verificação falhou.
 */

// ---------------------------------------------------------------------------
// Argumentos
// ---------------------------------------------------------------------------

$argumentos = array_slice($argv, 1);
$base = '';
$opcoes = array(
    'modo' => 'anonimo',
    'json' => null,
    'baseline' => __DIR__ . '/baseline.json',
    'gravar_baseline' => false,
    'timeout' => 15,
    'limite_total' => 300,
    'max_redirects' => 5,
    'tempo_estrito' => false,
    'norminha' => 'no-maximo-um',
    'rotas' => __DIR__ . '/rotas.php',
    'cor' => true,
);

foreach ($argumentos as $argumento) {
    if (strpos($argumento, '--') !== 0) {
        $base = $argumento;
        continue;
    }
    $par = explode('=', substr($argumento, 2), 2);
    $chave = $par[0];
    $valor = isset($par[1]) ? $par[1] : true;

    switch ($chave) {
        case 'modo':             $opcoes['modo'] = (string) $valor; break;
        case 'json':             $opcoes['json'] = (string) $valor; break;
        case 'baseline':         $opcoes['baseline'] = (string) $valor; break;
        case 'gravar-baseline':  $opcoes['gravar_baseline'] = true; break;
        case 'timeout':          $opcoes['timeout'] = max(1, (int) $valor); break;
        case 'limite-total':     $opcoes['limite_total'] = max(10, (int) $valor); break;
        case 'max-redirects':    $opcoes['max_redirects'] = max(0, (int) $valor); break;
        case 'tempo-estrito':    $opcoes['tempo_estrito'] = true; break;
        case 'norminha':         $opcoes['norminha'] = (string) $valor; break;
        case 'rotas':            $opcoes['rotas'] = (string) $valor; break;
        case 'sem-cor':          $opcoes['cor'] = false; break;
        default:
            fwrite(STDERR, "Opção desconhecida: --{$chave}\n");
            exit(1);
    }
}

if ($base === '') {
    fwrite(STDERR, "Uso: php tests/Smoke/smoke.php <url-base> [--modo=anonimo|autenticado|todos]\n");
    fwrite(STDERR, "Exemplo: php tests/Smoke/smoke.php https://desbloqueiacursos.com.br\n");
    exit(1);
}

$base = rtrim($base, '/');
if (!preg_match('#^https?://#i', $base)) {
    fwrite(STDERR, "URL base inválida: {$base}\n");
    exit(1);
}

if (!in_array($opcoes['modo'], array('anonimo', 'autenticado', 'todos'), true)) {
    fwrite(STDERR, "Modo inválido: {$opcoes['modo']}. Use anonimo, autenticado ou todos.\n");
    exit(1);
}

if (!in_array($opcoes['norminha'], array('no-maximo-um', 'exatamente-um'), true)) {
    fwrite(STDERR, "Valor inválido para --norminha. Use no-maximo-um ou exatamente-um.\n");
    exit(1);
}

if (!is_readable($opcoes['rotas'])) {
    fwrite(STDERR, "Arquivo de rotas não encontrado: {$opcoes['rotas']}\n");
    exit(1);
}
$rotas = require $opcoes['rotas'];

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function smoke_cor($texto, $cor, $ativo)
{
    if (!$ativo) {
        return $texto;
    }
    $mapa = array('verde' => '0;32', 'vermelho' => '0;31', 'amarelo' => '0;33', 'cinza' => '0;90');
    if (!isset($mapa[$cor])) {
        return $texto;
    }
    return "\033[" . $mapa[$cor] . 'm' . $texto . "\033[0m";
}

/** Preenche até $largura considerando acentuação (strlen conta bytes). */
function smoke_pad($texto, $largura)
{
    $tamanho = function_exists('mb_strlen') ? mb_strlen($texto, 'UTF-8') : strlen($texto);
    if ($tamanho >= $largura) {
        return $texto;
    }
    return $texto . str_repeat(' ', $largura - $tamanho);
}

/** Resolve um Location relativo contra a URL corrente. */
function smoke_resolver_url($atual, $destino)
{
    $destino = trim($destino);
    if ($destino === '') {
        return null;
    }
    if (preg_match('#^https?://#i', $destino)) {
        return $destino;
    }

    $partes = parse_url($atual);
    if (!$partes || empty($partes['scheme']) || empty($partes['host'])) {
        return null;
    }
    $raiz = $partes['scheme'] . '://' . $partes['host'] . (isset($partes['port']) ? ':' . $partes['port'] : '');

    if (strpos($destino, '/') === 0) {
        return $raiz . $destino;
    }

    $caminho = isset($partes['path']) ? $partes['path'] : '/';
    return $raiz . rtrim(dirname($caminho), '/') . '/' . $destino;
}

/**
 * Requisita seguindo redirects manualmente, registrando a cadeia.
 * Retorna array(status, tempo_ms, corpo, url_final, cadeia, erro).
 */
function smoke_requisitar($url, array $opcoes, array $post = null, $cookieJar = null)
{
    $cadeia = array();
    $atual = $url;
    $corpo = '';
    $status = 0;
    $erro = null;
    $inicio = microtime(true);

    for ($salto = 0; $salto <= $opcoes['max_redirects']; $salto++) {
        $ch = curl_init($atual);
        $config = array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT        => $opcoes['timeout'],
            CURLOPT_CONNECTTIMEOUT => min(10, $opcoes['timeout']),
            CURLOPT_USERAGENT      => 'DesbloqueiaSmoke/1.0 (+tests/Smoke/smoke.php)',
            CURLOPT_ENCODING       => '',
        );
        if ($cookieJar !== null) {
            $config[CURLOPT_COOKIEJAR]  = $cookieJar;
            $config[CURLOPT_COOKIEFILE] = $cookieJar;
        }
        // O POST só ocorre no primeiro salto (autenticação). Redirect após POST
        // é seguido como GET, que é o comportamento correto do PRG.
        if ($post !== null && $salto === 0) {
            $config[CURLOPT_POST]       = true;
            $config[CURLOPT_POSTFIELDS] = http_build_query($post);
        }
        curl_setopt_array($ch, $config);

        $resposta = curl_exec($ch);
        if ($resposta === false) {
            $erro = curl_error($ch);
            curl_close($ch);
            break;
        }

        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $tamanhoCabecalho = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $cabecalhos = substr($resposta, 0, $tamanhoCabecalho);
        $corpo = substr($resposta, $tamanhoCabecalho);
        $cadeia[] = array('url' => $atual, 'status' => $status);

        if (!in_array($status, array(301, 302, 303, 307, 308), true)) {
            break;
        }

        if (!preg_match('/^\s*Location:\s*(.+?)\s*$/mi', $cabecalhos, $m)) {
            break;
        }

        $proxima = smoke_resolver_url($atual, $m[1]);
        if ($proxima === null || $proxima === $atual) {
            break;
        }
        $atual = $proxima;

        if ($salto === $opcoes['max_redirects']) {
            $erro = 'excedeu o limite de ' . $opcoes['max_redirects'] . ' redirects';
        }
    }

    return array(
        'status'    => $status,
        'tempo_ms'  => (int) round((microtime(true) - $inicio) * 1000),
        'corpo'     => $corpo,
        'url_final' => $atual,
        'cadeia'    => $cadeia,
        'erro'      => $erro,
    );
}

/** Erros de PHP vazando no corpo da resposta. */
function smoke_erros_php($corpo)
{
    // Com html_errors=On (padrão em servidor web) o PHP embrulha o rótulo do
    // erro em <b>...</b>: o corpo traz "<b>Warning</b>:", e não "Warning:".
    // Sem esta normalização a guarda passaria batido justamente no ambiente
    // que ela existe para vigiar. (Descoberto ao testar o próprio runner.)
    $plano = str_replace(array('<b>', '</b>', '<br />', '<br>'), '', $corpo);

    $achados = array();
    if (strpos($plano, 'Fatal error') !== false) { $achados[] = 'Fatal error'; }
    if (strpos($plano, 'Parse error') !== false) { $achados[] = 'Parse error'; }
    if (strpos($plano, 'Uncaught ') !== false)   { $achados[] = 'Uncaught'; }

    // Os demais só contam junto de " on line ", para não confundir com texto
    // de curso que legitimamente use as palavras "Aviso" ou "Warning".
    if (strpos($plano, ' on line ') !== false) {
        foreach (array('Warning:', 'Notice:', 'Deprecated:') as $palavra) {
            if (strpos($plano, $palavra) !== false) {
                $achados[] = rtrim($palavra, ':');
            }
        }
    }

    return array_values(array_unique($achados));
}

/**
 * Guarda do layout global.
 *
 * É o motivo principal desta suíte existir: o componente da Norminha é montado
 * pelo layout, e uma duplicação passa despercebida com HTTP 200. Conta as
 * ocorrências da raiz real do componente (id="norminha-tutor", confirmado em
 * resources/views/components/tutor_norminha.php) e dos seus assets.
 */
function smoke_guarda_layout($corpo, $modoNorminha)
{
    $contar = function ($agulha) use ($corpo) {
        return substr_count($corpo, $agulha);
    };

    $componente = $contar('id="norminha-tutor"');
    $css = $contar('tutor-norminha.css');
    $js = $contar('tutor-norminha.js');

    $falhas = array();

    if ($componente > 1) {
        $falhas[] = "componente da Norminha aparece {$componente}x (esperado no máximo 1)";
    }
    if ($modoNorminha === 'exatamente-um' && $componente !== 1) {
        $falhas[] = "componente da Norminha aparece {$componente}x (esperado exatamente 1)";
    }
    if ($css > 1) {
        $falhas[] = "CSS da Norminha incluído {$css}x";
    }
    if ($js > 1) {
        $falhas[] = "JS da Norminha incluído {$js}x";
    }

    // Folha de estilo base (23/08/2026).
    //
    // Em 22/08 um edit no layout legado apagou quatro <link> junto com o da
    // Norminha, entre eles /assets/css/app.css — que nao estava dentro de
    // condicional nenhuma e sustenta TODAS as paginas, inclusive o admin. O
    // site subiu sem estilo e nenhum teste percebeu: todos olhavam status HTTP,
    // erro de PHP e os assets da Norminha. Nenhum perguntava se a pagina tinha
    // aparencia.
    //
    // Um documento HTML completo tem que trazer a folha do seu layout: app.css
    // no legado, v2-main.css na V2. Sem nenhuma das duas, chegou sem estilo.
    if (stripos($corpo, '<html') !== false && stripos($corpo, '</body>') !== false) {
        $temBase = $contar('/assets/css/app.css') > 0
            || $contar('/v2/assets/css/v2-main.css') > 0;
        if (!$temBase) {
            $falhas[] = 'pagina sem folha de estilo base (nem app.css nem v2-main.css)';
        }
    }

    foreach (smoke_erros_php($corpo) as $erro) {
        $falhas[] = "erro de PHP no corpo: {$erro}";
    }

    return array(
        'falhas'     => $falhas,
        'componente' => $componente,
        'css'        => $css,
        'js'         => $js,
    );
}

/** Extrai o token CSRF do formulário (App\Core\Csrf grava em name="_token"). */
function smoke_extrair_token($html)
{
    if (preg_match('/name="_token"\s+value="([^"]+)"/i', $html, $m)) {
        return $m[1];
    }
    if (preg_match('/value="([^"]+)"\s+name="_token"/i', $html, $m)) {
        return $m[1];
    }
    return null;
}

// ---------------------------------------------------------------------------
// Execução
// ---------------------------------------------------------------------------

$cor = $opcoes['cor'] && (getenv('NO_COLOR') === false);
$inicioSuite = microtime(true);
$resultados = array();
$falhasTotais = 0;
$avisosTotais = 0;
$abortado = false;

$baseline = array();
if (!$opcoes['gravar_baseline'] && is_readable($opcoes['baseline'])) {
    $lido = json_decode((string) file_get_contents($opcoes['baseline']), true);
    if (is_array($lido) && isset($lido['rotas']) && is_array($lido['rotas'])) {
        $baseline = $lido['rotas'];
    }
}

echo "\n";
echo "Smoke test — Desbloqueia Cursos\n";
echo "Base: {$base}\n";
echo "Modo: {$opcoes['modo']}   Norminha: {$opcoes['norminha']}   Timeout: {$opcoes['timeout']}s\n";
echo str_repeat('-', 100) . "\n";

/**
 * Executa e imprime uma verificação.
 */
$verificar = function ($grupo, array $rota, array $contexto) use (
    &$resultados, &$falhasTotais, &$avisosTotais, $base, $opcoes, $rotas, $baseline, $cor
) {
    $path = $rota['path'];
    $nome = isset($rota['nome']) ? $rota['nome'] : $path;
    $chave = $grupo . ' ' . $path;

    $resposta = smoke_requisitar(
        $base . $path,
        $opcoes,
        null,
        isset($contexto['cookies']) ? $contexto['cookies'] : null
    );

    $falhas = array();
    $avisos = array();

    if ($resposta['erro'] !== null) {
        $falhas[] = 'cURL: ' . $resposta['erro'];
    }

    if ($grupo === 'protegida') {
        // Teste de segurança: um 200 aqui significa conteúdo exposto a anônimo.
        $destino = isset($rota['destino']) ? $rota['destino'] : '/login';
        if (strpos($resposta['url_final'], $destino) === false) {
            $falhas[] = "não redirecionou para {$destino} (terminou em {$resposta['url_final']})";
        }
        if ($resposta['status'] !== 200) {
            $falhas[] = "status final {$resposta['status']} (esperado 200 na página de login)";
        }
    } else {
        $esperado = isset($rota['status']) ? (int) $rota['status'] : 200;
        if ($resposta['status'] !== $esperado) {
            $falhas[] = "status {$resposta['status']} (esperado {$esperado})";
        }

        $marcador = isset($rota['marcador']) ? $rota['marcador'] : $rotas['marcador_padrao'];
        if ($marcador !== '' && strpos($resposta['corpo'], $marcador) === false) {
            $falhas[] = 'marcador ausente: ' . $marcador;
        }
    }

    $guardaAtiva = !isset($rota['guarda']) || $rota['guarda'] !== false;
    $guarda = array('componente' => null, 'css' => null, 'js' => null);
    if ($guardaAtiva) {
        $guarda = smoke_guarda_layout($resposta['corpo'], $opcoes['norminha']);
        foreach ($guarda['falhas'] as $f) {
            $falhas[] = $f;
        }
    }

    // Tempo: comparado ao baseline. Ruído de rede é comum, então por padrão isto
    // é AVISO. --tempo-estrito transforma em falha.
    if (isset($baseline[$chave]['tempo_ms'])) {
        $anterior = (int) $baseline[$chave]['tempo_ms'];
        $limite = (int) round($anterior * 3) + 500;
        if ($resposta['tempo_ms'] > $limite) {
            $mensagem = "tempo {$resposta['tempo_ms']}ms vs baseline {$anterior}ms (limite {$limite}ms)";
            if ($opcoes['tempo_estrito']) {
                $falhas[] = $mensagem;
            } else {
                $avisos[] = $mensagem;
            }
        }
    }

    if (isset($rota['defeito']) && $rota['defeito'] !== '') {
        $avisos[] = 'defeito conhecido: ' . $rota['defeito'];
    }

    $ok = empty($falhas);
    if (!$ok) {
        $falhasTotais++;
    }
    $avisosTotais += count($avisos);

    $selo = $ok
        ? smoke_cor('PASS', 'verde', $cor)
        : smoke_cor('FAIL', 'vermelho', $cor);

    $saltos = count($resposta['cadeia']) - 1;
    $extra = $saltos > 0 ? smoke_cor(" ({$saltos} redirect" . ($saltos > 1 ? 's' : '') . ')', 'cinza', $cor) : '';

    printf(
        "%s  %s %s %s%s\n",
        $selo,
        smoke_pad($nome, 34),
        smoke_pad($path, 30),
        str_pad($resposta['status'] . '  ' . $resposta['tempo_ms'] . 'ms', 14, ' ', STR_PAD_LEFT),
        $extra
    );

    foreach ($falhas as $f) {
        echo '      ' . smoke_cor('· ' . $f, 'vermelho', $cor) . "\n";
    }
    foreach ($avisos as $a) {
        echo '      ' . smoke_cor('· aviso: ' . $a, 'amarelo', $cor) . "\n";
    }

    $resultados[$chave] = array(
        'grupo'      => $grupo,
        'nome'       => $nome,
        'path'       => $path,
        'status'     => $resposta['status'],
        'tempo_ms'   => $resposta['tempo_ms'],
        'url_final'  => $resposta['url_final'],
        'redirects'  => $saltos,
        'norminha'   => $guarda['componente'],
        'css'        => $guarda['css'],
        'js'         => $guarda['js'],
        'ok'         => $ok,
        'defeito'    => isset($rota['defeito']) ? $rota['defeito'] : null,
        'falhas'     => $falhas,
        'avisos'     => $avisos,
    );

    return $ok;
};

$excedeuTempo = function () use ($inicioSuite, $opcoes) {
    return (microtime(true) - $inicioSuite) > $opcoes['limite_total'];
};

// --- Modo anônimo ---------------------------------------------------------
if ($opcoes['modo'] === 'anonimo' || $opcoes['modo'] === 'todos') {
    echo "\n" . smoke_cor('MODO ANÔNIMO — rotas públicas', 'cinza', $cor) . "\n";
    foreach ($rotas['anonimo'] as $rota) {
        if ($excedeuTempo()) { $abortado = true; break; }
        $verificar('anonimo', $rota, array());
    }

    if (!$abortado) {
        echo "\n" . smoke_cor('MODO ANÔNIMO — rotas protegidas (devem negar acesso)', 'cinza', $cor) . "\n";
        foreach ($rotas['protegidas'] as $rota) {
            if ($excedeuTempo()) { $abortado = true; break; }
            $verificar('protegida', $rota, array());
        }
    }
}

// --- Modo autenticado -----------------------------------------------------
$autenticadoPulado = false;
if (!$abortado && ($opcoes['modo'] === 'autenticado' || $opcoes['modo'] === 'todos')) {
    $usuario = getenv('SMOKE_USER');
    $senha = getenv('SMOKE_PASS');

    echo "\n" . smoke_cor('MODO AUTENTICADO', 'cinza', $cor) . "\n";

    if ($usuario === false || $senha === false || $usuario === '' || $senha === '') {
        echo smoke_cor("SKIP  SMOKE_USER/SMOKE_PASS não definidos — modo autenticado pulado.", 'amarelo', $cor) . "\n";
        echo smoke_cor("      Defina as variáveis e use um usuário de teste dedicado.", 'cinza', $cor) . "\n";
        $autenticadoPulado = true;
    } else {
        $cookies = tempnam(sys_get_temp_dir(), 'smoke_cookies_');
        $conf = $rotas['login'];

        $paginaLogin = smoke_requisitar($base . $conf['pagina'], $opcoes, null, $cookies);
        $token = smoke_extrair_token($paginaLogin['corpo']);

        if ($token === null) {
            echo smoke_cor("FAIL  não foi possível extrair o token CSRF de {$conf['pagina']}", 'vermelho', $cor) . "\n";
            $falhasTotais++;
        } else {
            $campos = array_merge($conf['extras'], array(
                '_token'            => $token,
                $conf['campo_user'] => $usuario,
                $conf['campo_pass'] => $senha,
            ));

            // Único POST permitido nesta suíte.
            $login = smoke_requisitar($base . $conf['acao'], $opcoes, $campos, $cookies);
            $autenticou = strpos($login['url_final'], $conf['sucesso_nao_contem']) === false
                && $login['status'] === 200;

            if (!$autenticou) {
                echo smoke_cor("FAIL  login falhou (terminou em {$login['url_final']}, status {$login['status']})", 'vermelho', $cor) . "\n";
                $falhasTotais++;
            } else {
                echo smoke_cor("      login OK — sessão estabelecida", 'cinza', $cor) . "\n";
                foreach ($rotas['autenticado'] as $rota) {
                    if ($excedeuTempo()) { $abortado = true; break; }
                    $verificar('autenticado', $rota, array('cookies' => $cookies));
                }
            }
        }

        if (is_file($cookies)) {
            unlink($cookies);
        }
    }
}

// ---------------------------------------------------------------------------
// Fechamento
// ---------------------------------------------------------------------------

$duracao = round(microtime(true) - $inicioSuite, 1);
$total = count($resultados);
$passaram = $total - $falhasTotais;

echo "\n" . str_repeat('-', 100) . "\n";

if ($abortado) {
    echo smoke_cor("ABORTADO: limite total de {$opcoes['limite_total']}s excedido.", 'vermelho', $cor) . "\n";
    $falhasTotais++;
}

printf(
    "%d verificações · %s · %s · %s · %ss\n",
    $total,
    smoke_cor($passaram . ' PASS', 'verde', $cor),
    $falhasTotais > 0 ? smoke_cor($falhasTotais . ' FAIL', 'vermelho', $cor) : '0 FAIL',
    $avisosTotais > 0 ? smoke_cor($avisosTotais . ' aviso(s)', 'amarelo', $cor) : '0 aviso',
    $duracao
);

if ($autenticadoPulado) {
    echo smoke_cor("Atenção: modo autenticado não foi executado.\n", 'amarelo', $cor);
}

if ($opcoes['json'] !== null) {
    $conteudo = json_encode(array(
        'base'      => $base,
        'modo'      => $opcoes['modo'],
        'gerado_em' => date('c'),
        'duracao_s' => $duracao,
        'falhas'    => $falhasTotais,
        'rotas'     => $resultados,
    ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    file_put_contents($opcoes['json'], $conteudo);
    echo "JSON gravado em {$opcoes['json']}\n";
}

if ($opcoes['gravar_baseline']) {
    if ($falhasTotais > 0) {
        echo smoke_cor("Baseline NÃO regravado: a execução teve falhas. Corrija antes de fixar o baseline.\n", 'vermelho', $cor);
    } else {
        $enxuto = array();
        foreach ($resultados as $chave => $r) {
            $enxuto[$chave] = array('status' => $r['status'], 'tempo_ms' => $r['tempo_ms']);
        }
        file_put_contents($opcoes['baseline'], json_encode(array(
            'base'      => $base,
            'modo'      => $opcoes['modo'],
            'gerado_em' => date('c'),
            'rotas'     => $enxuto,
        ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        echo "Baseline regravado em {$opcoes['baseline']} ({$total} rotas).\n";
    }
}

echo "\n";
exit($falhasTotais > 0 ? 1 : 0);
