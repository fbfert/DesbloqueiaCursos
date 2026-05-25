<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Models\FrontendMenu;
use App\Models\FrontendMenuExibicaoRegra;
use App\Models\FrontendMenuItem;
use PDO;

class FrontendMenuService
{
    private $menuModel;
    private $regraModel;
    private $itemModel;
    private $auditService;
    private $trashService;

    public function __construct()
    {
        $this->menuModel = new FrontendMenu();
        $this->regraModel = new FrontendMenuExibicaoRegra();
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
        $menu = $id ? $this->menuModel->findById((int) $id) : null;
        $regrasExibicao = array();

        if ($menu && !empty($menu['id'])) {
            try {
                $regrasExibicao = $this->regraModel->listarPorMenu((int) $menu['id']);
            } catch (\Throwable $throwable) {
                $this->registrarProblemaRegra('Não foi possível carregar as regras do menu.', array(
                    'menu_id' => (int) $menu['id'],
                    'erro' => $throwable->getMessage(),
                ));
            }
        }

        return array(
            'menu' => $menu,
            'regras_exibicao' => $regrasExibicao,
        );
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

    public function buscarMenuAtivoPorPosicao($posicao, $codigo = null, array $contexto = array())
    {
        $menu = $this->menuModel->findActiveByPositionOrCode($posicao, $codigo);
        if (!$menu) {
            return null;
        }

        $menusFiltrados = $this->filtrarMenusPorContexto(array($menu), $contexto);
        return !empty($menusFiltrados) ? $menusFiltrados[0] : null;
    }

    public function listarItensAtivos($menuId)
    {
        return $this->itemModel->listActiveByMenu((int) $menuId);
    }

    public function menuTopoPublico($isAuthenticated, array $contexto = array())
    {
        $fallbackPublico = array(
            array('rotulo' => 'Início', 'url' => '/', 'target' => '_self', 'rel' => null),
            array('rotulo' => 'Cursos', 'url' => '/cursos', 'target' => '_self', 'rel' => null),
            array('rotulo' => 'Como funciona', 'url' => '/como-funciona', 'target' => '_self', 'rel' => null),
            array('rotulo' => 'Sobre', 'url' => '/sobre', 'target' => '_self', 'rel' => null),
            array('rotulo' => 'Contato', 'url' => '/contato', 'target' => '_self', 'rel' => null),
            array('rotulo' => 'Entrar', 'url' => '/login', 'target' => '_self', 'rel' => null),
        );
        $fallbackLogado = array(
            array('rotulo' => 'Minha Página', 'url' => '/minha-pagina', 'target' => '_self', 'rel' => null),
            array('rotulo' => 'Meus Cursos', 'url' => '/area-curso', 'target' => '_self', 'rel' => null),
            array('rotulo' => 'Avisos', 'url' => '/meus-cursos#avisos', 'target' => '_self', 'rel' => null),
            array('rotulo' => 'Certificados', 'url' => '/certificados', 'target' => '_self', 'rel' => null),
            array('rotulo' => 'Cursos', 'url' => '/cursos', 'target' => '_self', 'rel' => null),
            array('rotulo' => 'Sair', 'url' => '/logout', 'target' => '_self', 'rel' => null),
        );

        $codigo = $isAuthenticated ? 'menu_topo_logado' : 'menu_topo_publico';
        $posicao = $isAuthenticated ? 'topo_logado' : 'topo_publico';
        $fallback = $isAuthenticated ? $fallbackLogado : $fallbackPublico;
        $contexto = $this->obterContextoExibicao(array_merge($contexto, array(
            'auth_state' => $isAuthenticated ? 'logged' : 'guest',
        )));

        try {
            $menu = $this->menuModel->findActiveByPositionOrCode($posicao, $codigo);
            if (!$menu) {
                $menu = $this->menuModel->findActiveByPositionOrCode($posicao, null);
            }
            if (!$menu) {
                return array(
                    'menu' => null,
                    'itens' => $fallback,
                    'from_fallback' => true,
                );
            }

            $menusFiltrados = $this->filtrarMenusPorContexto(array($menu), $contexto);
            if (empty($menusFiltrados)) {
                return array(
                    'menu' => null,
                    'itens' => $fallback,
                    'from_fallback' => true,
                );
            }

            $itens = $this->sanitizeItens($this->itemModel->listActiveByMenu((int) $menu['id']));
            if ($isAuthenticated) {
                $itens = $this->appendAvisosMenuItem($itens);
            }
            if (!$itens) {
                return array(
                    'menu' => $menu,
                    'itens' => $fallback,
                    'from_fallback' => true,
                );
            }

            return array(
                'menu' => $menusFiltrados[0],
                'itens' => $itens,
                'from_fallback' => false,
            );
        } catch (\Throwable $exception) {
            Logger::error('frontend_menu.topo.carregamento_falhou', array(
                'codigo' => $codigo,
                'posicao' => $posicao,
                'message' => $exception->getMessage(),
            ));
            return array('menu' => null, 'itens' => $fallback, 'from_fallback' => true);
        }
    }

    public function obterContextoExibicao(array $contexto = array())
    {
        $requestPath = isset($contexto['route']) ? (string) $contexto['route'] : parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $requestPath = $this->normalizarCaminho($requestPath ?: '/');
        $pageKey = isset($contexto['page_key']) ? $this->normalizarChaveContexto($contexto['page_key']) : null;
        $area = isset($contexto['area']) ? $this->normalizarChaveContexto($contexto['area']) : $this->detectarAreaAtual($requestPath);
        $authState = isset($contexto['auth_state']) ? $this->normalizarChaveContexto($contexto['auth_state']) : null;

        if ($authState === null || $authState === '') {
            $authState = isset($_SESSION['usuario_id']) ? 'logged' : 'guest';
        }

        return array(
            'route' => $requestPath,
            'page_key' => $pageKey,
            'area' => $area,
            'auth_state' => $authState,
        );
    }

    public function filtrarMenusPorContexto(array $menus, array $contexto = array())
    {
        if (empty($menus)) {
            return array();
        }

        try {
            $contexto = $this->obterContextoExibicao($contexto);
            $menuIds = array();
            foreach ($menus as $menu) {
                if (is_array($menu) && !empty($menu['id'])) {
                    $menuIds[] = (int) $menu['id'];
                }
            }

            $regrasPorMenu = $this->regraModel->buscarRegrasAtivasPorMenus($menuIds);
            $menusFiltrados = array();

            foreach ($menus as $menu) {
                if (!is_array($menu) || empty($menu['id'])) {
                    $menusFiltrados[] = $menu;
                    continue;
                }

                $regras = isset($regrasPorMenu[(int) $menu['id']]) ? $regrasPorMenu[(int) $menu['id']] : array();
                if ($this->menuVisivelNoContexto($menu, $regras, $contexto)) {
                    $menusFiltrados[] = $menu;
                }
            }

            return $menusFiltrados;
        } catch (\Throwable $throwable) {
            $this->registrarProblemaRegra('Não foi possível aplicar as regras de exibição dos menus.', array(
                'erro' => $throwable->getMessage(),
            ));
        }

        return $menus;
    }

    private function appendAvisosMenuItem(array $itens)
    {
        foreach ($itens as $item) {
            $rotulo = function_exists('mb_strtolower') ? mb_strtolower(trim((string) ($item['rotulo'] ?? '')), 'UTF-8') : strtolower(trim((string) ($item['rotulo'] ?? '')));
            if ($rotulo === 'avisos') {
                return $itens;
            }
        }

        $avisosItem = array(
            'rotulo' => 'Avisos',
            'url' => '/meus-cursos#avisos',
            'target' => '_self',
            'rel' => null,
        );

        $resultado = array();
        $inserido = false;
        foreach ($itens as $item) {
            $resultado[] = $item;
            $rotulo = function_exists('mb_strtolower') ? mb_strtolower(trim((string) ($item['rotulo'] ?? '')), 'UTF-8') : strtolower(trim((string) ($item['rotulo'] ?? '')));
            if (!$inserido && $rotulo === 'meus cursos') {
                $resultado[] = $avisosItem;
                $inserido = true;
            }
        }

        if (!$inserido) {
            $resultado[] = $avisosItem;
        }

        return $resultado;
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
        $regrasExibicao = $this->extrairRegrasExibicao(isset($input['regras_exibicao']) && is_array($input['regras_exibicao']) ? $input['regras_exibicao'] : array());
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
        try {
            $this->regraModel->salvarRegrasDoMenu($id, $regrasExibicao);
        } catch (\Throwable $throwable) {
            $this->registrarProblemaRegra('Não foi possível salvar as regras de exibição do menu.', array(
                'menu_id' => $id,
                'erro' => $throwable->getMessage(),
            ));
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
        try {
            $this->regraModel->excluirPorMenu($id);
        } catch (\Throwable $throwable) {
            $this->registrarProblemaRegra('Não foi possível remover as regras de exibição do menu.', array(
                'menu_id' => (int) $id,
                'erro' => $throwable->getMessage(),
            ));
        }
        $this->menuModel->softDelete($id, $usuarioId, $justificativa);
        foreach ($this->itemModel->allByMenu((int) $id) as $item) {
            $this->trashService->record('frontend_menu_item', (int) $item['id'], $justificativa, $item, $usuarioId, $ipAddress, $userAgent);
            $this->itemModel->softDelete((int) $item['id'], $usuarioId, $justificativa);
        }
        $this->auditService->record('frontend_menu.excluido', 'frontend_menu', $id, array('justificativa' => $justificativa, 'antes' => $menu), $usuarioId, $ipAddress, $userAgent);
        $pdo->commit();
        return array('ok' => true);
    }

    public function duplicarMenu(array $input, $usuarioId = null, $ipAddress = null, $userAgent = null)
    {
        $id = isset($input['id']) ? (int) $input['id'] : 0;
        if ($id <= 0) {
            return array('ok' => false, 'errors' => array('Menu não encontrado.'));
        }

        $menu = $this->menuModel->findById($id);
        if (!$menu) {
            return array('ok' => false, 'errors' => array('Menu não encontrado.'));
        }

        $novoCodigo = $this->codigoDaCopia(isset($input['codigo']) && trim((string) $input['codigo']) !== '' ? $input['codigo'] : $menu['codigo']);
        $novoNome = $this->nomeDaCopia(isset($input['nome_admin']) && trim((string) $input['nome_admin']) !== '' ? $input['nome_admin'] : $menu['nome_admin']);
        $novoPosicao = $this->posicaoDaCopia(isset($input['posicao']) && trim((string) $input['posicao']) !== '' ? $input['posicao'] : $menu['posicao']);

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $menuId = $this->menuModel->create(array(
                'codigo' => $novoCodigo,
                'nome_admin' => $novoNome,
                'posicao' => $novoPosicao,
                'ativo' => 0,
                'ordem' => isset($input['ordem']) ? (int) $input['ordem'] : (int) $menu['ordem'],
                'observacoes_admin' => $this->nullableTrim(isset($input['observacoes_admin']) ? $input['observacoes_admin'] : $menu['observacoes_admin']),
                'criado_por' => $usuarioId ? (int) $usuarioId : null,
                'atualizado_por' => $usuarioId ? (int) $usuarioId : null,
            ));

            $regrasOriginais = $this->regraModel->listarPorMenu($id);
            $regrasCopia = array();
            foreach ($regrasOriginais as $regra) {
                if (!is_array($regra)) {
                    continue;
                }
                $regrasCopia[] = array(
                    'tipo_regra' => isset($regra['tipo_regra']) ? $regra['tipo_regra'] : 'include',
                    'alvo_tipo' => isset($regra['alvo_tipo']) ? $regra['alvo_tipo'] : 'route',
                    'alvo_valor' => isset($regra['alvo_valor']) ? $regra['alvo_valor'] : '',
                    'ativo' => isset($regra['ativo']) ? (int) $regra['ativo'] : 1,
                    'ordem' => isset($regra['ordem']) ? (int) $regra['ordem'] : 0,
                );
            }
            $this->regraModel->salvarRegrasDoMenu($menuId, $regrasCopia);

            $itensCopiados = 0;
            foreach ($this->itemModel->allByMenu($id) as $item) {
                $this->itemModel->create(array(
                    'menu_id' => $menuId,
                    'rotulo' => $this->nomeDaCopia(isset($item['rotulo']) ? $item['rotulo'] : ''),
                    'url' => isset($item['url']) ? $item['url'] : '',
                    'target' => isset($item['target']) ? $item['target'] : '_self',
                    'rel' => isset($item['rel']) ? $item['rel'] : null,
                    'ativo' => 0,
                    'ordem' => isset($item['ordem']) ? (int) $item['ordem'] : 0,
                    'criado_por' => $usuarioId ? (int) $usuarioId : null,
                    'atualizado_por' => $usuarioId ? (int) $usuarioId : null,
                ));
                $itensCopiados++;
            }

            $this->auditService->record(
                'frontend_menu.copiado',
                'frontend_menu',
                $menuId,
                array('origem_id' => $id, 'itens_copiados' => $itensCopiados),
                $usuarioId,
                $ipAddress,
                $userAgent
            );

            $pdo->commit();

            return array('ok' => true, 'id' => $menuId);
        } catch (\Throwable $throwable) {
            $pdo->rollBack();
            Logger::error('frontend_menu.copiar_falhou', array(
                'menu_id' => $id,
                'message' => $throwable->getMessage(),
            ));

            return array('ok' => false, 'errors' => array('Não foi possível criar a cópia do menu.'));
        }
    }

    public function duplicarItem(array $input, $usuarioId = null, $ipAddress = null, $userAgent = null)
    {
        $menuId = isset($input['menu_id']) ? (int) $input['menu_id'] : 0;
        $id = isset($input['id']) ? (int) $input['id'] : 0;

        $menu = $this->menuModel->findById($menuId);
        if (!$menu) {
            return array('ok' => false, 'errors' => array('Menu não encontrado.'));
        }

        if ($id <= 0) {
            return array('ok' => false, 'errors' => array('Item não encontrado.'));
        }

        $item = $this->itemModel->findById($id);
        if (!$item || (int) $item['menu_id'] !== $menuId) {
            return array('ok' => false, 'errors' => array('Item não encontrado.'));
        }

        $payload = array(
            'menu_id' => $menuId,
            'rotulo' => $this->nomeDaCopia(isset($input['rotulo']) && trim((string) $input['rotulo']) !== '' ? $input['rotulo'] : $item['rotulo']),
            'url' => isset($input['url']) && trim((string) $input['url']) !== '' ? trim((string) $input['url']) : $item['url'],
            'target' => isset($input['target']) && in_array($input['target'], array('_self', '_blank'), true) ? $input['target'] : $item['target'],
            'rel' => $this->nullableTrim(isset($input['rel']) ? $input['rel'] : $item['rel']),
            'ativo' => 0,
            'ordem' => isset($input['ordem']) ? (int) $input['ordem'] : (int) $item['ordem'],
            'criado_por' => $usuarioId ? (int) $usuarioId : null,
            'atualizado_por' => $usuarioId ? (int) $usuarioId : null,
        );

        if ($payload['target'] === '_blank' && ($payload['rel'] === null || trim((string) $payload['rel']) === '')) {
            $payload['rel'] = 'noopener noreferrer';
        }

        $errors = $this->validarItem($payload);
        if ($errors) {
            return array('ok' => false, 'errors' => $errors);
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $novoId = $this->itemModel->create($payload);
            $this->auditService->record(
                'frontend_menu_item.copiado',
                'frontend_menu_item',
                $novoId,
                array('origem_id' => $id, 'menu_id' => $menuId),
                $usuarioId,
                $ipAddress,
                $userAgent
            );
            $pdo->commit();
            return array('ok' => true, 'id' => $novoId);
        } catch (\Throwable $throwable) {
            $pdo->rollBack();
            Logger::error('frontend_menu_item.copiar_falhou', array(
                'item_id' => $id,
                'message' => $throwable->getMessage(),
            ));
            return array('ok' => false, 'errors' => array('Não foi possível criar a cópia do item.'));
        }
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
        if (preg_match('/<[^>]*>/', $payload['rotulo'])) {
            $errors[] = 'O rótulo do item não pode conter HTML.';
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
        if ($this->hasBlockedScheme($url)) {
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

    private function nomeDaCopia($valor)
    {
        $valor = trim((string) $valor);
        $valor = preg_replace('/^c[oó]pia de\s+/iu', '', $valor);
        return 'Cópia de ' . $valor;
    }

    private function codigoDaCopia($valor)
    {
        $base = $this->normalizarSlug($valor);
        $base = preg_replace('/-copia(?:-\d+)?$/', '', $base);
        $base = trim((string) $base, '_-');
        if ($base === '') {
            $base = 'menu';
        }
        $codigo = $base . '-copia';
        $sufixo = 2;
        while ($this->menuModel->findByCode($codigo)) {
            $codigo = $base . '-copia-' . $sufixo;
            $sufixo++;
        }
        return $codigo;
    }

    private function posicaoDaCopia($valor)
    {
        $valor = trim((string) $valor);
        $valor = preg_replace('/-copia(?:-\d+)?$/', '', $valor);
        $valor = trim($valor);
        if ($valor === '') {
            $valor = 'menu';
        }

        $posicao = $valor . '-copia';
        $sufixo = 2;
        while ($this->menuModel->findActiveByPositionOrCode($posicao, null)) {
            $posicao = $valor . '-copia-' . $sufixo;
            $sufixo++;
        }

        return $posicao;
    }

    private function nullableTrim($value)
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function extrairRegrasExibicao(array $entrada)
    {
        $tiposPermitidos = array('include', 'exclude');
        $alvosPermitidos = array('route', 'page_key', 'area', 'auth_state');
        $tipos = isset($entrada['tipo_regra']) && is_array($entrada['tipo_regra']) ? $entrada['tipo_regra'] : array();
        $alvos = isset($entrada['alvo_tipo']) && is_array($entrada['alvo_tipo']) ? $entrada['alvo_tipo'] : array();
        $valores = isset($entrada['alvo_valor']) && is_array($entrada['alvo_valor']) ? $entrada['alvo_valor'] : array();
        $ativos = isset($entrada['ativo']) && is_array($entrada['ativo']) ? $entrada['ativo'] : array();
        $ordens = isset($entrada['ordem']) && is_array($entrada['ordem']) ? $entrada['ordem'] : array();

        $quantidade = max(count($tipos), count($alvos), count($valores), count($ativos), count($ordens));
        $regras = array();

        for ($i = 0; $i < $quantidade; $i++) {
            $tipo = $this->normalizarChaveContexto(isset($tipos[$i]) ? $tipos[$i] : '');
            $alvoTipo = $this->normalizarChaveContexto(isset($alvos[$i]) ? $alvos[$i] : '');
            $alvoValor = trim((string) (isset($valores[$i]) ? $valores[$i] : ''));
            $ativo = isset($ativos[$i]) ? (int) $ativos[$i] : 0;
            $ordem = isset($ordens[$i]) ? (int) $ordens[$i] : 0;

            if ($tipo === '' && $alvoTipo === '' && $alvoValor === '') {
                continue;
            }

            if (!in_array($tipo, $tiposPermitidos, true)) {
                $this->registrarProblemaRegra('Regra de exibição ignorada por tipo inválido.', array('indice' => $i, 'tipo_regra' => $tipo));
                continue;
            }

            if (!in_array($alvoTipo, $alvosPermitidos, true)) {
                $this->registrarProblemaRegra('Regra de exibição ignorada por alvo inválido.', array('indice' => $i, 'alvo_tipo' => $alvoTipo));
                continue;
            }

            $alvoValor = $this->normalizarValorRegra($alvoValor);
            if ($alvoValor === '') {
                $this->registrarProblemaRegra('Regra de exibição ignorada por valor vazio.', array('indice' => $i, 'alvo_tipo' => $alvoTipo));
                continue;
            }

            $regras[] = array(
                'tipo_regra' => $tipo,
                'alvo_tipo' => $alvoTipo,
                'alvo_valor' => $alvoValor,
                'ativo' => $ativo === 1 ? 1 : 0,
                'ordem' => $ordem,
            );
        }

        return $regras;
    }

    private function menuVisivelNoContexto(array $menu, array $regras, array $contexto)
    {
        if (empty($regras)) {
            return true;
        }

        $regrasValidas = array();
        foreach ($regras as $regra) {
            if (!is_array($regra)) {
                continue;
            }

            $tipo = isset($regra['tipo_regra']) ? $this->normalizarChaveContexto($regra['tipo_regra']) : '';
            $alvoTipo = isset($regra['alvo_tipo']) ? $this->normalizarChaveContexto($regra['alvo_tipo']) : '';
            $alvoValor = isset($regra['alvo_valor']) ? trim((string) $regra['alvo_valor']) : '';

            if ($tipo === '' || $alvoTipo === '' || $alvoValor === '') {
                $this->registrarProblemaRegra('Regra de exibição ignorada durante o filtro.', array(
                    'menu_id' => isset($menu['id']) ? (int) $menu['id'] : null,
                ));
                continue;
            }

            $regrasValidas[] = array(
                'tipo_regra' => $tipo,
                'alvo_tipo' => $alvoTipo,
                'alvo_valor' => $alvoValor,
            );
        }

        if (empty($regrasValidas)) {
            return true;
        }

        $temInclude = false;
        $matchInclude = false;

        foreach ($regrasValidas as $regra) {
            $combinou = $this->regraCombinaComContexto($regra, $contexto);
            if ($regra['tipo_regra'] === 'exclude' && $combinou) {
                return false;
            }
            if ($regra['tipo_regra'] === 'include') {
                $temInclude = true;
                if ($combinou) {
                    $matchInclude = true;
                }
            }
        }

        if ($temInclude) {
            return $matchInclude;
        }

        return true;
    }

    private function regraCombinaComContexto(array $regra, array $contexto)
    {
        $alvoTipo = isset($regra['alvo_tipo']) ? $this->normalizarChaveContexto($regra['alvo_tipo']) : '';
        $alvoValor = isset($regra['alvo_valor']) ? trim((string) $regra['alvo_valor']) : '';
        $valorContexto = isset($contexto[$alvoTipo]) ? $contexto[$alvoTipo] : null;

        if ($alvoTipo === '' || $alvoValor === '' || $valorContexto === null) {
            return false;
        }

        $valorContexto = $this->normalizarValorContexto($alvoTipo, $valorContexto);
        $alvoValor = $this->normalizarValorContexto($alvoTipo, $alvoValor);

        if ($alvoTipo === 'route') {
            return $this->coringaCombina($alvoValor, $valorContexto);
        }

        return $alvoValor === $valorContexto;
    }

    private function coringaCombina($padrao, $valor)
    {
        if ($padrao === '*') {
            return true;
        }

        $expressao = preg_quote($padrao, '#');
        $expressao = str_replace('\\*', '.*', $expressao);

        return (bool) preg_match('#^' . $expressao . '$#i', $valor);
    }

    private function normalizarValorContexto($tipo, $valor)
    {
        $valor = trim((string) $valor);
        if ($tipo === 'route') {
            return $this->normalizarCaminho($valor);
        }

        return $this->normalizarChaveContexto($valor);
    }

    private function normalizarValorRegra($valor)
    {
        $valor = trim((string) $valor);
        if ($valor === '') {
            return '';
        }

        if (strpos($valor, '/') === 0) {
            return $this->normalizarCaminho($valor);
        }

        return $this->normalizarChaveContexto($valor);
    }

    private function normalizarCaminho($valor)
    {
        $valor = trim((string) $valor);
        if ($valor === '') {
            return '/';
        }

        $valor = str_replace('\\', '/', $valor);
        if (strpos($valor, '/') !== 0) {
            $valor = '/' . $valor;
        }

        if ($valor !== '/' && substr($valor, -1) === '/') {
            $valor = rtrim($valor, '/');
        }

        return function_exists('mb_strtolower') ? mb_strtolower($valor, 'UTF-8') : strtolower($valor);
    }

    private function normalizarChaveContexto($valor)
    {
        $valor = trim((string) $valor);
        return function_exists('mb_strtolower') ? mb_strtolower($valor, 'UTF-8') : strtolower($valor);
    }

    private function detectarAreaAtual($requestPath)
    {
        if (strpos($requestPath, '/admin') === 0) {
            return 'admin';
        }

        if (strpos($requestPath, '/professor') === 0) {
            return 'professor';
        }

        if (in_array($requestPath, array('/meus-cursos', '/area-curso', '/area-curso/modulo', '/area-curso/material'), true) || strpos($requestPath, '/area-curso/') === 0) {
            return 'aluno';
        }

        return 'publica';
    }

    private function registrarProblemaRegra($mensagem, array $contexto = array())
    {
        $mensagem = trim((string) $mensagem);
        if ($mensagem === '') {
            return;
        }

        $contextoTexto = $contexto ? ' ' . json_encode($contexto, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
        Logger::error('frontend_menu.regras_exibicao', array(
            'message' => $mensagem,
            'contexto' => $contexto,
        ));
        @error_log('[FrontendMenuService] ' . $mensagem . $contextoTexto);
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

    private function sanitizeItens(array $items)
    {
        $sanitized = array();

        foreach ($items as $item) {
            $url = trim((string) (isset($item['url']) ? $item['url'] : ''));
            $target = trim((string) (isset($item['target']) ? $item['target'] : '_self'));
            $rotulo = trim((string) (isset($item['rotulo']) ? $item['rotulo'] : ''));
            $rel = $this->nullableTrim(isset($item['rel']) ? $item['rel'] : null);

            if ($rotulo === '' || preg_match('/<[^>]*>/', $rotulo)) {
                continue;
            }
            if (!$this->urlValida($url)) {
                continue;
            }
            if (!in_array($target, array('_self', '_blank'), true)) {
                $target = '_self';
            }
            if ($target === '_blank' && ($rel === null || $rel === '')) {
                $rel = 'noopener noreferrer';
            }

            $item['url'] = $url;
            $item['target'] = $target;
            $item['rotulo'] = $rotulo;
            $item['rel'] = $rel;
            $sanitized[] = $item;
        }

        return $sanitized;
    }

    private function hasBlockedScheme($url)
    {
        return preg_match('/^\s*(javascript|data|vbscript)\s*:/i', (string) $url) === 1;
    }
}
