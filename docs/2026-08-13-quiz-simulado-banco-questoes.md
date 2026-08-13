# Quizzes: simulado com banco de questões (formato PND)

Data: 2026-08-13
Migration: `sql/070_quiz_simulado_banco_questoes.sql`

Evolução do sistema de quizzes para suportar simulados longos com banco de
questões, sorteio por blocos, tempo de prova e questão discursiva. O primeiro
uso previsto é a Prova Nacional Docente (PND).

**Nada muda nos quizzes existentes.** Os padrões novos são
`modo_selecao = 'todas'` e `duracao_minutos = NULL`, exatamente o
comportamento anterior.

## Modelagem

| Tabela | Papel |
|---|---|
| `conteudo_quiz_blocos` | Blocos de sorteio (código, título, quantidade a sortear, distribuição de dificuldade, ordem, status) |
| `conteudo_quiz_itens_utilizados` | Questões já sorteadas por inscrição — permite não repetir entre tentativas |
| `conteudo_quiz_correcoes_discursivas` | Correção da discursiva (nota, rubrica, feedback, corretor, data) |
| `conteudo_quiz_correcoes_discursivas_historico` | Histórico de alteração de cada correção |

Colunas acrescentadas: duração/modo/ação ao expirar em `conteudo_quizzes`;
bloco, dificuldade, tema, status e tipo `discursiva` em
`conteudo_quiz_perguntas`; prazo, totais objetivos e auditoria do sorteio em
`conteudo_quiz_tentativas`; bloco, tipo, texto e marcação de revisão em
`conteudo_quiz_respostas`.

### Decisões

- **Nenhum número da PND está no código.** 30 + 50 + 1 é configuração de
  bloco. A arquitetura serve a qualquer composição.
- **`conta_para_percentual` é do bloco, não do tipo da questão.** É essa flag
  que tira a discursiva do denominador — serve a qualquer bloco informativo.
- **VARCHAR onde há evolução** (`dificuldade`, `modo_selecao`, `status`,
  `acao_ao_expirar`); ENUM só em `perguntas.tipo`, seguindo a migration 061.
- **O snapshot da tentativa é a fonte de verdade**: guarda as questões
  sorteadas, a ordem apresentada, as alternativas embaralhadas, os blocos, o
  tempo e a auditoria do sorteio. Validação, correção e percentual saem dele —
  alterar o banco depois não muda uma prova já iniciada.

## Regras

- **Sorteio só na criação da tentativa.** Retomar nunca gera novo conjunto.
- **Dificuldade por maior resto** (determinístico): 30 com 20/60/20 → 6/18/6;
  50 → 10/30/10.
- **Questões inéditas primeiro.** Faltando inéditas, completa com usadas e
  registra a ocorrência em `sorteio_auditoria_json` e na auditoria.
- **Percentual só nas objetivas sorteadas.** A nota da discursiva é
  informativa: não altera aprovação nem aptidão ao certificado.
- **Discursiva é obrigatória para o envio manual** (não para o automático por
  tempo, que envia o que estiver salvo).
- **Melhor tentativa manda**: uma tentativa pior depois de uma aprovada não
  reabre o item obrigatório.
- **Prazo é do servidor** (`iniciada_em` + `duracao_minutos`). O cronômetro do
  navegador é visual e é reconferido a cada 60s.
- **Concorrência**: `SELECT … FOR UPDATE` na faixa da inscrição + chave única
  `(quiz_id, inscricao_id, numero_tentativa)`.

## Salvamento automático

Numa prova de 5h30 o aluno não pode perder o que respondeu por sair da página,
fechar a aba ou cair a conexão. Por isso a resposta é gravada assim que é
dada, sem depender de nenhum botão:

- **objetiva**: grava ~0,6 s após a escolha;
- **discursiva**: grava 2 s após a digitação parar (evita salvar a cada tecla);
- **ao sair da aba ou fechar**: o que estiver pendente é enviado com
  `keepalive`, que sobrevive ao descarregamento da página;
- **falha de rede**: a alteração volta para a fila e é reenviada na próxima
  gravação; a tela avisa em vez de fingir que salvou.

Só o que mudou é enviado — `gravarRespostas` faz upsert apenas das questões
recebidas, então gravar uma questão nunca apaga as outras. `findQuizParaAluno`
devolve as respostas já salvas quando existe tentativa aberta, e é isso que faz
"voltar" reexibir tudo preenchido. O gabarito continua oculto: a tentativa
ainda não foi enviada.

Coberto por `tests/Unit/quiz_rascunho.php`.

## Aleatoriedade

