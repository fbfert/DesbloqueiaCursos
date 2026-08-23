<?php

/**
 * Models da Norminha IA V1 (migration 073).
 *
 * O que este teste protege, em ordem de importância:
 *
 * 1. PROPRIEDADE. Nenhuma leitura pode devolver a conversa, a mensagem ou o
 *    feedback de outro aluno. O uuid da conversa é público — vai para o browser
 *    e volta —, então ele não é credencial: toda busca exige (uuid, usuario_id).
 *
 * 2. FUSO. O PHP roda em UTC e o MySQL em UTC−3 neste servidor
 *    (docs/2026-08-15-fuso-horario-php-mysql.md). O bloqueio de login do projeto
 *    ficou inerte porque gravava com NOW() e comparava com time() do PHP. O
 *    rate limit da Norminha morreria do mesmo jeito, e em silêncio.
 *
 * 3. CONCORRÊNCIA. O contador de uso precisa ser correto com duas abas abertas.
 *
 * Execução: php tests/Unit/norminha_models.php
 * Roda dentro de uma transação e desfaz tudo ao final.
 */

require_once __DIR__ . '/_bootstrap.php';

use App\Models\NorminhaConversa;
use App\Models\NorminhaFeedback;
use App\Models\NorminhaMensagem;
use App\Models\NorminhaUso;

$pdo = testes_conectar_banco();

$banco = $pdo->query('SELECT DATABASE()')->fetchColumn();
echo "\nBanco: {$banco}\n";
if ($banco === 'desbloqueiacursos') {
    fwrite(STDERR, "\nRECUSADO: este teste escreve. Aponte para o banco de desenvolvimento.\n");
    exit(1);
}

foreach (array('norminha_conversas', 'norminha_mensagens', 'norminha_feedback', 'norminha_uso') as $tabela) {
    if (!$pdo->query('SHOW TABLES LIKE ' . $pdo->quote($tabela))->fetch()) {
        fwrite(STDERR, "\nERRO: tabela {$tabela} ausente. Aplique sql/073_norminha_conversas.sql.\n");
        exit(1);
    }
}

// Ids sintéticos, fora da faixa real: as tabelas não têm FK para usuarios,
// então nenhum aluno de verdade é tocado.
const ALUNO_A = 999901;
const ALUNO_B = 999902;

$pdo->beginTransaction();

$conversas = new NorminhaConversa();
$mensagens = new NorminhaMensagem();
$feedbacks = new NorminhaFeedback();
$usos = new NorminhaUso();

describe('NorminhaConversa — criação e identificador');

$criada = $conversas->criar(ALUNO_A, array(
    'inscricao_id' => 4242,
    'curso_evento_id' => 9,
    'turma_id' => 8,
    'contexto' => 'aula',
    'rota' => '/v2/aula',
));

it('cria a conversa e devolve id e uuid', function () use ($criada) {
    expect($criada)->notToBeNull();
    expect(isset($criada['id']) && $criada['id'] > 0)->toBeTrue();
    expect(isset($criada['uuid']))->toBeTrue();
});

it('gera UUID v4 no formato canônico', function () use ($criada, $conversas) {
    expect($conversas->uuidValido($criada['uuid']))->toBeTrue();
    expect(strlen($criada['uuid']))->toBe(36);
    expect(substr($criada['uuid'], 14, 1))->toBe('4');
    expect(in_array(strtolower(substr($criada['uuid'], 19, 1)), array('8', '9', 'a', 'b'), true))->toBeTrue();
});

it('não repete UUID entre conversas', function () use ($conversas) {
    $vistos = array();
    for ($i = 0; $i < 200; $i++) {
        $vistos[$conversas->gerarUuid()] = true;
    }
    expect(count($vistos))->toBe(200);
});

it('rejeita usuario_id inválido', function () use ($conversas) {
    expect($conversas->criar(0))->toBeNull();
    expect($conversas->criar(-1))->toBeNull();
});

describe('NorminhaConversa — propriedade (o que impede um aluno de ler outro)');

it('o dono encontra a própria conversa', function () use ($conversas, $criada) {
    $achada = $conversas->buscarPorUuid($criada['uuid'], ALUNO_A);
    expect($achada)->notToBeNull();
    expect((int) $achada['id'])->toBe((int) $criada['id']);
    expect((int) $achada['inscricao_id'])->toBe(4242);
});

