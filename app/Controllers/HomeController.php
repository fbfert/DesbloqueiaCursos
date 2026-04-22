<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;

class HomeController extends Controller
{
    public function index(Request $request)
    {
        return $this->view('home', array(
            'title' => 'Polo Rainbow',
        ));
    }
}
