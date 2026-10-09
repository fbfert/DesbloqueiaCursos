<?php

/**
 * Extrações para a API do app — paridade com o comportamento do site.
 *
 * Regras que estavam presas em controllers HTML foram movidas para Services
 * (ConteudoAcessoAlunoService, PedidoService::cancelarPeloAluno,
 * PagamentoAbacatepayService, AuthService::autenticarCredenciais) e os
 * controllers do site passaram a usá-los. Este teste compara o serviço novo com
 * uma CÓPIA FIEL do código antigo do controller (funções `antigo_*` abaixo,
 * copiadas do commit fb7b504), com dublês que registram cada chamada: mesma
 * saída, mesmas mensagens, mesmas gravações, na mesma ordem.
 *
 * Execução (a parte do AuthService usa o banco e roda em transação revertida):
 *   DB_HOST=127.0.0.1 DB_PORT=33061 DB_DATABASE=desbloqueia_app_teste DB_USERNAME=root DB_PASSWORD=... php tests/Unit/app_extracoes.php
 */

require_once __DIR__ . '/_bootstrap.php';

use App\Services\AreaCursoService;
use App\Services\ConteudoAcessoAlunoService;
use App\Services\ConteudoAvaliacaoTextualService;
use App\Services\ConteudoCursoService;
use App\Services\PagamentoAbacatepayService;
use App\Services\Payments\AbacatePayService;
use App\Services\PedidoService;

// Logger grava em storage/logs; garante a pasta.
@mkdir(BASE_PATH . '/storage/logs', 0775, true);

function sem_construtor($classe, array $props = array())
{
    $ref = new ReflectionClass($classe);
    $obj = $ref->newInstanceWithoutConstructor();
    foreach ($props as $nome => $valor) {
        $p = new ReflectionProperty($ref->getParentClass() && !$ref->hasProperty($nome) ? $ref->getParentClass()->getName() : $classe, $nome);
        $p->setAccessible(true);
        $p->setValue($obj, $valor);
    }
    return $obj;
}

// ===================================================================
// Código ANTIGO (AreaCursoController, commit fb7b504), copiado sem mudança de lógica
// ===================================================================

function antigo_url_conteudo($inscricaoId, $cursoId, $turmaId, $moduloId, $conteudoId)
{
    return '/aluno/curso/' . (int) $inscricaoId . '/' . (int) $cursoId . '/' . (int) $turmaId . '/modulo/' . (int) $moduloId . '/conteudo/' . (int) $conteudoId;
}

function antigo_navegacao(array $modulos, $inscricaoId, $cursoId, $turmaId, $moduloId, $conteudoId)
{
    $sequencia = array();
    foreach ($modulos as $modulo) {
        $itens = !empty($modulo['itens']) && is_array($modulo['itens']) ? $modulo['itens'] : array();
        foreach ($itens as $item) {
            $sequencia[] = array('modulo' => $modulo, 'item' => $item);
        }
    }
    $indiceAtual = null;
    foreach ($sequencia as $indice => $registro) {
        if ((int) ($registro['item']['id'] ?? 0) === (int) $conteudoId) {
            $indiceAtual = $indice;
            break;
        }
    }
    if ($indiceAtual === null) {
        return array('anterior_url' => null, 'anterior_label' => null, 'proximo_url' => null, 'proximo_label' => null);
    }
    $anterior = isset($sequencia[$indiceAtual - 1]) ? $sequencia[$indiceAtual - 1] : null;
    $proximo = isset($sequencia[$indiceAtual + 1]) ? $sequencia[$indiceAtual + 1] : null;

    return array(
        'anterior_url' => $anterior !== null ? antigo_url_conteudo($inscricaoId, $cursoId, $turmaId, (int) ($anterior['modulo']['id'] ?? $moduloId), (int) ($anterior['item']['id'] ?? 0)) : null,
        'anterior_label' => $anterior !== null ? (string) ($anterior['item']['titulo'] ?? 'Conteúdo anterior') : null,
        'proximo_url' => $proximo !== null ? antigo_url_conteudo($inscricaoId, $cursoId, $turmaId, (int) ($proximo['modulo']['id'] ?? $moduloId), (int) ($proximo['item']['id'] ?? 0)) : null,
        'proximo_label' => $proximo !== null ? (string) ($proximo['item']['titulo'] ?? 'Próximo conteúdo') : null,
    );
}

