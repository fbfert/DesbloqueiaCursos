<?php

/**
 * Norminha — o chat pertence a quem está logado (regressão de 23/08/2026).
 *
 * O QUE ACONTECEU: o componente foi ao ar sem nenhuma checagem de sessão. Com
 * tutor_home=1, um visitante anônimo na home pública recebia o campo de
 * pergunta e as três ações de aluno ("Continuar de onde parei", "Ver meu
 * progresso", "Meu certificado"). Nada vazava — a API respondia
 * 'nao_autenticado' —, mas a tela oferecia o que o servidor ia recusar.
 *
 * O QUE ESTE TESTE PROTEGE: que a decisão de mostrar o chat venha da MESMA
 * fonte que a API usa para autorizar (Session::get('usuario_id')), e que o
 * visitante anônimo continue recebendo o componente antigo — avatar e fala —
 * sem campo, sem ações e sem token CSRF.
 *
 * Execução: php tests/Unit/norminha_visitante.php
 */

require_once __DIR__ . '/_bootstrap.php';

use App\Core\Session;
use App\Support\NorminhaHints;

// A sessao precisa existir ANTES da primeira linha de saida: Csrf::token(),
// chamado de dentro do componente, faz session_start(). Em CLI isso so
// funciona enquanto nada foi impresso, e describe() imprime.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION) || !is_array($_SESSION)) {
    $_SESSION = array();
}

/**
 * Renderiza o componente isolado e devolve o HTML.
 */
function norminha_render($usuarioId, $rota = '/')
{
    if ($usuarioId === null) {
        Session::forget('usuario_id');
    } else {
        Session::put('usuario_id', $usuarioId);
    }

    $tutorNorminha = array(
        'titulo' => 'Norminha',
        'texto' => 'Ola! Posso ajudar com seus cursos.',
        'contexto' => 'home',
        'estado_avatar' => 'speaking',
    );

    // O componente le as pistas de $norminhaContexto — a mesma variavel que os
    // layouts publicam. Passa-las dentro de $tutorNorminha nao tem efeito, e foi
    // assim que a primeira versao deste teste mediu sempre a rota '/'.
    $norminhaContexto = NorminhaHints::montar('home', array(), $rota);

    ob_start();
    require BASE_PATH . '/resources/views/components/tutor_norminha.php';
    return (string) ob_get_clean();
}

describe('Visitante anônimo');

it('recebe o componente, mas sem o chat', function () {
    $html = norminha_render(null);

    expect(strpos($html, 'id="norminha-tutor"') !== false)->toBeTrue();
    expect(strpos($html, 'Ola! Posso ajudar') !== false)->toBeTrue();

    foreach (array('data-norminha-form', 'data-norminha-input', 'data-norminha-quick',
                   'data-norminha-enviar', 'data-norminha-acao') as $marca) {
        if (strpos($html, $marca) !== false) {
            throw new RuntimeException("anonimo recebeu {$marca}");
        }
    }
});

it('é convidado a entrar, em vez de receber um campo que a API recusaria', function () {
    $html = norminha_render(null);
    expect(strpos($html, 'norminha-tutor__convite') !== false)->toBeTrue();
    expect(strpos($html, 'href="/login"') !== false)->toBeTrue();
});

it('não convida a entrar numa página que já é o login ou o cadastro', function () {
    foreach (array('/login', '/cadastro', '/recuperar-senha',
                   '/v2/login', '/v2/cadastro', '/v2/recuperar-senha/redefinir') as $rota) {
        $html = norminha_render(null, $rota);

        // A fala continua: é o que a Norminha sempre fez nessas telas.
        if (strpos($html, 'id="norminha-tutor"') === false) {
            throw new RuntimeException("perdeu o componente em {$rota}");
        }
        // O convite, não: seria circular pedir para entrar em quem está entrando.
        if (strpos($html, 'norminha-tutor__convite') !== false) {
            throw new RuntimeException("convite circular em {$rota}");
        }
        // E chat continua sendo coisa de quem tem sessão.
        if (strpos($html, 'data-norminha-form') !== false) {
            throw new RuntimeException("chat exposto em {$rota}");
        }
    }
    expect(true)->toBeTrue();
});

it('convida a entrar nas demais páginas públicas', function () {
    foreach (array('/', '/v2/', '/v2/catalogo', '/v2/quem-somos') as $rota) {
        $html = norminha_render(null, $rota);
        if (strpos($html, 'norminha-tutor__convite') === false) {
            throw new RuntimeException("faltou o convite em {$rota}");
        }
    }
    expect(true)->toBeTrue();
});

describe('Aluno logado');

it('recebe o chat completo', function () {
    $html = norminha_render(42);

    foreach (array('data-norminha-form', 'data-norminha-input', 'data-norminha-quick',
                   'data-norminha-enviar', 'data-norminha-acao="resume_course"',
                   'data-norminha-acao="show_progress"',
                   'data-norminha-acao="certificate_status"') as $marca) {
        if (strpos($html, $marca) === false) {
            throw new RuntimeException("aluno logado nao recebeu {$marca}");
        }
    }
    expect(strpos($html, 'norminha-tutor__convite') === false)->toBeTrue();
});

it('mantém launcher e minimizar nos dois estados — a casca não depende do chat', function () {
    foreach (array(null, 42) as $quem) {
        $html = norminha_render($quem);
        foreach (array('data-norminha-launcher', 'data-norminha-close', 'data-norminha-card') as $marca) {
            if (strpos($html, $marca) === false) {
                throw new RuntimeException('perdeu ' . $marca . ' para ' . var_export($quem, true));
            }
        }
    }
    expect(true)->toBeTrue();
});

exit(testes_resumo());
