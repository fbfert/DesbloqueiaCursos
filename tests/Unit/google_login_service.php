<?php

/**
 * GoogleLoginService e UsuarioIdentidade — resolução da conta no login com Google.
 *
 * O QUE ESTE TESTE PROTEGE
 *   - conta já vinculada entra pelo sub, mesmo se o e-mail mudou no Google;
 *   - e-mail verificado de conta existente vincula automaticamente, com auditoria
 *     e e-mail de aviso; e-mail não verificado é recusado;
 *   - pessoa nova vira aluno sem senha e sem CPF, com consentimentos e perfil aluno;
 *   - conta inativa nunca entra; bloqueio por senha errada não impede;
 *   - um sub só pode estar vinculado a uma conta (UNIQUE).
 *
 * Banco: precisa da migração 083. Roda em transação revertida.
 *   DB_HOST=127.0.0.1 DB_PORT=33061 DB_DATABASE=desbloqueia_app_teste DB_USERNAME=root DB_PASSWORD=... php tests/Unit/google_login_service.php
 */

require_once __DIR__ . '/_bootstrap.php';

use App\Models\UsuarioIdentidade;
use App\Services\Google\GoogleLoginService;

$pdo = testes_conectar_banco();
try {
    $pdo->query('SELECT 1 FROM usuario_identidades LIMIT 1');
} catch (Throwable $e) {
    echo "SKIP: tabela usuario_identidades ausente (aplique sql/083_login_google.sql).\n";
    exit(0);
}

$pdo->beginTransaction();
register_shutdown_function(function () use ($pdo) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
});

$sufixo = bin2hex(random_bytes(4));
$svc = new GoogleLoginService();
$ident = new UsuarioIdentidade();