/** Adaptador: a navegação nova + a montagem de URL que ficou no controller. */
function novo_navegacao(array $modulos, $inscricaoId, $cursoId, $turmaId, $moduloId, $conteudoId)
{
    $svc = sem_construtor(ConteudoAcessoAlunoService::class);
    $n = $svc->navegacao($modulos, $conteudoId, $moduloId);
    return array(
        'anterior_url' => $n['anterior'] !== null ? antigo_url_conteudo($inscricaoId, $cursoId, $turmaId, $n['anterior']['modulo_id'], $n['anterior']['item_id']) : null,
        'anterior_label' => $n['anterior'] !== null ? $n['anterior']['titulo'] : null,
        'proximo_url' => $n['proximo'] !== null ? antigo_url_conteudo($inscricaoId, $cursoId, $turmaId, $n['proximo']['modulo_id'], $n['proximo']['item_id']) : null,
        'proximo_label' => $n['proximo'] !== null ? $n['proximo']['titulo'] : null,
    );
}

/** Bloco antigo de conteudoItem(), do registro de acesso até as entregas da avaliação. */
function antigo_abrir_item($conteudoService, $avaliacaoService, $usuarioId, array $inscricao, $cursoId, $turmaId, $moduloId, $itemId, array $detalhe, $ip, $ua)
{
    $item = $detalhe['item'];
    $progressoItem = isset($detalhe['progresso']) && is_array($detalhe['progresso']) ? $detalhe['progresso'] : null;
    $itemJaConcluido = !empty($progressoItem) && in_array((string) ($progressoItem['status'] ?? ''), array('concluido', 'aprovada', 'corrigida'), true);
    $resumo = $conteudoService->obterResumoProgressoAluno($cursoId, (int) $usuarioId, (int) $inscricao['id'], $turmaId > 0 ? $turmaId : null);

    $acao = 'visualizou_item';
    if ((string) $item['tipo'] === 'texto') {
        $acao = 'abriu_texto';
    } elseif ((string) $item['tipo'] === 'html') {
        $acao = 'abriu_html';
    } elseif ((string) $item['tipo'] === 'video') {
        $acao = 'abriu_video';
    } elseif ((string) $item['tipo'] === 'video_incorporado') {
        $acao = 'abriu_video_incorporado';
    } elseif ((string) $item['tipo'] === 'avaliacao_textual') {
        $acao = 'visualizou_avaliacao_textual';
    }

    $conteudoService->registrarAcessoItem(array(
        'curso_evento_id' => $cursoId,
        'turma_id' => $turmaId > 0 ? $turmaId : null,
        'inscricao_id' => (int) $inscricao['id'],
        'aluno_id' => (int) $usuarioId,
        'modulo_id' => $moduloId,
        'item_id' => (int) $item['id'],
        'obrigatorio' => !empty($item['obrigatorio']) ? 1 : 0,
        'acao' => $acao,
        'status' => (string) $item['tipo'] === 'etiqueta' ? 'concluido' : 'em_andamento',
        'percentual' => (string) $item['tipo'] === 'etiqueta' ? 100 : 10,
        'concluido_em' => (string) $item['tipo'] === 'etiqueta' ? 'AGORA' : null,
        'ip' => $ip,
        'user_agent' => $ua,
    ));

    if (in_array((string) ($item['tipo'] ?? ''), array('texto', 'html'), true) && !$itemJaConcluido) {
        try {
            $resultadoConclusao = $conteudoService->concluirItemAluno(array(
                'curso_evento_id' => $cursoId,
                'turma_id' => $turmaId > 0 ? $turmaId : null,
                'inscricao_id' => (int) $inscricao['id'],
                'aluno_id' => (int) $usuarioId,
                'item_id' => (int) $item['id'],
                'modulo_id' => $moduloId,
                'ip' => $ip,
                'user_agent' => $ua,
            ));
            if (!empty($resultadoConclusao['ok'])) {
                $detalhe = $conteudoService->buscarItemPublicadoParaAluno($itemId, (int) $usuarioId, (int) $inscricao['id'], $cursoId, $turmaId > 0 ? $turmaId : null);
                if (!empty($detalhe['ok'])) {
                    $item = $detalhe['item'];
                }
                $resumo = $conteudoService->obterResumoProgressoAluno($cursoId, (int) $usuarioId, (int) $inscricao['id'], $turmaId > 0 ? $turmaId : null);
            }
        } catch (\Exception $exception) {
            // o antigo registrava warning no log e seguia
        }
    }

    $entregasAvaliacao = array();
    $avaliacaoPodeEnviar = null;
    if ((string) ($item['tipo'] ?? '') === 'avaliacao_textual') {
        $entregasAvaliacao = $avaliacaoService->listarEntregasAluno((int) ($detalhe['detalhe']['id'] ?? 0), (int) $usuarioId, (int) $inscricao['id']);
        $ultimaEntrega = !empty($entregasAvaliacao) ? $entregasAvaliacao[0] : null;
        if ($ultimaEntrega) {
            $ultimaEntrega['imagens'] = $avaliacaoService->imagensEntrega((int) $ultimaEntrega['id']);
            $entregasAvaliacao[0] = $ultimaEntrega;
        }
        $avaliacaoPodeEnviar = $avaliacaoService->podeReenviar((int) ($detalhe['detalhe']['id'] ?? 0), (int) $usuarioId, (int) $inscricao['id']);
        if (!empty($ultimaEntrega['status']) && in_array((string) $ultimaEntrega['status'], array('corrigida', 'aprovada', 'reprovada'), true)) {
            $conteudoService->registrarLogAluno(array(
                'curso_evento_id' => $cursoId,
                'turma_id' => $turmaId > 0 ? $turmaId : null,
                'inscricao_id' => (int) $inscricao['id'],
                'aluno_id' => (int) $usuarioId,
                'modulo_id' => $moduloId,
                'item_id' => (int) $item['id'],
                'acao' => 'visualizou_feedback',
                'ip' => $ip,
                'user_agent' => $ua,
            ));
        }
    }

    return array('item' => $item, 'resumo' => $resumo, 'entregas' => $entregasAvaliacao, 'pode_enviar' => $avaliacaoPodeEnviar, 'detalhe' => $detalhe);
}

