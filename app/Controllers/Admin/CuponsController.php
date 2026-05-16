<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Logger;
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
        try {
            return $this->view('admin/cupons/index', array_merge(
                array(
                    'title' => 'Cupons',
                    'success' => Session::pullFlash('success'),
                    'errors' => Session::pullFlash('errors', array()),
                ),
                $this->cupomService->listarBackoffice(Session::get('usuario_id'))
            ));
        } catch (\Throwable $throwable) {
            Logger::error('cupom.index_falhou', array(
                'message' => $throwable->getMessage(),
                'usuario_id' => Session::get('usuario_id'),
                'path' => $request->path(),
            ));

            Session::flash('errors', array('Nao foi possivel carregar a listagem de cupons no momento.'));

            return $this->view('admin/cupons/index', array(
                'title' => 'Cupons',
                'success' => Session::pullFlash('success'),
                'errors' => Session::pullFlash('errors', array()),
                'cupons' => array(),
                'cupons_inativos' => array(),
            ));
        }
    }

    public function create(Request $request)
    {
        return $this->view('admin/cupons/form', array(
            'title' => 'Novo cupom',
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
            'action_url' => '/admin/cupons/criar',
            'form_data' => $this->cupomService->formData(),
        ));
    }

    public function store(Request $request)
    {
        $action = $this->submitAction($request, 'save_exit');
        $input = $request->all();

        if ($action === 'save_copy') {
            $result = $this->cupomService->duplicar(
                $input,
                Session::get('usuario_id'),
                $request->ip(),
                $request->userAgent()
            );
            if (empty($result['ok'])) {
                Session::flash('errors', isset($result['errors']) ? $result['errors'] : array('Não foi possivel salvar o cupom.'));
                Session::flash('old', $input);
                return $this->redirect('/admin/cupons/criar');
            }

            Session::flash('success', 'Cópia do cupom criada com sucesso.');
            return $this->redirect('/admin/cupons/editar?cupom_id=' . (int) $result['cupom_id']);
        }

        $result = $this->cupomService->salvar(
            $input,
            Session::get('usuario_id'),
            $request->ip(),
            $request->userAgent()
        );

        if (empty($result['ok'])) {
            Session::flash('errors', isset($result['errors']) ? $result['errors'] : array('Não foi possivel salvar o cupom.'));
            Session::flash('old', $input);
            return $this->redirect('/admin/cupons/criar');
        }

        if ($action === 'save_stay') {
            Session::flash('success', 'Cupom salvo com sucesso.');
            return $this->redirect('/admin/cupons/editar?cupom_id=' . (int) $result['cupom_id']);
        }

        if ($action === 'save_new') {
            Session::flash('success', 'Cupom salvo com sucesso. Você já pode criar um novo cupom.');
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
            'form_data' => $this->cupomService->formData($cupomId),
        ));
    }

    public function update(Request $request)
    {
        $input = $request->all();
        $action = $this->submitAction($request, 'save_exit');
        $cupomId = (int) $request->input('id', 0);

        if ($action === 'save_copy') {
            $result = $this->cupomService->duplicar(
                $input,
                Session::get('usuario_id'),
                $request->ip(),
                $request->userAgent()
            );
            if (empty($result['ok'])) {
                Session::flash('errors', isset($result['errors']) ? $result['errors'] : array('Não foi possivel criar a cópia do cupom.'));
                Session::flash('old', $input);
                return $this->redirect('/admin/cupons/editar?cupom_id=' . $cupomId);
            }

            Session::flash('success', 'Cópia do cupom criada com sucesso.');
            return $this->redirect('/admin/cupons/editar?cupom_id=' . (int) $result['cupom_id']);
        }

        $result = $this->cupomService->salvar(
            $input,
            Session::get('usuario_id'),
            $request->ip(),
            $request->userAgent()
        );

        if (empty($result['ok'])) {
            Session::flash('errors', isset($result['errors']) ? $result['errors'] : array('Não foi possivel atualizar o cupom.'));
            Session::flash('old', $input);
            return $this->redirect('/admin/cupons/editar?cupom_id=' . $cupomId);
        }

        if ($action === 'save_stay') {
            Session::flash('success', 'Cupom atualizado com sucesso.');
            return $this->redirect('/admin/cupons/editar?cupom_id=' . (int) $result['cupom_id']);
        }

        if ($action === 'save_new') {
            Session::flash('success', 'Cupom atualizado com sucesso. Você já pode criar um novo cupom.');
            return $this->redirect('/admin/cupons/criar');
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
            Session::flash('errors', array(isset($result['message']) ? $result['message'] : 'Não foi possivel excluir o cupom.'));
            return $this->redirect('/admin/cupons/editar?cupom_id=' . $cupomId);
        }

        Session::flash('success', 'Cupom excluido e enviado para a lixeira.');
        return $this->redirect('/admin/cupons');
    }

    public function status(Request $request)
    {
        $cupomId = (int) $request->input('id', 0);
        $status = trim((string) $request->input('status', ''));

        $result = $this->cupomService->alterarStatus(
            $cupomId,
            $status,
            Session::get('usuario_id'),
            $request->ip(),
            $request->userAgent()
        );

        if (empty($result['ok'])) {
            Session::flash('errors', array(isset($result['message']) ? $result['message'] : 'Não foi possivel atualizar o status do cupom.'));
            return $this->redirect('/admin/cupons');
        }

        Session::flash('success', $status === 'ativo' ? 'Cupom reativado com sucesso.' : 'Cupom inativado com sucesso.');
        return $this->redirect('/admin/cupons');
    }
}

