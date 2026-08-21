<?php
/**
 * Cria o curso "OAB 1ª Fase Completo" e sua turma inicial.
 *
 * Estrutura: 25 módulos, 100 aulas tipo=html, 20 quizzes de treino (um por
 * disciplina, banco próprio) e o simulado oficial com 20 blocos de sorteio
 * reproduzindo a contagem exata do Exame de Ordem (80 questões).
 *
 * Idempotente: se o slug já existir, o script aborta sem gravar nada.
 * Uso: php scripts/seed_curso_oab_fase1.php [--dry-run]
 */

declare(strict_types=1);

$raiz = realpath(__DIR__ . '/..');
if ($raiz === false) {
    fwrite(STDERR, "Nao foi possivel localizar a raiz do projeto.\n");
    exit(1);
}
define('BASE_PATH', $raiz);
define('PUBLIC_PATH', $raiz);

require BASE_PATH . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register(BASE_PATH);
\App\Core\Env::load(BASE_PATH . '/.env');

$dryRun = in_array('--dry-run', $argv ?? [], true);
$pdo = \App\Core\Database::connection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$agora = date('Y-m-d H:i:s');
$hoje = date('Y-m-d');

const SLUG_CURSO = 'oab-1a-fase-completo';
const PROFESSOR_ID = 7; // FELIPE BOECK FERT (fbfert@gmail.com)
const CATEGORIA_ID = 8; // Direito na Prática

/**
 * Plano do curso.
 *
 * Cada disciplina traz:
 *   q       => questões que a disciplina tem no Exame de Ordem (total 80)
 *   codigo  => código do bloco de sorteio no simulado
 *   banco   => questões do bloco no banco do simulado (6x a contagem da prova)
 *   treino  => [banco do quiz de treino, quantidade sorteada por tentativa]
 *   aulas   => títulos das aulas tipo=html
 *
 * As três tentativas do simulado e as três de cada quiz de treino são sempre
 * inéditas: banco de treino = 3x o sorteio; banco do simulado = 6x a prova.
 */
