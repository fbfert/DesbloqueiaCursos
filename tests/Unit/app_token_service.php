<?php

/**
 * AppTokenService — tokens do app do aluno.
 *
 * O QUE ESTE TESTE PROTEGE
 *   - token opaco: o banco guarda só o SHA-256, nunca o token;
 *   - access de 1 h e refresh de 60 dias, vencimento decidido pelo PHP
 *     (App\Support\AppApi\Tempo) — independente do fuso do MySQL;
 *   - rotação: o refresh usado deixa de valer e os access antigos da família
 *     são revogados;
 *   - reuso de refresh já trocado (token copiado) revoga TUDO do dispositivo;
 *   - refresh apresentado por outro aparelho é recusado;
 *   - novo login no mesmo aparelho derruba a sessão anterior dele;
 *   - troca de senha revoga os outros aparelhos e mantém o atual.
 *
 * Banco: precisa da migração 082 (app_tokens). Roda em transação revertida.
 *   DB_HOST=127.0.0.1 DB_PORT=33061 DB_DATABASE=desbloqueia_app_teste DB_USERNAME=root DB_PASSWORD=... php tests/Unit/app_token_service.php
 */

require_once __DIR__ . '/_bootstrap.php';

use App\Services\AppTokenService;
use App\Support\AppApi\Tempo;

$pdo = testes_conectar_banco();
try {
    $pdo->query('SELECT 1 FROM app_tokens LIMIT 1');
} catch (Throwable $e) {
    echo "SKIP: tabela app_tokens ausente (aplique sql/082_app_mobile.sql).\n";
    exit(0);
}

$pdo->beginTransaction();
register_shutdown_function(function () use ($pdo) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    Tempo::fixar(null);
});

$usuarioId = (int) $pdo->query('SELECT id FROM usuarios WHERE deleted_at IS NULL ORDER BY id LIMIT 1')->fetchColumn();
if ($usuarioId <= 0) {
    echo "SKIP: nenhum usuário no banco.\n";
    exit(0);
}

$svc = new AppTokenService($pdo);
$agora = 1790000000; // instante fixo
Tempo::fixar($agora);
$dev = 'teste-unit-' . bin2hex(random_bytes(4));

function linha(PDO $pdo, $token)
{
    $st = $pdo->prepare('SELECT * FROM app_tokens WHERE token_hash = :h');
    $st->execute(array('h' => hash('sha256', $token)));
    return $st->fetch(PDO::FETCH_ASSOC);
}

describe('Emissão');

$par = $svc->emitirParaLogin($usuarioId, $dev, 'Aparelho de teste', '127.0.0.1', 'phpunit');

it('tokens opacos de 43 caracteres (32 bytes base64url)', function () use ($par) {
    expect(strlen($par['access_token']))->toBe(43);
    expect(preg_match('/^[A-Za-z0-9_-]+$/', $par['refresh_token']))->toBe(1);
    expect($par['access_token'] !== $par['refresh_token'])->toBeTrue();
});

it('banco guarda só o hash', function () use ($pdo, $par) {
    $st = $pdo->prepare('SELECT COUNT(*) FROM app_tokens WHERE token_hash IN (:a, :r)');
    $st->execute(array('a' => $par['access_token'], 'r' => $par['refresh_token']));
    expect((int) $st->fetchColumn())->toBe(0);
    expect(linha($pdo, $par['access_token'])['tipo'])->toBe('access');
    expect(linha($pdo, $par['refresh_token'])['tipo'])->toBe('refresh');
});

it('access vale 1 h e refresh 60 dias', function () use ($par, $agora) {
    expect($par['access_expira_em'])->toBe($agora + 3600);
    expect($par['refresh_expira_em'])->toBe($agora + 60 * 86400);
});

describe('Validação e vencimento');

it('access válido antes de vencer', function () use ($svc, $par) {
    $r = $svc->validarAccess($par['access_token']);
    expect($r['ok'])->toBeTrue();
});

it('access vencido → motivo expirado', function () use ($svc, $par, $agora) {
    Tempo::fixar($agora + 3601);
    $r = $svc->validarAccess($par['access_token']);
    Tempo::fixar($agora);
    expect($r['ok'])->toBeFalse();
    expect($r['motivo'])->toBe('expirado');
});

it('token desconhecido, vazio ou refresh usado como access → invalido', function () use ($svc, $par) {
    expect($svc->validarAccess('inexistente')['motivo'])->toBe('invalido');
    expect($svc->validarAccess('')['motivo'])->toBe('invalido');
    expect($svc->validarAccess($par['refresh_token'])['motivo'])->toBe('invalido');
});

