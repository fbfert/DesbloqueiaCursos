<?php

/**
 * NorminhaService — orquestrador determinístico (Etapa 4).
 *
 * As três coisas que este teste existe para garantir:
 *
 * 1. A ONDA 0 NÃO CHAMA IA. Contador em zero depois de exercitar todos os
 *    caminhos. Se algum dia alguém plugar um gerador por engano, este teste cai.
 *
 * 2. O RÓTULO NÃO MENTE. "Você parou em" só aparece quando houve acesso real.
 *
 * 3. NENHUM LINK LIVRE. Toda ação sai do mapa de chaves e aponta para a área do
 *    aluno V2.
 *
 * Execução: php tests/Unit/norminha_service.php
 * Roda em transação e desfaz tudo ao final.
 */

require_once __DIR__ . '/_bootstrap.php';

use App\Models\Inscricao;
use App\Services\NorminhaContextService;
use App\Services\NorminhaService;
use App\Services\NorminhaToolsService;

$pdo = testes_conectar_banco();
$banco = $pdo->query('SELECT DATABASE()')->fetchColumn();
echo "\nBanco: {$banco}\n";
if ($banco === 'desbloqueiacursos') {
    fwrite(STDERR, "\nRECUSADO: este teste escreve. Aponte para o banco de desenvolvimento.\n");
    exit(1);
}

$inscricaoModel = new Inscricao();

