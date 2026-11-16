<?php

/**
 * Repasses aos professores (openspec/changes/financeiro-correcoes).
 *
 * O QUE ESTE TESTE PROTEGE
 *   - gerar repasses duas vezes não duplica repasses nem espelhos de RPA;
 *   - PJ ou PF com "exige nota fiscal" começa em "aguardando documento"; PF sem exigência, em "pendente";
 *   - (anexar documento exige upload HTTP real: conferido manualmente na tela de repasses)
 *   - validar aprova e grava a data de validação; rejeitar exige motivo e volta a "aguardando documento";
 *   - pagamento só de repasse liberado, pelo valor líquido exato, com pago_em próprio e sem
 *     tocar em documento_validado_em; segundo pagamento recusado.
 *
 * Banco: migrations até a 084. Roda em transação revertida.
 *   docker exec desbloqueia-app-1 php tests/Unit/financeiro_repasses.php
 */

require_once __DIR__ . '/_bootstrap.php';

use App\Services\RepasseProfessorService;

$pdo = testes_conectar_banco();
try {
    $pdo->query('SELECT pago_em FROM repasses_professores LIMIT 1');
    $cursoId = (int) $pdo->query('SELECT id FROM cursos_eventos ORDER BY id LIMIT 1')->fetchColumn();
    if ($cursoId <= 0) {
        throw new RuntimeException('sem curso');
    }
} catch (Throwable $e) {
    echo "SKIP: precisa da migration 084 e de ao menos um curso/evento no banco.\n";
    exit(0);
}

$arquivosCriados = array();
$pdo->beginTransaction();
register_shutdown_function(function () use ($pdo, &$arquivosCriados) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    foreach ($arquivosCriados as $arquivo) {
        if (is_file($arquivo)) {
            @unlink($arquivo);
        }
    }
});
$_SESSION = array();

$sufixo = bin2hex(random_bytes(3));
$seq = 0;