// ===================================================================
// Dublês que registram chamadas
// ===================================================================

class DubleConteudo extends ConteudoCursoService
{
    public $log = array();
    public $cenario = array();

    public function __construct(array $cenario = array())
    {
        $this->cenario = $cenario;
    }

    private function anotar($metodo, $args)
    {
        // normaliza horário gerado na hora para comparar
        if (isset($args[0]) && is_array($args[0]) && !empty($args[0]['concluido_em'])) {
            $args[0]['concluido_em'] = 'AGORA';
        }
        $this->log[] = array($metodo, $args);
    }

    public function obterResumoProgressoAluno($cursoEventoId, $alunoId, $inscricaoId, $turmaId = null)
    {
        $this->anotar(__FUNCTION__, func_get_args());
        return array('ok' => true, 'percentual' => count($this->log));
    }

    public function registrarAcessoItem($dados)
    {
        $this->anotar(__FUNCTION__, func_get_args());
        return array('ok' => true);
    }

    public function concluirItemAluno(array $contexto)
    {
        $this->anotar(__FUNCTION__, func_get_args());
        if (!empty($this->cenario['concluir_lanca'])) {
            throw new \Exception('falha simulada');
        }
        return array('ok' => empty($this->cenario['concluir_falha']), 'message' => 'não');
    }

    public function buscarItemPublicadoParaAluno($itemId, $alunoId, $inscricaoId, $cursoEventoId, $turmaId = null)
    {
        $this->anotar(__FUNCTION__, func_get_args());
        $item = $this->cenario['item'];
        $item['recarregado'] = true;
        return array('ok' => true, 'item' => $item, 'modulo' => array('id' => 3), 'detalhe' => $this->cenario['detalhe'], 'progresso' => array('status' => 'concluido'));
    }

    public function registrarLogAluno($dados)
    {
        $this->anotar(__FUNCTION__, func_get_args());
        return array('ok' => true, 'id' => 1);
    }
}

class DubleAvaliacao extends ConteudoAvaliacaoTextualService
{
    public $log = array();
    public $entregas = array();

