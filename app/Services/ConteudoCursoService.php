<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Services\AuditService;
use App\Models\ConteudoArquivo;
use App\Models\ConteudoArquivoVersao;
use App\Models\ConteudoAvaliacaoEntrega;
use App\Models\ConteudoAvaliacaoTextual;
use App\Models\ConteudoEtiqueta;
use App\Models\ConteudoItem;
use App\Models\ConteudoLink;
use App\Models\ConteudoLogAluno;
use App\Models\ConteudoModulo;
use App\Models\ConteudoProgressoAluno;
use App\Models\ConteudoTexto;
use App\Models\ConteudoVideo;
use App\Models\Inscricao;
use App\Services\TrashService;
use App\Services\RbacService;
use App\Support\HtmlSanitizer;
use Exception;

class ConteudoCursoService
{
    private const TIPOS_ITEM_VALIDOS = array('etiqueta', 'texto', 'arquivo', 'link', 'avaliacao_textual', 'video');
    private const STATUS_ITEM_VALIDOS = array('rascunho', 'publicado', 'oculto', 'arquivado');
    private const STATUS_MODULO_VALIDOS = array('rascunho', 'publicado', 'oculto', 'arquivado');
    private const STATUS_PROGRESO_VALIDOS = array('nao_iniciado', 'acessado', 'em_andamento', 'concluido', 'pendente_correcao', 'reprovado');
    private const EXTENSOES_ARQUIVO_VALIDAS = array('pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'odt', 'xls', 'xlsx', 'ods', 'ppt', 'pptx', 'odp', 'txt', 'csv');
    private const LIMITE_ARQUIVO_BYTES = 10485760;

    private $moduloModel;
    private $itemModel;
    private $textoModel;
    private $etiquetaModel;
    private $arquivoModel;
    private $arquivoVersaoModel;
    private $linkModel;
    private $videoModel;
    private $avaliacaoTextualModel;
    private $avaliacaoEntregaModel;
    private $progressoModel;
    private $logModel;
    private $inscricaoModel;
    private $auditService;
    private $trashService;
    private $rbacService;

    public function __construct()
    {
        $this->moduloModel = new ConteudoModulo();
        $this->itemModel = new ConteudoItem();
        $this->textoModel = new ConteudoTexto();
        $this->etiquetaModel = new ConteudoEtiqueta();
        $this->arquivoModel = new ConteudoArquivo();
        $this->arquivoVersaoModel = new ConteudoArquivoVersao();
        $this->linkModel = new ConteudoLink();
        $this->videoModel = new ConteudoVideo();
        $this->avaliacaoTextualModel = new ConteudoAvaliacaoTextual();
        $this->avaliacaoEntregaModel = new ConteudoAvaliacaoEntrega();
        $this->progressoModel = new ConteudoProgressoAluno();
        $this->logModel = new ConteudoLogAluno();
        $this->inscricaoModel = new Inscricao();
        $this->auditService = new AuditService();
        $this->trashService = new TrashService();
        $this->rbacService = new RbacService();
    }

    public function listarModulosComItens($cursoEventoId)
    {
        $cursoEventoId = (int) $cursoEventoId;
        if ($cursoEventoId <= 0) {
            return array('ok' => false, 'message' => 'Curso invÃ¡lido.');
        }

        $modulosAtivos = $this->moduloModel->listAtivosForCurso($cursoEventoId);
        $modulosArquivados = $this->moduloModel->listArquivadosForCurso($cursoEventoId);

        foreach ($modulosAtivos as &$modulo) {
            $itensAtivos = $this->itemModel->listAtivosForModulo((int) $modulo['id']);
            $itensArquivados = $this->itemModel->listArquivadosForModulo((int) $modulo['id']);
            $this->anexarArquivoDetalheNosItens($itensAtivos);
            $this->anexarArquivoDetalheNosItens($itensArquivados);

            $modulo['itens'] = $itensAtivos;
            $modulo['itens_ativos'] = $itensAtivos;
            $modulo['itens_arquivados'] = $itensArquivados;
            $modulo['total_itens_ativos'] = count($itensAtivos);
            $modulo['total_itens_arquivados'] = count($itensArquivados);
        }
        unset($modulo);

        foreach ($modulosArquivados as &$modulo) {
            $modulo['itens'] = array();
            $modulo['itens_ativos'] = array();
            $modulo['itens_arquivados'] = array();
            $modulo['total_itens_ativos'] = 0;
            $modulo['total_itens_arquivados'] = 0;
        }
        unset($modulo);

        return array(
            'ok' => true,
            'modulos' => $modulosAtivos,
            'modulos_arquivados' => $modulosArquivados,
        );
    }

    public function detalharModuloComItens($cursoEventoId, $moduloId)
    {
        $cursoEventoId = (int) $cursoEventoId;
        $moduloId = (int) $moduloId;
        if ($cursoEventoId <= 0 || $moduloId <= 0) {
            return array('ok' => false, 'message' => 'Parâmetros inválidos.');
        }

        $modulo = $this->moduloModel->findById($moduloId);
        if (!$modulo || (int) $modulo['curso_evento_id'] !== $cursoEventoId) {
            return array('ok' => false, 'message' => 'O módulo selecionado não pertence a este curso.');
        }

        $itensAtivos = $this->itemModel->listAtivosForModulo($moduloId);
        $itensArquivados = $this->itemModel->listArquivadosForModulo($moduloId);
        $this->anexarArquivoDetalheNosItens($itensAtivos);
        $this->anexarArquivoDetalheNosItens($itensArquivados);

        $modulo['itens'] = $itensAtivos;
        $modulo['itens_ativos'] = $itensAtivos;
        $modulo['itens_arquivados'] = $itensArquivados;
        $modulo['total_itens_ativos'] = count($itensAtivos);
        $modulo['total_itens_arquivados'] = count($itensArquivados);

        return array('ok' => true, 'modulo' => $modulo);
    }

    public function listarConteudoPublicadoAluno($cursoEventoId, $alunoId, $inscricaoId, $turmaId = null)
    {
        $cursoEventoId = (int) $cursoEventoId;
        $alunoId = (int) $alunoId;
        $inscricaoId = (int) $inscricaoId;
        $turmaId = $turmaId !== null ? (int) $turmaId : null;

        if ($cursoEventoId <= 0 || $alunoId <= 0 || $inscricaoId <= 0) {
            return array('ok' => false, 'message' => 'ParÃ¢metros invÃ¡lidos para listar conteÃºdo.');
        }

        $modulos = $this->moduloModel->listForCurso($cursoEventoId, 'publicado');
        $progresso = $this->progressoModel->listForInscricao($inscricaoId);
        $progressoPorItem = array();
        foreach ($progresso as $registro) {
            $itemId = (int) ($registro['item_id'] ?? 0);
            if ($itemId > 0) {
                $progressoPorItem[$itemId] = $registro;
            }
        }

        $avaliacaoItemIds = array();
        foreach ($modulos as $moduloBase) {
            $itensModuloBase = $this->itemModel->listForModulo((int) $moduloBase['id'], 'publicado');
            foreach ($itensModuloBase as $itemModuloBase) {
                if ((string) ($itemModuloBase['tipo'] ?? '') === 'avaliacao_textual') {
                    $avaliacaoItemIds[] = (int) $itemModuloBase['id'];
                }
            }
        }
        $avaliacoesPorItem = !empty($avaliacaoItemIds)
            ? $this->avaliacaoEntregaModel->listarUltimasEntregasPorItensAluno($avaliacaoItemIds, $alunoId, $inscricaoId)
            : array();

        $totalItens = 0;
        $totalObrigatorios = 0;
        $concluidosItens = 0;
        $concluidosObrigatorios = 0;
        $avaliacoesPendentes = 0;

        foreach ($modulos as &$modulo) {
            $itens = $this->itemModel->listForModulo((int) $modulo['id'], 'publicado');
            $totalItensModulo = 0;
            $totalObrigatoriosModulo = 0;
            $concluidosItensModulo = 0;
            $concluidosObrigatoriosModulo = 0;
            $avaliacoesPendentesModulo = 0;
            foreach ($itens as &$item) {
                $itemId = (int) $item['id'];
                $tipo = (string) $item['tipo'];
                $item['detalhe'] = $this->carregarDetalhePorTipo($tipo, $itemId);
                $item['progresso_aluno'] = isset($progressoPorItem[$itemId]) ? $progressoPorItem[$itemId] : null;
                $item['avaliacao_entrega'] = isset($avaliacoesPorItem[$itemId]) ? $avaliacoesPorItem[$itemId] : null;
                $item = $this->enriquecerItemParaAluno($item, $cursoEventoId, $inscricaoId, $turmaId);

                $totalItens++;
                $totalItensModulo++;
                if (!empty($item['obrigatorio'])) {
                    $totalObrigatorios++;
                    $totalObrigatoriosModulo++;
                }
                if (!empty($item['concluido_aluno'])) {
                    $concluidosItens++;
                    $concluidosItensModulo++;
                    if (!empty($item['obrigatorio'])) {
                        $concluidosObrigatorios++;
                        $concluidosObrigatoriosModulo++;
                    }
                }
                if ($tipo === 'avaliacao_textual' && empty($item['concluido_aluno'])) {
                    $avaliacoesPendentes++;
                    $avaliacoesPendentesModulo++;
                }
            }
            unset($item);
            $modulo['itens'] = $itens;
            $modulo['total_itens'] = $totalItensModulo;
            $modulo['total_obrigatorios'] = $totalObrigatoriosModulo;
            $modulo['concluidos_itens'] = $concluidosItensModulo;
            $modulo['concluidos_obrigatorios'] = $concluidosObrigatoriosModulo;
            $modulo['pendentes_itens'] = max(0, $totalItensModulo - $concluidosItensModulo);
            $modulo['pendentes_obrigatorios'] = max(0, $totalObrigatoriosModulo - $concluidosObrigatoriosModulo);
            $modulo['avaliacoes_pendentes'] = $avaliacoesPendentesModulo;
            $modulo['percentual_conclusao'] = $totalObrigatoriosModulo > 0
                ? round(($concluidosObrigatoriosModulo / $totalObrigatoriosModulo) * 100, 2)
                : ($totalItensModulo > 0 ? round(($concluidosItensModulo / $totalItensModulo) * 100, 2) : 0);
            $modulo['status_publico'] = $this->statusModuloPublicoAluno($modulo);
            $modulo['status_label'] = $this->rotuloStatusPublicoAluno($modulo['status_publico']);
            $modulo['status_class'] = $this->classeStatusPublicoAluno($modulo['status_publico']);
        }
        unset($modulo);

        $resumo = $this->obterResumoProgressoAluno($cursoEventoId, $alunoId, $inscricaoId, $turmaId);
        if (!empty($resumo['ok'])) {
            $resumo['total_itens'] = $totalItens;
            $resumo['concluidos_itens'] = $concluidosItens;
            $resumo['itens_pendentes'] = max(0, $totalItens - $concluidosItens);
            $resumo['total_obrigatorios'] = $totalObrigatorios;
            $resumo['concluidos_obrigatorios'] = $concluidosObrigatorios;
            $resumo['pendentes_obrigatorios'] = max(0, $totalObrigatorios - $concluidosObrigatorios);
            $resumo['avaliacoes_pendentes'] = $avaliacoesPendentes;
            $resumo['status_publico'] = $this->statusGeralAluno($resumo);
            $resumo['status_label'] = $this->rotuloStatusPublicoAluno($resumo['status_publico']);
            $resumo['status_class'] = $this->classeStatusPublicoAluno($resumo['status_publico']);
        }

        return array(
            'ok' => true,
            'modulos' => $modulos,
            'resumo' => !empty($resumo['ok']) ? $resumo : array('ok' => false),
        );
    }

    public function buscarItemPublicadoParaAluno($itemId, $alunoId, $inscricaoId, $cursoEventoId, $turmaId = null)
    {
        $itemId = (int) $itemId;
        $alunoId = (int) $alunoId;
        $inscricaoId = (int) $inscricaoId;
        $cursoEventoId = (int) $cursoEventoId;
        $turmaId = $turmaId !== null ? (int) $turmaId : null;

        if ($itemId <= 0 || $alunoId <= 0 || $inscricaoId <= 0 || $cursoEventoId <= 0) {
            return array('ok' => false, 'message' => 'ParÃ¢metros invÃ¡lidos.');
        }

        $item = $this->itemModel->findById($itemId);
        if (!$item || (int) $item['curso_evento_id'] !== $cursoEventoId || (string) $item['status'] !== 'publicado') {
            return array('ok' => false, 'message' => 'Item não encontrado.');
        }

        $modulo = $this->moduloModel->findById((int) $item['modulo_id']);
        if (!$modulo || (int) $modulo['curso_evento_id'] !== $cursoEventoId || (string) $modulo['status'] !== 'publicado') {
            return array('ok' => false, 'message' => 'Módulo não disponível.');
        }

        $detalhe = $this->carregarDetalhePorTipo((string) $item['tipo'], (int) $item['id']);
        $progresso = $this->progressoModel->findByContext($alunoId, $inscricaoId, (int) $item['id']);

        return array(
            'ok' => true,
            'item' => $item,
            'modulo' => $modulo,
            'detalhe' => $detalhe,
            'progresso' => $progresso,
            'curso_evento_id' => $cursoEventoId,
            'turma_id' => $turmaId,
        );
    }

    public function obterResumoProgressoAluno($cursoEventoId, $alunoId, $inscricaoId, $turmaId = null)
    {
        $cursoEventoId = (int) $cursoEventoId;
        $alunoId = (int) $alunoId;
        $inscricaoId = (int) $inscricaoId;
        $turmaId = $turmaId !== null ? (int) $turmaId : null;
        if ($cursoEventoId <= 0 || $alunoId <= 0 || $inscricaoId <= 0) {
            return array('ok' => false, 'message' => 'ParÃ¢metros invÃ¡lidos para resumo.');
        }

        $modulos = $this->moduloModel->listForCurso($cursoEventoId, 'publicado');
        $idsItens = array();
        $totaisModulo = array();
        foreach ($modulos as $modulo) {
            $itens = $this->itemModel->listForModulo((int) $modulo['id'], 'publicado');
            $totalObrigatorios = 0;
            $totalItens = 0;
            foreach ($itens as $item) {
                $itemId = (int) $item['id'];
                $idsItens[] = $itemId;
                $totalItens++;
                if (!empty($item['obrigatorio'])) {
                    $totalObrigatorios++;
                }
            }
            $totaisModulo[(int) $modulo['id']] = array(
                'total_itens' => $totalItens,
                'total_obrigatorios' => $totalObrigatorios,
                'concluidos_itens' => 0,
                'concluidos_obrigatorios' => 0,
            );
        }

        $progresso = $this->progressoModel->listForInscricao($inscricaoId);
        $concluidos = array();
        foreach ($progresso as $registro) {
            if ((string) ($registro['status'] ?? '') === 'concluido') {
                $concluidos[(int) $registro['item_id']] = true;
            }
        }

        $totalItens = 0;
        $totalObrigatorios = 0;
        $concluidosItens = 0;
        $concluidosObrigatorios = 0;
        foreach ($modulos as $modulo) {
            $itens = $this->itemModel->listForModulo((int) $modulo['id'], 'publicado');
            foreach ($itens as $item) {
                $itemId = (int) $item['id'];
                $isObrigatorio = !empty($item['obrigatorio']);
                $isConcluido = !empty($concluidos[$itemId]);

                $totalItens++;
                if ($isObrigatorio) {
                    $totalObrigatorios++;
                }
                if ($isConcluido) {
                    $concluidosItens++;
                }
                if ($isObrigatorio && $isConcluido) {
                    $concluidosObrigatorios++;
                }

                $moduloId = (int) $modulo['id'];
                if ($isConcluido) {
                    $totaisModulo[$moduloId]['concluidos_itens']++;
                }
                if ($isObrigatorio && $isConcluido) {
                    $totaisModulo[$moduloId]['concluidos_obrigatorios']++;
                }
            }
        }

        $percentual = 0.00;
        $modo = 'obrigatorios';
        if ($totalObrigatorios > 0) {
            $percentual = round(($concluidosObrigatorios / $totalObrigatorios) * 100, 2);
        } elseif ($totalItens > 0) {
            $percentual = round(($concluidosItens / $totalItens) * 100, 2);
            $modo = 'todos';
        }

        return array(
            'ok' => true,
            'curso_evento_id' => $cursoEventoId,
            'turma_id' => $turmaId,
            'aluno_id' => $alunoId,
            'inscricao_id' => $inscricaoId,
            'modo_calculo' => $modo,
            'total_itens' => $totalItens,
            'concluidos_itens' => $concluidosItens,
            'total_obrigatorios' => $totalObrigatorios,
            'concluidos_obrigatorios' => $concluidosObrigatorios,
            'pendentes_obrigatorios' => max(0, $totalObrigatorios - $concluidosObrigatorios),
            'percentual' => $percentual,
            'modulos' => $totaisModulo,
        );
    }

    public function registrarDownloadArquivoAluno(array $contexto)
    {
        $contexto['acao'] = 'baixou_arquivo';
        $contexto['status'] = 'em_andamento';
        return $this->registrarAcessoItem($contexto);
    }

    public function registrarAcessoLinkAluno(array $contexto)
    {
        $contexto['acao'] = 'acessou_link';
        $contexto['status'] = 'em_andamento';
        return $this->registrarAcessoItem($contexto);
    }

    public function concluirItemAluno(array $contexto)
    {
        $contexto = (array) $contexto;
        $itemId = isset($contexto['item_id']) ? (int) $contexto['item_id'] : 0;
        $cursoEventoId = isset($contexto['curso_evento_id']) ? (int) $contexto['curso_evento_id'] : 0;
        if ($itemId <= 0 || $cursoEventoId <= 0) {
            return array('ok' => false, 'message' => 'ParÃ¢metros invÃ¡lidos para conclusÃ£o.');
        }

        $item = $this->itemModel->findById($itemId);
        if (!$item || (int) $item['curso_evento_id'] !== $cursoEventoId || (string) $item['status'] !== 'publicado') {
            return array('ok' => false, 'message' => 'Item não disponível para conclusão.');
        }

        $modulo = $this->moduloModel->findById((int) $item['modulo_id']);
        if (!$modulo || (string) $modulo['status'] !== 'publicado') {
            return array('ok' => false, 'message' => 'Módulo não disponível para conclusão.');
        }

        if ((string) $item['tipo'] === 'avaliacao_textual') {
            return array('ok' => false, 'message' => 'A avaliação textual não pode ser concluída manualmente nesta etapa.');
        }

        $contexto['modulo_id'] = (int) $item['modulo_id'];
        $contexto['item_id'] = $itemId;
        $contexto['obrigatorio'] = !empty($item['obrigatorio']) ? 1 : 0;
        $contexto['acao'] = 'marcou_como_concluido';
        $contexto['status'] = 'concluido';
        $contexto['concluido_em'] = date('Y-m-d H:i:s');
        $contexto['ultimo_acesso_em'] = date('Y-m-d H:i:s');

        $marcar = $this->marcarItemComoConcluido($contexto);
        if (empty($marcar['ok'])) {
            return $marcar;
        }

        $recalc = $this->recalcularProgressoInscricao((int) $contexto['inscricao_id']);
        return array('ok' => true, 'item' => $item, 'progresso' => $marcar, 'recalculo' => $recalc);
    }

    public function criarModulo($dados)
    {
        $payload = $this->normalizarModuloPayload((array) $dados);
        if (empty($payload['ok'])) {
            return $payload;
        }

        $payload['data']['ordem'] = $this->moduloModel->nextActiveOrderForCurso((int) $payload['data']['curso_evento_id']);
        $id = $this->moduloModel->create($payload['data']);
        return array('ok' => true, 'id' => $id);
    }

    public function atualizarModulo($id, $dados)
    {
        $id = (int) $id;
        if ($id <= 0) {
            return array('ok' => false, 'message' => 'Módulo inválido.');
        }

        $existente = $this->moduloModel->findById($id);
        if (!$existente) {
            return array('ok' => false, 'message' => 'Módulo não encontrado.');
        }

        $payload = $this->normalizarModuloPayload(array_merge($existente, (array) $dados));
        if (empty($payload['ok'])) {
            return $payload;
        }

        $this->moduloModel->update($payload['data'], $id);
        return array('ok' => true);
    }

    public function arquivarModulo($id, $usuarioId)
    {
        $id = (int) $id;
        if ($id <= 0) {
            return array('ok' => false, 'message' => 'Módulo inválido.');
        }

        $modulo = $this->moduloModel->findById($id);
        if (!$modulo) {
            return array('ok' => false, 'message' => 'Módulo não encontrado.');
        }

        if ((string) ($modulo['status'] ?? '') === 'arquivado') {
            return array('ok' => false, 'message' => 'Este módulo já está arquivado.');
        }

        $ok = $this->moduloModel->updateStatus($id, 'arquivado', $usuarioId ? (int) $usuarioId : null);
        return array('ok' => $ok);
    }

    public function reordenarModulos($cursoEventoId, array $ordens)
    {
        $cursoEventoId = (int) $cursoEventoId;
        if ($cursoEventoId <= 0) {
            return array('ok' => false, 'message' => 'Curso inválido.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            foreach ($ordens as $moduloId => $ordem) {
                $moduloId = (int) $moduloId;
                if ($moduloId <= 0) {
                    continue;
                }

                $modulo = $this->moduloModel->findById($moduloId);
                if (!$modulo || (int) $modulo['curso_evento_id'] !== $cursoEventoId || (string) ($modulo['status'] ?? '') === 'arquivado') {
                    continue;
                }

                $this->moduloModel->update(array(
                    'titulo' => $modulo['titulo'],
                    'descricao' => $modulo['descricao'],
                    'ordem' => (int) $ordem,
                    'status' => $modulo['status'],
                    'criado_por' => $modulo['criado_por'],
                    'atualizado_por' => $modulo['atualizado_por'],
                ), $moduloId);
            }

            $pdo->commit();
            return array('ok' => true);
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('conteudo.modulos.reordenar_falhou', array('message' => $exception->getMessage()));
            throw $exception;
        }
    }

    public function duplicarModulo($id, $usuarioId)
    {
        $id = (int) $id;
        if ($id <= 0) {
            return array('ok' => false, 'message' => 'MÃ³dulo invÃ¡lido.');
        }

        $origem = $this->moduloModel->findById($id);
        if (!$origem) {
            return array('ok' => false, 'message' => 'Módulo não encontrado.');
        }

        if ((string) ($origem['status'] ?? '') === 'arquivado') {
            return array('ok' => false, 'message' => 'Não é possível duplicar um módulo arquivado.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $novoModuloId = $this->moduloModel->create(array(
                'curso_evento_id' => (int) $origem['curso_evento_id'],
                'titulo' => 'CÃ³pia - ' . (string) $origem['titulo'],
                'descricao' => $origem['descricao'],
                'ordem' => $this->moduloModel->nextActiveOrderForCurso((int) $origem['curso_evento_id']),
                'status' => 'rascunho',
                'criado_por' => $usuarioId ? (int) $usuarioId : null,
                'atualizado_por' => $usuarioId ? (int) $usuarioId : null,
            ));

            $itens = $this->itemModel->listAtivosForModulo($id);
            foreach ($itens as $item) {
                $novoItemId = $this->itemModel->create(array(
                    'curso_evento_id' => (int) $item['curso_evento_id'],
                    'modulo_id' => $novoModuloId,
                    'tipo' => (string) $item['tipo'],
                    'titulo' => (string) $item['titulo'],
                    'descricao_curta' => $item['descricao_curta'],
                    'obrigatorio' => (int) $item['obrigatorio'],
                    'ordem' => $this->itemModel->nextActiveOrderForModulo($novoModuloId),
                    'status' => 'rascunho',
                    'abre_em' => $item['abre_em'],
                    'criado_por' => $usuarioId ? (int) $usuarioId : null,
                    'atualizado_por' => $usuarioId ? (int) $usuarioId : null,
                ));

                $this->duplicarConteudoPorTipo((string) $item['tipo'], (int) $item['id'], $novoItemId);
            }

            $pdo->commit();
            return array('ok' => true, 'id' => $novoModuloId);
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('conteudo.modulos.duplicar_falhou', array('message' => $exception->getMessage()));
            throw $exception;
        }
    }

