<?php

/**
 * Norminha — o chat pertence a quem está logado (regressão de 23/08/2026).
 *
 * O QUE ACONTECEU EM 22/08: o componente foi ao ar sem nenhuma checagem de
 * sessão, e o visitante anônimo recebia as três ações de ALUNO ("Continuar de
 * onde parei", "Ver meu progresso", "Meu certificado"). Nada vazava — a API
 * respondia 'nao_autenticado' —, mas a tela oferecia o que o servidor ia
 * recusar.
 *
 * O QUE MUDOU EM 23/08: o visitante passou a ter atendimento próprio, porque é
 * ele quem mais precisa de ajuda — trava no cadastro, depois no login, depois
 * em como comprar. Ele agora TEM chat. O que não muda é a fronteira:
 *
 *   - as ações do visitante são as do serviço público (cadastro, acesso,
 *     catálogo) e NUNCA as do aluno;
 *   - o pedido dele vai para /api/norminha/publico, nunca para o /chat;
 *   - e o contexto de aluno (inscrição, curso, aula) não é sequer montado.
 *
 * A decisão de qual dos dois mundos mostrar vem da MESMA fonte que a API usa
 * para autorizar: Session::get('usuario_id'). Tela e servidor não podem
 * discordar sobre quem está falando.
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

it('recebe o chat, apontado para o endpoint público', function () {
    $html = norminha_render(null);

    expect(strpos($html, 'id="norminha-tutor"') !== false)->toBeTrue();
    expect(strpos($html, 'data-norminha-form') !== false)->toBeTrue();
    expect(strpos($html, 'data-publico="1"') !== false)->toBeTrue();
});

it('NUNCA recebe as ações de aluno', function () {
    $html = norminha_render(null);

    foreach (array('resume_course', 'show_progress', 'certificate_status',
                   'explain_current_lesson') as $acaoDeAluno) {
        if (strpos($html, 'data-norminha-acao="' . $acaoDeAluno . '"') !== false) {
            throw new RuntimeException("visitante recebeu a ação de aluno {$acaoDeAluno}");
        }
    }
    expect(true)->toBeTrue();
});

it('recebe os atalhos do atendimento público', function () {
    $html = norminha_render(null);

    foreach (array('ja_tenho_conta', 'quero_me_cadastrar', 'escolher_curso') as $atalho) {
        if (strpos($html, 'data-norminha-acao="' . $atalho . '"') === false) {
            throw new RuntimeException("faltou o atalho público {$atalho}");
        }
    }
    expect(true)->toBeTrue();
});

it('é atendido também nas telas de cadastro e login', function () {
    // É onde a dificuldade acontece. Suprimir a Norminha justamente ali seria
    // tirá-la do único lugar em que este atendimento existe para servir.
    foreach (array('/v2/cadastro', '/v2/login', '/v2/recuperar-senha') as $rota) {
        $html = norminha_render(null, $rota);
        if (strpos($html, 'data-norminha-form') === false) {
            throw new RuntimeException("sem chat em {$rota}");
        }
        if (strpos($html, 'data-publico="1"') === false) {
            throw new RuntimeException("chat de {$rota} não está no modo público");
        }
    }
    expect(true)->toBeTrue();
});

describe('Aluno logado');

it('recebe o chat do aluno, apontado para o endpoint autenticado', function () {
    $html = norminha_render(42);

    expect(strpos($html, 'data-publico="0"') !== false)->toBeTrue();
    foreach (array('data-norminha-form', 'data-norminha-input', 'data-norminha-quick',
                   'data-norminha-acao="resume_course"',
                   'data-norminha-acao="show_progress"',
                   'data-norminha-acao="certificate_status"') as $marca) {
        if (strpos($html, $marca) === false) {
            throw new RuntimeException("aluno logado não recebeu {$marca}");
        }
    }
});

it('não recebe os atalhos do atendimento público', function () {
    $html = norminha_render(42);

    foreach (array('ja_tenho_conta', 'quero_me_cadastrar') as $atalhoPublico) {
        if (strpos($html, 'data-norminha-acao="' . $atalhoPublico . '"') !== false) {
            throw new RuntimeException("aluno logado recebeu o atalho público {$atalhoPublico}");
        }
    }
    expect(true)->toBeTrue();
});

it('mantém launcher e minimizar nos dois estados', function () {
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

describe('O JavaScript respeita a fronteira');

it('escolhe o endpoint pelo que o servidor disse, não por conta própria', function () {
    $js = (string) file_get_contents(BASE_PATH . '/assets/js/tutor-norminha.js');

    if (strpos($js, "getAttribute('data-publico')") === false) {
        throw new RuntimeException('o JS não lê data-publico');
    }
    if (strpos($js, '/api/norminha/publico') === false) {
        throw new RuntimeException('o JS não conhece o endpoint público');
    }
    expect(true)->toBeTrue();
});

exit(testes_resumo());
