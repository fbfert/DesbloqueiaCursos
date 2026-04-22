<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Services\TurmaService;

class TurmasController extends Controller
{
    private $turmaService;

    public function __construct()
    {
        $this->turmaService = new TurmaService();
    }

    public function index(Request $request)
    {
        return $this->view('admin/turmas/index', array_merge(
            array(
                'title' => 'Turmas',
                'success' => Session::pullFlash('success'),
                'errors' => Session::pullFlash('errors', array()),
            ),
            $this->turmaService->listAdmin()
        ));
    }
}
