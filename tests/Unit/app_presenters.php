<?php

/**
 * API do app — presenters e conversões (sem banco).
 *
 * O JSON da API é o contrato com o app Android (openspec/changes/api-app-v1/
 * contrato.md). Estes testes fixam os formatos que o app interpreta: status do
 * item no enum do contrato, dinheiro em centavos, datas ISO com fuso, CPF
 * mascarado, gabarito escondido enquanto a tentativa não foi enviada, vídeo
 * resolvido pela whitelist do site e respostas do quiz convertidas para o
 * formato do ConteudoQuizService.
 *
 * Execução: php tests/Unit/app_presenters.php
 */

require_once __DIR__ . '/_bootstrap.php';

use App\Support\AppApi\CatalogoPresenter;
use App\Support\AppApi\CursoPresenter;
use App\Support\AppApi\Formato;
use App\Support\AppApi\PedidoPresenter;
use App\Support\AppApi\QuizPresenter;
use App\Support\AppApi\Tempo;
use App\Support\AppApi\UsuarioPresenter;
use App\Support\AppApi\Versao;

putenv('APP_TIMEZONE=America/Sao_Paulo');
// Como na VPS: PHP sem fuso configurado (UTC) e MySQL em horário de Brasília.
date_default_timezone_set('UTC');
putenv('APP_URL=https://desbloqueiacursos.com.br');

describe('Formato e tempo');

it('centavos inteiros a partir de decimal do banco', function () {
    expect(Formato::centavos('129.90'))->toBe(12990);
    expect(Formato::centavos(0.1 + 0.2))->toBe(30);
    expect(Formato::centavos(null))->toBeNull();
});

it('CPF mascarado no formato do contrato', function () {
    expect(Formato::cpfMascarado('12345678900'))->toBe('123.***.***-00');
    expect(Formato::cpfMascarado('123.456.789-00'))->toBe('123.***.***-00');
    expect(Formato::cpfMascarado(''))->toBeNull();
});

it('datas do banco viram ISO-8601 com o fuso da aplicação', function () {
    expect(Tempo::iso('2026-10-07 18:30:00'))->toBe('2026-10-07T18:30:00-03:00');
    expect(Tempo::iso(null))->toBeNull();
    expect(Tempo::iso('0000-00-00 00:00:00'))->toBeNull();
    $ts = Tempo::timestamp('2026-10-07 18:30:00');
    expect(Tempo::sql($ts))->toBe('2026-10-07 18:30:00');
});

it('coluna gravada pelo PHP (UTC) sai no instante certo, com fuso de Brasília', function () {
    expect(Tempo::isoPhp('2026-10-07 21:30:00'))->toBe('2026-10-07T18:30:00-03:00');
    expect(Tempo::isoPhp(''))->toBeNull();
});

it('URL absoluta para caminho público e mantém URL externa', function () {
    expect(Formato::urlAbsoluta('/assets/uploads/capa.png'))->toBe('https://desbloqueiacursos.com.br/assets/uploads/capa.png');
    expect(Formato::urlAbsoluta('https://cdn.exemplo.com/x.png'))->toBe('https://cdn.exemplo.com/x.png');
    expect(Formato::urlAbsoluta(''))->toBeNull();
});

it('HTML do editor é sanitizado (sem script nem on*)', function () {
    $html = Formato::htmlSeguro('<p onclick="x()">Olá</p><script>alert(1)</script>');
    expect(strpos($html, '<script'))->toBeFalse();
    expect(strpos($html, 'onclick'))->toBeFalse();
    expect($html)->toContain('Olá');
});

it('paginação com limites', function () {
    expect(Formato::paginacao(2, 10))->toEqual(array(2, 10, 10));
    expect(Formato::paginacao(0, 999))->toEqual(array(1, 50, 0));
    expect(Formato::paginacao('x', null))->toEqual(array(1, 20, 0));
});

describe('Versão do app');

