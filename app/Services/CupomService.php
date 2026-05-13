<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Env;
use App\Core\Logger;
use App\Models\Cupom;
use App\Models\CupomCurso;
use App\Models\CupomHistorico;
use App\Models\CupomRelacao;
use App\Models\CupomUso;
use App\Models\CursoEvento;
use App\Models\Pedido;
use App\Models\PedidoCupom;
use App\Models\PedidoItem;
use Exception;

class CupomService
{
    private $cupomModel;
    private $cupomCursoModel;
    private $cupomRelacaoModel;
    private $cupomUsoModel;
    private $cupomHistoricoModel;
    private $cursoModel;
    private $pedidoCupomModel;
    private $pedidoModel;
    private $pedidoItemModel;
    private $auditService;
    private $trashService;
    private $rbacService;

    public function __construct()
    {
        $this->cupomModel = new Cupom();
        $this->cupomCursoModel = new CupomCurso();
        $this->cupomRelacaoModel = new CupomRelacao();
        $this->cupomUsoModel = new CupomUso();
        $this->cupomHistoricoModel = new CupomHistorico();
        $this->cursoModel = new CursoEvento();
        $this->pedidoCupomModel = new PedidoCupom();
        $this->pedidoModel = new Pedido();
        $this->pedidoItemModel = new PedidoItem();
        $this->auditService = new AuditService();
        $this->trashService = new TrashService();
        $this->rbacService = new RbacService();
    }

    public function listarBackoffice($usuarioId)
    {
        try {
            $canSee = $this->rbacService->userHasPermission($usuarioId, 'cupons.ver')
                || $this->rbacService->userHasPermission($usuarioId, 'cupons.gerenciar');

            if (!$canSee) {
                return array(
                    'cupons' => array(),
                    'cupons_inativos' => array(),
                );
            }

            $todos = $this->cupomModel->allForBackoffice();
            $cuponsAtivos = array();
            $cuponsInativos = array();

            foreach ($todos as $cupom) {
                if (isset($cupom['status']) && $cupom['status'] === 'inativo') {
                    $cuponsInativos[] = $cupom;
                    continue;
                }

                $cuponsAtivos[] = $cupom;
            }

            return array(
                'cupons' => $cuponsAtivos,
                'cupons_inativos' => $cuponsInativos,
            );
        } catch (\Throwable $throwable) {
            Logger::error('cupom.listar_backoffice_falhou', array(
                'message' => $throwable->getMessage(),
                'usuario_id' => $usuarioId,
            ));

            return array(
                'cupons' => array(),
                'cupons_inativos' => array(),
            );
        }
    }

    public function formData($cupomId = null)
    {
        if (!$cupomId) {
            return array(
                'cupom' => null,
                'cursos_disponiveis' => $this->loadCursosDisponiveis(),
                'cupom_cursos' => array(),
                'relacoes' => array(),
                'historico' => array(),
                'usos' => array(),
                'resumo' => array('total_usos' => 0, 'total_descontos' => 0),
            );
        }

        $cupom = $this->cupomModel->findById($cupomId);
        if (!$cupom) {
            return array(
                'cupom' => null,
                'cursos_disponiveis' => $this->loadCursosDisponiveis(),
                'cupom_cursos' => array(),
                'relacoes' => array(),
                'historico' => array(),
                'usos' => array(),
                'resumo' => array('total_usos' => 0, 'total_descontos' => 0),
            );
        }

        $resumo = $this->cupomUsoModel->countByCupom($cupomId);
        $cupom = $this->normalizeCupomPromocionalLink($cupom);

        return array(
            'cupom' => $cupom,
            'cursos_disponiveis' => $this->loadCursosDisponiveis(),
            'cupom_cursos' => $this->cupomCursoModel->forCupom($cupomId),
            'relacoes' => $this->cupomRelacaoModel->forCupom($cupomId),
            'historico' => $this->cupomHistoricoModel->forCupom($cupomId),
            'usos' => $this->cupomUsoModel->forCupom($cupomId),
            'resumo' => $resumo,
        );
    }

    public function detalharBackoffice($cupomId)
    {
        return $this->formData($cupomId);
    }

