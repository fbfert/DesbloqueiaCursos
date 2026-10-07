<?php

/**
 * Checkout logado (V2) — teste HTTP de ponta a ponta.
 *
 * Fala HTTP com o app de verdade (middleware auth.v2, CSRF, sessão, upload) e
 * confere o resultado no banco. Cobre:
 *   1. Proteção: sem sessão, as etapas do checkout vão para o login.
 *   2. Fluxo feliz: inscrição (2 vagas) → participantes → resumo → cupom →
 *      pagamento (PIX) → comprovante (PNG gerado aqui) → tela de enviado,
 *      com conferência no banco (pedido, participantes, desconto, comprovante).
 *   3. Validação: CPF inválido de participante é recusado e o pedido não
 *      avança; cupom inexistente é recusado com mensagem.
 *   4. Segurança: outro aluno não vê resumo/participantes/pagamento/comprovante
 *      do pedido e não consegue enviar comprovante nele.
 *
 * DADOS: cria um curso + turma (id 9871, slug/código "e2e-chk-…") e um cupom
 * público de 10% (código "E2E-CHK-…"). Tudo o que o fluxo gera — pedidos,
 * itens, participantes, inscrições, históricos, cupom aplicado/usos,
 * comprovantes PIX, auditoria, e-mails registrados e o arquivo enviado em
 * storage/private_uploads/comprovantes_pix/<pedido> — é APAGADO no final,
 * inclusive se uma asserção falhar (finally + register_shutdown_function).
 * A limpeza também roda no início, para remover sobras de uma execução
 * interrompida.
 *
 * GUARDA: só roda se o banco do .env for `desbloqueia_local` ou se
 * CHECKOUT_E2E_PERMITIR=1 estiver definido; caso contrário imprime SKIP e sai 0.
 * Nunca aponte para produção.
 *
 * PRÉ-REQUISITOS (ambiente Docker local, ver docker/local/)
 *   - app em http://127.0.0.1:8010 (container desbloqueia-app-1);
 *   - MariaDB em 127.0.0.1:3306 (base desbloqueia_local, credenciais do .env);
 *   - fixture tests/Fixtures/tema_caderno_aluno.sql aplicada (aluna 9001,
 *     aluno.caderno@teste.local / Local@12345) e o aluno
 *     aluno.homologacao@polorainbow.com.br com a mesma senha.
 *
 * Variáveis opcionais:
 *   CHECKOUT_E2E_URL     alvo (padrão http://127.0.0.1:8010)
 *   CHECKOUT_E2E_USER_A  / CHECKOUT_E2E_USER_B / CHECKOUT_E2E_PASS
 *   CHECKOUT_E2E_PERMITIR=1  permite banco diferente de desbloqueia_local
 *   CHECKOUT_E2E_TIMEOUT  segundos por requisição (padrão 120; o servidor
 *                         embutido do container atende uma requisição por vez
 *                         e fica lento se outra suíte estiver rodando junto)
 *
 * Execução:
 *   C:\Users\Dell\php84\php.exe tests/Unit/checkout_logado_e2e.php
 *   (ou: php tests/Unit/checkout_logado_e2e.php — requer extensões curl e pdo_mysql)
 */

require_once __DIR__ . '/_bootstrap.php';

// -------------------------------------------------------------------
// Guarda: nunca contra produção
// -------------------------------------------------------------------

$envTeste = testes_carregar_env();
$bancoAlvo = isset($envTeste['DB_DATABASE']) ? (string) $envTeste['DB_DATABASE'] : '';
if ($bancoAlvo !== 'desbloqueia_local' && getenv('CHECKOUT_E2E_PERMITIR') !== '1') {
    echo "SKIP: banco '{$bancoAlvo}' não é desbloqueia_local (defina CHECKOUT_E2E_PERMITIR=1 para forçar).\n";
    exit(0);
}

$base = rtrim((string) (getenv('CHECKOUT_E2E_URL') ?: 'http://127.0.0.1:8010'), '/');
$usuarioA = getenv('CHECKOUT_E2E_USER_A') ?: 'aluno.caderno@teste.local';
$usuarioB = getenv('CHECKOUT_E2E_USER_B') ?: 'aluno.homologacao@polorainbow.com.br';
$senha = getenv('CHECKOUT_E2E_PASS') ?: 'Local@12345';

const E2E_CURSO_ID = 9871;
const E2E_TURMA_ID = 9871;
const E2E_CUPOM_PREFIXO = 'E2E-CHK-';
const E2E_VALOR_CURSO = 150.00;
const E2E_CUPOM_PERCENTUAL = 10;

$pdo = testes_conectar_banco();
if ($pdo->query('SELECT DATABASE()')->fetchColumn() === 'desbloqueiacursos') {
    fwrite(STDERR, "RECUSADO: este teste escreve. Aponte para o banco de desenvolvimento.\n");
    exit(1);
}

echo "\nAlvo: {$base} | banco: {$bancoAlvo}\n";

// -------------------------------------------------------------------
// Cliente HTTP mínimo (cookies em arquivo, sem seguir redirect)
// -------------------------------------------------------------------

