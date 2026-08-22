<?php

/**
 * Fase 1 do checkout rapido: resolucao de usuario sem senha e abertura de
 * pedido com origem de trafego.
 *
 * ATENCAO: escreve no banco. Rode contra um banco de teste:
 *   DB_DATABASE=dc_teste DB_USERNAME=... DB_PASSWORD=... php tests/Unit/checkout_rapido_fase1.php
 *
 * Exige a migracao 071 aplicada. Toda massa criada e removida no final.
 */

require_once __DIR__ . '/_bootstrap.php';

use App\Services\CheckoutRapidoService;
use App\Support\Whatsapp;

$pdo = testes_conectar_banco();

// Pre-condicao: sem a migracao 071 nada disso faz sentido.
$temMigracao = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'cadastro_status'")->rowCount() > 0;
if (!$temMigracao) {
    fwrite(STDERR, "\n  Migracao 071 nao aplicada neste banco. Rode sql/071_checkout_rapido_v1.sql antes.\n\n");
    exit(1);
}

$sufixo = 'CRTEST' . substr((string) microtime(true), -6);
/** Base numerica unica por execucao, para os CPFs sinteticos nao colidirem. */
$baseCpf = (int) substr(preg_replace('/\D/', '', $sufixo . '000000'), 0, 6);
$criados = array('usuarios' => array(), 'pedidos' => array());

/** CPF sinteticamente valido a partir de 9 digitos base. */
function cpf_valido_de($base9) {
    $d = preg_replace('/\D/', '', (string) $base9);
    $d = str_pad(substr($d, 0, 9), 9, '0', STR_PAD_LEFT);
    for ($j = 0; $j < 2; $j++) {
        $soma = 0;
        $len = strlen($d);
        for ($i = 0; $i < $len; $i++) {
            $soma += (int) $d[$i] * (($len + 1) - $i);
        }
        $resto = ($soma * 10) % 11;
        $d .= ($resto === 10 ? 0 : $resto);
    }
    return $d;
}

$svc = new CheckoutRapidoService();

// ----------------------------------------------------------------
// Validacao do formulario
// ----------------------------------------------------------------

describe('validar() recusa entrada ruim campo a campo', function () {});

it('tres campos vazios geram tres erros', function () use ($svc) {
    $errors = $svc->validar(array('email' => '', 'whatsapp' => '', 'cpf' => ''));
    expect(count($errors))->toBe(3);
    expect($errors)->toHaveKey('email');
    expect($errors)->toHaveKey('whatsapp');
    expect($errors)->toHaveKey('cpf');
});

it('WhatsApp invalido e recusado no servidor, nao so no cliente', function () use ($svc) {
    $errors = $svc->validar(array(
        'email' => 'a@b.com',
        'whatsapp' => '1133334444',
        'cpf' => cpf_valido_de('111444777'),
    ));
    expect($errors)->toHaveKey('whatsapp');
    expect(count($errors))->toBe(1);
});

it('CPF invalido e recusado', function () use ($svc) {
    $errors = $svc->validar(array('email' => 'a@b.com', 'whatsapp' => '11988887777', 'cpf' => '11111111111'));
    expect($errors)->toHaveKey('cpf');
});

it('entrada boa nao gera erro', function () use ($svc) {
    $errors = $svc->validar(array(
        'email' => 'a@b.com',
        'whatsapp' => '(11) 98888-7777',
        'cpf' => cpf_valido_de('111444777'),
    ));
    expect(count($errors))->toBe(0);
});

// ----------------------------------------------------------------
// Resolucao de usuario
// ----------------------------------------------------------------

describe('resolverUsuario: e-mail novo', function () {});

it('cria usuario pendente, sem nome e sem senha', function () use ($svc, $pdo, $sufixo, $baseCpf, &$criados) {
    $email = strtolower($sufixo) . '.novo@teste.local';
    $cpf = cpf_valido_de($baseCpf . '001');

    $r = $svc->resolverUsuario($email, '+5511988887777', $cpf);
    expect($r['ok'])->toBeTrue();
    expect($r['novo'])->toBeTrue();

    $criados['usuarios'][] = $r['usuario_id'];

    $u = $pdo->query('SELECT * FROM usuarios WHERE id = ' . (int) $r['usuario_id'])->fetch();
    expect($u['nome'])->toBeNull();
    expect($u['senha_hash'])->toBeNull();
    expect($u['cadastro_status'])->toBe('pendente');
    expect($u['cadastro_origem'])->toBe('checkout_rapido');
    expect($u['whatsapp'])->toBe('+5511988887777');
    expect($u['cpf'])->toBe($cpf);
});