    public function criarItem($dados)
    {
        $payload = $this->normalizarItemPayload((array) $dados);
        if (empty($payload['ok'])) {
            return $payload;
        }

        $payload['data']['ordem'] = $this->itemModel->nextActiveOrderForModulo((int) $payload['data']['modulo_id']);
        $id = $this->itemModel->create($payload['data']);
        return array('ok' => true, 'id' => $id);
    }

    public function atualizarItem($id, $dados)
    {
        $id = (int) $id;
        if ($id <= 0) {
            return array('ok' => false, 'message' => 'Item invÃ¡lido.');
        }

        $existente = $this->itemModel->findById($id);
        if (!$existente) {
            return array('ok' => false, 'message' => 'Item nÃ£o encontrado.');
        }

        $payload = $this->normalizarItemPayload(array_merge($existente, (array) $dados));
        if (empty($payload['ok'])) {
            return $payload;
        }

        $this->itemModel->update($payload['data'], $id);
        return array('ok' => true);
    }

    public function arquivarItem($id, $usuarioId)
    {
        $id = (int) $id;
        if ($id <= 0) {
            return array('ok' => false, 'message' => 'Item invÃ¡lido.');
        }

        $item = $this->itemModel->findById($id);
        if (!$item) {
            return array('ok' => false, 'message' => 'Item não encontrado.');
        }

        if ((string) ($item['status'] ?? '') === 'arquivado') {
            return array('ok' => false, 'message' => 'Este item já está arquivado.');
        }

        $ok = $this->itemModel->updateStatus($id, 'arquivado', $usuarioId ? (int) $usuarioId : null);
        return array('ok' => $ok);
    }