    public function salvar(array $data, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $cupomId = !empty($data['id']) ? (int) $data['id'] : null;
        $codigo = $this->normalizeCodigo(isset($data['codigo']) ? $data['codigo'] : '');
        $nome = trim((string) (isset($data['nome']) ? $data['nome'] : ''));
        $tipo = isset($data['tipo']) ? trim((string) $data['tipo']) : 'publico';
        $descontoTipo = isset($data['desconto_tipo']) ? trim((string) $data['desconto_tipo']) : 'percentual';
        $valorDesconto = (float) (isset($data['valor_desconto']) ? $data['valor_desconto'] : 0);
        $quantidadeMinimaVagas = $this->nullableInt(isset($data['quantidade_minima_vagas']) ? $data['quantidade_minima_vagas'] : null);
        $limiteTotalUsos = $this->nullableInt(isset($data['limite_total_usos']) ? $data['limite_total_usos'] : null);
        $limitePorUsuario = $this->nullableInt(isset($data['limite_por_usuario']) ? $data['limite_por_usuario'] : null);
        $dataInicio = $this->nullableDateTime(isset($data['data_inicio']) ? $data['data_inicio'] : null);
        $dataFim = $this->nullableDateTime(isset($data['data_fim']) ? $data['data_fim'] : null);
        $status = isset($data['status']) ? trim((string) $data['status']) : 'rascunho';
        $escopo = $this->normalizeEscopo(isset($data['escopo']) ? $data['escopo'] : 'todo_site');
        $descricao = isset($data['descricao']) ? trim((string) $data['descricao']) : null;
        $linkPromocional = isset($data['link_promocional']) ? trim((string) $data['link_promocional']) : null;
        if ($linkPromocional === '') {
            $linkPromocional = null;
        }
        $cursosSelecionados = $this->normalizeCourseIds(isset($data['cupom_curso_ids']) ? $data['cupom_curso_ids'] : array());
        $cursosDisponiveis = $this->loadCursosDisponiveis();
        $cursosDisponiveisMap = array();
        foreach ($cursosDisponiveis as $curso) {
            $cursosDisponiveisMap[(int) $curso['id']] = $curso;
        }

        $errors = array();
        if ($codigo === '') {
            $errors[] = 'Codigo do cupom e obrigatorio.';
        }
        if ($nome === '') {
            $errors[] = 'Nome do cupom e obrigatorio.';
        }
        if (!in_array($tipo, array('publico', 'privado', 'usuario', 'empresa'), true)) {
            $errors[] = 'Tipo de cupom invalido.';
        }
        if (!in_array($descontoTipo, array('percentual', 'valor'), true)) {
            $errors[] = 'Tipo de desconto invalido.';
        }
        if ($valorDesconto < 0) {
            $errors[] = 'Valor de desconto invalido.';
        }
        if ($descontoTipo === 'percentual' && $valorDesconto > 100) {
            $errors[] = 'Cupom percentual nao pode passar de 100%.';
        }
        if (!in_array($status, array('rascunho', 'ativo', 'inativo', 'expirado'), true)) {
            $errors[] = 'Status do cupom invalido.';
        }
        if (!in_array($escopo, array('todo_site', 'cursos_especificos'), true)) {
            $errors[] = 'Validade do cupom invalida.';
        }

        if ($escopo === 'cursos_especificos') {
            if (empty($cursosSelecionados)) {
                $errors[] = 'Selecione ao menos um curso para este cupom.';
            } else {
                foreach ($cursosSelecionados as $cursoIdSelecionado) {
                    if (!isset($cursosDisponiveisMap[$cursoIdSelecionado])) {
                        $errors[] = 'Um dos cursos selecionados nao esta disponivel para este cupom.';
                        break;
                    }
                }
            }
        }

        $cupomExistente = $this->cupomModel->findByCodigo($codigo);
        if ($cupomExistente && (!$cupomId || (int) $cupomExistente['id'] !== $cupomId)) {
            $errors[] = 'Ja existe um cupom com este codigo.';
        }

        $relacoes = $this->buildRelacoes($data);
        if ($tipo !== 'publico' && empty($relacoes)) {
            $errors[] = 'Cupons nao publicos exigem ao menos uma relacao.';
        }

        if ($errors) {
            return array('ok' => false, 'errors' => $errors);
        }

        $payload = array(
            'codigo' => $codigo,
            'nome' => $nome,
            'descricao' => $descricao,
            'escopo' => $escopo,
            'tipo' => $tipo,
            'desconto_tipo' => $descontoTipo,
            'valor_desconto' => $valorDesconto,
            'quantidade_minima_vagas' => $quantidadeMinimaVagas,
            'limite_total_usos' => $limiteTotalUsos,
            'limite_por_usuario' => $limitePorUsuario,
            'data_inicio' => $dataInicio,
            'data_fim' => $dataFim,
            'status' => $status,
            'link_promocional' => $linkPromocional ?: $this->generatePromotionalLink($codigo, $escopo, $cursosSelecionados),
        );

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $acao = 'cupom.criado';
            if ($cupomId) {
                $this->cupomModel->update($cupomId, $payload);
                $acao = 'cupom.atualizado';
            } else {
                $cupomId = $this->cupomModel->create($payload);
            }

            $this->cupomRelacaoModel->sync($cupomId, $relacoes);
            $this->cupomCursoModel->sync($cupomId, $escopo === 'cursos_especificos' ? $cursosSelecionados : array());

            $this->cupomHistoricoModel->create(
                $cupomId,
                $acao,
                $acao === 'cupom.criado' ? 'Criacao do cupom' : 'Atualizacao do cupom',
                array('cupom' => $payload, 'relacoes' => $relacoes, 'cursos' => $cursosSelecionados),
                $actorUserId
            );

            $this->auditService->record(
                $acao,
                'cupom',
                $cupomId,
                array('cupom' => $payload, 'relacoes' => $relacoes, 'cursos' => $cursosSelecionados),
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            Logger::info($acao, array(
                'cupom_id' => $cupomId,
                'codigo' => $codigo,
                'usuario_id' => $actorUserId,
            ));

            $pdo->commit();

            return array('ok' => true, 'cupom_id' => $cupomId);
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('cupom.salvar_falhou', array(
                'message' => $exception->getMessage(),
                'codigo' => $codigo,
            ));

            throw $exception;
        }
    }

