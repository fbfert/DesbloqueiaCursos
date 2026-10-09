# Smoke tests do portal

Rede de regressão mínima do Desbloqueia Cursos. Responde a **uma** pergunta:
*a alteração derrubou alguma página?*

Não substitui teste unitário. Foi reconstruída na Etapa 0.5 do projeto Norminha IA V1, porque o
`CLAUDE.md` documentava o comando `php tests/Smoke/smoke.php` mas a pasta não existia no
repositório — ou seja, não havia nenhuma rede entre um erro e a produção.

## Como rodar

```bash
# modo anônimo (padrão): rotas públicas + rotas protegidas sem sessão
php tests/Smoke/smoke.php https://desbloqueiacursos.com.br

# local — o router script reproduz o front controller do .htaccess
php -S 127.0.0.1:8000 -t . tests/Smoke/router.php
php tests/Smoke/smoke.php http://127.0.0.1:8000

# tudo, incluindo a área do aluno autenticada
SMOKE_USER=aluno.teste@exemplo.com SMOKE_PASS='...' \
  php tests/Smoke/smoke.php https://homologacao.exemplo.com.br --modo=todos
```

Sai com **0** se tudo passou, **1** se qualquer verificação falhou. É o que permite usar em
pipeline ou em `&&`.

### Opções

| Opção | Efeito |
|---|---|
| `--modo=anonimo\|autenticado\|todos` | o que executar (padrão `anonimo`) |
| `--json=ARQUIVO` | grava o resultado completo em JSON |
| `--baseline=ARQUIVO` | baseline a comparar (padrão `tests/Smoke/baseline.json`) |
| `--gravar-baseline` | regrava o baseline com esta execução |
| `--timeout=N` | timeout por requisição, em segundos (padrão 15) |
| `--limite-total=N` | teto da suíte inteira, em segundos (padrão 300) |
| `--max-redirects=N` | limite da cadeia de redirects (padrão 5) |
| `--tempo-estrito` | degradação de tempo vira FALHA (padrão: apenas aviso) |
| `--norminha=no-maximo-um\|exatamente-um` | rigor da guarda do componente |
| `--rotas=ARQUIVO` | usa outro arquivo de rotas (serve para testar o próprio runner) |
| `--sem-cor` | desliga cores ANSI (respeita `NO_COLOR` também) |

## O que cada modo cobre

**Anônimo — rotas públicas.** 26 rotas (27 com `SMOKE_CURSO_ID`; home V1 e V2, catálogo, categorias, curso, login, cadastro,
recuperação de senha, validação de certificado, institucionais, sitemap, robots). Verifica status
HTTP e a presença de um marcador estrutural do layout.

**Anônimo — rotas protegidas.** 16 rotas (`/v2/aluno`, `/v2/aula`, `/v2/quiz`, `/v2/atividade`,
`/v2/minha-conta`, `/v2/pos-login`, `/area-curso`, `/meus-cursos`, `/minha-conta`, `/pedidos`).
**Isto é teste de segurança, não de disponibilidade:** um HTTP 200 aqui significa conteúdo de aluno
exposto a visitante. A suíte exige que a cadeia termine no login correto — `/v2/login` para o
namespace V2, `/login` para o legado.

**Autenticado.** Faz login com um usuário de teste e percorre a área do aluno. As credenciais vêm de
`SMOKE_USER` / `SMOKE_PASS` — **nunca hard-coded**. Ausentes, o modo é pulado com aviso, sem falhar
a suíte.

### Páginas do aluno no tema caderno (aula, quiz, atividade)

Com `TEMA_PUBLICO=caderno`, as páginas do aluno carregam `caderno.css` **e** `caderno-aluno.css`
(mais `caderno-aluno.js`); home e catálogo **não** podem referenciar `caderno-aluno.*` (campo
`proibe`). A exigência das duas folhas só vale quando a página veio no tema (campo `tema_exige`,
detectado por `assets/caderno/caderno.css` no HTML), então as mesmas rotas passam com
`TEMA_PUBLICO=v2`.

`/v2/aluno` e `/v2/minha-conta` já entram no modo autenticado. Aula, quiz e atividade dependem de
ids de matrícula e conteúdo, que não existem de forma fixa em produção; por isso o caminho (com
query) vem de variáveis opcionais, e cada uma é **pulada com `SKIP`** quando ausente:

| Variável | Verifica |
|---|---|
| `SMOKE_AULA_URL` | aula (ex.: `/v2/aula?inscricao_id=..&curso_id=..&turma_id=..&modulo_id=..&conteudo_id=..`) |
| `SMOKE_QUIZ_URL` | quiz, de preferência em andamento ou "antes de começar" (só GET) |
| `SMOKE_ATIVIDADE_URL` | atividade |

