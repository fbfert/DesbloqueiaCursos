<?php

namespace App\Services;

use App\Core\Database;
use App\Models\FrontendMenu;
use App\Models\FrontendMenuItem;
use PDO;

class FrontendMenuService
{
    private $menuModel;
    private $itemModel;
    private $auditService;
    private $trashService;

    public function __construct()
    {
        $this->menuModel = new FrontendMenu();
        $this->itemModel = new FrontendMenuItem();
        $this->auditService = new AuditService();
        $this->trashService = new TrashService();
    }

    public function listAdmin()
    {
        return array(
            'menus' => $this->menuModel->allAdmin(),
            'lixeira_menus' => $this->listarLixeira('frontend_menu'),
            'lixeira_itens' => $this->listarLixeira('frontend_menu_item'),
        );
    }

    public function formData($id = null)
    {
        return array('menu' => $id ? $this->menuModel->findById((int) $id) : null);
    }

    public function itensData($menuId)
    {
        $menu = $this->menuModel->findById((int) $menuId);
        return array(
            'menu' => $menu,
            'itens' => $menu ? $this->itemModel->allByMenu((int) $menuId) : array(),
        );
    }

    public function itemFormData($menuId, $itemId = null)
    {
        $menu = $this->menuModel->findById((int) $menuId);
        $item = $itemId ? $this->itemModel->findById((int) $itemId) : null;
        if ($item && (int) $item['menu_id'] !== (int) $menuId) {
            $item = null;
        }
        return array('menu' => $menu, 'item' => $item);
    }

    public function buscarMenuAtivoPorPosicao($posicao, $codigo = null)
    {
        return $this->menuModel->findActiveByPositionOrCode($posicao, $codigo);
    }

    public function listarItensAtivos($menuId)
    {
        return $this->itemModel->listActiveByMenu((int) $menuId);
    }

