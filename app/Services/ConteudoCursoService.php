<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
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
use App\Support\HtmlSanitizer;
use Exception;

class ConteudoCursoService
{
    private const TIPOS_ITEM_VALIDOS = array('etiqueta', 'texto', 'arquivo', 'link', 'avaliacao_textual', 'video');
    private const STATUS_ITEM_VALIDOS = array('rascunho', 'publicado', 'oculto', 'arquivado');
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
    }

    public function listarModulosComItens($cursoEventoId)
    {
        $cursoEventoId = (int) $cursoEventoId;
        if ($cursoEventoId <= 0) {
            return array('ok' => false, 'message' => 'Curso inválido.');
        }

        $modulos = $this->moduloModel->listForCurso($cursoEventoId);
        foreach ($modulos as &$modulo) {
            $modulo['itens'] = $this->itemModel->listForModulo((int) $modulo['id']);
        }
        unset($modulo);

        return array('ok' => true, 'modulos' => $modulos);
    }

    public function criarModulo($dados)
    {
        $payload = $this->normalizarModuloPayload((array) $dados);
        if (empty($payload['ok'])) {
            return $payload;
        }

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
                if (!$modulo || (int) $modulo['curso_evento_id'] !== $cursoEventoId) {
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
            return array('ok' => false, 'message' => 'Módulo inválido.');
        }

        $origem = $this->moduloModel->findById($id);
        if (!$origem) {
            return array('ok' => false, 'message' => 'Módulo não encontrado.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $novoModuloId = $this->moduloModel->create(array(
                'curso_evento_id' => (int) $origem['curso_evento_id'],
                'titulo' => 'Cópia - ' . (string) $origem['titulo'],
                'descricao' => $origem['descricao'],
                'ordem' => (int) $origem['ordem'] + 1,
                'status' => 'rascunho',
                'criado_por' => $usuarioId ? (int) $usuarioId : null,
                'atualizado_por' => $usuarioId ? (int) $usuarioId : null,
            ));

            $itens = $this->itemModel->listForModulo($id);
            foreach ($itens as $item) {
                $novoItemId = $this->itemModel->create(array(
                    'curso_evento_id' => (int) $item['curso_evento_id'],
                    'modulo_id' => $novoModuloId,
                    'tipo' => (string) $item['tipo'],
                    'titulo' => (string) $item['titulo'],
                    'descricao_curta' => $item['descricao_curta'],
                    'obrigatorio' => (int) $item['obrigatorio'],
                    'ordem' => (int) $item['ordem'],
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

        $id = $this->itemModel->create($payload['data']);
        return array('ok' => true, 'id' => $id);
    }

    public function atualizarItem($id, $dados)
    {
        $id = (int) $id;
        if ($id <= 0) {
            return array('ok' => false, 'message' => 'Item inválido.');
        }

        $existente = $this->itemModel->findById($id);
        if (!$existente) {
            return array('ok' => false, 'message' => 'Item não encontrado.');
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
            return array('ok' => false, 'message' => 'Item inválido.');
        }

        $ok = $this->itemModel->updateStatus($id, 'arquivado', $usuarioId ? (int) $usuarioId : null);
        return array('ok' => $ok);
    }

    public function moverItemParaModulo($itemId, $novoModuloId, $usuarioId)
    {
        $itemId = (int) $itemId;
        $novoModuloId = (int) $novoModuloId;
        if ($itemId <= 0 || $novoModuloId <= 0) {
            return array('ok' => false, 'message' => 'Parâmetros inválidos.');
        }

        $item = $this->itemModel->findById($itemId);
        $modulo = $this->moduloModel->findById($novoModuloId);
        if (!$item || !$modulo) {
            return array('ok' => false, 'message' => 'Item ou módulo não encontrado.');
        }

        if ((int) $item['curso_evento_id'] !== (int) $modulo['curso_evento_id']) {
            return array('ok' => false, 'message' => 'O item não pertence ao mesmo curso do módulo.');
        }

        $ok = $this->itemModel->moverParaModulo($itemId, $novoModuloId, $usuarioId ? (int) $usuarioId : null);
        return array('ok' => $ok);
    }

    public function reordenarItens($moduloId, array $ordens)
    {
        $moduloId = (int) $moduloId;
        if ($moduloId <= 0) {
            return array('ok' => false, 'message' => 'Módulo inválido.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $itens = $this->itemModel->listForModulo($moduloId);
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
            return array('ok' => false, 'message' => 'Item inválido.');
        }

        $origem = $this->itemModel->findById($id);
        if (!$origem) {
            return array('ok' => false, 'message' => 'Item não encontrado.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $novoItemId = $this->itemModel->create(array(
                'curso_evento_id' => (int) $origem['curso_evento_id'],
                'modulo_id' => (int) $origem['modulo_id'],
                'tipo' => (string) $origem['tipo'],
                'titulo' => 'Cópia - ' . (string) $origem['titulo'],
                'descricao_curta' => $origem['descricao_curta'],
                'obrigatorio' => (int) $origem['obrigatorio'],
                'ordem' => (int) $origem['ordem'] + 1,
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
            return array('ok' => false, 'message' => 'Item não encontrado.');
        }

        if ((string) $item['tipo'] === 'avaliacao_textual' && !empty($item['obrigatorio'])) {
            $avaliacao = $this->avaliacaoTextualModel->findByItemId($itemId);
            if (!$avaliacao) {
                return array('ok' => false, 'message' => 'Avaliação textual não configurada para este item.');
            }

            $entrega = $this->avaliacaoEntregaModel->findLatestByContext((int) $avaliacao['id'], $alunoId, $inscricaoId);
            if (!$entrega) {
                return array('ok' => false, 'message' => 'Você precisa enviar a avaliação antes de concluir este item.');
            }

            $statusEntrega = isset($entrega['status']) ? (string) $entrega['status'] : '';
            if (in_array($statusEntrega, array('enviada', 'reenviada', 'devolvida'), true)) {
                return array('ok' => false, 'message' => 'A avaliação ainda está pendente de correção.');
            }

            $notaMinima = array_key_exists('nota_minima', $avaliacao) ? $avaliacao['nota_minima'] : null;
            if ($notaMinima !== null && $notaMinima !== '') {
                if (!array_key_exists('nota', $entrega) || $entrega['nota'] === null || $entrega['nota'] === '') {
                    return array('ok' => false, 'message' => 'A avaliação ainda não possui nota registrada para conclusão.');
                }

                if ((float) $entrega['nota'] < (float) $notaMinima) {
                    return array('ok' => false, 'message' => 'A avaliação foi corrigida, mas não atingiu a nota mínima para conclusão.');
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
            return array('ok' => false, 'message' => 'Inscrição inválida.');
        }

        $inscricao = $this->inscricaoModel->findById($inscricaoId);
        if (!$inscricao) {
            return array('ok' => false, 'message' => 'Inscrição não encontrada.');
        }

        $cursoEventoId = (int) $inscricao['curso_evento_id'];
        $alunoId = !empty($inscricao['usuario_id']) ? (int) $inscricao['usuario_id'] : 0;
        if ($alunoId <= 0) {
            return array('ok' => false, 'message' => 'Aluno inválido na inscrição.');
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
            return array('ok' => false, 'message' => 'Informe o curso do módulo.');
        }

        $titulo = isset($dados['titulo']) ? trim((string) $dados['titulo']) : '';
        if ($titulo === '') {
            return array('ok' => false, 'message' => 'Informe o título do módulo.');
        }

        $status = isset($dados['status']) ? (string) $dados['status'] : 'rascunho';
        if (!in_array($status, self::STATUS_ITEM_VALIDOS, true)) {
            return array('ok' => false, 'message' => 'Status do módulo inválido.');
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
            return array('ok' => false, 'message' => 'Informe o módulo do item.');
        }

        $modulo = $this->moduloModel->findById($moduloId);
        if (!$modulo || (int) $modulo['curso_evento_id'] !== $cursoEventoId) {
            return array('ok' => false, 'message' => 'O módulo informado não pertence ao curso.');
        }

        $tipo = isset($dados['tipo']) ? (string) $dados['tipo'] : '';
        if (!in_array($tipo, self::TIPOS_ITEM_VALIDOS, true)) {
            return array('ok' => false, 'message' => 'Tipo de item inválido.');
        }

        if ($tipo === 'arquivo') {
            $arquivoValidacao = $this->validarArquivoMeta($dados);
            if (empty($arquivoValidacao['ok'])) {
                return $arquivoValidacao;
            }
        }

        $titulo = isset($dados['titulo']) ? trim((string) $dados['titulo']) : '';
        if ($titulo === '') {
            return array('ok' => false, 'message' => 'Informe o título do item.');
        }

        $status = isset($dados['status']) ? (string) $dados['status'] : 'rascunho';
        if (!in_array($status, self::STATUS_ITEM_VALIDOS, true)) {
            return array('ok' => false, 'message' => 'Status do item inválido.');
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

    private function normalizarLogAlunoPayload(array $dados)
    {
        $cursoEventoId = isset($dados['curso_evento_id']) ? (int) $dados['curso_evento_id'] : 0;
        if ($cursoEventoId <= 0) {
            return array('ok' => false, 'message' => 'Curso inválido para log.');
        }

        $alunoId = isset($dados['aluno_id']) ? (int) $dados['aluno_id'] : 0;
        if ($alunoId <= 0) {
            return array('ok' => false, 'message' => 'Aluno inválido para log.');
        }

        $acao = isset($dados['acao']) ? trim((string) $dados['acao']) : '';
        if ($acao === '') {
            return array('ok' => false, 'message' => 'Ação inválida para log.');
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
            return array('ok' => false, 'message' => 'Curso inválido para progresso.');
        }

        $inscricaoId = isset($dados['inscricao_id']) ? (int) $dados['inscricao_id'] : 0;
        if ($inscricaoId <= 0) {
            return array('ok' => false, 'message' => 'Inscrição inválida para progresso.');
        }

        $alunoId = isset($dados['aluno_id']) ? (int) $dados['aluno_id'] : 0;
        if ($alunoId <= 0) {
            return array('ok' => false, 'message' => 'Aluno inválido para progresso.');
        }

        $moduloId = isset($dados['modulo_id']) ? (int) $dados['modulo_id'] : 0;
        $itemId = isset($dados['item_id']) ? (int) $dados['item_id'] : 0;
        if ($moduloId <= 0 || $itemId <= 0) {
            return array('ok' => false, 'message' => 'Módulo/item inválido para progresso.');
        }

        $status = isset($dados['status']) ? (string) $dados['status'] : 'nao_iniciado';
        if (!in_array($status, self::STATUS_PROGRESO_VALIDOS, true)) {
            return array('ok' => false, 'message' => 'Status de progresso inválido.');
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
            // - NÃO referencia arquivo físico nem versões
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

    private function validarArquivoMeta(array $dados)
    {
        $extensao = isset($dados['extensao']) ? strtolower(trim((string) $dados['extensao'])) : '';
        if ($extensao !== '' && !in_array($extensao, self::EXTENSOES_ARQUIVO_VALIDAS, true)) {
            return array('ok' => false, 'message' => 'Extensão de arquivo não permitida.');
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
                return array('ok' => false, 'message' => 'Item nÃ£o encontrado apÃ³s salvar.');
            }

            $detalhes = $this->salvarDetalhesPorTipo($itemId, (string) $item['tipo'], $dados, $arquivoUpload, $usuarioId);
            if (empty($detalhes['ok'])) {
                $pdo->rollBack();
                return $detalhes;
            }

            $pdo->commit();
            return array('ok' => true, 'id' => $itemId);
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('conteudo.item.salvar_falhou', array('message' => $exception->getMessage()));
            throw $exception;
        }
    }

    public function detalharModulo($id, $cursoEventoId)
    {
        $id = (int) $id;
        $cursoEventoId = (int) $cursoEventoId;
        if ($id <= 0 || $cursoEventoId <= 0) {
            return array('ok' => false, 'message' => 'ParÃ¢metros invÃ¡lidos.');
        }

        $modulo = $this->moduloModel->findById($id);
        if (!$modulo || (int) $modulo['curso_evento_id'] !== $cursoEventoId) {
            return array('ok' => false, 'message' => 'MÃ³dulo nÃ£o encontrado.');
        }

        return array('ok' => true, 'modulo' => $modulo);
    }

    public function detalharItem($id, $cursoEventoId)
    {
        $id = (int) $id;
        $cursoEventoId = (int) $cursoEventoId;
        if ($id <= 0 || $cursoEventoId <= 0) {
            return array('ok' => false, 'message' => 'ParÃ¢metros invÃ¡lidos.');
        }

        $item = $this->itemModel->findById($id);
        if (!$item || (int) $item['curso_evento_id'] !== $cursoEventoId) {
            return array('ok' => false, 'message' => 'Item nÃ£o encontrado.');
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
                return array('ok' => false, 'message' => 'Informe uma URL vÃ¡lida para o link.');
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
                return array('ok' => false, 'message' => 'Informe uma URL vÃ¡lida para o vÃ­deo.');
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
                return array('ok' => false, 'message' => 'Informe o enunciado da avaliaÃ§Ã£o textual.');
            }

            $orientacoes = isset($dados['avaliacao_orientacoes']) ? HtmlSanitizer::clean((string) $dados['avaliacao_orientacoes'], 'basic') : null;

            $notaMaxima = isset($dados['avaliacao_nota_maxima']) && $dados['avaliacao_nota_maxima'] !== '' ? (float) $dados['avaliacao_nota_maxima'] : null;
            $notaMinima = isset($dados['avaliacao_nota_minima']) && $dados['avaliacao_nota_minima'] !== '' ? (float) $dados['avaliacao_nota_minima'] : null;

            if ($notaMaxima !== null && $notaMinima !== null && $notaMinima > $notaMaxima) {
                return array('ok' => false, 'message' => 'A nota mÃ­nima nÃ£o pode ser maior que a nota mÃ¡xima.');
            }

            $peso = isset($dados['avaliacao_peso']) && $dados['avaliacao_peso'] !== '' ? (float) $dados['avaliacao_peso'] : 1.00;
            if ($peso <= 0) {
                return array('ok' => false, 'message' => 'O peso da avaliaÃ§Ã£o deve ser maior que zero.');
            }

            $prazo = isset($dados['avaliacao_prazo']) ? trim((string) $dados['avaliacao_prazo']) : '';
            if ($prazo === '') {
                $prazo = null;
            } elseif (!$this->dataHoraValida($prazo)) {
                return array('ok' => false, 'message' => 'Informe um prazo vÃ¡lido (data e hora) para a avaliaÃ§Ã£o.');
            }

            $permiteReenvio = !empty($dados['avaliacao_permite_reenvio']) ? 1 : 0;
            $reenvioLivreAtePrazo = !empty($dados['avaliacao_reenvio_livre_ate_prazo']) ? 1 : 0;

            $this->avaliacaoTextualModel->upsertByItemId($itemId, array(
                'enunciado' => $enunciado,
                'orientacoes' => $orientacoes,
                'nota_maxima' => $notaMaxima,
                'nota_minima' => $notaMinima,
                'peso' => $peso,
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

    private function uploadArquivoConteudo($itemId, array $arquivoUpload)
    {
        $originalName = isset($arquivoUpload['name']) ? (string) $arquivoUpload['name'] : '';
        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));

        if ($extension === '' || !in_array($extension, self::EXTENSOES_ARQUIVO_VALIDAS, true)) {
            throw new \InvalidArgumentException('ExtensÃ£o de arquivo nÃ£o permitida.');
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