function gls_usuario(PDO $pdo, $email, array $extra = array())
{
    $dados = array_merge(array('nome' => 'CONTA EXISTENTE', 'status' => 'ativo', 'bloqueado_ate' => null, 'senha_hash' => password_hash('Local@12345', PASSWORD_DEFAULT)), $extra);
    $pdo->prepare('INSERT INTO usuarios (nome, email, cpf, senha_hash, status, bloqueado_ate, tentativas_login, created_at, updated_at)
                   VALUES (:nome, :email, :cpf, :senha, :status, :bloqueado, 5, NOW(), NOW())')
        ->execute(array('nome' => $dados['nome'], 'email' => $email, 'cpf' => testes_cpf_livre($pdo), 'senha' => $dados['senha_hash'], 'status' => $dados['status'], 'bloqueado' => $dados['bloqueado_ate']));
    return (int) $pdo->lastInsertId();
}

function gls_claims($sub, $email, array $extra = array())
{
    return array_merge(array('sub' => $sub, 'email' => $email, 'email_verified' => true, 'name' => 'Maria da Silva'), $extra);
}

function gls_contar(PDO $pdo, $sql, array $p)
{
    $st = $pdo->prepare($sql);
    $st->execute($p);
    return (int) $st->fetchColumn();
}

describe('Criação de conta');

$emailNovo = "nova.$sufixo@gmail.test";
$r = $svc->resolverUsuario(gls_claims("sub-novo-$sufixo", $emailNovo), 'site', '127.0.0.1', 'teste');

it('pessoa nova entra e a conta é marcada como nova', function () use ($r) {
    expect($r['ok'])->toBeTrue();
    expect($r['novo'])->toBeTrue();
    expect($r['vinculado_agora'])->toBeFalse();
});

it('conta nasce sem senha, sem CPF, pendente e de origem google', function () use ($pdo, $r) {
    $u = $pdo->query('SELECT * FROM usuarios WHERE id = ' . (int) $r['usuario']['id'])->fetch();
    expect($u['cpf'])->toBeNull();
    expect($u['senha_hash'])->toBeNull();
    expect($u['cadastro_status'])->toBe('pendente');
    expect($u['cadastro_origem'])->toBe('google');
    expect($u['nome'])->toBe('MARIA DA SILVA');
});

it('tem perfil aluno, consentimentos e auditoria', function () use ($pdo, $r) {
    $id = (int) $r['usuario']['id'];
    expect(gls_contar($pdo, "SELECT COUNT(*) FROM usuario_perfis up JOIN perfis p ON p.id = up.perfil_id WHERE up.usuario_id = :id AND p.slug = 'aluno'", array('id' => $id)))->toBe(1);
    expect(gls_contar($pdo, "SELECT COUNT(*) FROM usuario_consentimentos WHERE usuario_id = :id AND consentido = 1 AND tipo IN ('termos_uso','politica_privacidade')", array('id' => $id)))->toBe(2);
    expect(gls_contar($pdo, "SELECT COUNT(*) FROM auditoria_logs WHERE entidade_tipo = 'usuario' AND entidade_id = :id AND acao = 'autenticacao.google_conta_criada'", array('id' => $id)))->toBe(1);
});

it('vínculo gravado pelo sub', function () use ($ident, $r, $sufixo) {
    $v = $ident->findByProvedorSub('google', "sub-novo-$sufixo");
    expect((int) $v['usuario_id'])->toBe((int) $r['usuario']['id']);
});

it('o login registra o acesso como login_google', function () use ($pdo, $r) {
    expect(gls_contar($pdo, "SELECT COUNT(*) FROM acessos_logs WHERE usuario_id = :id AND evento = 'login_google' AND resultado = 'success'", array('id' => (int) $r['usuario']['id'])))->toBe(1);
});

it('duas contas novas convivem sem CPF', function () use ($svc, $sufixo) {
    $r2 = $svc->resolverUsuario(gls_claims("sub-novo2-$sufixo", "nova2.$sufixo@gmail.test"), 'app', '127.0.0.1', 'teste');
    expect($r2['ok'])->toBeTrue();
    expect($r2['novo'])->toBeTrue();
});

describe('Conta já vinculada');

it('entra de novo pelo sub, sem criar outra conta', function () use ($svc, $sufixo, $emailNovo, $r) {
    $de_novo = $svc->resolverUsuario(gls_claims("sub-novo-$sufixo", $emailNovo), 'site', '127.0.0.1', 'teste');
    expect($de_novo['novo'])->toBeFalse();
    expect((int) $de_novo['usuario']['id'])->toBe((int) $r['usuario']['id']);
});

it('e-mail trocado no Google continua na mesma conta', function () use ($svc, $sufixo, $r) {
    $trocado = $svc->resolverUsuario(gls_claims("sub-novo-$sufixo", "outro.$sufixo@gmail.test", array('email_verified' => false)), 'site', '127.0.0.1', 'teste');
    expect($trocado['ok'])->toBeTrue();
    expect((int) $trocado['usuario']['id'])->toBe((int) $r['usuario']['id']);
});

it('o mesmo sub não pode ser vinculado a outra conta', function () use ($ident, $pdo, $sufixo) {
    $outro = gls_usuario($pdo, "dono2.$sufixo@teste.local");
    $erro = null;
    try {
        $ident->vincular($outro, 'google', "sub-novo-$sufixo", 'x@y');
    } catch (PDOException $e) {
        $erro = $e->getCode();
    }
    expect((string) $erro)->toBe('23000');
});

describe('Vínculo automático por e-mail verificado');

$emailExistente = "existente.$sufixo@teste.local";
$idExistente = gls_usuario($pdo, $emailExistente);
$rv = $svc->resolverUsuario(gls_claims("sub-exist-$sufixo", strtoupper($emailExistente)), 'site', '127.0.0.1', 'teste');

it('conta existente com o mesmo e-mail é vinculada e entra', function () use ($rv, $idExistente) {
    expect($rv['ok'])->toBeTrue();
    expect($rv['vinculado_agora'])->toBeTrue();
    expect($rv['novo'])->toBeFalse();
    expect((int) $rv['usuario']['id'])->toBe($idExistente);
});

it('vínculo auditado e e-mail de aviso registrado', function () use ($pdo, $idExistente) {
    expect(gls_contar($pdo, "SELECT COUNT(*) FROM auditoria_logs WHERE entidade_tipo = 'usuario' AND entidade_id = :id AND acao = 'autenticacao.google_vinculado'", array('id' => $idExistente)))->toBe(1);
    expect(gls_contar($pdo, "SELECT COUNT(*) FROM emails_envios WHERE entidade_tipo = 'usuario' AND entidade_id = :id AND evento = 'email.google_vinculado'", array('id' => $idExistente)))->toBe(1);
});

it('o e-mail de aviso traz o e-mail do Google e a data', function () use ($pdo, $idExistente) {
    $st = $pdo->prepare("SELECT contexto_json, assunto FROM emails_envios WHERE entidade_id = :id AND evento = 'email.google_vinculado' ORDER BY id DESC LIMIT 1");
    $st->execute(array('id' => $idExistente));
    $e = $st->fetch();
    $ctx = json_decode((string) $e['contexto_json'], true);
    expect($e['assunto'])->toBe('Sua conta foi vinculada ao Google');
    expect($ctx['vinculo']['email_google'])->toContain('existente.');
    expect(preg_match('#^\d{2}/\d{2}/\d{4} às \d{2}:\d{2}$#u', (string) $ctx['vinculo']['data_hora']))->toBe(1);
});

it('e-mail não verificado é recusado', function () use ($svc, $pdo, $sufixo) {
    gls_usuario($pdo, "naoverif.$sufixo@teste.local");
    $r = $svc->resolverUsuario(gls_claims("sub-nv-$sufixo", "naoverif.$sufixo@teste.local", array('email_verified' => false)), 'site', '127.0.0.1', 'teste');
    expect($r['ok'])->toBeFalse();
    expect($r['erro'])->toBe('email_nao_verificado');
    expect($r['message'])->toBe('Seu e-mail no Google não está verificado.');
});

it('email_verified como texto "true" também vale', function () use ($svc, $sufixo) {
    $r = $svc->resolverUsuario(gls_claims("sub-txt-$sufixo", "texto.$sufixo@gmail.test", array('email_verified' => 'true')), 'site', '127.0.0.1', 'teste');
    expect($r['ok'])->toBeTrue();
});

it('conta que já tem outra conta Google não é revinculada', function () use ($svc, $sufixo, $emailExistente) {
    $r = $svc->resolverUsuario(gls_claims("sub-intruso-$sufixo", $emailExistente), 'site', '127.0.0.1', 'teste');
    expect($r['ok'])->toBeFalse();
    expect($r['erro'])->toBe('falha');
});

describe('Status da conta');

it('conta inativa não entra (nem vinculando, nem já vinculada)', function () use ($svc, $pdo, $sufixo, $ident) {
    $id = gls_usuario($pdo, "inativa.$sufixo@teste.local", array('status' => 'inativo'));
    $r = $svc->resolverUsuario(gls_claims("sub-inat-$sufixo", "inativa.$sufixo@teste.local"), 'site', '127.0.0.1', 'teste');
    expect($r['erro'])->toBe('conta_inativa');
    expect($r['message'])->toBe('Usuário sem permissão de acesso.');
    expect($ident->findByProvedorSub('google', "sub-inat-$sufixo"))->toBeNull();

    $ident->vincular($id, 'google', "sub-inat2-$sufixo", null);
    $r2 = $svc->resolverUsuario(gls_claims("sub-inat2-$sufixo", "inativa.$sufixo@teste.local"), 'site', '127.0.0.1', 'teste');
    expect($r2['erro'])->toBe('conta_inativa');
});

it('bloqueio temporário por senha errada não impede o Google', function () use ($svc, $pdo, $sufixo) {
    $id = gls_usuario($pdo, "bloq.$sufixo@teste.local", array('bloqueado_ate' => date('Y-m-d H:i:s', time() + 3600)));
    $r = $svc->resolverUsuario(gls_claims("sub-bloq-$sufixo", "bloq.$sufixo@teste.local"), 'site', '127.0.0.1', 'teste');
    expect($r['ok'])->toBeTrue();
    expect((int) $r['usuario']['id'])->toBe($id);
});

it('sub ausente é recusado', function () use ($svc) {
    $r = $svc->resolverUsuario(array('email' => 'x@y.z', 'email_verified' => true), 'site', '127.0.0.1', 'teste');
    expect($r['ok'])->toBeFalse();
});

describe('UsuarioIdentidade');

it('tocarUso atualiza o e-mail e remover apaga', function () use ($ident, $pdo, $sufixo) {
    $id = gls_usuario($pdo, "ident.$sufixo@teste.local");
    $vid = $ident->vincular($id, 'google', "sub-ident-$sufixo", 'Antes@Teste.local');
    $ident->tocarUso($vid, 'depois@teste.local');
    expect($ident->findByUsuario($id, 'google')['email'])->toBe('depois@teste.local');
    expect($ident->remover($vid))->toBeTrue();
    expect($ident->findByUsuario($id, 'google'))->toBeNull();
});

exit(testes_resumo());
