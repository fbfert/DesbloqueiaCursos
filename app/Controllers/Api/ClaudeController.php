<?php

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Services\ClaudeService;

class ClaudeController extends Controller
{
    private $service;

    public function __construct()
    {
        $this->service = new ClaudeService();
    }

    public function testar(Request $request)
    {
        $resultado = $this->service->gerarResposta($request->all(), Session::get('usuario_id'), $request->ip(), $request->userAgent());

        if (empty($resultado['ok'])) {
            return $this->json(array(
                'ok' => false,
                'message' => isset($resultado['message']) ? $resultado['message'] : 'Não foi possível consultar o Claude.',
            ), isset($resultado['status']) ? (int) $resultado['status'] : 500);
        }

        return $this->json(array(
            'ok' => true,
            'model' => isset($resultado['model']) ? $resultado['model'] : null,
            'id' => isset($resultado['id']) ? $resultado['id'] : null,
            'content' => isset($resultado['content']) ? $resultado['content'] : '',
            'usage' => isset($resultado['usage']) ? $resultado['usage'] : array(),
            'stop_reason' => isset($resultado['stop_reason']) ? $resultado['stop_reason'] : null,
        ));
    }
}
