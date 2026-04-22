<?php

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Request;

class HealthController extends Controller
{
    public function show(Request $request)
    {
        return $this->json(array(
            'status' => 'ok',
            'app' => 'polo_rainbow',
            'timestamp' => date('c'),
        ));
    }
}