    public function excluir($cupomId, $justificativa, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $cupom = $this->cupomModel->findById($cupomId);
        if (!$cupom) {
            return array('ok' => false, 'message' => 'Cupom nao encontrado.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $this->trashService->record('cupom', $cupomId, $justificativa, $cupom, $actorUserId, $ipAddress, $userAgent);
            $this->cupomModel->softDelete($cupomId);

            $this->cupomHistoricoModel->create(
                $cupomId,
                'excluido',
                $justificativa,
                array('cupom' => $cupom),
                $actorUserId
            );

            $this->auditService->record(
                'cupom.excluido',
                'cupom',
                $cupomId,
                array('justificativa' => $justificativa),
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            Logger::info('cupom.excluido', array(
                'cupom_id' => $cupomId,
                'usuario_id' => $actorUserId,
            ));

            $pdo->commit();

            return array('ok' => true);
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('cupom.excluir_falhou', array(
                'cupom_id' => $cupomId,
                'message' => $exception->getMessage(),
            ));

            throw $exception;
        }
    }

    public function alterarStatus($cupomId, $status, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $cupom = $this->cupomModel->findById($cupomId);
        if (!$cupom) {
            return array('ok' => false, 'message' => 'Cupom nao encontrado.');
        }

        $status = trim((string) $status);
        if (!in_array($status, array('ativo', 'inativo'), true)) {
            return array('ok' => false, 'message' => 'Status invalido.');
        }

        $statusAnterior = isset($cupom['status']) ? (string) $cupom['status'] : 'rascunho';

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $this->cupomModel->updateStatus($cupomId, $status);

            $acao = $status === 'ativo' ? 'cupom.reativado' : 'cupom.inativado';
            $observacao = $status === 'ativo'
                ? 'Cupom reativado no painel administrativo'
                : 'Cupom inativado no painel administrativo';

            $this->cupomHistoricoModel->create(
                $cupomId,
                $acao,
                $observacao,
                array(
                    'status_anterior' => $statusAnterior,
                    'status_novo' => $status,
                ),
                $actorUserId
            );

            $this->auditService->record(
                $acao,
                'cupom',
                $cupomId,
                array(
                    'status_anterior' => $statusAnterior,
                    'status_novo' => $status,
                ),
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            Logger::info($acao, array(
                'cupom_id' => $cupomId,
                'status_anterior' => $statusAnterior,
                'status_novo' => $status,
            ));

            $pdo->commit();

            return array('ok' => true, 'status' => $status);
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('cupom.status_falhou', array(
                'cupom_id' => $cupomId,
                'message' => $exception->getMessage(),
            ));

            throw $exception;
        } catch (\Throwable $throwable) {
            $pdo->rollBack();
            Logger::error('cupom.status_falhou', array(
                'cupom_id' => $cupomId,
                'message' => $throwable->getMessage(),
            ));

            throw $throwable;
        }
    }

    public function resumoUso($cupomId)
    {
        $cupom = $this->cupomModel->findById($cupomId);
        if (!$cupom) {
            return array(
                'cupom' => null,
                'cursos_disponiveis' => $this->loadCursosDisponiveis(),
                'cupom_cursos' => array(),
                'relacoes' => array(),
                'historico' => array(),
                'usos' => array(),
                'resumo' => array('total_usos' => 0, 'total_descontos' => 0),
            );
        }

        $cupom = $this->normalizeCupomPromocionalLink($cupom);

        return array(
            'cupom' => $cupom,
            'cursos_disponiveis' => $this->loadCursosDisponiveis(),
            'cupom_cursos' => $this->cupomCursoModel->forCupom($cupomId),
            'relacoes' => $this->cupomRelacaoModel->forCupom($cupomId),
            'historico' => $this->cupomHistoricoModel->forCupom($cupomId),
            'usos' => $this->cupomUsoModel->forCupom($cupomId),
            'resumo' => $this->cupomUsoModel->countByCupom($cupomId),
        );
    }

    public function aplicarAoPedido($pedidoId, $cupomCodigo, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        return $this->persistirAplicacao($pedidoId, $cupomCodigo, 'pedido.cupom.aplicado', 'aplicado', $actorUserId, $ipAddress, $userAgent);
    }

    public function revalidarNoFechamento($pedidoId, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $pedidoCupom = $this->pedidoCupomModel->findByPedido($pedidoId);
        if (!$pedidoCupom) {
            return array('ok' => true, 'message' => 'Pedido sem cupom.');
        }

        return $this->persistirAplicacao($pedidoId, $pedidoCupom['cupom_codigo'], 'pedido.cupom.revalidado', 'revalidado', $actorUserId, $ipAddress, $userAgent);
    }

    public function validarCupomNoPedido($pedidoId, $cupomCodigo = null)
    {
        $pedido = $this->loadPedidoContext($pedidoId);
        if (!$pedido) {
            return array('ok' => false, 'errors' => array('Pedido nao encontrado.'));
        }

        $cupomCodigo = $cupomCodigo !== null ? $this->normalizeCodigo($cupomCodigo) : (isset($pedido['cupom_codigo']) ? $this->normalizeCodigo($pedido['cupom_codigo']) : '');
        if ($cupomCodigo === '') {
            return array('ok' => false, 'errors' => array('Codigo do cupom e obrigatorio.'));
        }

        $cupom = $this->cupomModel->findByCodigo($cupomCodigo);
        if (!$cupom) {
            return array('ok' => false, 'errors' => array('Cupom nao encontrado.'));
        }

        if ($pedido['status'] === 'cancelado' || $pedido['status'] === 'reembolsado' || $pedido['status'] === 'expirado' || $pedido['status'] === 'pago') {
            return array('ok' => false, 'errors' => array('Pedido nao permite aplicacao de cupom neste status.'));
        }

        $relacoes = $this->groupRelations($this->cupomRelacaoModel->forCupom($cupom['id']));
        $errors = $this->validateCupomContext($cupom, $pedido, $relacoes);
        if ($errors) {
            return array('ok' => false, 'errors' => $errors);
        }

        $desconto = $this->calculateDiscount($cupom, $pedido);

        return array(
            'ok' => true,
            'cupom' => $cupom,
            'pedido' => $pedido,
            'valor_desconto' => $desconto,
            'subtotal' => (float) $pedido['subtotal_calculado'],
        );
    }

    private function persistirAplicacao($pedidoId, $cupomCodigo, $eventoAuditoria, $acaoHistorico, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $validacao = $this->validarCupomNoPedido($pedidoId, $cupomCodigo);
        if (empty($validacao['ok'])) {
            return array('ok' => false, 'errors' => isset($validacao['errors']) ? $validacao['errors'] : array('Cupom invalido.'));
        }

        $pedidoCupomAtual = $this->pedidoCupomModel->findByPedido($pedidoId);
        if ($pedidoCupomAtual && strtoupper((string) $pedidoCupomAtual['cupom_codigo']) !== strtoupper((string) $cupomCodigo)) {
            return array('ok' => false, 'errors' => array('Pedido ja possui outro cupom aplicado.'));
        }

        $pedido = $validacao['pedido'];
        $cupom = $validacao['cupom'];
        $desconto = round((float) $validacao['valor_desconto'], 2);
        $subtotal = round((float) $validacao['subtotal'], 2);
        $total = max(0, round($subtotal - $desconto, 2));

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $pedidoCupomId = $this->pedidoCupomModel->upsert(array(
                'pedido_id' => $pedidoId,
                'cupom_id' => $cupom['id'],
                'cupom_codigo' => $cupom['codigo'],
                'valor_desconto' => $desconto,
                'status' => 'aplicado',
                'observacao' => null,
            ));

            $pedidoCupomAtual = $this->pedidoCupomModel->findByPedido($pedidoId);

            $this->cupomUsoModel->create(array(
                'cupom_id' => $cupom['id'],
                'pedido_id' => $pedidoId,
                'pedido_cupom_id' => $pedidoCupomAtual ? (int) $pedidoCupomAtual['id'] : $pedidoCupomId,
                'usuario_id' => isset($pedido['pagador_usuario_id']) && $pedido['pagador_usuario_id'] ? (int) $pedido['pagador_usuario_id'] : (isset($pedido['comprador_usuario_id']) ? (int) $pedido['comprador_usuario_id'] : null),
                'cupom_codigo' => $cupom['codigo'],
                'valor_desconto' => $desconto,
            ));

            $this->pedidoModel->updateCupom($pedidoId, $cupom['codigo'], $desconto, $total);

            $this->cupomHistoricoModel->create(
                $cupom['id'],
                $acaoHistorico,
                'Cupom aplicado ao pedido #' . $pedidoId,
                array(
                    'pedido_id' => $pedidoId,
                    'cupom_codigo' => $cupom['codigo'],
                    'subtotal' => $subtotal,
                    'valor_desconto' => $desconto,
                    'total_final' => $total,
                ),
                $actorUserId
            );

            $this->auditService->record(
                $eventoAuditoria,
                'cupom',
                $cupom['id'],
                array(
                    'pedido_id' => $pedidoId,
                    'cupom_codigo' => $cupom['codigo'],
                    'subtotal' => $subtotal,
                    'valor_desconto' => $desconto,
                    'total_final' => $total,
                ),
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            Logger::info($eventoAuditoria, array(
                'cupom_id' => $cupom['id'],
                'pedido_id' => $pedidoId,
                'valor_desconto' => $desconto,
            ));

            $pdo->commit();

            return array(
                'ok' => true,
                'pedido_id' => $pedidoId,
                'cupom_id' => $cupom['id'],
                'valor_desconto' => $desconto,
                'total_final' => $total,
            );
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('cupom.aplicar_falhou', array(
                'pedido_id' => $pedidoId,
                'cupom_codigo' => $cupomCodigo,
                'message' => $exception->getMessage(),
            ));

            throw $exception;
        }
    }

    private function loadPedidoContext($pedidoId)
    {
        $pedido = $this->pedidoModel->findById($pedidoId);
        if (!$pedido) {
            return null;
        }

        $itens = $this->pedidoItemModel->forPedido($pedidoId);
        $pedido['itens'] = $itens;
        $pedido['quantidade_total'] = 0;
        $pedido['subtotal_calculado'] = 0;

        foreach ($itens as $item) {
            $pedido['quantidade_total'] += (int) $item['quantidade'];
            $pedido['subtotal_calculado'] += (float) $item['valor_total'];
        }

        if ((float) $pedido['subtotal'] > 0) {
            $pedido['subtotal_calculado'] = (float) $pedido['subtotal'];
        }

        return $pedido;
    }

    private function validateCupomContext(array $cupom, array $pedido, array $relacoes)
    {
        $errors = array();
        $agora = date('Y-m-d H:i:s');

        if ($cupom['status'] !== 'ativo') {
            $errors[] = 'Cupom nao esta ativo.';
        }

        if (!empty($cupom['data_inicio']) && $cupom['data_inicio'] > $agora) {
            $errors[] = 'Cupom ainda nao esta valido.';
        }

        if (!empty($cupom['data_fim']) && $cupom['data_fim'] < $agora) {
            $errors[] = 'Cupom expirado.';
        }

        if (empty($pedido['itens'])) {
            $errors[] = 'Pedido sem itens.';
            return $errors;
        }

        if (!empty($cupom['limite_total_usos'])) {
            $usoTotal = $this->cupomUsoModel->countByCupom($cupom['id']);
            $usoTotal = isset($usoTotal['total_usos']) ? (int) $usoTotal['total_usos'] : 0;
            if ($usoTotal >= (int) $cupom['limite_total_usos']) {
                $errors[] = 'Limite total de usos do cupom atingido.';
            }
        }

        $pedidoUsuarioId = $this->pedidoUsuarioId($pedido);
        if (!empty($cupom['limite_por_usuario']) && $pedidoUsuarioId) {
            $usoUsuario = $this->cupomUsoModel->countByCupomAndUsuario($cupom['id'], $pedidoUsuarioId);
            if ($usoUsuario >= (int) $cupom['limite_por_usuario']) {
                $errors[] = 'Limite de uso por usuario atingido.';
            }
        }

        if (!empty($cupom['quantidade_minima_vagas']) && (int) $pedido['quantidade_total'] < (int) $cupom['quantidade_minima_vagas']) {
            $errors[] = 'Quantidade minima de vagas nao atingida.';
        }

        foreach ($pedido['itens'] as $item) {
            if (!empty($item['curso_em_promocao']) && (int) $item['curso_em_promocao'] === 1) {
                $errors[] = 'Curso em promocao nao aceita cupom.';
                break;
            }
        }

        $relacaoTiposCurso = isset($relacoes['tipo_curso']) ? $relacoes['tipo_curso'] : array();
        if ($relacaoTiposCurso) {
            foreach ($pedido['itens'] as $item) {
                $tipoCursoItem = function_exists('mb_strtolower') ? mb_strtolower(trim((string) $item['curso_tipo']), 'UTF-8') : strtolower(trim((string) $item['curso_tipo']));
                if (!in_array($tipoCursoItem, $relacaoTiposCurso, true)) {
                    $errors[] = 'Cupom restrito a tipo de curso especifico.';
                    break;
                }
            }
        }

        $relacaoCidades = isset($relacoes['cidade']) ? $relacoes['cidade'] : array();
        if ($relacaoCidades) {
            $cidade = $this->normalizeToken(isset($pedido['pagador_cidade']) ? $pedido['pagador_cidade'] : '');
            if ($cidade === '' || !in_array($cidade, $relacaoCidades, true)) {
                $errors[] = 'Cupom restrito a cidade especifica.';
            }
        }

        $relacaoEstados = isset($relacoes['estado']) ? $relacoes['estado'] : array();
        if ($relacaoEstados) {
            $estado = $this->normalizeToken(isset($pedido['pagador_estado']) ? $pedido['pagador_estado'] : '');
            if ($estado === '' || !in_array($estado, $relacaoEstados, true)) {
                $errors[] = 'Cupom restrito a estado especifico.';
            }
        }

        $relacaoUsuarios = isset($relacoes['usuario']) ? $relacoes['usuario'] : array();
        if ($cupom['tipo'] === 'usuario' || $relacaoUsuarios) {
            if (!$pedidoUsuarioId || !in_array((string) $pedidoUsuarioId, $relacaoUsuarios, true)) {
                $errors[] = 'Cupom restrito a usuario especifico.';
            }
        }

        $relacaoEmpresas = isset($relacoes['empresa']) ? $relacoes['empresa'] : array();
        if ($cupom['tipo'] === 'empresa' || $relacaoEmpresas) {
            $empresaDocumento = $this->normalizeToken(isset($pedido['pagador_empresa_documento']) ? $pedido['pagador_empresa_documento'] : '');
            $empresaNome = $this->normalizeToken(isset($pedido['pagador_empresa_nome']) ? $pedido['pagador_empresa_nome'] : '');
            $empresaPermitida = false;
            foreach ($relacaoEmpresas as $empresa) {
                if ($empresaDocumento !== '' && $empresa === $empresaDocumento) {
                    $empresaPermitida = true;
                    break;
                }
                if ($empresaNome !== '' && $empresa === $empresaNome) {
                    $empresaPermitida = true;
                    break;
                }
            }
            if (!$empresaPermitida) {
                $errors[] = 'Cupom restrito a empresa especifica.';
            }
        }

        $relacaoPerfis = isset($relacoes['perfil']) ? $relacoes['perfil'] : array();
        if ($relacaoPerfis) {
            $perfisUsuario = $this->loadUserProfiles($pedidoUsuarioId);
            $permitido = false;
            foreach ($perfisUsuario as $perfil) {
                if (in_array($this->normalizeToken($perfil), $relacaoPerfis, true)) {
                    $permitido = true;
                    break;
                }
            }
            if (!$permitido) {
                $errors[] = 'Cupom restrito a perfil especifico.';
            }
        }

        if (($cupom['escopo'] ?? 'todo_site') === 'cursos_especificos') {
            $cursosPermitidos = $this->cupomCursoModel->courseIdsForCupom($cupom['id']);
            if (empty($cursosPermitidos)) {
                $errors[] = 'Cupom configurado para cursos especificos sem cursos vinculados.';
            } else {
                $cursosDoPedido = array();
                foreach ($pedido['itens'] as $item) {
                    $cursosDoPedido[] = (int) $item['curso_evento_id'];
                }
                $cursosDoPedido = array_values(array_unique($cursosDoPedido));
                $temIntersecao = false;
                foreach ($cursosDoPedido as $cursoIdPedido) {
                    if (in_array($cursoIdPedido, $cursosPermitidos, true)) {
                        $temIntersecao = true;
                        break;
                    }
                }

                if (!$temIntersecao) {
                    $errors[] = 'Este cupom nao e valido para o curso selecionado.';
                }
            }
        }

        if ($cupom['tipo'] !== 'publico' && empty($relacoes)) {
            $errors[] = 'Cupom privado sem relacoes configuradas.';
        }

        return $errors;
    }

    private function calculateDiscount(array $cupom, array $pedido)
    {
        $subtotal = (float) $pedido['subtotal_calculado'];
        if ($subtotal <= 0) {
            return 0.00;
        }

        if ($cupom['desconto_tipo'] === 'percentual') {
            $desconto = $subtotal * ((float) $cupom['valor_desconto'] / 100);
        } else {
            $desconto = (float) $cupom['valor_desconto'];
        }

        if ($desconto > $subtotal) {
            $desconto = $subtotal;
        }

        return round($desconto, 2);
    }

    private function buildRelacoes(array $data)
    {
        $relacoes = array();
        $map = array(
            'usuario' => 'relacoes_usuarios',
            'curso_evento' => 'relacoes_cursos_eventos',
            'empresa' => 'relacoes_empresas',
            'perfil' => 'relacoes_perfis',
            'tipo_curso' => 'relacoes_tipos_curso',
            'cidade' => 'relacoes_cidades',
            'estado' => 'relacoes_estados',
        );

        foreach ($map as $tipo => $field) {
            $values = $this->normalizeList(isset($data[$field]) ? $data[$field] : null);
            foreach ($values as $value) {
                $relacoes[] = array(
                    'tipo_relacao' => $tipo,
                    'valor_relacao' => $this->normalizeRelationValue($tipo, $value),
                );
            }
        }

        return $relacoes;
    }

    private function groupRelations(array $rows)
    {
        $relacoes = array(
            'usuario' => array(),
            'curso_evento' => array(),
            'empresa' => array(),
            'perfil' => array(),
            'tipo_curso' => array(),
            'cidade' => array(),
            'estado' => array(),
        );

        foreach ($rows as $row) {
            if (!is_array($row) || empty($row['tipo_relacao'])) {
                continue;
            }

            $tipo = (string) $row['tipo_relacao'];
            if (!array_key_exists($tipo, $relacoes)) {
                continue;
            }

            $relacoes[$tipo][] = $this->normalizeRelationValue($tipo, isset($row['valor_relacao']) ? $row['valor_relacao'] : '');
        }

        return $relacoes;
    }

    private function loadCursosDisponiveis()
    {
        return $this->cursoModel->allForSelect(array('ativo', 'rascunho'));
    }

    private function normalizeEscopo($escopo)
    {
        $escopo = trim((string) $escopo);
        if ($escopo === '') {
            return 'todo_site';
        }

        return $escopo;
    }

    private function normalizeCourseIds($values)
    {
        if (!is_array($values)) {
            $values = array($values);
        }

        $ids = array();
        foreach ($values as $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $courseId = (int) $value;
            if ($courseId > 0) {
                $ids[] = $courseId;
            }
        }

        return array_values(array_unique($ids));
    }

    private function normalizeCodigo($codigo)
    {
        $codigo = strtoupper(trim((string) $codigo));
        $codigo = preg_replace('/\s+/', '', $codigo);

        return $codigo;
    }

    private function normalizeToken($value)
    {
        $value = trim((string) $value);
        $value = function_exists('mb_strtoupper') ? mb_strtoupper($value, 'UTF-8') : strtoupper($value);

        return $value;
    }

    private function normalizeRelationValue($tipo, $value)
    {
        $value = trim((string) $value);

        if ($tipo === 'usuario' || $tipo === 'curso_evento') {
            return $value;
        }

        if ($tipo === 'tipo_curso') {
            return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
        }

        if ($tipo === 'estado' || $tipo === 'cidade' || $tipo === 'perfil' || $tipo === 'empresa') {
            return $this->normalizeToken($value);
        }

        return $value;
    }

    private function normalizeList($value)
    {
        if (is_array($value)) {
            $items = $value;
        } else {
            $value = trim((string) $value);
            if ($value === '') {
                return array();
            }
            $items = preg_split('/[\r\n,;]+/', $value);
        }

        $clean = array();
        foreach ($items as $item) {
            $item = trim((string) $item);
            if ($item === '') {
                continue;
            }
            $clean[] = $item;
        }

        return array_values(array_unique($clean));
    }

    private function nullableInt($value)
    {
        $value = trim((string) $value);
        return $value === '' ? null : (int) $value;
    }

    private function nullableDateTime($value)
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function generatePromotionalLink($codigo, $escopo = 'todo_site', array $cursosSelecionados = array())
    {
        $base = rtrim((string) Env::get('APP_URL', 'http://localhost'), '/');
        $cursoIdPromocional = $this->resolvePromotionalCourseId($escopo, $cursosSelecionados);

        if ($cursoIdPromocional > 0) {
            return $base . '/cursos/detalhe?curso_id=' . $cursoIdPromocional . '&cupom=' . urlencode($codigo);
        }

        return $base . '/cupom?codigo=' . urlencode($codigo);
    }

    private function normalizeCupomPromocionalLink(array $cupom)
    {
        if (empty($cupom['codigo'])) {
            return $cupom;
        }

        $linkPromocional = isset($cupom['link_promocional']) ? trim((string) $cupom['link_promocional']) : '';
        if ($linkPromocional === '' || strpos($linkPromocional, '/?cupom=') !== false) {
            $cursosSelecionados = array();
            if (($cupom['escopo'] ?? 'todo_site') === 'cursos_especificos' && !empty($cupom['id'])) {
                $cursosSelecionados = $this->cupomCursoModel->courseIdsForCupom((int) $cupom['id']);
            }

            $cupom['link_promocional'] = $this->generatePromotionalLink(
                $cupom['codigo'],
                isset($cupom['escopo']) ? (string) $cupom['escopo'] : 'todo_site',
                $cursosSelecionados
            );
        }

        return $cupom;
    }

    private function resolvePromotionalCourseId($escopo, array $cursosSelecionados = array())
    {
        if ($escopo !== 'cursos_especificos' || empty($cursosSelecionados)) {
            return 0;
        }

        foreach ($cursosSelecionados as $cursoId) {
            $curso = $this->cursoModel->findPublicById((int) $cursoId);
            if (!empty($curso['id'])) {
                return (int) $curso['id'];
            }
        }

        return 0;
    }

    private function loadUserProfiles($usuarioId)
    {
        if (!$usuarioId) {
            return array();
        }

        $stmt = Database::connection()->prepare(
            'SELECT p.slug
             FROM perfis p
             INNER JOIN usuario_perfis up ON up.perfil_id = p.id
             WHERE up.usuario_id = :usuario_id
               AND p.deleted_at IS NULL'
        );

        $stmt->execute(array('usuario_id' => $usuarioId));

        return array_map(function ($row) {
            return isset($row['slug']) ? $row['slug'] : '';
        }, $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    private function pedidoUsuarioId(array $pedido)
    {
        if (!empty($pedido['pagador_usuario_id'])) {
            return (int) $pedido['pagador_usuario_id'];
        }

        if (!empty($pedido['comprador_usuario_id'])) {
            return (int) $pedido['comprador_usuario_id'];
        }

        return null;
    }
}


