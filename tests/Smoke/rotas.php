<?php

/**
 * Definição declarativa das rotas cobertas pelo smoke test.
 *
 * Cada rota foi conferida em routes/web.php antes de ser incluída aqui. Se uma
 * rota for removida do projeto, remova-a também deste arquivo — o runner não
 * "descobre" rotas, ele afirma o contrato que este arquivo declara.
 *
 * Campos de cada entrada:
 *   path       (obrigatório) caminho a requisitar, relativo à URL base
 *   nome       rótulo legível na saída
 *   status     status HTTP esperado ao FINAL da cadeia de redirects (padrão 200)
 *   marcador   trecho que precisa existir no corpo (padrão: marcador_padrao)
 *   guarda     true  -> aplica a guarda de layout global (Norminha, erros de PHP)
 *              false -> pula (usar em respostas não-HTML: XML, texto puro)
 *   destino    somente em 'protegidas': trecho que o URL final precisa conter
 *   proibe     trechos que NÃO podem aparecer no corpo (string ou lista)
 *   tema_exige trechos obrigatórios, só se a página veio no tema caderno
 *              (detectado por assets/caderno/caderno.css); vale nos dois temas
 *   redireciona array(status, trecho): o PRIMEIRO salto precisa ter esse status
 *              e o Location conter o trecho (rotas legadas → tema)
 *   proibe_legado false desliga a guarda do visual antigo (ligada por padrão
 *              fora de /admin, /professor e /revisor: falha se o corpo tiver
 *              o marcador_legado do layout V1)
 *   defeito    descreve um defeito conhecido do portal. A rota conta como PASS
 *              (para o baseline não ficar vermelho), mas o runner avisa sempre.
 *              Use só com um comentário explicando o defeito e como corrigi-lo.
 *
 * Ver tests/Smoke/README.md para como adicionar uma rota.
 */

