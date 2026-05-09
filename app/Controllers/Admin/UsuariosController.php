<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Services\GestaoAcessoService;

class UsuariosController extends Controller
{
    private $service;

    public function __construct()
    {
        $this->service = new GestaoAcessoService();
    }

    public function index(Request $request)
    {
        $filters = array(
            'q' => $request->query('q', ''),
            'status' => $request->query('status', ''),
            'perfil' => $request->query('perfil', ''),
            'sort_by' => $request->query('sort_by', 'id'),
            'sort_dir' => $request->query('sort_dir', 'desc'),
        );

        return $this->view('admin/usuarios/index', array_merge(
            array(
                'title' => 'Usuários',
                'success' => Session::pullFlash('success'),
                'errors' => Session::pullFlash('errors', array()),
                'filters' => $filters,
            ),
            $this->service->painelUsuarios($filters)
        ));
    }

    public function create(Request $request)
    {
        return $this->view('admin/usuarios/form', array(
            'title' => 'Novo usuário',
            'action_url' => '/admin/usuarios/criar',
            'submit_label' => 'Salvar usuário',
            'form_data' => $this->service->dadosUsuario(null),
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
            'old' => Session::pullFlash('old', array()),
        ));
    }

    public function store(Request $request)
    {
        $result = $this->service->salvarUsuario($request->all(), Session::get('usuario_id'), $request->ip(), $request->userAgent());
        if (empty($result['ok'])) {
            Session::flash('errors', $result['errors'] ?? array('Não foi possível salvar o usuário.'));
            Session::flash('old', $request->all());
            return $this->redirect('/admin/usuarios/criar');
        }
        Session::flash('success', 'Usuário salvo com sucesso.');
        return $this->redirect('/admin/usuarios');
    }

    public function edit(Request $request)
    {
        $usuarioId = (int) $request->query('usuario_id', 0);
        return $this->view('admin/usuarios/form', array(
            'title' => 'Editar usuário',
            'action_url' => '/admin/usuarios/editar',
            'submit_label' => 'Atualizar usuário',
            'form_data' => $this->service->dadosUsuario($usuarioId),
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
            'old' => Session::pullFlash('old', array()),
        ));
    }

    public function update(Request $request)
    {
        $result = $this->service->salvarUsuario($request->all(), Session::get('usuario_id'), $request->ip(), $request->userAgent());
        $usuarioId = (int) $request->input('id', 0);
        if (empty($result['ok'])) {
            Session::flash('errors', $result['errors'] ?? array('Não foi possível atualizar o usuário.'));
            Session::flash('old', $request->all());
            return $this->redirect('/admin/usuarios/editar?usuario_id=' . $usuarioId);
        }
        Session::flash('success', 'Usuário atualizado com sucesso.');
        return $this->redirect('/admin/usuarios');
    }

    public function destroy(Request $request)
    {
        $usuarioId = (int) $request->input('id', 0);
        $justificativa = trim((string) $request->input('justificativa', ''));
        $result = $this->service->excluirUsuario($usuarioId, $justificativa, Session::get('usuario_id'), $request->ip(), $request->userAgent());
        if (empty($result['ok'])) {
            Session::flash('errors', array($result['message'] ?? 'Não foi possível excluir o usuário.'));
            return $this->redirect('/admin/usuarios');
        }
        Session::flash('success', 'Usuário inativado e enviado para a lixeira.');
        return $this->redirect('/admin/usuarios');
    }
}