$modulos = [
    [
        'titulo' => 'O Exame de Ordem: estrutura, edital e estratégia de prova',
        'aulas' => [
            'A prova, o edital e a matemática da aprovação: 40 acertos em 80',
            'Caderno de aprofundamento: como a FGV escreve um item e o que ela cobra',
            'Mapa visual: as 20 disciplinas, seus pesos e a ordem do caderno',
            'Prática guiada: diagnóstico inicial e montagem do plano de estudo',
        ],
    ],
    [
        'titulo' => 'Ética Profissional e Estatuto da OAB',
        'q' => 8, 'codigo' => 'ETICA', 'banco' => 48, 'treino' => [30, 10],
        'aulas' => [
            'Panorama: por que Ética vale 8 questões e como a FGV a cobra',
            'Estatuto da Advocacia: atividade privativa, prerrogativas e impedimentos',
            'Inscrição, sociedade de advogados, honorários e publicidade profissional',
            'Infrações disciplinares, sanções e processo ético-disciplinar',
            'Mapa visual: órgãos da OAB, prazos e quadro de sanções',
            'Prática guiada: questões comentadas e as pegadinhas recorrentes de Ética',
        ],
    ],
    [
        'titulo' => 'Filosofia do Direito',
        'q' => 2, 'codigo' => 'FILOSOFIA', 'banco' => 12, 'treino' => [15, 5],
        'aulas' => [
            'Jusnaturalismo, positivismo e pós-positivismo: o que a FGV cobra',
            'Mapa visual: correntes, autores e teses em uma página',
            'Prática guiada: identificar a corrente pela citação',
        ],
    ],
    [
        'titulo' => 'Direito Constitucional',
        'q' => 6, 'codigo' => 'CONSTITUCIONAL', 'banco' => 36, 'treino' => [30, 10],
        'aulas' => [
            'Panorama: como Constitucional é cobrado e onde estão os pontos seguros',
            'Direitos e garantias fundamentais e remédios constitucionais',
            'Organização do Estado e repartição de competências',
            'Poderes, processo legislativo e controle de constitucionalidade',
            'Mapa visual: competências, remédios constitucionais e vias de controle',
            'Prática guiada: questões comentadas e a leitura literal do dispositivo',
        ],
    ],
    [
        'titulo' => 'Direitos Humanos',
        'q' => 2, 'codigo' => 'DIREITOSHUMANOS', 'banco' => 12, 'treino' => [15, 5],
        'aulas' => [
            'Sistemas global e interamericano de proteção aos direitos humanos',
            'Mapa visual: tratados, órgãos e incorporação ao direito interno',
            'Prática guiada: questões comentadas de Direitos Humanos',
        ],
    ],
    [
        'titulo' => 'Direito Eleitoral',
        'q' => 2, 'codigo' => 'ELEITORAL', 'banco' => 12, 'treino' => [15, 5],
        'aulas' => [
            'Elegibilidade, inelegibilidade e registro de candidatura',
            'Mapa visual: prazos eleitorais, partidos, propaganda e prestação de contas',
            'Prática guiada: questões comentadas de Direito Eleitoral',
        ],
    ],
    [
        'titulo' => 'Direito Internacional',
        'q' => 2, 'codigo' => 'INTERNACIONAL', 'banco' => 12, 'treino' => [15, 5],
        'aulas' => [
            'Público e privado: tratados, nacionalidade e conflito de leis na LINDB',
            'Mapa visual: homologação de sentença estrangeira, competência e condição do estrangeiro',
            'Prática guiada: questões comentadas de Direito Internacional',
        ],
    ],
    [
        'titulo' => 'Direito Financeiro',
        'q' => 2, 'codigo' => 'FINANCEIRO', 'banco' => 12, 'treino' => [15, 5],
        'aulas' => [
            'Orçamento público, receita, despesa e Lei de Responsabilidade Fiscal',
            'Mapa visual: ciclo orçamentário, PPA, LDO e LOA',
            'Prática guiada: questões comentadas de Direito Financeiro',
        ],
    ],
    [
        'titulo' => 'Direito Tributário',
        'q' => 5, 'codigo' => 'TRIBUTARIO', 'banco' => 30, 'treino' => [25, 8],
        'aulas' => [
            'Panorama: competência tributária, limitações ao poder de tributar e imunidades',
            'Obrigação e crédito tributário: lançamento, suspensão, extinção e exclusão',
            'Impostos em espécie e responsabilidade tributária',
            'Mapa visual: princípios, imunidades e o ciclo do crédito tributário',
            'Prática guiada: questões comentadas de Direito Tributário',
        ],
    ],
    [
        'titulo' => 'Direito Administrativo',
        'q' => 5, 'codigo' => 'ADMINISTRATIVO', 'banco' => 30, 'treino' => [25, 8],
        'aulas' => [
            'Panorama: princípios, poderes e regime jurídico administrativo',
            'Atos administrativos, licitações e contratos na Lei 14.133/2021',
            'Servidores, serviços públicos, improbidade e responsabilidade do Estado',
            'Mapa visual: atributos do ato, modalidades de licitação e formas de controle',
            'Prática guiada: questões comentadas de Direito Administrativo',
        ],
    ],
    [
        'titulo' => 'Direito Ambiental',
        'q' => 2, 'codigo' => 'AMBIENTAL', 'banco' => 12, 'treino' => [15, 5],
        'aulas' => [
            'Princípios, competências, licenciamento e responsabilidade ambiental',
            'Mapa visual: SNUC, área de preservação permanente, reserva legal e instrumentos da política nacional',
            'Prática guiada: questões comentadas de Direito Ambiental',
        ],
    ],
    [
        'titulo' => 'Direito Civil',
        'q' => 6, 'codigo' => 'CIVIL', 'banco' => 36, 'treino' => [30, 10],
        'aulas' => [
            'Panorama: como Civil é cobrado e a estrutura do Código',
            'Pessoas, bens, negócio jurídico, prescrição e decadência',
            'Obrigações, contratos e responsabilidade civil',
            'Direitos reais, família e sucessões',
            'Mapa visual: prazos de prescrição, regimes de bens e ordem de vocação hereditária',
            'Prática guiada: questões comentadas de Direito Civil',
        ],
    ],
    [
        'titulo' => 'Estatuto da Criança e do Adolescente',
        'q' => 2, 'codigo' => 'ECA', 'banco' => 12, 'treino' => [15, 5],
        'aulas' => [
            'Proteção integral, medidas protetivas e ato infracional',
            'Mapa visual: medidas socioeducativas, competências e prazos',
            'Prática guiada: questões comentadas do ECA',
        ],
    ],
    [
        'titulo' => 'Direito do Consumidor',
        'q' => 2, 'codigo' => 'CONSUMIDOR', 'banco' => 12, 'treino' => [15, 5],
        'aulas' => [
            'Relação de consumo, vício, fato do produto e práticas abusivas',
            'Mapa visual: prazos de reclamação, prescrição e cadeia de responsabilidade',
            'Prática guiada: questões comentadas de Direito do Consumidor',
        ],
    ],
    [
        'titulo' => 'Direito Empresarial',
        'q' => 4, 'codigo' => 'EMPRESARIAL', 'banco' => 24, 'treino' => [20, 6],
        'aulas' => [
            'Empresário, sociedades e desconsideração da personalidade jurídica',
            'Títulos de crédito, propriedade industrial e contratos empresariais',
            'Mapa visual: tipos societários, recuperação judicial e falência',
            'Prática guiada: questões comentadas de Direito Empresarial',
        ],
    ],
    [
        'titulo' => 'Direito Processual Civil',
        'q' => 6, 'codigo' => 'PROCCIVIL', 'banco' => 36, 'treino' => [30, 10],
        'aulas' => [
            'Panorama: a estrutura do CPC e como a FGV cobra processo',
            'Jurisdição, competência, partes, litisconsórcio e intervenção de terceiros',
            'Procedimento comum: petição inicial, resposta, provas e sentença',
            'Recursos, cumprimento de sentença e execução',
            'Mapa visual: prazos, cabimento recursal e fluxo do procedimento comum',
            'Prática guiada: questões comentadas de Processo Civil',
        ],
    ],
    [
        'titulo' => 'Direito Penal',
        'q' => 6, 'codigo' => 'PENAL', 'banco' => 36, 'treino' => [30, 10],
        'aulas' => [
            'Panorama: a teoria do crime como chave de leitura dos itens',
            'Aplicação da lei penal, tipicidade, ilicitude e culpabilidade',
            'Concurso de pessoas, concurso de crimes e dosimetria da pena',
            'Crimes em espécie mais cobrados e legislação penal especial',
            'Mapa visual: excludentes, causas de aumento e quadros de penas',
            'Prática guiada: questões comentadas de Direito Penal',
        ],
    ],
    [
        'titulo' => 'Direito Processual Penal',
        'q' => 6, 'codigo' => 'PROCPENAL', 'banco' => 36, 'treino' => [30, 10],
        'aulas' => [
            'Panorama: da notícia-crime à sentença, o fluxo do processo penal',
            'Inquérito policial, ação penal e competência',
            'Prisões, medidas cautelares e provas',
            'Procedimentos, nulidades e recursos',
            'Mapa visual: prazos, cabimento das prisões e fluxo procedimental',
            'Prática guiada: questões comentadas de Processo Penal',
        ],
    ],
    [
        'titulo' => 'Direito Previdenciário',
        'q' => 2, 'codigo' => 'PREVIDENCIARIO', 'banco' => 12, 'treino' => [15, 5],
        'aulas' => [
            'Segurados, custeio, carência e benefícios do Regime Geral',
            'Mapa visual: benefícios, requisitos e prazos',
            'Prática guiada: questões comentadas de Direito Previdenciário',
        ],
    ],
    [
        'titulo' => 'Direito do Trabalho',
        'q' => 5, 'codigo' => 'TRABALHO', 'banco' => 30, 'treino' => [25, 8],
        'aulas' => [
            'Panorama: relação de emprego, contrato de trabalho e princípios',
            'Jornada, remuneração, férias, FGTS e alteração do contrato',
            'Extinção do contrato, verbas rescisórias, estabilidades e direito coletivo',
            'Mapa visual: quadro de verbas, prazos e hipóteses de estabilidade',
            'Prática guiada: questões comentadas de Direito do Trabalho',
        ],
    ],
    [
        'titulo' => 'Direito Processual do Trabalho',
        'q' => 5, 'codigo' => 'PROCTRABALHO', 'banco' => 30, 'treino' => [25, 8],
        'aulas' => [
            'Panorama: competência da Justiça do Trabalho e partes do processo',
            'Procedimentos, audiência, provas e sentença trabalhista',
            'Recursos, execução trabalhista e custas',
            'Mapa visual: prazos, cabimento recursal e depósito recursal',
            'Prática guiada: questões comentadas de Processo do Trabalho',
        ],
    ],
    [
        'titulo' => 'Oficina de questões FGV: anatomia do item e distratores',
        'aulas' => [
            'Os cinco passos para dissecar um item antes de responder',
            'Caderno de aprofundamento: a tipologia dos distratores da FGV',
            'Mapa visual: padrões de erro e armadilhas recorrentes por disciplina',
            'Prática guiada: escrever o próprio item e prever o gabarito',
        ],
    ],
    [
        'titulo' => 'Reta final: revisão de alto rendimento e plano de prova',
        'aulas' => [
            'O que revisar nos últimos 30 dias e o que já não vale a pena estudar',
            'Caderno de aprofundamento: gestão das 5 horas de prova',
            'Mapa visual: o resumo dos resumos das 20 disciplinas',
            'Prática guiada: plano pessoal de prova e roteiro do dia anterior',
        ],
    ],
    [
        'titulo' => 'Aula ao vivo de dicas para o Exame de Ordem',
        'aulas' => [
            'Aula ao vivo: dicas e estratégia final para a 1ª fase',
        ],
    ],
    [
        'titulo' => 'Simulado oficial OAB 1ª Fase',
        'simulado' => true,
        'aulas' => [],
    ],
];

