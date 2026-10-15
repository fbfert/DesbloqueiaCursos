<?php

/**
 * URLs de retorno do AbacatePay no tema (openspec/changes/tema-caderno-unico).
 *
 * Sem banco. Execução: php tests/Unit/abacatepay_urls_retorno.php
 */

require_once __DIR__ . '/_bootstrap.php';

use App\Services\Payments\AbacatePayService;

describe('AbacatePayService::urlsRetorno');

it('devolve o aluno ao resumo e ao pagamento concluído do tema', function () {
    list($retorno, $conclusao) = AbacatePayService::urlsRetorno(40);
    expect(substr($retorno, -strlen('/v2/checkout/resumo?pedido_id=40')))->toBe('/v2/checkout/resumo?pedido_id=40');
    expect(substr($conclusao, -strlen('/v2/checkout/sucesso?pedido_id=40')))->toBe('/v2/checkout/sucesso?pedido_id=40');
});

it('nunca aponta para o checkout legado', function () {
    foreach (AbacatePayService::urlsRetorno(7) as $url) {
        expect(strpos($url, '/v2/checkout/') !== false)->toBeTrue();
    }
});

testes_resumo();