    public function moverItemParaModulo($itemId, $novoModuloId, $usuarioId)
    {
        $itemId = (int) $itemId;
        $novoModuloId = (int) $novoModuloId;
        if ($itemId <= 0 || $novoModuloId <= 0) {
            return array('ok' => false, 'message' => 'ParÃ¢metros invÃ¡lidos.');
        }

        $item = $this->itemModel->findById($itemId);
        $modulo = $this->moduloModel->findById($novoModuloId);
        if (!$item || !$modulo) {
            return array('ok' => false, 'message' => 'Item ou módulo não encontrado.');
        }

        if ((string) ($item['status'] ?? '') === 'arquivado' || (string) ($modulo['status'] ?? '') === 'arquivado') {
            return array('ok' => false, 'message' => 'Não é possível mover conteúdos arquivados ou para módulos arquivados.');
        }

        if ((int) $item['curso_evento_id'] !== (int) $modulo['curso_evento_id']) {
            return array('ok' => false, 'message' => 'O item nÃ£o pertence ao mesmo curso do mÃ³dulo.');
        }

        $ok = $this->itemModel->moverParaModulo($itemId, $novoModuloId, $usuarioId ? (int) $usuarioId : null);
        return array('ok' => $ok);
    }

    public function reordenarItens($moduloId, array $ordens)
    {
        $moduloId = (int) $moduloId;
        if ($moduloId <= 0) {
            return array('ok' => false, 'message' => 'MÃ³dulo invÃ¡lido.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $itens = $this->itemModel->listAtivosForModulo($moduloId);
            $mapa = array();
            foreach ($itens as $item) {
                $mapa[(int) $item['id']] = $item;
            }

            foreach ($ordens as $itemId => $ordem) {
                $itemId = (int) $itemId;
                if ($itemId <= 0 || !isset($mapa[$itemId])) {
                    continue;
                }

                $item = $mapa[$itemId];
                $this->itemModel->update(array(
                    'curso_evento_id' => (int) $item['curso_evento_id'],
                    'modulo_id' => (int) $moduloId,
                    'tipo' => (string) $item['tipo'],
                    'titulo' => (string) $item['titulo'],
                    'descricao_curta' => $item['descricao_curta'],
                    'obrigatorio' => (int) $item['obrigatorio'],
                    'ordem' => (int) $ordem,
                    'status' => (string) $item['status'],
                    'abre_em' => $item['abre_em'],
                    'criado_por' => $item['criado_por'],
                    'atualizado_por' => $item['atualizado_por'],
                ), $itemId);
            }

            $pdo->commit();
            return array('ok' => true);
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('conteudo.itens.reordenar_falhou', array('message' => $exception->getMessage()));
            throw $exception;
        }
    }

    public function duplicarItem($id, $usuarioId)
    {
        $id = (int) $id;
        if ($id <= 0) {
            return array('ok' => false, 'message' => 'Item invÃ¡lido.');
        }

        $origem = $this->itemModel->findById($id);
        if (!$origem) {
            return array('ok' => false, 'message' => 'Item nÃ£o encontrado.');
        }

        if ((string) ($origem['status'] ?? '') === 'arquivado') {
            return array('ok' => false, 'message' => 'Não é possível duplicar um item arquivado.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $novoItemId = $this->itemModel->create(array(
                'curso_evento_id' => (int) $origem['curso_evento_id'],
                'modulo_id' => (int) $origem['modulo_id'],
                'tipo' => (string) $origem['tipo'],
                'titulo' => 'CÃ³pia - ' . (string) $origem['titulo'],
                'descricao_curta' => $origem['descricao_curta'],
                'obrigatorio' => (int) $origem['obrigatorio'],
                'ordem' => $this->itemModel->nextActiveOrderForModulo((int) $origem['modulo_id']),
                'status' => 'rascunho',
                'abre_em' => $origem['abre_em'],
                'criado_por' => $usuarioId ? (int) $usuarioId : null,
                'atualizado_por' => $usuarioId ? (int) $usuarioId : null,
            ));

            $this->duplicarConteudoPorTipo((string) $origem['tipo'], (int) $origem['id'], $novoItemId);

            $pdo->commit();
            return array('ok' => true, 'id' => $novoItemId);
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('conteudo.itens.duplicar_falhou', array('message' => $exception->getMessage()));
            throw $exception;
        }
    }

    public function excluirDefinitivamenteModuloArquivado($id, $cursoEventoId, $justificativa, $usuarioId, $ipAddress = null, $userAgent = null)
    {
        $id = (int) $id;
        $cursoEventoId = (int) $cursoEventoId;
        if ($id <= 0) {
            return array('ok' => false, 'message' => 'Módulo inválido.');
        }

        if ($cursoEventoId <= 0) {
            return array('ok' => false, 'message' => 'Curso inválido.');
        }

        if (!$this->rbacService->isSuperAdmin($usuarioId)) {
            return array('ok' => false, 'message' => 'Somente superadministrador pode excluir definitivamente módulos arquivados.');
        }

        $modulo = $this->moduloModel->findById($id);
        if (!$modulo) {
            return array('ok' => false, 'message' => 'Módulo não encontrado.');
        }

        if ((string) ($modulo['status'] ?? '') !== 'arquivado') {
            return array('ok' => false, 'message' => 'O módulo precisa estar arquivado para exclusão definitiva.');
        }

        if ((int) $modulo['curso_evento_id'] !== $cursoEventoId) {
            return array('ok' => false, 'message' => 'O módulo não pertence ao curso informado.');
        }

        $itens = $this->itemModel->listForModulo($id);
        if ($this->moduloTemArquivoFisico($itens)) {
            return array('ok' => false, 'message' => 'Este módulo possui conteúdos com arquivos enviados. A exclusão definitiva foi bloqueada para evitar perda indevida de arquivos físicos.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $snapshot = array(
                'modulo' => $modulo,
                'itens' => $itens,
            );

            $this->trashService->record('conteudo_modulo', $id, $justificativa, $snapshot, $usuarioId, $ipAddress, $userAgent);
            $this->moduloModel->hardDelete($id);

            $this->auditService->record(
                'conteudo.modulo.excluido_definitivamente',
                'conteudo_modulo',
                $id,
                array(
                    'justificativa' => $justificativa,
                    'snapshot' => $snapshot,
                ),
                $usuarioId,
                $ipAddress,
                $userAgent
            );

            Logger::info('conteudo.modulo.excluido_definitivamente', array(
                'modulo_id' => $id,
                'usuario_id' => $usuarioId,
            ));

            $pdo->commit();
            return array('ok' => true);
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('conteudo.modulo.exclusao_definitiva_falhou', array('message' => $exception->getMessage()));
            throw $exception;
        }
    }

