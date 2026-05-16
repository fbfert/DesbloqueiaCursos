<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Services\GestaoAcessoService;

class PermissoesController extends Controller
{
    private $service;

    public function __construct()
    {
        $this->service = new GestaoAcessoService();
    }

    public function index(Request $request)
    {
        return $this->view('admin/permissoes/index', array_merge(
            array(
                'title' => 'Permissões',
                'success' => Session::pullFlash('success'),
                'errors' => Session::pullFlash('errors', array()),
            ),
            $this->service->painelPermissoes()
        ));
    }

    public function perfilForm(Request $request)
    {
        $perfilId = (int) $request->query('perfil_id', 0);
        return $this->view('admin/permissoes/perfil-form', array(
            'title' => $perfilId > 0 ? 'Editar papel' : 'Novo papel',
            'form_data' => $this->service->dadosPerfil($perfilId),
            'action_url' => $perfilId > 0 ? '/admin/permissoes/perfil/editar' : '/admin/permissoes/perfil/criar',
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
            'old' => Session::pullFlash('old', array()),
        ));
    }

    public function perfilStore(Request $request)
    {
        $input = $request->all();
        $action = $this->submitAction($request, 'save_exit');
        if ($action === 'save_copy') {
            $result = $this->service->duplicarPerfil($input, Session::get('usuario_id'), $request->ip(), $request->userAgent());
            if (empty($result['ok'])) {
                Session::flash('errors', $result['errors'] ?? array('Não foi possível criar a cópia do papel.'));
                Session::flash('old', $input);
                return $this->redirect('/admin/permissoes/perfil/criar');
            }

            Session::flash('success', 'Cópia do papel criada com sucesso.');
            return $this->redirect('/admin/permissoes/perfil/editar?perfil_id=' . (int) $result['id']);
        }

        $result = $this->service->salvarPerfil($input, Session::get('usuario_id'), $request->ip(), $request->userAgent());
        if (empty($result['ok'])) {
            Session::flash('errors', $result['errors'] ?? array('Não foi possível salvar o papel.'));
            Session::flash('old', $input);
            return $this->redirect('/admin/permissoes/perfil/criar');
        }
        if ($action === 'save_stay') {
            Session::flash('success', 'Papel salvo com sucesso.');
            return $this->redirect('/admin/permissoes/perfil/editar?perfil_id=' . (int) $result['id']);
        }
        if ($action === 'save_new') {
            Session::flash('success', 'Papel salvo com sucesso. Você já pode criar um novo papel.');
            return $this->redirect('/admin/permissoes/perfil/criar');
        }
        Session::flash('success', 'Papel salvo com sucesso.');
        return $this->redirect('/admin/permissoes');
    }

    public function perfilUpdate(Request $request)
    {
        $input = $request->all();
        $perfilId = (int) $request->input('id', 0);
        $action = $this->submitAction($request, 'save_exit');
        if ($action === 'save_copy') {
            $result = $this->service->duplicarPerfil($input, Session::get('usuario_id'), $request->ip(), $request->userAgent());
            if (empty($result['ok'])) {
                Session::flash('errors', $result['errors'] ?? array('Não foi possível criar a cópia do papel.'));
                Session::flash('old', $input);
                return $this->redirect('/admin/permissoes/perfil/editar?perfil_id=' . $perfilId);
            }

            Session::flash('success', 'Cópia do papel criada com sucesso.');
            return $this->redirect('/admin/permissoes/perfil/editar?perfil_id=' . (int) $result['id']);
        }

        $result = $this->service->salvarPerfil($input, Session::get('usuario_id'), $request->ip(), $request->userAgent());
        if (empty($result['ok'])) {
            Session::flash('errors', $result['errors'] ?? array('Não foi possível atualizar o papel.'));
            Session::flash('old', $input);
            return $this->redirect('/admin/permissoes/perfil/editar?perfil_id=' . $perfilId);
        }
        if ($action === 'save_stay') {
            Session::flash('success', 'Papel atualizado com sucesso.');
            return $this->redirect('/admin/permissoes/perfil/editar?perfil_id=' . (int) $result['id']);
        }
        if ($action === 'save_new') {
            Session::flash('success', 'Papel atualizado com sucesso. Você já pode criar um novo papel.');
            return $this->redirect('/admin/permissoes/perfil/criar');
        }
        Session::flash('success', 'Papel atualizado com sucesso.');
        return $this->redirect('/admin/permissoes');
    }

    public function perfilDestroy(Request $request)
    {
        $perfilId = (int) $request->input('id', 0);
        $justificativa = trim((string) $request->input('justificativa', ''));
        $result = $this->service->excluirPerfil($perfilId, $justificativa, Session::get('usuario_id'), $request->ip(), $request->userAgent());
        if (empty($result['ok'])) {
            Session::flash('errors', array($result['message'] ?? 'Não foi possível excluir o papel.'));
            return $this->redirect('/admin/permissoes');
        }
        Session::flash('success', 'Papel enviado para a lixeira.');
        return $this->redirect('/admin/permissoes');
    }

