# Perfil Revisor: revisão de conteúdo sem poder de edição

Data: 2026-08-21
Spec: `specs/0002-perfil-revisor/`
Migration: `sql/072_perfil_revisor_comentarios.sql`
Estado: **fases 1 a 3 concluídas**; fila de triagem no admin (fase 4) pendente

Um especialista externo — advogado, professor da área, revisor técnico — passa a
ler os cursos que lhe forem atribuídos e registrar apontamentos por escrito,
**sem qualquer permissão de alteração de conteúdo**.

## O problema que existia

Os oito cursos preparatórios produzidos em escala (118 a 124 da coleção PND e o
125 de OAB) terminam todos com a mesma pendência em aberto: *"revisão por
professor da área antes de divulgar"*. Nenhum tinha por onde essa revisão
acontecer.

Os dois caminhos possíveis falhavam nos extremos opostos:

- **vincular como professor** dá leitura da área do curso, mas a tela de edição
  de conteúdo HTML só existe no admin e não há tela de professor para o banco de
  questões — o revisor não enxergava justamente as questões, que é o que mais
  precisa de revisão;
- **dar acesso de admin** resolve o acesso e entrega junto o poder de alterar
  conteúdo publicado, sem trilha de quem pediu o quê.

## A garantia é de permissão, não de interface

O perfil Revisor tem exatamente **duas** permissões:
`area_curso.revisor.ver` e `area_curso.revisor.comentar`. Não tem nenhuma
permissão terminada em `gerenciar` de conteúdo. Não é um botão escondido numa
tela: é o `PermissionMiddleware` barrando a rota.

Três camadas sustentam isso:

1. **RBAC** — as rotas `/revisor/*` exigem as permissões acima, e nenhuma outra;
2. **Escopo** — `RevisorAcademicScopeService` resolve o acesso por
   `curso_pessoas_vinculadas` com `tipo_pessoa = 'revisor'` e `status = 'ativo'`.
   Vínculo de professor **não** vale como vínculo de revisor, e vínculo inativo
   não dá acesso;
3. **Estrutura** — nenhum controller do revisor injeta `ConteudoCursoService`
   nem qualquer Service de gravação de conteúdo. Foi para preservar isso que
   nasceu o `RevisorLeituraService`, que só tem `SELECT`: sem ele, ou o
   controller ficaria gordo de SQL, ou seria preciso injetar exatamente o objeto
   que a feature existe para manter longe dali.

## O que foi construído

| Camada | Arquivos |
|---|---|
| Banco | `sql/072_perfil_revisor_comentarios.sql` |
| Model | `app/Models/RevisaoComentario.php` |
| Services | `RevisorAcademicScopeService`, `RevisaoComentarioService`, `RevisorLeituraService` |
| Controllers | `app/Controllers/Revisor/{Dashboard,Revisao}Controller.php` |
| Views | `resources/views/revisor/{_shell,dashboard,curso,conteudo,questoes,_painel_comentarios}.php` |
| Rotas | 4 GET e 3 POST em `routes/web.php` |
| Testes | `tests/Unit/revisor_academic_scope.php` (24), `revisor_permissoes.php` (8) |

### A tabela

`revisao_comentarios` tem alvo polimórfico (`alvo_tipo` + `alvo_id`) apontando
para item de conteúdo, módulo, pergunta ou alternativa — quatro tabelas
distintas, portanto sem chave estrangeira, no mesmo padrão de `emails_envios`.
A integridade é validada no Service, e é uma validação que importa: sem ela, um
revisor do curso A poderia comentar numa questão do curso B só informando o id.

Dois campos transformam a feature em fluxo de trabalho, e não em caixa de
sugestões:

- **`severidade`** — `erro`, `impreciso`, `sugestao`, `duvida`. Permite triagem:
  erro trava, sugestão não;
- **`status` + `resposta`** — `aberto → aceito / recusado / resolvido`. O revisor
  volta e vê o que foi feito com cada apontamento. Recusar exige resposta:
  recusar em silêncio ensina o revisor a parar de apontar.