    public function excluirDefinitivamenteItemArquivado($id, $cursoEventoId, $moduloId, $justificativa, $usuarioId, $ipAddress = null, $userAgent = null)
    {
        $id = (int) $id;
        $cursoEventoId = (int) $cursoEventoId;
        $moduloId = (int) $moduloId;
        if ($id <= 0) {
            return array('ok' => false, 'message' => 'Item inválido.');
        }

        if ($cursoEventoId <= 0) {
            return array('ok' => false, 'message' => 'Curso inválido.');
        }

        if ($moduloId <= 0) {
            return array('ok' => false, 'message' => 'Módulo inválido.');
        }

        if (!$this->rbacService->isSuperAdmin($usuarioId)) {
            return array('ok' => false, 'message' => 'Somente superadministrador pode excluir definitivamente conteúdos arquivados.');
        }

        $item = $this->itemModel->findById($id);
        if (!$item) {
            return array('ok' => false, 'message' => 'Item não encontrado.');
        }

        if ((string) ($item['status'] ?? '') !== 'arquivado') {
            return array('ok' => false, 'message' => 'O conteúdo precisa estar arquivado para exclusão definitiva.');
        }

        if ((int) $item['curso_evento_id'] !== $cursoEventoId || (int) $item['modulo_id'] !== $moduloId) {
            return array('ok' => false, 'message' => 'O conteúdo não pertence ao módulo ou curso informados.');
        }

        if ((string) ($item['tipo'] ?? '') === 'arquivo') {
            return array('ok' => false, 'message' => 'Este conteúdo possui arquivo enviado e a exclusão definitiva foi bloqueada para evitar perda indevida de arquivos físicos.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $snapshot = array(
                'item' => $item,
                'detalhe' => $this->carregarDetalhePorTipo((string) $item['tipo'], $id),
            );

            $this->trashService->record('conteudo_item', $id, $justificativa, $snapshot, $usuarioId, $ipAddress, $userAgent);
            $this->itemModel->hardDelete($id);

            $this->auditService->record(
                'conteudo.item.excluido_definitivamente',
                'conteudo_item',
                $id,
                array(
                    'justificativa' => $justificativa,
                    'snapshot' => $snapshot,
                ),
                $usuarioId,
                $ipAddress,
                $userAgent
            );

            Logger::info('conteudo.item.excluido_definitivamente', array(
                'item_id' => $id,
                'usuario_id' => $usuarioId,
            ));

            $pdo->commit();
            return array('ok' => true);
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('conteudo.item.exclusao_definitiva_falhou', array('message' => $exception->getMessage()));
            throw $exception;
        }
    }

    public function registrarLogAluno($dados)
    {
        $payload = $this->normalizarLogAlunoPayload((array) $dados);
        if (empty($payload['ok'])) {
            return $payload;
        }

        $id = $this->logModel->create($payload['data']);
        return array('ok' => true, 'id' => $id);
    }

    public function registrarAcessoItem($dados)
    {
        $dados = (array) $dados;
        $dados['acao'] = isset($dados['acao']) && $dados['acao'] !== '' ? (string) $dados['acao'] : 'visualizou_item';
        $log = $this->registrarLogAluno($dados);
        if (empty($log['ok'])) {
            return $log;
        }

        $status = isset($dados['status']) && $dados['status'] !== '' ? (string) $dados['status'] : 'acessado';
        $now = date('Y-m-d H:i:s');

        $inscricaoId = isset($dados['inscricao_id']) ? (int) $dados['inscricao_id'] : 0;
        $alunoId = isset($dados['aluno_id']) ? (int) $dados['aluno_id'] : 0;
        $itemId = isset($dados['item_id']) ? (int) $dados['item_id'] : 0;
        $existing = ($inscricaoId > 0 && $alunoId > 0 && $itemId > 0)
            ? $this->progressoModel->findByContext($alunoId, $inscricaoId, $itemId)
            : null;

        if ($existing) {
            $existingStatus = isset($existing['status']) ? (string) $existing['status'] : '';
            if (in_array($existingStatus, array('concluido', 'pendente_correcao', 'reprovado'), true)) {
                $status = $existingStatus;
            } elseif ($existingStatus === 'acessado' && $status === 'acessado') {
                $status = 'em_andamento';
            }
        }

        $progresso = $this->registrarProgressoAluno(array_merge($dados, array(
            'status' => $status,
            'primeiro_acesso_em' => $now,
            'ultimo_acesso_em' => $now,
            'concluido_em' => $existing && !empty($existing['concluido_em']) ? $existing['concluido_em'] : null,
        )));

        return array('ok' => !empty($progresso['ok']), 'log_id' => $log['id'], 'progresso' => $progresso);
    }

    public function marcarItemComoConcluido($dados)
    {
        $dados = (array) $dados;
        $itemId = isset($dados['item_id']) ? (int) $dados['item_id'] : 0;
        $inscricaoId = isset($dados['inscricao_id']) ? (int) $dados['inscricao_id'] : 0;
        $alunoId = isset($dados['aluno_id']) ? (int) $dados['aluno_id'] : 0;

        if ($itemId <= 0 || $inscricaoId <= 0 || $alunoId <= 0) {
            return array('ok' => false, 'message' => 'Dados insuficientes para concluir item.');
        }

        $item = $this->itemModel->findById($itemId);
        if (!$item) {
            return array('ok' => false, 'message' => 'Item nÃ£o encontrado.');
        }

        if ((string) $item['tipo'] === 'avaliacao_textual' && !empty($item['obrigatorio'])) {
            $avaliacao = $this->avaliacaoTextualModel->findByItemId($itemId);
            if (!$avaliacao) {
                return array('ok' => false, 'message' => 'AvaliaÃ§Ã£o textual nÃ£o configurada para este item.');
            }

            $entrega = $this->avaliacaoEntregaModel->findLatestByContext((int) $avaliacao['id'], $alunoId, $inscricaoId);
            if (!$entrega) {
                return array('ok' => false, 'message' => 'VocÃª precisa enviar a avaliaÃ§Ã£o antes de concluir este item.');
            }

            $statusEntrega = isset($entrega['status']) ? (string) $entrega['status'] : '';
            if (in_array($statusEntrega, array('enviada', 'reenviada', 'devolvida'), true)) {
                return array('ok' => false, 'message' => 'A avaliaÃ§Ã£o ainda estÃ¡ pendente de correÃ§Ã£o.');
            }

            $notaMinima = array_key_exists('nota_minima', $avaliacao) ? $avaliacao['nota_minima'] : null;
            if ($notaMinima !== null && $notaMinima !== '') {
                if (!array_key_exists('nota', $entrega) || $entrega['nota'] === null || $entrega['nota'] === '') {
                    return array('ok' => false, 'message' => 'A avaliaÃ§Ã£o ainda nÃ£o possui nota registrada para conclusÃ£o.');
                }

                if ((float) $entrega['nota'] < (float) $notaMinima) {
                    return array('ok' => false, 'message' => 'A avaliaÃ§Ã£o foi corrigida, mas nÃ£o atingiu a nota mÃ­nima para conclusÃ£o.');
                }
            }
        }

        $dados['acao'] = 'marcou_como_concluido';
        $log = $this->registrarLogAluno($dados);
        if (empty($log['ok'])) {
            return $log;
        }

        $progresso = $this->registrarProgressoAluno(array_merge($dados, array(
            'status' => 'concluido',
            'percentual' => 100.00,
            'concluido_em' => isset($dados['concluido_em']) ? $dados['concluido_em'] : date('Y-m-d H:i:s'),
            'ultimo_acesso_em' => isset($dados['ultimo_acesso_em']) ? $dados['ultimo_acesso_em'] : date('Y-m-d H:i:s'),
        )));

        return array('ok' => !empty($progresso['ok']), 'log_id' => $log['id'], 'progresso' => $progresso);
    }

    public function recalcularProgressoInscricao($inscricaoId)
    {
        $inscricaoId = (int) $inscricaoId;
        if ($inscricaoId <= 0) {
            return array('ok' => false, 'message' => 'InscriÃ§Ã£o invÃ¡lida.');
        }

        $inscricao = $this->inscricaoModel->findById($inscricaoId);
        if (!$inscricao) {
            return array('ok' => false, 'message' => 'InscriÃ§Ã£o nÃ£o encontrada.');
        }

        $cursoEventoId = (int) $inscricao['curso_evento_id'];
        $alunoId = !empty($inscricao['usuario_id']) ? (int) $inscricao['usuario_id'] : 0;
        if ($alunoId <= 0) {
            return array('ok' => false, 'message' => 'Aluno invÃ¡lido na inscriÃ§Ã£o.');
        }

        $itensObrigatorios = $this->itemModel->listObrigatoriosPublicadosForCurso($cursoEventoId);
        $totalObrigatorios = count($itensObrigatorios);
        if ($totalObrigatorios <= 0) {
            return array('ok' => true, 'percentual' => 0.00, 'total_obrigatorios' => 0, 'concluidos' => 0);
        }

        $concluidos = 0;
        foreach ($itensObrigatorios as $item) {
            $registro = $this->progressoModel->findByContext($alunoId, $inscricaoId, (int) $item['id']);
            if ($registro && (string) $registro['status'] === 'concluido') {
                $concluidos++;
            }
        }

        $percentual = round(($concluidos / $totalObrigatorios) * 100, 2);
        $this->inscricaoModel->updateProgress($inscricaoId, $percentual);

        return array(
            'ok' => true,
            'percentual' => $percentual,
            'total_obrigatorios' => $totalObrigatorios,
            'concluidos' => $concluidos,
        );
    }

