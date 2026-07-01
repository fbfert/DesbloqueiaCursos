<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Services\PedidoRecuperacaoService;

class PedidoRecuperacaoController extends Controller
{
    private $pedidoRecuperacaoService;

    public function __construct()
    {
        $this->pedidoRecuperacaoService = new PedidoRecuperacaoService();
    }

    public function descadastrar(Request $request)
    {
        $token = trim((string) $request->query('token', ''));
        $resultado = $this->pedidoRecuperacaoService->confirmarDescadastro($token);

        return $this->view('pedido-recuperacao/descadastrar', array(
            'title' => 'Descadastro de lembretes de recuperação',
            'ok' => !empty($resultado['ok']),
            'message' => isset($resultado['message']) ? $resultado['message'] : null,
        ));
    }
}
