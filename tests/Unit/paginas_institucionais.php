<?php

/**
 * Institucionais publicadas: sitemap e rodapé do tema caderno só anunciam página
 * que existe (antes, /v2/como-funciona-a-sala-virtual ia ao sitemap mesmo excluída
 * em `paginas` — 404 anunciado ao Google).
 *
 * Escreve no banco dentro de uma transação desfeita no fim.
 *
 * Execução: php tests/Unit/paginas_institucionais.php
 */

require_once __DIR__ . '/_bootstrap.php';

use App\Controllers\SitemapController;
use App\Core\Request;
use App\Services\PaginaService;
use App\Support\V2Nav;

$pdo = testes_conectar_banco();
$pdo->beginTransaction();

// Estado controlado: quem-somos publicada, sala virtual excluída, onde-estamos rascunho.
$pdo->exec("DELETE FROM paginas WHERE rota IN ('/quem-somos', '/como-funciona-a-sala-virtual', '/onde-estamos')");
$ins = $pdo->prepare(
    'INSERT INTO paginas (titulo, slug, rota, status, created_at, updated_at, deleted_at)
     VALUES (:t, :s, :r, :st, NOW(), NOW(), :del)'
);
$ins->execute(array('t' => 'Quem somos', 's' => 'quem-somos', 'r' => '/quem-somos', 'st' => 'publicada', 'del' => null));
$ins->execute(array('t' => 'Sala virtual', 's' => 'como-funciona-a-sala-virtual', 'r' => '/como-funciona-a-sala-virtual', 'st' => 'publicada', 'del' => date('Y-m-d H:i:s')));
$ins->execute(array('t' => 'Onde estamos', 's' => 'onde-estamos', 'r' => '/onde-estamos', 'st' => 'rascunho', 'del' => null));

describe('PaginaService::institucionaisV2Publicadas');

$publicadas = (new PaginaService())->institucionaisV2Publicadas();

it('inclui a página publicada pela URL V2', function () use ($publicadas) {
    expect(in_array(V2Nav::QUEM_SOMOS, $publicadas, true))->toBe(true);
});

it('exclui página apagada e página em rascunho', function () use ($publicadas) {
    expect(in_array(V2Nav::COMO_FUNCIONA_SALA, $publicadas, true))->toBe(false);
    expect(in_array(V2Nav::ONDE_ESTAMOS, $publicadas, true))->toBe(false);
});

describe('SitemapController');

$resposta = (new SitemapController())->index(new Request('GET', '/sitemap.xml', array(), array()));
$prop = new ReflectionProperty($resposta, 'content');
$prop->setAccessible(true);
$xml = (string) $prop->getValue($resposta);

it('anuncia a institucional publicada', function () use ($xml) {
    expect(strpos($xml, '/v2/quem-somos') !== false)->toBe(true);
});

it('não anuncia página excluída nem rascunho', function () use ($xml) {
    expect(strpos($xml, 'como-funciona-a-sala-virtual') === false)->toBe(true);
    expect(strpos($xml, '/v2/onde-estamos') === false)->toBe(true);
});

it('mantém as páginas fixas', function () use ($xml) {
    expect(strpos($xml, '/v2/catalogo/') !== false)->toBe(true);
    expect(strpos($xml, '/v2/certificados/validar') !== false)->toBe(true);
});

$pdo->rollBack();

exit(testes_resumo());
