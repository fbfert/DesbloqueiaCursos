<?php

namespace App\Controllers\Api\App;

use App\Core\Request;
use App\Models\AppNotificacao;
use App\Support\AppApi\CatalogoPresenter;
use App\Support\AppApi\Formato;
use App\Support\AppApi\Tempo;

/**
 * Histórico de notificações do aluno (paginado) e marcação de lida.
 */
class NotificacoesController extends AppController
{
    public function index(Request $request)
    {
        list($pagina, $porPagina, $offset) = Formato::paginacao($request->query('pagina', 1), $request->query('por_pagina', 20));
        $modelo = new AppNotificacao();

        $saida = array();
        foreach ($modelo->listarDoUsuario($this->usuarioId(), $porPagina, $offset) as $notificacao) {
            $saida[] = CatalogoPresenter::notificacao($notificacao);
        }

        return $this->ok($saida, 200, array(
            'pagina' => $pagina,
            'por_pagina' => $porPagina,
            'total' => $modelo->contarDoUsuario($this->usuarioId()),
        ));
    }

    public function lida(Request $request)
    {
        $modelo = new AppNotificacao();
        $notificacao = $modelo->findById((int) $request->route('id', 0));
        if (!$notificacao || (int) $notificacao['usuario_id'] !== $this->usuarioId()) {
            return $this->naoEncontrado('Notificação não encontrada.');
        }

        $modelo->marcarLida((int) $notificacao['id'], $this->usuarioId(), Tempo::sql());

        return $this->ok(array('ok' => true));
    }
}
