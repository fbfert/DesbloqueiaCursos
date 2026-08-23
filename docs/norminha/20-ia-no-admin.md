# Configurar a IA pela tela do admin

`/admin/tutor-norminha/ia` — 23/08/2026

Até aqui, ligar a IA exigia acesso ao servidor: chave e modelo só existiam no
`.env`. Esta tela passa os dois para o painel, junto com um teto de gasto, um
botão de teste e o consumo do mês.

## O achado que veio antes da tela

`APP_KEY` **não existia** no `.env` de produção. Sem ela, `App\Support\Crypto::encrypt()`
devolve `null` — e o `PagamentoGatewayConfiguracao`, que já usava esse
mecanismo, gravava `null` por cima do segredo. Ou seja: quem digitasse a chave
do gateway de pagamento no admin veria "salvo com sucesso" e o sistema teria
descartado o valor.

Isso explica o estado encontrado: `api_key_encrypted` **NULL** e o último teste
do gateway com status `erro`, de 20/07/2026.

A chave foi gerada antes de qualquer outra coisa. Sem ela, esta tela teria o
mesmo defeito — e um formulário que engole a credencial em silêncio é pior que
não ter formulário.

## Onde a chave fica

Cifrada (AES-256-CBC + HMAC) em `tutor_configuracoes`, com a chave de cifragem
no `.env`. A separação é o ponto: **um dump do banco não entrega a credencial**,
porque `APP_KEY` não está no banco. Depois de um backup que ficou público por
três meses, guardar segredo em texto puro no banco não é uma opção.

O `.env` continua tendo precedência. Com `OPENAI_API_KEY` definida lá, a tela
informa a origem e desabilita o campo — é o arranjo mais fechado, em que nem
quem administra o painel troca a credencial.

A tela **nunca** devolve a chave ao navegador, nem os últimos dígitos. Um
prefixo já é informação para quem chegou ali sem dever. "Está configurada?" é
respondido por origem mais botão de teste, que diz algo melhor que quatro
dígitos: se funciona.

Como o campo chega sempre vazio, salvar em branco **não apaga** a chave. Se
apagasse, mudar o teto destruiria a credencial. Para remover existe uma caixa
explícita.

## Modelo: lista fechada, com preço à vista

Campo livre deixaria digitar um modelo inexistente, e o erro só apareceria na
primeira pergunta de um aluno — como uma falha de rede sem explicação.

`App\Support\NorminhaModelos` é a fonte única: id, faixa e os três preços
(entrada, entrada em cache, saída), conferidos na documentação oficial em
23/08/2026.

O preço de entrada em cache é **dez vezes menor**, e isso não é detalhe: a
Norminha reenvia o mesmo prompt de sistema a cada pergunta, e é essa parte que
o provedor mantém em cache. Ignorar o desconto superestimaria o gasto em várias
vezes.

## Teto de gasto

O custo sai de `norminha_mensagens`, que já registra modelo e as três contagens
de token por resposta. **Não há tabela nova de contabilidade**: uma segunda
tabela seria uma cópia que um dia diverge da origem.

Toda janela de tempo é calculada em SQL. O PHP roda em UTC e o MySQL em UTC−3;
uma data "de hoje" calculada em PHP consulta o dia errado das 21h à meia-noite.
Este projeto já teve um bloqueio de login inerte por exatamente isso.

Duas decisões que valem explicitar:

**Modelo fora do catálogo custa `NULL`, não zero.** Zero seria lido como "de
graça" e furaria o teto em silêncio. A tela conta essas respostas à parte e
avisa que elas não entram na soma.

**O teto falha fechando.** Se a contagem não puder ser feita, a resposta é "não
pode gastar". O contrário liberaria despesa ilimitada exatamente quando ninguém
está conseguindo medi-la. Verificado por mutação.

Ao atingir o teto, a IA some e o chat volta ao modo determinístico: o aluno
continua obtendo progresso, retomada e certificado, e as perguntas livres
recebem "ainda não consigo responder isso". Sem tela de erro.

