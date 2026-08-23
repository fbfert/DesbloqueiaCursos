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

        // norminha_uso_publico entrou em 23/08/2026: e o contador por origem do
        // atendimento a quem nao tem conta. Guarda numero e um hash de IP, nunca
        // texto do visitante -- o teste "o atendimento publico nao guarda o que
        // o visitante escreve" cobre essa parte.
        $permitidas = array('norminha_conversas', 'norminha_mensagens', 'norminha_feedback',
            'norminha_uso', 'norminha_uso_publico');
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

describe('Aparência: a marcação e a folha de estilo concordam');

/**
 * Este bloco existe por causa de 23/08/2026.
 *
 * Quando a Norminha deixou de ser um balão de fala e virou chat, a marcação
 * passou de .norminha-tutor__card para .norminha-tutor__panel. Todo o
 * tratamento visual — fundo, borda, sombra — ficou preso à classe antiga, que
 * sumiu do HTML e continuou no CSS. O painel foi ao ar sem fundo nenhum.
 *
 * No desktop, sobre página clara, ninguém percebeu. No celular, sobre conteúdo,
 * o chat ficou ilegível. Nenhum dos testes viu: todos olhavam comportamento,
 * dados e segurança. Nenhum perguntava se a coisa tinha superfície.
 */

function classesDaMarcacao()
{
    $html = (string) file_get_contents(BASE_PATH . '/resources/views/components/tutor_norminha.php');
    preg_match_all('/class="([^"]*)"/', $html, $m);

    $classes = array();
    foreach ($m[1] as $atributo) {
        // Ignora atributos montados por PHP: o valor literal não é o que sai.
        if (strpos($atributo, '<?php') !== false) {
            continue;
        }
        foreach (preg_split('/\s+/', trim($atributo)) as $classe) {
            if ($classe !== '' && strpos($classe, 'norminha') === 0) {
                $classes[$classe] = true;
            }
        }
    }

    return array_keys($classes);
}

it('toda classe da Norminha usada no HTML tem regra no CSS', function () {
    $css = (string) file_get_contents(BASE_PATH . '/assets/css/tutor-norminha.css');
    // Fora os comentários: uma classe citada só em explicação não estiliza nada.
    $cssSemComentario = preg_replace('#/\*.*?\*/#s', '', $css);

    $orfas = array();
    foreach (classesDaMarcacao() as $classe) {
        if (strpos($cssSemComentario, '.' . $classe) === false) {
            $orfas[] = $classe;
        }
    }

    if ($orfas) {
        throw new RuntimeException('classe no HTML sem regra no CSS: ' . implode(', ', $orfas));
    }
    expect(true)->toBeTrue();
});

it('nenhuma regra do CSS aponta para classe que o HTML não usa mais', function () {
    $css = preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents(BASE_PATH . '/assets/css/tutor-norminha.css'));
    preg_match_all('/\.(norminha-tutor__[a-z0-9-]+)/', $css, $m);

    $noHtml = array_flip(classesDaMarcacao());
    // Classes que o JavaScript liga e desliga não aparecem no HTML estático.
    $js = (string) file_get_contents(BASE_PATH . '/assets/js/tutor-norminha.js');

    $mortas = array();
    foreach (array_unique($m[1]) as $classe) {
        if (isset($noHtml[$classe]) || strpos($js, $classe) !== false) {
            continue;
        }
        $mortas[] = $classe;
    }

    if ($mortas) {
        throw new RuntimeException('regra de CSS para classe que ninguém usa: ' . implode(', ', $mortas));
    }
    expect(true)->toBeTrue();
});

it('o painel do chat tem superfície própria — fundo, borda e sombra', function () {
    $css = preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents(BASE_PATH . '/assets/css/tutor-norminha.css'));

    // Pega o bloco que declara o painel com mais de uma propriedade visual.
    preg_match_all('/\.norminha-tutor__panel\s*\{([^}]*)\}/', $css, $m);
    $tudo = implode(' ', $m[1]);

    foreach (array('background', 'border', 'box-shadow') as $propriedade) {
        if (strpos($tudo, $propriedade) === false) {
            throw new RuntimeException("o painel do chat não declara {$propriedade} — "
                . 'sobre conteúdo, no celular, a conversa fica ilegível');
        }
    }
    expect(true)->toBeTrue();
});