function chk_http($base, $metodo, $caminho, $dados = null, $cookies = null, $multipart = false)
{
    $ch = curl_init($base . $caminho);
    $opts = array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => (int) (getenv('CHECKOUT_E2E_TIMEOUT') ?: 120),
    );
    if ($cookies !== null) {
        $opts[CURLOPT_COOKIEJAR] = $cookies;
        $opts[CURLOPT_COOKIEFILE] = $cookies;
    }
    if ($metodo === 'POST') {
        $opts[CURLOPT_POST] = true;
        // multipart: array com CURLFile; urlencoded: http_build_query (aceita arrays aninhados).
        $opts[CURLOPT_POSTFIELDS] = $multipart ? $dados : http_build_query((array) $dados);
    }
    curl_setopt_array($ch, $opts);

    $bruto = curl_exec($ch);
    $erro = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $tamanho = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    $cabecalhos = substr((string) $bruto, 0, $tamanho);
    $corpo = substr((string) $bruto, $tamanho);

    return array(
        'status' => $status,
        'erro' => $erro,
        'corpo' => $corpo,
        'location' => preg_match('/^\s*Location:\s*(.+?)\s*$/mi', $cabecalhos, $m) ? $m[1] : null,
    );
}

/** Caminho relativo do Location (remove esquema/host, se houver). */
function chk_caminho_location($location)
{
    if ($location === null) {
        return '';
    }
    $partes = parse_url($location);
    $caminho = isset($partes['path']) ? $partes['path'] : '';
    return $caminho . (isset($partes['query']) ? '?' . $partes['query'] : '');
}

function chk_token($html)
{
    return preg_match('/name="_token"\s+value="([^"]+)"/i', (string) $html, $m) ? $m[1] : null;
}

function chk_login($base, $usuario, $senha)
{
    $cookies = tempnam(sys_get_temp_dir(), 'chk_e2e_');
    $pagina = chk_http($base, 'GET', '/v2/login/', null, $cookies);
    $token = chk_token($pagina['corpo']);
    if ($token === null) {
        return null;
    }
    $r = chk_http($base, 'POST', '/login', array(
        '_token' => $token, 'origem' => 'v2', 'login' => $usuario, 'senha' => $senha,
    ), $cookies);
    if ($r['status'] !== 302 && $r['status'] !== 200) {
        return null;
    }
    // Confirma que a sessão é de fato autenticada.
    $area = chk_http($base, 'GET', '/v2/checkout/participantes?pedido_id=1', null, $cookies);
    if ($area['location'] !== null && strpos($area['location'], '/login') !== false) {
        return null;
    }
    return $cookies;
}

/** Gera um CPF válido determinístico a partir de 9 dígitos-base. */
function chk_cpf_valido($base9)
{
    $d = array_map('intval', str_split($base9));
    for ($t = 9; $t < 11; $t++) {
        $soma = 0;
        for ($i = 0; $i < $t; $i++) {
            $soma += $d[$i] * (($t + 1) - $i);
        }
        $d[$t] = ((10 * $soma) % 11) % 10;
    }
    return implode('', $d);
}

function chk_formatar_cpf($cpf)
{
    return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
}

function chk_so_digitos($v)
{
    return preg_replace('/\D+/', '', (string) $v);
}

/** Lança se a condição for falsa (mensagem legível no relatório). */
function chk_garantir($condicao, $mensagem)
{
    if (!$condicao) {
        throw new RuntimeException($mensagem);
    }
}

// -------------------------------------------------------------------
// Dados de teste: criação e limpeza
// -------------------------------------------------------------------

function chk_ids_in(array $ids)
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    return $ids ? implode(',', $ids) : '';
}

