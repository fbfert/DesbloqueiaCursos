<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Services\CategoriaService;

class CategoriasController extends Controller
{
    private $categoriaService;

    public function __construct()
    {
        $this->categoriaService = new CategoriaService();
    }

    public function index(Request $request)
    {
        return $this->view('admin/categorias/index', array_merge(
            array(
                'title' => 'Categorias',
                'success' => Session::pullFlash('success'),
                'errors' => Session::pullFlash('errors', array()),
            ),
            $this->categoriaService->listAdmin()
        ));
    }

    public function create(Request $request)
    {
        return $this->view('admin/categorias/form', array(
            'title' => 'Nova categoria',
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
            'action_url' => '/admin/categorias/criar',
            'submit_label' => 'Salvar categoria',
            'form_data' => $this->categoriaService->formData(),
        ));
    }

    public function store(Request $request)
    {
        $result = $this->categoriaService->salvar($request->all(), Session::get('usuario_id'), $request->ip(), $request->userAgent());

        if (empty($result['ok'])) {
            Session::flash('errors', isset($result['errors']) ? $result['errors'] : array('Não foi possivel salvar a categoria.'));
            return $this->redirect('/admin/categorias/criar');
        }

        Session::flash('success', 'Categoria salva com sucesso.');
        return $this->redirect('/admin/categorias');
    }

    public function edit(Request $request)
    {
        $categoriaId = (int) $request->query('categoria_id', 0);

        return $this->view('admin/categorias/form', array(
            'title' => 'Editar categoria',
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
            'action_url' => '/admin/categorias/editar',
            'submit_label' => 'Atualizar categoria',
            'form_data' => $this->categoriaService->formData($categoriaId),
        ));
    }

    public function update(Request $request)
    {
        $result = $this->categoriaService->salvar($request->all(), Session::get('usuario_id'), $request->ip(), $request->userAgent());
        $categoriaId = (int) $request->input('id', 0);

        if (empty($result['ok'])) {
            Session::flash('errors', isset($result['errors']) ? $result['errors'] : array('Não foi possivel atualizar a categoria.'));
            return $this->redirect('/admin/categorias/editar?categoria_id=' . $categoriaId);
        }

        Session::flash('success', 'Categoria atualizada com sucesso.');
        return $this->redirect('/admin/categorias');
    }

    public function show(Request $request)
    {
        $categoriaId = (int) $request->query('categoria_id', 0);
        $formData = $this->categoriaService->formData($categoriaId);

        if (empty($formData['categoria'])) {
            Session::flash('errors', array('Categoria nao encontrada.'));
            return $this->redirect('/admin/categorias');
        }

        return $this->view('admin/categorias/show', array_merge(
            array(
                'title' => 'Categoria',
                'success' => Session::pullFlash('success'),
                'errors' => Session::pullFlash('errors', array()),
            ),
            $formData
        ));
    }

    public function destroy(Request $request)
    {
        $categoriaId = (int) $request->input('id', 0);
        $justificativa = trim((string) $request->input('justificativa', ''));

        $result = $this->categoriaService->excluir($categoriaId, $justificativa, Session::get('usuario_id'), $request->ip(), $request->userAgent());

        if (empty($result['ok'])) {
            Session::flash('errors', array(isset($result['message']) ? $result['message'] : 'Não foi possivel excluir a categoria.'));
            return $this->redirect('/admin/categorias/editar?categoria_id=' . $categoriaId);
        }

        Session::flash('success', 'Categoria excluida e enviada para a lixeira.');
        return $this->redirect('/admin/categorias');
    }
}