    public function __construct(array $entregas = array())
    {
        $this->entregas = $entregas;
    }

    public function listarEntregasAluno($avaliacaoId, $alunoId, $inscricaoId)
    {
        $this->log[] = array(__FUNCTION__, func_get_args());
        return $this->entregas;
    }

    public function imagensEntrega($entregaId)
    {
        $this->log[] = array(__FUNCTION__, func_get_args());
        return array(array('id' => 1));
    }

    public function podeReenviar($avaliacaoId, $alunoId, $inscricaoId)
    {
        $this->log[] = array(__FUNCTION__, func_get_args());
        return true;
    }
}

// ===================================================================
describe('ConteudoAcessoAlunoService::navegacao = navegação antiga do controller');
// ===================================================================

it('mesmas URLs e rótulos em árvores variadas (inclusive títulos ausentes e módulos vazios)', function () {
    mt_srand(42);
    for ($caso = 0; $caso < 200; $caso++) {
        $modulos = array();
        $itemId = 1;
        $nMod = mt_rand(0, 4);
        for ($m = 1; $m <= $nMod; $m++) {
            $itens = array();
            $nItens = mt_rand(0, 4);
            for ($i = 0; $i < $nItens; $i++) {
                $item = array('id' => $itemId++);
                if (mt_rand(0, 4) > 0) {
                    $item['titulo'] = 'Item ' . $item['id'];
                }
                $itens[] = $item;
            }
            $modulo = array('itens' => $itens);
            if (mt_rand(0, 5) > 0) {
                $modulo['id'] = 100 + $m;
            }
            $modulos[] = $modulo;
        }
        $alvo = mt_rand(0, $itemId + 1);
        $moduloPadrao = mt_rand(0, 3);
        $antigo = antigo_navegacao($modulos, 10, 20, 30, $moduloPadrao, $alvo);
        $novo = novo_navegacao($modulos, 10, 20, 30, $moduloPadrao, $alvo);
        if ($antigo !== $novo) {
            throw new RuntimeException('divergência no caso ' . $caso . ': ' . json_encode(array($antigo, $novo)));
        }
    }
});

// ===================================================================
describe('ConteudoAcessoAlunoService::abrirItem = bloco antigo de conteudoItem()');
// ===================================================================

$cenarios = array(
    'texto não concluído (auto-conclusão)' => array('item' => array('id' => 7, 'tipo' => 'texto', 'obrigatorio' => 1), 'progresso' => null),
    'texto já concluído' => array('item' => array('id' => 7, 'tipo' => 'texto', 'obrigatorio' => 1), 'progresso' => array('status' => 'concluido')),
    'html com conclusão recusada' => array('item' => array('id' => 8, 'tipo' => 'html', 'obrigatorio' => 0), 'progresso' => null, 'concluir_falha' => true),
    'html com conclusão que lança' => array('item' => array('id' => 8, 'tipo' => 'html', 'obrigatorio' => 0), 'progresso' => null, 'concluir_lanca' => true),
    'etiqueta' => array('item' => array('id' => 9, 'tipo' => 'etiqueta', 'obrigatorio' => 0), 'progresso' => null),
    'vídeo' => array('item' => array('id' => 10, 'tipo' => 'video', 'obrigatorio' => 1), 'progresso' => array('status' => 'acessado')),
    'vídeo incorporado' => array('item' => array('id' => 11, 'tipo' => 'video_incorporado', 'obrigatorio' => 0), 'progresso' => null),
    'avaliação corrigida (log de feedback)' => array('item' => array('id' => 12, 'tipo' => 'avaliacao_textual', 'obrigatorio' => 1), 'progresso' => null, 'entregas' => array(array('id' => 5, 'status' => 'corrigida'))),
    'avaliação sem entrega' => array('item' => array('id' => 12, 'tipo' => 'avaliacao_textual', 'obrigatorio' => 1), 'progresso' => null, 'entregas' => array()),
    'quiz' => array('item' => array('id' => 13, 'tipo' => 'quiz', 'obrigatorio' => 1), 'progresso' => null),
);

