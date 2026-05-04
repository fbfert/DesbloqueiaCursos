<?php

namespace App\Services;

use App\Core\Database;
use App\Models\FrontendModulo;
use PDO;

class FrontendModuloService
{
    private $model;
    private $auditService;
    private $trashService;

    public function __construct()
    {
        $this->model = new FrontendModulo();
        $this->auditService = new AuditService();
        $this->trashService = new TrashService();
    }

    public function listAdmin()
    {
        return array(
            'modulos' => $this->model->allAdmin(),
            'lixeira_modulos' => $this->listarLixeira(),
        );
    }

    public function formData($id = null)
    {
        return array('modulo' => $id ? $this->model->findById((int) $id) : null);
    }

    public function buscarAtivoPorPosicaoOuCodigo($posicao, $codigo = null)
    {
        return $this->model->findActiveByPositionOrCode($posicao, $codigo);
    }

    public function salvar(array $input, $usuarioId = null, $ipAddress = null, $userAgent = null)
    {
        $id = isset($input['id']) ? (int) $input['id'] : 0;
        $codigo = $this->normalizarSlug(isset($input['codigo']) ? $input['codigo'] : '');
        $payload = array(
            'codigo' => $codigo,
            'nome_admin' => trim((string) (isset($input['nome_admin']) ? $input['nome_admin'] : '')),
            'titulo' => $this->nullableTrim(isset($input['titulo']) ? $input['titulo'] : null),
            'subtitulo' => $this->nullableTrim(isset($input['subtitulo']) ? $input['subtitulo'] : null),
            'conteudo' => $this->nullableTrim(isset($input['conteudo']) ? $input['conteudo'] : null),
            'posicao' => $this->normalizarSlug(isset($input['posicao']) ? $input['posicao'] : ''),
            'tipo' => $this->normalizarSlug(isset($input['tipo']) ? $input['tipo'] : 'bloco_texto'),
            'ativo' => isset($input['ativo']) ? 1 : 0,
            'ordem' => isset($input['ordem']) ? (int) $input['ordem'] : 0,
            'permite_html' => isset($input['permite_html']) ? 1 : 0,
            'observacoes_admin' => $this->nullableTrim(isset($input['observacoes_admin']) ? $input['observacoes_admin'] : null),
            'criado_por' => $usuarioId ? (int) $usuarioId : null,
            'atualizado_por' => $usuarioId ? (int) $usuarioId : null,
        );

        $errors = array();
        if ($payload['nome_admin'] === '') {
            $errors[] = 'Informe o nome administrativo do módulo.';
        }
        if ($payload['codigo'] === '') {
            $errors[] = 'Informe o código do módulo.';
        }
        if ($payload['posicao'] === '') {
            $errors[] = 'Informe a posição do módulo.';
        }
        if ($payload['tipo'] === '') {
            $errors[] = 'Informe o tipo do módulo.';
        }
        if ($payload['permite_html'] === 1) {
            $errors[] = 'HTML livre não está habilitado por segurança.';
            $payload['permite_html'] = 0;
        }
        if ($this->model->findByCode($payload['codigo'], $id > 0 ? $id : null)) {
            $errors[] = 'Já existe um módulo com este código.';
        }
        if ($errors) {
            return array('ok' => false, 'errors' => $errors);
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        if ($id > 0) {
            $anterior = $this->model->findById($id);
            if (!$anterior) {
                $pdo->rollBack();
                return array('ok' => false, 'errors' => array('Módulo não encontrado.'));
            }
            $this->model->update($id, $payload);
            $acao = 'frontend_modulo.atualizado';
        } else {
            $anterior = null;
            $id = $this->model->create($payload);
            $acao = 'frontend_modulo.criado';
        }

        $this->auditService->record($acao, 'frontend_modulo', $id, array('antes' => $anterior, 'depois' => $payload), $usuarioId, $ipAddress, $userAgent);
        $pdo->commit();
        return array('ok' => true, 'id' => $id);
    }

    public function excluir($id, $justificativa, $usuarioId = null, $ipAddress = null, $userAgent = null)
    {
        $modulo = $this->model->findById((int) $id);
        if (!$modulo) {
            return array('ok' => false, 'message' => 'Módulo não encontrado.');
        }

        $justificativa = trim((string) $justificativa);
        if ($justificativa === '') {
            return array('ok' => false, 'message' => 'Informe a justificativa para excluir o módulo.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        $this->trashService->record('frontend_modulo', $id, $justificativa, $modulo, $usuarioId, $ipAddress, $userAgent);
        $this->model->softDelete($id, $usuarioId, $justificativa);
        $this->auditService->record('frontend_modulo.excluido', 'frontend_modulo', $id, array('justificativa' => $justificativa, 'antes' => $modulo), $usuarioId, $ipAddress, $userAgent);
        $pdo->commit();
        return array('ok' => true);
    }

    private function normalizarSlug($value)
    {
        $value = trim((string) $value);
        $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
        $value = preg_replace('/[^a-z0-9_\-]+/', '_', $value);
        return trim((string) $value, '_');
    }

    private function nullableTrim($value)
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function listarLixeira()
    {
        $stmt = Database::connection()->prepare(
            'SELECT l.id, l.entidade_id, l.justificativa, l.snapshot_dados, l.created_at, u.nome AS excluido_por_nome
             FROM lixeira l
             LEFT JOIN usuarios u ON u.id = l.excluido_por_usuario_id
             WHERE l.entidade_tipo = "frontend_modulo"
               AND l.restaurado_em IS NULL
             ORDER BY l.id DESC'
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
