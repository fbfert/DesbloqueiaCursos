<?php

/**
 * Fase 0 do checkout rapido: normalizacao de WhatsApp, captura de origem
 * de trafego e a feature flag.
 *
 * Execucao:
 *   php tests/Unit/checkout_rapido_fase0.php
 *
 * Nao escreve no banco. A parte de flag usa o fallback de .env.
 */

require_once __DIR__ . '/_bootstrap.php';

use App\Support\OrigemTrafego;
use App\Support\Whatsapp;

// ----------------------------------------------------------------
// WhatsApp -> E.164
// ----------------------------------------------------------------

describe('Whatsapp::normalizar aceita o que brasileiro digita', function () {});

it('celular com 11 digitos e DDD valido', function () {
    expect(Whatsapp::normalizar('11988887777'))->toBe('+5511988887777');
});

it('formatado com parenteses, espaco e hifen', function () {
    expect(Whatsapp::normalizar('(11) 98888-7777'))->toBe('+5511988887777');
});

it('com +55 na frente', function () {
    expect(Whatsapp::normalizar('+55 11 98888-7777'))->toBe('+5511988887777');
});

it('com 55 sem o mais', function () {
    expect(Whatsapp::normalizar('5511988887777'))->toBe('+5511988887777');
});

it('com 0055 internacional', function () {
    expect(Whatsapp::normalizar('005511988887777'))->toBe('+5511988887777');
});

it('com zero de operadora antes do DDD', function () {
    expect(Whatsapp::normalizar('011988887777'))->toBe('+5511988887777');
});

it('celular antigo de 8 digitos ganha o nono digito', function () {
    expect(Whatsapp::normalizar('1188887777'))->toBe('+5511988887777');
});

describe('Whatsapp::normalizar recusa o que nao serve', function () {});

it('DDD inexistente (20) e recusado', function () {
    expect(Whatsapp::normalizar('20988887777'))->toBeNull();
});

it('DDD inexistente (00) e recusado', function () {
    expect(Whatsapp::normalizar('00988887777'))->toBeNull();
});

it('telefone fixo (8 digitos comecando com 3) e recusado', function () {
    expect(Whatsapp::normalizar('1133334444'))->toBeNull();
});

it('celular de 9 digitos que nao comeca com 9 e recusado', function () {
    expect(Whatsapp::normalizar('11888887777'))->toBeNull();
});

it('curto demais e recusado', function () {
    expect(Whatsapp::normalizar('11988'))->toBeNull();
});

it('longo demais e recusado', function () {
    expect(Whatsapp::normalizar('119888877771234'))->toBeNull();
});

it('numero estrangeiro e recusado', function () {
    expect(Whatsapp::normalizar('+1 415 555 2671'))->toBeNull();
});

it('vazio e recusado', function () {
    expect(Whatsapp::normalizar(''))->toBeNull();
});

it('so letras e recusado', function () {
    expect(Whatsapp::normalizar('meu zap'))->toBeNull();
});

it('valido() concorda com normalizar()', function () {
    expect(Whatsapp::valido('11988887777'))->toBeTrue();
    expect(Whatsapp::valido('1133334444'))->toBeFalse();
});

it('formatar devolve leitura humana', function () {
    expect(Whatsapp::formatar('+5511988887777'))->toBe('(11) 98888-7777');
});

// ----------------------------------------------------------------
// Origem de trafego
// ----------------------------------------------------------------

/** Request de mentira: so precisa responder query(). */
final class RequestFalso extends \App\Core\Request
{
    private $q;
    public function __construct(array $query) { $this->q = $query; }
    public function query($key, $default = null) { return $this->q[$key] ?? $default; }
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    $_SESSION = array();
}

describe('OrigemTrafego preserva a primeira visita', function () {});

it('captura UTMs e gclid da query string', function () {
    OrigemTrafego::esquecer();
    OrigemTrafego::capturar(new RequestFalso(array(
        'utm_source' => 'google',
        'utm_medium' => 'cpc',
        'utm_campaign' => 'pnd-edfisica',
        'utm_term' => 'prova nacional docente',
        'utm_content' => 'anuncio-a',
        'gclid' => 'Cj0KCQtest123',
    )));

    $origem = OrigemTrafego::atual();
    expect($origem['utm_source'])->toBe('google');
    expect($origem['utm_campaign'])->toBe('pnd-edfisica');
    expect($origem['gclid'])->toBe('Cj0KCQtest123');
    expect($origem['origem_capturada_em'])->notToBeNull();
});

