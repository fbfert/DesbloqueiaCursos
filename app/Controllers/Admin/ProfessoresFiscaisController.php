<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Services\FinanceiroService;

class ProfessoresFiscaisController extends Controller
{
    private $financeiroService;

    public function __construct()
    {
        $this->financeiroService = new FinanceiroService();
    }

    public function index(Request $request)
    {
        $panel = $this->financeiroService->painelAdmin();

        return $this->view('admin/professores-fiscais/index', array(
            'title' => 'Professores fiscais',
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
            'professores_fiscal' => $panel['professores_fiscal'],
            'professores' => $panel['professores'],
        ));
    }

    public function create(Request $request)
    {
        $panel = $this->financeiroService->painelAdmin();

        return $this->view('admin/professores-fiscais/form', array(
            'title' => 'Novo perfil fiscal',
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
            'action_url' => '/admin/professores-fiscais/criar',
            'submit_label' => 'Salvar perfil fiscal',
            'form_data' => array('perfil' => null, 'professores' => $panel['professores']),
        ));
    }

    public function store(Request $request)
    {
        $result = $this->financeiroService->salvarProfessorFiscal(
            $request->all(),
            Session::get('usuario_id'),
            $request->ip(),
            $request->userAgent()
        );

        if (empty($result['ok'])) {
            Session::flash('errors', isset($result['message']) ? array($result['message']) : array('Não foi possivel salvar o perfil fiscal.'));
            return $this->redirect('/admin/professores-fiscais/criar');
        }

        Session::flash('success', 'Perfil fiscal salvo com sucesso.');
        return $this->redirect('/admin/professores-fiscais');
    }

    public function edit(Request $request)
    {
        $perfilId = (int) $request->query('perfil_id', 0);
        $panel = $this->financeiroService->painelAdmin();

        $perfil = null;
        foreach ($panel['professores_fiscal'] as $item) {
            if ((int) $item['id'] === $perfilId) {
                $perfil = $item;
                break;
            }
        }

        if (!$perfil) {
            Session::flash('errors', array('Perfil fiscal nao encontrado.'));
            return $this->redirect('/admin/professores-fiscais');
        }

        return $this->view('admin/professores-fiscais/form', array(
            'title' => 'Editar perfil fiscal',
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
            'action_url' => '/admin/professores-fiscais/editar',
            'submit_label' => 'Atualizar perfil fiscal',
            'form_data' => array('perfil' => $perfil, 'professores' => $panel['professores']),
        ));
    }

    public function update(Request $request)
    {
        $result = $this->financeiroService->salvarProfessorFiscal(
            $request->all(),
            Session::get('usuario_id'),
            $request->ip(),
            $request->userAgent()
        );

        if (empty($result['ok'])) {
            $perfilId = (int) $request->input('id', 0);
            Session::flash('errors', isset($result['message']) ? array($result['message']) : array('Não foi possivel atualizar o perfil fiscal.'));
            return $this->redirect('/admin/professores-fiscais/editar?perfil_id=' . $perfilId);
        }

        Session::flash('success', 'Perfil fiscal atualizado com sucesso.');
        return $this->redirect('/admin/professores-fiscais');
    }

    public function show(Request $request)
    {
        $perfilId = (int) $request->query('perfil_id', 0);
        $panel = $this->financeiroService->painelAdmin();
        $perfil = null;

        foreach ($panel['professores_fiscal'] as $item) {
            if ((int) $item['id'] === $perfilId) {
                $perfil = $item;
                break;
            }
        }

        if (!$perfil) {
            Session::flash('errors', array('Perfil fiscal nao encontrado.'));
            return $this->redirect('/admin/professores-fiscais');
        }

        return $this->view('admin/professores-fiscais/show', array(
            'title' => 'Perfil fiscal',
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
            'perfil' => $perfil,
        ));
    }

    public function destroy(Request $request)
    {
        $perfilId = (int) $request->input('id', 0);
        $justificativa = trim((string) $request->input('justificativa', ''));

        if ($perfilId <= 0 || $justificativa === '') {
            Session::flash('errors', array('Informe o perfil e a justificativa para excluir.'));
            return $this->redirect('/admin/professores-fiscais');
        }

        $perfil = $this->financeiroService->painelAdmin()['professores_fiscal'];
        $alvo = null;
        foreach ($perfil as $item) {
            if ((int) $item['id'] === $perfilId) {
                $alvo = $item;
                break;
            }
        }

        if (!$alvo) {
            Session::flash('errors', array('Perfil fiscal nao encontrado.'));
            return $this->redirect('/admin/professores-fiscais');
        }

        $result = $this->financeiroService->removerProfessorFiscal($perfilId, $justificativa, Session::get('usuario_id'), $request->ip(), $request->userAgent());
        if (empty($result['ok'])) {
            Session::flash('errors', array(isset($result['message']) ? $result['message'] : 'Não foi possivel remover o perfil fiscal.'));
            return $this->redirect('/admin/professores-fiscais/show?perfil_id=' . $perfilId);
        }

        Session::flash('success', 'Perfil fiscal removido e enviado para a lixeira.');
        return $this->redirect('/admin/professores-fiscais');
    }
}

