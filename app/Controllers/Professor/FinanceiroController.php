<?php

namespace App\Controllers\Professor;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
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
}