foreach ($cenarios as $nome => $cenario) {
    it($nome . ': mesmas chamadas, mesma ordem, mesmo resultado', function () use ($cenario) {
        $cenario['detalhe'] = array('id' => 99);
        $detalhe = array('ok' => true, 'item' => $cenario['item'], 'modulo' => array('id' => 3), 'detalhe' => $cenario['detalhe'], 'progresso' => $cenario['progresso']);
        $inscricao = array('id' => 40, 'curso_evento_id' => 20, 'turma_id' => 30);
        $entregas = isset($cenario['entregas']) ? $cenario['entregas'] : array();

        $cAntigo = new DubleConteudo($cenario);
        $aAntigo = new DubleAvaliacao($entregas);
        $antigo = antigo_abrir_item($cAntigo, $aAntigo, 5, $inscricao, 20, 30, 3, (int) $cenario['item']['id'], $detalhe, '1.2.3.4', 'ua');

        $cNovo = new DubleConteudo($cenario);
        $aNovo = new DubleAvaliacao($entregas);
        $svc = new ConteudoAcessoAlunoService(sem_construtor(AreaCursoService::class), $cNovo, $aNovo);
        $novo = $svc->abrirItem(5, $inscricao, 20, 30, 3, (int) $cenario['item']['id'], $detalhe, '1.2.3.4', 'ua');

        expect(json_encode($cNovo->log))->toBe(json_encode($cAntigo->log));
        expect(json_encode($aNovo->log))->toBe(json_encode($aAntigo->log));
        expect(json_encode($novo['item']))->toBe(json_encode($antigo['item']));
        expect(json_encode($novo['resumo']))->toBe(json_encode($antigo['resumo']));
        expect(json_encode($novo['entregas_avaliacao']))->toBe(json_encode($antigo['entregas']));
        expect($novo['avaliacao_pode_enviar'])->toBe($antigo['pode_enviar']);
    });
}

// ===================================================================
describe('ConteudoAcessoAlunoService::carregarContexto = carregarContextoAlunoConteudo antigo');
// ===================================================================

class DubleAreaCurso extends AreaCursoService
{
    public $inscricao;

    public function __construct($inscricao)
    {
        $this->inscricao = $inscricao;
    }

    public function carregarAluno($usuarioId, $inscricaoId = null, $moduloId = null, $aulaId = null, $cursoId = null, $turmaId = null, $atividadeId = null)
    {
        return array('inscricao' => $this->inscricao);
    }
}

it('mensagens e destino de redirecionamento iguais; a API exige a inscrição exata', function () {
    $insc = array('id' => 40, 'curso_evento_id' => 20, 'turma_id' => 30);
    $svc = new ConteudoAcessoAlunoService(new DubleAreaCurso($insc), sem_construtor(ConteudoCursoService::class), sem_construtor(ConteudoAvaliacaoTextualService::class));

    expect($svc->carregarContexto(5, array('inscricao_id' => 0))['message'])->toBe('Inscrição inválida.');
    $ok = $svc->carregarContexto(5, array('inscricao_id' => 40, 'curso_id' => 20, 'turma_id' => 30));
    expect($ok['ok'])->toBeTrue();
    expect($ok['curso_id'])->toBe(20);
    $curso = $svc->carregarContexto(5, array('inscricao_id' => 40, 'curso_id' => 21));
    expect($curso['message'])->toBe('O curso informado não corresponde à inscrição selecionada.');
    expect($curso['redirecionar'])->toEqual(array('inscricao_id' => 40, 'curso_id' => 20, 'turma_id' => 30));
    $turma = $svc->carregarContexto(5, array('inscricao_id' => 40, 'turma_id' => 31));
    expect($turma['message'])->toBe('A turma informada não corresponde à inscrição selecionada.');
    // o site aceita a inscrição devolvida pelo carregarAluno (comportamento mantido)...
    expect($svc->carregarContexto(5, array('inscricao_id' => 41))['ok'])->toBeTrue();
    // ...a API não
    expect($svc->carregarContexto(5, array('inscricao_id' => 41), true)['motivo'])->toBe('sem_acesso');

    $vazio = new ConteudoAcessoAlunoService(new DubleAreaCurso(null), sem_construtor(ConteudoCursoService::class), sem_construtor(ConteudoAvaliacaoTextualService::class));
    expect($vazio->carregarContexto(5, array('inscricao_id' => 40))['message'])->toBe('Nenhuma inscrição válida foi encontrada para este usuário.');
});

