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
 *   defeito    descreve um defeito conhecido do portal. A rota conta como PASS
 *              (para o baseline não ficar vermelho), mas o runner avisa sempre.
 *              Use só com um comentário explicando o defeito e como corrigi-lo.
 *
 * Ver tests/Smoke/README.md para como adicionar uma rota.
 */

return array(

    // Presente em toda página HTML do portal, nos dois layouts (legado e V2).
    'marcador_padrao' => '<html lang="pt-BR"',

    // ---------------------------------------------------------------------
    // MODO ANÔNIMO — devem responder 200 para visitante sem sessão.
    // ---------------------------------------------------------------------
    'anonimo' => array(
        array('path' => '/',                            'nome' => 'Home'),
        array('path' => '/v2',                          'nome' => 'Home V2'),
        array('path' => '/v2/catalogo',                 'nome' => 'Catálogo V2'),
        array('path' => '/v2/categorias',               'nome' => 'Categorias V2'),
        array('path' => '/v2/curso',                    'nome' => 'Curso V2'),
        array('path' => '/v2/login',                    'nome' => 'Login V2'),
        array('path' => '/v2/cadastro',                 'nome' => 'Cadastro V2'),
        array('path' => '/v2/recuperar-senha',          'nome' => 'Recuperar senha V2'),
        array('path' => '/v2/certificados/validar',     'nome' => 'Validar certificado V2'),
        array('path' => '/v2/quem-somos',               'nome' => 'Quem somos V2'),
        array('path' => '/v2/termos-de-uso',            'nome' => 'Termos de uso V2'),
        array('path' => '/v2/politica-de-privacidade',  'nome' => 'Política de privacidade V2'),
        array('path' => '/v2/onde-estamos',             'nome' => 'Onde estamos V2'),
        // DEFEITO CONHECIDO (encontrado pelo smoke em 22/08/2026): a rota está
        // registrada em routes/web.php:118 e listada no sitemap.xml, mas a página
        // correspondente foi excluída (paginas.deleted_at preenchido para
        // /como-funciona-a-sala-virtual). Resultado: 404 anunciado ao Google.
        // Declarado como 404 para o baseline ficar verde; o campo 'defeito' faz o
        // runner avisar em toda execução. Ao corrigir, volte para status 200.
        array('path' => '/v2/como-funciona-a-sala-virtual', 'nome' => 'Sala virtual V2',
              'status' => 404, 'marcador' => '', 'guarda' => false,
              'defeito' => 'rota viva e no sitemap, mas a página está excluída em `paginas`'),
        array('path' => '/categorias',                  'nome' => 'Categorias (legado)'),
        array('path' => '/cursos',                      'nome' => 'Cursos (legado)'),
        array('path' => '/como-funciona',               'nome' => 'Como funciona (legado)'),
        array('path' => '/sobre',                       'nome' => 'Sobre (legado)'),
        array('path' => '/contato',                     'nome' => 'Contato (legado)'),
        array('path' => '/login',                       'nome' => 'Login (legado)'),
        array('path' => '/cadastro',                    'nome' => 'Cadastro (legado)'),
        array('path' => '/certificados/validar',        'nome' => 'Validar certificado (legado)'),

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
        array('path' => '/area-curso',     'nome' => 'Área do curso (legado)', 'destino' => '/login'),
        array('path' => '/meus-cursos',    'nome' => 'Meus cursos (legado)',   'destino' => '/login'),
        array('path' => '/minha-conta',    'nome' => 'Minha conta (legado)',   'destino' => '/login'),
        array('path' => '/pedidos',        'nome' => 'Pedidos (legado)',       'destino' => '/login'),
    ),

    // ---------------------------------------------------------------------
    // MODO AUTENTICADO — exige SMOKE_USER/SMOKE_PASS. Somente leitura.
    // Use SEMPRE um usuário de teste dedicado, nunca a conta de um aluno real.
    // ---------------------------------------------------------------------
    'autenticado' => array(
        array('path' => '/v2/aluno',       'nome' => 'Área do aluno V2'),
        array('path' => '/v2/minha-conta', 'nome' => 'Minha conta V2'),
        array('path' => '/meus-cursos',    'nome' => 'Meus cursos (legado)'),
        array('path' => '/area-curso',     'nome' => 'Área do curso (legado)'),
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