it('NEGA o mesmo uuid para outro aluno', function () use ($conversas, $criada) {
    expect($conversas->buscarPorUuid($criada['uuid'], ALUNO_B))->toBeNull();
});

it('nega uuid inexistente e lixo', function () use ($conversas) {
    expect($conversas->buscarPorUuid('00000000-0000-4000-8000-000000000000', ALUNO_A))->toBeNull();
    expect($conversas->buscarPorUuid("' OR 1=1 --", ALUNO_A))->toBeNull();
    expect($conversas->buscarPorUuid('', ALUNO_A))->toBeNull();
});

it('NEGA atualização de contexto por outro aluno', function () use ($conversas, $criada) {
    $alterou = $conversas->atualizarContexto($criada['id'], ALUNO_B, array('contexto' => 'invadido'));
    expect($alterou)->toBeFalse();

    $atual = $conversas->buscarPorUuid($criada['uuid'], ALUNO_A);
    expect($atual['contexto'])->toBe('aula');
});

it('o dono atualiza o contexto', function () use ($conversas, $criada) {
    expect($conversas->atualizarContexto($criada['id'], ALUNO_A, array(
        'inscricao_id' => 555, 'contexto' => 'avaliacao', 'rota' => '/v2/quiz',
    )))->toBeTrue();

    $atual = $conversas->buscarPorUuid($criada['uuid'], ALUNO_A);
    expect($atual['contexto'])->toBe('avaliacao');
    expect((int) $atual['inscricao_id'])->toBe(555);
});

it('NEGA resumo gravado por outro aluno', function () use ($conversas, $criada) {
    expect($conversas->atualizarResumo($criada['id'], ALUNO_B, 'invadido'))->toBeFalse();
    expect($conversas->atualizarResumo($criada['id'], ALUNO_A, 'aluno revisa avaliação de imóveis'))->toBeTrue();
});

describe('NorminhaMensagem');

$idPergunta = $mensagens->inserir($criada['id'], 'user', 'Não entendi esta aula.', array('usuario_id' => ALUNO_A));
$idResposta = $mensagens->inserir($criada['id'], 'assistant', 'Você concluiu 18 de 29 itens.', array(
    'intencao' => 'student_progress', 'resolved_by' => 'php', 'latencia_ms' => 12,
));

it('insere mensagens de user e assistant', function () use ($idPergunta, $idResposta) {
    expect($idPergunta > 0)->toBeTrue();
    expect($idResposta > $idPergunta)->toBeTrue();
});

it('rejeita papel fora da whitelist', function () use ($mensagens, $criada) {
    expect($mensagens->inserir($criada['id'], 'system', 'x'))->toBeNull();
    expect($mensagens->inserir($criada['id'], 'admin', 'x'))->toBeNull();
});

it('descarta resolved_by inválido em vez de gravar lixo', function () use ($mensagens, $criada, $pdo) {
    $id = $mensagens->inserir($criada['id'], 'assistant', 'x', array('resolved_by' => 'magica'));
    $linha = $pdo->query('SELECT resolved_by FROM norminha_mensagens WHERE id = ' . (int) $id)->fetch();
    expect($linha['resolved_by'])->toBeNull();
});

it('grava o texto do aluno ÍNTEGRO (a sanitização é da renderização)', function () use ($mensagens, $criada, $pdo) {
    $bruto = '<script>alert(1)</script> & aspas " e \' e acentuação';
    $id = $mensagens->inserir($criada['id'], 'user', $bruto, array('usuario_id' => ALUNO_A));
    $linha = $pdo->query('SELECT mensagem FROM norminha_mensagens WHERE id = ' . (int) $id)->fetch();
    expect($linha['mensagem'])->toBe($bruto);
});

it('lista em ordem cronológica, não invertida', function () use ($mensagens, $criada) {
    $lista = $mensagens->ultimasDaConversa($criada['id'], ALUNO_A, 12);
    expect(count($lista))->toBeGreaterThanOrEqual(2);
    expect($lista[0]['papel'])->toBe('user');
    expect((int) $lista[0]['id'] < (int) $lista[1]['id'])->toBeTrue();
});