    private function registrarProgressoAluno(array $dados)
    {
        $payload = $this->normalizarProgressoAlunoPayload($dados);
        if (empty($payload['ok'])) {
            return $payload;
        }

        $id = $this->progressoModel->upsert($payload['data']);
        return array('ok' => true, 'id' => $id);
    }

    private function normalizarModuloPayload(array $dados)
    {
        $cursoEventoId = isset($dados['curso_evento_id']) ? (int) $dados['curso_evento_id'] : 0;
        if ($cursoEventoId <= 0) {
            return array('ok' => false, 'message' => 'Informe o curso do mÃ³dulo.');
        }

        $titulo = isset($dados['titulo']) ? trim((string) $dados['titulo']) : '';
        if ($titulo === '') {
            return array('ok' => false, 'message' => 'Informe o tÃ­tulo do mÃ³dulo.');
        }

        $status = isset($dados['status']) ? trim((string) $dados['status']) : 'publicado';
        if ($status === '') {
            $status = 'publicado';
        }
        if (!in_array($status, self::STATUS_MODULO_VALIDOS, true)) {
            return array('ok' => false, 'message' => 'Status do mÃ³dulo invÃ¡lido.');
        }

        return array(
            'ok' => true,
            'data' => array(
                'curso_evento_id' => $cursoEventoId,
                'titulo' => $titulo,
                'descricao' => isset($dados['descricao']) ? $dados['descricao'] : null,
                'ordem' => isset($dados['ordem']) ? (int) $dados['ordem'] : 0,
                'status' => $status,
                'criado_por' => isset($dados['criado_por']) ? (int) $dados['criado_por'] : null,
                'atualizado_por' => isset($dados['atualizado_por']) ? (int) $dados['atualizado_por'] : null,
            ),
        );
    }

    private function normalizarItemPayload(array $dados)
    {
        $cursoEventoId = isset($dados['curso_evento_id']) ? (int) $dados['curso_evento_id'] : 0;
        if ($cursoEventoId <= 0) {
            return array('ok' => false, 'message' => 'Informe o curso do item.');
        }

        $moduloId = isset($dados['modulo_id']) ? (int) $dados['modulo_id'] : 0;
        if ($moduloId <= 0) {
            return array('ok' => false, 'message' => 'Informe o mÃ³dulo do item.');
        }

        $modulo = $this->moduloModel->findById($moduloId);
        if (!$modulo || (int) $modulo['curso_evento_id'] !== $cursoEventoId) {
            return array('ok' => false, 'message' => 'O mÃ³dulo informado nÃ£o pertence ao curso.');
        }

        $tipo = isset($dados['tipo']) ? (string) $dados['tipo'] : '';
        if (!in_array($tipo, self::TIPOS_ITEM_VALIDOS, true)) {
            return array('ok' => false, 'message' => 'Tipo de item invÃ¡lido.');
        }

        if ($tipo === 'arquivo') {
            $arquivoValidacao = $this->validarArquivoMeta($dados);
            if (empty($arquivoValidacao['ok'])) {
                return $arquivoValidacao;
            }
        }

        $titulo = isset($dados['titulo']) ? trim((string) $dados['titulo']) : '';
        if ($titulo === '') {
            return array('ok' => false, 'message' => 'Informe o tÃ­tulo do item.');
        }

        $status = isset($dados['status']) ? trim((string) $dados['status']) : 'publicado';
        if ($status === '') {
            $status = 'publicado';
        }
        if (!in_array($status, self::STATUS_ITEM_VALIDOS, true)) {
            return array('ok' => false, 'message' => 'Status do item invÃ¡lido.');
        }

        $abreEm = isset($dados['abre_em']) && $dados['abre_em'] !== '' ? (string) $dados['abre_em'] : null;

        return array(
            'ok' => true,
            'data' => array(
                'curso_evento_id' => $cursoEventoId,
                'modulo_id' => $moduloId,
                'tipo' => $tipo,
                'titulo' => $titulo,
                'descricao_curta' => isset($dados['descricao_curta']) ? $dados['descricao_curta'] : null,
                'obrigatorio' => !empty($dados['obrigatorio']) ? 1 : 0,
                'ordem' => isset($dados['ordem']) ? (int) $dados['ordem'] : 0,
                'status' => $status,
                'abre_em' => $abreEm,
                'criado_por' => isset($dados['criado_por']) ? (int) $dados['criado_por'] : null,
                'atualizado_por' => isset($dados['atualizado_por']) ? (int) $dados['atualizado_por'] : null,
            ),
        );
    }

    private function anexarArquivoDetalheNosItens(array &$itens)
    {
        foreach ($itens as &$item) {
            if ((string) ($item['tipo'] ?? '') === 'arquivo') {
                $item['arquivo_detalhe'] = $this->arquivoModel->findByItemId((int) $item['id']);
            }
        }
        unset($item);
    }

    private function normalizarLogAlunoPayload(array $dados)
    {
        $cursoEventoId = isset($dados['curso_evento_id']) ? (int) $dados['curso_evento_id'] : 0;
        if ($cursoEventoId <= 0) {
            return array('ok' => false, 'message' => 'Curso invÃ¡lido para log.');
        }

        $alunoId = isset($dados['aluno_id']) ? (int) $dados['aluno_id'] : 0;
        if ($alunoId <= 0) {
            return array('ok' => false, 'message' => 'Aluno invÃ¡lido para log.');
        }

        $acao = isset($dados['acao']) ? trim((string) $dados['acao']) : '';
        if ($acao === '') {
            return array('ok' => false, 'message' => 'AÃ§Ã£o invÃ¡lida para log.');
        }

        $json = null;
        if (isset($dados['dados_json']) && $dados['dados_json'] !== null && $dados['dados_json'] !== '') {
            $json = is_string($dados['dados_json']) ? $dados['dados_json'] : json_encode($dados['dados_json']);
        }

        return array(
            'ok' => true,
            'data' => array(
                'curso_evento_id' => $cursoEventoId,
                'turma_id' => isset($dados['turma_id']) && $dados['turma_id'] !== '' ? (int) $dados['turma_id'] : null,
                'inscricao_id' => isset($dados['inscricao_id']) && $dados['inscricao_id'] !== '' ? (int) $dados['inscricao_id'] : null,
                'aluno_id' => $alunoId,
                'modulo_id' => isset($dados['modulo_id']) && $dados['modulo_id'] !== '' ? (int) $dados['modulo_id'] : null,
                'item_id' => isset($dados['item_id']) && $dados['item_id'] !== '' ? (int) $dados['item_id'] : null,
                'acao' => $acao,
                'dados_json' => $json,
                'ip' => isset($dados['ip']) ? $dados['ip'] : null,
                'user_agent' => isset($dados['user_agent']) ? $dados['user_agent'] : null,
            ),
        );
    }

    private function normalizarProgressoAlunoPayload(array $dados)
    {
        $cursoEventoId = isset($dados['curso_evento_id']) ? (int) $dados['curso_evento_id'] : 0;
        if ($cursoEventoId <= 0) {
            return array('ok' => false, 'message' => 'Curso invÃ¡lido para progresso.');
        }

        $inscricaoId = isset($dados['inscricao_id']) ? (int) $dados['inscricao_id'] : 0;
        if ($inscricaoId <= 0) {
            return array('ok' => false, 'message' => 'InscriÃ§Ã£o invÃ¡lida para progresso.');
        }

        $alunoId = isset($dados['aluno_id']) ? (int) $dados['aluno_id'] : 0;
        if ($alunoId <= 0) {
            return array('ok' => false, 'message' => 'Aluno invÃ¡lido para progresso.');
        }

        $moduloId = isset($dados['modulo_id']) ? (int) $dados['modulo_id'] : 0;
        $itemId = isset($dados['item_id']) ? (int) $dados['item_id'] : 0;
        if ($moduloId <= 0 || $itemId <= 0) {
            return array('ok' => false, 'message' => 'MÃ³dulo/item invÃ¡lido para progresso.');
        }

        $status = isset($dados['status']) ? (string) $dados['status'] : 'nao_iniciado';
        if (!in_array($status, self::STATUS_PROGRESO_VALIDOS, true)) {
            return array('ok' => false, 'message' => 'Status de progresso invÃ¡lido.');
        }

        $percentual = isset($dados['percentual']) ? (float) $dados['percentual'] : 0.00;
        if ($percentual < 0) {
            $percentual = 0.00;
        }
        if ($percentual > 100) {
            $percentual = 100.00;
        }

        return array(
            'ok' => true,
            'data' => array(
                'curso_evento_id' => $cursoEventoId,
                'turma_id' => isset($dados['turma_id']) && $dados['turma_id'] !== '' ? (int) $dados['turma_id'] : null,
                'inscricao_id' => $inscricaoId,
                'aluno_id' => $alunoId,
                'modulo_id' => $moduloId,
                'item_id' => $itemId,
                'status' => $status,
                'percentual' => $percentual,
                'obrigatorio' => !empty($dados['obrigatorio']) ? 1 : 0,
                'primeiro_acesso_em' => isset($dados['primeiro_acesso_em']) ? $dados['primeiro_acesso_em'] : null,
                'ultimo_acesso_em' => isset($dados['ultimo_acesso_em']) ? $dados['ultimo_acesso_em'] : null,
                'concluido_em' => isset($dados['concluido_em']) ? $dados['concluido_em'] : null,
            ),
        );
    }