$rotas = array(

    // Presente em toda página HTML do portal, nos dois layouts (legado e V2).
    'marcador_padrao' => '<html lang="pt-BR"',

    // Classe do <body> que só o layout V1 do site (resources/views/layout.php) emite.
    'marcador_legado' => 'frontend-theme',

    // ---------------------------------------------------------------------
    // MODO ANÔNIMO — devem responder 200 para visitante sem sessão.
    // ---------------------------------------------------------------------
    'anonimo' => array(
        array('path' => '/',                            'nome' => 'Home', 'proibe' => 'caderno-aluno'),
        array('path' => '/v2',                          'nome' => 'Home V2', 'proibe' => 'caderno-aluno'),
        array('path' => '/v2/catalogo',                 'nome' => 'Catálogo V2', 'proibe' => 'caderno-aluno'),
        array('path' => '/v2/categorias',               'nome' => 'Categorias V2'),
        array('path' => '/v2/curso',                    'nome' => 'Curso V2'),
        array('path' => '/v2/catalogo/?q=caderno',      'nome' => 'Catálogo V2 com busca'),
        array('path' => '/v2/login',                    'nome' => 'Login V2'),
        array('path' => '/v2/cadastro',                 'nome' => 'Cadastro V2'),
        array('path' => '/v2/recuperar-senha',          'nome' => 'Recuperar senha V2'),
        array('path' => '/v2/certificados/validar',     'nome' => 'Validar certificado V2'),
        // Página de erro do tema (404 com layout).
        array('path' => '/v2/pagina-que-nao-existe',    'nome' => 'Erro 404 V2', 'status' => 404),
        array('path' => '/v2/quem-somos',              'nome' => 'Quem somos V2'),
        array('path' => '/v2/termos-de-uso',            'nome' => 'Termos de uso V2'),
        array('path' => '/v2/politica-de-privacidade',  'nome' => 'Política de privacidade V2'),
        array('path' => '/v2/onde-estamos',             'nome' => 'Onde estamos V2'),
        // A página /como-funciona-a-sala-virtual foi excluída no admin
        // (paginas.deleted_at), então a rota responde 404. Desde 07/10/2026 o
        // sitemap e o rodapé do tema caderno só anunciam institucionais
        // publicadas (PaginaService::institucionaisV2Publicadas,
        // tests/Unit/paginas_institucionais.php), e o 404 deixou de ser anunciado.
        // Se a página for republicada, volte este status para 200.
        array('path' => '/v2/como-funciona-a-sala-virtual', 'nome' => 'Sala virtual V2',
              'status' => 404, 'marcador' => '', 'guarda' => false),
        // Telas que só existiam no visual antigo e agora estão no tema (tema-caderno-unico).
        array('path' => '/pagina-que-nao-existe',       'nome' => 'Erro 404 fora de /v2', 'status' => 404),
        array('path' => '/termos-de-uso',               'nome' => 'Página dinâmica (termos)'),
        array('path' => '/v2/certificados/versao-online?codigo=SMOKE-INEXISTENTE', 'nome' => 'Certificado online inexistente', 'status' => 404),
        array('path' => '/pedido-recuperacao/descadastrar?token=smoke', 'nome' => 'Descadastro (token inválido)'),

        // Rotas legadas: redirecionam ao tema (301 públicas, 302 com sessão/checkout).
        array('path' => '/categorias',                  'nome' => 'Categorias (legado)',   'redireciona' => array(301, '/v2/categorias')),
        array('path' => '/categorias/x/cursos',         'nome' => 'Categoria (legado)',    'redireciona' => array(301, '/v2/catalogo/?categoria=x')),
        array('path' => '/cursos',                      'nome' => 'Cursos (legado)',       'redireciona' => array(301, '/v2/catalogo')),
        array('path' => '/como-funciona',               'nome' => 'Como funciona (legado)', 'redireciona' => array(301, '/v2/como-funciona-a-sala-virtual'), 'status' => 404, 'marcador' => '', 'guarda' => false),
        array('path' => '/sobre',                       'nome' => 'Sobre (legado)',        'redireciona' => array(301, '/v2/quem-somos')),
        array('path' => '/contato',                     'nome' => 'Contato (legado)',      'redireciona' => array(301, '/v2/onde-estamos')),
        array('path' => '/login',                       'nome' => 'Login (legado)',        'redireciona' => array(301, '/v2/login')),
        array('path' => '/cadastro',                    'nome' => 'Cadastro (legado)',     'redireciona' => array(301, '/v2/cadastro')),
        array('path' => '/recuperar-senha',             'nome' => 'Recuperar senha (legado)', 'redireciona' => array(301, '/v2/recuperar-senha')),
        array('path' => '/certificados/validar?codigo=SMOKE', 'nome' => 'Validar certificado (legado)', 'redireciona' => array(301, '/v2/certificados/validar?codigo=SMOKE')),
        array('path' => '/certificados/show?codigo=SMOKE', 'nome' => 'Certificado (legado)', 'redireciona' => array(301, '/v2/certificados/validar?codigo=SMOKE')),

        // Respostas não-HTML: sem marcador de layout e sem guarda.
        array('path' => '/sitemap.xml', 'nome' => 'Sitemap', 'marcador' => '<urlset', 'guarda' => false),
        array('path' => '/robots.txt',  'nome' => 'robots.txt', 'marcador' => 'User-agent', 'guarda' => false),
    ),

    // ---------------------------------------------------------------------
    // ROTAS PROTEGIDAS — teste de SEGURANÇA, não de disponibilidade.
    // Sem sessão, precisam terminar no login correto. Um 200 aqui é FALHA.
    // ---------------------------------------------------------------------
    'protegidas' => array(
        array('path' => '/v2/aluno',       'nome' => 'Área do aluno V2',   'destino' => '/v2/login'),
        array('path' => '/v2/aula',        'nome' => 'Aula V2',            'destino' => '/v2/login'),
        array('path' => '/v2/quiz',        'nome' => 'Quiz V2',            'destino' => '/v2/login'),
        array('path' => '/v2/atividade',   'nome' => 'Atividade V2',       'destino' => '/v2/login'),
        array('path' => '/v2/minha-conta', 'nome' => 'Minha conta V2',     'destino' => '/v2/login'),
        array('path' => '/v2/pos-login',   'nome' => 'Pós-login V2',       'destino' => '/v2/login'),
        // Checkout do tema caderno/V2: sem sessão, todo passo termina no login.
        array('path' => '/v2/checkout/inscricao',     'nome' => 'Checkout inscrição V2',     'destino' => '/v2/login'),
        array('path' => '/v2/checkout/participantes', 'nome' => 'Checkout participantes V2', 'destino' => '/v2/login'),
        array('path' => '/v2/checkout/resumo',        'nome' => 'Checkout resumo V2',        'destino' => '/v2/login'),
        array('path' => '/v2/checkout/pagamento',     'nome' => 'Checkout pagamento V2',     'destino' => '/v2/login'),
        array('path' => '/v2/checkout/comprovante',   'nome' => 'Checkout comprovante V2',   'destino' => '/v2/login'),
        array('path' => '/v2/checkout/sucesso?pedido_id=1', 'nome' => 'Pagamento concluído', 'destino' => '/v2/login'),
        // Todo login exigido termina no login do tema, com o destino preservado.
        array('path' => '/area-curso',     'nome' => 'Área do curso (legado)', 'destino' => '/v2/login?redirect=%2Farea-curso'),
        array('path' => '/meus-cursos',    'nome' => 'Meus cursos (legado)',   'destino' => '/v2/login?redirect=%2Fmeus-cursos'),
        array('path' => '/minha-conta',    'nome' => 'Minha conta (legado)',   'destino' => '/v2/login?redirect=%2Fminha-conta'),
        array('path' => '/pedidos',        'nome' => 'Pedidos (legado)',       'destino' => '/v2/login?redirect=%2Fpedidos'),
        array('path' => '/logout',         'nome' => 'Sair da conta',          'destino' => '/v2/login'),
        array('path' => '/admin/revisoes', 'nome' => 'Fila de revisões (admin)', 'destino' => '/v2/login?redirect=%2Fadmin%2Frevisoes'),
        array('path' => '/admin/certificados/retidos', 'nome' => 'Certificados aguardando CPF (admin)', 'destino' => '/v2/login?redirect=%2Fadmin%2Fcertificados%2Fretidos'),
        array('path' => '/conta/completar', 'nome' => 'Completar cadastro (login com Google)', 'destino' => '/v2/login'),
    ),

    // ---------------------------------------------------------------------
    // MODO AUTENTICADO — exige SMOKE_USER/SMOKE_PASS. Somente leitura.
    // Use SEMPRE um usuário de teste dedicado, nunca a conta de um aluno real.
    // ---------------------------------------------------------------------
    'autenticado' => array(
        // No tema caderno, as páginas do aluno trazem as duas folhas (base + aluno).
        array('path' => '/v2/aluno',       'nome' => 'Área do aluno V2',
              'tema_exige' => array('assets/caderno/caderno-aluno.css', 'assets/caderno/caderno-aluno.js')),
        array('path' => '/v2/minha-conta', 'nome' => 'Minha conta V2',
              'tema_exige' => array('assets/caderno/caderno-aluno.css', 'assets/caderno/caderno-aluno.js')),
        // Legadas com sessão: 302 para o tema.
        array('path' => '/meus-cursos',    'nome' => 'Meus cursos (legado)', 'redireciona' => array(302, '/v2/aluno/')),
        array('path' => '/pedidos',        'nome' => 'Pedidos (legado)',     'redireciona' => array(302, '/v2/aluno/?aba=pedidos')),
        array('path' => '/minha-conta',    'nome' => 'Minha conta (legado)', 'redireciona' => array(302, '/v2/minha-conta')),
        // Confirmação de saída no tema (só leitura: o POST de saída não é enviado).
        array('path' => '/logout',         'nome' => 'Sair da conta', 'marcador' => 'action="/v2/logout"'),
    ),

    // ---------------------------------------------------------------------
    // Login: descoberto na auditoria da Etapa 0. A página V2 de login publica
    // em /login (rota única, compartilhada com o legado), enviando `origem=v2`.
    // ---------------------------------------------------------------------
    'login' => array(
        'pagina'     => '/v2/login',
        'acao'       => '/login',
        'campo_user' => 'login',
        'campo_pass' => 'senha',
        'extras'     => array('origem' => 'v2'),
        'sucesso_nao_contem' => '/v2/login',
    ),
);

