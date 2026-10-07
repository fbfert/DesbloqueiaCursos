<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Env;
use App\Core\Logger;
use App\Models\AppDispositivo;
use App\Models\AppNotificacao;
use App\Support\AppApi\Tempo;
use PDO;

/**
 * Notificações push do app do aluno — Firebase Cloud Messaging HTTP v1.
 *
 * REGRAS
 * - Toda notificação é GRAVADA em app_notificacoes antes de qualquer envio: o
 *   histórico do app não depende do FCM.
 * - O envio é "melhor esforço" com timeout curto (conexão 2 s, total 4 s).
 * - NUNCA lança exceção para quem chamou: a aprovação de um pedido não pode
 *   falhar porque o Google não respondeu. Tudo é capturado e vai para o log.
 * - Token morto (UNREGISTERED / INVALID_ARGUMENT) desativa o dispositivo.
 * - Desligado por padrão (FCM_ENABLED=false): grava e deixa pendente; o cron
 *   (scripts/push_reenviar_pendentes.php) envia quando for ligado.
 *
 * AUTENTICAÇÃO NO GOOGLE (sem biblioteca)
 * JWT RS256 assinado com openssl_sign usando a conta de serviço
 * (FCM_SERVICE_ACCOUNT_PATH), trocado por um access token OAuth2 que fica em
 * cache em storage/cache até 1 minuto antes de vencer.
 *
 * TRANSPORTE INJETÁVEL
 * O construtor aceita um callable ($metodo, $url, array $cabecalhos, $corpo)
 * → ['status' => int, 'corpo' => string, 'erro' => ?string], usado pelos testes.
 */
class PushService
{
    const ESCOPO_FCM = 'https://www.googleapis.com/auth/firebase.messaging';
    const TOKEN_URI_PADRAO = 'https://oauth2.googleapis.com/token';
    const MAX_TENTATIVAS = 5;

    private $pdo;
    private $transporte;
    private $config;
    private $notificacoes;
    private $dispositivos;

    public function __construct(?PDO $pdo = null, ?callable $transporte = null, ?array $config = null)
    {
        $this->pdo = $pdo;
        $this->transporte = $transporte;
        $this->config = $config !== null ? $config : array(
            'habilitado' => strtolower(trim((string) Env::get('FCM_ENABLED', 'false'))) === 'true',
            'projeto' => trim((string) Env::get('FCM_PROJECT_ID', '')),
            'conta_servico' => trim((string) Env::get('FCM_SERVICE_ACCOUNT_PATH', '')),
            'cache_dir' => BASE_PATH . '/storage/cache',
        );
        $this->notificacoes = new AppNotificacao($pdo);
        $this->dispositivos = new AppDispositivo($pdo);
    }

    public function habilitado()
    {
        return !empty($this->config['habilitado']);
    }

    /**
     * Grava a notificação e tenta enviar para os aparelhos ativos do usuário.
     *
     * @return int|null id da notificação gravada (null se nem a gravação foi possível)
     */
    public function notificar($usuarioId, $tipo, $titulo, $corpo, array $dados = array())
    {
        try {
            $usuarioId = (int) $usuarioId;
            if ($usuarioId <= 0) {
                return null;
            }

            $id = $this->notificacoes->create($usuarioId, $tipo, $titulo, $corpo, $this->normalizarDados($dados), Tempo::sql());
            $this->enviarNotificacao($id);

            return $id;
        } catch (\Throwable $e) {
            Logger::error('push.notificar_falhou', array(
                'usuario_id' => (int) $usuarioId,
                'tipo' => (string) $tipo,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ));
            return null;
        }
    }