it('refresh vencido → expirado', function () use ($pdo, $usuarioId, $agora) {
    $s = new AppTokenService($pdo);
    $d = 'teste-unit-exp-' . bin2hex(random_bytes(3));
    $p = $s->emitirParaLogin($usuarioId, $d, null, null, null);
    Tempo::fixar($agora + 61 * 86400);
    $r = $s->renovar($p['refresh_token'], $d, null, null);
    Tempo::fixar($agora);
    expect($r['motivo'])->toBe('expirado');
});

describe('Rotação e reuso');

$rotacao = array();

it('renovar devolve par novo e consome o refresh usado', function () use ($svc, $par, $dev, $pdo, &$rotacao) {
    $novo = $svc->renovar($par['refresh_token'], $dev, '127.0.0.1', 'phpunit');
    expect($novo['ok'])->toBeTrue();
    expect($novo['refresh_token'] !== $par['refresh_token'])->toBeTrue();
    $antigo = linha($pdo, $par['refresh_token']);
    expect($antigo['consumido_em'] !== null)->toBeTrue();
    expect((int) $antigo['substituido_por_id'])->toBe((int) linha($pdo, $novo['refresh_token'])['id']);
    expect(linha($pdo, $novo['refresh_token'])['familia'])->toBe($antigo['familia']);
    $rotacao = $novo;
});

it('access antigo da família é revogado; o novo vale', function () use ($svc, $par, &$rotacao) {
    expect($svc->validarAccess($par['access_token'])['motivo'])->toBe('revogado');
    expect($svc->validarAccess($rotacao['access_token'])['ok'])->toBeTrue();
});

it('reuso do refresh consumido → reuso e revoga TODOS os tokens do aparelho', function () use ($svc, $par, $dev, $pdo, &$rotacao) {
    $r = $svc->renovar($par['refresh_token'], $dev, '10.0.0.9', 'atacante');
    expect($r['ok'])->toBeFalse();
    expect($r['motivo'])->toBe('reuso');
    expect($svc->validarAccess($rotacao['access_token'])['motivo'])->toBe('revogado');
    $r2 = $svc->renovar($rotacao['refresh_token'], $dev, null, null);
    expect($r2['motivo'])->toBe('revogado'); // o refresh sucessor também caiu
    $st = $pdo->prepare('SELECT COUNT(*) FROM app_tokens WHERE device_id = :d AND revogado_em IS NULL');
    $st->execute(array('d' => $dev));
    expect((int) $st->fetchColumn())->toBe(0);
});

it('refresh de outro aparelho → dispositivo_divergente e família revogada', function () use ($pdo, $usuarioId) {
    $s = new AppTokenService($pdo);
    $d = 'teste-unit-a-' . bin2hex(random_bytes(3));
    $p = $s->emitirParaLogin($usuarioId, $d, null, null, null);
    $r = $s->renovar($p['refresh_token'], 'teste-unit-outro-aparelho', null, null);
    expect($r['motivo'])->toBe('dispositivo_divergente');
    expect($s->validarAccess($p['access_token'])['motivo'])->toBe('revogado');
});

describe('Janela de tolerância (resposta da renovação perdida)');

function novo_aparelho(PDO $pdo, $usuarioId, $agora)
{
    Tempo::fixar($agora);
    $s = new AppTokenService($pdo);
    $d = 'teste-unit-janela-' . bin2hex(random_bytes(3));
    $p = $s->emitirParaLogin($usuarioId, $d, null, null, null);
    $r = $s->renovar($p['refresh_token'], $d, null, null); // resposta "perdida"
    return array($s, $d, $p, $r);
}

it('dentro de 60 s, mesmo aparelho, par novo sem uso → 200 com outro par; o par perdido é revogado', function () use ($pdo, $usuarioId, $agora) {
    list($s, $d, $p, $perdido) = novo_aparelho($pdo, $usuarioId, $agora);
    Tempo::fixar($agora + 30);
    $r = $s->renovar($p['refresh_token'], $d, null, null);
    expect($r['ok'])->toBeTrue();
    expect($r['refresh_token'] !== $perdido['refresh_token'])->toBeTrue();
    expect($s->validarAccess($r['access_token'])['ok'])->toBeTrue();
    expect($s->validarAccess($perdido['access_token'])['motivo'])->toBe('revogado');
    expect($s->renovar($perdido['refresh_token'], $d, null, null)['ok'])->toBeFalse();
    expect(linha($pdo, $r['refresh_token'])['familia'])->toBe(linha($pdo, $p['refresh_token'])['familia']);
    Tempo::fixar($agora);
});