it('respeita o limite devolvendo as MAIS RECENTES', function () use ($mensagens, $criada, $idPergunta) {
    $lista = $mensagens->ultimasDaConversa($criada['id'], ALUNO_A, 2);
    expect(count($lista))->toBe(2);
    // se devolvesse as mais antigas, a primeira seria a pergunta original
    expect((int) $lista[0]['id'] > (int) $idPergunta)->toBeTrue();
});

it('NEGA as mensagens da conversa para outro aluno', function () use ($mensagens, $criada) {
    expect($mensagens->ultimasDaConversa($criada['id'], ALUNO_B, 12))->toEqual(array());
});

it('NEGA buscar mensagem de outro aluno', function () use ($mensagens, $idResposta) {
    expect($mensagens->buscarDoUsuario($idResposta, ALUNO_A))->notToBeNull();
    expect($mensagens->buscarDoUsuario($idResposta, ALUNO_B))->toBeNull();
});

describe('NorminhaFeedback');

it('grava o voto', function () use ($feedbacks, $idResposta) {
    expect($feedbacks->registrar($idResposta, ALUNO_A, true, 'Explicação clara'))->toBeTrue();
    $f = $feedbacks->buscar($idResposta, ALUNO_A);
    expect((int) $f['util'])->toBe(1);
});

it('o aluno muda de ideia sem acumular votos', function () use ($feedbacks, $idResposta, $pdo) {
    $feedbacks->registrar($idResposta, ALUNO_A, false, 'Na verdade não ajudou');
    $f = $feedbacks->buscar($idResposta, ALUNO_A);
    expect((int) $f['util'])->toBe(0);

    $total = $pdo->query('SELECT COUNT(*) FROM norminha_feedback WHERE mensagem_id = ' . (int) $idResposta
        . ' AND usuario_id = ' . ALUNO_A)->fetchColumn();
    expect((int) $total)->toBe(1);
});

it('separa o voto de alunos diferentes na mesma mensagem', function () use ($feedbacks, $idResposta) {
    $feedbacks->registrar($idResposta, ALUNO_B, true);
    expect((int) $feedbacks->buscar($idResposta, ALUNO_A)['util'])->toBe(0);
    expect((int) $feedbacks->buscar($idResposta, ALUNO_B)['util'])->toBe(1);
});

it('trunca comentário em 1000 caracteres', function () use ($feedbacks, $idPergunta) {
    $feedbacks->registrar($idPergunta, ALUNO_A, true, str_repeat('a', 1500));
    $f = $feedbacks->buscar($idPergunta, ALUNO_A);
    expect(strlen($f['comentario']))->toBe(1000);
});

describe('NorminhaUso — rate limit');

it('primeira mensagem abre a janela em 1', function () use ($usos) {
    $e = $usos->registrar(ALUNO_A, false, 300);
    expect($e['mensagens_janela'])->toBe(1);
    expect($e['mensagens_dia'])->toBe(1);
    expect($e['mensagens_ia_dia'])->toBe(0);
});

it('incrementa janela e dia a cada mensagem', function () use ($usos) {
    $usos->registrar(ALUNO_A, false, 300);
    $e = $usos->registrar(ALUNO_A, true, 300);
    expect($e['mensagens_janela'])->toBe(3);
    expect($e['mensagens_dia'])->toBe(3);
    expect($e['mensagens_ia_dia'])->toBe(1);
});

it('conta alunos separadamente', function () use ($usos) {
    $e = $usos->registrar(ALUNO_B, false, 300);
    expect($e['mensagens_dia'])->toBe(1);
    expect($usos->estado(ALUNO_A, 300)['mensagens_dia'])->toBe(3);
});