// ===================================================================
describe('PedidoService::cancelarPeloAluno = cancelamento antigo (V1 e V2)');
// ===================================================================

class DublePedidoModel
{
    public $pedidos = array();

    public function findById($id)
    {
        return isset($this->pedidos[$id]) ? $this->pedidos[$id] : null;
    }
}

class DublePedidoService extends PedidoService
{
    public $registros = array();
    public $resposta = array('ok' => true);

    public function registrarStatus($pedidoId, $novoStatus, $observacao = null, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $this->registros[] = func_get_args();
        return $this->resposta;
    }
}

it('lista de status canceláveis idêntica à que estava duplicada nos controllers', function () {
    $antiga = array('rascunho', 'aguardando_pagamento', 'pendencia', 'aguardando_reenvio', 'comprovante_enviado', 'em_analise');
    expect(PedidoService::STATUS_CANCELAVEIS_PELO_ALUNO)->toEqual($antiga);
    foreach (array('rascunho', 'aguardando_pagamento', 'pendencia', 'aguardando_reenvio', 'comprovante_enviado', 'em_analise', 'aprovado', 'pago', 'cancelado', 'expirado', 'reembolsado', '') as $status) {
        expect(PedidoService::pedidoPodeSerCanceladoPeloAluno($status))->toBe(in_array($status, $antiga, true));
    }
});

it('mesmas mensagens e mesma chamada a registrarStatus', function () {
    $modelo = new DublePedidoModel();
    $modelo->pedidos[1] = array('id' => 1, 'status' => 'aguardando_pagamento', 'comprador_usuario_id' => 5, 'pagador_usuario_id' => 5);
    $modelo->pedidos[2] = array('id' => 2, 'status' => 'pago', 'comprador_usuario_id' => 5, 'pagador_usuario_id' => 5);
    $modelo->pedidos[3] = array('id' => 3, 'status' => 'rascunho', 'comprador_usuario_id' => 6, 'pagador_usuario_id' => 6);
    $svc = sem_construtor(DublePedidoService::class, array('pedidoModel' => $modelo));

    expect($svc->cancelarPeloAluno(0, 5, 'x')['message'])->toBe('Pedido inválido para cancelamento.');
    expect($svc->cancelarPeloAluno(1, 5, '   ')['message'])->toBe('Informe o motivo do cancelamento do pedido.');
    expect($svc->cancelarPeloAluno(99, 5, 'x')['message'])->toBe('Pedido não encontrado.');
    expect($svc->cancelarPeloAluno(2, 5, 'x')['message'])->toBe('Este pedido não pode mais ser cancelado pelo aluno.');
    expect($svc->registros)->toEqual(array());

    expect($svc->cancelarPeloAluno(1, 5, ' Mudei de ideia ', '1.1.1.1', 'ua')['ok'])->toBeTrue();
    expect($svc->registros[0])->toEqual(array(1, 'cancelado', 'Cancelamento solicitado pelo aluno. Motivo: Mudei de ideia', 5, '1.1.1.1', 'ua'));

    $svc->resposta = array('ok' => false, 'message' => 'Você não tem permissão para alterar este pedido.');
    expect($svc->cancelarPeloAluno(3, 5, 'x')['message'])->toBe('Você não tem permissão para alterar este pedido.');
    $svc->resposta = array('ok' => false);
    expect($svc->cancelarPeloAluno(3, 5, 'x')['message'])->toBe('Não foi possível cancelar o pedido.');
    // API: pedido de outro usuário vira "não encontrado" antes do registrarStatus
    $antes = count($svc->registros);
    expect($svc->cancelarPeloAluno(3, 5, 'x', null, null, true)['motivo'])->toBe('nao_encontrado');
    expect(count($svc->registros))->toBe($antes);
});

// ===================================================================
describe('PagamentoAbacatepayService = orquestração antiga de pagarAbacatepay');
// ===================================================================

class DublePedidoCheckout extends PedidoService
{
    public $pedido;
    public $finalizou = 0;
    public $finalizacao = array('ok' => true);

    public function detalharCheckout($pedidoId, $usuarioId = null, $exigirUsuarioAutenticado = false)
    {
        return array('pedido' => $this->pedido);
    }