Em produção, use os ids de um curso de teste da conta de teste dedicada. A fixture local
(`tests/Fixtures/tema_caderno_aluno.sql`, **somente local**) nunca é exigida. Com ela:

```bash
docker compose -f docker/local/compose.yml exec -T \
  -e SMOKE_USER=aluno.caderno@teste.local -e SMOKE_PASS='Local@12345' \
  -e 'SMOKE_AULA_URL=/v2/aula?inscricao_id=9001&curso_id=9001&turma_id=9001&modulo_id=9002&conteudo_id=9002' \
  -e 'SMOKE_QUIZ_URL=/v2/quiz?inscricao_id=9001&curso_id=9001&turma_id=9001&modulo_id=9002&conteudo_id=9016' \
  -e 'SMOKE_ATIVIDADE_URL=/v2/atividade?inscricao_id=9001&curso_id=9001&turma_id=9001&modulo_id=9003&conteudo_id=9021' \
  app php tests/Smoke/smoke.php http://127.0.0.1:8010 --modo=todos
```

Resultado de referência: 49 verificações, 49 PASS (anônimo + aluno + aula/quiz/atividade).

## Guarda do layout global

É o motivo principal desta suíte existir.

O componente da Norminha é montado pelo layout (`resources/views/layout.php`), não por cada página.
Um erro ali não derruba o chat: derruba toda página que use aquele layout. E uma **duplicação** do
componente passa despercebida com HTTP 200 — a página responde, só está errada.

Para cada rota que renderiza HTML, a suíte verifica:

1. a raiz do componente (`id="norminha-tutor"`, confirmada em
   `resources/views/components/tutor_norminha.php`) aparece **no máximo uma vez**;
2. o CSS e o JS da Norminha são incluídos **no máximo uma vez cada**;
3. não há erro de PHP vazando no corpo (`Fatal error`, `Parse error`, `Uncaught`, e
   `Warning`/`Notice`/`Deprecated` quando acompanhados de `on line`);
4. o tempo não degradou além de 3× o baseline + 500 ms.

Qualquer duplicação é **FALHA**, mesmo com HTTP 200.

### `no-maximo-um` × `exatamente-um`

Hoje `tutor_configuracoes.tutor_ativo = 0`: a Norminha está desligada e não renderiza em nenhuma
página. Por isso o padrão é `no-maximo-um` — que pega a duplicação sem exigir presença.

**Ao ligar `tutor_ativo=1`, passe a rodar com `--norminha=exatamente-um`** nas rotas onde ela deve
aparecer. É essa forma que prova que o componente não sumiu.

> Detecção de erro de PHP: com `html_errors=On` (padrão em servidor web) o PHP escreve
> `<b>Warning</b>:`, não `Warning:`. O runner normaliza `<b>`/`</b>` antes de procurar. Sem isso a
> guarda passaria batido justamente no ambiente que ela existe para vigiar — foi um bug real,
> encontrado ao testar o próprio runner.

## Rodando local

Use **sempre** `tests/Smoke/router.php` com o servidor embutido:

```bash
php -S 127.0.0.1:8000 -t . tests/Smoke/router.php
```

O `php -S` não lê `.htaccess`. Sem o router, um caminho com extensão que é servido por rota (o caso
de `/sitemap.xml`) volta 404 antes de chegar ao front controller, e o smoke acusa uma falha que só
existe no servidor local. O router também bloqueia `app/`, `config/`, `sql/`, `storage/` e
`backups/`, espelhando o que o `.htaccess` faz em produção.

Com ele, a suíte passa igual em local e em produção — o que é o requisito para o baseline valer
alguma coisa.

## Baseline

`tests/Smoke/baseline.json` guarda status e tempo de cada rota, e serve de referência entre
execuções. Não contém dado sensível (só caminho, status e milissegundos), por isso é versionado.

Para regravar **conscientemente**:

```bash
php tests/Smoke/smoke.php https://desbloqueiacursos.com.br --gravar-baseline
```

O baseline **não é regravado se a execução tiver falhas** — do contrário, fixar um baseline quebrado
transformaria a regressão em "normal". Corrija primeiro, regrave depois.

Regrave quando: uma rota nova entrar em regime, uma rota sair do projeto, ou o ambiente de medição
mudar (produção × homologação têm tempos diferentes). Não regrave para "calar" uma falha.

## Como adicionar uma rota

Edite `tests/Smoke/rotas.php`. **Confira antes em `routes/web.php` que a rota existe** — o runner
não descobre rotas, ele afirma o contrato que o arquivo declara.

```php
array('path' => '/nova-rota', 'nome' => 'Nome legível'),
```

Campos disponíveis: `path`, `nome`, `status` (padrão 200), `marcador` (padrão: marcador global),
`guarda` (`false` para respostas não-HTML), `destino` (só em `protegidas`), `proibe` (trechos que
não podem aparecer no corpo), `tema_exige` (trechos obrigatórios quando a página veio no tema
caderno) e `defeito`.