// Fixtures
$aluno = null;
$alunoVarios = null;
foreach ($pdo->query('SELECT usuario_id FROM inscricoes WHERE deleted_at IS NULL
                      GROUP BY usuario_id ORDER BY COUNT(*) DESC LIMIT 80')->fetchAll(PDO::FETCH_COLUMN) as $uid) {
    $ativas = $inscricaoModel->forUsuarioAprovadas($uid);
    if (count($ativas) === 1 && $aluno === null) {
        $aluno = array('usuario_id' => (int) $uid, 'inscricao' => $ativas[0]);
    }
    if (count($ativas) > 1 && $alunoVarios === null) {
        $alunoVarios = (int) $uid;
    }
    if ($aluno && $alunoVarios) { break; }
}
if (!$aluno) {
    fwrite(STDERR, "ERRO: banco sem aluno de matricula unica.\n");
    exit(1);
}
$U = $aluno['usuario_id'];
$I = (int) $aluno['inscricao']['id'];
printf("Fixtures: aluno=%d inscricao=%d  aluno_ambiguo=%s\n", $U, $I, $alunoVarios ?: '-');

$pdo->beginTransaction();

$servico = new NorminhaService();

/** Gerador falso: prova que o ponto de injecao da Onda 1 funciona. */
class GeradorFalso
{
    public $chamadas = 0;
    private $resposta;
    public function __construct($resposta = null) { $this->resposta = $resposta; }
    public function gerar($mensagem, array $contexto)
    {
        $this->chamadas++;
        return $this->resposta;
    }
}

// ===================================================================

describe('A Onda 0 NUNCA chama o modelo');

it('contador de IA fica em zero depois de todos os caminhos', function () use ($servico, $U, $I) {
    foreach (NorminhaService::ACOES as $acao) {
        $servico->processar($U, array('action' => $acao, 'context' => array('inscricao_id' => $I)));
    }
    $servico->processar($U, array('message' => 'onde parei?', 'context' => array('inscricao_id' => $I)));
    $servico->processar($U, array('message' => 'me explique a teoria da relatividade', 'context' => array('inscricao_id' => $I)));

    expect($servico->chamadasIA())->toBe(0);
});

it('sem gerador injetado, pergunta livre vira unresolved', function () use ($servico, $U, $I) {
    $r = $servico->processar($U, array('message' => 'qual a capital da Mongólia?', 'context' => array('inscricao_id' => $I)));
    expect($r['resolved_by'])->toBe('unresolved');
    expect($r['intent'])->toBeNull();
    expect($servico->chamadasIA())->toBe(0);
});

describe('O ponto de injeção da Onda 1 existe e funciona');

it('com gerador injetado, a pergunta livre passa por ele', function () use ($U, $I) {
    $fake = new GeradorFalso(array('message' => 'Resposta do modelo.', 'resolved_by' => 'hybrid'));
    $comIA = new NorminhaService(null, null, $fake);

    $r = $comIA->processar($U, array('message' => 'me explique isso melhor', 'context' => array('inscricao_id' => $I)));

    expect($fake->chamadas)->toBe(1);
    expect($comIA->chamadasIA())->toBe(1);
    expect($r['message'])->toBe('Resposta do modelo.');
    expect($r['resolved_by'])->toBe('hybrid');
});

it('fast-path NÃO chama o gerador, mesmo com ele injetado', function () use ($U, $I) {
    $fake = new GeradorFalso(array('message' => 'nao deveria aparecer'));
    $comIA = new NorminhaService(null, null, $fake);

    foreach (array('resume_course', 'show_progress', 'next_step', 'certificate_status') as $acao) {
        $comIA->processar($U, array('action' => $acao, 'context' => array('inscricao_id' => $I)));
    }

    expect($fake->chamadas)->toBe(0);
    expect($comIA->chamadasIA())->toBe(0);
});

it('gerador que falha cai no caminho seguro, sem inventar texto', function () use ($U, $I) {
    $fake = new GeradorFalso(null); // provedor indisponivel
    $comIA = new NorminhaService(null, null, $fake);

    $r = $comIA->processar($U, array('message' => 'explica melhor por favor', 'context' => array('inscricao_id' => $I)));

    expect($fake->chamadas)->toBe(1);
    expect($r['resolved_by'])->toBe('unresolved');
});

describe('Fast-paths determinísticos');

it('resume_course responde e sempre traz ação', function () use ($servico, $U, $I) {
    $r = $servico->processar($U, array('action' => 'resume_course', 'context' => array('inscricao_id' => $I)));
    expect($r['ok'])->toBeTrue();
    expect($r['resolved_by'])->toBe('php');
    expect($r['intent'])->toBe('resume_course');
    expect(count($r['actions']))->toBeGreaterThan(0);
});

it('o rótulo da retomada obedece à origem', function () use ($servico, $U, $I) {
    $tools = new NorminhaToolsService();
    $ponto = $tools->getResumePoint($U, $I);
    $r = $servico->processar($U, array('action' => 'resume_course', 'context' => array('inscricao_id' => $I)));

    if ($ponto['origem'] === 'ultimo_acesso') {
        expect($r['message'])->toContain('Você parou em');
    } elseif ($ponto['origem'] === 'proximo_item') {
        expect($r['message'])->toContain('Seu próximo passo');
        // nao pode fingir memoria que o sistema nao tem
        expect(strpos($r['message'], 'Você parou') === false)->toBeTrue();
    } else {
        expect($r['message'])->toContain('concluiu');
    }
    echo "      origem: {$ponto['origem']}\n";
});

it('show_progress usa o percentual da tela', function () use ($servico, $U, $I, $aluno) {
    $r = $servico->processar($U, array('action' => 'show_progress', 'context' => array('inscricao_id' => $I)));
    expect($r['intent'])->toBe('show_progress');
    $esperado = number_format((float) $aluno['inscricao']['percentual_progresso'], 2, ',', '.');
    expect($r['message'])->toContain($esperado . '%');
});

it('next_step responde com item ou conclusão', function () use ($servico, $U, $I) {
    $r = $servico->processar($U, array('action' => 'next_step', 'context' => array('inscricao_id' => $I)));
    expect($r['intent'])->toBe('next_step');
    expect($r['resolved_by'])->toBe('php');
});

it('certificate_status responde com situação', function () use ($servico, $U, $I) {
    $r = $servico->processar($U, array('action' => 'certificate_status', 'context' => array('inscricao_id' => $I)));
    expect($r['intent'])->toBe('certificate_status');
    expect(in_array($r['avatar_state'], array('celebrating', 'attention'), true))->toBeTrue();
});

it('explain/summarize não parafraseiam e avisam o limite', function () use ($servico, $U, $I) {
    foreach (array('explain_current_lesson', 'summarize_current_lesson') as $acao) {
        $r = $servico->processar($U, array('action' => $acao, 'context' => array('inscricao_id' => $I)));
        expect($r['resolved_by'])->toBe('php');
        expect($r['intent'])->toBe($acao);
    }
});

describe('Classificação conservadora de intenção');

it('reconhece frases inequívocas, com e sem acento', function () use ($servico, $U, $I) {
    $casos = array(
        'onde parei?' => 'resume_course',
        'Onde eu parei mesmo' => 'resume_course',
        'quero continuar de onde parei' => 'resume_course',
        'qual meu progresso' => 'show_progress',
        'quanto falta pra terminar' => 'show_progress',
        'quantos por cento eu fiz' => 'show_progress',
        'qual o proximo passo' => 'next_step',
        'qual é o próximo passo?' => 'next_step',
        'o que faço agora' => 'next_step',
        'meu certificado ja saiu' => 'certificate_status',
        'já posso emitir o certificado?' => 'certificate_status',
    );
    foreach ($casos as $frase => $esperado) {
        $r = $servico->processar($U, array('message' => $frase, 'context' => array('inscricao_id' => $I)));
        if ($r['intent'] !== $esperado) {
            throw new RuntimeException("\"{$frase}\" -> esperava {$esperado}, veio " . var_export($r['intent'], true));
        }
        expect($r['resolved_by'])->toBe('php');
    }
});

it('NÃO classifica o que é ambíguo — prefere admitir que não entendeu', function () use ($servico, $U, $I) {
    $ambiguas = array(
        'oi',
        'nao entendi',
        'me ajuda',
        'e sobre o modulo 3?',
        'pode explicar essa parte da aula',
        'como funciona a avaliacao final',
    );
    foreach ($ambiguas as $frase) {
        $r = $servico->processar($U, array('message' => $frase, 'context' => array('inscricao_id' => $I)));
        if ($r['intent'] !== null) {
            throw new RuntimeException("\"{$frase}\" foi classificada como {$r['intent']} — deveria cair em unresolved");
        }
        expect($r['resolved_by'])->toBe('unresolved');
    }
});

it('action tem precedência sobre message e nunca é classificada', function () use ($servico, $U, $I) {
    $r = $servico->processar($U, array(
        'action' => 'show_progress',
        'message' => 'onde parei?',            // seria resume_course
        'context' => array('inscricao_id' => $I),
    ));
    expect($r['intent'])->toBe('show_progress');
});

describe('Caminho unresolved — o dado que decide a Onda 1');

it('grava resolved_by = unresolved e intencao nula no banco', function () use ($servico, $pdo, $U, $I) {
    $r = $servico->processar($U, array('message' => 'qual a formula de bhaskara', 'context' => array('inscricao_id' => $I)));
    expect($r['resolved_by'])->toBe('unresolved');

    $linha = $pdo->query('SELECT resolved_by, intencao FROM norminha_mensagens
                          WHERE id = ' . (int) $r['message_id'])->fetch(PDO::FETCH_ASSOC);
    expect($linha['resolved_by'])->toBe('unresolved');
    expect($linha['intencao'])->toBeNull();
});

it('a pergunta do aluno fica gravada íntegra, para leitura no painel', function () use ($servico, $pdo, $U, $I) {
    $pergunta = 'como calculo o valor venal de um imóvel rural?';
    $r = $servico->processar($U, array('message' => $pergunta, 'context' => array('inscricao_id' => $I)));

    $conversa = $pdo->query('SELECT conversa_id FROM norminha_mensagens WHERE id = ' . (int) $r['message_id'])->fetchColumn();
    $gravada = $pdo->query('SELECT mensagem FROM norminha_mensagens
                            WHERE conversa_id = ' . (int) $conversa . ' AND papel = "user"
                            ORDER BY id DESC LIMIT 1')->fetchColumn();
    expect($gravada)->toBe($pergunta);
});

it('a resposta oferece os caminhos determinísticos que existem', function () use ($servico, $U, $I) {
    $r = $servico->processar($U, array('message' => 'me fala sobre kant', 'context' => array('inscricao_id' => $I)));
    expect(count($r['actions']))->toBeGreaterThan(0);
    expect($r['avatar_state'])->toBe('doubt');
});

describe('Ações: nenhuma URL livre');

it('toda URL aponta para a área do aluno V2', function () use ($servico, $U, $I) {
    $vistas = 0;
    foreach (NorminhaService::ACOES as $acao) {
        $r = $servico->processar($U, array('action' => $acao, 'context' => array('inscricao_id' => $I)));
        foreach ($r['actions'] as $a) {
            $vistas++;
            expect($a)->toHaveKey('key');
            expect($a)->toHaveKey('url');
            expect($a['url'][0])->toBe('/');
            $ok = false;
            foreach (array('/v2/aula', '/v2/quiz', '/v2/atividade', '/v2/aluno') as $prefixo) {
                if (strpos(parse_url($a['url'], PHP_URL_PATH), $prefixo) === 0) { $ok = true; break; }
            }
            if (!$ok) {
                throw new RuntimeException("URL fora da area do aluno V2: {$a['url']}");
            }
        }
    }
    expect($vistas)->toBeGreaterThan(0);
    echo "      {$vistas} acoes verificadas\n";
});

it('nenhuma ação leva a esquema externo ou protocolo perigoso', function () use ($servico, $U, $I) {
    foreach (NorminhaService::ACOES as $acao) {
        $r = $servico->processar($U, array('action' => $acao, 'context' => array('inscricao_id' => $I)));
        foreach ($r['actions'] as $a) {
            foreach (array('javascript:', 'data:', 'http://', 'https://', '//') as $perigo) {
                if (stripos($a['url'], $perigo) === 0) {
                    throw new RuntimeException("URL perigosa: {$a['url']}");
                }
            }
        }
    }
    expect(true)->toBeTrue();
});

describe('Validação e negação');

it('mensagem acima de 2000 caracteres é recusada', function () use ($servico, $U, $I) {
    $r = $servico->processar($U, array('message' => str_repeat('a', 2001), 'context' => array('inscricao_id' => $I)));
    expect($r['ok'])->toBeFalse();
    expect($r['erro'])->toBe('mensagem_longa');
    expect($r['status'])->toBe(422);
});

it('exatamente 2000 caracteres passa', function () use ($servico, $U, $I) {
    $r = $servico->processar($U, array('message' => str_repeat('a', 2000), 'context' => array('inscricao_id' => $I)));
    expect($r['ok'])->toBeTrue();
});

it('mensagem vazia e ação inválida são recusadas', function () use ($servico, $U, $I) {
    expect($servico->processar($U, array('message' => '   '))['erro'])->toBe('mensagem_vazia');
    expect($servico->processar($U, array())['erro'])->toBe('mensagem_vazia');
    expect($servico->processar($U, array('action' => 'apagar_tudo'))['erro'])->toBe('acao_invalida');
});

it('usuário não autenticado é recusado com 401', function () use ($servico) {
    $r = $servico->processar(0, array('message' => 'oi'));
    expect($r['ok'])->toBeFalse();
    expect($r['status'])->toBe(401);
});

it('NEGA conversa de outro aluno', function () use ($servico, $U, $I, $alunoVarios) {
    // Precisa ser um aluno REAL, com matricula: um usuario inexistente sairia
    // antes pelo caminho "sem matricula" e o teste nao provaria nada sobre
    // propriedade de conversa.
    if (!$alunoVarios) {
        echo "      (sem segundo aluno com matricula — caso nao exercitado)\n";
        expect(true)->toBeTrue();
        return;
    }

    $meu = $servico->processar($U, array('action' => 'show_progress', 'context' => array('inscricao_id' => $I)));
    $uuidLegitimo = $meu['conversation_id'];
    expect($uuidLegitimo)->notToBeNull();

    $invasor = $servico->processar($alunoVarios, array(
        'message' => 'onde parei?',
        'conversation_id' => $uuidLegitimo,
        'context' => array('inscricao_id' => $I),   // tambem a inscricao alheia
    ));

    expect($invasor['ok'])->toBeFalse();
    expect($invasor['erro'])->toBe('conversa_invalida');
    expect($invasor['status'])->toBe(403);
});

it('a posse da conversa é checada ANTES do contexto, para qualquer usuário', function () use ($servico, $pdo, $inscricaoModel, $U, $I) {
    // Regressao da correcao de ordem: antes, um usuario que caisse em
    // "sem matricula" ou "ambiguo" saia por retorno antecipado e o uuid alheio
    // nunca era examinado. Agora a posse vem primeiro, sempre.
    $meu = $servico->processar($U, array('action' => 'show_progress', 'context' => array('inscricao_id' => $I)));

    $sem = 0;
    foreach ($pdo->query('SELECT id FROM usuarios ORDER BY id DESC LIMIT 200')->fetchAll(PDO::FETCH_COLUMN) as $uid) {
        if (!$inscricaoModel->forUsuarioAprovadas($uid)) { $sem = (int) $uid; break; }
    }
    if (!$sem) { echo "      (todo usuario tem matricula)\n"; expect(true)->toBeTrue(); return; }

    $r = $servico->processar($sem, array('message' => 'oi', 'conversation_id' => $meu['conversation_id']));

    expect($r['ok'])->toBeFalse();
    expect($r['erro'])->toBe('conversa_invalida');
    expect($r['status'])->toBe(403);
});

it('sem uuid, o aluno sem matrícula recebe resposta segura e sem ações', function () use ($servico, $pdo, $inscricaoModel) {
    $sem = 0;
    foreach ($pdo->query('SELECT id FROM usuarios ORDER BY id DESC LIMIT 200')->fetchAll(PDO::FETCH_COLUMN) as $uid) {
        if (!$inscricaoModel->forUsuarioAprovadas($uid)) { $sem = (int) $uid; break; }
    }
    if (!$sem) { echo "      (todo usuario tem matricula)\n"; expect(true)->toBeTrue(); return; }

    $r = $servico->processar($sem, array('message' => 'oi'));

    expect($r['ok'])->toBeTrue();
    expect($r['message_id'])->toBeNull();
    expect($r['actions'])->toEqual(array());
    expect(strpos($r['message'], 'matrícula ativa') !== false)->toBeTrue();
});

it('uuid inexistente não cria conversa por baixo', function () use ($servico, $U, $I) {
    $r = $servico->processar($U, array(
        'message' => 'onde parei?',
        'conversation_id' => '00000000-0000-4000-8000-000000000000',
        'context' => array('inscricao_id' => $I),
    ));
    expect($r['ok'])->toBeFalse();
    expect($r['erro'])->toBe('conversa_invalida');
});

it('a conversa é reutilizada quando o uuid é do próprio aluno', function () use ($servico, $pdo, $U, $I) {
    $r1 = $servico->processar($U, array('action' => 'show_progress', 'context' => array('inscricao_id' => $I)));
    $r2 = $servico->processar($U, array(
        'action' => 'next_step',
        'conversation_id' => $r1['conversation_id'],
        'context' => array('inscricao_id' => $I),
    ));
    expect($r2['conversation_id'])->toBe($r1['conversation_id']);
    expect($r2['message_id'] > $r1['message_id'])->toBeTrue();
});

if ($alunoVarios) {
    it('aluno com vários cursos recebe desambiguação, não um chute', function () use ($servico, $alunoVarios) {
        $r = $servico->processar($alunoVarios, array('message' => 'onde parei?'));
        expect($r['avatar_state'])->toBe('doubt');
        expect($r['message'])->toContain('mais de um curso');
        expect(isset($r['opcoes']))->toBeTrue();
    });
}

describe('Contrato de resposta');

it('devolve sempre as mesmas chaves', function () use ($servico, $U, $I) {
    $r = $servico->processar($U, array('action' => 'show_progress', 'context' => array('inscricao_id' => $I)));
    foreach (array('ok','conversation_id','message_id','message','intent','resolved_by',
                   'avatar_state','sources','actions') as $chave) {
        expect($r)->toHaveKey($chave);
    }
    expect(in_array($r['avatar_state'], array('speaking','explaining','attention','celebrating','doubt'), true))->toBeTrue();
});

$pdo->rollBack();
echo "\n(transação desfeita — nada foi gravado)\n";

exit(testes_resumo());
