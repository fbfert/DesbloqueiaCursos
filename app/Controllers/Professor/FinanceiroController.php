<?php

namespace App\Controllers\Professor;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\FinanceiroService;

class FinanceiroController extends Controller
{
    private $financeiroService;

    public function __construct()
    {
        $this->financeiroService = new FinanceiroService();
    }

    public function index(Request $request)
    {
        return $this->view('professor/financeiro/index', array_merge(
            array(
                'title' => 'Meu financeiro',
                'success' => Session::pullFlash('success'),
                'errors' => Session::pullFlash('errors', array()),
            ),
            $this->financeiroService->painelProfessor(Session::get('usuario_id'))
        ));
    }

    public function rpa(Request $request)
    {
        $result = $this->financeiroService->espelhoRpaDoProfessor(
            (int) $request->query('id', 0),
            Session::get('usuario_id'),
            $request->ip(),
            $request->userAgent()
        );

        if (empty($result['ok'])) {
            $status = isset($result['status']) ? (int) $result['status'] : 404;
            $view = $status === 403 ? 'errors/403' : 'errors/404';
            $titulo = $status === 403 ? 'Acesso negado' : 'Espelho de RPA não encontrado';

            return new Response(View::render($view, array('title' => $titulo)), $status);
        }

        $arquivo = $result['arquivo'];

        return new Response(file_get_contents($arquivo['caminho']), 200, array(
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="' . str_replace('"', '', basename($arquivo['nome'])) . '"',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'",
        ));
    }
}