`defeito` documenta um problema conhecido do portal: a rota conta como PASS, para o baseline não
ficar vermelho, mas o runner avisa em toda execução. Use sempre com um comentário explicando o
defeito e como corrigi-lo.

### Login com Google

`/login/google` muda de comportamento conforme o `.env` do alvo, que o smoke não lê. Sem as
chaves `GOOGLE_*` (padrão), a rota precisa responder **404**. Depois de configurar o Google, rode
com `SMOKE_GOOGLE=ativo`: a rota passa a ser conferida como protegida e precisa terminar em
`accounts.google.com` (a tela de escolha de conta do Google).

```bash
SMOKE_GOOGLE=ativo php tests/Smoke/smoke.php https://desbloqueiacursos.com.br
```

## O que a suíte NÃO faz

Restrições deliberadas, para poder rodar contra produção sem medo:

- **Nenhum POST** que crie ou altere pedido, pagamento, matrícula, nota ou progresso.
  O **único** POST permitido é o de autenticação.
- Nenhum disparo de e-mail.
- Nenhuma escrita em produção além do próprio login.
- Nenhuma dependência externa: PHP puro + cURL nativo. Sem composer, sem PHPUnit.

⚠️ **Não rode o modo autenticado contra produção com a conta de um aluno real.** Use um usuário de
teste dedicado.

## Testando o próprio runner

Um teste que nunca falha não é teste. O `--rotas=` existe para provar que este falha:

```bash
# aponte para um domínio inexistente: todas as verificações devem FALHAR, exit 1
php tests/Smoke/smoke.php https://dominio-que-nao-existe.invalid --timeout=5
```

Para exercitar a guarda de layout, sirva uma página com o componente duplicado e rode contra ela com
um arquivo de rotas próprio. Os quatro modos de falha que precisam ser pegos:
componente duplicado · CSS/JS duplicado · erro de PHP no corpo · rota protegida respondendo 200.

## Defeitos conhecidos registrados hoje

| Rota | Problema |
|---|---|
| `/v2/como-funciona-a-sala-virtual` | Rota registrada em `routes/web.php:118` e anunciada no `sitemap.xml`, mas a página está excluída em `paginas` (`deleted_at` preenchido). Retorna 404 — encontrado pelo smoke na primeira execução. Corrigir: republicar a página no admin **ou** remover a rota, a entrada do sitemap (`SitemapController.php:37`) e a constante `V2Nav::COMO_FUNCIONA_SALA`. |

## Tema público (`TEMA_PUBLICO=caderno|v2`)

O tema "caderno" é escolhido pela chave `TEMA_PUBLICO` do `.env` (lida a cada requisição). A suíte
precisa passar **com as mesmas rotas PASS nos dois valores**:

```bash
# local, dentro do container (o app responde em 127.0.0.1:8010)
sed -i 's/^TEMA_PUBLICO=.*/TEMA_PUBLICO=v2/' .env
docker compose -f docker/local/compose.yml exec -T app php tests/Smoke/smoke.php http://127.0.0.1:8010
sed -i 's/^TEMA_PUBLICO=.*/TEMA_PUBLICO=caderno/' .env
docker compose -f docker/local/compose.yml exec -T app php tests/Smoke/smoke.php http://127.0.0.1:8010
```

Notas:

- A ficha de curso com curso real (`/v2/curso/?curso_id=N`) só entra quando `SMOKE_CURSO_ID=N` está
  definida; sem ela, a rota é pulada (nenhum id fixo, para não dar FAIL falso numa base sem aquele
  curso). No ambiente local, a fixture `tests/Fixtures/tema_caderno_vitrine.sql` cria o curso 15 (e
  dá corpo a `/v2/quem-somos` e `/v2/onde-estamos`):
  `docker compose -f docker/local/compose.yml exec -T -e SMOKE_CURSO_ID=15 app php tests/Smoke/smoke.php http://127.0.0.1:8010`.
  Em produção, use o id de um curso publicado.
- Os passos do checkout (`/v2/checkout/*`) entram em **protegidas**: sem sessão, terminam em `/v2/login`.
  O conteúdo deles logado não é coberto pelo smoke (que não faz POST de pedido); a paridade de links e
  campos entre os dois temas foi conferida à parte, com cookie de login e `pedido_id` de um pedido
  `aguardando_pagamento` local.
- A guarda "folha de estilo base" aceita `app.css` (legado), `v2-main.css` (V2) ou `caderno.css`
  (tema). Com a chave em `caderno`, as páginas do escopo do tema trazem só `caderno.css`.
- Deixe a chave em `caderno` ao terminar, para o ambiente local voltar ao estado de desenvolvimento.