function fin_professor(PDO $pdo, $tipo, $exigeNota)
{
    global $sufixo, $seq;
    $seq++;
    $pdo->prepare("INSERT INTO usuarios (nome, email, status, created_at, updated_at)
                   VALUES ('PROFESSOR TESTE FIN', :e, 'ativo', NOW(), NOW())")
        ->execute(array('e' => "fin$seq.$sufixo@teste.local"));
    $uid = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO professores_fiscal (usuario_id, tipo_pessoa, aliquota_retencao, exige_nota_fiscal, status, created_at, updated_at)
                   VALUES (:u, :t, 0, :n, 'ativo', NOW(), NOW())")
        ->execute(array('u' => $uid, 't' => $tipo, 'n' => $exigeNota ? 1 : 0));

    return $uid;
}

/** Apuração fechada com um rateio e os participantes dados: [usuarioId, tipo, valorLiquido]. */
function fin_apuracao(PDO $pdo, $cursoId, array $participantes)
{
    global $seq;
    $seq++;
    $competencia = sprintf('19%02d-%02d', $seq % 100, ($seq % 12) + 1);
    $pdo->prepare("INSERT INTO apuracoes_mensais (competencia, data_inicio, data_fim, status, created_at, updated_at)
                   VALUES (:c, '2020-01-01', '2020-01-31', 'fechada', NOW(), NOW())")
        ->execute(array('c' => $competencia));
    $apuracaoId = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO cursos_rateio (apuracao_id, curso_evento_id, competencia, base_liquida, percentual_total, valor_rateio_total, status, created_at, updated_at)
                   VALUES (:a, :c, :comp, 1000, 75, 750, 'fechado', NOW(), NOW())")
        ->execute(array('a' => $apuracaoId, 'c' => $cursoId, 'comp' => $competencia));
    $rateioId = (int) $pdo->lastInsertId();
    foreach ($participantes as $p) {
        $pdo->prepare("INSERT INTO cursos_rateio_participantes (curso_rateio_id, usuario_id, tipo_fiscal, percentual, valor_base, valor_rateado, valor_liquido, created_at, updated_at)
                       VALUES (:r, :u, :t, 37.5, 1000, :v, :v2, NOW(), NOW())")
            ->execute(array('r' => $rateioId, 'u' => $p[0], 't' => $p[1], 'v' => $p[2], 'v2' => $p[2]));
    }

    return $apuracaoId;
}

function fin_repasse(PDO $pdo, $apuracaoId, $usuarioId)
{
    $stmt = $pdo->prepare('SELECT * FROM repasses_professores WHERE apuracao_id = :a AND usuario_id = :u AND deleted_at IS NULL');
    $stmt->execute(array('a' => $apuracaoId, 'u' => $usuarioId));

    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function fin_contar(PDO $pdo, $sql, array $params)
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}

/** Documento anexado direto no banco + transição de status do service (sem upload real). */
function fin_documento(PDO $pdo, $repasseId)
{
    $pdo->prepare("INSERT INTO repasses_documentos (repasse_professor_id, tipo_documento, arquivo_caminho, arquivo_nome_original, status, created_at, updated_at)
                   VALUES (:r, 'nota_fiscal', 'financeiro/documentos/teste.pdf', 'teste.pdf', 'recebido', NOW(), NOW())")
        ->execute(array('r' => $repasseId));

    return (int) $pdo->lastInsertId();
}

function fin_registrar_espelhos(PDO $pdo, $apuracaoId)
{
    global $arquivosCriados;
    $stmt = $pdo->prepare('SELECT re.arquivo_caminho FROM rpa_espelhos re INNER JOIN repasses_professores rp ON rp.id = re.repasse_professor_id WHERE rp.apuracao_id = :a');
    $stmt->execute(array('a' => $apuracaoId));
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $relativo) {
        $arquivosCriados[] = BASE_PATH . '/storage/private_uploads/' . $relativo;
    }
}

$service = new RepasseProfessorService();
$adminId = (int) $pdo->query("SELECT id FROM usuarios ORDER BY id LIMIT 1")->fetchColumn();

describe('Geração de repasses');

$pj = fin_professor($pdo, 'pj', false);
$pfNota = fin_professor($pdo, 'pf', true);
$pfLivre = fin_professor($pdo, 'pf', false);
$apuracaoA = fin_apuracao($pdo, $cursoId, array(array($pj, 'pj', 250), array($pfNota, 'pf', 250), array($pfLivre, 'pf', 250)));

it('a primeira geração cria um repasse por professor', function () use ($service, $pdo, $apuracaoA, $adminId) {
    $r = $service->gerarRepassesDaApuracao($apuracaoA, $adminId);
    fin_registrar_espelhos($pdo, $apuracaoA);
    expect($r['ok'])->toBeTrue();
    expect(count($r['repasses_ids']))->toBe(3);
});

it('a segunda geração é recusada sem criar repasses nem espelhos', function () use ($service, $pdo, $apuracaoA, $adminId) {
    $espelhosAntes = fin_contar($pdo, 'SELECT COUNT(*) FROM rpa_espelhos re INNER JOIN repasses_professores rp ON rp.id = re.repasse_professor_id WHERE rp.apuracao_id = :a', array('a' => $apuracaoA));
    $r = $service->gerarRepassesDaApuracao($apuracaoA, $adminId);
    expect($r['ok'])->toBeFalse();
    expect($r['message'])->toBe('Os repasses desta apuração já foram gerados.');
    expect(fin_contar($pdo, 'SELECT COUNT(*) FROM repasses_professores WHERE apuracao_id = :a', array('a' => $apuracaoA)))->toBe(3);
    expect(fin_contar($pdo, 'SELECT COUNT(*) FROM rpa_espelhos re INNER JOIN repasses_professores rp ON rp.id = re.repasse_professor_id WHERE rp.apuracao_id = :a', array('a' => $apuracaoA)))->toBe($espelhosAntes);
});

it('PJ e PF com "exige nota fiscal" começam aguardando documento; PF sem exigência, pendente', function () use ($pdo, $apuracaoA, $pj, $pfNota, $pfLivre) {
    expect(fin_repasse($pdo, $apuracaoA, $pj)['status'])->toBe('aguardando_documento');
    expect(fin_repasse($pdo, $apuracaoA, $pfNota)['status'])->toBe('aguardando_documento');
    expect((int) fin_repasse($pdo, $apuracaoA, $pfNota)['documento_obrigatorio'])->toBe(1);
    expect(fin_repasse($pdo, $apuracaoA, $pfLivre)['status'])->toBe('pendente');
});

describe('Documento e pagamento');

it('PJ sem documento validado não pode ser pago', function () use ($service, $pdo, $apuracaoA, $pj, $adminId) {
    $repasse = fin_repasse($pdo, $apuracaoA, $pj);
    $r = $service->registrarPagamento((int) $repasse['id'], array('valor' => '250,00'), null, $adminId);
    expect($r['ok'])->toBeFalse();
    expect($r['message'])->toBe('Este repasse exige documento fiscal validado antes do pagamento.');
});

it('documento rejeitado sem motivo é recusado; com motivo volta a aguardar documento', function () use ($service, $pdo, $apuracaoA, $pj, $adminId) {
    $repasse = fin_repasse($pdo, $apuracaoA, $pj);
    $pdo->prepare("UPDATE repasses_professores SET status = 'documento_recebido' WHERE id = :id")->execute(array('id' => $repasse['id']));
    $documentoId = fin_documento($pdo, (int) $repasse['id']);

    $semMotivo = $service->rejeitarDocumento($documentoId, '   ', $adminId);
    expect($semMotivo['ok'])->toBeFalse();
    expect($semMotivo['message'])->toBe('Informe o motivo da rejeição do documento.');

    $r = $service->rejeitarDocumento($documentoId, 'Nota com CNPJ errado', $adminId);
    expect($r['ok'])->toBeTrue();
    expect(fin_repasse($pdo, $apuracaoA, $pj)['status'])->toBe('aguardando_documento');
    $doc = $pdo->query('SELECT status, motivo_rejeicao FROM repasses_documentos WHERE id = ' . $documentoId)->fetch(PDO::FETCH_ASSOC);
    expect($doc['status'])->toBe('rejeitado');
    expect($doc['motivo_rejeicao'])->toBe('Nota com CNPJ errado');
});

it('validar aprova o repasse e grava a data de validação', function () use ($service, $pdo, $apuracaoA, $pj, $adminId) {
    $repasse = fin_repasse($pdo, $apuracaoA, $pj);
    $documentoId = fin_documento($pdo, (int) $repasse['id']);
    $r = $service->validarDocumento($documentoId, $adminId);
    expect($r['ok'])->toBeTrue();
    $depois = fin_repasse($pdo, $apuracaoA, $pj);
    expect($depois['status'])->toBe('aprovado');
    expect($depois['documento_validado_em'] !== null)->toBeTrue();
});

it('valor diferente do líquido é recusado com o valor esperado', function () use ($service, $pdo, $apuracaoA, $pj, $adminId) {
    $repasse = fin_repasse($pdo, $apuracaoA, $pj);
    $r = $service->registrarPagamento((int) $repasse['id'], array('valor' => '200,00'), null, $adminId);
    expect($r['ok'])->toBeFalse();
    expect($r['message'])->toBe('O valor pago precisa ser igual ao valor líquido do repasse: R$ 250,00.');
});

it('pagamento válido grava pago_em e preserva documento_validado_em', function () use ($service, $pdo, $apuracaoA, $pj, $adminId) {
    $antes = fin_repasse($pdo, $apuracaoA, $pj);
    $pdo->prepare("UPDATE repasses_professores SET documento_validado_em = '2020-02-01 10:00:00' WHERE id = :id")->execute(array('id' => $antes['id']));
    $r = $service->registrarPagamento((int) $antes['id'], array('valor' => '250,00', 'metodo' => 'pix'), null, $adminId);
    expect($r['ok'])->toBeTrue();
    $depois = fin_repasse($pdo, $apuracaoA, $pj);
    expect($depois['status'])->toBe('pago');
    expect($depois['pago_em'] !== null)->toBeTrue();
    expect($depois['documento_validado_em'])->toBe('2020-02-01 10:00:00');
});

it('segundo pagamento do mesmo repasse é recusado', function () use ($service, $pdo, $apuracaoA, $pj, $adminId) {
    $repasse = fin_repasse($pdo, $apuracaoA, $pj);
    $r = $service->registrarPagamento((int) $repasse['id'], array('valor' => '250,00'), null, $adminId);
    expect($r['ok'])->toBeFalse();
    expect($r['message'])->toBe('Este repasse já foi pago.');
    expect(fin_contar($pdo, 'SELECT COUNT(*) FROM pagamentos_professores WHERE repasse_professor_id = :r', array('r' => $repasse['id'])))->toBe(1);
});

it('PF sem exigência e pendente pode ser pago direto', function () use ($service, $pdo, $apuracaoA, $pfLivre, $adminId) {
    $repasse = fin_repasse($pdo, $apuracaoA, $pfLivre);
    $r = $service->registrarPagamento((int) $repasse['id'], array('valor' => '250'), null, $adminId);
    expect($r['ok'])->toBeTrue();
});

testes_resumo();