// ---------------------------------------------------------------- conferência
$totalQuestoesProva = 0;
$totalBancoSimulado = 0;
$totalBancoTreino = 0;
$totalAulas = 0;
foreach ($modulos as $m) {
    $totalAulas += count($m['aulas']);
    if (!isset($m['q'])) {
        continue;
    }
    $totalQuestoesProva += $m['q'];
    $totalBancoSimulado += $m['banco'];
    $totalBancoTreino += $m['treino'][0];
    if ($m['banco'] < $m['q'] * 3) {
        throw new RuntimeException("Banco do simulado insuficiente em {$m['titulo']}: 3 tentativas exigem " . ($m['q'] * 3));
    }
    if ($m['treino'][0] < $m['treino'][1] * 3) {
        throw new RuntimeException("Banco de treino insuficiente em {$m['titulo']}: 3 tentativas exigem " . ($m['treino'][1] * 3));
    }
}
if ($totalQuestoesProva !== 80) {
    throw new RuntimeException("A soma das disciplinas deu {$totalQuestoesProva}, e o Exame de Ordem tem 80 questões.");
}

echo "Plano conferido:\n";
echo "  Módulos ................. " . count($modulos) . "\n";
echo "  Aulas tipo=html ......... {$totalAulas}\n";
echo "  Questões do simulado .... {$totalQuestoesProva}\n";
echo "  Banco do simulado ....... {$totalBancoSimulado}\n";
echo "  Banco dos treinos ....... {$totalBancoTreino}\n";
echo "  Total de questões ....... " . ($totalBancoSimulado + $totalBancoTreino) . "\n\n";

