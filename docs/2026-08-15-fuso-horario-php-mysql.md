# Divergência de fuso entre PHP e MySQL: o que ela quebrou

Data: 2026-08-15

Investigação aberta depois que a conferência de uma tentativa de simulado
mostrou, na mesma linha do banco, `iniciada_em` às 14:23 e `created_at` às
11:23.

## A causa

| Relógio | Fuso | Motivo |
|---|---|---|
| Sistema operacional | UTC−3 | correto para o Brasil |
| MySQL | UTC−3 | `time_zone = SYSTEM`, segue o SO |
| **PHP** | **UTC** | **nenhum fuso configurado**; cai no padrão |

Não há `date_default_timezone_set` na aplicação, nem `date.timezone` no
ambiente, nem variável no `.env`. **O PHP é o desalinhado**, não o banco.

O resultado é que a mesma linha carrega dois relógios: colunas gravadas por
`NOW()` ficam no horário local e colunas gravadas por `date()` do PHP ficam três
horas à frente. Isoladamente cada uma é coerente; o defeito aparece quando uma é
comparada com a outra.

Ordem de grandeza: 325 usos de `NOW()` e 89 gravações via `date()` do PHP.

## Defeitos encontrados

### 1. Envio automático de simulado expirado, 3h atrasado — corrigido

`ConteudoQuizTentativa::listExpiradas()` cortava por `expira_em <= NOW()`, mas
`expira_em` é gravado pelo PHP. O cron só enxergava a tentativa vencida três
horas depois do prazo real, atrasando na mesma medida o envio automático de quem
fechou o navegador — justamente o cenário que o cron existe para cobrir.

O corte passou a usar o relógio do PHP, o mesmo que gravou a coluna.

O cronômetro do aluno **nunca foi afetado**: `tempoDaTentativa()` já comparava
`expira_em` com `time()`, ambos em PHP.

### 2. Bloqueio de login por tentativas era inerte — corrigido

O mais grave dos dois.

- `Usuario::incrementLoginAttempts()` gravava
  `bloqueado_ate = DATE_ADD(NOW(), INTERVAL 15 MINUTE)` — relógio do **banco**;
- `AuthService::isBlocked()` avalia `strtotime($bloqueado_ate) > time()` —
  relógio do **PHP**, três horas à frente.

Um prazo local de 15 minutos nasce três horas atrás do ponto de vista do PHP,
então a comparação **nunca era verdadeira**. Só um bloqueio maior que ~180
minutos funcionaria, e por acidente.

Efeito prático: **a proteção contra força bruta no login não existia**. A conta
era marcada como bloqueada no banco e continuava aceitando tentativas.

Indício no banco: o usuário 130 tem `tentativas_login = 10`, o dobro do máximo
de 5, com um `bloqueado_ate` registrado.

Correção: o prazo passou a ser calculado pelo PHP, ficando do mesmo lado de quem
o lê. Verificado que bloqueios de 15, 30 e 60 minutos agora valem.

### 3. `acesso_expira_em` encurta o acesso em 3h — não corrigido, sem impacto hoje

A coluna vem de um `datetime-local` preenchido pelo admin, ou seja, horário de
parede local. Ela é lida de dois jeitos:

- em SQL (`PresenteBeneficiario`), comparada com `NOW()` — **correto**;
- em PHP (`CertificadoService`, `AreaCursoService`, `ProgressoService`),
  comparada com `time()` — **corta o acesso três horas antes**.

Não foi corrigido porque a tabela `presentes_beneficiarios` está vazia: hoje não
há ninguém afetado, e a correção certa depende da decisão da próxima seção.
**Precisa ser tratado antes da primeira campanha de presente.**

## Verificado e correto, sem ação

- **Token de recuperação de senha**: gravado com `DATE_ADD(NOW(), ...)` e
  validado com `>= NOW()`. Os dois lados no banco, coerente;
- **Prazo de atividade** e datas digitadas pelo admin comparadas com `NOW()` em
  SQL: os dois lados em horário local, coerente.

## A decisão que fica em aberto

O certo estruturalmente é **configurar o PHP em `America/Sao_Paulo`**, alinhando
os três relógios. Isso corrige de uma vez o item 3 e qualquer caso futuro.

O custo é a transição, e ele não é nulo:

- **dados históricos gravados por PHP mudam de significado.** As colunas em UTC
  passariam a ser lidas como locais, deslocando três horas para trás qualquer
  data já gravada por `date()` — inclusive em relatórios e telas de admin;
- **tentativas de prova em andamento no momento da mudança ganham 3 horas.**
  Hoje há 1 tentativa aberta com prazo e 3 no total com `expira_em`.

Por isso não foi feito agora. É uma mudança de sistema inteiro, que pede janela
sem prova em andamento e uma decisão sobre o que fazer com o histórico —
converter as colunas afetadas ou aceitar o deslocamento.

Enquanto isso não acontece, a regra prática para código novo é: **compare datas
sempre do mesmo lado em que elas foram gravadas.** Coluna escrita por `NOW()` se
compara com `NOW()`; coluna escrita por `date()` do PHP se compara com `time()`.
