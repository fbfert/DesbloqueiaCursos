<?php

namespace App\Services\Google;

use App\Core\Logger;

/**
 * Valida o id_token (JWT RS256) emitido pelo Google, sem biblioteca externa.
 *
 * Aceita o token só se: assinatura confere com uma chave pública vigente do Google
 * (JWKS), `iss` é o Google, `aud` está na lista do canal (site ou app), `exp` não
 * passou (tolerância de 60 s), `iat` não está no futuro e, quando esperado, o
 * `nonce` coincide.
 *
 * CHAVES PÚBLICAS EM CACHE
 *
 * O JWKS fica em storage/cache/google_jwks.json pelo max-age do Cache-Control do
 * Google (entre 1 h e 24 h). Um `kid` desconhecido força nova busca — o Google roda
 * as chaves —, no máximo uma por minuto, para um token forjado com `kid` aleatório
 * não virar amplificador de requisições.
 *
 * No site o token chega direto do Google por TLS e, pela especificação OIDC, até
 * poderia dispensar a assinatura; verificamos assim mesmo, para haver um único
 * caminho, testado, nos dois canais.
 */
class GoogleIdTokenVerifier
{
    const URL_JWKS = 'https://www.googleapis.com/oauth2/v3/certs';
    const EMISSORES = array('accounts.google.com', 'https://accounts.google.com');
    const TOLERANCIA_SEGUNDOS = 60;
    const CACHE_MIN_SEGUNDOS = 3600;
    const CACHE_MAX_SEGUNDOS = 86400;
    const INTERVALO_MIN_BUSCA = 60;

    private $buscarJwks;
    private $relogio;
    private $arquivoCache;

    /**
     * @param callable|null $buscarJwks function () → array('ok' => bool, 'chaves' => array, 'max_age' => ?int)
     * @param callable|null $relogio    function () → int (timestamp)
     * @param string|null   $arquivoCache caminho do cache do JWKS
     */
    public function __construct(?callable $buscarJwks = null, ?callable $relogio = null, $arquivoCache = null)
    {
        $this->buscarJwks = $buscarJwks ?: array($this, 'buscarJwksPadrao');
        $this->relogio = $relogio ?: function () {
            return time();
        };
        if ($arquivoCache === null) {
            $storage = require BASE_PATH . '/config/storage.php';
            // Chaves do "Google de teste" nunca dividem o cache com as reais.
            $nome = (string) GoogleConfig::get('jwks_arquivo_teste', '') !== '' ? 'google_jwks_teste.json' : 'google_jwks.json';
            $arquivoCache = rtrim($storage['cache'], '/\\') . '/' . $nome;
        }
        $this->arquivoCache = $arquivoCache;
    }