$existente = $pdo->prepare('SELECT id FROM cursos_eventos WHERE slug = ? LIMIT 1');
$existente->execute([SLUG_CURSO]);
if ($linha = $existente->fetch(PDO::FETCH_ASSOC)) {
    fwrite(STDERR, "Curso já existe (id {$linha['id']}). Nada foi gravado.\n");
    exit(1);
}

if ($dryRun) {
    echo "--dry-run: nada foi gravado.\n";
    exit(0);
}

// ------------------------------------------------------------------- gravação
$descricaoCompleta = <<<TXT
Curso preparatório completo para a 1ª fase do Exame de Ordem Unificado, cobrindo as 20 disciplinas do edital com profundidade proporcional ao peso de cada uma na prova.

São 80 horas de estudo assíncrono distribuídas em 25 módulos e 100 conteúdos obrigatórios. Cada disciplina tem seu próprio módulo, na mesma ordem em que aparece no caderno de prova, e termina com um quiz de treino de banco próprio, com três tentativas sempre inéditas.

O percurso abre com a estrutura do exame e a matemática da aprovação — 40 acertos em 80 questões — e se organiza pela ordem real da prova: Ética Profissional e Estatuto da OAB, Filosofia do Direito, Constitucional, Direitos Humanos, Eleitoral, Internacional, Financeiro, Tributário, Administrativo, Ambiental, Civil, ECA, Consumidor, Empresarial, Processo Civil, Penal, Processo Penal, Previdenciário, Trabalho e Processo do Trabalho. Fecham o curso uma oficina dedicada à anatomia do item da FGV e à tipologia dos distratores, e um módulo de reta final com revisão de alto rendimento e montagem do plano pessoal de prova.