it('várias tentativas dentro da janela continuam funcionando enquanto o par não for usado', function () use ($pdo, $usuarioId, $agora) {
    list($s, $d, $p) = novo_aparelho($pdo, $usuarioId, $agora);
    Tempo::fixar($agora + 10);
    expect($s->renovar($p['refresh_token'], $d, null, null)['ok'])->toBeTrue();
    Tempo::fixar($agora + 20);
    $r = $s->renovar($p['refresh_token'], $d, null, null);
    expect($r['ok'])->toBeTrue();
    Tempo::fixar($agora + 21);
    expect($s->validarAccess($r['access_token'])['ok'])->toBeTrue();
    Tempo::fixar($agora);
});

it('depois de 60 s → reuso e revoga o aparelho', function () use ($pdo, $usuarioId, $agora) {
    list($s, $d, $p, $perdido) = novo_aparelho($pdo, $usuarioId, $agora);
    Tempo::fixar($agora + 61);
    expect($s->renovar($p['refresh_token'], $d, null, null)['motivo'])->toBe('reuso');
    expect($s->validarAccess($perdido['access_token'])['motivo'])->toBe('revogado');
    Tempo::fixar($agora);
});

it('access novo já usado → reuso e revoga o aparelho', function () use ($pdo, $usuarioId, $agora) {
    list($s, $d, $p, $novo) = novo_aparelho($pdo, $usuarioId, $agora);
    expect($s->validarAccess($novo['access_token'])['ok'])->toBeTrue();
    Tempo::fixar($agora + 5);
    expect($s->renovar($p['refresh_token'], $d, null, null)['motivo'])->toBe('reuso');
    expect($s->validarAccess($novo['access_token'])['motivo'])->toBe('revogado');
    Tempo::fixar($agora);
});

it('refresh novo já usado → reuso e revoga o aparelho', function () use ($pdo, $usuarioId, $agora) {
    list($s, $d, $p, $novo) = novo_aparelho($pdo, $usuarioId, $agora);
    Tempo::fixar($agora + 5);
    $seguinte = $s->renovar($novo['refresh_token'], $d, null, null);
    expect($seguinte['ok'])->toBeTrue();
    expect($s->renovar($p['refresh_token'], $d, null, null)['motivo'])->toBe('reuso');
    expect($s->validarAccess($seguinte['access_token'])['motivo'])->toBe('revogado');
    Tempo::fixar($agora);
});

it('outro device_id dentro da janela → recusado e família revogada', function () use ($pdo, $usuarioId, $agora) {
    list($s, $d, $p, $novo) = novo_aparelho($pdo, $usuarioId, $agora);
    Tempo::fixar($agora + 5);
    $r = $s->renovar($p['refresh_token'], 'teste-unit-aparelho-estranho', null, null);
    expect($r['ok'])->toBeFalse();
    expect($r['motivo'])->toBe('dispositivo_divergente');
    expect($s->validarAccess($novo['access_token'])['motivo'])->toBe('revogado');
    Tempo::fixar($agora);
});

describe('Login e troca de senha');

it('novo login no mesmo aparelho derruba a sessão anterior', function () use ($pdo, $usuarioId) {
    $s = new AppTokenService($pdo);
    $d = 'teste-unit-relogin-' . bin2hex(random_bytes(3));
    $p1 = $s->emitirParaLogin($usuarioId, $d, null, null, null);
    $p2 = $s->emitirParaLogin($usuarioId, $d, null, null, null);
    expect($s->validarAccess($p1['access_token'])['motivo'])->toBe('revogado');
    expect($s->validarAccess($p2['access_token'])['ok'])->toBeTrue();
});

it('revogar os outros aparelhos mantém o atual', function () use ($pdo, $usuarioId) {
    $s = new AppTokenService($pdo);
    $atual = 'teste-unit-atual-' . bin2hex(random_bytes(3));
    $outro = 'teste-unit-outro-' . bin2hex(random_bytes(3));
    $pa = $s->emitirParaLogin($usuarioId, $atual, null, null, null);
    $po = $s->emitirParaLogin($usuarioId, $outro, null, null, null);
    $s->revogarOutrosDispositivos($usuarioId, $atual);
    expect($s->validarAccess($pa['access_token'])['ok'])->toBeTrue();
    expect($s->validarAccess($po['access_token'])['motivo'])->toBe('revogado');
});

it('logout (revogar dispositivo) invalida access e refresh', function () use ($pdo, $usuarioId) {
    $s = new AppTokenService($pdo);
    $d = 'teste-unit-logout-' . bin2hex(random_bytes(3));
    $p = $s->emitirParaLogin($usuarioId, $d, null, null, null);
    $s->revogarDispositivo($d);
    expect($s->validarAccess($p['access_token'])['motivo'])->toBe('revogado');
    expect($s->renovar($p['refresh_token'], $d, null, null)['motivo'])->toBe('revogado');
});

$codigo = testes_resumo();
$pdo->rollBack();
Tempo::fixar(null);
exit($codigo);
