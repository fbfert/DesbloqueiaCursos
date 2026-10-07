<?php

namespace App\Middleware;

use App\Core\Logger;
use App\Core\Request;
use App\Models\Usuario;
use App\Services\AppTokenService;
use App\Services\AuditService;
use App\Support\AppApi\Resposta;
use App\Support\AppApi\Versao;
use App\Support\AppAuth;

/**
 * Autenticação do app do aluno (`auth.app`).
 *
 * - Versão mínima: build de `X-App-Version` abaixo do mínimo → 426.
 * - `Authorization: Bearer <access_token>` validado por AppTokenService.
 *   Vencido → 401 token_expirado (o app renova e repete uma vez);
 *   ausente/desconhecido/revogado → 401 nao_autenticado.
 * - Usuário precisa continuar existindo e com status `ativo`.
 *
 * A identidade vai para App\Support\AppAuth (memória desta requisição). Nada de
 * sessão PHP: identidade nunca vem de corpo, query ou cookie.
 */
class AppAuthenticateMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next)
    {
        AppAuth::limpar();

        if (Versao::exigeAtualizacao($request->header('X-App-Version'))) {
            return Resposta::erro('atualizacao_obrigatoria', null, 426);
        }

        $token = self::bearer($request);
        if ($token === null) {
            $this->negar($request, 'seguranca.app.token_ausente', null);
            return Resposta::erro('nao_autenticado', null, 401);
        }

        $resultado = (new AppTokenService())->validarAccess($token);
        if (empty($resultado['ok'])) {
            $motivo = isset($resultado['motivo']) ? $resultado['motivo'] : 'invalido';
            if ($motivo === 'expirado') {
                return Resposta::erro('token_expirado', null, 401);
            }
            $this->negar($request, 'seguranca.app.token_invalido', isset($resultado['token']) ? $resultado['token'] : null, $motivo);
            return Resposta::erro('nao_autenticado', null, 401);
        }

        $registro = $resultado['token'];
        $usuario = (new Usuario())->findById((int) $registro['usuario_id']);
        if (!$usuario || (string) ($usuario['status'] ?? '') !== 'ativo') {
            (new AppTokenService())->revogarDispositivo((string) $registro['device_id'], 'usuario_inativo');
            $this->negar($request, 'seguranca.app.usuario_inativo', $registro);
            return Resposta::erro('nao_autenticado', null, 401);
        }

        AppAuth::definir((int) $registro['usuario_id'], $registro);

        try {
            return $next();
        } finally {
            AppAuth::limpar();
        }
    }

    /** Token do cabeçalho Authorization (Apache/FPM podem entregá-lo em variáveis diferentes). */
    public static function bearer(Request $request)
    {
        $server = $request->server();
        $valor = '';
        foreach (array('HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION', 'Authorization') as $chave) {
            if (!empty($server[$chave])) {
                $valor = (string) $server[$chave];
                break;
            }
        }
        if ($valor === '' && function_exists('apache_request_headers')) {
            $cabecalhos = apache_request_headers();
            foreach ((array) $cabecalhos as $nome => $conteudo) {
                if (strcasecmp((string) $nome, 'Authorization') === 0) {
                    $valor = (string) $conteudo;
                    break;
                }
            }
        }

        if (!preg_match('/^\s*Bearer\s+([A-Za-z0-9._~+\/=-]{20,200})\s*$/', $valor, $m)) {
            return null;
        }

        return $m[1];
    }

    private function negar(Request $request, $evento, ?array $token = null, $motivo = null)
    {
        $contexto = array(
            'path' => $request->path(),
            'method' => $request->method(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'motivo' => $motivo,
            'device_id' => $token ? (string) $token['device_id'] : null,
        );
        $usuarioId = $token ? (int) $token['usuario_id'] : null;

        try {
            (new AuditService())->record($evento, 'request', null, $contexto, $usuarioId, $request->ip(), $request->userAgent());
        } catch (\Throwable $e) {
            // AuditService tem fallback próprio
        }
        Logger::error($evento, $contexto);
    }
}