Cada aula combina texto explicativo, cards de síntese, mapas mentais, esquemas animados, resumos, dicas de prova e exercícios comentados, sempre ancorados no dispositivo legal que a banca efetivamente cobra.

O curso encerra com um simulado no formato oficial: 80 questões objetivas distribuídas pelas 20 disciplinas exatamente como no exame real, 5 horas de duração e até três tentativas, cada uma com questões inteiramente diferentes das anteriores.
TXT;

$objetivosEspecificos = implode("\n", [
    'Compreender a estrutura da 1ª fase do Exame de Ordem, a distribuição das 80 questões e a lógica de correção.',
    'Dominar Ética Profissional e o Estatuto da OAB, a disciplina de maior peso na prova.',
    'Revisar as 20 disciplinas do edital com profundidade proporcional ao peso de cada uma.',
    'Reconhecer o dispositivo legal cobrado em cada item e localizá-lo com segurança.',
    'Identificar a tipologia dos distratores da FGV e os padrões de erro mais frequentes.',
    'Administrar o tempo das 5 horas de prova e definir a ordem de resolução do caderno.',
    'Treinar cada disciplina em quiz próprio, com três rodadas de questões inéditas.',
    'Realizar simulado integral no formato oficial e atingir pelo menos 50% das questões objetivas.',
]);

$preRequisitosItens = implode("\n", [
    'Acesso à internet e dispositivo capaz de exibir conteúdos em HTML.',
    'Disponibilidade para cumprir 80 horas de estudo assíncrono.',
    'Disponibilidade adicional de 5 horas para cada tentativa do simulado integral.',
    'A aula online ao vivo será opcional e terá data informada posteriormente.',
]);

$produtoFinal = implode("\n", [
    'Interpretar o comando do item e identificar a disciplina e o dispositivo cobrados.',
    'Resolver questões objetivas das 20 disciplinas do edital com método, e não por eliminação intuitiva.',
    'Reconhecer distratores construídos por troca de prazo, de competência, de sujeito ou de exceção.',
    'Administrar o tempo de prova e decidir a ordem de resolução do caderno.',
    'Diagnosticar o próprio desempenho por disciplina e redistribuir o tempo de estudo.',
    'Chegar à prova com plano definido e com pelo menos três simulados completos realizados.',
]);

$avaliacao = 'Simulado no formato oficial com 80 questões objetivas distribuídas pelas 20 disciplinas do edital, na mesma proporção do Exame de Ordem, com duração de 5 horas. Serão permitidas até três tentativas, cada uma com questões inteiramente diferentes das anteriores. A aprovação exige pelo menos 50% de acertos, o mesmo critério da prova real. Cada disciplina tem ainda um quiz de treino com três tentativas de questões inéditas, obrigatório para a conclusão mas sem exigência de nota. A conclusão do curso exige 100% dos conteúdos obrigatórios. O certificado de 80 horas é emitido para quem concluir todos os conteúdos obrigatórios e atingir o mínimo exigido no simulado. A aula ao vivo é opcional e não interfere na conclusão.';

