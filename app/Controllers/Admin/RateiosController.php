<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Services\RateioService;

class RateiosController extends Controller
{
    private $rateioService;

    public function __construct()
    {
        $this->rateioService = new RateioService();
    }

    public function index(Request $request)
    {
        $apuracaoId = (int) $request->query('apuracao_id', 0);
        $panel = $this->rateioService->listAdmin($apuracaoId > 0 ? $apuracaoId : null);

        return $this->view('admin/rateios/index', array(
            'title' => 'Rateios',
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
            'apuracao_id' => $apuracaoId,
            'apuracoes' => $panel['apuracoes'],
            'rateios' => $panel['rateios'],
        ));
    }

    public function create(Request $request)
    {
        return $this->view('admin/rateios/form', array(
            'title' => 'Novo rateio',
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
            'action_url' => '/admin/rateios/criar',
            'submit_label' => 'Salvar rateio',
            'form_data' => $this->rateioService->formData(null, (int) $request->query('apuracao_id', 0)),
        ));
    }

    public function store(Request $request)
    {
        $result = $this->rateioService->salvar($request->all(), Session::get('usuario_id'), $request->ip(), $request->userAgent());
        if (empty($result['ok'])) {
            Session::flash('errors', isset($result['errors']) ? (array) $result['errors'] : array('Nao foi possivel salvar o rateio.'));
            return $this->redirect('/admin/rateios/criar?apuracao_id=' . (int) $request->input('apuracao_id', 0));
        }

        Session::flash('success', 'Rateio salvo com sucesso.');
        return $this->redirect('/admin/rateios/show?rateio_id=' . (int) $result['id']);
    }

    public function edit(Request $request)
    {
        $rateioId = (int) $request->query('rateio_id', 0);
        $formData = $this->rateioService->formData($rateioId);

        if (empty($formData['rateio'])) {
            Session::flash('errors', array('Rateio nao encontrado.'));
            return $this->redirect('/admin/rateios');
        }

        return $this->view('admin/rateios/form', array(
            'title' => 'Editar rateio',
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
            'action_url' => '/admin/rateios/editar',
            'submit_label' => 'Atualizar rateio',
            'form_data' => $formData,
        ));
    }

    public function update(Request $request)
    {
        $result = $this->rateioService->salvar($request->all(), Session::get('usuario_id'), $request->ip(), $request->userAgent());
        $rateioId = (int) $request->input('id', 0);

        if (empty($result['ok'])) {
            Session::flash('errors', isset($result['errors']) ? (array) $result['errors'] : array('Nao foi possivel atualizar o rateio.'));
            return $this->redirect('/admin/rateios/editar?rateio_id=' . $rateioId);
        }

        Session::flash('success', 'Rateio atualizado com sucesso.');
        return $this->redirect('/admin/rateios/show?rateio_id=' . (int) $result['id']);
    }

    public function show(Request $request)
    {
        $rateioId = (int) $request->query('rateio_id', 0);
        if ($rateioId <= 0) {
            Session::flash('errors', array('Informe um rateio valido.'));
            return $this->redirect('/admin/rateios');
        }

        $resultado = $this->rateioService->showRateioAdmin($rateioId);
        if (!$resultado) {
            Session::flash('errors', array('Rateio nao encontrado.'));
            return $this->redirect('/admin/rateios');
        }

        return $this->view('admin/rateios/show', array(
            'title' => 'Rateio da apuracao',
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
            'rateio' => $resultado['rateio'],
            'participantes' => $resultado['participantes'],
        ));
    }

    public function destroy(Request $request)
    {
        $rateioId = (int) $request->input('id', 0);
        $justificativa = trim((string) $request->input('justificativa', ''));

        $result = $this->rateioService->excluir($rateioId, $justificativa, Session::get('usuario_id'), $request->ip(), $request->userAgent());
        if (empty($result['ok'])) {
            Session::flash('errors', isset($result['message']) ? array($result['message']) : array('Nao foi possivel excluir o rateio.'));
            return $this->redirect('/admin/rateios/show?rateio_id=' . $rateioId);
        }

        Session::flash('success', 'Rateio excluido e enviado para a lixeira.');
        return $this->redirect('/admin/rateios');
    }
}