    private function duplicarConteudoPorTipo($tipo, $itemIdOrigem, $itemIdDestino)
    {
        if ($tipo === 'texto') {
            $registro = $this->textoModel->findByItemId($itemIdOrigem);
            if ($registro && isset($registro['conteudo'])) {
                $this->textoModel->upsertByItemId($itemIdDestino, $registro['conteudo']);
            }
            return;
        }

        if ($tipo === 'etiqueta') {
            $registro = $this->etiquetaModel->findByItemId($itemIdOrigem);
            if ($registro && isset($registro['conteudo'])) {
                $this->etiquetaModel->upsertByItemId($itemIdDestino, $registro['conteudo']);
            }
            return;
        }

        if ($tipo === 'link') {
            $registro = $this->linkModel->findByItemId($itemIdOrigem);
            if ($registro && isset($registro['url'])) {
                $this->linkModel->upsertByItemId($itemIdDestino, array(
                    'url' => (string) $registro['url'],
                    'modo_abertura' => isset($registro['modo_abertura']) ? (string) $registro['modo_abertura'] : 'nova_aba',
                    'provedor' => $registro['provedor'],
                    'embed_html' => $registro['embed_html'],
                ));
            }
            return;
        }

        if ($tipo === 'video') {
            $registro = $this->videoModel->findByItemId($itemIdOrigem);
            if ($registro && isset($registro['url'])) {
                $this->videoModel->upsertByItemId($itemIdDestino, array(
                    'url' => (string) $registro['url'],
                    'provedor' => $registro['provedor'],
                    'embed_html' => $registro['embed_html'],
                    'duracao_segundos' => $registro['duracao_segundos'],
                ));
            }
            return;
        }

        if ($tipo === 'avaliacao_textual') {
            $registro = $this->avaliacaoTextualModel->findByItemId($itemIdOrigem);
            if ($registro) {
                $this->avaliacaoTextualModel->upsertByItemId($itemIdDestino, array(
                    'enunciado' => (string) $registro['enunciado'],
                    'orientacoes' => $registro['orientacoes'],
                    'nota_maxima' => $registro['nota_maxima'],
                    'nota_minima' => $registro['nota_minima'],
                    'peso' => $registro['peso'],
                    'prazo' => $registro['prazo'],
                    'permite_reenvio' => (int) $registro['permite_reenvio'],
                    'reenvio_livre_ate_prazo' => (int) $registro['reenvio_livre_ate_prazo'],
                ));
            }
            return;
        }

        if ($tipo === 'arquivo') {
            // Conservador:
            // - duplica o item como rascunho (feito no caller)
            // - NÃƒO referencia arquivo fÃ­sico nem versÃµes
            // - se existir metadado, cria registro "vazio" para exigir novo upload
            $arquivo = $this->arquivoModel->findByItemId($itemIdOrigem);
            if ($arquivo) {
                $this->arquivoModel->create(array(
                    'item_id' => (int) $itemIdDestino,
                    'nome_original' => $arquivo['nome_original'],
                    'nome_arquivo' => null,
                    'caminho' => null,
                    'mime_type' => $arquivo['mime_type'],
                    'extensao' => $arquivo['extensao'],
                    'tamanho_bytes' => $arquivo['tamanho_bytes'],
                    'versao_atual_id' => null,
                    'permite_download' => 0,
                ));
            }
            return;
        }
    }

    private function moduloTemArquivoFisico(array $itens)
    {
        foreach ($itens as $item) {
            if ((string) ($item['tipo'] ?? '') === 'arquivo') {
                return true;
            }
        }

        return false;
    }

    private function validarArquivoMeta(array $dados)
    {
        $extensao = isset($dados['extensao']) ? strtolower(trim((string) $dados['extensao'])) : '';
        if ($extensao !== '' && !in_array($extensao, self::EXTENSOES_ARQUIVO_VALIDAS, true)) {
            return array('ok' => false, 'message' => 'ExtensÃ£o de arquivo nÃ£o permitida.');
        }

        if (isset($dados['tamanho_bytes']) && $dados['tamanho_bytes'] !== null && $dados['tamanho_bytes'] !== '') {
            $tamanho = (int) $dados['tamanho_bytes'];
            if ($tamanho > self::LIMITE_ARQUIVO_BYTES) {
                return array('ok' => false, 'message' => 'Arquivo excede o limite de 10 MB.');
            }
        }

        return array('ok' => true);
    }

    public function salvarItemComDetalhes($dados, ?array $arquivoUpload, $usuarioId)
    {
        $dados = (array) $dados;
        $id = isset($dados['id']) ? (int) $dados['id'] : 0;

        $dados['descricao_curta'] = isset($dados['descricao_curta']) ? trim((string) $dados['descricao_curta']) : null;
        $dados['atualizado_por'] = $usuarioId ? (int) $usuarioId : null;
        if ($id <= 0) {
            $dados['criado_por'] = $usuarioId ? (int) $usuarioId : null;
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            if ($id > 0) {
                $resultado = $this->atualizarItem($id, $dados);
                if (empty($resultado['ok'])) {
                    $pdo->rollBack();
                    return $resultado;
                }
                $itemId = $id;
            } else {
                $resultado = $this->criarItem($dados);
                if (empty($resultado['ok']) || empty($resultado['id'])) {
                    $pdo->rollBack();
                    return $resultado;
                }
                $itemId = (int) $resultado['id'];
            }

            $item = $this->itemModel->findById($itemId);
            if (!$item) {
                $pdo->rollBack();
                return array('ok' => false, 'message' => 'Item nÃƒÂ£o encontrado apÃƒÂ³s salvar.');
            }

            $detalhes = $this->salvarDetalhesPorTipo($itemId, (string) $item['tipo'], $dados, $arquivoUpload, $usuarioId);
            if (empty($detalhes['ok'])) {
                $pdo->rollBack();
                return $detalhes;
            }

            $pdo->commit();
            return array('ok' => true, 'id' => $itemId);
        } catch (\InvalidArgumentException $exception) {
            $pdo->rollBack();
            return array('ok' => false, 'message' => $exception->getMessage());
        } catch (\RuntimeException $exception) {
            $pdo->rollBack();
            Logger::error('conteudo.item.salvar_arquivo_falhou', array('message' => $exception->getMessage()));
            return array('ok' => false, 'message' => 'Não foi possível salvar o arquivo enviado.');
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('conteudo.item.salvar_falhou', array('message' => $exception->getMessage()));
            throw $exception;
        }
    }

    public function obterArquivoDoItem($itemId, $cursoEventoId)
    {
        $itemId = (int) $itemId;
        $cursoEventoId = (int) $cursoEventoId;
        if ($itemId <= 0 || $cursoEventoId <= 0) {
            return array('ok' => false, 'message' => 'Parâmetros inválidos.');
        }

        $item = $this->itemModel->findById($itemId);
        if (!$item || (int) $item['curso_evento_id'] !== $cursoEventoId || (string) $item['tipo'] !== 'arquivo') {
            return array('ok' => false, 'message' => 'Arquivo de conteúdo não encontrado.');
        }

        $arquivo = $this->arquivoModel->findByItemId($itemId);
        if (!$arquivo || empty($arquivo['caminho'])) {
            return array('ok' => false, 'message' => 'Arquivo de conteúdo indisponível.');
        }

        return array('ok' => true, 'item' => $item, 'arquivo' => $arquivo);
    }

    public function detalharModulo($id, $cursoEventoId)
    {
        $id = (int) $id;
        $cursoEventoId = (int) $cursoEventoId;
        if ($id <= 0 || $cursoEventoId <= 0) {
            return array('ok' => false, 'message' => 'ParÃƒÂ¢metros invÃƒÂ¡lidos.');
        }

        $modulo = $this->moduloModel->findById($id);
        if (!$modulo || (int) $modulo['curso_evento_id'] !== $cursoEventoId) {
            return array('ok' => false, 'message' => 'MÃƒÂ³dulo nÃƒÂ£o encontrado.');
        }

        return array('ok' => true, 'modulo' => $modulo);
    }

    public function detalharItem($id, $cursoEventoId)
    {
        $id = (int) $id;
        $cursoEventoId = (int) $cursoEventoId;
        if ($id <= 0 || $cursoEventoId <= 0) {
            return array('ok' => false, 'message' => 'ParÃƒÂ¢metros invÃƒÂ¡lidos.');
        }

        $item = $this->itemModel->findById($id);
        if (!$item || (int) $item['curso_evento_id'] !== $cursoEventoId) {
            return array('ok' => false, 'message' => 'Item nÃƒÂ£o encontrado.');
        }

        $detalhe = $this->carregarDetalhePorTipo((string) $item['tipo'], (int) $item['id']);
        return array('ok' => true, 'item' => $item, 'detalhe' => $detalhe);
    }

    private function carregarDetalhePorTipo($tipo, $itemId)
    {
        if ($tipo === 'texto') {
            return $this->textoModel->findByItemId($itemId);
        }
        if ($tipo === 'etiqueta') {
            return $this->etiquetaModel->findByItemId($itemId);
        }
        if ($tipo === 'link') {
            return $this->linkModel->findByItemId($itemId);
        }
        if ($tipo === 'video') {
            return $this->videoModel->findByItemId($itemId);
        }
        if ($tipo === 'avaliacao_textual') {
            return $this->avaliacaoTextualModel->findByItemId($itemId);
        }
        if ($tipo === 'arquivo') {
            return $this->arquivoModel->findByItemId($itemId);
        }

        return null;
    }

    private function enriquecerItemParaAluno(array $item, $cursoEventoId, $inscricaoId, $turmaId = null)
    {
        $tipo = (string) ($item['tipo'] ?? '');
        $progresso = !empty($item['progresso_aluno']) && is_array($item['progresso_aluno']) ? $item['progresso_aluno'] : null;
        $entrega = !empty($item['avaliacao_entrega']) && is_array($item['avaliacao_entrega']) ? $item['avaliacao_entrega'] : null;
        $statusPublico = $this->statusItemPublicoAluno($tipo, $progresso, $entrega);

        $item['status_publico'] = $statusPublico;
        $item['status_label'] = $this->rotuloStatusPublicoAluno($statusPublico);
        $item['status_class'] = $this->classeStatusPublicoAluno($statusPublico);
        $item['concluido_aluno'] = in_array($statusPublico, array('concluido', 'aprovada', 'corrigida'), true);
        $item['aguardando_correcao'] = in_array($statusPublico, array('aguardando_correcao', 'pendente_correcao'), true);
        $item['tipo_label'] = $this->rotuloTipoItemPublicoAluno($tipo);
        $item['acao_label'] = $this->acaoItemPublicaAluno($tipo, $entrega);
        $item['acao_url'] = $this->urlAcaoItemPublicaAluno($item, $cursoEventoId, $inscricaoId, $turmaId, $tipo, $entrega);
        $item['detalhes_url'] = $this->urlDetalhesItemPublicoAluno($item, $cursoEventoId, $inscricaoId, $turmaId);

        return $item;
    }