    public function salvarMenu(array $input, $usuarioId = null, $ipAddress = null, $userAgent = null)
    {
        $id = isset($input['id']) ? (int) $input['id'] : 0;
        $payload = array(
            'codigo' => $this->normalizarSlug(isset($input['codigo']) ? $input['codigo'] : ''),
            'nome_admin' => trim((string) (isset($input['nome_admin']) ? $input['nome_admin'] : '')),
            'posicao' => $this->normalizarSlug(isset($input['posicao']) ? $input['posicao'] : ''),
            'ativo' => isset($input['ativo']) ? 1 : 0,
            'ordem' => isset($input['ordem']) ? (int) $input['ordem'] : 0,
            'observacoes_admin' => $this->nullableTrim(isset($input['observacoes_admin']) ? $input['observacoes_admin'] : null),
            'criado_por' => $usuarioId ? (int) $usuarioId : null,
            'atualizado_por' => $usuarioId ? (int) $usuarioId : null,
        );

        $errors = array();
        if ($payload['codigo'] === '') {
            $errors[] = 'Informe o código do menu.';
        }
        if ($payload['nome_admin'] === '') {
            $errors[] = 'Informe o nome administrativo do menu.';
        }
        if ($payload['posicao'] === '') {
            $errors[] = 'Informe a posição do menu.';
        }
        if ($this->menuModel->findByCode($payload['codigo'], $id > 0 ? $id : null)) {
            $errors[] = 'Já existe um menu com este código.';
        }
        if ($errors) {
            return array('ok' => false, 'errors' => $errors);
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        if ($id > 0) {
            $anterior = $this->menuModel->findById($id);
            if (!$anterior) {
                $pdo->rollBack();
                return array('ok' => false, 'errors' => array('Menu não encontrado.'));
            }
            $this->menuModel->update($id, $payload);
            $acao = 'frontend_menu.atualizado';
        } else {
            $anterior = null;
            $id = $this->menuModel->create($payload);
            $acao = 'frontend_menu.criado';
        }
        $this->auditService->record($acao, 'frontend_menu', $id, array('antes' => $anterior, 'depois' => $payload), $usuarioId, $ipAddress, $userAgent);
        $pdo->commit();
        return array('ok' => true, 'id' => $id);
    }

    public function excluirMenu($id, $justificativa, $usuarioId = null, $ipAddress = null, $userAgent = null)
    {
        $menu = $this->menuModel->findById((int) $id);
        if (!$menu) {
            return array('ok' => false, 'message' => 'Menu não encontrado.');
        }
        $justificativa = trim((string) $justificativa);
        if ($justificativa === '') {
            return array('ok' => false, 'message' => 'Informe a justificativa para excluir o menu.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        $this->trashService->record('frontend_menu', $id, $justificativa, $menu, $usuarioId, $ipAddress, $userAgent);
        $this->menuModel->softDelete($id, $usuarioId, $justificativa);
        foreach ($this->itemModel->allByMenu((int) $id) as $item) {
            $this->trashService->record('frontend_menu_item', (int) $item['id'], $justificativa, $item, $usuarioId, $ipAddress, $userAgent);
            $this->itemModel->softDelete((int) $item['id'], $usuarioId, $justificativa);
        }
        $this->auditService->record('frontend_menu.excluido', 'frontend_menu', $id, array('justificativa' => $justificativa, 'antes' => $menu), $usuarioId, $ipAddress, $userAgent);
        $pdo->commit();
        return array('ok' => true);
    }

    public function salvarItem($menuId, array $input, $usuarioId = null, $ipAddress = null, $userAgent = null)
    {
        $menu = $this->menuModel->findById((int) $menuId);
        if (!$menu) {
            return array('ok' => false, 'errors' => array('Menu não encontrado.'));
        }

        $id = isset($input['id']) ? (int) $input['id'] : 0;
        $target = (string) (isset($input['target']) ? $input['target'] : '_self');
        $rel = $this->nullableTrim(isset($input['rel']) ? $input['rel'] : null);
        if ($target === '_blank' && ($rel === null || trim($rel) === '')) {
            $rel = 'noopener noreferrer';
        }

        $payload = array(
            'menu_id' => (int) $menuId,
            'rotulo' => trim((string) (isset($input['rotulo']) ? $input['rotulo'] : '')),
            'url' => trim((string) (isset($input['url']) ? $input['url'] : '')),
            'target' => $target,
            'rel' => $rel,
            'ativo' => isset($input['ativo']) ? 1 : 0,
            'ordem' => isset($input['ordem']) ? (int) $input['ordem'] : 0,
            'criado_por' => $usuarioId ? (int) $usuarioId : null,
            'atualizado_por' => $usuarioId ? (int) $usuarioId : null,
        );

        $errors = $this->validarItem($payload);
        if ($errors) {
            return array('ok' => false, 'errors' => $errors);
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        if ($id > 0) {
            $anterior = $this->itemModel->findById($id);
            if (!$anterior || (int) $anterior['menu_id'] !== (int) $menuId) {
                $pdo->rollBack();
                return array('ok' => false, 'errors' => array('Item não encontrado.'));
            }
            $this->itemModel->update($id, $payload);
            $acao = 'frontend_menu_item.atualizado';
        } else {
            $anterior = null;
            $id = $this->itemModel->create($payload);
            $acao = 'frontend_menu_item.criado';
        }
        $this->auditService->record($acao, 'frontend_menu_item', $id, array('antes' => $anterior, 'depois' => $payload), $usuarioId, $ipAddress, $userAgent);
        $pdo->commit();
        return array('ok' => true, 'id' => $id);
    }

    public function excluirItem($menuId, $itemId, $justificativa, $usuarioId = null, $ipAddress = null, $userAgent = null)
    {
        $item = $this->itemModel->findById((int) $itemId);
        if (!$item || (int) $item['menu_id'] !== (int) $menuId) {
            return array('ok' => false, 'message' => 'Item não encontrado.');
        }

        $justificativa = trim((string) $justificativa);
        if ($justificativa === '') {
            return array('ok' => false, 'message' => 'Informe a justificativa para excluir o item.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        $this->trashService->record('frontend_menu_item', $itemId, $justificativa, $item, $usuarioId, $ipAddress, $userAgent);
        $this->itemModel->softDelete($itemId, $usuarioId, $justificativa);
        $this->auditService->record('frontend_menu_item.excluido', 'frontend_menu_item', $itemId, array('justificativa' => $justificativa, 'antes' => $item), $usuarioId, $ipAddress, $userAgent);
        $pdo->commit();
        return array('ok' => true);
    }

    public function reordenarItens($menuId, array $ordens, $usuarioId = null, $ipAddress = null, $userAgent = null)
    {
        $itens = $this->itemModel->allByMenu((int) $menuId);
        $porId = array();
        foreach ($itens as $item) {
            $porId[(int) $item['id']] = $item;
        }
        if (empty($porId)) {
            return array('ok' => true);
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        $antes = array();
        $depois = array();
        foreach ($ordens as $itemId => $ordem) {
            $itemId = (int) $itemId;
            if (!isset($porId[$itemId])) {
                continue;
            }
            $antes[$itemId] = (int) $porId[$itemId]['ordem'];
            $novaOrdem = (int) $ordem;
            $depois[$itemId] = $novaOrdem;
            $this->itemModel->update($itemId, array(
                'rotulo' => $porId[$itemId]['rotulo'],
                'url' => $porId[$itemId]['url'],
                'target' => $porId[$itemId]['target'],
                'rel' => $porId[$itemId]['rel'],
                'ativo' => (int) $porId[$itemId]['ativo'],
                'ordem' => $novaOrdem,
                'atualizado_por' => $usuarioId ? (int) $usuarioId : null,
            ));
        }

        $this->auditService->record('frontend_menu_item.reordenado', 'frontend_menu', $menuId, array('antes' => $antes, 'depois' => $depois), $usuarioId, $ipAddress, $userAgent);
        $pdo->commit();
        return array('ok' => true);
    }

    private function validarItem(array $payload)
    {
        $errors = array();
        if ($payload['rotulo'] === '') {
            $errors[] = 'Informe o rótulo do item.';
        }
        if ($payload['url'] === '') {
            $errors[] = 'Informe a URL do item.';
        } elseif (!$this->urlValida($payload['url'])) {
            $errors[] = 'Informe uma URL válida (interna iniciando com / ou externa iniciando com http:// ou https://).';
        }
        if (!in_array($payload['target'], array('_self', '_blank'), true)) {
            $errors[] = 'O target deve ser _self ou _blank.';
        }
        return $errors;
    }

    private function urlValida($url)
    {
        $url = trim((string) $url);
        if ($url === '') {
            return false;
        }
        if (strpos($url, '/') === 0) {
            return true;
        }
        return strpos($url, 'http://') === 0 || strpos($url, 'https://') === 0;
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

    private function listarLixeira($entidadeTipo)
    {
        $stmt = Database::connection()->prepare(
            'SELECT l.id, l.entidade_id, l.justificativa, l.snapshot_dados, l.created_at, u.nome AS excluido_por_nome
             FROM lixeira l
             LEFT JOIN usuarios u ON u.id = l.excluido_por_usuario_id
             WHERE l.entidade_tipo = :tipo
               AND l.restaurado_em IS NULL
             ORDER BY l.id DESC'
        );
        $stmt->execute(array('tipo' => $entidadeTipo));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
