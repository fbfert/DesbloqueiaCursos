<?php

/**
 * Usuario — CPF opcional (mudança login-google, migração 083).
 *
 * O QUE ESTE TESTE PROTEGE
 *   - conta nova sem CPF grava NULL, nunca '' (com o UNIQUE, o segundo '' quebraria);
 *   - várias contas sem CPF convivem;
 *   - login por e-mail inexistente não casa com conta legada de cpf = '';
 *   - login por CPF continua funcionando;
 *   - findByCpf('') não devolve ninguém.
 *
 * Banco: precisa da migração 083. Roda em transação revertida.
 *   DB_HOST=127.0.0.1 DB_PORT=33061 DB_DATABASE=desbloqueia_app_teste DB_USERNAME=root DB_PASSWORD=... php tests/Unit/usuario_cpf_nulo.php
 */

require_once __DIR__ . '/_bootstrap.php';

use App\Models\Usuario;

$pdo = testes_conectar_banco();
$nulo = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'cpf'")->fetch();
if (!$nulo || strtoupper($nulo['Null']) !== 'YES') {
    echo "SKIP: usuarios.cpf ainda é NOT NULL (aplique sql/083_login_google.sql).\n";
    exit(0);
}

$pdo->beginTransaction();
register_shutdown_function(function () use ($pdo) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
});

$model = new Usuario();
$sufixo = bin2hex(random_bytes(4));

describe('normalizarCpf / semCpf');

it('vazio e só pontuação viram NULL', function () {
    expect(Usuario::normalizarCpf(''))->toBeNull();
    expect(Usuario::normalizarCpf(null))->toBeNull();
    expect(Usuario::normalizarCpf('...-'))->toBeNull();
    expect(Usuario::normalizarCpf('123.456.789-09'))->toBe('12345678909');
});

it('semCpf reconhece NULL e o legado vazio', function () {
    expect(Usuario::semCpf(array('cpf' => null)))->toBeTrue();
    expect(Usuario::semCpf(array('cpf' => '')))->toBeTrue();
    expect(Usuario::semCpf(array()))->toBeTrue();
    expect(Usuario::semCpf(array('cpf' => '12345678909')))->toBeFalse();
});

describe('Gravação');

$idA = $model->create(array('nome' => 'Sem CPF A', 'email' => "semcpf.a.$sufixo@teste.local", 'senha_hash' => null));
$idB = $model->create(array('nome' => 'Sem CPF B', 'email' => "semcpf.b.$sufixo@teste.local", 'cpf' => '', 'senha_hash' => null));

it('create sem CPF grava NULL', function () use ($pdo, $idA, $idB) {
    $st = $pdo->prepare('SELECT COUNT(*) FROM usuarios WHERE id IN (:a, :b) AND cpf IS NULL');
    $st->execute(array('a' => $idA, 'b' => $idB));
    expect((int) $st->fetchColumn())->toBe(2);
});

it('updateProfile mantém NULL sem violar o UNIQUE', function () use ($pdo, $model, $idA) {
    $model->updateProfile($idA, array('nome' => 'Sem CPF A2', 'email' => 'x', 'cpf' => null, 'telefone' => ''));
    $st = $pdo->prepare('SELECT cpf FROM usuarios WHERE id = :id');
    $st->execute(array('id' => $idA));
    expect($st->fetchColumn())->toBeNull();
});

describe('Busca');

// Conta legada com cpf = '' (existe em produção, de antes do CPF obrigatório).
$pdo->exec("UPDATE usuarios SET cpf = NULL WHERE cpf = ''");
$pdo->prepare("INSERT INTO usuarios (nome, email, cpf, status, created_at) VALUES ('Legado', :e, '', 'ativo', NOW())")
    ->execute(array('e' => "legado.$sufixo@teste.local"));

it('login por e-mail inexistente não casa com cpf vazio', function () use ($model, $sufixo) {
    expect($model->findByLogin("ninguem.$sufixo@teste.local"))->toBeNull();
});

it('login por e-mail encontra a conta', function () use ($model, $sufixo, $idB) {
    $u = $model->findByLogin("SemCpf.B.$sufixo@teste.local");
    expect((int) $u['id'])->toBe($idB);
});

it('login por CPF continua funcionando', function () use ($model, $pdo, $sufixo) {
    $cpf = testes_cpf_livre($pdo);
    $pdo->prepare("INSERT INTO usuarios (nome, email, cpf, status, created_at) VALUES ('Com CPF', :e, :c, 'ativo', NOW())")
        ->execute(array('e' => "comcpf.$sufixo@teste.local", 'c' => $cpf));
    $u = $model->findByLogin(substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9));
    expect($u['email'])->toBe("comcpf.$sufixo@teste.local");
});

it('findByCpf vazio não devolve ninguém', function () use ($model) {
    expect($model->findByCpf(''))->toBeNull();
    expect($model->findByCpf(null))->toBeNull();
});

describe('AuthService::updateAccount');

$_SESSION = array();
$auth = new \App\Services\AuthService();
$base = array('nome' => 'Fulana Google', 'telefone' => '', 'cidade' => '', 'estado' => '');

it('conta sem CPF salva o perfil sem informar CPF', function () use ($auth, $pdo, $idA, $sufixo, $base) {
    $r = $auth->updateAccount($idA, $base + array('email' => "semcpf.a.$sufixo@teste.local", 'cpf' => ''), '127.0.0.1', 'teste');
    expect($r['ok'])->toBeTrue();
    $st = $pdo->prepare('SELECT cpf FROM usuarios WHERE id = :id');
    $st->execute(array('id' => $idA));
    expect($st->fetchColumn())->toBeNull();
});

it('conta sem CPF recusa CPF inválido', function () use ($auth, $idA, $sufixo, $base) {
    $r = $auth->updateAccount($idA, $base + array('email' => "semcpf.a.$sufixo@teste.local", 'cpf' => '111.111.111-12'), '127.0.0.1', 'teste');
    expect($r['ok'])->toBeFalse();
    expect($r['errors']['cpf'])->toBe('Informe um CPF válido.');
});

it('conta sem CPF recusa CPF de outra conta', function () use ($auth, $pdo, $idA, $sufixo, $base) {
    $outro = (string) $pdo->query("SELECT cpf FROM usuarios WHERE cpf IS NOT NULL AND cpf <> '' LIMIT 1")->fetchColumn();
    $r = $auth->updateAccount($idA, $base + array('email' => "semcpf.a.$sufixo@teste.local", 'cpf' => $outro), '127.0.0.1', 'teste');
    expect($r['errors']['cpf'])->toBe('Este CPF já está cadastrado em outra conta. Fale com o atendimento.');
});

it('CPF já preenchido não muda pelo formulário', function () use ($auth, $pdo, $sufixo, $base) {
    $cpf = testes_cpf_livre($pdo);
    $pdo->prepare("INSERT INTO usuarios (nome, email, cpf, status, created_at) VALUES ('Fixo', :e, :c, 'ativo', NOW())")
        ->execute(array('e' => "fixo.$sufixo@teste.local", 'c' => $cpf));
    $id = (int) $pdo->lastInsertId();
    $r = $auth->updateAccount($id, $base + array('email' => "fixo.$sufixo@teste.local", 'cpf' => testes_cpf_livre($pdo)), '127.0.0.1', 'teste');
    expect($r['ok'])->toBeTrue();
    $st = $pdo->prepare('SELECT cpf FROM usuarios WHERE id = :id');
    $st->execute(array('id' => $id));
    expect($st->fetchColumn())->toBe($cpf);
});

exit(testes_resumo() > 0 ? 1 : 0);
