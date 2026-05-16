<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Services\PaginaService;

class PaginasController extends Controller
{
    private $paginaService;

    public function __construct()
    {
        $this->paginaService = new PaginaService();
    }

    public function index(Request $request)
    {
        return $this->view('admin/paginas/index', array_merge(
            array(
                'title' => 'Páginas',
                'success' => Session::pullFlash('success'),
                'errors' => Session::pullFlash('errors', array()),
            ),
            $this->paginaService->listAdmin()
        ));
    }

    public function create(Request $request)
    {
        return $this->view('admin/paginas/form', array(
            'title' => 'Nova página',
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
            'action_url' => '/admin/paginas/criar',
            'submit_label' => 'Salvar página',
            'form_data' => $this->paginaService->formData(),
        ));
    }

    public function store(Request $request)
    {
        $input = $request->all();
        $action = $this->submitAction($request, 'save_exit');

        if ($action === 'save_copy') {
            $copia = $this->paginaService->duplicar($input, Session::get('usuario_id'), $request->ip(), $request->userAgent());
            if (empty($copia['ok'])) {
                Session::flash('errors', isset($copia['errors']) ? $copia['errors'] : array('Não foi possível criar a cópia da página.'));
                Session::flash('old', $input);
                return $this->redirect('/admin/paginas/criar');
            }

            Session::flash('success', 'Cópia da página criada com sucesso.');
            return $this->redirect('/admin/paginas/editar?pagina_id=' . (int) $copia['id']);
        }

        $result = $this->paginaService->salvar($input, Session::get('usuario_id'), $request->ip(), $request->userAgent());

        if (empty($result['ok'])) {
            Session::flash('errors', isset($result['errors']) ? $result['errors'] : array('Não foi possível salvar a página.'));
            Session::flash('old', $input);
            return $this->redirect('/admin/paginas/criar');
        }

        if ($action === 'save_stay') {
            Session::flash('success', 'Página salva com sucesso.');
            return $this->redirect('/admin/paginas/editar?pagina_id=' . (int) $result['id']);
        }

        if ($action === 'save_new') {
            Session::flash('success', 'Página salva com sucesso. Você já pode criar uma nova página.');
            return $this->redirect('/admin/paginas/criar');
        }

        Session::flash('success', 'Página salva com sucesso.');
        return $this->redirect('/admin/paginas');
    }

    public function edit(Request $request)
    {
        $paginaId = (int) $request->query('pagina_id', 0);

        return $this->view('admin/paginas/form', array(
            'title' => 'Editar página',
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
            'action_url' => '/admin/paginas/editar',
            'submit_label' => 'Atualizar página',
            'form_data' => $this->paginaService->formData($paginaId),
        ));
    }

    public function update(Request $request)
    {
        $paginaId = (int) $request->input('id', 0);
        $input = $request->all();
        $action = $this->submitAction($request, 'save_exit');

        if ($action === 'save_copy') {
            $copia = $this->paginaService->duplicar($input, Session::get('usuario_id'), $request->ip(), $request->userAgent());
            if (empty($copia['ok'])) {
                Session::flash('errors', isset($copia['errors']) ? $copia['errors'] : array('Não foi possível criar a cópia da página.'));
                Session::flash('old', $input);
                return $this->redirect('/admin/paginas/editar?pagina_id=' . $paginaId);
            }

            Session::flash('success', 'Cópia da página criada com sucesso.');
            return $this->redirect('/admin/paginas/editar?pagina_id=' . (int) $copia['id']);
        }

        $result = $this->paginaService->salvar($input, Session::get('usuario_id'), $request->ip(), $request->userAgent());

        if (empty($result['ok'])) {
            Session::flash('errors', isset($result['errors']) ? $result['errors'] : array('Não foi possível atualizar a página.'));
            Session::flash('old', $input);
            return $this->redirect('/admin/paginas/editar?pagina_id=' . $paginaId);
        }

        if ($action === 'save_stay') {
            Session::flash('success', 'Página atualizada com sucesso.');
            return $this->redirect('/admin/paginas/editar?pagina_id=' . (int) $result['id']);
        }

        if ($action === 'save_new') {
            Session::flash('success', 'Página atualizada com sucesso. Você já pode criar uma nova página.');
            return $this->redirect('/admin/paginas/criar');
        }

        Session::flash('success', 'Página atualizada com sucesso.');
        return $this->redirect('/admin/paginas');
    }

    public function show(Request $request)
    {
        $paginaId = (int) $request->query('pagina_id', 0);
        $formData = $this->paginaService->formData($paginaId);

        if (empty($formData['pagina'])) {
            Session::flash('errors', array('Página não encontrada.'));
            return $this->redirect('/admin/paginas');
        }

        return $this->view('admin/paginas/show', array_merge(
            array(
                'title' => 'Página',
                'success' => Session::pullFlash('success'),
                'errors' => Session::pullFlash('errors', array()),
            ),
            $formData
        ));
    }

    public function destroy(Request $request)
    {
        $paginaId = (int) $request->input('id', 0);
        $justificativa = trim((string) $request->input('justificativa', ''));
        $result = $this->paginaService->excluir($paginaId, $justificativa, Session::get('usuario_id'), $request->ip(), $request->userAgent());

        if (empty($result['ok'])) {
            Session::flash('errors', array(isset($result['message']) ? $result['message'] : 'Não foi possível excluir a página.'));
            return $this->redirect('/admin/paginas/editar?pagina_id=' . $paginaId);
        }

        Session::flash('success', 'Página excluída e enviada para a lixeira.');
        return $this->redirect('/admin/paginas');
    }
}