`App\Services\Quiz\QuizRandomizerInterface` — produção usa
`QuizRandomizerSeguro` (`random_int`); os testes injetam
`QuizRandomizerSemente` (LCG próprio, determinístico). Nenhuma consulta usa
`ORDER BY RAND()`.

## Correção da discursiva

Hoje é manual, pela fila em
`/admin/area-curso/conteudo/quiz/discursivas`. A correção automatizada futura
entra implementando `App\Services\Quiz\CorretorDiscursivaInterface` e
injetando em `ConteudoQuizDiscursivaService`. **Esta entrega não tem chamada
de API, credencial nem dependência de fornecedor.**

## Cron obrigatório

O prazo é aplicado quando o aluno interage com a prova. Se ele fechar o
navegador e não voltar, a tentativa ficaria `em_andamento` para sempre — por
isso existe a rede de segurança:

```cron
*/5 * * * * /usr/bin/php "/home/desbloqueiacursos/public_html/scripts/cron_quiz_tentativas_expiradas.php" --limit=100 >> "/home/desbloqueiacursos/public_html/storage/logs/cron-quiz-expiradas.log" 2>&1
```

O script tem `--dry-run` e usa lock em `storage/tmp` contra execuções
sobrepostas. Ele aplica a regra configurada em cada quiz
(`enviar_automatico` ou `encerrar_sem_envio`).

## Como configurar um simulado

1. No item de conteúdo tipo quiz: duração (ex.: 330), modo **Sortear por
   blocos**, tentativas máximas, percentual mínimo, ação ao expirar.
2. Em **Blocos de sorteio**, criar os blocos. Para a PND de Pedagogia:
   `FGD` (30 objetivas), `PEDAGOGIA` (50 objetivas) e `DISCURSIVA`
   (1 discursiva, sem contar no percentual).
3. Cadastrar as questões no banco, cada uma com bloco, dificuldade e tema.
4. Conferir a validação de composição: ela **impede publicar** se o banco não
   comporta a configuração. A exceção consciente
   (`permitir_banco_insuficiente`) converte o erro em alerta explícito.

## Primeiro simulado em produção (PND de Pedagogia)

Estrutura já criada em produção, aguardando apenas o banco de questões.

| O quê | ID |
|---|---|
| Curso | 118 — "PND na prática - PEDAGOGIA" |
| Módulo | 363 — "17. Simulado oficial PND" |
| Item de conteúdo | 1537 — "Simulado oficial PND - Pedagogia" |
| Quiz | **24** |
| Bloco `FGD` | **1** — sorteia 30 objetivas |
| Bloco `PEDAGOGIA` | **2** — sorteia 50 objetivas |
| Bloco `DISCURSIVA` | **3** — sorteia 1 discursiva |

Configuração: 3 tentativas, 330 minutos, mínimo de 60%, modo `blocos`, envio
automático ao expirar, sem repetir questões entre tentativas, embaralhamento
de questões e alternativas ligado. Item publicado e obrigatório.

### Importar o banco de questões

O molde de SQL está em `sql/modelo_questoes_simulado_pnd.sql` — copie os
blocos, troque o conteúdo e rode no phpMyAdmin. Ele traz o par
pergunta + alternativas usando `LAST_INSERT_ID()`, o exemplo da discursiva e
as consultas de conferência.

Para a distribuição 20/60/20 nunca precisar completar com outra faixa, o
banco deve ter no mínimo:

- `FGD` (90 itens): 18 fáceis · 54 médias · 18 difíceis
- `PEDAGOGIA` (150 itens): 30 fáceis · 90 médias · 30 difíceis

Cadastre **mais de uma discursiva**: o sistema sorteia 1 por tentativa e tenta
não repetir entre as 3 tentativas do aluno.

### Conferir depois de importar

```bash
php scripts/verificar_banco_questoes.php --quiz=24
```

Faz o inventário por bloco e dificuldade, aponta questão com número errado de
alternativas corretas, dificuldade inválida, bloco divergente e enunciado
duplicado, e **simula um sorteio real** mostrando se sai 30 + 50 + 1. Sai com
código 1 se houver problema — dá para usar em pipeline.

## Testes

```bash
php tests/Unit/quiz_sorteio.php        # sorteio puro, determinístico (sem banco)
php tests/Unit/quiz_simulado_pnd.php   # integração/aceite do simulado
php tests/Unit/quiz_system.php         # regressão do quiz legado
```

Os dois últimos criam e removem a própria massa. Para não tocar a base real,
aponte para um banco de testes:

```bash
DB_DATABASE=dc_quiz_test DB_USERNAME=... DB_PASSWORD=... php tests/Unit/quiz_simulado_pnd.php
```
