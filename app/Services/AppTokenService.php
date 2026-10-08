<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Models\AppToken;
use App\Support\AppApi\Tempo;
use PDO;

/**
 * Tokens do app do aluno.
 *
 * - Opacos: 32 bytes de random_bytes em base64url. No banco, só o SHA-256.
 * - Access: 1 hora. Refresh: 60 dias, rotativo — cada renovação consome o
 *   refresh usado e emite um novo par na mesma "família" (sessão do login).
 * - Reuso de refresh já consumido = token copiado: revoga TODOS os tokens do
 *   dispositivo e devolve `sessao_revogada`. Exceção (resposta perdida na rede
 *   móvel): mesmo device_id, até 60 s após o consumo e par gerado ainda não usado
 *   → o par não usado é revogado e um novo é emitido (reemitirNaJanela).
 * - Um novo login no mesmo device_id revoga o que existia nele (inclusive de
 *   outra conta), para nunca haver duas sessões ativas no mesmo aparelho.
 *
 * Horários gravados e comparados no PHP (App\Support\AppApi\Tempo), nunca com
 * NOW() do banco: PHP e MySQL da VPS estão em fusos diferentes.
 */
class AppTokenService
{
    const ACCESS_TTL = 3600;
    const REFRESH_TTL = 5184000; // 60 dias
    /** Tolerância para reapresentar um refresh recém-consumido (resposta perdida). */
    const JANELA_REUSO_SEGUNDOS = 60;

