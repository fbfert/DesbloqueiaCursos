# Desbloqueia Cursos — Frontend V2 (demonstração)

## Objetivo
Versão visual **independente e estática** do frontend, isolada em `public_html/v2/`.
É uma **demonstração navegável** da nova experiência (Home, Catálogo, Curso, Checkout,
Área do Aluno, LMS, Autenticação, Eventos, Categorias e páginas institucionais),
**sem integração com o backend**. Não altera, não substitui e não depende de nenhuma
rota, view, controller, model, serviço, banco, `.env` ou `.htaccess` do sistema atual.

> **Aviso:** tudo aqui é demonstrativo. Não há login, matrícula, pagamento, certificado,
> e-mail ou progresso reais. Conteúdos institucionais e jurídicos são ilustrativos e
> não substituem documentos oficiais.

## Acesso
- Produção: `https://desbloqueiacursos.com.br/v2/`
- Local: `php -S 127.0.0.1:8000 -t public_html` e abrir `/v2/`.

## Stack
HTML5 + CSS3 + JavaScript vanilla. Sem React/Vue/Tailwind/Bootstrap/jQuery, sem
bundlers e sem build. CDNs apenas para **Google Fonts** (Inter, Sora) e **Tabler Icons**.

## Rotas canônicas (pastas com `index.html`)
```
/v2/                                  Home
/v2/catalogo/                         Catálogo (busca, filtros, ordenação, paginação)
/v2/curso/?id=ID                      Ficha do curso (turmas, conteúdo, FAQ)
/v2/checkout/?curso=ID&turma=ID       Checkout em 4 etapas (cupom, PIX, comprovante)
/v2/aluno/?aba=cursos|pedidos|certificados|perfil   Área do aluno
/v2/aula/?curso=ID&aula=ID            LMS / aula (player, módulos, quiz)
/v2/login/                            Login
/v2/cadastro/                         Cadastro (força de senha, máscara de CPF)
/v2/recuperar-senha/                  Recuperação de acesso
/v2/eventos/                          Eventos (filtros + destaque)
/v2/categorias/?id=ID                 Hub e detalhe de categorias
/v2/quem-somos/                       Institucional
/v2/contato/                          Contato (formulário demonstrativo)
/v2/onde-estamos/                     Institucional
/v2/politica-de-privacidade/          Demonstrativa (não jurídica)
/v2/termos-de-uso/                    Demonstrativa (não jurídica)
/v2/certificados/validar/?codigo=…    Validação demonstrativa (código V2-DEMO-2026-001)
```

## Rotas legadas (redirecionamento de compatibilidade)
Os arquivos `*.html` originais permanecem **apenas como redirecionamento** acessível
(JS `location.replace` + `<noscript>` + `<link rel=canonical>`), preservando a query string:
```
/v2/cursos.html                    → /v2/catalogo/
/v2/curso-direito-consumidor.html  → /v2/curso/?id=direito-consumidor
/v2/checkout.html                  → /v2/checkout/   (mantém ?curso=&turma=)
/v2/aluno.html                     → /v2/aluno/      (mantém ?aba=)
/v2/aula.html                      → /v2/aula/       (mantém ?curso=&aula=)
/v2/login.html                     → /v2/login/      (mantém ?redirect=)
/v2/cadastro.html                  → /v2/cadastro/   (mantém ?redirect=)
```

## Estrutura de arquivos
```
v2/
  index.html                         Home (rota /v2/)
  catalogo/ curso/ checkout/         Catálogo, ficha, checkout
  aluno/ aula/                       Área do aluno e LMS
  login/ cadastro/ recuperar-senha/  Autenticação
  eventos/ categorias/               Eventos e categorias
  quem-somos/ contato/ onde-estamos/ Institucionais
  politica-de-privacidade/ termos-de-uso/   Legais (demonstrativas)
  certificados/validar/              Validação demonstrativa de certificado
  *.html                             Redirecionamentos legados (não remover)
  assets/css/v2-main.css             Design system (prefixo v2-, escopo .v2-app)
  assets/js/v2-main.js               Toda a interatividade (init por página, sem rede)
  assets/img/logo-v2.svg             Logo
  assets/img/placeholders/           Reservado para capas locais
  data/cursos.js                     Dados fictícios
```

## Dados demonstrativos (`data/cursos.js`)
- `window.V2_CURSOS` — 8 cursos + 5 eventos (`tipo: "evento"`), com turmas, módulos/aulas e quiz.
- `window.V2_CATEGORIAS` / `window.V2_CATEGORIAS_INFO` / `window.V2_CATEGORIA_DE` — taxonomia das categorias.
- `window.V2_ALUNO` — perfil fictício (Ana Souza), matrículas, pedidos e certificados de exemplo.

## Isolamento e segurança
- CSS prefixado `v2-` e encapsulado em `.v2-app` (sem reset global agressivo) — não afeta páginas fora de `/v2/`.
- Todos os links internos apontam para rotas da própria V2.
- Formulários usam `preventDefault`; **nenhum** `fetch`, `XMLHttpRequest`, `POST`, upload real, cookie ou chamada externa.
- `localStorage` é usado **apenas** para estado técnico não sensível, nas chaves:
  `v2_demo_state`, `v2_checkout_demo_state`, `v2_aluno_aba_ativa`,
  `v2_aluno_demo_preferencias`, `v2_lms_completed_lessons`.
  **Nunca** são armazenados nome, e-mail, CPF, telefone ou senha.
- Redirecionamentos pós-login/cadastro são sanitizados (`safeRedirect`) — somente caminhos internos `/v2/…` são aceitos; URLs externas caem em `/v2/aluno/`.

## Acessibilidade
HTML semântico, `lang="pt-BR"`, breadcrumbs com `aria-label`, labels reais, erros com
`aria-describedby`, feedbacks com `aria-live`, tabs com `aria-selected`, accordions com
`aria-expanded`, modais/bottom-sheets fecháveis por Escape e overlay, foco visível e
navegação por teclado.

## Responsividade
Mobile-first, breakpoints 320 / 375 / 414 / 768 / 1024 / 1280 / 1440px. Uma coluna no
mobile; grids/sidebars no desktop. Navbar no desktop e bottom navigation no mobile
(exceto páginas de autenticação, sem bottom nav). Sem rolagem horizontal no documento
(estrutura com `min-width:0` / `max-width:100%`, sem `overflow-x:hidden` como paliativo).

## Limitações
- Não há backend, banco, sessão, autenticação, pagamento, e-mail, PDF, QR Code ou certificado reais.
- "Sucessos" (login, cadastro, contato, checkout, conclusão de aula) são simulações visuais.
- Datas, vagas, locais, instrutores e textos legais/institucionais são fictícios/ilustrativos.

## Próxima etapa — Fase de Integração (orientação)
Quando a V2 for promovida a produção integrada, sugere-se:
1. Substituir `data/cursos.js` por dados reais (idealmente injetados pelo backend na própria view, sem expor segredos).
2. Trocar as simulações de formulário (login, cadastro, contato, checkout) por submissões reais com CSRF, validação no servidor e tratamento de erros.
3. Implementar a validação de certificado contra o serviço oficial, mantendo a mesma UI.
4. Reavaliar `localStorage` conforme as regras de privacidade reais.
5. Publicar conteúdo jurídico oficial em política de privacidade e termos de uso.
6. Manter o design system (`.v2-app`, prefixo `v2-`) como base, integrando ao roteamento real.
