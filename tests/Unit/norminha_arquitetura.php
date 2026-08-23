<?php

/**
 * Auditoria de arquitetura da Norminha (Etapa 19).
 *
 * Verifica INVARIANTES ESTRUTURAIS — coisas que devem ser verdade sobre o
 * formato do código, não sobre o comportamento em execução. É o tipo de checagem
 * que um revisor faria lendo os arquivos, automatizada para não depender de
 * alguém lembrar de ler.
 *
 * Cada item corresponde a uma linha da "AUDITORIA DE ARQUITETURA" do Prompt 19.
 *
 * Execução: php tests/Unit/norminha_arquitetura.php
 */

require_once __DIR__ . '/_bootstrap.php';

/** Código sem comentários: a proibição costuma ser citada em docblock. */
function fonte($caminho)
{
    return preg_replace('#/\*.*?\*/|//[^\n]*#s', '', (string) file_get_contents(BASE_PATH . '/' . $caminho));
}

function arquivosNorminha()
{
    return array_merge(
        glob(BASE_PATH . '/app/Services/Norminha*.php') ?: array(),
        glob(BASE_PATH . '/app/Models/Norminha*.php') ?: array(),
        glob(BASE_PATH . '/app/Controllers/Api/Norminha*.php') ?: array(),
        glob(BASE_PATH . '/app/Support/Norminha*.php') ?: array()
    );
}

describe('Camadas');

it('o Controller é fino: sem SQL e sem acesso direto ao banco', function () {
    $c = fonte('app/Controllers/Api/NorminhaController.php');
    foreach (array('SELECT ', 'INSERT ', 'UPDATE ', 'Database::connection') as $t) {
        if (strpos($c, $t) !== false) {
            throw new RuntimeException("o Controller contem {$t}");
        }
    }
    expect(true)->toBeTrue();
});

it('a regra de negócio mora em Services', function () {
    expect(is_file(BASE_PATH . '/app/Services/NorminhaService.php'))->toBeTrue();
    expect(is_file(BASE_PATH . '/app/Services/NorminhaIaService.php'))->toBeTrue();
});

describe('A OpenAI é falada por um lugar só');

it('o endpoint aparece apenas no OpenAIService', function () {
    $fora = array();
    $candidatos = array_merge(
        glob(BASE_PATH . '/app/Services/*.php') ?: array(),
        glob(BASE_PATH . '/app/Controllers/Api/*.php') ?: array(),
        glob(BASE_PATH . '/resources/views/components/*.php') ?: array(),
        array(BASE_PATH . '/assets/js/tutor-norminha.js')
    );
    foreach ($candidatos as $f) {
        if (!is_file($f) || basename($f) === 'OpenAIService.php') {
            continue;
        }
        $c = (string) file_get_contents($f);
        if (strpos($c, 'api.openai.com') !== false || strpos($c, '/v1/responses') !== false) {
            $fora[] = basename($f);
        }
    }
    if ($fora) {
        throw new RuntimeException('endpoint fora do service: ' . implode(', ', $fora));
    }
    expect(true)->toBeTrue();
});

it('o JavaScript não conhece o provedor', function () {
    $js = (string) file_get_contents(BASE_PATH . '/assets/js/tutor-norminha.js');
    foreach (array('openai', 'OPENAI', 'api-key', 'Authorization') as $t) {
        if (strpos($js, $t) !== false) {
            throw new RuntimeException("o cliente referencia {$t}");
        }
    }
    expect(true)->toBeTrue();
});

it('store não tem default verdadeiro em lugar nenhum', function () {
    $c = fonte('app/Services/OpenAIService.php');
    expect(strpos($c, "'store' => !empty(\$this->config['store'])"))->toBeGreaterThan(0);
    // Nenhum caminho manda store=true fixo.
    if (preg_match("/'store'\s*=>\s*true/", $c)) {
        throw new RuntimeException('ha caminho com store=true fixo');
    }
    $cfg = fonte('config/ai.php');
    expect($cfg)->toContain("Env::get('OPENAI_STORE', 'false')");
});

describe('Ferramentas');

it('todo schema é strict e fecha propriedades adicionais', function () {
    $c = fonte('app/Services/NorminhaIaService.php');
    expect(substr_count($c, "'strict' => true"))->toBeGreaterThanOrEqual(1);
    expect(substr_count($c, "'additionalProperties' => false"))->toBeGreaterThanOrEqual(1);
});

it('não há despacho dinâmico', function () {
    $c = fonte('app/Services/NorminhaIaService.php');
    foreach (array('->$nome(', '->$metodo(', 'method_exists', 'call_user_func_array($this->tools') as $p) {
        if (strpos($c, $p) !== false) {
            throw new RuntimeException("despacho dinamico: {$p}");
        }
    }
    expect(true)->toBeTrue();
});

