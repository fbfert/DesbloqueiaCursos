<?php
/**
 * Salvamento automático do simulado.
 *
 * Responder uma questão por vez deve persistir imediatamente, sobreviver a
 * "sair e voltar" e nunca apagar o que já havia sido respondido — é o que
 * evita o aluno refazer uma prova de 5h30.
 *
 * Execução:
 *   DB_DATABASE=dc_quiz_test DB_USERNAME=... DB_PASSWORD=... php tests/Unit/quiz_rascunho.php
 */

require_once __DIR__ . '/_bootstrap.php';
$pdo = testes_conectar_banco();

function ins(PDO $pdo, $sql, array $p = array()) { $s = $pdo->prepare($sql); $s->execute($p); return (int) $pdo->lastInsertId(); }

$sufixo = 'AS' . substr((string) microtime(true), -6);

$cursoId  = ins($pdo, 'INSERT INTO cursos_eventos (nome,slug,created_at,updated_at) VALUES (:n,:s,NOW(),NOW())',
    array('n' => '__AUTOSAVE__ ' . $sufixo, 's' => 'as-' . strtolower($sufixo)));
$moduloId = ins($pdo, 'INSERT INTO conteudo_modulos (curso_evento_id,titulo,status,ordem,created_at,updated_at) VALUES (:c,\'M\',\'publicado\',1,NOW(),NOW())', array('c' => $cursoId));
$itemId   = ins($pdo, 'INSERT INTO conteudo_itens (curso_evento_id,modulo_id,tipo,titulo,obrigatorio,status,ordem,created_at,updated_at) VALUES (:c,:m,\'quiz\',\'Simulado autosave\',1,\'publicado\',1,NOW(),NOW())',
    array('c' => $cursoId, 'm' => $moduloId));
