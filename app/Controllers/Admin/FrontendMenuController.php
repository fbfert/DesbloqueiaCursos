<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Services\FrontendMenuService;

class FrontendMenuController extends Controller
{
    private $service;

    public function __construct()
    {
        $this->service = new FrontendMenuService();
    }

    public function index(Request $request)
    {
        return $this->view('admin/frontend/menus/index', array_merge(
            array(
                'title' => 'Menus do frontend',
                'success' => Session::pullFlash('success'),
                'errors' => Session::pullFlash('errors', array()),
            ),
            $this->service->listAdmin()
        ));
    }

    public function create(Request $request)
    {
        return $this->view('admin/frontend/menus/form', array(
            'title' => 'Novo menu do frontend',
            'action_url' => '/admin/frontend/menus/criar',
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
            'form_data' => $this->service->formData(),
        ));
    }

    public function store(Request $request)
    {
        $input = $request->all();
        $result = $this->service->salvarMenu($input, Session::get('usuario_id'), $request->ip(), $request->userAgent());
        if (empty($result['ok'])) {
            Session::flash('errors', isset($result['errors']) ? $result['errors'] : array('Não foi possível salvar o menu.'));
            Session::flash('old', $input);
            return $this->redirect('/admin/frontend/menus/criar');
        }
        Session::flash('success', 'Menu salvo com sucesso.');
        return $this->redirect('/admin/frontend/menus');
    }

    public function edit(Request $request)
    {
        $menuId = (int) $request->query('menu_id', 0);
        return $this->view('admin/frontend/menus/form', array(
            'title' => 'Editar menu do frontend',
            'action_url' => '/admin/frontend/menus/editar',
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
            'form_data' => $this->service->formData($menuId),
        ));
    }

    public function update(Request $request)
    {
        $menuId = (int) $request->input('id', 0);
        $input = $request->all();
        $result = $this->service->salvarMenu($input, Session::get('usuario_id'), $request->ip(), $request->userAgent());
        if (empty($result['ok'])) {
            Session::flash('errors', isset($result['errors']) ? $result['errors'] : array('Não foi possível atualizar o menu.'));
            Session::flash('old', $input);
            return $this->redirect('/admin/frontend/menus/editar?menu_id=' . $menuId);
        }
        Session::flash('success', 'Menu atualizado com sucesso.');
        return $this->redirect('/admin/frontend/menus');
    }

    public function destroy(Request $request)
    {
        $menuId = (int) $request->input('id', 0);
        $justificativa = trim((string) $request->input('justificativa', ''));
        $result = $this->service->excluirMenu($menuId, $justificativa, Session::get('usuario_id'), $request->ip(), $request->userAgent());
        if (empty($result['ok'])) {
            Session::flash('errors', array(isset($result['message']) ? $result['message'] : 'Não foi possível excluir o menu.'));
            return $this->redirect('/admin/frontend/menus');
        }
        Session::flash('success', 'Menu excluído e enviado para a lixeira.');
        return $this->redirect('/admin/frontend/menus');
    }

    public function itens(Request $request)
    {
        $menuId = (int) $request->query('menu_id', 0);
        return $this->view('admin/frontend/menus/itens', array_merge(
            array(
                'title' => 'Itens do menu',
                'success' => Session::pullFlash('success'),
                'errors' => Session::pullFlash('errors', array()),
            ),
            $this->service->itensData($menuId)
        ));
    }

    public function itemCreate(Request $request)
    {
        $menuId = (int) $request->query('menu_id', 0);
        return $this->view('admin/frontend/menus/item_form', array_merge(
            array(
                'title' => 'Novo item de menu',
                'action_url' => '/admin/frontend/menus/itens/criar',
                'success' => Session::pullFlash('success'),
                'errors' => Session::pullFlash('errors', array()),
            ),
            $this->service->itemFormData($menuId)
        ));
    }

    public function itemStore(Request $request)
    {
        $menuId = (int) $request->input('menu_id', 0);
        $input = $request->all();
        $result = $this->service->salvarItem($menuId, $input, Session::get('usuario_id'), $request->ip(), $request->userAgent());
        if (empty($result['ok'])) {
            Session::flash('errors', isset($result['errors']) ? $result['errors'] : array('Não foi possível salvar o item.'));
            Session::flash('old', $input);
            return $this->redirect('/admin/frontend/menus/itens/criar?menu_id=' . $menuId);
        }
        Session::flash('success', 'Item salvo com sucesso.');
        return $this->redirect('/admin/frontend/menus/itens?menu_id=' . $menuId);
    }

    public function itemEdit(Request $request)
    {
        $menuId = (int) $request->query('menu_id', 0);
        $itemId = (int) $request->query('item_id', 0);
        return $this->view('admin/frontend/menus/item_form', array_merge(
            array(
                'title' => 'Editar item de menu',
                'action_url' => '/admin/frontend/menus/itens/editar',
                'success' => Session::pullFlash('success'),
                'errors' => Session::pullFlash('errors', array()),
            ),
            $this->service->itemFormData($menuId, $itemId)
        ));
    }

    public function itemUpdate(Request $request)
    {
        $menuId = (int) $request->input('menu_id', 0);
        $itemId = (int) $request->input('id', 0);
        $input = $request->all();
        $result = $this->service->salvarItem($menuId, $input, Session::get('usuario_id'), $request->ip(), $request->userAgent());
        if (empty($result['ok'])) {
            Session::flash('errors', isset($result['errors']) ? $result['errors'] : array('Não foi possível atualizar o item.'));
            Session::flash('old', $input);
            return $this->redirect('/admin/frontend/menus/itens/editar?menu_id=' . $menuId . '&item_id=' . $itemId);
        }
        Session::flash('success', 'Item atualizado com sucesso.');
        return $this->redirect('/admin/frontend/menus/itens?menu_id=' . $menuId);
    }

    public function itemDestroy(Request $request)
    {
        $menuId = (int) $request->input('menu_id', 0);
        $itemId = (int) $request->input('id', 0);
        $justificativa = trim((string) $request->input('justificativa', ''));
        $result = $this->service->excluirItem($menuId, $itemId, $justificativa, Session::get('usuario_id'), $request->ip(), $request->userAgent());
        if (empty($result['ok'])) {
            Session::flash('errors', array(isset($result['message']) ? $result['message'] : 'Não foi possível excluir o item.'));
            return $this->redirect('/admin/frontend/menus/itens?menu_id=' . $menuId);
        }
        Session::flash('success', 'Item excluído e enviado para a lixeira.');
        return $this->redirect('/admin/frontend/menus/itens?menu_id=' . $menuId);
    }

    public function itensReordenar(Request $request)
    {
        $menuId = (int) $request->input('menu_id', 0);
        $ordens = $request->input('ordens', array());
        if (!is_array($ordens)) {
            $ordens = array();
        }
        $result = $this->service->reordenarItens($menuId, $ordens, Session::get('usuario_id'), $request->ip(), $request->userAgent());
        if (empty($result['ok'])) {
            Session::flash('errors', array('Não foi possível reordenar os itens.'));
            return $this->redirect('/admin/frontend/menus/itens?menu_id=' . $menuId);
        }
        Session::flash('success', 'Itens reordenados com sucesso.');
        return $this->redirect('/admin/frontend/menus/itens?menu_id=' . $menuId);
    }
}
