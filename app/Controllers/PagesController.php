<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;

class PagesController extends Controller
{
    public function comoFunciona(Request $request)
    {
        return $this->view('pages/como-funciona', array(
            'title' => 'Como funciona',
            'success' => Session::pullFlash('success'),
        ));
    }

    public function sobre(Request $request)
    {
        return $this->view('pages/sobre', array(
            'title' => 'Sobre',
            'success' => Session::pullFlash('success'),
        ));
    }

    public function contato(Request $request)
    {
        return $this->view('pages/contato', array(
            'title' => 'Contato',
            'success' => Session::pullFlash('success'),
        ));
    }
}