    public function finalizarCheckout($pedidoId, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $this->finalizou++;
        if (!empty($this->finalizacao['ok'])) {
            $this->pedido['status'] = 'aguardando_pagamento';
        }
        return $this->finalizacao;
    }
}

class DubleAbacate extends AbacatePayService
{
    public $habilitado = true;
    public $checkout = array('ok' => true, 'checkout' => array('url' => 'https://pay.exemplo/abc'));
    public $registro = array('ok' => true);
    public $alunoRecebido;

    public function __construct()
    {
    }

    public function isEnabled()
    {
        return $this->habilitado;
    }

    public function createCheckout(array $pedido, array $aluno, array $itens): array
    {
        $this->alunoRecebido = $aluno;
        return $this->checkout;
    }

    public function registrarCheckoutNoPedido(array $pedido, array $checkoutResult, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        return $this->registro;
    }
}

it('cada desfecho com a mesma mensagem do controller antigo', function () {
    $pedidoBase = array('id' => 9, 'status' => 'aguardando_pagamento', 'total' => '100.00', 'payment_gateway' => null, 'pagador_nome' => '', 'pagador_email' => 'p@x.com', 'itens' => array());
    $ped = sem_construtor(DublePedidoCheckout::class);
    $aba = new DubleAbacate();
    $svc = new PagamentoAbacatepayService($ped, $aba);
    $padrao = array('nome' => 'Da Sessão', 'email' => 's@x.com', 'cpf' => '1', 'telefone' => '2');

    expect($svc->iniciarParaPedido(0, 5)['message'])->toBe('Pedido inválido.');

    $aba->habilitado = false;
    expect($svc->iniciarParaPedido(9, 5)['message'])->toBe('O pagamento online está desativado no momento.');
    $aba->habilitado = true;

    $ped->pedido = null;
    expect($svc->iniciarParaPedido(9, 5)['message'])->toBe('Você não tem permissão para acessar este pedido.');

    $ped->pedido = array_merge($pedidoBase, array('payment_gateway' => 'AbacatePay', 'payment_provider_payment_url' => 'https://pay.exemplo/existente'));
    $r = $svc->iniciarParaPedido(9, 5);
    expect($r['url'])->toBe('https://pay.exemplo/existente');
    expect($r['reaproveitado'])->toBeTrue();

    $ped->pedido = array_merge($pedidoBase, array('status' => 'pago'));
    expect($svc->iniciarParaPedido(9, 5)['message'])->toBe('Este pedido já está pago.');
    $ped->pedido = array_merge($pedidoBase, array('total' => '0'));
    expect($svc->iniciarParaPedido(9, 5)['message'])->toBe('Este pedido não possui cobrança. A liberação segue o fluxo gratuito.');

    $ped->pedido = array_merge($pedidoBase, array('status' => 'rascunho'));
    $ped->finalizacao = array('ok' => false);
    expect($svc->iniciarParaPedido(9, 5)['message'])->toBe('Não foi possível preparar o pedido para pagamento.');
    $ped->finalizacao = array('ok' => true);

    $ped->pedido = $pedidoBase;
    $aba->checkout = array('ok' => false);
    expect($svc->iniciarParaPedido(9, 5)['message'])->toBe('Não foi possível iniciar o checkout da AbacatePay.');
    $aba->checkout = array('ok' => true, 'checkout' => array('url' => 'https://pay.exemplo/abc'));
    $aba->registro = array('ok' => false);
    expect($svc->iniciarParaPedido(9, 5)['message'])->toBe('Não foi possível registrar o checkout.');
    $aba->registro = array('ok' => true);
    $aba->checkout = array('ok' => true, 'checkout' => array());
    expect($svc->iniciarParaPedido(9, 5)['message'])->toBe('A URL de pagamento não foi retornada pela AbacatePay.');

    $aba->checkout = array('ok' => true, 'checkout' => array('url' => 'https://pay.exemplo/abc'));
    $ped->pedido = array_merge($pedidoBase, array('status' => 'rascunho'));
    $ok = $svc->iniciarParaPedido(9, 5, $padrao);
    expect($ok['url'])->toBe('https://pay.exemplo/abc');
    expect($ped->finalizou)->toBeGreaterThan(0);
    // mesmo preenchimento do aluno: dados do pagador, com a sessão como reserva
    expect($aba->alunoRecebido['nome'])->toBe('Da Sessão');
    expect($aba->alunoRecebido['email'])->toBe('p@x.com');
    expect($aba->alunoRecebido['cpf'])->toBe('1');
});