it('o fundo do painel é opaco', function () {
    $css = preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents(BASE_PATH . '/assets/css/tutor-norminha.css'));
    preg_match_all('/\.norminha-tutor__panel\s*\{([^}]*)\}/', $css, $m);
    $tudo = implode(' ', $m[1]);

    if (preg_match('/background[^;]*rgba\([^)]*,\s*0?\.\d+\s*\)/', $tudo)) {
        throw new RuntimeException('o fundo do painel usa transparência — '
            . 'a superfície onde se lê a conversa precisa ser opaca');
    }
    expect(true)->toBeTrue();
});

describe('A fronteira do atendimento público');

/**
 * Este é o invariante que sustenta o atendimento a quem não tem conta.
 *
 * O serviço público responde sem sessão: não há usuário identificado, e
 * portanto não há a quem pertencer nenhum dado. Se um dia ele consultar uma
 * tabela de aluno, qualquer visitante estará a uma consulta de distância de
 * dado de terceiro — e o pior é que funcionaria em silêncio, devolvendo a
 * matrícula de outra pessoa como se fosse resposta.
 *
 * Por isso o serviço é SEPARADO do NorminhaService em vez de um `if` dentro
 * dele: com um ramo, bastaria um caminho esquecido.
 */

it('o serviço público não menciona tabela de aluno', function () {
    $fonte = fonte('app/Services/NorminhaPublicoService.php');

    $proibidas = array('usuarios', 'inscricoes', 'pedidos', 'certificados',
        'norminha_conversas', 'norminha_mensagens', 'norminha_feedback', 'norminha_uso',
        'conteudo_progresso', 'usuario_curso', 'usuario_turma');

    foreach ($proibidas as $tabela) {
        if (preg_match('/\b' . preg_quote($tabela, '/') . '\b/i', $fonte)) {
            throw new RuntimeException("o atendimento público menciona a tabela {$tabela}");
        }
    }
    expect(true)->toBeTrue();
});

it('o serviço público não executa SQL nem lê a sessão', function () {
    $fonte = fonte('app/Services/NorminhaPublicoService.php');

    foreach (array('Database::', '->query(', '->prepare(', 'Session::', '$_SESSION') as $proibido) {
        if (strpos($fonte, $proibido) !== false) {
            throw new RuntimeException("o atendimento público usa {$proibido}");
        }
    }
    expect(true)->toBeTrue();
});

it('o endpoint público não exige sessão, e o do aluno exige', function () {
    $rotas = (string) file_get_contents(BASE_PATH . '/routes/api.php');

    if (!preg_match("/'\/api\/norminha\/publico'.*?array\(([^)]*)\)\);/s", $rotas, $m)) {
        throw new RuntimeException('a rota pública não foi encontrada');
    }
    if (strpos($m[1], 'auth.api') !== false) {
        throw new RuntimeException('a rota pública exige sessão — então não atende quem não tem conta');
    }
    // CSRF continua: o componente fornece o token mesmo em sessão anônima.
    if (strpos($m[1], 'csrf') === false) {
        throw new RuntimeException('a rota pública ficou sem csrf');
    }

    if (!preg_match("/'\/api\/norminha\/chat'.*?array\(([^)]*)\)\);/s", $rotas, $mc)
        || strpos($mc[1], 'auth.api') === false) {
        throw new RuntimeException('o /chat do aluno deixou de exigir sessão');
    }
    expect(true)->toBeTrue();
});

it('o atendimento público não guarda o que o visitante escreve', function () {
    // Quem não tem conta não consentiu com nada. O único registro permitido é o
    // contador por origem, e mesmo o IP entra como hash.
    $limite = fonte('app/Services/NorminhaPublicoLimiteService.php');

    if (preg_match('/INSERT INTO\s+(?!norminha_uso_publico)/i', $limite)) {
        throw new RuntimeException('o freio público escreve em outra tabela');
    }
    foreach (array('mensagem', 'message', 'texto') as $campo) {
        if (preg_match('/INSERT INTO[^;]*\b' . $campo . '\b/is', $limite)) {
            throw new RuntimeException("o freio público grava o campo {$campo}");
        }
    }
    if (strpos($limite, "hash('sha256'") === false) {
        throw new RuntimeException('o IP não está sendo transformado em hash');
    }
    expect(true)->toBeTrue();
});

exit(testes_resumo());
