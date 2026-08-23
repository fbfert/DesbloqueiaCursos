# O que sai do servidor quando a IA está ligada

Ligar a Norminha com IA significa enviar dado de aluno a um operador estrangeiro. Este documento diz
**exatamente** quais campos saem, quais não saem, e por quê — para que a decisão jurídica seja tomada
sobre fatos, não sobre suposição.

> Enquanto `OPENAI_ENABLED=false` **nada sai**. Nenhuma requisição é montada, nenhum socket é aberto.
> Verificado por teste: com a integração desligada, a chamada retorna em 0 ms.

## O que É enviado

### 1. Instruções de sistema

Texto fixo, definido em `NorminhaPromptService`. Não contém dado de aluno. Pode incluir a orientação
complementar escrita pela administração, que também é texto institucional.

### 2. Contexto acadêmico

| Campo | Exemplo | Por que vai |
|---|---|---|
| `inscricao_id` | `17` | identificador interno; sem ele o modelo não sabe do que se fala |
| `curso` | `"Avaliação de Imóveis"` | nome público do curso |
| `turma` | `"Turma 2026/2"` | nome público da turma |
| `modulo_atual` | `"Métodos de avaliação"` | título do módulo |
| `item_atual` | `"Método comparativo direto"` | título da aula |
| `contexto_avaliacao` | `true` / `false` | aciona o guardrail pedagógico |

### 3. Conteúdo oficial do curso

Trechos de `conteudo_textos` e `conteudo_htmls` — o material que a instituição publicou e que o aluno
já pode ler. Teto de 4 trechos e 8.000 caracteres.

### 4. A pergunta do aluno, e o histórico curto

O texto que o próprio aluno escreveu, mais até 12 mensagens anteriores da mesma conversa (teto de
6.000 caracteres). Se o aluno escrever seu CPF numa pergunta, esse texto vai — **como qualquer
sistema de chat**. A minimização técnica não alcança o que o titular digita por vontade própria.

## O que NÃO é enviado

Nenhum destes campos existe no payload, e há teste que falha se algum aparecer:

- **nome, e-mail, telefone, CPF** do aluno ou do pagador
- **`usuario_id`** — removido por `paraModelo()`. A identidade nunca é escolhida pelo modelo
- **notas, gabaritos, alternativas corretas, chaves de correção** — excluídos por estrutura
- **dados de pagamento, pedidos, comprovantes**
- **hash de senha, token de sessão, cookie, chave de API**
- **prompt completo em log** — os logs guardam ids técnicos, status, latência e contagem de tokens

## Retenção

| Onde | O quê | Por quanto tempo |
|---|---|---|
| **Provedor** | nada | `store=false`; sem `previous_response_id` |
| **`norminha_conversas` / `_mensagens`** | conversa íntegra | sem expurgo automático na V1 |
| **`norminha_uso`** | contadores | 90 dias, com rotação |
| **`storage/logs`** | ids, status, latência, tokens | rotação do servidor |

O texto do aluno é gravado íntegro **de propósito**: sem ler a pergunta real, a telemetria não
responde a pergunta que justifica o projeto. A V1 não expõe exclusão de conversa pelo aluno —
política de retenção é etapa posterior, alinhada à LGPD.

## Base para a decisão jurídica

1. **Há transferência internacional de dado pessoal.** O contexto acadêmico e a pergunta do aluno
   trafegam para servidor do provedor.
2. **Não há dado sensível deliberado.** Nenhum campo de saúde, biometria, origem racial ou convicção.
3. **Não há decisão automatizada com efeito jurídico.** A Norminha explica matéria; não aprova, não
   reprova, não emite certificado, não altera nota. Todas as ferramentas são de leitura.
4. **O titular consegue não usar.** O chat é iniciado pelo aluno.
5. **Falta atualizar** `/v2/politica-de-privacidade` para refletir a transferência.

O item 5 é pré-requisito de habilitação, não de código.

## Como reverificar

```bash
php tests/Unit/norminha_hardening.php   # payload sem campo pessoal, logs sem segredo
php tests/Unit/norminha_prompt.php      # contexto sem PII
php tests/Unit/norminha_openai.php      # chave nunca vaza
```