// ===================================================================
describe('AuthService::autenticarCredenciais + login() (banco)');
// ===================================================================

try {
    $pdo = testes_conectar_banco();
} catch (Throwable $e) {
    echo "  SKIP parte com banco: " . get_class($e) . "\n";
    exit(testes_resumo());
}

$pdo->beginTransaction();
register_shutdown_function(function () use ($pdo) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
});
$_SESSION = array();
$email = 'paridade.login.' . bin2hex(random_bytes(3)) . '@teste.local';
$cpf = '20' . str_pad((string) mt_rand(0, 999999999), 9, '0', STR_PAD_LEFT);
$pdo->prepare("INSERT INTO usuarios (nome, email, cpf, senha_hash, status, tentativas_login, created_at, updated_at) VALUES ('PARIDADE', :e, :c, :h, 'ativo', 0, NOW(), NOW())")
    ->execute(array('e' => $email, 'c' => $cpf, 'h' => password_hash('Certa@123', PASSWORD_DEFAULT)));
$idUsuario = (int) $pdo->lastInsertId();

it('falhas: mesmas mensagens do site e motivo para a API; sucesso grava a sessão como antes', function () use ($pdo, $email, $idUsuario) {
    $auth = new \App\Services\AuthService();
    $r = $auth->login($email, 'errada', '127.0.0.1', 'teste');
    expect($r)->toEqual(array('ok' => false, 'message' => 'Dados de acesso inválidos.'));
    $c = $auth->autenticarCredenciais($email, 'errada', '127.0.0.1', 'teste', 'app_login');
    expect($c['motivo'])->toBe('credenciais_invalidas');
    expect($c['message'])->toBe('Dados de acesso inválidos.');
    expect($auth->autenticarCredenciais('naoexiste@teste.local', 'x', null, null)['motivo'])->toBe('credenciais_invalidas');

    $pdo->prepare('UPDATE usuarios SET bloqueado_ate = :b WHERE id = :id')->execute(array('b' => date('Y-m-d H:i:s', time() + 600), 'id' => $idUsuario));
    expect($auth->login($email, 'Certa@123', null, null)['message'])->toBe('Acesso temporariamente bloqueado. Tente novamente mais tarde.');
    $b = $auth->autenticarCredenciais($email, 'Certa@123', null, null);
    expect($b['motivo'])->toBe('conta_bloqueada');
    expect($b['minutos_restantes'])->toBe(10);

    $pdo->prepare("UPDATE usuarios SET bloqueado_ate = NULL, status = 'inativo' WHERE id = :id")->execute(array('id' => $idUsuario));
    expect($auth->login($email, 'Certa@123', null, null)['message'])->toBe('Usuário sem permissão de acesso.');
    expect($auth->autenticarCredenciais($email, 'Certa@123', null, null)['motivo'])->toBe('conta_inativa');

    $pdo->prepare("UPDATE usuarios SET status = 'ativo' WHERE id = :id")->execute(array('id' => $idUsuario));
    $_SESSION = array();
    $ok = $auth->login($email, 'Certa@123', null, null);
    expect($ok['ok'])->toBeTrue();
    expect((int) $_SESSION['usuario_id'])->toBe($idUsuario);
    expect($_SESSION['usuario_email'])->toBe($email);
    expect($ok['redirect_to'])->toBe('/');

    // autenticarCredenciais não toca na sessão
    $_SESSION = array();
    expect($auth->autenticarCredenciais($email, 'Certa@123', null, null, 'app_login')['ok'])->toBeTrue();
    expect($_SESSION)->toEqual(array());
    $evento = $pdo->query("SELECT evento FROM acessos_logs WHERE usuario_id = {$idUsuario} ORDER BY id DESC LIMIT 1")->fetchColumn();
    expect($evento)->toBe('app_login');
});

$codigo = testes_resumo();
$pdo->rollBack();
exit($codigo);
