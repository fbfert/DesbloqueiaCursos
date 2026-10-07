<?php

namespace App\Controllers\Api\App;

use App\Core\Controller;
use App\Core\Request;
use App\Services\ConteudoAcessoAlunoService;
use App\Support\AppApi\Resposta;
use App\Support\AppAuth;

/**
 * Base dos controllers da API do app (/api/app/v1). Controllers finos: leem o
 * Request, chamam Services e devolvem JSON pelo formato do contrato.
 */
abstract class AppController extends Controller
{
    protected function usuarioId()
    {
        return (int) AppAuth::usuarioId();
    }

    protected function ok($dados, $status = 200, ?array $meta = null)
    {
        return Resposta::ok($dados, $status, $meta);
    }

    protected function erro($codigo, $mensagem = null, $status = 400, ?array $campos = null, array $cabecalhos = array())
    {
        return Resposta::erro($codigo, $mensagem, $status, $campos, $cabecalhos);
    }

    protected function texto(Request $request, $campo, $limite = 500)
    {
        $valor = $request->input($campo, '');
        if (!is_scalar($valor)) {
            return '';
        }
        return mb_substr(trim((string) $valor), 0, $limite);
    }

    /** IP do socket (REMOTE_ADDR): o X-Forwarded-For é do cliente e não serve para limite de taxa. */
    protected function ipReal(Request $request)
    {
        $server = $request->server();
        return isset($server['REMOTE_ADDR']) ? (string) $server['REMOTE_ADDR'] : (string) $request->ip();
    }

    /**
     * Inscrição do caminho, conferida contra o usuário do token (exatamente a
     * pedida; inscrição de outro aluno ou sem acesso → null).
     *
     * @return array|null resultado de ConteudoAcessoAlunoService::carregarContexto (ok=true)
     */
    protected function inscricaoDoAluno(Request $request, ?ConteudoAcessoAlunoService $acesso = null)
    {
        $inscricaoId = (int) $request->route('id', 0);
        if ($inscricaoId <= 0) {
            return null;
        }
        $acesso = $acesso ?: new ConteudoAcessoAlunoService();
        $contexto = $acesso->carregarContexto($this->usuarioId(), array('inscricao_id' => $inscricaoId), true);

        return !empty($contexto['ok']) ? $contexto : null;
    }

    protected function semAcesso()
    {
        return $this->erro('sem_acesso', 'Você não tem acesso a este curso.', 403);
    }

    protected function naoEncontrado($mensagem = 'Não encontrado.')
    {
        return $this->erro('nao_encontrado', $mensagem, 404);
    }
}