describe('resolverUsuario: e-mail que ja existe', function () {});

it('reaproveita o cadastro e NAO pede login', function () use ($svc, $pdo, $sufixo, $baseCpf, &$criados) {
    $email = strtolower($sufixo) . '.existente@teste.local';
    $cpf = cpf_valido_de($baseCpf . '002');

    $primeiro = $svc->resolverUsuario($email, '+5511988887777', $cpf);
    $criados['usuarios'][] = $primeiro['usuario_id'];

    $segundo = $svc->resolverUsuario($email, '+5521977776666', $cpf);

    expect($segundo['ok'])->toBeTrue();
    expect($segundo['novo'])->toBeFalse();
    expect($segundo['usuario_id'])->toBe($primeiro['usuario_id']);
});

it('WhatsApp ja preenchido nao e sobrescrito', function () use ($svc, $pdo, $sufixo, $baseCpf, &$criados) {
    $email = strtolower($sufixo) . '.zap@teste.local';
    $cpf = cpf_valido_de($baseCpf . '003');

    $r = $svc->resolverUsuario($email, '+5511911112222', $cpf);
    $criados['usuarios'][] = $r['usuario_id'];

    $svc->resolverUsuario($email, '+5521933334444', $cpf);

    $u = $pdo->query('SELECT whatsapp FROM usuarios WHERE id = ' . (int) $r['usuario_id'])->fetch();
    expect($u['whatsapp'])->toBe('+5511911112222');
});

describe('resolverUsuario: colisoes de CPF e e-mail', function () {});

it('CPF ja cadastrado com OUTRO e-mail reaproveita a conta, sem trocar o e-mail dela', function () use ($svc, $pdo, $sufixo, $baseCpf, &$criados) {
    $emailConta = strtolower($sufixo) . '.dono@teste.local';
    $cpf = cpf_valido_de($baseCpf . '004');

    $dono = $svc->resolverUsuario($emailConta, '+5511988887777', $cpf);
    $criados['usuarios'][] = $dono['usuario_id'];

    $outro = $svc->resolverUsuario(strtolower($sufixo) . '.outro@teste.local', '+5511988887777', $cpf);

    expect($outro['ok'])->toBeTrue();
    expect($outro['usuario_id'])->toBe($dono['usuario_id']);
    expect($outro['email_divergente'])->toBeTrue();

    // O e-mail da conta continua o original: trocar seria tomada de conta.
    $u = $pdo->query('SELECT email FROM usuarios WHERE id = ' . (int) $dono['usuario_id'])->fetch();
    expect($u['email'])->toBe($emailConta);
});

it('e-mail ja cadastrado com OUTRO CPF e recusado com mensagem clara', function () use ($svc, $sufixo, $baseCpf, &$criados) {
    $email = strtolower($sufixo) . '.conflito@teste.local';

    $primeiro = $svc->resolverUsuario($email, '+5511988887777', cpf_valido_de($baseCpf . '005'));
    $criados['usuarios'][] = $primeiro['usuario_id'];

    $segundo = $svc->resolverUsuario($email, '+5511988887777', cpf_valido_de($baseCpf . '006'));

    expect($segundo['ok'])->toBeFalse();
    expect($segundo['campo'])->toBe('cpf');
    expect($segundo['message'])->toContain('outro CPF');
});