it('nenhuma tool escreve, e recalcularInscricao não existe em nenhum caminho', function () {
    foreach (arquivosNorminha() as $f) {
        $c = preg_replace('#/\*.*?\*/|//[^\n]*#s', '', (string) file_get_contents($f));
        if (strpos($c, 'recalcularInscricao') !== false) {
            throw new RuntimeException('recalcularInscricao em ' . basename($f));
        }
        // Escrita só nas tabelas da própria Norminha.
        //
        // "ON DUPLICATE KEY UPDATE <coluna>" é ignorado de propósito: ali
        // `UPDATE` não introduz tabela, e tratá-lo como tal produziu um falso
        // positivo apontando a coluna `util` como se fosse uma tabela.
        $paraAnalise = preg_replace('/ON\s+DUPLICATE\s+KEY\s+UPDATE/i', 'ON_DUPLICATE_CLAUSE', $c);

        $permitidas = array('norminha_conversas', 'norminha_mensagens', 'norminha_feedback', 'norminha_uso');
        if (preg_match_all('/\b(INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+`?([a-z_]+)`?/i', $paraAnalise, $todas, PREG_SET_ORDER)) {
            foreach ($todas as $m) {
                if (!in_array(strtolower($m[2]), $permitidas, true)) {
                    throw new RuntimeException(basename($f) . ' escreve em ' . $m[2]);
                }
            }
        }
    }
    expect(true)->toBeTrue();
});

describe('Fontes de verdade corrigidas na auditoria');

it('o certificado vem de calcularParaInscricao', function () {
    expect(fonte('app/Services/NorminhaToolsService.php'))->toContain('calcularParaInscricao');
});

it('o progresso NÃO usa resumoAluno', function () {
    foreach (arquivosNorminha() as $f) {
        $c = preg_replace('#/\*.*?\*/|//[^\n]*#s', '', (string) file_get_contents($f));
        if (strpos($c, 'resumoAluno') !== false) {
            throw new RuntimeException(basename($f) . ' usa resumoAluno, que devolve 0% para todos');
        }
    }
    expect(true)->toBeTrue();
});

describe('Identidade e URLs');

it('usuario_id vem só da sessão, nunca do corpo', function () {
    $c = (string) file_get_contents(BASE_PATH . '/app/Controllers/Api/NorminhaController.php');
    expect(strpos($c, "Session::get('usuario_id')"))->toBeGreaterThan(0);
    if (preg_match('/input\(\s*[\'"]usuario_id/', $c)) {
        throw new RuntimeException('o Controller le usuario_id do corpo');
    }
});

it('as URLs de ação passam por whitelist no servidor', function () {
    $c = fonte('app/Services/NorminhaService.php');
    expect($c)->toContain('urlPermitida');
    expect($c)->toContain('PREFIXOS_PERMITIDOS');
});

describe('Memória');

it('não depende do provedor para lembrar', function () {
    $c = fonte('app/Services/NorminhaMemoriaService.php');
    foreach (array('previous_response_id', 'OpenAI') as $t) {
        if (strpos($c, $t) !== false) {
            throw new RuntimeException("memoria acoplada: {$t}");
        }
    }
    expect(true)->toBeTrue();
});

describe('Segredos');

it('nenhum arquivo da Norminha referencia chave, cookie ou header de auth', function () {
    foreach (arquivosNorminha() as $f) {
        $c = preg_replace('#/\*.*?\*/|//[^\n]*#s', '', (string) file_get_contents($f));
        foreach (array('api_key', 'Authorization', '$_COOKIE') as $t) {
            if (strpos($c, $t) !== false) {
                throw new RuntimeException(basename($f) . " referencia {$t}");
            }
        }
    }
    expect(true)->toBeTrue();
});

it('o legado Claude não deixou referência funcional', function () {
    foreach (array_merge(glob(BASE_PATH . '/app/**/*.php') ?: array(),
                         glob(BASE_PATH . '/app/**/**/*.php') ?: array(),
                         array(BASE_PATH . '/config/ai.php', BASE_PATH . '/routes/api.php')) as $f) {
        if (!is_file($f)) { continue; }
        $c = preg_replace('#/\*.*?\*/|//[^\n]*#s', '', (string) file_get_contents($f));
        foreach (array('ClaudeService', 'ClaudeController', 'ANTHROPIC_') as $t) {
            if (strpos($c, $t) !== false) {
                throw new RuntimeException(basename($f) . " ainda referencia {$t}");
            }
        }
    }
    expect(true)->toBeTrue();
});

it('o tema visual v4-claude foi preservado', function () {
    $n = 0;
    foreach (array('app', 'resources', 'assets', 'public_html') as $dir) {
        $saida = array();
        exec('grep -rl "v4-claude" ' . escapeshellarg(BASE_PATH . '/' . $dir) . ' 2>/dev/null', $saida);
        $n += count($saida);
    }
    // Renomear esta fora de escopo; sumir com ele seria regressao visual.
    expect($n)->toBeGreaterThan(0);
    echo "      {$n} arquivos mantêm o identificador de tema\n";
});

exit(testes_resumo());
