<?php

/**
 * Destino depois do login do tema (openspec/changes/tema-caderno-unico).
 *
 * Sem banco. Execução: php tests/Unit/login_destino.php
 */

require_once __DIR__ . '/_bootstrap.php';

use App\Support\LoginDestino;

describe('LoginDestino::sucesso');

it('leva cada perfil à sua área quando não há destino', function () {
    expect(LoginDestino::sucesso('v2', '', '/admin/dashboard'))->toBe('/admin/dashboard');
    expect(LoginDestino::sucesso('v2', '', '/professor/dashboard'))->toBe('/professor/dashboard');
    expect(LoginDestino::sucesso('v2', '', '/revisor'))->toBe('/revisor');
    expect(LoginDestino::sucesso('v2', '', '/'))->toBe('/v2/pos-login');
    expect(LoginDestino::sucesso('', '', null))->toBe('/v2/pos-login');
});

it('o destino pedido vence a área do perfil', function () {
    expect(LoginDestino::sucesso('v2', '/admin/pedidos?status=pago', '/admin/dashboard'))->toBe('/admin/pedidos?status=pago');
    expect(LoginDestino::sucesso('v2', '/v2/checkout/resumo?pedido_id=4', '/'))->toBe('/v2/checkout/resumo?pedido_id=4');
});

it('descarta destino externo e usa o padrão do perfil', function () {
    expect(LoginDestino::sucesso('v2', 'https://evil.com/admin', '/admin/dashboard'))->toBe('/admin/dashboard');
    expect(LoginDestino::sucesso('v2', '//evil.com', '/'))->toBe('/v2/pos-login');
});

it('aluno vindo da área do aluno volta para ela', function () {
    expect(LoginDestino::sucesso('v2_aluno', '', '/'))->toBe('/v2/aluno');
});

describe('LoginDestino::erro');

it('volta sempre ao login do tema', function () {
    expect(LoginDestino::erro('', ''))->toBe('/v2/login');
    expect(LoginDestino::erro('v2', ''))->toBe('/v2/login');
    expect(LoginDestino::erro('v2_aluno', ''))->toBe('/v2/login?origem=v2_aluno');
    expect(LoginDestino::erro('v2', '/admin/pedidos'))->toBe('/v2/login?redirect=%2Fadmin%2Fpedidos');
    expect(LoginDestino::erro('v2', 'https://evil.com'))->toBe('/v2/login');
});

testes_resumo();