    private $tokens;
    private $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
        $this->tokens = new AppToken($pdo);
    }

    private function db()
    {
        return $this->pdo ?: Database::connection();
    }

    public static function gerarToken()
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    public static function hash($token)
    {
        return hash('sha256', (string) $token);
    }

    /**
     * Login: abre uma família nova para o dispositivo e devolve o par.
     *
     * @return array access_token, access_expira_em (ts), refresh_token, refresh_expira_em (ts), familia
     */
    public function emitirParaLogin($usuarioId, $deviceId, $deviceName, $ip, $userAgent)
    {
        $agora = Tempo::agoraTs();
        $this->tokens->revogarDispositivo($deviceId, Tempo::sql($agora), 'novo_login');

        $familia = bin2hex(random_bytes(16));
        return $this->emitirPar((int) $usuarioId, (string) $deviceId, $deviceName, $familia, null, $ip, $userAgent);
    }

    /**
     * Renova com rotação.
     *
     * @return array ok=true + par | ok=false + motivo (invalido|expirado|revogado|reuso|dispositivo_divergente)
     */
    public function renovar($refreshToken, $deviceId, $ip, $userAgent)
    {
        $refreshToken = trim((string) $refreshToken);
        $deviceId = trim((string) $deviceId);
        if ($refreshToken === '' || $deviceId === '') {
            return array('ok' => false, 'motivo' => 'invalido');
        }

        $registro = $this->tokens->findByHash(self::hash($refreshToken));
        if (!$registro || (string) $registro['tipo'] !== 'refresh') {
            return array('ok' => false, 'motivo' => 'invalido');
        }

        $agora = Tempo::agoraTs();
        $agoraSql = Tempo::sql($agora);

        if ((string) $registro['device_id'] !== $deviceId) {
            // Refresh de um aparelho apresentado por outro: trata como vazamento.
            $this->tokens->revogarFamilia((string) $registro['familia'], $agoraSql, 'dispositivo_divergente');
            $this->registrar('app.token.refresh_dispositivo_divergente', $registro, $ip, $userAgent);
            return array('ok' => false, 'motivo' => 'dispositivo_divergente', 'usuario_id' => (int) $registro['usuario_id']);
        }

        if (!empty($registro['consumido_em'])) {
            // Janela de tolerância: resposta da renovação perdida na rede móvel.
            $reemissao = $this->reemitirNaJanela($registro, $agora, $ip, $userAgent);
            if ($reemissao !== null) {
                return $reemissao;
            }

            $revogados = $this->tokens->revogarDispositivo($deviceId, $agoraSql, 'reuso_refresh');
            $this->registrar('app.token.reuso_refresh', $registro, $ip, $userAgent, array('tokens_revogados' => $revogados));
            return array('ok' => false, 'motivo' => 'reuso', 'usuario_id' => (int) $registro['usuario_id']);
        }

        if (!empty($registro['revogado_em'])) {
            return array('ok' => false, 'motivo' => 'revogado', 'usuario_id' => (int) $registro['usuario_id']);
        }

        $expira = Tempo::timestamp($registro['expira_em']);
        if ($expira === null || $expira <= $agora) {
            return array('ok' => false, 'motivo' => 'expirado', 'usuario_id' => (int) $registro['usuario_id']);
        }

        $pdo = $this->db();
        $propria = !$pdo->inTransaction();
        if ($propria) {
            $pdo->beginTransaction();
        }

        try {
            if (!$this->tokens->marcarConsumido((int) $registro['id'], $agoraSql)) {
                // Outra renovação com o mesmo refresh venceu a corrida: é reuso.
                if ($propria) {
                    $pdo->rollBack();
                }
                $this->tokens->revogarDispositivo($deviceId, $agoraSql, 'reuso_refresh');
                $this->registrar('app.token.reuso_refresh', $registro, $ip, $userAgent, array('corrida' => true));
                return array('ok' => false, 'motivo' => 'reuso', 'usuario_id' => (int) $registro['usuario_id']);
            }

            $par = $this->emitirPar(
                (int) $registro['usuario_id'],
                $deviceId,
                $registro['device_name'],
                (string) $registro['familia'],
                (int) $registro['id'],
                $ip,
                $userAgent
            );
            $this->tokens->definirSucessor((int) $registro['id'], (int) $par['refresh_id']);
            $this->tokens->revogarAccessDaFamiliaExceto((string) $registro['familia'], (int) $par['access_id'], $agoraSql, 'rotacao');

            if ($propria) {
                $pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($propria && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        $par['ok'] = true;
        $par['usuario_id'] = (int) $registro['usuario_id'];
        return $par;
    }

    /**
     * Valida um access token.
     *
     * @return array ok=true + token | ok=false + motivo (invalido|expirado|revogado)
     */
    public function validarAccess($accessToken)
    {
        $accessToken = trim((string) $accessToken);
        if ($accessToken === '' || strlen($accessToken) > 200) {
            return array('ok' => false, 'motivo' => 'invalido');
        }

        $registro = $this->tokens->findByHash(self::hash($accessToken));
        if (!$registro || (string) $registro['tipo'] !== 'access') {
            return array('ok' => false, 'motivo' => 'invalido');
        }

        if (!empty($registro['revogado_em'])) {
            return array('ok' => false, 'motivo' => 'revogado', 'token' => $registro);
        }

        $agora = Tempo::agoraTs();
        $expira = Tempo::timestamp($registro['expira_em']);
        if ($expira === null || $expira <= $agora) {
            return array('ok' => false, 'motivo' => 'expirado', 'token' => $registro);
        }

        // Registra uso no máximo a cada 5 minutos, para não escrever a cada chamada.
        $ultimoUso = Tempo::timestamp(isset($registro['ultimo_uso_em']) ? $registro['ultimo_uso_em'] : '');
        if ($ultimoUso === null || ($agora - $ultimoUso) >= 300) {
            try {
                $this->tokens->tocarUso((int) $registro['id'], Tempo::sql($agora));
            } catch (\Throwable $e) {
                // uso é informativo; nunca derruba a requisição
            }
        }

        return array('ok' => true, 'token' => $registro);
    }

    public function revogarDispositivo($deviceId, $motivo = 'logout')
    {
        return $this->tokens->revogarDispositivo((string) $deviceId, Tempo::sql(), $motivo);
    }

    public function revogarOutrosDispositivos($usuarioId, $deviceIdAtual, $motivo = 'troca_senha')
    {
        return $this->tokens->revogarUsuarioExcetoDispositivo((int) $usuarioId, (string) $deviceIdAtual, Tempo::sql(), $motivo);
    }

    /**
     * Rede móvel: a renovação chega ao servidor, a resposta se perde e o app tenta
     * de novo com o refresh que acabou de ser consumido. Isso NÃO é roubo quando:
     *   - o refresh foi consumido há no máximo JANELA_REUSO_SEGUNDOS;
     *   - o pedido vem do mesmo device_id (conferido antes, em renovar());
     *   - o refresh não foi revogado; e
     *   - o par que ele gerou nunca foi usado (nem o access, nem o refresh).
     * Nesse caso o par não usado é revogado e um par novo é emitido na mesma
     * família. Fora disso, devolve null e o chamador trata como reuso.
     *
     * @return array|null resultado de renovar() ou null
     */
    private function reemitirNaJanela(array $registro, $agora, $ip, $userAgent)
    {
        if (!empty($registro['revogado_em'])) {
            return null;
        }
        $consumido = Tempo::timestamp($registro['consumido_em']);
        if ($consumido === null || ($agora - $consumido) > self::JANELA_REUSO_SEGUNDOS || $agora < $consumido) {
            return null;
        }

        $sucessores = $this->tokens->filhosDe((int) $registro['id']);
        if (empty($sucessores)) {
            return null;
        }
        foreach ($sucessores as $sucessor) {
            // Revogado também conta como "não reaproveitável" (ex.: logout no meio).
            if (!empty($sucessor['ultimo_uso_em']) || !empty($sucessor['consumido_em']) || !empty($sucessor['revogado_em'])) {
                return null;
            }
        }

        $agoraSql = Tempo::sql($agora);
        $pdo = $this->db();
        $propria = !$pdo->inTransaction();
        if ($propria) {
            $pdo->beginTransaction();
        }
        try {
            $revogados = 0;
            foreach ($sucessores as $sucessor) {
                $revogados += $this->tokens->revogarSeNaoUsado((int) $sucessor['id'], $agoraSql, 'reemissao_janela');
            }
            if ($revogados !== count($sucessores)) {
                // Outro pedido usou o par entre a leitura e a revogação: é reuso.
                if ($propria) {
                    $pdo->rollBack();
                }
                return null;
            }

            $par = $this->emitirPar(
                (int) $registro['usuario_id'],
                (string) $registro['device_id'],
                $registro['device_name'],
                (string) $registro['familia'],
                (int) $registro['id'],
                $ip,
                $userAgent
            );
            $this->tokens->definirSucessor((int) $registro['id'], (int) $par['refresh_id']);

            if ($propria) {
                $pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($propria && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        Logger::info('app.token.reemissao_janela', array(
            'usuario_id' => (int) $registro['usuario_id'],
            'device_id' => (string) $registro['device_id'],
            'familia' => (string) $registro['familia'],
            'segundos_desde_consumo' => $agora - $consumido,
        ));

        $par['ok'] = true;
        $par['usuario_id'] = (int) $registro['usuario_id'];
        $par['reemitido'] = true;
        return $par;
    }

    private function emitirPar($usuarioId, $deviceId, $deviceName, $familia, $parentId, $ip, $userAgent)
    {
        $agora = Tempo::agoraTs();
        $access = self::gerarToken();
        $refresh = self::gerarToken();
        $accessExpira = $agora + self::ACCESS_TTL;
        $refreshExpira = $agora + self::REFRESH_TTL;

        $base = array(
            'usuario_id' => $usuarioId,
            'device_id' => $deviceId,
            'device_name' => $deviceName !== null ? mb_substr((string) $deviceName, 0, 150) : null,
            'familia' => $familia,
            'parent_id' => $parentId,
            'ip' => $ip !== null ? substr((string) $ip, 0, 45) : null,
            'user_agent' => $userAgent !== null ? mb_substr((string) $userAgent, 0, 255) : null,
            'created_at' => Tempo::sql($agora),
        );

        $accessId = $this->tokens->create(array_merge($base, array(
            'tipo' => 'access',
            'token_hash' => self::hash($access),
            'expira_em' => Tempo::sql($accessExpira),
        )));
        $refreshId = $this->tokens->create(array_merge($base, array(
            'tipo' => 'refresh',
            'token_hash' => self::hash($refresh),
            'expira_em' => Tempo::sql($refreshExpira),
        )));

        return array(
            'access_token' => $access,
            'access_expira_em' => $accessExpira,
            'refresh_token' => $refresh,
            'refresh_expira_em' => $refreshExpira,
            'familia' => $familia,
            'access_id' => $accessId,
            'refresh_id' => $refreshId,
        );
    }

    private function registrar($evento, array $registro, $ip, $userAgent, array $extra = array())
    {
        $contexto = array_merge(array(
            'usuario_id' => (int) $registro['usuario_id'],
            'device_id' => (string) $registro['device_id'],
            'familia' => (string) $registro['familia'],
            'token_id' => (int) $registro['id'],
            'ip_address' => $ip,
        ), $extra);

        try {
            (new AuditService())->record($evento, 'app_token', (int) $registro['id'], $contexto, (int) $registro['usuario_id'], $ip, $userAgent);
        } catch (\Throwable $e) {
            // AuditService já tem fallback próprio; nada a fazer aqui
        }
        Logger::warning($evento, $contexto);
    }
}
