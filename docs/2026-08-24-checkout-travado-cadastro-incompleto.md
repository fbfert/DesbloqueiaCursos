# Checkout travado por cadastro incompleto: a venda que virava "editar minha conta"

Data: 2026-08-24
Commits: `bc745e3` (desbloqueio), `1934c92` (WhatsApp + campo opcional)
Estado: **corrigido e em produção**

Aluno logado abria a ficha de um curso, clicava em **Quero desbloquear** e caía
na tela de editar a conta em vez de comprar. Sempre. Não havia mensagem clara,
não havia caminho de volta para o curso, e o pedido nunca começava.

## A causa

`CheckoutController::inscricao()` tinha uma trava logo antes de renderizar o
formulário:

```php
if (cpf vazio || telefone vazio) {
    flash('Atualize seu cadastro com CPF e telefone antes de iniciar a inscrição.');
    redirect('/minha-conta');
}
```

O gatilho era **sempre o telefone**, nunca o CPF — dos 160 cadastros ativos,
nenhum estava sem CPF (é obrigatório no cadastro) e 3 estavam sem telefone.

E o telefone estava vazio porque o próprio cadastro convidava a pular:

| Onde | O que dizia | O que exigia |
|---|---|---|
| `/v2/cadastro` | "Telefone **(opcional)**" | nada — `validateRegistration()` não valida |
| `/v2/checkout/inscricao` | — | telefone **obrigatório**, sob pena de redirect |

Quem aceitava o convite do formulário de cadastro ficava impedido de comprar
para sempre. E o redirect apontava para `/minha-conta`, a tela **V1**: o
comprador era jogado para fora do fluxo V2, com outro layout e sem link de
retorno para o curso.

O rastro no log de acesso, num teste de 24/08 às 18:43:

```
GET /v2/checkout/inscricao?curso_id=119&turma_id=73   302
GET /minha-conta                                      200
```

## A trava ainda era redundante

A etapa de inscrição **já pede** esses dados: o formulário tem `pagador_cpf` e
`pagador_telefone`, pré-preenchidos pelo perfil, e `validateInscricao()` já
valida os dois no POST. A trava não protegia nada que o POST não protegesse —
só expulsava o comprador antes de ele ver o formulário.

## O que foi feito

**1. Removida a trava** (`CheckoutController::inscricao()`). O checkout abre e
pede o que faltar, dentro do fluxo V2.

**2. O dado que faltava volta para o cadastro.**
`sincronizarCidadeEstadoNoCadastro()` virou
`sincronizarDadosDoPagadorNoCadastro()` e passou a completar também o telefone —
que era a intenção original da trava, agora sem bloquear a venda. Só preenche
campo vazio; nunca sobrescreve dado já informado pelo aluno.

**3. O preenchimento deixou de ser obrigatório.** `pedidos.pagador_telefone`
aceita NULL e o AbacatePay não envia telefone no payload, então exigir só
custava venda. Nome, e-mail e CPF seguem obrigatórios.

**4. "Telefone" virou "WhatsApp"** em tudo que alguém lê: checkout e cadastro
V2, telas V1 legadas (`auth`, `checkout`, `v4-claude`), backoffice (pedidos,
turmas, inscritos, usuários), a planilha de inscritos
(`TurmaInscritosExportService`) e o roteiro da Norminha. **Só o rótulo mudou** —
o campo continua `telefone` / `pagador_telefone` no banco e nos formulários.

## Fora do escopo, de propósito

- **`admin/configuracoes-globais`** — aquele "Telefone" é o **da empresa**
  (`institucional.telefone`, o contato exibido no site), não o do cliente.
  Renomear seria errado.
- **Pedido manual do admin** (`PedidoService::criarPedidoManual`) — o WhatsApp
  segue **obrigatório**. Ali quem digita é a equipe, não o comprador; só a
  mensagem de erro foi renomeada.

## Como foi verificado

- GET `/v2/checkout/inscricao` com o usuário que estava travado (sem telefone):
  era `302 → /minha-conta`, passou a ser **200** com o formulário.
- `validateInscricao()` chamado direto: WhatsApp vazio → sem erros; WhatsApp
  preenchido → sem erros; CPF vazio → ainda barra.
- `sincronizarDadosDoPagadorNoCadastro()` exercitado contra o banco real dentro
  de transação com rollback: telefone gravado só com dígitos, cidade/estado já
  existentes preservados, telefone já preenchido não sobrescrito, banco intacto
  ao final.
- Smoke: 34/34 PASS. `php -l` limpo nos 21 arquivos alterados.

## Armadilha para a próxima vez

`app/Services/PedidoService.php` e `resources/views/admin/pedidos/show.php` têm
**finais de linha mistos (CRLF + LF)**. Script de substituição em massa que leia
e reescreva em modo texto normaliza o arquivo inteiro e transforma uma troca de
rótulo num diff de 1.700 linhas. Nesses arquivos, edite em **modo binário** e
confira `git diff --numstat` antes de commitar.

## Recomendação em aberto

O descompasso de origem continua: o cadastro pede WhatsApp como opcional e o
checkout aproveita quando existe. Isso agora é coerente — mas se um dia o
WhatsApp virar canal de comunicação obrigatório, o lugar de exigir é o
**cadastro** (`AuthService::validateRegistration()`), nunca uma trava no meio do
funil de compra.