it('navegar em paginas sem UTM nao apaga a origem', function () {
    OrigemTrafego::esquecer();
    OrigemTrafego::capturar(new RequestFalso(array('utm_source' => 'google', 'gclid' => 'abc123')));
    OrigemTrafego::capturar(new RequestFalso(array()));           // pagina do curso
    OrigemTrafego::capturar(new RequestFalso(array()));           // catalogo
    OrigemTrafego::capturar(new RequestFalso(array()));           // checkout

    $origem = OrigemTrafego::atual();
    expect($origem['utm_source'])->toBe('google');
    expect($origem['gclid'])->toBe('abc123');
});

it('origem organica posterior nao sobrescreve a paga', function () {
    OrigemTrafego::esquecer();
    OrigemTrafego::capturar(new RequestFalso(array('utm_source' => 'google', 'gclid' => 'pago1')));
    OrigemTrafego::capturar(new RequestFalso(array('utm_source' => 'newsletter')));

    expect(OrigemTrafego::atual()['utm_source'])->toBe('google');
    expect(OrigemTrafego::atual()['gclid'])->toBe('pago1');
});

it('um novo clique de anuncio (gclid) sobrescreve a origem anterior', function () {
    OrigemTrafego::esquecer();
    OrigemTrafego::capturar(new RequestFalso(array('utm_source' => 'newsletter')));
    OrigemTrafego::capturar(new RequestFalso(array('utm_source' => 'google', 'gclid' => 'pago2')));

    expect(OrigemTrafego::atual()['utm_source'])->toBe('google');
    expect(OrigemTrafego::atual()['gclid'])->toBe('pago2');
});

it('sem nenhuma UTM, temOrigem() e falso e as chaves existem nulas', function () {
    OrigemTrafego::esquecer();
    OrigemTrafego::capturar(new RequestFalso(array()));

    expect(OrigemTrafego::temOrigem())->toBeFalse();
    $origem = OrigemTrafego::atual();
    foreach (OrigemTrafego::CAMPOS as $campo) {
        expect(array_key_exists($campo, $origem))->toBeTrue();
    }
});

it('valor gigante e cortado no limite da coluna', function () {
    OrigemTrafego::esquecer();
    OrigemTrafego::capturar(new RequestFalso(array('utm_campaign' => str_repeat('x', 500))));

    expect(strlen(OrigemTrafego::atual()['utm_campaign']))->toBe(191);
});

it('caractere de controle e removido', function () {
    OrigemTrafego::esquecer();
    OrigemTrafego::capturar(new RequestFalso(array('utm_source' => "goo\x00gle\x1F")));

    expect(OrigemTrafego::atual()['utm_source'])->toBe('google');
});

// ----------------------------------------------------------------
// Feature flag
// ----------------------------------------------------------------

describe('CheckoutRapidoConfigService', function () {});

it('nasce desligado quando nada esta configurado', function () {
    $svc = new App\Services\CheckoutRapidoConfigService();
    $config = $svc->config();
    expect(array_key_exists('ativo', $config))->toBeTrue();
    expect($svc->ativo())->toBe(!empty($config['ativo']));
});

it('expoe os parametros com piso de seguranca', function () {
    $svc = new App\Services\CheckoutRapidoConfigService();
    expect($svc->pixExpiraMinutos())->toBeGreaterThanOrEqual(5);
    expect($svc->pollingIntervaloSegundos())->toBeGreaterThanOrEqual(2);
    expect($svc->tokenAcessoValidadeHoras())->toBeGreaterThanOrEqual(1);
});

it('sempre ha texto de LGPD para exibir', function () {
    $svc = new App\Services\CheckoutRapidoConfigService();
    expect(strlen($svc->textoLgpd()))->toBeGreaterThan(20);
});

exit(testes_resumo());
