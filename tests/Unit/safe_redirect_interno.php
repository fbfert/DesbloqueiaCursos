<?php

/**
 * SafeRedirect::caminhoInterno e v2Path (openspec/changes/tema-caderno-unico).
 *
 * Sem banco. Execução: php tests/Unit/safe_redirect_interno.php
 */

require_once __DIR__ . '/_bootstrap.php';

use App\Support\SafeRedirect;

describe('SafeRedirect::caminhoInterno');

it('aceita o tema, o backoffice e as rotas legadas do aluno', function () {
    expect(SafeRedirect::caminhoInterno('/v2/aluno/?aba=pedidos'))->toBe('/v2/aluno/?aba=pedidos');
    expect(SafeRedirect::caminhoInterno('/admin/pedidos?status=pago'))->toBe('/admin/pedidos?status=pago');
    expect(SafeRedirect::caminhoInterno('/admin'))->toBe('/admin');
    expect(SafeRedirect::caminhoInterno('/professor/dashboard'))->toBe('/professor/dashboard');
    expect(SafeRedirect::caminhoInterno('/revisor'))->toBe('/revisor');
    expect(SafeRedirect::caminhoInterno('/aluno/curso/5/12/30/modulo/7/conteudo/99'))->toBe('/aluno/curso/5/12/30/modulo/7/conteudo/99');
    expect(SafeRedirect::caminhoInterno('/meus-cursos'))->toBe('/meus-cursos');
});

it('rejeita destinos externos e malformados', function () {
    $invalidos = array(
        '', 'admin', '//evil.com', '/\\evil.com', 'https://evil.com/admin', 'javascript:alert(1)',
        "/admin\r\nLocation: x", '/v2/../admin', '/admin/./x', '/v2/%2e%2e/x', '/adminx', '/api/app/v1/me',
        '/webhooks/abacatepay', '/login', '/',
    );
    foreach ($invalidos as $valor) {
        expect(SafeRedirect::caminhoInterno($valor))->toBeNull();
    }
});

describe('SafeRedirect::v2Path');

it('continua aceitando só /v2', function () {
    expect(SafeRedirect::v2Path('/v2/curso?curso_id=3'))->toBe('/v2/curso?curso_id=3');
    expect(SafeRedirect::v2Path('/v2'))->toBe('/v2');
    expect(SafeRedirect::v2Path('/admin'))->toBeNull();
    expect(SafeRedirect::v2Path('/v2x'))->toBeNull();
    expect(SafeRedirect::v2Path('/v2/../admin'))->toBeNull();
});

describe('SafeRedirect::v2LoginWithReturn');

it('embute retornos internos, inclusive do backoffice', function () {
    expect(SafeRedirect::v2LoginWithReturn('/admin/dashboard'))->toBe('/v2/login?redirect=%2Fadmin%2Fdashboard');
    expect(SafeRedirect::v2LoginWithReturn('https://evil.com'))->toBe('/v2/login');
});

testes_resumo();