    /**
     * Vários destinatários (ex.: conteúdo novo para a turma inteira): só grava como
     * pendente — o envio fica para o cron, para não prender a requisição do admin.
     *
     * @param array $destinos usuario_id => dados extras daquele destinatário (ex.: inscricao_id)
     * @param array $dados    dados comuns a todos
     *
     * @return int quantidade gravada
     */
    public function enfileirar(array $destinos, $tipo, $titulo, $corpo, array $dados = array())
    {
        try {
            $porUsuario = array();
            foreach ($destinos as $usuarioId => $extras) {
                $porUsuario[(int) $usuarioId] = $this->normalizarDados(array_merge($dados, is_array($extras) ? $extras : array()));
            }
            return $this->notificacoes->criarEmLote($porUsuario, $tipo, $titulo, $corpo, Tempo::sql());
        } catch (\Throwable $e) {
            Logger::error('push.enfileirar_falhou', array(
                'tipo' => (string) $tipo,
                'destinatarios' => count($destinos),
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ));
            return 0;
        }
    }

    /**
     * Envia (ou reenvia) uma notificação já gravada. Não lança exceção.
     *
     * @return string status final (enviado|falhou|sem_dispositivo|pendente)
     */
    public function enviarNotificacao($notificacaoId)
    {
        try {
            $notificacao = $this->notificacoes->findById((int) $notificacaoId);
            if (!$notificacao) {
                return 'falhou';
            }

            if (!$this->habilitado()) {
                return 'pendente';
            }

            $dispositivos = $this->dispositivos->ativosDoUsuario((int) $notificacao['usuario_id']);
            if (empty($dispositivos)) {
                $this->notificacoes->atualizarEnvio((int) $notificacao['id'], 'sem_dispositivo', null, Tempo::sql());
                return 'sem_dispositivo';
            }

            $accessToken = $this->accessToken();
            if ($accessToken === null) {
                $this->notificacoes->atualizarEnvio((int) $notificacao['id'], 'falhou', 'oauth_indisponivel', Tempo::sql());
                return 'falhou';
            }

            $enviados = 0;
            $erros = array();
            foreach ($dispositivos as $dispositivo) {
                $resultado = $this->enviarParaDispositivo($accessToken, $dispositivo, $notificacao);
                if ($resultado['ok']) {
                    $enviados++;
                    $this->dispositivos->registrarEnvio((int) $dispositivo['id'], Tempo::sql());
                    continue;
                }
                $erros[] = $resultado['erro'];
                if (!empty($resultado['token_invalido'])) {
                    $this->dispositivos->desativar((int) $dispositivo['id'], $resultado['erro'], Tempo::sql());
                }
            }

            $status = $enviados > 0 ? 'enviado' : 'falhou';
            $this->notificacoes->atualizarEnvio(
                (int) $notificacao['id'],
                $status,
                empty($erros) ? null : implode('; ', array_unique($erros)),
                Tempo::sql()
            );

            if ($status === 'falhou') {
                Logger::warning('push.envio_falhou', array('notificacao_id' => (int) $notificacao['id'], 'erros' => $erros));
            }

            return $status;
        } catch (\Throwable $e) {
            Logger::error('push.envio_excecao', array(
                'notificacao_id' => (int) $notificacaoId,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ));
            try {
                $this->notificacoes->atualizarEnvio((int) $notificacaoId, 'falhou', 'excecao: ' . get_class($e), Tempo::sql());
            } catch (\Throwable $ignorado) {
                // nada a fazer
            }
            return 'falhou';
        }
    }

    /**
     * Cron: envia pendentes e reenvia falhas recentes.
     *
     * @return array processadas, enviadas, falhas, sem_dispositivo, expiradas
     */
    public function reenviarPendentes($limite = 200, $horasValidade = 48)
    {
        $resumo = array('processadas' => 0, 'enviadas' => 0, 'falhas' => 0, 'sem_dispositivo' => 0, 'expiradas' => 0, 'habilitado' => $this->habilitado());
        if (!$this->habilitado()) {
            return $resumo;
        }

        $limiteValidade = Tempo::sql(Tempo::agoraTs() - ((int) $horasValidade * 3600));
        $resumo['expiradas'] = $this->notificacoes->expirarPendentes($limiteValidade);

        foreach ($this->notificacoes->paraReenvio(self::MAX_TENTATIVAS, $limiteValidade, $limite) as $notificacao) {
            $resumo['processadas']++;
            $status = $this->enviarNotificacao((int) $notificacao['id']);
            if ($status === 'enviado') {
                $resumo['enviadas']++;
            } elseif ($status === 'sem_dispositivo') {
                $resumo['sem_dispositivo']++;
            } else {
                $resumo['falhas']++;
            }
        }

        return $resumo;
    }