it('lê o build entre parênteses de X-App-Version', function () {
    expect(Versao::build('1.0.0 (1)'))->toBe(1);
    expect(Versao::build('2.3.1 (42)'))->toBe(42);
    expect(Versao::build('7'))->toBe(7);
    expect(Versao::build(''))->toBeNull();
    expect(Versao::build('beta'))->toBeNull();
});

it('exige atualização só com build legível abaixo da mínima', function () {
    putenv('APP_MOBILE_VERSAO_MINIMA_ANDROID=5');
    expect(Versao::exigeAtualizacao('1.0.0 (4)'))->toBeTrue();
    expect(Versao::exigeAtualizacao('1.0.0 (5)'))->toBeFalse();
    expect(Versao::exigeAtualizacao(null))->toBeFalse();
    putenv('APP_MOBILE_VERSAO_MINIMA_ANDROID=1');
});

describe('Usuário');

it('objeto usuario do contrato, sem dados sensíveis', function () {
    $u = UsuarioPresenter::usuario(array('id' => '7', 'nome' => 'Ana', 'email' => 'a@b.com', 'cpf' => '98765432100', 'telefone' => '', 'cidade' => 'Santos', 'estado' => 'SP', 'senha_hash' => 'x', 'token_recuperacao' => 'y'));
    expect(array_keys($u))->toEqual(array('id', 'nome', 'email', 'cpf', 'telefone', 'cidade', 'estado', 'pendencias'));
    expect($u['id'])->toBe(7);
    expect($u['cpf'])->toBe('987.***.***-00');
    expect($u['telefone'])->toBeNull();
    expect($u['pendencias'])->toEqual(array());
});

it('conta sem CPF (login com Google): cpf nulo e pendencias ["cpf"]', function () {
    $u = UsuarioPresenter::usuario(array('id' => 8, 'nome' => 'Bia', 'email' => 'b@c.com', 'cpf' => null));
    expect($u['cpf'])->toBeNull();
    expect($u['pendencias'])->toEqual(array('cpf'));
});

describe('Cursos e conteúdo');

it('status público do site → enum do contrato', function () {
    $mapa = array(
        'nao_iniciado' => 'nao_iniciado', 'aguardando_envio' => 'nao_iniciado', 'pendente' => 'nao_iniciado',
        'acessado' => 'em_andamento', 'em_andamento' => 'em_andamento', 'devolvida' => 'em_andamento',
        'concluido' => 'concluido', 'aguardando_correcao' => 'enviado', 'pendente_correcao' => 'enviado',
        'corrigida' => 'corrigido', 'aprovada' => 'aprovado', 'reprovada' => 'reprovado', 'reprovado' => 'reprovado',
        'qualquer_outro' => 'nao_iniciado',
    );
    foreach ($mapa as $site => $contrato) {
        expect(CursoPresenter::statusItem($site))->toBe($contrato);
    }
});

it('inscrição: certificado emitido/apto/nao_apto e turma nula sem turma', function () {
    $base = array('id' => 10, 'status' => 'ativa', 'curso_evento_id' => 3, 'turma_id' => null, 'curso_nome' => 'Curso', 'curso_slug' => 'curso',
        'percentual_progresso' => '41.6', 'acesso_expira_em' => null, 'apto_certificado' => 0, 'certificado_codigo' => null, 'updated_at' => '2026-10-07 10:00:00');
    $i = CursoPresenter::inscricao($base, array('carga_horaria' => 40, 'thumbnail' => '/assets/uploads/c.png'), null);
    expect($i['turma'])->toBeNull();
    expect($i['progresso_percentual'])->toBe(42);
    expect($i['certificado'])->toEqual(array('status' => 'nao_apto', 'codigo' => null));
    expect($i['curso']['carga_horaria'])->toBe(40);
    expect($i['curso']['imagem_url'])->toBe('https://desbloqueiacursos.com.br/assets/uploads/c.png');
    $base['apto_certificado'] = 1;
    expect(CursoPresenter::inscricao($base)['certificado']['status'])->toBe('apto');
    $base['certificado_codigo'] = 'ABC123';
    expect(CursoPresenter::inscricao($base)['certificado'])->toEqual(array('status' => 'emitido', 'codigo' => 'ABC123'));
});

