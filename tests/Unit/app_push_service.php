<?php

/**
 * PushService — FCM HTTP v1 sem biblioteca, e isolamento de falha.
 *
 * O QUE ESTE TESTE PROTEGE
 *   - o JWT da conta de serviço (RS256 com openssl_sign) tem cabeçalho e claims
 *     que o Google aceita e a assinatura confere com a chave pública;
 *   - a mensagem FCM é `data` com valores string e as chaves do contrato;
 *   - toda notificação é gravada ANTES do envio;
 *   - token morto (UNREGISTERED) desativa o aparelho;
 *   - FCM fora do ar, transporte que lança exceção, OAuth recusado ou banco sem
 *     a tabela NUNCA propagam exceção para quem chamou (a ação de origem —
 *     aprovar pedido, corrigir avaliação — não pode falhar por causa do push);
 *   - com FCM desligado nada é enviado e a notificação fica pendente;
 *   - o reenvio (cron) entrega as pendentes.
 *
 * Banco: usa app_notificacoes/app_dispositivos (migração 082) em transação
 * revertida; sem a tabela, só a parte de JWT/mensagem roda.
 *   DB_HOST=127.0.0.1 DB_PORT=33061 DB_DATABASE=desbloqueia_app_teste DB_USERNAME=root DB_PASSWORD=... php tests/Unit/app_push_service.php
 */

require_once __DIR__ . '/_bootstrap.php';

use App\Services\PushService;

function b64url_decode($v)
{
    return base64_decode(strtr($v, '-_', '+/') . str_repeat('=', (4 - strlen($v) % 4) % 4));
}

// No Windows o OpenSSL do PHP precisa do openssl.cnf explícito para gerar chave.
$opcoesChave = array('private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA);
$cnf = dirname(PHP_BINARY) . '/extras/ssl/openssl.cnf';
if (is_file($cnf)) {
    $opcoesChave['config'] = $cnf;
}
$chave = openssl_pkey_new($opcoesChave);
if ($chave === false) {
    echo "SKIP: OpenSSL do PHP não gera chave RSA neste ambiente.\n";
    exit(0);
}
openssl_pkey_export($chave, $privadaPem, null, $opcoesChave);
$publicaPem = openssl_pkey_get_details($chave)['key'];
$contaServico = array(
    'type' => 'service_account',
    'project_id' => 'projeto-teste',
    'private_key_id' => 'kid-123',
    'private_key' => $privadaPem,
    'client_email' => 'fcm@projeto-teste.iam.gserviceaccount.com',
    'token_uri' => 'https://oauth2.googleapis.com/token',
);

describe('JWT da conta de serviço');

it('cabeçalho RS256 com kid, claims do fluxo jwt-bearer e assinatura válida', function () use ($contaServico, $publicaPem) {
    $jwt = PushService::montarJwt($contaServico, 1790000000);
    $partes = explode('.', $jwt);
    expect(count($partes))->toBe(3);
    $cabecalho = json_decode(b64url_decode($partes[0]), true);
    $claims = json_decode(b64url_decode($partes[1]), true);
    expect($cabecalho)->toEqual(array('alg' => 'RS256', 'typ' => 'JWT', 'kid' => 'kid-123'));
    expect($claims['iss'])->toBe('fcm@projeto-teste.iam.gserviceaccount.com');
    expect($claims['scope'])->toBe('https://www.googleapis.com/auth/firebase.messaging');
    expect($claims['aud'])->toBe('https://oauth2.googleapis.com/token');
    expect($claims['exp'] - $claims['iat'])->toBe(3600);
    $ok = openssl_verify($partes[0] . '.' . $partes[1], b64url_decode($partes[2]), $publicaPem, OPENSSL_ALGO_SHA256);
    expect($ok)->toBe(1);
    expect(strpos($partes[2], '='))->toBeFalse();
});

it('chave privada inválida lança no montarJwt (e o serviço captura)', function () use ($contaServico) {
    $ruim = $contaServico;
    $ruim['private_key'] = 'nao-e-uma-chave';
    $lancou = false;
    try {
        PushService::montarJwt($ruim, time());
    } catch (\RuntimeException $e) {
        $lancou = true;
    }
    expect($lancou)->toBeTrue();
});

it('mensagem FCM v1: data com strings, prioridade alta', function () {
    $svc = new PushService(null, function () { return array('status' => 200, 'corpo' => '{}'); }, array('habilitado' => true));
    $m = $svc->montarMensagem('token-fcm', array('id' => 12, 'tipo' => 'pedido_aprovado', 'titulo' => 'Pagamento confirmado', 'corpo' => 'Seu pedido foi aprovado.', 'dados' => '{"pedido_id":50,"vazio":null}'));
    expect($m['message']['token'])->toBe('token-fcm');
    expect($m['message']['android']['priority'])->toBe('HIGH');
    expect($m['message']['data'])->toEqual(array('pedido_id' => '50', 'tipo' => 'pedido_aprovado', 'titulo' => 'Pagamento confirmado', 'corpo' => 'Seu pedido foi aprovado.', 'notificacao_id' => '12'));
    foreach ($m['message']['data'] as $valor) {
        expect(is_string($valor))->toBeTrue();
    }
});

// -------------------------------------------------------------------
// Parte com banco
// -------------------------------------------------------------------
$pdo = null;
try {
    $pdo = testes_conectar_banco();
    $pdo->query('SELECT 1 FROM app_notificacoes LIMIT 1');
} catch (Throwable $e) {
    echo "\n  SKIP parte com banco: " . get_class($e) . "\n";
    exit(testes_resumo());
}

$pdo->beginTransaction();
register_shutdown_function(function () use ($pdo) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
});

