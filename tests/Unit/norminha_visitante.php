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
function norminha_render($usuarioId)
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
        'hints' => array('contexto' => 'home', 'rota' => '/'),
    );

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