it('cadastro antigo sem CPF recebe o CPF informado', function () use ($svc, $pdo, $sufixo, $baseCpf, &$criados) {
    $email = strtolower($sufixo) . '.semcpf@teste.local';
    $pdo->exec("INSERT INTO usuarios (nome, email, cpf, senha_hash, status, cadastro_status, tentativas_login, created_at, updated_at)
                VALUES ('Antigo', '$email', '', 'hash', 'ativo', 'completo', 0, NOW(), NOW())");
    $id = (int) $pdo->lastInsertId();
    $criados['usuarios'][] = $id;

    $cpf = cpf_valido_de($baseCpf . '007');
    $r = $svc->resolverUsuario($email, '+5511988887777', $cpf);

    expect($r['ok'])->toBeTrue();
    expect($r['usuario_id'])->toBe($id);

    $u = $pdo->query("SELECT cpf FROM usuarios WHERE id = $id")->fetch();
    expect($u['cpf'])->toBe($cpf);
});

// ----------------------------------------------------------------
// Pedido com origem de trafego
// ----------------------------------------------------------------

describe('iniciarPedido grava a origem do anuncio no pedido', function () {});

$cursoTeste = $pdo->query(
    "SELECT ce.id, ce.nome, t.id AS turma_id
       FROM cursos_eventos ce
       INNER JOIN turmas t ON t.curso_evento_id = ce.id AND t.status = 'aberta' AND t.deleted_at IS NULL
      WHERE ce.status = 'ativo' AND ce.deleted_at IS NULL AND ce.valor > 0
      ORDER BY ce.id DESC LIMIT 1"
)->fetch();

if (!$cursoTeste) {
    it('SEM curso pago com turma aberta neste banco — testes de pedido pulados', function () {
        expect(true)->toBeTrue();
    });
} else {
    it('abre pedido em aguardando_pagamento com UTMs e gclid gravados', function () use ($svc, $pdo, $sufixo, $baseCpf, $cursoTeste, &$criados) {
        $email = strtolower($sufixo) . '.pedido@teste.local';
        $cpf = cpf_valido_de($baseCpf . '008');
        $u = $svc->resolverUsuario($email, '+5511988887777', $cpf);
        $criados['usuarios'][] = $u['usuario_id'];

        $r = $svc->iniciarPedido(array(
            'curso_evento_id' => (int) $cursoTeste['id'],
            'turma_id' => (int) $cursoTeste['turma_id'],
            'usuario_id' => $u['usuario_id'],
            'email' => $email,
            'whatsapp' => '+5511988887777',
            'cpf' => $cpf,
            'origem' => array(
                'utm_source' => 'google',
                'utm_medium' => 'cpc',
                'utm_campaign' => 'pnd-edfisica',
                'utm_term' => 'prova docente',
                'utm_content' => 'anuncio-a',
                'gclid' => 'Cj0KTESTE',
                'origem_capturada_em' => date('Y-m-d H:i:s'),
            ),
        ), '127.0.0.1', 'phpunit');

        expect($r['ok'])->toBeTrue();
        $criados['pedidos'][] = $r['pedido_id'];

        $p = $pdo->query('SELECT * FROM pedidos WHERE id = ' . (int) $r['pedido_id'])->fetch();
        expect($p['status'])->toBe('aguardando_pagamento');
        expect($p['canal_origem'])->toBe('checkout_rapido');
        expect($p['utm_source'])->toBe('google');
        expect($p['utm_campaign'])->toBe('pnd-edfisica');
        expect($p['gclid'])->toBe('Cj0KTESTE');
        expect($p['pagador_email'])->toBe($email);
    });

    it('participante e inscricao sao criados sem exigir nome', function () use ($pdo, &$criados) {
        $pedidoId = (int) end($criados['pedidos']);

        $part = $pdo->query("SELECT * FROM participantes_pedido WHERE pedido_id = $pedidoId")->fetch();
        expect($part)->notToBeNull();
        expect($part['nome'])->toBeNull();

        $insc = $pdo->query("SELECT * FROM inscricoes WHERE pedido_id = $pedidoId")->fetch();
        expect($insc)->notToBeNull();
        expect($insc['status'])->toBe('pendente');
    });

    it('recarregar a tela reaproveita o pedido em vez de abrir outro', function () use ($svc, $pdo, $sufixo, $baseCpf, $cursoTeste, &$criados) {
        $pedidoId = (int) end($criados['pedidos']);
        $usuarioId = (int) end($criados['usuarios']);
        $email = strtolower($sufixo) . '.pedido@teste.local';

        $r = $svc->iniciarPedido(array(
            'curso_evento_id' => (int) $cursoTeste['id'],
            'turma_id' => (int) $cursoTeste['turma_id'],
            'usuario_id' => $usuarioId,
            'email' => $email,
            'whatsapp' => '+5511988887777',
            'cpf' => cpf_valido_de($baseCpf . '008'),
            'origem' => array(),
        ), '127.0.0.1', 'phpunit');

        expect($r['ok'])->toBeTrue();
        expect($r['reaproveitado'])->toBeTrue();
        expect($r['pedido_id'])->toBe($pedidoId);
    });

    it('polling exige o codigo certo e nunca diz "pago" sozinho', function () use ($svc, $pdo, &$criados) {
        $pedidoId = (int) end($criados['pedidos']);
        $codigo = $pdo->query("SELECT codigo FROM pedidos WHERE id = $pedidoId")->fetchColumn();

        expect($svc->pedidoParaPolling($pedidoId, 'CODIGO-ERRADO'))->toBeNull();

        $estado = $svc->pedidoParaPolling($pedidoId, $codigo);
        expect($estado['ok'])->toBeTrue();
        expect($estado['pago'])->toBeFalse();
        expect($estado['status'])->toBe('aguardando');
    });

    it('polling reflete o pagamento assim que o BANCO muda', function () use ($svc, $pdo, &$criados) {
        $pedidoId = (int) end($criados['pedidos']);
        $codigo = $pdo->query("SELECT codigo FROM pedidos WHERE id = $pedidoId")->fetchColumn();

        $pdo->exec("UPDATE pedidos SET status = 'pago' WHERE id = $pedidoId");
        $estado = $svc->pedidoParaPolling($pedidoId, $codigo);

        expect($estado['pago'])->toBeTrue();
        expect($estado['status'])->toBe('pago');

        $pdo->exec("UPDATE pedidos SET status = 'aguardando_pagamento' WHERE id = $pedidoId");
    });

    it('ja matriculado nao gera cobranca nova', function () use ($svc, $pdo, $sufixo, $baseCpf, $cursoTeste, &$criados) {
        $pedidoId = (int) end($criados['pedidos']);
        $usuarioId = (int) end($criados['usuarios']);

        $pdo->exec("UPDATE pedidos SET status = 'pago' WHERE id = $pedidoId");
        $pdo->exec("UPDATE inscricoes SET status = 'ativa', confirmado_em = NOW() WHERE pedido_id = $pedidoId");

        $r = $svc->iniciarPedido(array(
            'curso_evento_id' => (int) $cursoTeste['id'],
            'turma_id' => (int) $cursoTeste['turma_id'],
            'usuario_id' => $usuarioId,
            'email' => strtolower($sufixo) . '.pedido@teste.local',
            'whatsapp' => '+5511988887777',
            'cpf' => cpf_valido_de($baseCpf . '008'),
            'origem' => array(),
        ), '127.0.0.1', 'phpunit');

        expect($r['ok'])->toBeFalse();
        expect($r['ja_matriculado'])->toBeTrue();
    });
}

// ----------------------------------------------------------------
// Limpeza
// ----------------------------------------------------------------

foreach (array_unique($criados['pedidos']) as $pedidoId) {
    $pedidoId = (int) $pedidoId;
    $pdo->exec("DELETE FROM inscricoes WHERE pedido_id = $pedidoId");
    $pdo->exec("DELETE FROM participantes_pedido WHERE pedido_id = $pedidoId");
    $pdo->exec("DELETE FROM pedido_itens WHERE pedido_id = $pedidoId");
    $pdo->exec("DELETE FROM pedidos WHERE id = $pedidoId");
}
foreach (array_unique($criados['usuarios']) as $usuarioId) {
    $usuarioId = (int) $usuarioId;
    $pdo->exec("DELETE FROM usuario_perfis WHERE usuario_id = $usuarioId");
    $pdo->exec("DELETE FROM usuario_consentimentos WHERE usuario_id = $usuarioId");
    $pdo->exec("DELETE FROM usuarios WHERE id = $usuarioId");
}
$pdo->exec("DELETE FROM auditoria_logs WHERE user_agent = 'phpunit'");

echo "\n  massa de teste removida\n";

exit(testes_resumo());
