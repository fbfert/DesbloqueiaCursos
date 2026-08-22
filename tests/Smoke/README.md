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

**Anônimo — rotas públicas.** 24 rotas (home V1 e V2, catálogo, categorias, curso, login, cadastro,
recuperação de senha, validação de certificado, institucionais, sitemap, robots). Verifica status
HTTP e a presença de um marcador estrutural do layout.

**Anônimo — rotas protegidas.** 10 rotas (`/v2/aluno`, `/v2/aula`, `/v2/quiz`, `/v2/atividade`,
`/v2/minha-conta`, `/v2/pos-login`, `/area-curso`, `/meus-cursos`, `/minha-conta`, `/pedidos`).
**Isto é teste de segurança, não de disponibilidade:** um HTTP 200 aqui significa conteúdo de aluno
exposto a visitante. A suíte exige que a cadeia termine no login correto — `/v2/login` para o
namespace V2, `/login` para o legado.

**Autenticado.** Faz login com um usuário de teste e percorre a área do aluno. As credenciais vêm de
`SMOKE_USER` / `SMOKE_PASS` — **nunca hard-coded**. Ausentes, o modo é pulado com aviso, sem falhar
a suíte.

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
`guarda` (`false` para respostas não-HTML), `destino` (só em `protegidas`) e `defeito`.

`defeito` documenta um problema conhecido do portal: a rota conta como PASS, para o baseline não
ficar vermelho, mas o runner avisa em toda execução. Use sempre com um comentário explicando o
defeito e como corrigi-lo.

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