it('item aberto: vídeo YouTube, link externo, arquivo com download_url da API', function () {
    $nav = array('anterior' => array('item_id' => 9, 'titulo' => 'Antes', 'modulo_id' => 1), 'proximo' => null);
    $v = CursoPresenter::itemAberto(10, array('id' => 100, 'tipo' => 'video', 'titulo' => 'V', 'ordem' => 1, 'obrigatorio' => 1, 'status_publico' => 'nao_iniciado'), array('url' => 'https://youtu.be/M7lc1UVf-VE'), $nav);
    expect($v['conteudo']['video'])->toEqual(array('provedor' => 'youtube', 'video_id' => 'M7lc1UVf-VE', 'url' => 'https://youtu.be/M7lc1UVf-VE'));
    expect($v['conteudo']['html'])->toBeNull();
    expect($v['anterior'])->toEqual(array('id' => 9, 'titulo' => 'Antes'));
    expect($v['proximo'])->toBeNull();
    expect($v['pode_concluir_manualmente'])->toBeTrue();

    $l = CursoPresenter::itemAberto(10, array('id' => 101, 'tipo' => 'link', 'titulo' => 'L'), array('url' => 'https://www.gov.br/'), $nav);
    expect($l['conteudo']['link'])->toEqual(array('url' => 'https://www.gov.br/', 'abrir_externo' => true));

    $a = CursoPresenter::itemAberto(10, array('id' => 102, 'tipo' => 'arquivo', 'titulo' => 'A'), array('caminho' => 'x/y.pdf', 'nome_original' => 'apostila.pdf', 'mime_type' => 'application/pdf', 'tamanho_bytes' => '1234'), $nav);
    expect($a['conteudo']['arquivo'])->toEqual(array('nome' => 'apostila.pdf', 'mime' => 'application/pdf', 'tamanho_bytes' => 1234, 'download_url' => '/api/app/v1/inscricoes/10/itens/102/arquivo'));

    $t = CursoPresenter::itemAberto(10, array('id' => 103, 'tipo' => 'texto', 'titulo' => 'T'), array('conteudo' => '<p>Oi</p>'), $nav);
    expect($t['pode_concluir_manualmente'])->toBeFalse();
    expect($t['conteudo']['html'])->toBe('<p>Oi</p>');
});

it('vídeo incorporado: usa o src do iframe; host fora da whitelist vira "outro"', function () {
    $v = CursoPresenter::itemAberto(1, array('id' => 1, 'tipo' => 'video_incorporado', 'titulo' => 'X'), array('conteudo' => '<iframe src="https://player.vimeo.com/video/123456789"></iframe>'), array());
    expect($v['conteudo']['video']['provedor'])->toBe('vimeo');
    expect($v['conteudo']['video']['video_id'])->toBe('123456789');
    $o = CursoPresenter::videoDeUrl('https://exemplo.com/video.mp4');
    expect($o)->toEqual(array('provedor' => 'outro', 'video_id' => null, 'url' => 'https://exemplo.com/video.mp4'));
});

describe('Quiz');

it('respostas do app → formato do ConteudoQuizService', function () {
    list($obj, $disc) = QuizPresenter::respostasParaServico(array(
        '1' => array('alternativas' => array(11)),
        '2' => array('texto' => 'Minha resposta'),
        '3' => array('alternativas' => array()),
        'x' => 'lixo',
    ));
    expect($obj)->toEqual(array(1 => 11, 3 => ''));
    expect($disc)->toEqual(array(2 => 'Minha resposta'));
});

