<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;

class HomeController extends Controller
{
    public function index(Request $request)
    {
        return $this->view('home', array(
            'title' => 'Polo Rainbow',
            'success' => Session::pullFlash('success'),
            'usuarioNome' => Session::get('usuario_nome'),
        ));
    }
}