$programatico = [];
foreach ($modulos as $m) {
    $itens = $m['aulas'];
    if (isset($m['treino'])) {
        $itens[] = 'Quiz de treino: ' . $m['titulo'];
    }
    if (!empty($m['simulado'])) {
        $itens[] = 'Simulado oficial OAB 1ª Fase';
    }
    $programatico[] = ['titulo' => $m['titulo'], 'itens' => $itens];
}

$pdo->beginTransaction();
try {
    $sql = 'INSERT INTO cursos_eventos
        (categoria_id, nome, slug, tipo, modalidade, descricao_curta, descricao_completa,
         carga_horaria, valor, valor_promocional, usar_turmas, permite_compra_lote,
         permite_compra_terceiros, certificado_previsto, em_promocao, destaque, ordem, status,
         exige_presenca, percentual_minimo_presenca, percentual_minimo_conclusao, exige_avaliacao,
         nota_minima, progresso_base, created_at, updated_at, objetivo_geral, objetivos_especificos,
         publico_alvo, pre_requisitos_texto, pre_requisitos_itens, ementa,
         conteudo_programatico_tipo, conteudo_programatico_modulos, metodologia, produto_final, avaliacao)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';

    $pdo->prepare($sql)->execute([
        CATEGORIA_ID,
        'OAB 1ª Fase Completo: curso preparatório para o Exame de Ordem',
        SLUG_CURSO,
        'curso',
        'online_ao_vivo',
        'Preparação completa para a 1ª fase do Exame de Ordem: as 20 disciplinas do edital com peso proporcional ao da prova, quiz de treino por disciplina e simulado de 80 questões no formato oficial, com três tentativas de questões inéditas.',
        $descricaoCompleta,
        80,
        150.00, // PROVISÓRIO — preço ainda não definido; o curso está em rascunho.
        null,
        1, 1, 1, 1,
        0, // em_promocao
        0, // destaque
        25,
        'rascunho',
        0,      // exige_presenca
        75.00,  // percentual_minimo_presenca
        100.00, // percentual_minimo_conclusao
        0,      // exige_avaliacao
        50.00,  // nota_minima — mesmo critério da prova real
        'conteudo',
        $agora, $agora,
        'Preparar bacharéis e concluintes em Direito para a 1ª fase do Exame de Ordem Unificado, cobrindo as 20 disciplinas do edital com profundidade proporcional ao peso de cada uma, treinando a leitura do item no padrão FGV e a resolução de 80 questões objetivas em 5 horas.',
        $objetivosEspecificos,
        'Bacharéis e concluintes em Direito inscritos ou a se inscrever no Exame de Ordem Unificado, incluindo quem já reprovou em edições anteriores e precisa de preparação estruturada nas 20 disciplinas.',
        'Não há pré-requisito formal. O curso pressupõe graduação em Direito em andamento ou concluída e disponibilidade para uma preparação extensa e orientada por questões.',
        $preRequisitosItens,
        'Estrutura e estratégia do Exame de Ordem; Ética Profissional e Estatuto da OAB; Filosofia do Direito; Direito Constitucional; Direitos Humanos; Direito Eleitoral; Direito Internacional; Direito Financeiro; Direito Tributário; Direito Administrativo; Direito Ambiental; Direito Civil; Estatuto da Criança e do Adolescente; Direito do Consumidor; Direito Empresarial; Direito Processual Civil; Direito Penal; Direito Processual Penal; Direito Previdenciário; Direito do Trabalho; Direito Processual do Trabalho; oficina de questões e análise de distratores; reta final e plano de prova.',
        'modulos',
        json_encode($programatico, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'Percurso assíncrono de 80 horas em 25 módulos e 100 conteúdos obrigatórios. Cada disciplina do edital tem módulo próprio, na ordem em que aparece no caderno de prova, com profundidade proporcional ao seu peso: as de maior incidência recebem seis aulas, as intermediárias cinco ou quatro, e as de duas questões recebem três. Cada aula reúne texto explicativo, cards de síntese, mapas mentais, esquemas animados, resumos, dicas de prova e exercícios comentados. Todo módulo de disciplina encerra com um quiz de treino de banco próprio, com três tentativas de questões inéditas. O curso inclui oficina de análise de itens, módulo de reta final, simulado integral obrigatório no formato oficial e uma aula online ao vivo opcional.',
        $produtoFinal,
        $avaliacao,
    ]);
    $cursoId = (int) $pdo->lastInsertId();

    $pdo->prepare('INSERT INTO curso_pessoas_vinculadas
        (curso_evento_id, usuario_id, nome, tipo_pessoa, ordem, status, created_at, updated_at)
        VALUES (?,?,?,?,?,?,?,?)')
        ->execute([$cursoId, PROFESSOR_ID, 'FELIPE BOECK FERT', 'professor', 1, 'ativo', $agora, $agora]);

    $pdo->prepare('INSERT INTO turmas
        (curso_evento_id, nome, slug, codigo, data_inicio, data_fim, inscricoes_abrem_em,
         inscricoes_encerram_em, local_nome, observacoes_publicas, exige_presenca,
         percentual_minimo_presenca, percentual_minimo_conclusao, exige_avaliacao, nota_minima,
         progresso_base, status, created_at, updated_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([
            $cursoId,
            'OAB1F01-2026A', 'oab1f01-2026a', 'OAB1F01-2026A',
            $hoje, '2026-12-31',
            $hoje . ' 00:00:00', '2026-12-31 23:59:59',
            'Ambiente virtual Desbloqueia Cursos',
            'Turma online com 80 horas de conteúdos assíncronos. Cada disciplina traz um quiz de treino com três tentativas de questões inéditas, e o curso encerra com um simulado de 80 questões e 5 horas, também com três tentativas. Haverá uma aula online ao vivo opcional, com data e link informados posteriormente. A participação na aula ao vivo não interfere na conclusão do curso.',
            0, 75.00, 100.00, 0, 50.00, 'conteudo', 'planejada',
            $agora, $agora,
        ]);
    $turmaId = (int) $pdo->lastInsertId();

    $insModulo = $pdo->prepare('INSERT INTO conteudo_modulos
        (curso_evento_id, titulo, ordem, status, created_at, updated_at) VALUES (?,?,?,?,?,?)');
    $insItem = $pdo->prepare('INSERT INTO conteudo_itens
        (curso_evento_id, modulo_id, tipo, titulo, obrigatorio, ordem, status, created_at, updated_at)
        VALUES (?,?,?,?,?,?,?,?,?)');
    $insHtml = $pdo->prepare('INSERT INTO conteudo_htmls (item_id, conteudo, created_at, updated_at) VALUES (?,?,?,?)');
    $insQuiz = $pdo->prepare('INSERT INTO conteudo_quizzes
        (item_id, instrucoes, tentativas_maximas, percentual_minimo, duracao_minutos, modo_selecao,
         acao_ao_expirar, evitar_repeticao_tentativas, permitir_banco_insuficiente,
         limite_caracteres_discursiva, exige_aprovacao, exibir_resultado_apos_envio,
         exibir_gabarito_apos_envio, exibir_comentarios_apos_envio, embaralhar_perguntas,
         embaralhar_alternativas, created_at, updated_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $insBloco = $pdo->prepare('INSERT INTO conteudo_quiz_blocos
        (quiz_id, codigo, titulo, tipo_questao, quantidade_sortear, distribuicao_dificuldade_json,
         conta_para_percentual, obrigatorio_para_envio, ordem, status, created_at, updated_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');

    $dificuldade = json_encode(['facil' => 20, 'media' => 60, 'dificil' => 20]);
    $placeholder = '<!-- Aula ainda não produzida. Conteúdo HTML será gravado na fase de produção. -->';
    $simuladoQuizId = null;
    $treinos = [];
    $ordemModulo = 0;

    foreach ($modulos as $m) {
        $ordemModulo++;
        $titulo = sprintf('%02d. %s', $ordemModulo, $m['titulo']);
        $insModulo->execute([$cursoId, $titulo, $ordemModulo, 'publicado', $agora, $agora]);
        $moduloId = (int) $pdo->lastInsertId();

        $ordemItem = 0;
        foreach ($m['aulas'] as $aula) {
            $ordemItem++;
            $insItem->execute([$cursoId, $moduloId, 'html', $aula, 1, $ordemItem, 'rascunho', $agora, $agora]);
            $insHtml->execute([(int) $pdo->lastInsertId(), $placeholder, $agora, $agora]);
        }

        if (isset($m['treino'])) {
            [$bancoTreino, $sorteia] = $m['treino'];
            $ordemItem++;
            $insItem->execute([
                $cursoId, $moduloId, 'quiz', 'Quiz de treino: ' . $m['titulo'],
                1, $ordemItem, 'rascunho', $agora, $agora,
            ]);
            $itemId = (int) $pdo->lastInsertId();
            $insQuiz->execute([
                $itemId,
                "Quiz de treino de {$m['titulo']}: {$sorteia} questões objetivas sorteadas de um banco de {$bancoTreino}. São três tentativas, e cada uma traz questões inteiramente diferentes das anteriores. Este quiz não tem tempo limite e não exige nota mínima — o banco é exclusivo do treino e nenhuma destas questões aparece no simulado final.",
                3, 50.00, null, 'blocos', 'enviar', 1, 0, 5000,
                0, // exige_aprovacao — treino não bloqueia a conclusão
                1, 1, 1, 1, 1, $agora, $agora,
            ]);
            $quizId = (int) $pdo->lastInsertId();
            $insBloco->execute([
                $quizId, $m['codigo'], $m['titulo'], 'multipla_escolha', $sorteia,
                $dificuldade, 1, 1, 1, 'ativo', $agora, $agora,
            ]);
            $treinos[] = [$m['titulo'], $quizId, $bancoTreino, $sorteia];
        }

        if (!empty($m['simulado'])) {
            $insItem->execute([
                $cursoId, $moduloId, 'quiz', 'Simulado oficial OAB 1ª Fase',
                1, 1, 'rascunho', $agora, $agora,
            ]);
            $itemId = (int) $pdo->lastInsertId();
            $insQuiz->execute([
                $itemId,
                'Simulado no formato oficial da 1ª fase do Exame de Ordem: 80 questões objetivas distribuídas pelas 20 disciplinas do edital, na mesma proporção e na mesma ordem do caderno real, com duração de 5 horas. A aprovação exige 50% de acertos, o mesmo critério da prova. São três tentativas, e cada uma traz 80 questões inteiramente diferentes das anteriores.',
                3, 50.00, 300, 'blocos', 'enviar', 1, 0, 5000,
                1, 1, 1, 1, 1, 1, $agora, $agora,
            ]);
            $simuladoQuizId = (int) $pdo->lastInsertId();

            $ordemBloco = 0;
            foreach ($modulos as $d) {
                if (!isset($d['q'])) {
                    continue;
                }
                $ordemBloco++;
                $insBloco->execute([
                    $simuladoQuizId, $d['codigo'], $d['titulo'], 'multipla_escolha', $d['q'],
                    $dificuldade, 1, 1, $ordemBloco, 'ativo', $agora, $agora,
                ]);
            }
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, 'Falhou, nada foi gravado: ' . $e->getMessage() . "\n");
    exit(1);
}

echo "Curso ................ {$cursoId}\n";
echo "Turma ................ {$turmaId} (OAB1F01-2026A, planejada)\n";
echo "Quiz do simulado ..... {$simuladoQuizId}\n";
echo "Quizzes de treino .... " . count($treinos) . "\n";
foreach ($treinos as [$nome, $qid, $banco, $sorteia]) {
    echo sprintf("  quiz %-4d %-38s banco %3d, sorteia %2d\n", $qid, $nome, $banco, $sorteia);
}
