<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Services\RbacService;

class RbacController extends Controller
{
    private $rbacService;

    public function __construct()
    {
        $this->rbacService = new RbacService();
    }

    public function index(Request $request)
    {
        return $this->view('admin/rbac/index', array(
            'title' => 'RBAC',
            'profiles' => $this->rbacService->listProfiles(),
            'permissions' => $this->rbacService->listPermissions(),
            'errors' => Session::pullFlash('errors', array()),
            'success' => Session::pullFlash('success'),
        ));
    }

    public function syncProfilePermissions(Request $request)
    {
        $perfilId = (int) $request->input('perfil_id');
        $permissaoIds = (array) $request->input('permissao_ids', array());

        $result = $this->rbacService->syncProfilePermissions(
            $perfilId,
            $permissaoIds,
            Session::get('usuario_id'),
            array(
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            )
        );

        if (!$result['ok']) {
            Session::flash('errors', $result['errors']);
            return $this->redirect('/admin/rbac');
        }

        Session::flash('success', 'Permissoes do perfil atualizadas.');
        return $this->redirect('/admin/rbac');
    }

    public function syncUserProfiles(Request $request)
    {
        $usuarioId = (int) $request->input('usuario_id');
        $perfilIds = (array) $request->input('perfil_ids', array());

        $result = $this->rbacService->syncUserProfiles(
            $usuarioId,
            $perfilIds,
            Session::get('usuario_id'),
            array(
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            )
        );

        if (!$result['ok']) {
            Session::flash('errors', $result['errors']);
            return $this->redirect('/admin/rbac');
        }

        Session::flash('success', 'Perfis do usuario atualizados.');
        return $this->redirect('/admin/rbac');
    }
}