it('tentativa em andamento: sem correta/explicação; resposta salva devolvida', function () {
    $perguntas = array(
        array('id' => 1, 'tipo' => 'multipla_escolha', 'enunciado' => 'P1', 'ordem_apresentacao' => 1, 'explicacao' => 'segredo',
            'alternativas' => array(array('id' => 11, 'texto' => 'A', 'correta' => 1), array('id' => 12, 'texto' => 'B', 'correta' => 0)),
            'resposta' => array('alternativa_id' => 12, 'texto_resposta' => null)),
        array('id' => 2, 'tipo' => 'discursiva', 'enunciado' => 'P2', 'ordem_apresentacao' => 2, 'alternativas' => array(), 'resposta' => null),
    );
    $d = QuizPresenter::tentativaIniciada(array('id' => 5, 'expira_em' => null), $perguntas, null);
    $json = json_encode($d);
    expect(strpos($json, 'correta'))->toBeFalse();
    expect(strpos($json, 'segredo'))->toBeFalse();
    expect($d['perguntas'][0]['tipo'])->toBe('unica');
    expect($d['perguntas'][1]['tipo'])->toBe('discursiva');
    expect($d['respostas'])->toEqual(array('1' => array('alternativas' => array(12), 'texto' => null)));
    expect($d['tentativa']['segundos_restantes'])->toBeNull();
});

it('resultado respeita mostra_resultado / mostra_gabarito / comentários', function () {
    $pergunta = array('id' => 1, 'tipo' => 'multipla_escolha', 'enunciado' => 'P', 'explicacao' => '<p>Porque sim</p>',
        'alternativas' => array(array('id' => 11, 'texto' => 'A', 'correta' => 1), array('id' => 12, 'texto' => 'B', 'correta' => 0)),
        'resposta' => array('alternativa_id' => 11, 'correta' => 1));
    $tentativa = array('id' => 5, 'status' => 'corrigida', 'percentual' => '66.67', 'aprovado' => 1, 'discursiva_status' => 'nao_aplicavel', 'enviada_em' => '2026-10-07 10:00:00');

    $tudo = QuizPresenter::resultado(array('tentativa' => $tentativa, 'quiz' => array('exibir_resultado_apos_envio' => 1, 'exibir_gabarito_apos_envio' => 1, 'exibir_comentarios_apos_envio' => 1), 'perguntas' => array($pergunta)));
    expect($tudo['percentual'])->toBe(67);
    expect($tudo['aprovado'])->toBeTrue();
    expect($tudo['perguntas'][0]['gabarito'])->toEqual(array(11));
    expect($tudo['perguntas'][0]['correta'])->toBeTrue();
    expect($tudo['perguntas'][0]['comentario_html'])->toBe('<p>Porque sim</p>');

    $nada = QuizPresenter::resultado(array('tentativa' => $tentativa, 'quiz' => array('exibir_resultado_apos_envio' => 0, 'exibir_gabarito_apos_envio' => 0, 'exibir_comentarios_apos_envio' => 0), 'perguntas' => array($pergunta)));
    expect($nada['percentual'])->toBeNull();
    expect($nada['aprovado'])->toBeNull();
    expect($nada['perguntas'][0]['gabarito'])->toBeNull();
    expect($nada['perguntas'][0]['correta'])->toBeNull();
    expect($nada['perguntas'][0]['comentario_html'])->toBeNull();
});

it('status da tentativa: discursiva pendente = aguardando_correcao', function () {
    expect(QuizPresenter::statusTentativa(array('status' => 'corrigida', 'discursiva_status' => 'pendente')))->toBe('aguardando_correcao');
    expect(QuizPresenter::statusTentativa(array('status' => 'corrigida', 'discursiva_status' => 'corrigida')))->toBe('corrigida');
    expect(QuizPresenter::statusTentativa(array('status' => 'enviada')))->toBe('enviada');
});

describe('Pedidos, certificados e notificações');