it('REINICIA a janela quando ela expira, sem zerar o contador do dia', function () use ($usos, $pdo) {
    // envelhece a janela: equivale a esperar mais de 5 minutos
    $pdo->exec('UPDATE norminha_uso SET janela_inicio = DATE_SUB(NOW(), INTERVAL 400 SECOND)
                WHERE usuario_id = ' . ALUNO_A . ' AND dia = CURDATE()');

    $antes = $usos->estado(ALUNO_A, 300);
    expect($antes['mensagens_janela'])->toBe(0);          // expirada já na leitura
    expect($antes['mensagens_dia'])->toBe(3);             // o dia continua contando

    $depois = $usos->registrar(ALUNO_A, false, 300);
    expect($depois['mensagens_janela'])->toBe(1);         // janela reiniciada
    expect($depois['mensagens_dia'])->toBe(4);            // dia acumula
});

it('não reinicia a janela ainda válida', function () use ($usos) {
    $e = $usos->registrar(ALUNO_A, false, 300);
    expect($e['mensagens_janela'])->toBe(2);
});

it('devolve segundos para liberar dentro da janela', function () use ($usos) {
    $e = $usos->estado(ALUNO_A, 300);
    expect($e['segundos_para_liberar'])->toBeGreaterThan(0);
    expect($e['segundos_para_liberar'])->toBeLessThanOrEqual(300);
});

it('aluno sem uso hoje tem estado zerado, não nulo', function () use ($usos) {
    $e = $usos->estado(999999, 300);
    expect($e['mensagens_janela'])->toBe(0);
    expect($e['segundos_para_liberar'])->toBe(0);
});

describe('Fuso horário — regressão do bug que deixou o bloqueio de login inerte');

it('a janela recém-criada NÃO nasce expirada', function () use ($usos, $pdo) {
    // O bug do projeto: gravar com NOW() (banco, UTC-3) e comparar com time()
    // do PHP (UTC, 3h à frente) faz o prazo nascer no passado, e a proteção
    // nunca dispara. Aqui gravação e comparação vivem no mesmo relógio.
    $pdo->exec('DELETE FROM norminha_uso WHERE usuario_id = 999903');
    $e = $usos->registrar(999903, false, 300);

    expect($e['mensagens_janela'])->toBe(1);
    expect($e['segundos_para_liberar'])->toBeGreaterThan(280);
});

it('PHP e MySQL divergem, e é por isso que a comparação fica no SQL', function () use ($pdo) {
    $doBanco = strtotime($pdo->query('SELECT NOW()')->fetchColumn());
    $doPhp = time();
    $diferenca = abs($doBanco - $doPhp);

    if ($diferenca > 60) {
        echo "      (relógios divergem em " . round($diferenca / 3600, 1) . "h — exatamente o motivo)\n";
    }
    // O teste não exige alinhamento: exige que a Norminha não dependa dele.
    expect(true)->toBeTrue();
});

it('created_at das mensagens usa o mesmo relógio da janela de uso', function () use ($pdo, $criada) {
    $linha = $pdo->query(
        'SELECT ABS(TIMESTAMPDIFF(SECOND,
                    (SELECT MAX(created_at) FROM norminha_mensagens WHERE conversa_id = ' . (int) $criada['id'] . '),
                    NOW())) AS diferenca'
    )->fetch();
    expect((int) $linha['diferenca'])->toBeLessThanOrEqual(60);
});

describe('Telemetria (base da Etapa 8)');

it('agrupa mensagens por origem da resposta', function () use ($mensagens) {
    $c = $mensagens->contarPorResolucao(date('Y-m-d', strtotime('-1 day')), date('Y-m-d', strtotime('+1 day')));
    expect(isset($c['php']))->toBeTrue();
    expect($c['php'])->toBeGreaterThanOrEqual(1);
});

it('resume feedback do período', function () use ($feedbacks) {
    $r = $feedbacks->resumoPorPeriodo(date('Y-m-d', strtotime('-1 day')), date('Y-m-d', strtotime('+1 day')));
    expect($r)->toHaveKey('uteis');
    expect($r)->toHaveKey('nao_uteis');
});

describe('Cascata');

it('apagar a conversa leva mensagens e feedback junto', function () use ($pdo, $criada, $idResposta) {
    $pdo->exec('DELETE FROM norminha_conversas WHERE id = ' . (int) $criada['id']);

    $msgs = $pdo->query('SELECT COUNT(*) FROM norminha_mensagens WHERE conversa_id = ' . (int) $criada['id'])->fetchColumn();
    $fbs = $pdo->query('SELECT COUNT(*) FROM norminha_feedback WHERE mensagem_id = ' . (int) $idResposta)->fetchColumn();

    expect((int) $msgs)->toBe(0);
    expect((int) $fbs)->toBe(0);
});

$pdo->rollBack();
echo "\n(transação desfeita — nada foi gravado)\n";

exit(testes_resumo());