Este é um freio nosso, calculado a partir do que registramos. **Não substitui**
o limite do painel da OpenAI, que é o único que o provedor garante.

## `OPENAI_ENABLED` mudou de papel

Antes precisava valer `true` para qualquer chamada acontecer, o que obrigaria
acesso ao servidor e esvaziaria esta tela. Agora:

| valor | efeito |
|---|---|
| `false` | desligado sempre, aconteça o que acontecer no admin |
| `true` | ligado |
| ausente | ligado quando existirem chave **e** modelo |

Continua sendo a palavra final da infraestrutura. O interruptor de produto
segue separado e independente: `tutor_ia_ativo`, no banco. Nenhuma das duas
chaves sozinha habilita a IA.

## O botão de teste

Faz uma chamada mínima ao provedor e informa modelo, latência e **quanto o teste
custou**. Gasta alguns tokens de propósito: usar a chave é a única forma honesta
de responder se a chave funciona.

Ele distingue falha local de falha do provedor. Um `payload_invalido` é defeito
nosso, e dizer "a chave falhou" nesse caso mandaria alguém trocar uma
credencial que estava correta. Falhas do provedor viram texto acionável —
`credencial` vira "a OpenAI recusou a chave; confira se foi copiada inteira e
se continua ativa no painel".

## Cobertura

`tests/Unit/norminha_ia_admin.php`, 17 casos. Verificados por mutação:

- campo vazio passar a apagar a chave → **pego**;
- modelo desconhecido passar a custar zero → **pego**;
- teto passar a falhar abrindo → **pego**.

Para o terceiro, `NorminhaIaService` recebeu injeção do serviço de custo: sem
isso não havia como provar que ele falha fechando.

## Um teste que passava por acidente

`norminha_tool_loop` afirmava "IA indisponível" construindo o serviço com a
configuração real e contando com `OPENAI_ENABLED` ausente. O `.env` do dev nunca
teve essa variável, e o `Env` da aplicação sequer era carregado nos testes — o
bootstrap tinha leitor próprio. Passava por coincidência.

Quando a chave passou a poder vir do banco, "ausente" deixou de significar
"desligado" e o teste passou a depender do que estivesse gravado. Agora a
indisponibilidade é declarada, não herdada do ambiente. O bootstrap também
passou a popular `App\Core\Env` — sem isso, qualquer teste que use `Crypto`
falharia por falta de ambiente e não por defeito no código.

---

## O teste falhava em todos os modelos — e a tela escondia o porquê

Relatado em 23/08/2026, com chave real configurada: `requisicao_recusada,
HTTP 400` em todos os modelos experimentados.

A OpenAI tinha dito exatamente o que estava errado:

> `Unsupported parameter: 'temperature' is not supported with this model.`

A família GPT-5 inteira recusa `temperature`, e o `config/ai.php` mandava `0.2`
por padrão. Ou seja: a integração **não funcionava com nenhum dos cinco modelos
oferecidos na tela**.

`OPENAI_TEMPERATURE` passou a nascer vazia, e o catálogo diz quem aceita o
parâmetro. Modelo fora do catálogo continua recebendo o que estiver configurado
— apagar em silêncio uma opção que alguém escolheu de propósito seria pior que
deixar o provedor recusar com uma mensagem clara.

### O defeito atrás do defeito

O parâmetro a mais custou minutos. O que custou caro foi a tela ter dito
**"pode ser um modelo indisponível para esta conta"** — um palpite meu — enquanto
a explicação exata do provedor ia só para o log. O responsável ficou trocando de
modelo às cegas, procurando um problema de conta que não existia.

`OpenAIService::falha()` agora carrega `provedor_mensagem`, e a tela mostra o
texto do provedor palavra por palavra, junto do código interno. Esse texto vai
para tela de administração e nunca para o aluno: é diagnóstico de integração,
não conversa.

**A regra que fica:** quando um sistema externo explica a recusa, a explicação
dele vale mais que qualquer texto nosso. Registrar a explicação no log e mostrar
um palpite na tela é ter a resposta e escondê-la de quem precisa dela.