$quizId   = ins($pdo, 'INSERT INTO conteudo_quizzes (item_id,tentativas_maximas,percentual_minimo,duracao_minutos,modo_selecao,acao_ao_expirar,evitar_repeticao_tentativas,exige_aprovacao,exibir_resultado_apos_envio,exibir_gabarito_apos_envio,exibir_comentarios_apos_envio,created_at,updated_at)
    VALUES (:i,3,60,330,\'blocos\',\'enviar_automatico\',1,1,1,1,1,NOW(),NOW())', array('i' => $itemId));

$bObj = ins($pdo, 'INSERT INTO conteudo_quiz_blocos (quiz_id,codigo,titulo,tipo_questao,quantidade_sortear,conta_para_percentual,obrigatorio_para_envio,ordem,status,created_at,updated_at)
    VALUES (:q,\'FGD\',\'Formação Geral Docente\',\'multipla_escolha\',4,1,1,1,\'ativo\',NOW(),NOW())', array('q' => $quizId));
$bDis = ins($pdo, 'INSERT INTO conteudo_quiz_blocos (quiz_id,codigo,titulo,tipo_questao,quantidade_sortear,conta_para_percentual,obrigatorio_para_envio,ordem,status,created_at,updated_at)
    VALUES (:q,\'DISCURSIVA\',\'Questão discursiva\',\'discursiva\',1,0,1,2,\'ativo\',NOW(),NOW())', array('q' => $quizId));

for ($i = 0; $i < 6; $i++) {
    $pid = ins($pdo, 'INSERT INTO conteudo_quiz_perguntas (quiz_id,bloco_id,enunciado,tipo,dificuldade,tema,status,peso,obrigatoria,ordem,created_at,updated_at)
        VALUES (:q,:b,:e,\'multipla_escolha\',\'media\',\'Tema\',\'ativo\',1,1,:o,NOW(),NOW())',
        array('q' => $quizId, 'b' => $bObj, 'e' => 'Questão ' . $i, 'o' => $i + 1));
    for ($a = 1; $a <= 4; $a++) {
        ins($pdo, 'INSERT INTO conteudo_quiz_alternativas (pergunta_id,texto,correta,ordem,created_at,updated_at) VALUES (:p,:t,:c,:o,NOW(),NOW())',
            array('p' => $pid, 't' => 'Alt ' . $a, 'c' => $a === 2 ? 1 : 0, 'o' => $a));
    }
}
ins($pdo, 'INSERT INTO conteudo_quiz_perguntas (quiz_id,bloco_id,enunciado,tipo,dificuldade,tema,status,nota_maxima,peso,obrigatoria,ordem,created_at,updated_at)
    VALUES (:q,:b,\'Disserte.\',\'discursiva\',\'media\',\'P\',\'ativo\',10,1,1,1,NOW(),NOW())', array('q' => $quizId, 'b' => $bDis));

$alunoId = ins($pdo, 'INSERT INTO usuarios (nome,email,cpf,senha_hash,status,created_at,updated_at) VALUES (:n,:e,:c,\'x\',\'ativo\',NOW(),NOW())',
    array('n' => 'AS Aluno', 'e' => 'as' . $sufixo . '@quiz.test', 'c' => substr('500' . substr($sufixo, -8), 0, 11)));
$pedidoId = ins($pdo, 'INSERT INTO pedidos (comprador_usuario_id,pagador_usuario_id,codigo,created_at,updated_at) VALUES (:u,:u,:c,NOW(),NOW())',
    array('u' => $alunoId, 'c' => 'AS' . substr(uniqid(), -8)));
$ipId = ins($pdo, 'INSERT INTO pedido_itens (pedido_id,curso_evento_id,created_at,updated_at) VALUES (:p,:c,NOW(),NOW())', array('p' => $pedidoId, 'c' => $cursoId));
$ppId = ins($pdo, 'INSERT INTO participantes_pedido (pedido_id,pedido_item_id,usuario_id,nome,created_at,updated_at) VALUES (:p,:i,:u,\'AS\',NOW(),NOW())',
    array('p' => $pedidoId, 'i' => $ipId, 'u' => $alunoId));
$inscricaoId = ins($pdo, 'INSERT INTO inscricoes (pedido_id,pedido_item_id,participante_pedido_id,curso_evento_id,usuario_id,status,created_at,updated_at) VALUES (:p,:i,:pa,:c,:u,\'ativa\',NOW(),NOW())',
    array('p' => $pedidoId, 'i' => $ipId, 'pa' => $ppId, 'c' => $cursoId, 'u' => $alunoId));

$falhas = 0;
function checar($ok, $msg) { global $falhas; echo ($ok ? "    ✓ " : "    ✗ ") . $msg . "\n"; if (!$ok) { $falhas++; } }

$svc = new \App\Services\ConteudoQuizService(new \App\Services\Quiz\QuizRandomizerSemente(11));
$r = $svc->iniciarOuRetomar(array('quiz_id' => $quizId, 'inscricao_id' => $inscricaoId, 'aluno_id' => $alunoId, 'curso_evento_id' => $cursoId));
$tid = (int) $r['tentativa']['id'];
$snap = json_decode((string) $r['tentativa']['quiz_snapshot_json'], true);

$objetivas = array(); $discursivaId = null;
foreach ($snap['perguntas'] as $p) {
    if ($p['tipo'] === 'discursiva') { $discursivaId = (int) $p['id']; continue; }
    $alt = null;
    foreach ($p['alternativas'] as $a) { if (!empty($a['correta'])) { $alt = (int) $a['id']; } }
    $objetivas[(int) $p['id']] = $alt;
}
$ids = array_keys($objetivas);

$respostaModel = new \App\Models\ConteudoQuizResposta();
function respondidas($tid) {
    $model = new \App\Models\ConteudoQuizResposta();
    $n = 0;
    foreach ($model->listForTentativa($tid) as $r) {
        if (!empty($r['alternativa_id']) || trim((string) ($r['texto_resposta'] ?? '')) !== '') { $n++; }
    }
    return $n;
}

echo "\n  Salvamento incremental (uma questão por vez)\n";

// O JS manda só a questão que mudou.
$svc->salvarRascunho(array('tentativa_id' => $tid, 'aluno_id' => $alunoId, 'item_id' => $itemId,
    'respostas' => array($ids[0] => $objetivas[$ids[0]])));
checar(respondidas($tid) === 1, 'a 1ª resposta é gravada sozinha');

$svc->salvarRascunho(array('tentativa_id' => $tid, 'aluno_id' => $alunoId, 'item_id' => $itemId,
    'respostas' => array($ids[1] => $objetivas[$ids[1]])));
checar(respondidas($tid) === 2, 'a 2ª resposta não apaga a 1ª');

$svc->salvarRascunho(array('tentativa_id' => $tid, 'aluno_id' => $alunoId, 'item_id' => $itemId,
    'discursivas' => array($discursivaId => 'Rascunho parcial da minha resposta.')));
checar(respondidas($tid) === 3, 'a discursiva é gravada sem apagar as objetivas');

// Digitar mais na discursiva não pode zerar as objetivas já salvas.
$svc->salvarRascunho(array('tentativa_id' => $tid, 'aluno_id' => $alunoId, 'item_id' => $itemId,
    'discursivas' => array($discursivaId => 'Rascunho parcial da minha resposta, agora mais completo.')));
$rd = $respostaModel->findByTentativaEPergunta($tid, $discursivaId);
checar(strpos((string) $rd['texto_resposta'], 'mais completo') !== false, 'a discursiva é atualizada');
checar(respondidas($tid) === 3, 'atualizar a discursiva preserva as objetivas');

echo "\n  Sair e voltar\n";
// "Voltar" = recarregar a tela: o serviço devolve o que estava salvo.
$quizAluno = $svc->findQuizParaAluno($itemId, $alunoId, $inscricaoId);
$comResposta = 0; $textoVoltou = '';
foreach ($quizAluno['perguntas'] as $p) {
    if (!empty($p['respondida'])) { $comResposta++; }
    if ((string) $p['tipo'] === 'discursiva') { $textoVoltou = (string) $p['texto_resposta']; }
}
checar($comResposta === 3, 'ao voltar, as 3 respostas continuam marcadas (recebido: ' . $comResposta . ')');
checar(strpos($textoVoltou, 'mais completo') !== false, 'ao voltar, o texto da discursiva reaparece');

echo "\n  Marcação de revisão não interfere\n";
$svc->marcarParaRevisao(array('tentativa_id' => $tid, 'aluno_id' => $alunoId, 'pergunta_id' => $ids[0], 'marcada' => true));
$r0 = $respostaModel->findByTentativaEPergunta($tid, $ids[0]);
checar((int) $r0['marcada_para_revisao'] === 1 && (int) $r0['alternativa_id'] === $objetivas[$ids[0]],
    'marcar para revisão preserva a resposta');

echo "\n  Segurança do rascunho\n";
$outro = $svc->salvarRascunho(array('tentativa_id' => $tid, 'aluno_id' => $alunoId + 999, 'item_id' => $itemId,
    'respostas' => array($ids[2] => $objetivas[$ids[2]])));
checar(empty($outro['ok']), 'outro aluno não consegue gravar no rascunho desta tentativa');
checar(respondidas($tid) === 3, 'e nada foi gravado');

// Limpeza
$pdo->exec("DELETE FROM conteudo_progresso_aluno WHERE curso_evento_id={$cursoId}");
$pdo->exec("DELETE FROM conteudo_quiz_tentativas WHERE curso_evento_id={$cursoId}");
$pdo->exec("DELETE FROM conteudo_quiz_alternativas WHERE pergunta_id IN (SELECT id FROM conteudo_quiz_perguntas WHERE quiz_id={$quizId})");
$pdo->exec("DELETE FROM conteudo_quiz_perguntas WHERE quiz_id={$quizId}");
$pdo->exec("DELETE FROM conteudo_quiz_blocos WHERE quiz_id={$quizId}");
$pdo->exec("DELETE FROM conteudo_quizzes WHERE id={$quizId}");
$pdo->exec("DELETE FROM conteudo_itens WHERE curso_evento_id={$cursoId}");
$pdo->exec("DELETE FROM conteudo_modulos WHERE curso_evento_id={$cursoId}");
$pdo->exec("DELETE FROM inscricoes WHERE curso_evento_id={$cursoId}");
$pdo->exec("DELETE FROM participantes_pedido WHERE pedido_id={$pedidoId}");
$pdo->exec("DELETE FROM pedido_itens WHERE pedido_id={$pedidoId}");
$pdo->exec("DELETE FROM pedidos WHERE id={$pedidoId}");
$pdo->exec("DELETE FROM usuarios WHERE id={$alunoId}");
$pdo->exec("DELETE FROM cursos_eventos WHERE id={$cursoId}");

echo "\n" . str_repeat('=', 46) . "\n";
echo $falhas === 0 ? "Autosave OK\n" : "Falhas: {$falhas}\n";
exit($falhas > 0 ? 1 : 0);