    public function permissaoForm(Request $request)
    {
        $permissaoId = (int) $request->query('permissao_id', 0);
        return $this->view('admin/permissoes/permissao-form', array(
            'title' => $permissaoId > 0 ? 'Editar permissão' : 'Nova permissão',
            'form_data' => $this->service->dadosPermissao($permissaoId),
            'action_url' => $permissaoId > 0 ? '/admin/permissoes/item/editar' : '/admin/permissoes/item/criar',
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
            'old' => Session::pullFlash('old', array()),
        ));
    }

    public function permissaoStore(Request $request)
    {
        $input = $request->all();
        $action = $this->submitAction($request, 'save_exit');
        if ($action === 'save_copy') {
            $result = $this->service->duplicarPermissao($input, Session::get('usuario_id'), $request->ip(), $request->userAgent());
            if (empty($result['ok'])) {
                Session::flash('errors', $result['errors'] ?? array('Não foi possível criar a cópia da permissão.'));
                Session::flash('old', $input);
                return $this->redirect('/admin/permissoes/item/criar');
            }

            Session::flash('success', 'Cópia da permissão criada com sucesso.');
            return $this->redirect('/admin/permissoes/item/editar?permissao_id=' . (int) $result['id']);
        }

        $result = $this->service->salvarPermissao($input, Session::get('usuario_id'), $request->ip(), $request->userAgent());
        if (empty($result['ok'])) {
            Session::flash('errors', $result['errors'] ?? array('Não foi possível salvar a permissão.'));
            Session::flash('old', $input);
            return $this->redirect('/admin/permissoes/item/criar');
        }
        if ($action === 'save_stay') {
            Session::flash('success', 'Permissão salva com sucesso.');
            return $this->redirect('/admin/permissoes/item/editar?permissao_id=' . (int) $result['id']);
        }
        if ($action === 'save_new') {
            Session::flash('success', 'Permissão salva com sucesso. Você já pode criar uma nova permissão.');
            return $this->redirect('/admin/permissoes/item/criar');
        }
        Session::flash('success', 'Permissão salva com sucesso.');
        return $this->redirect('/admin/permissoes');
    }

    public function permissaoUpdate(Request $request)
    {
        $input = $request->all();
        $permissaoId = (int) $request->input('id', 0);
        $action = $this->submitAction($request, 'save_exit');
        if ($action === 'save_copy') {
            $result = $this->service->duplicarPermissao($input, Session::get('usuario_id'), $request->ip(), $request->userAgent());
            if (empty($result['ok'])) {
                Session::flash('errors', $result['errors'] ?? array('Não foi possível criar a cópia da permissão.'));
                Session::flash('old', $input);
                return $this->redirect('/admin/permissoes/item/editar?permissao_id=' . $permissaoId);
            }

            Session::flash('success', 'Cópia da permissão criada com sucesso.');
            return $this->redirect('/admin/permissoes/item/editar?permissao_id=' . (int) $result['id']);
        }

        $result = $this->service->salvarPermissao($input, Session::get('usuario_id'), $request->ip(), $request->userAgent());
        if (empty($result['ok'])) {
            Session::flash('errors', $result['errors'] ?? array('Não foi possível atualizar a permissão.'));
            Session::flash('old', $input);
            return $this->redirect('/admin/permissoes/item/editar?permissao_id=' . $permissaoId);
        }
        if ($action === 'save_stay') {
            Session::flash('success', 'Permissão atualizada com sucesso.');
            return $this->redirect('/admin/permissoes/item/editar?permissao_id=' . (int) $result['id']);
        }
        if ($action === 'save_new') {
            Session::flash('success', 'Permissão atualizada com sucesso. Você já pode criar uma nova permissão.');
            return $this->redirect('/admin/permissoes/item/criar');
        }
        Session::flash('success', 'Permissão atualizada com sucesso.');
        return $this->redirect('/admin/permissoes');
    }

    public function permissaoDestroy(Request $request)
    {
        $permissaoId = (int) $request->input('id', 0);
        $justificativa = trim((string) $request->input('justificativa', ''));
        $result = $this->service->excluirPermissao($permissaoId, $justificativa, Session::get('usuario_id'), $request->ip(), $request->userAgent());
        if (empty($result['ok'])) {
            Session::flash('errors', array($result['message'] ?? 'Não foi possível excluir a permissão.'));
            return $this->redirect('/admin/permissoes');
        }
        Session::flash('success', 'Permissão enviada para a lixeira.');
        return $this->redirect('/admin/permissoes');
    }
}
