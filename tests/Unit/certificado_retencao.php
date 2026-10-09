<?php

/**
 * Certificados retidos aguardando CPF e ContaCpfService (mudança login-google).
 *
 * O QUE ESTE TESTE PROTEGE
 *   - emissão para aluno sem CPF (conta sem CPF) retém em vez de emitir;
 *   - reter duas vezes a mesma inscrição mantém uma retenção ativa;
 *   - o aluno recebe um único e-mail de aviso a cada 24 h;
 *   - informar o CPF grava a conta, copia para os participantes e libera os retidos;
 *   - liberação emite no máximo 5 por vez; falha fica registrada e o CPF continua salvo;
 *   - ContaCpfService recusa CPF inválido, de outra conta e conta que já tem CPF;
 *   - cancelar exige justificativa e vai para a lixeira.
 *
 * Banco: migração 083 + fixtures da API (curso/turma 9101). Roda em transação revertida.
 *   DB_HOST=127.0.0.1 DB_PORT=33061 DB_DATABASE=desbloqueia_app_teste DB_USERNAME=root DB_PASSWORD=... php tests/Unit/certificado_retencao.php
 */

require_once __DIR__ . '/_bootstrap.php';

use App\Models\CertificadoRetido;
use App\Services\CertificadoRetencaoService;
use App\Services\CertificadoService;
use App\Services\ContaCpfService;

$pdo = testes_conectar_banco();
try {
    $pdo->query('SELECT 1 FROM certificados_retidos LIMIT 1');
    if (!$pdo->query('SELECT 1 FROM turmas WHERE id = 9101')->fetchColumn()) {
        throw new RuntimeException('fixtures');
    }
} catch (Throwable $e) {
    echo "SKIP: precisa da migração 083 e das fixtures da API (php tests/Api/montar_banco.php).\n";
    exit(0);
}

$pdo->beginTransaction();
register_shutdown_function(function () use ($pdo) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
});
$_SESSION = array();

$sufixo = bin2hex(random_bytes(3));
$seq = 0;

