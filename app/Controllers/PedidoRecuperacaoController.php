<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\PedidoRecuperacaoService;
use App\Support\TemaPublico;

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

        // Tema caderno (tema-caderno-unico), na mesma URL dos e-mails já enviados.
        return new Response(View::render(TemaPublico::view('descadastro'), array(
            'pageTitle' => 'Lembretes de pedido — Desbloqueia Cursos',
            'ok' => !empty($resultado['ok']),
        ), false));
    }
}