    /**
     * @param string[]    $audiencias client IDs aceitos neste canal
     * @return array ok => true, claims => array | ok => false, motivo => string
     */
    public function verificar($idToken, array $audiencias, $nonceEsperado = null)
    {
        $partes = explode('.', (string) $idToken);
        if (count($partes) !== 3) {
            return $this->falha('formato');
        }

        $cabecalho = json_decode((string) self::base64UrlDecode($partes[0]), true);
        $claims = json_decode((string) self::base64UrlDecode($partes[1]), true);
        $assinatura = self::base64UrlDecode($partes[2]);
        if (!is_array($cabecalho) || !is_array($claims) || $assinatura === false || $assinatura === '') {
            return $this->falha('formato');
        }

        if (($cabecalho['alg'] ?? '') !== 'RS256') {
            return $this->falha('algoritmo');
        }

        $kid = isset($cabecalho['kid']) ? (string) $cabecalho['kid'] : '';
        $chave = $this->chavePorKid($kid);
        if ($chave === null) {
            return $this->falha('chave_desconhecida');
        }

        $pem = self::pemDeModuloExpoente($chave['n'] ?? '', $chave['e'] ?? '');
        if ($pem === null) {
            return $this->falha('chave_invalida');
        }

        $valido = openssl_verify($partes[0] . '.' . $partes[1], $assinatura, $pem, OPENSSL_ALGO_SHA256);
        if ($valido !== 1) {
            return $this->falha('assinatura');
        }

        if (!in_array((string) ($claims['iss'] ?? ''), self::EMISSORES, true)) {
            return $this->falha('emissor');
        }

        $aud = $claims['aud'] ?? '';
        $auds = is_array($aud) ? $aud : array($aud);
        $audienciaAceita = false;
        foreach ($auds as $a) {
            if ($a !== '' && in_array((string) $a, $audiencias, true)) {
                $audienciaAceita = true;
                break;
            }
        }
        if (!$audienciaAceita) {
            return $this->falha('audiencia');
        }

        $agora = (int) call_user_func($this->relogio);
        if (!isset($claims['exp']) || (int) $claims['exp'] + self::TOLERANCIA_SEGUNDOS < $agora) {
            return $this->falha('expirado');
        }
        if (isset($claims['iat']) && (int) $claims['iat'] - self::TOLERANCIA_SEGUNDOS > $agora) {
            return $this->falha('emitido_no_futuro');
        }

        if ($nonceEsperado !== null && $nonceEsperado !== '') {
            if (!isset($claims['nonce']) || !hash_equals((string) $nonceEsperado, (string) $claims['nonce'])) {
                return $this->falha('nonce');
            }
        }

        if (trim((string) ($claims['sub'] ?? '')) === '') {
            return $this->falha('sem_sub');
        }

        return array('ok' => true, 'claims' => $claims);
    }

    private function falha($motivo)
    {
        return array('ok' => false, 'motivo' => $motivo);
    }

    // ------------------------------------------------------------------
    // Chaves públicas (JWKS) com cache
    // ------------------------------------------------------------------

    private function chavePorKid($kid)
    {
        $agora = (int) call_user_func($this->relogio);
        $cache = $this->lerCache();

        if ($cache !== null && $cache['expira_em'] > $agora) {
            $chave = $this->procurar($cache['chaves'], $kid);
            if ($chave !== null) {
                return $chave;
            }
            // kid desconhecido: o Google pode ter rodado as chaves. Nova busca no
            // máximo uma vez por minuto.
            if ($agora - $cache['buscado_em'] < self::INTERVALO_MIN_BUSCA) {
                return null;
            }
        }

        $novo = $this->buscar($agora);
        if ($novo === null) {
            // Google fora do ar: um cache vencido ainda serve melhor que nada.
            return $cache !== null ? $this->procurar($cache['chaves'], $kid) : null;
        }

        return $this->procurar($novo['chaves'], $kid);
    }

    private function procurar(array $chaves, $kid)
    {
        foreach ($chaves as $chave) {
            if (!is_array($chave)) {
                continue;
            }
            if ((string) ($chave['kid'] ?? '') === (string) $kid && ($chave['kty'] ?? 'RSA') === 'RSA') {
                return $chave;
            }
        }
        return null;
    }

    private function buscar($agora)
    {
        $r = call_user_func($this->buscarJwks);
        if (empty($r['ok']) || empty($r['chaves']) || !is_array($r['chaves'])) {
            Logger::warning('Login Google: não foi possível obter as chaves públicas do Google.', array(
                'erro' => isset($r['erro']) ? (string) $r['erro'] : null,
            ));
            return null;
        }

        $maxAge = isset($r['max_age']) ? (int) $r['max_age'] : 0;
        $ttl = min(self::CACHE_MAX_SEGUNDOS, max(self::CACHE_MIN_SEGUNDOS, $maxAge));
        $cache = array(
            'buscado_em' => $agora,
            'expira_em' => $agora + $ttl,
            'chaves' => array_values($r['chaves']),
        );
        $this->gravarCache($cache);

        return $cache;
    }

