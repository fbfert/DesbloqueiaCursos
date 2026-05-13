<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Services\AvisoService;

class AvisosController extends Controller
{
    private $service;

    public function __construct()
    {
        $this->service = new AvisoService();
    }

    public function ocultar(Request $request)
    {
        $avisoId = (int) $request->input('aviso_id', 0);
        $result = $this->service->ocultar($avisoId, Session::get('usuario_id'), $request->ip(), $request->userAgent());

        if (empty($result['ok'])) {
            Session::flash('errors', array(isset($result['message']) ? $result['message'] : 'Não foi possível ocultar o aviso.'));
        }

        $redirectTo = trim((string) $request->input('redirect_to', ''));
        if ($redirectTo === '' && !empty($_SERVER['HTTP_REFERER'])) {
            $redirectTo = (string) $_SERVER['HTTP_REFERER'];
        }

        if ($redirectTo === '' || strpos($redirectTo, '/') !== 0 || strpos($redirectTo, '//') === 0) {
            $redirectTo = '/meus-cursos';
        }

        return $this->redirect($redirectTo);
    }
}
