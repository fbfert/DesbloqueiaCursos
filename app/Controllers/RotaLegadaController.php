<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\ConteudoItem;
use App\Models\ConteudoQuizTentativa;
use App\Support\RotaLegada;
use App\Support\V2ErrorPage;

/**
 * Redireciona as rotas GET legadas (visual V1) para o tema caderno
 * (mudança OpenSpec tema-caderno-unico). O mapa mora em App\Support\RotaLegada;
 * aqui só se resolvem os destinos que dependem do banco.
 */
class RotaLegadaController extends Controller
{
    public function redirecionar(Request $request)
    {
        $path = (string) $request->path();
        $query = $request->queryAll();
        $destino = RotaLegada::destino($path, is_array($query) ? $query : array());

        if ($destino === null) {
            return V2ErrorPage::notFound($path);
        }

        if (isset($destino['url'])) {
            return Response::redirect($destino['url'], (int) $destino['status']);
        }

        if ($destino['resolver'] === 'conteudo') {
            return Response::redirect($this->urlConteudo($destino['contexto'], (int) $destino['conteudo_id']), 302);
        }

        return $this->resultadoQuiz($path, (int) $destino['tentativa_id']);
    }

    private function urlConteudo(array $ctx, int $conteudoId): string
    {
        $item = $conteudoId > 0 ? (new ConteudoItem())->findById($conteudoId) : null;
        if ($item && (int) ($ctx['modulo_id'] ?? 0) <= 0) {
            $ctx['modulo_id'] = (int) $item['modulo_id'];
        }

        // Item inexistente vai para a aula, que informa em texto que o conteúdo não está disponível.
        return RotaLegada::urlEstudo($item ? (string) $item['tipo'] : '', $ctx, $conteudoId);
    }

    private function resultadoQuiz(string $path, int $tentativaId)
    {
        $contexto = $tentativaId > 0
            ? (new ConteudoQuizTentativa())->contextoDoAluno($tentativaId, (int) Session::get('usuario_id'))
            : null;

        if (!$contexto) {
            return V2ErrorPage::notFound($path);
        }

        $url = RotaLegada::urlEstudo('quiz', array(
            'inscricao_id' => (int) $contexto['inscricao_id'],
            'curso_id' => (int) $contexto['curso_evento_id'],
            'turma_id' => (int) $contexto['turma_id'],
            'modulo_id' => (int) $contexto['modulo_id'],
        ), (int) $contexto['item_id']);

        $url .= (strpos($url, '?') === false ? '?' : '&') . 'tentativa_id=' . $tentativaId;

        return Response::redirect($url, 302);
    }
}