    private function lerCache()
    {
        if (!is_file($this->arquivoCache)) {
            return null;
        }
        $dados = json_decode((string) @file_get_contents($this->arquivoCache), true);
        if (!is_array($dados) || !isset($dados['chaves'], $dados['expira_em'], $dados['buscado_em']) || !is_array($dados['chaves'])) {
            return null;
        }
        $dados['expira_em'] = (int) $dados['expira_em'];
        $dados['buscado_em'] = (int) $dados['buscado_em'];
        return $dados;
    }

    private function gravarCache(array $cache)
    {
        $dir = dirname($this->arquivoCache);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $tmp = $this->arquivoCache . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($tmp, json_encode($cache)) !== false) {
            @rename($tmp, $this->arquivoCache);
        }
        if (is_file($tmp)) {
            @unlink($tmp);
        }
    }

    /** Busca padrão: arquivo de teste (fora de produção) ou o endpoint do Google. */
    public function buscarJwksPadrao()
    {
        $arquivoTeste = (string) GoogleConfig::get('jwks_arquivo_teste', '');
        if ($arquivoTeste !== '') {
            $dados = json_decode((string) @file_get_contents($arquivoTeste), true);
            return array('ok' => is_array($dados) && !empty($dados['keys']), 'chaves' => $dados['keys'] ?? array(), 'max_age' => 0);
        }

        $r = (new GoogleHttp())->requisitar('GET', self::URL_JWKS);
        if ($r['status'] !== 200) {
            return array('ok' => false, 'erro' => $r['erro'] ?: ('HTTP ' . $r['status']));
        }
        $dados = json_decode($r['corpo'], true);
        $maxAge = 0;
        if (isset($r['cabecalhos']['cache-control']) && preg_match('/max-age=(\d+)/', $r['cabecalhos']['cache-control'], $m)) {
            $maxAge = (int) $m[1];
        }

        return array(
            'ok' => is_array($dados) && !empty($dados['keys']),
            'chaves' => is_array($dados) && isset($dados['keys']) ? $dados['keys'] : array(),
            'max_age' => $maxAge,
        );
    }

    // ------------------------------------------------------------------
    // Codificação
    // ------------------------------------------------------------------

    public static function base64UrlDecode($valor)
    {
        $valor = strtr((string) $valor, '-_', '+/');
        $resto = strlen($valor) % 4;
        if ($resto) {
            $valor .= str_repeat('=', 4 - $resto);
        }
        return base64_decode($valor, true);
    }

    /**
     * Chave pública PEM (SubjectPublicKeyInfo) a partir do módulo `n` e do expoente
     * `e` do JWK, em DER montado à mão — sem phpseclib.
     */
    public static function pemDeModuloExpoente($n, $e)
    {
        $modulo = self::base64UrlDecode($n);
        $expoente = self::base64UrlDecode($e);
        if ($modulo === false || $expoente === false || $modulo === '' || $expoente === '') {
            return null;
        }

        $inteiro = function ($bytes) {
            $bytes = ltrim($bytes, "\x00");
            if ($bytes === '' || (ord($bytes[0]) & 0x80)) {
                $bytes = "\x00" . $bytes;
            }
            return "\x02" . self::derTamanho(strlen($bytes)) . $bytes;
        };

        $rsaPublicKey = $inteiro($modulo) . $inteiro($expoente);
        $rsaPublicKey = "\x30" . self::derTamanho(strlen($rsaPublicKey)) . $rsaPublicKey;

        // AlgorithmIdentifier: rsaEncryption (1.2.840.113549.1.1.1) + NULL
        $algoritmo = "\x30\x0d\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00";
        $bitString = "\x03" . self::derTamanho(strlen($rsaPublicKey) + 1) . "\x00" . $rsaPublicKey;
        $spki = $algoritmo . $bitString;
        $spki = "\x30" . self::derTamanho(strlen($spki)) . $spki;

        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($spki), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    private static function derTamanho($tamanho)
    {
        if ($tamanho < 0x80) {
            return chr($tamanho);
        }
        $bytes = '';
        while ($tamanho > 0) {
            $bytes = chr($tamanho & 0xff) . $bytes;
            $tamanho >>= 8;
        }
        return chr(0x80 | strlen($bytes)) . $bytes;
    }
}
