<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Models\Categoria;
use Exception;

class CategoriaService
{
    private $categoriaModel;
    private $auditService;
    private $trashService;

    public function __construct()
    {
        $this->categoriaModel = new Categoria();
        $this->auditService = new AuditService();
        $this->trashService = new TrashService();
    }

    public function listAdmin()
    {
        return array(
            'categorias' => $this->categoriaModel->allWithCounts(),
        );
    }

    public function formData($categoriaId = null)
    {
        return array(
            'categoria' => $categoriaId ? $this->categoriaModel->findById($categoriaId) : null,
            'categorias' => $this->categoriaModel->allForSelect(),
        );
    }

    public function salvar(array $data, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $id = !empty($data['id']) ? (int) $data['id'] : 0;
        $nome = trim((string) (isset($data['nome']) ? $data['nome'] : ''));
        $slug = $this->slugify(isset($data['slug']) && trim((string) $data['slug']) !== '' ? $data['slug'] : $nome);
        $descricao = isset($data['descricao']) ? trim((string) $data['descricao']) : null;
        $parentId = isset($data['parent_id']) && $data['parent_id'] !== '' ? (int) $data['parent_id'] : null;
        $ordem = isset($data['ordem']) ? (int) $data['ordem'] : 0;
        $status = isset($data['status']) && in_array($data['status'], array('ativo', 'inativo'), true) ? $data['status'] : 'ativo';

        $errors = array();
        if ($nome === '') {
            $errors[] = 'Nome da categoria e obrigatorio.';
        }

        if ($slug === '') {
            $errors[] = 'Slug da categoria e obrigatorio.';
        }

        $existente = $this->categoriaModel->findBySlug($slug);
        if ($existente && (int) $existente['id'] !== $id) {
            $errors[] = 'Ja existe uma categoria com este slug.';
        }

        if ($parentId && $parentId === $id) {
            $errors[] = 'Categoria pai nao pode ser a propria categoria.';
        }

        if ($errors) {
            return array('ok' => false, 'errors' => $errors);
        }

        $payload = array(
            'nome' => $nome,
            'slug' => $slug,
            'descricao' => $descricao,
            'parent_id' => $parentId,
            'ordem' => $ordem,
            'status' => $status,
        );

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            if ($id > 0) {
                $anterior = $this->categoriaModel->findById($id);
                $this->categoriaModel->update($payload, $id);
                $acao = 'catalogo.categoria.atualizada';
            } else {
                $anterior = null;
                $id = $this->categoriaModel->create($payload);
                $acao = 'catalogo.categoria.criada';
            }

            $this->auditService->record(
                $acao,
                'categoria',
                $id,
                array('anterior' => $anterior, 'novo' => $payload),
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            Logger::info($acao, array('categoria_id' => $id, 'slug' => $slug));
            $pdo->commit();

            return array('ok' => true, 'id' => $id);
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('catalogo.categoria.falhou', array('message' => $exception->getMessage()));
            throw $exception;
        }
    }

    public function excluir($id, $justificativa, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $categoria = $this->categoriaModel->findById($id);
        if (!$categoria) {
            return array('ok' => false, 'message' => 'Categoria nao encontrada.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $this->trashService->record('categoria', $id, $justificativa, $categoria, $actorUserId, $ipAddress, $userAgent);
            $this->categoriaModel->softDelete($id);

            $this->auditService->record(
                'catalogo.categoria.excluida',
                'categoria',
                $id,
                array('justificativa' => $justificativa),
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            Logger::info('catalogo.categoria.excluida', array('categoria_id' => $id));
            $pdo->commit();

            return array('ok' => true);
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('catalogo.categoria.excluir_falhou', array('message' => $exception->getMessage()));
            throw $exception;
        }
    }

    private function slugify($value)
    {
        $value = trim((string) $value);
        $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/i', '-', $value);
        $value = trim($value, '-');

        return $value;
    }
}