    private function statusItemPublicoAluno($tipo, ?array $progresso, ?array $entrega)
    {
        $tipo = (string) $tipo;

        if ($tipo === 'avaliacao_textual') {
            $statusEntrega = !empty($entrega['status']) ? (string) $entrega['status'] : '';
            if ($statusEntrega === '') {
                return 'aguardando_envio';
            }
            if (in_array($statusEntrega, array('enviada', 'reenviada'), true)) {
                return 'aguardando_correcao';
            }
            if ($statusEntrega === 'devolvida') {
                return 'devolvida';
            }
            if ($statusEntrega === 'reprovada') {
                return 'reprovada';
            }
            if ($statusEntrega === 'aprovada') {
                return 'aprovada';
            }
            if ($statusEntrega === 'corrigida') {
                return 'corrigida';
            }
            if ($statusEntrega === 'cancelada') {
                return 'cancelada';
            }

            return $statusEntrega !== '' ? $statusEntrega : 'aguardando_envio';
        }

        if ($tipo === 'etiqueta') {
            if (!empty($progresso) && !empty($progresso['status']) && (string) $progresso['status'] === 'concluido') {
                return 'concluido';
            }
            if (!empty($progresso) && !empty($progresso['status'])) {
                return (string) $progresso['status'];
            }
            return 'nao_iniciado';
        }

        $status = !empty($progresso) && !empty($progresso['status']) ? (string) $progresso['status'] : 'nao_iniciado';
        if ($status === 'acessado') {
            return 'em_andamento';
        }

        return $status;
    }

    private function statusModuloPublicoAluno(array $modulo)
    {
        $totalObrigatorios = isset($modulo['total_obrigatorios']) ? (int) $modulo['total_obrigatorios'] : 0;
        $concluidosObrigatorios = isset($modulo['concluidos_obrigatorios']) ? (int) $modulo['concluidos_obrigatorios'] : 0;
        $totalItens = isset($modulo['total_itens']) ? (int) $modulo['total_itens'] : 0;
        $concluidosItens = isset($modulo['concluidos_itens']) ? (int) $modulo['concluidos_itens'] : 0;
        $avaliacoesPendentes = isset($modulo['avaliacoes_pendentes']) ? (int) $modulo['avaliacoes_pendentes'] : 0;

        if ($totalItens <= 0) {
            return 'pendente';
        }
        if ($avaliacoesPendentes > 0) {
            return 'aguardando_correcao';
        }
        if ($totalObrigatorios > 0 && $concluidosObrigatorios >= $totalObrigatorios) {
            return 'concluido';
        }
        if ($concluidosItens > 0) {
            return 'em_andamento';
        }

        return 'pendente';
    }

    private function statusGeralAluno(array $resumo)
    {
        $totalItens = isset($resumo['total_itens']) ? (int) $resumo['total_itens'] : 0;
        $concluidosItens = isset($resumo['concluidos_itens']) ? (int) $resumo['concluidos_itens'] : 0;
        $totalObrigatorios = isset($resumo['total_obrigatorios']) ? (int) $resumo['total_obrigatorios'] : 0;
        $concluidosObrigatorios = isset($resumo['concluidos_obrigatorios']) ? (int) $resumo['concluidos_obrigatorios'] : 0;
        $avaliacoesPendentes = isset($resumo['avaliacoes_pendentes']) ? (int) $resumo['avaliacoes_pendentes'] : 0;

        if ($totalItens <= 0) {
            return 'pendente';
        }
        if ($avaliacoesPendentes > 0) {
            return 'aguardando_correcao';
        }
        if ($totalObrigatorios > 0 && $concluidosObrigatorios >= $totalObrigatorios) {
            return 'concluido';
        }
        if ($concluidosItens > 0) {
            return 'em_andamento';
        }

        return 'pendente';
    }

    private function rotuloTipoItemPublicoAluno($tipo)
    {
        $mapa = array(
            'etiqueta' => 'Etiqueta',
            'texto' => 'Texto',
            'arquivo' => 'Arquivo',
            'link' => 'Link externo',
            'video' => 'Vídeo',
            'avaliacao_textual' => 'Avaliação textual',
        );

        $tipo = (string) $tipo;
        return isset($mapa[$tipo]) ? $mapa[$tipo] : 'Conteúdo';
    }

    private function acaoItemPublicaAluno($tipo, ?array $entrega = null)
    {
        $tipo = (string) $tipo;
        if ($tipo === 'texto') {
            return 'Abrir conteúdo';
        }
        if ($tipo === 'arquivo') {
            return 'Baixar arquivo';
        }
        if ($tipo === 'link') {
            return 'Acessar link';
        }
        if ($tipo === 'video') {
            return 'Assistir vídeo';
        }
        if ($tipo === 'avaliacao_textual') {
            $statusEntrega = !empty($entrega['status']) ? (string) $entrega['status'] : '';
            return in_array($statusEntrega, array('enviada', 'reenviada', 'devolvida', 'corrigida', 'aprovada', 'reprovada'), true)
                ? 'Ver avaliação'
                : 'Responder avaliação';
        }

        return 'Ver conteúdo';
    }

    private function urlDetalhesItemPublicoAluno(array $item, $cursoEventoId, $inscricaoId, $turmaId = null)
    {
        $moduloId = !empty($item['modulo_id']) ? (int) $item['modulo_id'] : 0;
        $itemId = (int) ($item['id'] ?? 0);
        $url = '/aluno/curso/' . (int) $inscricaoId . '/' . (int) $cursoEventoId . '/' . ($turmaId !== null ? (int) $turmaId : 0);
        if ($moduloId > 0) {
            $url .= '/modulo/' . $moduloId;
        }
        $url .= '/conteudo/' . $itemId;

        return $url;
    }

    private function urlAcaoItemPublicaAluno(array $item, $cursoEventoId, $inscricaoId, $turmaId = null, $tipo = null, ?array $entrega = null)
    {
        $tipo = $tipo !== null ? (string) $tipo : (string) ($item['tipo'] ?? '');
        if ($tipo === 'link') {
            return '/aluno/cursos/conteudo/link/acessar?id=' . (int) ($item['id'] ?? 0) . '&inscricao_id=' . (int) $inscricaoId . '&curso_id=' . (int) $cursoEventoId . ($turmaId !== null && (int) $turmaId > 0 ? '&turma_id=' . (int) $turmaId : '');
        }
        if ($tipo === 'arquivo') {
            return '/aluno/cursos/conteudo/arquivo/download?id=' . (int) ($item['id'] ?? 0) . '&inscricao_id=' . (int) $inscricaoId . '&curso_id=' . (int) $cursoEventoId . ($turmaId !== null && (int) $turmaId > 0 ? '&turma_id=' . (int) $turmaId : '');
        }

        return $this->urlDetalhesItemPublicoAluno($item, $cursoEventoId, $inscricaoId, $turmaId);
    }

    private function rotuloStatusPublicoAluno($status)
    {
        $mapa = array(
            'nao_iniciado' => 'Não iniciado',
            'acessado' => 'Acessado',
            'em_andamento' => 'Em andamento',
            'concluido' => 'Concluído',
            'aguardando_envio' => 'Aguardando envio',
            'aguardando_correcao' => 'Aguardando correção',
            'pendente_correcao' => 'Aguardando correção',
            'devolvida' => 'Devolvida',
            'aprovada' => 'Aprovada',
            'reprovada' => 'Reprovada',
            'corrigida' => 'Corrigida',
            'cancelada' => 'Cancelada',
            'pendente' => 'Pendente',
        );

        $status = (string) $status;
        if (isset($mapa[$status])) {
            return $mapa[$status];
        }

        return $status !== '' ? ucwords(str_replace('_', ' ', $status)) : '-';
    }

    private function classeStatusPublicoAluno($status)
    {
        $status = (string) $status;
        if (in_array($status, array('concluido', 'aprovada', 'corrigida'), true)) {
            return 'success';
        }
        if (in_array($status, array('aguardando_correcao', 'pendente_correcao', 'devolvida', 'aguardando_envio'), true)) {
            return 'warning';
        }
        if (in_array($status, array('reprovada', 'cancelada'), true)) {
            return 'danger';
        }
        if (in_array($status, array('em_andamento', 'acessado'), true)) {
            return 'info';
        }

        return 'neutral';
    }

    private function salvarDetalhesPorTipo($itemId, $tipo, array $dados, ?array $arquivoUpload, $usuarioId)
    {
        if ($tipo === 'etiqueta') {
            $conteudo = isset($dados['etiqueta_conteudo']) ? HtmlSanitizer::clean((string) $dados['etiqueta_conteudo'], 'full') : '';
            $this->etiquetaModel->upsertByItemId($itemId, $conteudo);
            return array('ok' => true);
        }

        if ($tipo === 'texto') {
            $conteudo = isset($dados['texto_conteudo']) ? HtmlSanitizer::clean((string) $dados['texto_conteudo'], 'full') : '';
            $this->textoModel->upsertByItemId($itemId, $conteudo);
            return array('ok' => true);
        }

        if ($tipo === 'link') {
            $url = isset($dados['link_url']) ? trim((string) $dados['link_url']) : '';
            if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
                return array('ok' => false, 'message' => 'Informe uma URL vÃƒÂ¡lida para o link.');
            }

            $modo = isset($dados['link_modo_abertura']) ? (string) $dados['link_modo_abertura'] : 'nova_aba';
            if (!in_array($modo, array('nova_aba', 'embed', 'botao'), true)) {
                $modo = 'nova_aba';
            }

            $provedor = $this->detectarProvedorUrl($url);
            $this->linkModel->upsertByItemId($itemId, array(
                'url' => $url,
                'modo_abertura' => $modo,
                'provedor' => $provedor,
                'embed_html' => null,
            ));
            return array('ok' => true);
        }

        if ($tipo === 'video') {
            $url = isset($dados['video_url']) ? trim((string) $dados['video_url']) : '';
            if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
                return array('ok' => false, 'message' => 'Informe uma URL vÃƒÂ¡lida para o vÃƒÂ­deo.');
            }