$usuarioId = (int) $pdo->query('SELECT id FROM usuarios WHERE deleted_at IS NULL ORDER BY id LIMIT 1')->fetchColumn();
$cacheDir = sys_get_temp_dir() . '/push-teste-' . bin2hex(random_bytes(4));
@mkdir($cacheDir);
$contaArquivo = $cacheDir . '/conta.json';
file_put_contents($contaArquivo, json_encode($contaServico));
$config = array('habilitado' => true, 'projeto' => 'projeto-teste', 'conta_servico' => $contaArquivo, 'cache_dir' => $cacheDir);

function dispositivo(PDO $pdo, $usuarioId, $deviceId, $token)
{
    $pdo->prepare('DELETE FROM app_dispositivos WHERE device_id = :d')->execute(array('d' => $deviceId));
    $pdo->prepare('INSERT INTO app_dispositivos (usuario_id, device_id, fcm_token, plataforma, ativo, created_at, updated_at) VALUES (:u, :d, :t, "android", 1, NOW(), NOW())')
        ->execute(array('u' => $usuarioId, 'd' => $deviceId, 't' => $token));
}

function notificacao(PDO $pdo, $id)
{
    $st = $pdo->prepare('SELECT * FROM app_notificacoes WHERE id = :id');
    $st->execute(array('id' => $id));
    return $st->fetch(PDO::FETCH_ASSOC);
}

$pdo->prepare('UPDATE app_dispositivos SET ativo = 0 WHERE usuario_id = :u')->execute(array('u' => $usuarioId));

describe('Envio e isolamento de falha');

it('envio feliz: OAuth uma vez (cache), FCM 200 → enviado', function () use ($pdo, $usuarioId, $config, $cacheDir) {
    dispositivo($pdo, $usuarioId, 'push-unit-1', 'token-bom');
    $chamadas = array();
    $transporte = function ($metodo, $url, $cab, $corpo) use (&$chamadas) {
        $chamadas[] = $url;
        if (strpos($url, 'oauth2') !== false) {
            parse_str($corpo, $form);
            if ($form['grant_type'] !== 'urn:ietf:params:oauth:grant-type:jwt-bearer' || substr_count($form['assertion'], '.') !== 2) {
                return array('status' => 400, 'corpo' => '{"error":"invalid_grant"}');
            }
            return array('status' => 200, 'corpo' => '{"access_token":"ya29.teste","expires_in":3599}');
        }
        $msg = json_decode($corpo, true);
        if (!in_array('Authorization: Bearer ya29.teste', $cab, true) || $msg['message']['token'] !== 'token-bom') {
            return array('status' => 401, 'corpo' => '{}');
        }
        return array('status' => 200, 'corpo' => '{"name":"projects/projeto-teste/messages/1"}');
    };
    $svc = new PushService($pdo, $transporte, $config);
    $id = $svc->notificar($usuarioId, 'pedido_aprovado', 'Pagamento confirmado', 'Corpo', array('pedido_id' => 50));
    expect(notificacao($pdo, $id)['envio_status'])->toBe('enviado');
    $id2 = $svc->notificar($usuarioId, 'pedido_aprovado', 'Pagamento confirmado', 'Corpo', array('pedido_id' => 51));
    expect(notificacao($pdo, $id2)['envio_status'])->toBe('enviado');
    $oauth = array_filter($chamadas, function ($u) { return strpos($u, 'oauth2') !== false; });
    expect(count($oauth))->toBe(1);
    expect(is_file($cacheDir . '/fcm_access_token.json'))->toBeTrue();
});

it('UNREGISTERED desativa o aparelho e a notificação fica como falhou', function () use ($pdo, $usuarioId, $config) {
    $pdo->prepare('UPDATE app_dispositivos SET ativo = 0 WHERE usuario_id = :u')->execute(array('u' => $usuarioId));
    dispositivo($pdo, $usuarioId, 'push-unit-2', 'token-morto');
    $svc = new PushService($pdo, function ($m, $url) {
        return array('status' => 404, 'corpo' => '{"error":{"code":404,"status":"NOT_FOUND","details":[{"@type":"type.googleapis.com/google.firebase.fcm.v1.FcmError","errorCode":"UNREGISTERED"}]}}');
    }, $config);
    $id = $svc->notificar($usuarioId, 'avaliacao_corrigida', 'Avaliação corrigida', 'Corpo');
    $n = notificacao($pdo, $id);
    expect($n['envio_status'])->toBe('falhou');
    expect($n['ultimo_erro'])->toBe('UNREGISTERED');
    $ativo = $pdo->query("SELECT ativo FROM app_dispositivos WHERE device_id = 'push-unit-2'")->fetchColumn();
    expect((int) $ativo)->toBe(0);
});