/** Tabelas (da base atual) que têm a coluna informada. */
function chk_tabelas_com_coluna(PDO $pdo, $coluna)
{
    $stmt = $pdo->prepare(
        'SELECT c.TABLE_NAME FROM information_schema.COLUMNS c
         JOIN information_schema.TABLES t ON t.TABLE_SCHEMA = c.TABLE_SCHEMA AND t.TABLE_NAME = c.TABLE_NAME
         WHERE c.TABLE_SCHEMA = DATABASE() AND c.COLUMN_NAME = :c AND t.TABLE_TYPE = "BASE TABLE"'
    );
    $stmt->execute(array('c' => $coluna));
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function chk_apagar_por_coluna(PDO $pdo, $coluna, array $ids, array $exceto = array())
{
    $lista = chk_ids_in($ids);
    if ($lista === '') {
        return;
    }
    foreach (chk_tabelas_com_coluna($pdo, $coluna) as $tabela) {
        if (in_array($tabela, $exceto, true)) {
            continue;
        }
        $pdo->exec("DELETE FROM `{$tabela}` WHERE `{$coluna}` IN ({$lista})");
    }
}

function chk_apagar_diretorio($dir)
{
    if (!is_dir($dir)) {
        return;
    }
    foreach (scandir($dir) as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $caminho = $dir . DIRECTORY_SEPARATOR . $item;
        is_dir($caminho) ? chk_apagar_diretorio($caminho) : @unlink($caminho);
    }
    @rmdir($dir);
}

/**
 * Apaga TUDO o que o teste cria ou o fluxo gera. Idempotente; só toca em linhas
 * ligadas ao curso 9871 ou a cupons com prefixo E2E-CHK-.
 */
function chk_limpar(PDO $pdo)
{
    $cupomIds = $pdo->query("SELECT id FROM cupons WHERE codigo LIKE '" . E2E_CUPOM_PREFIXO . "%'")->fetchAll(PDO::FETCH_COLUMN);

    $pedidoIds = $pdo->query('SELECT DISTINCT pedido_id FROM pedido_itens WHERE curso_evento_id = ' . E2E_CURSO_ID)->fetchAll(PDO::FETCH_COLUMN);
    $pedidoIds = array_merge(
        $pedidoIds,
        $pdo->query("SELECT id FROM pedidos WHERE cupom_codigo LIKE '" . E2E_CUPOM_PREFIXO . "%'")->fetchAll(PDO::FETCH_COLUMN)
    );
    $listaPedidos = chk_ids_in($pedidoIds);

    $inscricaoIds = $pdo->query('SELECT id FROM inscricoes WHERE curso_evento_id = ' . E2E_CURSO_ID
        . ($listaPedidos !== '' ? " OR pedido_id IN ({$listaPedidos})" : ''))->fetchAll(PDO::FETCH_COLUMN);
    $participanteIds = $listaPedidos !== ''
        ? $pdo->query("SELECT id FROM participantes_pedido WHERE pedido_id IN ({$listaPedidos})")->fetchAll(PDO::FETCH_COLUMN)
        : array();
    $comprovantes = $listaPedidos !== ''
        ? $pdo->query("SELECT id, arquivo_caminho FROM comprovantes_pix WHERE pedido_id IN ({$listaPedidos})")->fetchAll(PDO::FETCH_ASSOC)
        : array();
    $comprovanteIds = array_column($comprovantes, 'id');

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    try {
        // Linhas dependentes (qualquer tabela que referencie o que criamos).
        chk_apagar_por_coluna($pdo, 'inscricao_id', $inscricaoIds);
        chk_apagar_por_coluna($pdo, 'participante_pedido_id', $participanteIds);
        chk_apagar_por_coluna($pdo, 'comprovante_pix_id', $comprovanteIds);
        chk_apagar_por_coluna($pdo, 'pedido_id', $pedidoIds);
        chk_apagar_por_coluna($pdo, 'cupom_id', $cupomIds);

        $mapaEntidades = array(
            'pedido' => $pedidoIds,
            'comprovante_pix' => $comprovanteIds,
            'cupom' => $cupomIds,
            'inscricao' => $inscricaoIds,
        );
        foreach ($mapaEntidades as $tipo => $ids) {
            $lista = chk_ids_in($ids);
            if ($lista === '') {
                continue;
            }
            $pdo->exec("DELETE FROM auditoria_logs WHERE entidade_tipo = " . $pdo->quote($tipo) . " AND entidade_id IN ({$lista})");
            $pdo->exec("DELETE FROM emails_envios WHERE entidade_tipo = " . $pdo->quote($tipo) . " AND entidade_id IN ({$lista})");
        }

        if (chk_ids_in($inscricaoIds) !== '') {
            $pdo->exec('DELETE FROM inscricoes WHERE id IN (' . chk_ids_in($inscricaoIds) . ')');
        }
        if ($listaPedidos !== '') {
            $pdo->exec("DELETE FROM pedidos WHERE id IN ({$listaPedidos})");
        }
        if (chk_ids_in($cupomIds) !== '') {
            $pdo->exec('DELETE FROM cupons WHERE id IN (' . chk_ids_in($cupomIds) . ')');
        }
        chk_apagar_por_coluna($pdo, 'curso_evento_id', array(E2E_CURSO_ID), array('cursos_eventos'));
        chk_apagar_por_coluna($pdo, 'turma_id', array(E2E_TURMA_ID), array('turmas'));
        $pdo->exec('DELETE FROM turmas WHERE id = ' . E2E_TURMA_ID);
        $pdo->exec('DELETE FROM cursos_eventos WHERE id = ' . E2E_CURSO_ID);
    } finally {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    // Arquivos enviados (storage fora de public_html, montado no container).
    foreach ($pedidoIds as $pid) {
        chk_apagar_diretorio(BASE_PATH . '/storage/private_uploads/comprovantes_pix/' . (int) $pid);
    }
    foreach ($comprovantes as $c) {
        foreach (array(BASE_PATH . '/storage/private_uploads/' . ltrim($c['arquivo_caminho'], '/'), BASE_PATH . '/' . ltrim($c['arquivo_caminho'], '/')) as $arquivo) {
            if (is_file($arquivo)) {
                @unlink($arquivo);
            }
        }
    }

    return count(array_unique(array_map('intval', $pedidoIds)));
}

function chk_criar_dados(PDO $pdo, $cupomCodigo)
{
    $pdo->exec("INSERT INTO cursos_eventos (id, nome, slug, tipo, modalidade, descricao_curta, carga_horaria, valor,
            usar_turmas, permite_compra_lote, permite_compra_terceiros, certificado_previsto, em_promocao, status, created_at, updated_at)
        VALUES (" . E2E_CURSO_ID . ", 'Curso E2E do checkout (teste automático)', 'e2e-chk-curso', 'curso', 'online_ao_vivo',
            'Criado e apagado por tests/Unit/checkout_logado_e2e.php.', 8, " . E2E_VALOR_CURSO . ",
            1, 1, 1, 1, 0, 'ativo', NOW(), NOW())");
    $pdo->exec("INSERT INTO turmas (id, curso_evento_id, nome, slug, codigo, data_inicio, data_fim, status, created_at, updated_at)
        VALUES (" . E2E_TURMA_ID . ", " . E2E_CURSO_ID . ", 'Turma E2E do checkout', 'e2e-chk-turma', 'E2E-CHK-T9871',
            DATE_ADD(CURDATE(), INTERVAL 30 DAY), DATE_ADD(CURDATE(), INTERVAL 120 DAY), 'aberta', NOW(), NOW())");
    $stmt = $pdo->prepare("INSERT INTO cupons (codigo, nome, descricao, tipo, desconto_tipo, valor_desconto, status, escopo, created_at, updated_at)
        VALUES (:codigo, 'Cupom E2E do checkout', 'Criado e apagado pelo teste automático.', 'publico', 'percentual', :valor, 'ativo', 'todo_site', NOW(), NOW())");
    $stmt->execute(array('codigo' => $cupomCodigo, 'valor' => E2E_CUPOM_PERCENTUAL));
}

// -------------------------------------------------------------------
// Preparação
// -------------------------------------------------------------------

$sondagem = chk_http($base, 'GET', '/v2/login/');
if ($sondagem['status'] === 0) {
    fwrite(STDERR, "ERRO: {$base} não respondeu ({$sondagem['erro']}). O container do app está no ar?\n");
    exit(1);
}

$sobras = chk_limpar($pdo);
if ($sobras > 0) {
    echo "Limpeza inicial: {$sobras} pedido(s) de execução anterior removido(s).\n";
}

$GLOBALS['__chk_limpo'] = false;
register_shutdown_function(function () use ($pdo) {
    if (empty($GLOBALS['__chk_limpo'])) {
        try {
            chk_limpar($pdo);
            echo "Limpeza (shutdown) concluída.\n";
        } catch (Throwable $e) {
            fwrite(STDERR, "FALHA NA LIMPEZA: " . $e->getMessage() . "\n");
        }
    }
});

$cupomCodigo = E2E_CUPOM_PREFIXO . strtoupper(bin2hex(random_bytes(3)));
$cpf1 = chk_cpf_valido('529982247');           // 529.982.247-25
$cpf2 = chk_cpf_valido('111444777');           // 111.444.777-35
$part1 = array('nome' => 'Participante E2E Um', 'cpf' => chk_formatar_cpf($cpf1), 'email' => 'e2e-chk-1@teste.local', 'telefone' => '(11) 91111-1111');
$part2 = array('nome' => 'Participante E2E Dois', 'cpf' => chk_formatar_cpf($cpf2), 'email' => 'e2e-chk-2@teste.local', 'telefone' => '(11) 92222-2222');
$subtotalEsperado = round(E2E_VALOR_CURSO * 2, 2);
$descontoEsperado = round($subtotalEsperado * E2E_CUPOM_PERCENTUAL / 100, 2);
$totalEsperado = round($subtotalEsperado - $descontoEsperado, 2);
$dinheiro = function ($v) { return 'R$ ' . number_format((float) $v, 2, ',', '.'); };

// Estado compartilhado entre os casos (o fluxo é sequencial).
$ctx = array('cookiesA' => null, 'pedidoId' => 0, 'itemId' => 0, 'codigo' => '');

$codigoSaida = 1;
try {
    chk_criar_dados($pdo, $cupomCodigo);
    echo "Dados criados: curso/turma " . E2E_CURSO_ID . " (" . $dinheiro(E2E_VALOR_CURSO) . "), cupom {$cupomCodigo} (" . E2E_CUPOM_PERCENTUAL . "%).\n";

    // ===============================================================
    describe('1. Proteção — sem sessão não há checkout');

    it('GET /v2/checkout/participantes anônimo redireciona para o login V2', function () use ($base) {
        $r = chk_http($base, 'GET', '/v2/checkout/participantes?pedido_id=1');
        expect($r['status'])->toBe(302);
        expect((string) $r['location'])->toContain('/v2/login');
        chk_garantir(strpos($r['corpo'], 'v2-checkout-participantes-form') === false, 'corpo trouxe o formulário de participantes');
    });

    it('resumo, pagamento e comprovante anônimos também vão para o login', function () use ($base) {
        foreach (array('/v2/checkout/resumo?pedido_id=1', '/v2/checkout/pagamento?pedido_id=1', '/v2/checkout/comprovante?pedido_id=1', '/v2/checkout/inscricao?curso_id=' . E2E_CURSO_ID) as $rota) {
            $r = chk_http($base, 'GET', $rota);
            chk_garantir($r['status'] === 302 && strpos((string) $r['location'], '/login') !== false, "{$rota} respondeu {$r['status']} sem ir ao login");
        }
        expect(true)->toBeTrue();
    });

    it('POST anônimo de participantes não grava (vai para o login)', function () use ($base) {
        $r = chk_http($base, 'POST', '/v2/checkout/participantes?pedido_id=1', array('participantes' => array(array('nome' => 'x'))));
        chk_garantir(in_array($r['status'], array(302, 403, 419), true), "status inesperado {$r['status']}");
        chk_garantir($r['status'] !== 200, 'POST anônimo respondeu 200');
    });

    // ===============================================================
    describe('2. Fluxo feliz logado — 2 vagas, cupom e comprovante PIX');

    it('aluno A faz login', function () use ($base, $usuarioA, $senha, &$ctx) {
        $ctx['cookiesA'] = chk_login($base, $usuarioA, $senha);
        chk_garantir($ctx['cookiesA'] !== null, "login de {$usuarioA} falhou");
    });

    it('inscrição: escolhe curso/turma e 2 vagas → cria pedido e vai a participantes', function () use ($base, $pdo, &$ctx, $subtotalEsperado) {
        chk_garantir($ctx['cookiesA'] !== null, 'sem sessão do aluno A');
        $pagina = chk_http($base, 'GET', '/v2/checkout/inscricao?curso_id=' . E2E_CURSO_ID . '&turma_id=' . E2E_TURMA_ID, null, $ctx['cookiesA']);
        expect($pagina['status'])->toBe(200);
        expect($pagina['corpo'])->toContain('v2-checkout-inscricao-form');
        expect($pagina['corpo'])->toContain('Curso E2E do checkout');
        $token = chk_token($pagina['corpo']);
        expect($token)->notToBeNull();

        $r = chk_http($base, 'POST', '/v2/checkout/inscricao', array(
            '_token' => $token,
            'curso_evento_id' => E2E_CURSO_ID,
            'turma_id' => E2E_TURMA_ID,
            'tipo_pedido' => 'terceiros',
            'quantidade' => 2,
            'pagador_nome' => 'Aluna Caderno Teste',
            'pagador_cpf' => '987.654.321-00',
            'pagador_email' => 'aluno.caderno@teste.local',
            'pagador_telefone' => '(11) 98888-7766',
            'pagador_estado' => 'SP',
            'pagador_cidade' => 'São Paulo',
        ), $ctx['cookiesA']);
        expect($r['status'])->toBe(302);
        $destino = chk_caminho_location($r['location']);
        chk_garantir(preg_match('#^/v2/checkout/participantes\?pedido_id=(\d+)$#', $destino, $m) === 1, "destino inesperado: {$destino}");
        $ctx['pedidoId'] = (int) $m[1];

        $pedido = $pdo->query('SELECT * FROM pedidos WHERE id = ' . $ctx['pedidoId'])->fetch();
        expect((int) $pedido['comprador_usuario_id'])->toBe(9001);
        expect($pedido['status'])->toBe('rascunho');
        expect($pedido['tipo_pedido'])->toBe('terceiros');
        expect(round((float) $pedido['subtotal'], 2))->toBe($subtotalEsperado);
        $item = $pdo->query('SELECT * FROM pedido_itens WHERE pedido_id = ' . $ctx['pedidoId'])->fetch();
        expect((int) $item['quantidade'])->toBe(2);
        expect((int) $item['turma_id'])->toBe(E2E_TURMA_ID);
        $ctx['itemId'] = (int) $item['id'];
        $ctx['codigo'] = (string) $pedido['codigo'];
        echo "      pedido #{$ctx['pedidoId']} ({$ctx['codigo']})\n";
    });

    // ---------------------------------------------------------------
    describe('3a. Validação — participante com CPF inválido');

    it('CPF inválido é recusado com mensagem e o pedido não avança', function () use ($base, $pdo, &$ctx, $part1) {
        chk_garantir($ctx['pedidoId'] > 0, 'sem pedido criado');
        $pagina = chk_http($base, 'GET', '/v2/checkout/participantes?pedido_id=' . $ctx['pedidoId'], null, $ctx['cookiesA']);
        expect($pagina['status'])->toBe(200);
        expect($pagina['corpo'])->toContain('participantes[1][nome]');   // 2 vagas → 2 blocos
        $token = chk_token($pagina['corpo']);

        // Dígito verificador errado de propósito. NÃO usar 123.456.789-00 e afins:
        // com APP_ENV != production e ALLOW_TEST_CPFS ligado (ambiente local),
        // Validator::cpf aceita uma lista de CPFs de teste conhecidos.
        $cpfInvalido = substr(chk_formatar_cpf(chk_cpf_valido('529982247')), 0, -1) . '6';   // 529.982.247-26
        $r = chk_http($base, 'POST', '/v2/checkout/participantes?pedido_id=' . $ctx['pedidoId'], array(
            '_token' => $token,
            'participantes' => array(
                array('pedido_item_id' => $ctx['itemId']) + $part1,
                array('pedido_item_id' => $ctx['itemId'], 'nome' => 'Participante CPF Ruim', 'cpf' => $cpfInvalido, 'email' => 'e2e-chk-ruim@teste.local', 'telefone' => ''),
            ),
        ), $ctx['cookiesA']);
        expect($r['status'])->toBe(302);
        expect(chk_caminho_location($r['location']))->toBe('/v2/checkout/participantes?pedido_id=' . $ctx['pedidoId']);

        $volta = chk_http($base, 'GET', '/v2/checkout/participantes?pedido_id=' . $ctx['pedidoId'], null, $ctx['cookiesA']);
        expect($volta['status'])->toBe(200);
        // O tema caderno reescreve a mensagem do controller ("#2" → "2") e a
        // repete junto do campo, que fica marcado com aria-invalid.
        expect($volta['corpo'])->toContain('O CPF do participante 2 é inválido.');
        expect($volta['corpo'])->toContain('id="cp-cpf-1-erro"');

        expect((int) $pdo->query('SELECT COUNT(*) FROM participantes_pedido WHERE pedido_id = ' . $ctx['pedidoId'])->fetchColumn())->toBe(0);
        expect((int) $pdo->query('SELECT COUNT(*) FROM inscricoes WHERE pedido_id = ' . $ctx['pedidoId'])->fetchColumn())->toBe(0);
        expect($pdo->query('SELECT status FROM pedidos WHERE id = ' . $ctx['pedidoId'])->fetchColumn())->toBe('rascunho');
    });

    // ---------------------------------------------------------------
    describe('2 (cont.). Participantes, resumo e cupom');

    it('participantes válidos são salvos e o pedido segue para o resumo', function () use ($base, $pdo, &$ctx, $part1, $part2) {
        chk_garantir($ctx['pedidoId'] > 0, 'sem pedido criado');
        $pagina = chk_http($base, 'GET', '/v2/checkout/participantes?pedido_id=' . $ctx['pedidoId'], null, $ctx['cookiesA']);
        $token = chk_token($pagina['corpo']);
        chk_garantir(preg_match('/name="participantes\[0\]\[pedido_item_id\]" value="(\d+)"/', $pagina['corpo'], $m) === 1, 'campo pedido_item_id ausente');
        expect((int) $m[1])->toBe($ctx['itemId']);

        $r = chk_http($base, 'POST', '/v2/checkout/participantes?pedido_id=' . $ctx['pedidoId'], array(
            '_token' => $token,
            'participantes' => array(
                array('pedido_item_id' => $ctx['itemId']) + $part1,
                array('pedido_item_id' => $ctx['itemId']) + $part2,
            ),
        ), $ctx['cookiesA']);
        expect($r['status'])->toBe(302);
        expect(chk_caminho_location($r['location']))->toBe('/v2/checkout/resumo?pedido_id=' . $ctx['pedidoId']);

        $linhas = $pdo->query('SELECT nome, cpf, email, telefone, ordem, pedido_item_id FROM participantes_pedido WHERE pedido_id = ' . $ctx['pedidoId'] . ' ORDER BY ordem')->fetchAll();
        expect(count($linhas))->toBe(2);
        foreach (array($part1, $part2) as $i => $esperado) {
            expect($linhas[$i]['nome'])->toBe($esperado['nome']);
            expect(chk_so_digitos($linhas[$i]['cpf']))->toBe(chk_so_digitos($esperado['cpf']));
            expect($linhas[$i]['email'])->toBe($esperado['email']);
            expect((int) $linhas[$i]['pedido_item_id'])->toBe($ctx['itemId']);
        }
        expect($pdo->query('SELECT status FROM pedidos WHERE id = ' . $ctx['pedidoId'])->fetchColumn())->toBe('aguardando_pagamento');
        expect((int) $pdo->query('SELECT COUNT(*) FROM inscricoes WHERE pedido_id = ' . $ctx['pedidoId'])->fetchColumn())->toBe(2);
    });

    it('resumo mostra os 2 participantes e o total de 2 vagas', function () use ($base, &$ctx, $part1, $part2, $subtotalEsperado, $dinheiro) {
        $r = chk_http($base, 'GET', '/v2/checkout/resumo?pedido_id=' . $ctx['pedidoId'], null, $ctx['cookiesA']);
        expect($r['status'])->toBe(200);
        expect($r['corpo'])->toContain($part1['nome']);
        expect($r['corpo'])->toContain($part2['nome']);
        expect($r['corpo'])->toContain($dinheiro($subtotalEsperado));
        expect($r['corpo'])->toContain('name="cupom_codigo"');
    });

    // ---------------------------------------------------------------
    describe('3b. Validação — cupom inexistente');

    it('cupom inexistente é recusado com mensagem e não altera o total', function () use ($base, $pdo, &$ctx, $subtotalEsperado) {
        $pagina = chk_http($base, 'GET', '/v2/checkout/resumo?pedido_id=' . $ctx['pedidoId'], null, $ctx['cookiesA']);
        $r = chk_http($base, 'POST', '/v2/checkout/cupom', array(
            '_token' => chk_token($pagina['corpo']),
            'pedido_id' => $ctx['pedidoId'],
            'cupom_codigo' => E2E_CUPOM_PREFIXO . 'NAO-EXISTE',
        ), $ctx['cookiesA']);
        expect($r['status'])->toBe(302);
        expect(chk_caminho_location($r['location']))->toBe('/v2/checkout/resumo?pedido_id=' . $ctx['pedidoId']);

        $volta = chk_http($base, 'GET', '/v2/checkout/resumo?pedido_id=' . $ctx['pedidoId'], null, $ctx['cookiesA']);
        expect($volta['corpo'])->toContain('Cupom não encontrado.');

        $pedido = $pdo->query('SELECT total, desconto_total, cupom_codigo FROM pedidos WHERE id = ' . $ctx['pedidoId'])->fetch();
        expect(round((float) $pedido['total'], 2))->toBe($subtotalEsperado);
        expect(round((float) $pedido['desconto_total'], 2))->toBe(0.0);
        expect((int) $pdo->query('SELECT COUNT(*) FROM pedidos_cupons WHERE pedido_id = ' . $ctx['pedidoId'])->fetchColumn())->toBe(0);
    });

    describe('2 (cont.). Cupom válido, pagamento e comprovante');

    it('cupom válido reduz o total pelo desconto esperado', function () use ($base, $pdo, &$ctx, $cupomCodigo, $descontoEsperado, $totalEsperado, $dinheiro) {
        $pagina = chk_http($base, 'GET', '/v2/checkout/resumo?pedido_id=' . $ctx['pedidoId'], null, $ctx['cookiesA']);
        $r = chk_http($base, 'POST', '/v2/checkout/cupom', array(
            '_token' => chk_token($pagina['corpo']),
            'pedido_id' => $ctx['pedidoId'],
            'cupom_codigo' => strtolower($cupomCodigo),     // o código é normalizado
        ), $ctx['cookiesA']);
        expect($r['status'])->toBe(302);

        $volta = chk_http($base, 'GET', '/v2/checkout/resumo?pedido_id=' . $ctx['pedidoId'], null, $ctx['cookiesA']);
        expect($volta['status'])->toBe(200);
        expect($volta['corpo'])->toContain('Cupom aplicado. O novo total já está no resumo.');
        expect($volta['corpo'])->toContain($cupomCodigo);
        expect($volta['corpo'])->toContain($dinheiro($totalEsperado));
        expect($volta['corpo'])->toContain($dinheiro($descontoEsperado));

        $pedido = $pdo->query('SELECT total, desconto_total, cupom_codigo FROM pedidos WHERE id = ' . $ctx['pedidoId'])->fetch();
        expect(round((float) $pedido['desconto_total'], 2))->toBe($descontoEsperado);
        expect(round((float) $pedido['total'], 2))->toBe($totalEsperado);
        expect(strtoupper((string) $pedido['cupom_codigo']))->toBe($cupomCodigo);
        $pc = $pdo->query('SELECT cupom_codigo, valor_desconto, status FROM pedidos_cupons WHERE pedido_id = ' . $ctx['pedidoId'])->fetch();
        expect($pc['status'])->toBe('aplicado');
        expect(round((float) $pc['valor_desconto'], 2))->toBe($descontoEsperado);
        expect((int) $pdo->query('SELECT COUNT(*) FROM cupons_usos WHERE pedido_id = ' . $ctx['pedidoId'])->fetchColumn())->toBe(1);
    });

    it('pagamento oferece PIX com link para o comprovante V2', function () use ($base, &$ctx, $totalEsperado, $dinheiro) {
        $r = chk_http($base, 'GET', '/v2/checkout/pagamento?pedido_id=' . $ctx['pedidoId'], null, $ctx['cookiesA']);
        expect($r['status'])->toBe(200);
        expect($r['corpo'])->toContain('/v2/checkout/comprovante?pedido_id=' . $ctx['pedidoId']);
        expect($r['corpo'])->toContain('Pagar com PIX');
        expect($r['corpo'])->toContain($dinheiro($totalEsperado));
    });

    it('envia comprovante PIX (PNG) e chega à tela de enviado', function () use ($base, &$ctx, $totalEsperado, $dinheiro) {
        $pagina = chk_http($base, 'GET', '/v2/checkout/comprovante?pedido_id=' . $ctx['pedidoId'], null, $ctx['cookiesA']);
        expect($pagina['status'])->toBe(200);
        expect($pagina['corpo'])->toContain('name="comprovante"');
        chk_garantir(preg_match('/name="valor_informado" value="([^"]*)"/', $pagina['corpo'], $mv) === 1, 'campo valor_informado ausente');

        // PNG 1x1 válido (assinatura + IHDR/IDAT/IEND), gerado sem GD.
        $png = tempnam(sys_get_temp_dir(), 'chk_png_') . '.png';
        file_put_contents($png, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));
        try {
            $r = chk_http($base, 'POST', '/v2/checkout/comprovante/enviar?pedido_id=' . $ctx['pedidoId'], array(
                '_token' => chk_token($pagina['corpo']),
                'pedido_id' => (string) $ctx['pedidoId'],
                'valor_informado' => $mv[1],
                'comprovante' => new CURLFile($png, 'image/png', 'comprovante-e2e.png'),
            ), $ctx['cookiesA'], true);
        } finally {
            @unlink($png);
        }
        expect($r['status'])->toBe(302);
        expect(chk_caminho_location($r['location']))->toBe('/v2/checkout/comprovante/enviado?pedido_id=' . $ctx['pedidoId']);

        $enviado = chk_http($base, 'GET', '/v2/checkout/comprovante/enviado?pedido_id=' . $ctx['pedidoId'], null, $ctx['cookiesA']);
        expect($enviado['status'])->toBe(200);
        expect($enviado['corpo'])->toContain('Em análise');
        expect($enviado['corpo'])->toContain($ctx['codigo']);
        expect($enviado['corpo'])->toContain($dinheiro($totalEsperado));
    });

    it('no banco: pedido em "comprovante_enviado" com comprovante PIX registrado e arquivo salvo', function () use ($pdo, &$ctx, $totalEsperado, $descontoEsperado) {
        $pedido = $pdo->query('SELECT status, total, desconto_total, comprador_usuario_id FROM pedidos WHERE id = ' . $ctx['pedidoId'])->fetch();
        expect($pedido['status'])->toBe('comprovante_enviado');
        expect((int) $pedido['comprador_usuario_id'])->toBe(9001);
        expect(round((float) $pedido['total'], 2))->toBe($totalEsperado);
        expect(round((float) $pedido['desconto_total'], 2))->toBe($descontoEsperado);

        $comp = $pdo->query('SELECT * FROM comprovantes_pix WHERE pedido_id = ' . $ctx['pedidoId'] . ' AND deleted_at IS NULL')->fetchAll();
        expect(count($comp))->toBe(1);
        expect((int) $comp[0]['is_atual'])->toBe(1);
        expect((int) $comp[0]['usuario_id'])->toBe(9001);
        expect($comp[0]['arquivo_mime_type'])->toBe('image/png');
        expect(round((float) $comp[0]['valor_informado'], 2))->toBe($totalEsperado);
        expect(in_array($comp[0]['status'], array('pendente', 'em_analise'), true))->toBeTrue();

        $caminho = ltrim((string) $comp[0]['arquivo_caminho'], '/');
        $existe = is_file(BASE_PATH . '/storage/private_uploads/' . $caminho) || is_file(BASE_PATH . '/' . $caminho);
        chk_garantir($existe, "arquivo do comprovante não encontrado: {$caminho}");
        chk_garantir(strpos($caminho, 'public_html') === false, 'comprovante gravado na área pública');

        $hist = $pdo->query("SELECT COUNT(*) FROM status_pedidos_historico WHERE pedido_id = {$ctx['pedidoId']} AND status_novo = 'comprovante_enviado'")->fetchColumn();
        expect((int) $hist)->toBe(1);
    });

    // ===============================================================
    describe('4. Segurança — outro aluno não acessa o pedido');

    it('aluno B não vê resumo, participantes, pagamento, comprovante nem a tela de enviado', function () use ($base, $usuarioB, $senha, &$ctx, $part1, $part2) {
        chk_garantir($ctx['pedidoId'] > 0, 'sem pedido criado');
        $cookiesB = chk_login($base, $usuarioB, $senha);
        chk_garantir($cookiesB !== null, "login de {$usuarioB} falhou");
        $ctx['cookiesB'] = $cookiesB;

        foreach (array('resumo', 'participantes', 'pagamento', 'comprovante', 'comprovante/enviado') as $etapa) {
            $r = chk_http($base, 'GET', '/v2/checkout/' . $etapa . '?pedido_id=' . $ctx['pedidoId'], null, $cookiesB);
            chk_garantir($r['status'] === 404, "{$etapa}: esperava 404, recebeu {$r['status']}");
            foreach (array($part1['nome'], $part2['nome'], $ctx['codigo'], chk_so_digitos($part1['cpf'])) as $dado) {
                chk_garantir(strpos($r['corpo'], $dado) === false, "{$etapa}: corpo expôs '{$dado}'");
            }
        }
        expect(true)->toBeTrue();
    });

    it('aluno B não consegue enviar comprovante nem aplicar cupom no pedido de A', function () use ($base, $pdo, &$ctx, $cupomCodigo) {
        chk_garantir(!empty($ctx['cookiesB']), 'sem sessão do aluno B');
        $paginaB = chk_http($base, 'GET', '/v2/aluno/', null, $ctx['cookiesB']);
        $tokenB = chk_token($paginaB['corpo']);
        if ($tokenB === null) {
            $paginaB = chk_http($base, 'GET', '/v2/checkout/inscricao?curso_id=' . E2E_CURSO_ID . '&turma_id=' . E2E_TURMA_ID, null, $ctx['cookiesB']);
            $tokenB = chk_token($paginaB['corpo']);
        }
        expect($tokenB)->notToBeNull();

        $png = tempnam(sys_get_temp_dir(), 'chk_png_') . '.png';
        file_put_contents($png, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));
        try {
            $r = chk_http($base, 'POST', '/v2/checkout/comprovante/enviar', array(
                '_token' => $tokenB,
                'pedido_id' => (string) $ctx['pedidoId'],
                'motivo_reenvio' => 'tentativa de invasão',
                'comprovante' => new CURLFile($png, 'image/png', 'invasor.png'),
            ), $ctx['cookiesB'], true);
        } finally {
            @unlink($png);
        }
        expect($r['status'])->toBe(302);
        expect(chk_caminho_location($r['location']))->toBe('/v2/aluno/?aba=pedidos');
        expect((int) $pdo->query('SELECT COUNT(*) FROM comprovantes_pix WHERE pedido_id = ' . $ctx['pedidoId'])->fetchColumn())->toBe(1);

        $antes = $pdo->query('SELECT total FROM pedidos WHERE id = ' . $ctx['pedidoId'])->fetchColumn();
        chk_http($base, 'POST', '/v2/checkout/cupom', array(
            '_token' => $tokenB, 'pedido_id' => $ctx['pedidoId'], 'cupom_codigo' => $cupomCodigo,
        ), $ctx['cookiesB']);
        expect($pdo->query('SELECT total FROM pedidos WHERE id = ' . $ctx['pedidoId'])->fetchColumn())->toBe($antes);
        expect((int) $pdo->query('SELECT COUNT(*) FROM cupons_usos WHERE pedido_id = ' . $ctx['pedidoId'])->fetchColumn())->toBe(1);
    });

    $codigoSaida = testes_resumo();
} finally {
    try {
        $removidos = chk_limpar($pdo);
        $GLOBALS['__chk_limpo'] = true;
        $restantes = (int) $pdo->query('SELECT COUNT(*) FROM pedido_itens WHERE curso_evento_id = ' . E2E_CURSO_ID)->fetchColumn()
            + (int) $pdo->query("SELECT COUNT(*) FROM cupons WHERE codigo LIKE '" . E2E_CUPOM_PREFIXO . "%'")->fetchColumn()
            + (int) $pdo->query('SELECT COUNT(*) FROM cursos_eventos WHERE id = ' . E2E_CURSO_ID)->fetchColumn();
        echo "Limpeza: {$removidos} pedido(s) e dados de teste apagados" . ($restantes > 0 ? " — ATENÇÃO: {$restantes} linha(s) restante(s)" : '') . ".\n";
        foreach (array('cookiesA', 'cookiesB') as $c) {
            if (!empty($ctx[$c]) && is_file($ctx[$c])) {
                @unlink($ctx[$c]);
            }
        }
    } catch (Throwable $e) {
        fwrite(STDERR, "FALHA NA LIMPEZA: " . $e->getMessage() . "\n");
        $codigoSaida = 1;
    }
}

exit($codigoSaida);