it('pedido: centavos, rótulo PT-BR e regras pode_* do PedidoService', function () {
    $p = PedidoPresenter::pedido(
        array('id' => 50, 'codigo' => 'PED-0050', 'status' => 'aguardando_pagamento', 'total' => '199.00', 'created_at' => '2026-10-01 09:00:00'),
        array(array('curso_nome' => 'Curso', 'turma_nome' => 'T1', 'valor_total' => '199.00')),
        null,
        true
    );
    expect($p['total_centavos'])->toBe(19900);
    expect($p['status_rotulo'])->toBe('Aguardando pagamento');
    expect($p['pode_cancelar'])->toBeTrue();
    expect($p['pode_enviar_comprovante'])->toBeTrue();
    expect($p['pode_pagar_online'])->toBeTrue();
    expect($p['pix']['chave'])->toBe('cpeducacursos@gmail.com');
    expect($p['itens'][0])->toEqual(array('titulo' => 'Curso — T1', 'valor_centavos' => 19900));

    $pago = PedidoPresenter::pedido(array('id' => 51, 'codigo' => 'X', 'status' => 'pago', 'total' => '10'), array(), array('status' => 'aprovado'), true);
    expect($pago['pode_cancelar'])->toBeFalse();
    expect($pago['pode_enviar_comprovante'])->toBeFalse();
    expect($pago['pode_pagar_online'])->toBeFalse();
    expect($pago['pix'])->toBeNull();
    expect($pago['comprovante'])->toEqual(array('status' => 'aprovado', 'motivo' => null));

    $reprovado = PedidoPresenter::pedido(array('id' => 52, 'codigo' => 'Y', 'status' => 'aguardando_reenvio', 'total' => '10'), array(), array('status' => 'reprovado', 'analise_observacao' => 'Ilegível'), false);
    expect($reprovado['comprovante'])->toEqual(array('status' => 'reprovado', 'motivo' => 'Ilegível'));
    expect($reprovado['pode_pagar_online'])->toBeFalse();
    $pendente = PedidoPresenter::pedido(array('id' => 53, 'codigo' => 'Z', 'status' => 'comprovante_enviado', 'total' => '10'), array(), array('status' => 'pendente'), false);
    expect($pendente['comprovante']['status'])->toBe('em_analise');
});

it('certificado com URLs da API e da validação pública', function () {
    $c = CatalogoPresenter::certificado(array('codigo' => 'ABC123', 'curso_nome' => 'Curso', 'emitido_em' => '2026-10-01 10:00:00', 'curso_carga_horaria' => '40', 'validacao_url' => 'https://site/certificados/validar?codigo=ABC123'));
    expect($c['pdf_url'])->toBe('/api/app/v1/certificados/ABC123/pdf');
    expect($c['carga_horaria'])->toBe(40);
    // emitido_em é gravado pelo PHP (date()), em UTC na VPS: 10:00 UTC = 07:00 em Brasília
    expect($c['emitido_em'])->toBe('2026-10-01T07:00:00-03:00');
});

it('notificação: dados como objeto e lida como booleano', function () {
    $n = CatalogoPresenter::notificacao(array('id' => 1, 'tipo' => 'pedido_aprovado', 'titulo' => 'T', 'corpo' => 'C', 'dados' => '{"pedido_id":"50"}', 'lida_em' => null, 'created_at' => '2026-10-07 10:00:00'));
    expect($n['dados'])->toEqual(array('pedido_id' => '50'));
    expect($n['lida'])->toBeFalse();
    $vazia = CatalogoPresenter::notificacao(array('id' => 2, 'tipo' => 't', 'titulo' => 'T', 'corpo' => 'C', 'dados' => null, 'lida_em' => '2026-10-07 10:00:00', 'created_at' => null));
    expect(json_encode($vazia['dados']))->toBe('{}');
    expect($vazia['lida'])->toBeTrue();
});

it('catálogo: preço promocional só quando em promoção e menor', function () {
    $base = array('id' => 1, 'nome' => 'C', 'slug' => 'c', 'valor' => '100.00', 'valor_promocional' => '80.00', 'em_promocao' => 1, 'carga_horaria' => 10, 'modalidade' => 'online_ao_vivo');
    expect(CatalogoPresenter::cursoResumo($base)['preco_promocional_centavos'])->toBe(8000);
    $base['em_promocao'] = 0;
    expect(CatalogoPresenter::cursoResumo($base)['preco_promocional_centavos'])->toBeNull();
});

exit(testes_resumo());