/** Aluno (com ou sem CPF) e N inscrições no curso 9101. Devolve [usuarioId, inscricaoIds, participanteIds]. */
function ret_aluno(PDO $pdo, $comCpf, $inscricoes = 1)
{
    global $sufixo, $seq;
    $seq++;
    $cpf = $comCpf ? testes_cpf_livre($pdo) : null;
    $pdo->prepare("INSERT INTO usuarios (nome, email, cpf, status, cadastro_status, cadastro_origem, created_at, updated_at)
                   VALUES ('ALUNA RETENCAO', :e, :c, 'ativo', 'pendente', 'google', NOW(), NOW())")
        ->execute(array('e' => "ret$seq.$sufixo@teste.local", 'c' => $cpf));
    $uid = (int) $pdo->lastInsertId();
    $insc = array();
    $parts = array();
    for ($i = 0; $i < $inscricoes; $i++) {
        $pdo->exec("INSERT INTO pedidos (codigo, comprador_usuario_id, pagador_usuario_id, pagador_nome, pagador_email, tipo_pedido, status, subtotal, desconto_total, acrescimo_total, total, canal_origem, created_at, updated_at)
                    VALUES ('RET-$sufixo-$seq-$i', $uid, $uid, 'ALUNA', 'x@y', 'propria', 'pago', 0, 0, 0, 0, 'teste', NOW(), NOW())");
        $pid = (int) $pdo->lastInsertId();
        $pdo->exec("INSERT INTO pedido_itens (pedido_id, curso_evento_id, turma_id, quantidade, valor_unitario, valor_total, status, created_at, updated_at)
                    VALUES ($pid, 9101, NULL, 1, 0, 0, 'ativo', NOW(), NOW())");
        $item = (int) $pdo->lastInsertId();
        $pdo->exec("INSERT INTO participantes_pedido (pedido_id, pedido_item_id, usuario_id, nome, cpf, email, ordem, status, created_at, updated_at)
                    VALUES ($pid, $item, $uid, 'ALUNA RETENCAO', NULL, 'x@y', 1, 'ativo', NOW(), NOW())");
        $part = (int) $pdo->lastInsertId();
        $pdo->exec("INSERT INTO inscricoes (pedido_id, pedido_item_id, participante_pedido_id, usuario_id, curso_evento_id, turma_id, status, percentual_progresso, apto_certificado, confirmado_em, created_at, updated_at)
                    VALUES ($pid, $item, $part, $uid, 9101, NULL, 'concluida', 100, 1, NOW(), NOW(), NOW())");
        $insc[] = (int) $pdo->lastInsertId();
        $parts[] = $part;
    }
    return array($uid, $insc, $parts);
}

function ret_contar(PDO $pdo, $sql)
{
    return (int) $pdo->query($sql)->fetchColumn();
}

describe('Emissão para conta sem CPF');

list($uidA, $inscA) = ret_aluno($pdo, false, 2);
$cert = new CertificadoService();
$r1 = $cert->emitir($inscA[0], array('nao_duplicar' => true), null, '127.0.0.1', 'teste');

it('não emite: retém aguardando CPF com a mensagem da spec', function () use ($r1, $pdo, $inscA) {
    expect(!empty($r1['retido']))->toBeTrue();
    expect($r1['ok'])->toBeFalse();
    expect($r1['message'])->toBe('Certificado retido: o aluno ainda não informou o CPF. Ele será emitido automaticamente quando o CPF for informado.');
    expect(ret_contar($pdo, 'SELECT COUNT(*) FROM certificados WHERE inscricao_id = ' . $inscA[0]))->toBe(0);
    expect(ret_contar($pdo, "SELECT COUNT(*) FROM certificados_retidos WHERE inscricao_id = {$inscA[0]} AND status = 'aguardando_cpf'"))->toBe(1);
});

it('reter de novo a mesma inscrição mantém uma retenção ativa', function () use ($cert, $pdo, $inscA) {
    $cert->emitir($inscA[0], array('nao_duplicar' => true, 'template_id' => 1), null, '127.0.0.1', 'teste');
    expect(ret_contar($pdo, "SELECT COUNT(*) FROM certificados_retidos WHERE inscricao_id = {$inscA[0]} AND status = 'aguardando_cpf'"))->toBe(1);
    $opcoes = json_decode($pdo->query("SELECT opcoes FROM certificados_retidos WHERE inscricao_id = {$inscA[0]}")->fetchColumn(), true);
    expect((int) $opcoes['template_id'])->toBe(1);
});

it('segunda inscrição do mesmo aluno não gera outro e-mail (24 h)', function () use ($cert, $pdo, $inscA, $uidA) {
    $cert->emitir($inscA[1], array('nao_duplicar' => true), null, '127.0.0.1', 'teste');
    expect(ret_contar($pdo, "SELECT COUNT(*) FROM certificados_retidos WHERE usuario_id = $uidA AND status = 'aguardando_cpf'"))->toBe(2);
    expect(ret_contar($pdo, "SELECT COUNT(*) FROM emails_envios WHERE entidade_tipo = 'usuario' AND entidade_id = $uidA AND evento = 'email.certificado_retido'"))->toBe(1);
});

it('o e-mail aponta para Minha conta', function () use ($pdo, $uidA) {
    $ctx = json_decode((string) $pdo->query("SELECT contexto_json FROM emails_envios WHERE entidade_id = $uidA AND evento = 'email.certificado_retido'")->fetchColumn(), true);
    expect($ctx['retencao']['conta_url'])->toContain('/v2/minha-conta');
    expect($ctx['retencao']['curso_nome'])->toBe('Curso do aluno B (fixture app)');
});

describe('Liberação ao informar o CPF');

$emitidas = array();
$emissorOk = function (array $retencao) use (&$emitidas) {
    $emitidas[] = (int) $retencao['inscricao_id'];
    return array('ok' => true, 'certificado_id' => 900000 + count($emitidas));
};

it('liberação sem CPF na conta não faz nada', function () use ($uidA, $emissorOk, &$emitidas) {
    $r = (new CertificadoRetencaoService($emissorOk))->liberarDoUsuario($uidA);
    expect($r['emitidos'])->toBe(0);
    expect(count($emitidas))->toBe(0);
});

it('ContaCpfService recusa CPF inválido', function () use ($uidA) {
    $r = (new ContaCpfService())->informar($uidA, '529.982.247-26', 'site');
    expect($r['erro'])->toBe('validacao');
    expect($r['message'])->toBe('Informe um CPF válido.');
});

it('ContaCpfService recusa CPF de outra conta', function () use ($uidA) {
    $r = (new ContaCpfService())->informar($uidA, '529.982.247-25', 'site');
    expect($r['erro'])->toBe('cpf_em_uso');
    expect($r['message'])->toBe('Este CPF já está cadastrado em outra conta. Fale com o atendimento.');
});

$cpfA = testes_cpf_livre($pdo);
$rInf = (new ContaCpfService())->informar($uidA, $cpfA, 'site', '127.0.0.1', 'teste');

it('informar grava o CPF, completa o cadastro e copia para os participantes', function () use ($rInf, $pdo, $uidA, $cpfA) {
    expect($rInf['ok'])->toBeTrue();
    $u = $pdo->query("SELECT cpf, cadastro_status FROM usuarios WHERE id = $uidA")->fetch();
    expect($u['cpf'])->toBe($cpfA);
    expect($u['cadastro_status'])->toBe('completo');
    expect(ret_contar($pdo, "SELECT COUNT(*) FROM participantes_pedido WHERE usuario_id = $uidA AND cpf = '$cpfA'"))->toBe(2);
    expect(ret_contar($pdo, "SELECT COUNT(*) FROM auditoria_logs WHERE acao = 'conta.cpf_informado' AND entidade_id = $uidA"))->toBe(1);
});

it('segunda tentativa: CPF já informado', function () use ($uidA, $pdo) {
    $r = (new ContaCpfService())->informar($uidA, testes_cpf_livre($pdo), 'app');
    expect($r['erro'])->toBe('cpf_ja_informado');
});

it('com CPF, a liberação emite os retidos e marca emitido', function () use ($uidA, $pdo, $emissorOk, &$emitidas) {
    $r = (new CertificadoRetencaoService($emissorOk))->liberarDoUsuario($uidA);
    expect($r['emitidos'])->toBe(2);
    expect($r['restantes'])->toBe(0);
    expect(ret_contar($pdo, "SELECT COUNT(*) FROM certificados_retidos WHERE usuario_id = $uidA AND status = 'emitido' AND certificado_id IS NOT NULL"))->toBe(2);
});

it('falha na emissão fica registrada e a retenção continua aguardando', function () use ($pdo) {
    list($uid, $insc) = ret_aluno($pdo, true, 1);
    $retidos = new CertificadoRetido();
    $retidos->criar(array('inscricao_id' => $insc[0], 'usuario_id' => $uid, 'modo' => 'individual', 'opcoes' => array(), 'contexto' => array(), 'solicitado_por' => null));
    $r = (new CertificadoRetencaoService(function () {
        return array('ok' => false, 'message' => 'Acesso negado.');
    }))->liberarDoUsuario($uid);
    expect($r['falhas'])->toBe(1);
    $linha = $pdo->query("SELECT * FROM certificados_retidos WHERE inscricao_id = {$insc[0]}")->fetch();
    expect($linha['status'])->toBe('aguardando_cpf');
    expect($linha['ultima_falha'])->toBe('Acesso negado.');
    expect((int) $linha['tentativas'])->toBe(1);
});

it('libera no máximo 5 por vez: 7 retidos saem em duas aberturas', function () use ($pdo) {
    list($uid, $insc) = ret_aluno($pdo, true, 7);
    $retidos = new CertificadoRetido();
    foreach ($insc as $i) {
        $retidos->criar(array('inscricao_id' => $i, 'usuario_id' => $uid, 'modo' => 'lote', 'opcoes' => array(), 'contexto' => array(), 'solicitado_por' => null));
    }
    $svc = new CertificadoRetencaoService(function () {
        return array('ok' => true, 'certificado_id' => null);
    });
    $primeira = $svc->liberarSePendente($uid);
    expect($primeira['emitidos'])->toBe(5);
    expect($primeira['restantes'])->toBe(2);
    $segunda = $svc->liberarSePendente($uid);
    expect($segunda['emitidos'])->toBe(2);
    expect($svc->liberarSePendente($uid))->toBeNull();
});

describe('Cancelamento pelo admin');

it('cancelar exige justificativa e registra na lixeira', function () use ($pdo) {
    list($uid, $insc) = ret_aluno($pdo, false, 1);
    $id = (new CertificadoRetido())->criar(array('inscricao_id' => $insc[0], 'usuario_id' => $uid, 'modo' => 'individual', 'opcoes' => array(), 'contexto' => array(), 'solicitado_por' => null));
    $svc = new CertificadoRetencaoService();
    expect($svc->cancelar($id, '   ')['ok'])->toBeFalse();
    expect($svc->cancelar($id, 'Aluno pediu para não emitir.')['ok'])->toBeTrue();
    expect($pdo->query("SELECT status FROM certificados_retidos WHERE id = $id")->fetchColumn())->toBe('cancelado');
    expect(ret_contar($pdo, "SELECT COUNT(*) FROM lixeira WHERE entidade_tipo = 'certificado_retido' AND entidade_id = $id"))->toBe(1);
});

exit(testes_resumo());