            $provedor = $this->detectarProvedorUrl($url);
            $this->videoModel->upsertByItemId($itemId, array(
                'url' => $url,
                'provedor' => $provedor,
                'embed_html' => null,
                'duracao_segundos' => isset($dados['video_duracao_segundos']) && $dados['video_duracao_segundos'] !== '' ? (int) $dados['video_duracao_segundos'] : null,
            ));
            return array('ok' => true);
        }

        if ($tipo === 'avaliacao_textual') {
            $enunciado = isset($dados['avaliacao_enunciado']) ? HtmlSanitizer::clean((string) $dados['avaliacao_enunciado'], 'full') : '';
            if (trim(strip_tags($enunciado)) === '') {
                return array('ok' => false, 'message' => 'Informe o enunciado da avaliaÃƒÂ§ÃƒÂ£o textual.');
            }

            $orientacoes = isset($dados['avaliacao_orientacoes']) ? HtmlSanitizer::clean((string) $dados['avaliacao_orientacoes'], 'basic') : null;

            $notaMaximaNormalizada = $this->normalizarDecimalInput($dados['avaliacao_nota_maxima'] ?? null, 'nota máxima', true);
            if (empty($notaMaximaNormalizada['ok'])) {
                return $notaMaximaNormalizada;
            }
            $notaMinimaNormalizada = $this->normalizarDecimalInput($dados['avaliacao_nota_minima'] ?? null, 'nota mínima', true);
            if (empty($notaMinimaNormalizada['ok'])) {
                return $notaMinimaNormalizada;
            }
            $notaMaxima = $notaMaximaNormalizada['value'];
            $notaMinima = $notaMinimaNormalizada['value'];

            if ($notaMaxima !== null && $notaMinima !== null && $notaMinima > $notaMaxima) {
                return array('ok' => false, 'message' => 'A nota mÃƒÂ­nima nÃƒÂ£o pode ser maior que a nota mÃƒÂ¡xima.');
            }

            $pesoNormalizado = $this->normalizarDecimalInput($dados['avaliacao_peso'] ?? 1, 'peso', false);
            if (empty($pesoNormalizado['ok'])) {
                return $pesoNormalizado;
            }
            $peso = $pesoNormalizado['value'];
            if ($peso <= 0) {
                return array('ok' => false, 'message' => 'O peso da avaliaÃƒÂ§ÃƒÂ£o deve ser maior que zero.');
            }

            $prazo = isset($dados['avaliacao_prazo']) ? trim((string) $dados['avaliacao_prazo']) : '';
            $prazo = $this->normalizarDataHoraEntrada($prazo);
            if ($prazo === '') {
                $prazo = null;
            } elseif (!$this->dataHoraValida($prazo)) {
                return array('ok' => false, 'message' => 'Informe um prazo vÃƒÂ¡lido (data e hora) para a avaliaÃƒÂ§ÃƒÂ£o.');
            }

            $permiteReenvio = !empty($dados['avaliacao_permite_reenvio']) ? 1 : 0;
            $reenvioLivreAtePrazo = !empty($dados['avaliacao_reenvio_livre_ate_prazo']) ? 1 : 0;

            $this->avaliacaoTextualModel->upsertByItemId($itemId, array(
                'enunciado' => $enunciado,
                'orientacoes' => $orientacoes,
                'nota_maxima' => $notaMaxima !== null ? number_format($notaMaxima, 2, '.', '') : null,
                'nota_minima' => $notaMinima !== null ? number_format($notaMinima, 2, '.', '') : null,
                'peso' => number_format($peso, 2, '.', ''),
                'prazo' => $prazo,
                'permite_reenvio' => $permiteReenvio,
                'reenvio_livre_ate_prazo' => $reenvioLivreAtePrazo,
            ));

            return array('ok' => true);
        }

        if ($tipo === 'arquivo') {
            $arquivoExistente = $this->arquivoModel->findByItemId($itemId);
            $permiteDownload = !empty($dados['arquivo_permite_download']) ? 1 : 0;

            if ($arquivoUpload && !empty($arquivoUpload['tmp_name'])) {
                $upload = $this->uploadArquivoConteudo($itemId, $arquivoUpload);

                if ($arquivoExistente) {
                    $arquivoId = (int) $arquivoExistente['id'];
                    $this->arquivoModel->update(array(
                        'nome_original' => $upload['original_name'],
                        'nome_arquivo' => basename($upload['relative_path']),
                        'caminho' => $upload['relative_path'],
                        'mime_type' => $upload['mime_type'],
                        'extensao' => $upload['extension'],
                        'tamanho_bytes' => $upload['size'],
                        'permite_download' => $permiteDownload,
                    ), $arquivoId);
                } else {
                    $arquivoId = $this->arquivoModel->create(array(
                        'item_id' => (int) $itemId,
                        'nome_original' => $upload['original_name'],
                        'nome_arquivo' => basename($upload['relative_path']),
                        'caminho' => $upload['relative_path'],
                        'mime_type' => $upload['mime_type'],
                        'extensao' => $upload['extension'],
                        'tamanho_bytes' => $upload['size'],
                        'versao_atual_id' => null,
                        'permite_download' => $permiteDownload,
                    ));
                }

                $versaoAtual = 0;
                $versoes = $this->arquivoVersaoModel->listForArquivo($arquivoId);
                if (!empty($versoes)) {
                    $versaoAtual = (int) $versoes[0]['versao'];
                }

                $versaoId = $this->arquivoVersaoModel->create(array(
                    'arquivo_id' => $arquivoId,
                    'item_id' => (int) $itemId,
                    'nome_original' => $upload['original_name'],
                    'nome_arquivo' => basename($upload['relative_path']),
                    'caminho' => $upload['relative_path'],
                    'mime_type' => $upload['mime_type'],
                    'extensao' => $upload['extension'],
                    'tamanho_bytes' => $upload['size'],
                    'versao' => $versaoAtual + 1,
                    'substituido_por' => null,
                    'criado_por' => $usuarioId ? (int) $usuarioId : null,
                ));

                if (!empty($arquivoExistente['versao_atual_id'])) {
                    $this->arquivoVersaoModel->marcarSubstituida((int) $arquivoExistente['versao_atual_id'], $versaoId);
                }
                $this->arquivoModel->setVersaoAtual($arquivoId, $versaoId);
                return array('ok' => true);
            }

            if ($arquivoExistente) {
                $this->arquivoModel->update(array(
                    'nome_original' => $arquivoExistente['nome_original'],
                    'nome_arquivo' => $arquivoExistente['nome_arquivo'],
                    'caminho' => $arquivoExistente['caminho'],
                    'mime_type' => $arquivoExistente['mime_type'],
                    'extensao' => $arquivoExistente['extensao'],
                    'tamanho_bytes' => $arquivoExistente['tamanho_bytes'],
                    'versao_atual_id' => $arquivoExistente['versao_atual_id'],
                    'permite_download' => $permiteDownload,
                ), (int) $arquivoExistente['id']);
            } else {
                $extensao = isset($dados['extensao']) ? strtolower(trim((string) $dados['extensao'])) : null;
                $tamanhoBytes = isset($dados['tamanho_bytes']) && $dados['tamanho_bytes'] !== '' ? (int) $dados['tamanho_bytes'] : null;

                $arquivoValidacao = $this->validarArquivoMeta(array(
                    'extensao' => $extensao,
                    'tamanho_bytes' => $tamanhoBytes,
                ));
                if (empty($arquivoValidacao['ok'])) {
                    return $arquivoValidacao;
                }

                $this->arquivoModel->create(array(
                    'item_id' => (int) $itemId,
                    'nome_original' => isset($dados['nome_original']) ? trim((string) $dados['nome_original']) : null,
                    'nome_arquivo' => null,
                    'caminho' => null,
                    'mime_type' => isset($dados['mime_type']) ? trim((string) $dados['mime_type']) : null,
                    'extensao' => $extensao,
                    'tamanho_bytes' => $tamanhoBytes,
                    'versao_atual_id' => null,
                    'permite_download' => $permiteDownload,
                ));
            }

            return array('ok' => true);
        }

        return array('ok' => true);
    }

    private function detectarProvedorUrl($url)
    {
        $host = (string) (parse_url((string) $url, PHP_URL_HOST) ?: '');
        $host = strtolower($host);
        $host = preg_replace('/^www\\./', '', $host);

        if (strpos($host, 'youtube.com') !== false || strpos($host, 'youtu.be') !== false) {
            return 'youtube';
        }
        if (strpos($host, 'vimeo.com') !== false) {
            return 'vimeo';
        }

        return $host !== '' ? $host : null;
    }

    private function dataHoraValida($valor)
    {
        $valor = trim((string) $valor);
        if ($valor === '') {
            return false;
        }

        $dt = \DateTime::createFromFormat('Y-m-d H:i:s', $valor);
        if ($dt && $dt->format('Y-m-d H:i:s') === $valor) {
            return true;
        }

        $dt = \DateTime::createFromFormat('Y-m-d\\TH:i', $valor);
        if ($dt) {
            return true;
        }

        return false;
    }

    private function normalizarDataHoraEntrada($valor)
    {
        $valor = trim((string) $valor);
        if ($valor === '') {
            return '';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $valor)) {
            return str_replace('T', ' ', $valor) . ':00';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/', $valor)) {
            return str_replace('T', ' ', $valor);
        }

        return $valor;
    }

    private function normalizarDecimalInput($valor, $campo, $permitirNulo = true)
    {
        if ($valor === null) {
            return array('ok' => true, 'value' => null);
        }

        $texto = trim((string) $valor);
        if ($texto === '') {
            return $permitirNulo
                ? array('ok' => true, 'value' => null)
                : array('ok' => false, 'message' => 'Informe um valor vÃ¡lido para ' . $campo . '.');
        }

        if (strpos($texto, ',') !== false && strpos($texto, '.') !== false) {
            return array('ok' => false, 'message' => 'Formato invÃ¡lido para ' . $campo . '. Use apenas vÃ­rgula ou ponto decimal.');
        }

        if (!preg_match('/^\d+(?:[\.,]\d+)?$/', $texto)) {
            return array('ok' => false, 'message' => 'Formato invÃ¡lido para ' . $campo . '.');
        }

        $normalizado = str_replace(',', '.', $texto);
        if (!is_numeric($normalizado)) {
            return array('ok' => false, 'message' => 'Formato invÃ¡lido para ' . $campo . '.');
        }

        return array('ok' => true, 'value' => (float) $normalizado);
    }

    private function uploadArquivoConteudo($itemId, array $arquivoUpload)
    {
        $originalName = isset($arquivoUpload['name']) ? (string) $arquivoUpload['name'] : '';
        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));

        if ($extension === '' || !in_array($extension, self::EXTENSOES_ARQUIVO_VALIDAS, true)) {
            throw new \InvalidArgumentException('ExtensÃƒÂ£o de arquivo nÃƒÂ£o permitida.');
        }

        if (!empty($arquivoUpload['size']) && (int) $arquivoUpload['size'] > self::LIMITE_ARQUIVO_BYTES) {
            throw new \InvalidArgumentException('Arquivo excede o limite de 10 MB.');
        }

        $storage = new FileStorageService();
        $directory = 'conteudos/itens/' . (int) $itemId;
        $upload = $storage->storeUploadedFile($arquivoUpload, $directory, 'conteudo', array(
            'max_size_bytes' => self::LIMITE_ARQUIVO_BYTES,
            'allowed_extensions' => self::EXTENSOES_ARQUIVO_VALIDAS,
        ));

        return array(
            'relative_path' => $upload['relative_path'],
            'original_name' => $upload['original_name'],
            'mime_type' => $upload['mime_type'],
            'size' => isset($upload['size']) ? (int) $upload['size'] : null,
            'extension' => $extension,
        );
    }
}