O apontamento **sempre** nasce `aberto`. O teste envia `status: 'aceito'` no
payload para provar que o Service ignora — o revisor não define o desfecho do
próprio comentário.

## Um teste de configuração, de propósito

`revisor_permissoes.php` afirma o conjunto **exato** de permissões do perfil e
falha se aparecer uma nova, com mensagem que manda atualizar a spec junto.

Normalmente não se testa configuração. Aqui a configuração *é* a feature: um
perfil que ganhe `area_curso.gerenciar` por engano deixa de ser revisor e vira
administrador, **sem que nenhuma tela mude de aparência e sem que nenhum outro
teste falhe**.

## Três defeitos que só apareceram ao rodar

Nenhum deles seria pego por `php -l`, e os três quebravam a feature em produção.

1. **`Request::input()` lê apenas o corpo do POST.** As telas do revisor são GET
   com `?curso_id=` / `?item_id=`, e o método correto é `query()`. As três
   páginas internas redirecionavam em silêncio. Corrigido com um helper que lê
   dos dois lugares;
2. **Quatro classes CSS que não existem** no `admin.css`: `form-row`,
   `form-actions`, `text-danger`, `inline-form`. Os formulários foram reescritos
   no padrão real do projeto, que é `<label>` envolvendo o campo;
3. **`conteudo-html-embed.css/js` não carregava no escopo do revisor.** É o
   script que dá altura real ao iframe por `postMessage`; sem ele, uma aula de
   92 KB apareceria numa moldura de algumas centenas de pixels.

## Percurso validado de ponta a ponta

Com usuário real, sessão real e HTTP real:

| Rota | Resultado |
|---|---|
| `/revisor` | 200 — painel com cursos e contadores |
| `/revisor/curso?curso_id=125` | 200 · 169 KB |
| `/revisor/conteudo?item_id=1923` | 200 · 92 KB, aula no iframe do aluno |
| `/revisor/questoes?item_id=1933` | 200 · 166 KB, 30 questões com gabarito |
| `/revisor/curso?curso_id=124` | 302 — curso não atribuído |
| `/admin/area-curso`, `/admin/cursos`, `/professor/area-curso` | **403** |

Um apontamento de severidade `erro` foi registrado numa questão e gravou com
status `aberto`. A tentativa de comentar num item do curso 124 informando o
curso 125 no contexto foi recusada. A massa de teste foi removida integralmente.

## Decisões que merecem registro

**Shell próprio, CSS do admin.** O `admin/_shell.php` filtra o menu por
permissão e derruba grupos vazios, mas o item "Dashboard" não exige permissão
nenhuma — um revisor veria um link para `/admin/dashboard` que receberia 403 ao
clicar. Menu com link morto é pior que menu separado. O
`resources/views/revisor/_shell.php` reaproveita `admin.css` e as mesmas
classes, com menu curto e sem link que não abre.

**Escopo por curso, não por turma.** O revisor revisa conteúdo, e conteúdo é do
curso. Turma não altera conteúdo.

**O gabarito fica à vista.** É necessário: é o gabarito que o revisor precisa
julgar. Está declarado na seção de riscos da spec, e o acesso depende de
`area_curso.revisor.ver`, que nunca é concedida ao perfil Aluno.

## Pendências

**Fase 4 — fila de triagem no admin.** Sem ela, os apontamentos entram e ninguém
responde. É o outro lado do fluxo e a próxima entrega.

**Fora de escopo declarado** (segunda versão, se houver demanda): seleção de
trecho dentro do iframe por `postMessage`, comentário em alternativa individual,
notificação por e-mail e exportação da fila.

## Achado colateral

`tests/Unit/professor_academic_scope.php`, citado no `CLAUDE.md` como exemplo de
comando de teste, **não existe**. O diretório `tests/Unit/` tem apenas
`_bootstrap.php`, `checkout_rapido_*`, `quiz_rascunho`, `quiz_simulado_pnd`,
`quiz_sorteio` e `quiz_system`. Ou seja: o escopo de professor, na prática, não
é coberto por teste — o que reforçou a decisão de cobrir o do revisor.
