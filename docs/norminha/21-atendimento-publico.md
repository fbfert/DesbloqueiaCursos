# A Norminha para quem ainda não tem conta

23/08/2026

## O problema

Muita gente trava antes de virar aluno: no cadastro, depois no login, depois em
como comprar. Até aqui o visitante deslogado recebia da Norminha apenas um
convite para entrar — ou seja, a ajuda só existia para quem já não precisava
dela.

## O desenho

Um serviço **separado**, `App\Services\NorminhaPublicoService`, e um endpoint
próprio, `POST /api/norminha/publico`.

A separação não é organizacional, é de segurança. O `NorminhaService` responde
sobre matrícula, progresso e certificado, tudo derivado de uma sessão. Fosse um
`if` dentro dele, bastaria um caminho esquecido para um visitante anônimo cair
no ramo do aluno — e o pior é que funcionaria em silêncio, devolvendo a
matrícula de outra pessoa como se fosse resposta.

O serviço público não consulta `usuarios`, `inscricoes`, `pedidos`,
`certificados` nem nenhuma tabela `norminha_*` de conversa. Não executa SQL e
não lê a sessão. Os únicos dados que ele toca são o catálogo público, pelo mesmo
`CursoService::listPublic()` que a página de catálogo usa — reusar o caminho já
validado em vez de reimplementar a regra.

## O que ele responde

Quatro atalhos abrem a conversa, que são as quatro dúvidas que travam o
visitante:

| Atalho | Para onde leva |
|---|---|
| Já tenho cadastro | login, e recuperação de senha ao lado |
| Quero me cadastrar | a lista do que é preciso ter em mãos, e o link |
| Vamos escolher um curso? | alguns cursos com carga horária e preço, e o catálogo |
| Como faço para comprar? | os quatro passos, do catálogo ao pagamento |

O texto livre é classificado por palavra-chave — não por modelo de linguagem.
Cobre senha esquecida, preço, certificado, formas de pagamento e pedido de
atendimento humano. **O que não casa cai em "ainda não sei responder isso"**,
com os atalhos e o contato. Nunca uma resposta inventada.

## Sem modelo de linguagem, por enquanto

Endpoint público e sem sessão é a superfície mais fácil de abusar que existe:
um robô gastaria a cota da OpenAI sem esforço. As respostas aqui são escritas à
mão. Se um dia a IA entrar neste caminho, precisa vir com teto próprio e desafio
anti-robô — é decisão de produto, não detalhe técnico.

## Nada do que o visitante escreve é guardado

Não há persistência de conversa. Quem não tem conta não consentiu com coisa
alguma, e guardar o que essa pessoa digita criaria um acervo de dado pessoal sem
base legal, sem política de retenção e sem titular identificável para exercer
direito nenhum.

O único registro é um contador por origem, em `norminha_uso_publico`, e mesmo o
IP entra como **hash** com a `APP_KEY` como sal. Sem a APP_KEY, que não está no
banco, um dump dessa tabela não diz de quem são as linhas. Endereço de IP é dado
pessoal, e este projeto acabou de aprender o que custa guardar dado pessoal sem
necessidade.

Limites: 15 mensagens por 5 minutos e 120 por dia, por origem. O freio **falha
abrindo**, ao contrário do teto de gasto: aqui não há dinheiro em jogo, e recusar
atendimento a quem está tentando criar uma conta é o pior dos dois erros.

## A Norminha voltou às telas de cadastro e login

Em 22/08 eu havia suprimido o convite nessas páginas, com o argumento de que
convidar a entrar quem já está entrando é circular. O argumento continua certo
para um convite — e é irrelevante agora, porque ali não há mais convite e sim
atendimento. Suprimir a Norminha justamente na tela de cadastro seria tirá-la do
único lugar onde este trabalho existe para servir.

## Cobertura

`norminha_visitante.php` (8 casos) trava a fronteira do lado da tela: o visitante
recebe chat, recebe os atalhos públicos, **nunca** recebe ação de aluno, e o
componente marca `data-publico` para o JavaScript saber qual endpoint chamar —
quem decide é o servidor, porque deixar o navegador dizer se há sessão seria
confiar no cliente sobre a própria identidade.

`norminha_arquitetura.php` trava do lado do servidor: o serviço público não
menciona tabela de aluno, não executa SQL, não lê sessão; a rota pública não
exige `auth.api` e a do aluno exige; e o contador não grava texto do visitante.
