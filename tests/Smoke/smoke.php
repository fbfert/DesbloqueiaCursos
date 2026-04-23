<?php

declare(strict_types=1);

function normalizeBaseUrl(string $baseUrl): string
{
    $baseUrl = trim($baseUrl);
    return rtrim($baseUrl, '/');
}

function requestStatus(string $url): array
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_NOBODY => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ));
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        return array(
            'status' => (int) $status,
            'error' => $error !== '' ? $error : null,
            'raw' => is_string($response) ? $response : '',
        );
    }

    $context = stream_context_create(array(
        'http' => array(
            'method' => 'GET',
            'timeout' => 20,
            'ignore_errors' => true,
            'follow_location' => 0,
        ),
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
        ),
    ));

    $handle = @fopen($url, 'rb', false, $context);
    $status = 0;
    $error = null;

    if ($handle !== false) {
        $meta = stream_get_meta_data($handle);
        fclose($handle);

        $headers = $meta['wrapper_data'] ?? array();
        if (!empty($headers) && preg_match('/\s(\d{3})\s/', (string) $headers[0], $matches) === 1) {
            $status = (int) $matches[1];
        }
    } else {
        $error = 'fopen failed';
    }

    return array(
        'status' => $status,
        'error' => $error,
        'raw' => '',
    );
}

$baseUrl = $argv[1] ?? getenv('SMOKE_BASE_URL') ?? '';
if ($baseUrl === '') {
    fwrite(STDERR, "Informe a base URL como argumento ou via SMOKE_BASE_URL.\n");
    exit(1);
}

$baseUrl = normalizeBaseUrl($baseUrl);

$routes = array(
    '/' => 200,
    '/cursos' => 200,
    '/login' => 200,
    '/cadastro' => 200,
    '/recuperar-senha' => 200,
    '/api/health' => 200,
    '/admin' => 302,
    '/admin/dashboard' => 302,
    '/admin/configuracoes-globais' => 302,
    '/admin/financeiro' => 302,
    '/admin/area-curso' => 302,
    '/admin/certificados' => 302,
    '/admin/cupons' => 302,
    '/meus-cursos' => 302,
    '/area-curso' => 302,
    '/admin/pedidos' => 302,
    '/admin/inscricoes' => 302,
    '/admin/comprovantes-pix' => 302,
    '/professor' => 302,
    '/professor/dashboard' => 302,
    '/professor/financeiro' => 302,
    '/professor/area-curso' => 302,
    '/certificados/validar' => 200,
);

$failures = array();

foreach ($routes as $path => $expectedStatus) {
    $url = $baseUrl . $path;
    $result = requestStatus($url);
    $ok = $result['status'] === $expectedStatus;

    $label = sprintf(
        "[%s] %s => %d (esperado %d)",
        $ok ? 'OK' : 'FAIL',
        $path,
        $result['status'],
        $expectedStatus
    );

    if ($result['error'] !== null) {
        $label .= ' | ' . $result['error'];
    }

    echo $label . PHP_EOL;

    if (!$ok) {
        $failures[] = $path;
    }
}

if (!empty($failures)) {
    fwrite(STDERR, PHP_EOL . 'Falharam: ' . implode(', ', $failures) . PHP_EOL);
    exit(1);
}

echo PHP_EOL . 'Smoke test concluido com sucesso.' . PHP_EOL;
exit(0);