it('transporte que lança exceção não propaga; notificação gravada', function () use ($pdo, $usuarioId, $config) {
    dispositivo($pdo, $usuarioId, 'push-unit-3', 'token-qualquer');
    $svc = new PushService($pdo, function () {
        throw new \RuntimeException('rede caiu');
    }, $config);
    $id = $svc->notificar($usuarioId, 'quiz_corrigido', 'Quiz corrigido', 'Corpo');
    expect($id > 0)->toBeTrue();
    expect(notificacao($pdo, $id)['envio_status'])->toBe('falhou');
});

it('FCM 500 / OAuth recusado → falhou, sem exceção', function () use ($pdo, $usuarioId, $config, $cacheDir) {
    @unlink($cacheDir . '/fcm_access_token.json');
    $svc = new PushService($pdo, function ($m, $url) {
        return array('status' => 500, 'corpo' => 'erro', 'erro' => null);
    }, $config);
    $id = $svc->notificar($usuarioId, 'certificado_emitido', 'Certificado emitido', 'Corpo');
    $n = notificacao($pdo, $id);
    expect($n['envio_status'])->toBe('falhou');
    expect($n['ultimo_erro'])->toBe('oauth_indisponivel');
});

it('sem aparelho ativo → sem_dispositivo', function () use ($pdo, $usuarioId, $config) {
    $pdo->prepare('UPDATE app_dispositivos SET ativo = 0 WHERE usuario_id = :u')->execute(array('u' => $usuarioId));
    $svc = new PushService($pdo, function () { return array('status' => 200, 'corpo' => '{}'); }, $config);
    $id = $svc->notificar($usuarioId, 'conteudo_novo', 'Conteúdo novo', 'Corpo');
    expect(notificacao($pdo, $id)['envio_status'])->toBe('sem_dispositivo');
});

it('FCM desligado: grava pendente e não chama a rede; cron também não envia', function () use ($pdo, $usuarioId) {
    $chamou = false;
    $svc = new PushService($pdo, function () use (&$chamou) { $chamou = true; return array('status' => 200, 'corpo' => '{}'); }, array('habilitado' => false));
    $id = $svc->notificar($usuarioId, 'pedido_aprovado', 'T', 'C');
    expect(notificacao($pdo, $id)['envio_status'])->toBe('pendente');
    expect($svc->reenviarPendentes()['processadas'])->toBe(0);
    expect($chamou)->toBeFalse();
});

it('banco sem a tabela: notificar devolve null e não lança', function () use ($usuarioId) {
    $sqlite = new PDO('sqlite::memory:');
    $sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $svc = new PushService($sqlite, null, array('habilitado' => true));
    expect($svc->notificar($usuarioId, 'pedido_aprovado', 'T', 'C'))->toBeNull();
    expect($svc->enfileirar(array($usuarioId => array()), 'conteudo_novo', 'T', 'C'))->toBe(0);
});

it('enfileirar grava em lote, com dados por destinatário, e o cron envia', function () use ($pdo, $usuarioId, $config) {
    $pdo->prepare('UPDATE app_dispositivos SET ativo = 0 WHERE usuario_id = :u')->execute(array('u' => $usuarioId));
    dispositivo($pdo, $usuarioId, 'push-unit-4', 'token-bom');
    $antes = (int) $pdo->query('SELECT COALESCE(MAX(id), 0) FROM app_notificacoes')->fetchColumn();
    $svc = new PushService($pdo, function ($m, $url) {
        if (strpos($url, 'oauth2') !== false) {
            return array('status' => 200, 'corpo' => '{"access_token":"ya29.teste","expires_in":3599}');
        }
        return array('status' => 200, 'corpo' => '{}');
    }, $config);
    $total = $svc->enfileirar(array($usuarioId => array('inscricao_id' => 77)), 'conteudo_novo', 'Conteúdo novo', 'Novo conteúdo', array('item_id' => 5));
    expect($total)->toBe(1);
    $linha = $pdo->query('SELECT * FROM app_notificacoes WHERE id > ' . $antes . ' ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    expect($linha['envio_status'])->toBe('pendente');
    expect(json_decode($linha['dados'], true))->toEqual(array('item_id' => '5', 'inscricao_id' => '77'));
    $resumo = $svc->reenviarPendentes(500);
    expect($resumo['enviadas'])->toBeGreaterThanOrEqual(1);
    expect(notificacao($pdo, (int) $linha['id'])['envio_status'])->toBe('enviado');
});

$codigo = testes_resumo();
$pdo->rollBack();
array_map('unlink', glob($cacheDir . '/*'));
@rmdir($cacheDir);
exit($codigo);