    /** Mensagem FCM v1 (`data` message: o app monta a notificação). */
    public function montarMensagem($fcmToken, array $notificacao)
    {
        $dados = array();
        $extras = json_decode((string) ($notificacao['dados'] ?? ''), true);
        if (is_array($extras)) {
            foreach ($extras as $chave => $valor) {
                if ($valor === null || is_array($valor)) {
                    continue;
                }
                $dados[(string) $chave] = (string) $valor;
            }
        }
        $dados['tipo'] = (string) $notificacao['tipo'];
        $dados['titulo'] = (string) $notificacao['titulo'];
        $dados['corpo'] = (string) $notificacao['corpo'];
        $dados['notificacao_id'] = (string) $notificacao['id'];

        return array(
            'message' => array(
                'token' => (string) $fcmToken,
                'data' => $dados,
                'android' => array('priority' => 'HIGH'),
            ),
        );
    }

    /** JWT RS256 da conta de serviço (assertion do fluxo OAuth2 jwt-bearer). */
    public static function montarJwt(array $contaServico, $agora)
    {
        $cabecalho = array('alg' => 'RS256', 'typ' => 'JWT');
        if (!empty($contaServico['private_key_id'])) {
            $cabecalho['kid'] = (string) $contaServico['private_key_id'];
        }
        $claims = array(
            'iss' => (string) $contaServico['client_email'],
            'scope' => self::ESCOPO_FCM,
            'aud' => !empty($contaServico['token_uri']) ? (string) $contaServico['token_uri'] : self::TOKEN_URI_PADRAO,
            'iat' => (int) $agora,
            'exp' => (int) $agora + 3600,
        );

        $entrada = self::base64url(json_encode($cabecalho)) . '.' . self::base64url(json_encode($claims));
        $chave = openssl_pkey_get_private((string) $contaServico['private_key']);
        if ($chave === false) {
            throw new \RuntimeException('Chave privada da conta de serviço inválida.');
        }
        $assinatura = '';
        if (!openssl_sign($entrada, $assinatura, $chave, OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('Falha ao assinar o JWT da conta de serviço.');
        }

        return $entrada . '.' . self::base64url($assinatura);
    }

    public static function base64url($valor)
    {
        return rtrim(strtr(base64_encode((string) $valor), '+/', '-_'), '=');
    }

    /** Access token OAuth2 (cache em arquivo até 60 s antes de vencer). */
    private function accessToken()
    {
        $cache = rtrim((string) ($this->config['cache_dir'] ?? ''), '/\\') . '/fcm_access_token.json';
        $agora = Tempo::agoraTs();

        if (is_file($cache)) {
            $salvo = json_decode((string) @file_get_contents($cache), true);
            if (is_array($salvo) && !empty($salvo['access_token']) && (int) ($salvo['expira_em'] ?? 0) > $agora + 60) {
                return (string) $salvo['access_token'];
            }
        }

        $contaServico = $this->contaServico();
        if ($contaServico === null) {
            return null;
        }

        try {
            $jwt = self::montarJwt($contaServico, $agora);
        } catch (\Throwable $e) {
            Logger::error('push.jwt_falhou', array('message' => $e->getMessage()));
            return null;
        }

        $resposta = $this->http(
            'POST',
            !empty($contaServico['token_uri']) ? (string) $contaServico['token_uri'] : self::TOKEN_URI_PADRAO,
            array('Content-Type: application/x-www-form-urlencoded'),
            http_build_query(array(
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ))
        );

        $json = json_decode((string) $resposta['corpo'], true);
        if ((int) $resposta['status'] !== 200 || !is_array($json) || empty($json['access_token'])) {
            Logger::error('push.oauth_falhou', array(
                'status' => (int) $resposta['status'],
                'erro' => $resposta['erro'],
                'resposta' => is_array($json) && isset($json['error']) ? $json['error'] : null,
            ));
            return null;
        }

        $expiraEm = $agora + max(60, (int) ($json['expires_in'] ?? 3600));
        if (!empty($this->config['cache_dir']) && (is_dir($this->config['cache_dir']) || @mkdir($this->config['cache_dir'], 0775, true))) {
            @file_put_contents($cache, json_encode(array('access_token' => $json['access_token'], 'expira_em' => $expiraEm)), LOCK_EX);
            @chmod($cache, 0600);
        }

        return (string) $json['access_token'];
    }

    private function contaServico()
    {
        $caminho = (string) ($this->config['conta_servico'] ?? '');
        if ($caminho === '' || !is_readable($caminho)) {
            Logger::error('push.conta_servico_ausente', array('configurado' => $caminho !== ''));
            return null;
        }
        $json = json_decode((string) file_get_contents($caminho), true);
        if (!is_array($json) || empty($json['client_email']) || empty($json['private_key'])) {
            Logger::error('push.conta_servico_invalida', array());
            return null;
        }

        return $json;
    }

    private function enviarParaDispositivo($accessToken, array $dispositivo, array $notificacao)
    {
        $projeto = (string) ($this->config['projeto'] ?? '');
        if ($projeto === '') {
            return array('ok' => false, 'erro' => 'projeto_nao_configurado');
        }

        $resposta = $this->http(
            'POST',
            'https://fcm.googleapis.com/v1/projects/' . rawurlencode($projeto) . '/messages:send',
            array('Authorization: Bearer ' . $accessToken, 'Content-Type: application/json; charset=utf-8'),
            json_encode($this->montarMensagem($dispositivo['fcm_token'], $notificacao), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        if ((int) $resposta['status'] === 200) {
            return array('ok' => true);
        }

        $json = json_decode((string) $resposta['corpo'], true);
        $codigo = '';
        if (is_array($json) && isset($json['error'])) {
            $codigo = (string) ($json['error']['status'] ?? '');
            foreach ((array) ($json['error']['details'] ?? array()) as $detalhe) {
                if (is_array($detalhe) && !empty($detalhe['errorCode'])) {
                    $codigo = (string) $detalhe['errorCode'];
                    break;
                }
            }
        }
        if ($codigo === '') {
            $codigo = $resposta['erro'] !== null ? 'transporte: ' . $resposta['erro'] : 'http_' . (int) $resposta['status'];
        }

        if ((int) $resposta['status'] === 401) {
            // Token OAuth recusado: descarta o cache para a próxima tentativa.
            @unlink(rtrim((string) ($this->config['cache_dir'] ?? ''), '/\\') . '/fcm_access_token.json');
        }

        return array(
            'ok' => false,
            'erro' => $codigo,
            'token_invalido' => in_array($codigo, array('UNREGISTERED', 'INVALID_ARGUMENT'), true),
        );
    }

    private function http($metodo, $url, array $cabecalhos, $corpo)
    {
        if ($this->transporte !== null) {
            $r = call_user_func($this->transporte, $metodo, $url, $cabecalhos, $corpo);
            return array(
                'status' => (int) ($r['status'] ?? 0),
                'corpo' => (string) ($r['corpo'] ?? ''),
                'erro' => isset($r['erro']) ? $r['erro'] : null,
            );
        }

        if (!function_exists('curl_init')) {
            return array('status' => 0, 'corpo' => '', 'erro' => 'curl_indisponivel');
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_CUSTOMREQUEST => $metodo,
            CURLOPT_POSTFIELDS => $corpo,
            CURLOPT_HTTPHEADER => $cabecalhos,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT => 4,
            CURLOPT_SSL_VERIFYPEER => true,
        ));
        $resposta = curl_exec($ch);
        $erro = $resposta === false ? curl_error($ch) : null;
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return array('status' => $status, 'corpo' => $resposta === false ? '' : (string) $resposta, 'erro' => $erro);
    }

    private function normalizarDados(array $dados)
    {
        $limpo = array();
        foreach ($dados as $chave => $valor) {
            if ($valor === null || $valor === '' || is_array($valor) || is_object($valor)) {
                continue;
            }
            $limpo[(string) $chave] = (string) $valor;
        }

        return $limpo;
    }
}