// Ficha de curso com curso real: o id vem de SMOKE_CURSO_ID (ex.: 15 na fixture
// tests/Fixtures/tema_caderno_vitrine.sql; em produção, o id de um curso publicado).
// Sem a variável, a rota é pulada — nenhum id fixo, para não gerar FAIL falso em
// base que não tenha aquele curso.
$smokeCursoId = (int) getenv('SMOKE_CURSO_ID');
if ($smokeCursoId > 0) {
    $rotas['anonimo'][] = array(
        'path' => '/v2/curso/?curso_id=' . $smokeCursoId,
        'nome' => 'Curso V2 com curso_id=' . $smokeCursoId . ' (SMOKE_CURSO_ID)',
    );
}

// Login com Google (mudança login-google). O recurso só liga com GOOGLE_CLIENT_ID e
// GOOGLE_CLIENT_SECRET no .env do alvo, e o smoke não tem como ler esse .env:
//   SMOKE_GOOGLE=ativo  -> /login/google precisa terminar na tela do Google
//                          (accounts.google.com), como uma rota protegida;
//   ausente/desligado   -> /login/google precisa responder 404 com o layout do
//                          portal (recurso desligado, botão oculto).
if (strtolower((string) getenv('SMOKE_GOOGLE')) === 'ativo') {
    $rotas['protegidas'][] = array('path' => '/login/google?origem=v2', 'nome' => 'Login com Google (ativo)', 'destino' => 'accounts.google.com', 'guarda' => false);
} else {
    $rotas['anonimo'][] = array('path' => '/login/google', 'nome' => 'Login com Google (desligado)', 'status' => 404);
}

// Aula, quiz e atividade autenticados: o caminho (com query) vem de variáveis de
// ambiente, porque ids de matrícula/conteúdo não existem de forma fixa em produção.
// Sem a variável, a verificação é pulada com SKIP. A fixture local
// tests/Fixtures/tema_caderno_aluno.sql nunca é exigida (ver README, "Páginas do aluno").
$rotas['autenticado_pulados'] = array();
$opcionaisAluno = array(
    'SMOKE_AULA_URL'      => 'Aula V2',
    'SMOKE_QUIZ_URL'      => 'Quiz V2',
    'SMOKE_ATIVIDADE_URL' => 'Atividade V2',
);
foreach ($opcionaisAluno as $env => $nome) {
    $caminho = (string) getenv($env);
    if ($caminho !== '' && $caminho[0] === '/') {
        $rotas['autenticado'][] = array(
            'path'       => $caminho,
            'nome'       => $nome . ' (' . $env . ')',
            'tema_exige' => array('assets/caderno/caderno-aluno.css', 'assets/caderno/caderno-aluno.js'),
        );
    } else {
        $rotas['autenticado_pulados'][] = array('env' => $env, 'nome' => $nome);
    }
}

return $rotas;
