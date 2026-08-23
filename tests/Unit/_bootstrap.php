<?php

/**
 * Bootstrap compartilhado dos testes unitarios.
 *
 * Uso:
 *   require_once __DIR__ . '/_bootstrap.php';        // helpers apenas
 *   $pdo = testes_conectar_banco();                  // conexao PDO
 *
 * Banco: por padrao usa as credenciais do .env. Para rodar contra um banco de
 * testes, exporte as variaveis antes de executar:
 *   DB_DATABASE=dc_quiz_test DB_USERNAME=... DB_PASSWORD=... php tests/Unit/arquivo.php
 */

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(dirname(__DIR__)));
}

/**
 * Le o .env sem parse_ini_file: o arquivo do projeto usa comentarios com "#"
 * e parenteses, que quebram o parser de INI do PHP.
 */
function testes_carregar_env()
{
    $config = array();
    $caminho = BASE_PATH . '/.env';

    if (is_readable($caminho)) {
        foreach (file($caminho, FILE_IGNORE_NEW_LINES) as $linha) {
            $linha = trim($linha);
            if ($linha === '' || $linha[0] === '#' || $linha[0] === ';' || strpos($linha, '=') === false) {
                continue;
            }
            list($chave, $valor) = explode('=', $linha, 2);
            $valor = trim($valor);
            if (strlen($valor) > 1 && ($valor[0] === '"' || $valor[0] === "'") && substr($valor, -1) === $valor[0]) {
                $valor = substr($valor, 1, -1);
            }
            $config[trim($chave)] = $valor;
        }
    }

    // Variaveis de ambiente tem prioridade (permite apontar para o banco de teste).
    foreach (array('DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD') as $chave) {
        $valor = getenv($chave);
        if ($valor !== false && $valor !== '') {
            $config[$chave] = $valor;
        }
    }

    return $config;
}

function testes_conectar_banco()
{
    $env = testes_carregar_env();
    if (empty($env['DB_DATABASE'])) {
        fwrite(STDERR, "ERRO: configuração de banco não encontrada (.env ou variáveis de ambiente).\n");
        exit(1);
    }

    $dsn = 'mysql:host=' . ($env['DB_HOST'] ?? '127.0.0.1')
        . ';port=' . ($env['DB_PORT'] ?? '3306')
        . ';dbname=' . $env['DB_DATABASE']
        . ';charset=utf8mb4';

    $pdo = new PDO(
        $dsn,
        $env['DB_USERNAME'] ?? '',
        $env['DB_PASSWORD'] ?? '',
        array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC)
    );

    require_once BASE_PATH . '/app/Core/Database.php';
    \App\Core\Database::setConnection($pdo);

    // Popula tambem App\Core\Env (23/08/2026).
    //
    // Este bootstrap tem leitor proprio de .env, e por isso o Env da aplicacao
    // ficava VAZIO nos testes. Nada reclamava: quem lia configuracao recebia o
    // valor padrao e seguia. Ate aparecer App\Support\Crypto, que sem APP_KEY
    // devolve null em vez de cifrar — o teste de credencial passava a falhar
    // por falta de ambiente, e nao por defeito no codigo.
    //
    // Carregar aqui, junto do banco, cobre todo teste que ja depende de .env.
    require_once BASE_PATH . '/app/Core/Env.php';
    \App\Core\Env::load(BASE_PATH . '/.env');

    return $pdo;
}

spl_autoload_register(function ($class) {
    $file = BASE_PATH . '/' . str_replace('\\', '/', str_replace('App\\', 'app/', $class)) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// ----------------------------------------------------------------
// Helpers de teste (mesma API usada pelos testes existentes)
// ----------------------------------------------------------------

$GLOBALS['__testes_passou'] = 0;
$GLOBALS['__testes_falhou'] = 0;

if (!function_exists('describe')) {
    function describe(string $desc): void
    {
        echo "\n  {$desc}\n";
    }
}

if (!function_exists('it')) {
    function it(string $desc, callable $fn): void
    {
        try {
            $fn();
            echo "    ✓ {$desc}\n";
            $GLOBALS['__testes_passou']++;
        } catch (Throwable $e) {
            echo "    ✗ {$desc}\n";
            echo "      → " . $e->getMessage() . "\n";
            $GLOBALS['__testes_falhou']++;
        }
    }
}

if (!function_exists('expect')) {
    function expect($actual): object
    {
        return new class($actual) {
            public function __construct(private $value) {}
            public function toBe($expected): void
            {
                if ($this->value !== $expected) {
                    throw new RuntimeException("Esperava " . json_encode($expected) . ", recebeu " . json_encode($this->value));
                }
            }
            public function toEqual($expected): void
            {
                if ($this->value != $expected) {
                    throw new RuntimeException("Esperava " . json_encode($expected) . " (frouxo), recebeu " . json_encode($this->value));
                }
            }
            public function toBeTrue(): void { $this->toBe(true); }
            public function toBeFalse(): void { $this->toBe(false); }
            public function toBeNull(): void { $this->toBe(null); }
            public function toBeGreaterThan($n): void
            {
                if (!($this->value > $n)) {
                    throw new RuntimeException(json_encode($this->value) . " não é maior que " . json_encode($n));
                }
            }
            public function toBeGreaterThanOrEqual($n): void
            {
                if (!($this->value >= $n)) {
                    throw new RuntimeException(json_encode($this->value) . " não é maior ou igual a " . json_encode($n));
                }
            }
            public function toBeLessThanOrEqual($n): void
            {
                if (!($this->value <= $n)) {
                    throw new RuntimeException(json_encode($this->value) . " não é menor ou igual a " . json_encode($n));
                }
            }
            public function toContain($needle): void
            {
                if (is_string($this->value)) {
                    if (strpos($this->value, (string) $needle) === false) {
                        throw new RuntimeException(json_encode($this->value) . " não contém " . json_encode($needle));
                    }
                    return;
                }
                if (!in_array($needle, (array) $this->value, true)) {
                    throw new RuntimeException(json_encode($this->value) . " não contém " . json_encode($needle));
                }
            }
            public function toHaveKey($key): void
            {
                if (!array_key_exists($key, (array) $this->value)) {
                    throw new RuntimeException(json_encode(array_keys((array) $this->value)) . " não tem chave {$key}");
                }
            }
            public function notToBeNull(): void
            {
                if ($this->value === null) {
                    throw new RuntimeException("Esperava valor não-nulo");
                }
            }
        };
    }
}

if (!function_exists('testes_resumo')) {
    function testes_resumo(): int
    {
        $passou = $GLOBALS['__testes_passou'];
        $falhou = $GLOBALS['__testes_falhou'];
        echo "\n" . str_repeat('=', 46) . "\n";
        echo "Resultado: {$passou} passou | {$falhou} falhou\n";
        return $falhou > 0 ? 1 : 0;
    }
}
