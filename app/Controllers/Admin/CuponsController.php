<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Services\CupomService;

class CuponsController extends Controller
{
    private $cupomService;

    public function __construct()
    {
        $this->cupomService = new CupomService();
    }

    public function index(Request $request)
    {
        return $this->view('admin/cupons/index', array_merge(
            array(
                'title' => 'Cupons',
                'success' => Session::pullFlash('success'),
                'errors' => Session::pullFlash('errors', array()),
            ),
            $this->cupomService->listarBackoffice(Session::get('usuario_id'))
        ));
    }

    public function create(Request $request)
    {
        return $this->view('admin/cupons/form', array(
            'title' => 'Novo cupom',
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
            'action_url' => '/admin/cupons/criar',
            'submit_label' => 'Salvar cupom',
            'form_data' => $this->cupomService->formData(),
        ));
    }

    public function store(Request $request)
    {
        $result = $this->cupomService->salvar(
            $request->all(),
            Session::get('usuario_id'),
            $request->ip(),
            $request->userAgent()
        );

        if (empty($result['ok'])) {
            Session::flash('errors', isset($result['errors']) ? $result['errors'] : array('Nao foi possivel salvar o cupom.'));
            return $this->redirect('/admin/cupons/criar');
        }

        Session::flash('success', 'Cupom salvo com sucesso.');
        return $this->redirect('/admin/cupons');
    }

    public function edit(Request $request)
    {
        $cupomId = (int) $request->query('cupom_id', 0);

        return $this->view('admin/cupons/form', array(
            'title' => 'Editar cupom',
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
            'action_url' => '/admin/cupons/editar',
            'submit_label' => 'Atualizar cupom',
            'form_data' => $this->cupomService->formData($cupomId),
        ));
    }

    public function update(Request $request)
    {
        $result = $this->cupomService->salvar(
            $request->all(),
            Session::get('usuario_id'),
            $request->ip(),
            $request->userAgent()
        );

        if (empty($result['ok'])) {
            $cupomId = (int) $request->input('id', 0);
            Session::flash('errors', isset($result['errors']) ? $result['errors'] : array('Nao foi possivel atualizar o cupom.'));
            return $this->redirect('/admin/cupons/editar?cupom_id=' . $cupomId);
        }

        Session::flash('success', 'Cupom atualizado com sucesso.');
        return $this->redirect('/admin/cupons');
    }

    public function show(Request $request)
    {
        $cupomId = (int) $request->query('cupom_id', 0);
        $formData = $this->cupomService->resumoUso($cupomId);

        if (empty($formData['cupom'])) {
            Session::flash('errors', array('Cupom nao encontrado.'));
            return $this->redirect('/admin/cupons');
        }

        return $this->view('admin/cupons/show', array_merge(
            array(
                'title' => 'Resumo do cupom',
                'success' => Session::pullFlash('success'),
                'errors' => Session::pullFlash('errors', array()),
            ),
            $formData
        ));
    }

    public function destroy(Request $request)
    {
        $cupomId = (int) $request->input('id', 0);
        $justificativa = trim((string) $request->input('justificativa', ''));

        $result = $this->cupomService->excluir(
            $cupomId,
            $justificativa,
            Session::get('usuario_id'),
            $request->ip(),
            $request->userAgent()
        );

        if (empty($result['ok'])) {
            Session::flash('errors', array(isset($result['message']) ? $result['message'] : 'Nao foi possivel excluir o cupom.'));
            return $this->redirect('/admin/cupons/editar?cupom_id=' . $cupomId);
        }

        Session::flash('success', 'Cupom excluido e enviado para a lixeira.');
        return $this->redirect('/admin/cupons');
    }
}
