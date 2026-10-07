# Spec Delta

## MODIFIED Requirements

### Requirement: Páginas do escopo
O tema caderno SHALL cobrir home, catálogo, categorias, curso, páginas institucionais, validação de certificado, página de erro, login, escolha pós-login, cadastro, recuperação e redefinição de senha, as etapas do checkout (inscrição, participantes, resumo, pagamento, comprovante e comprovante enviado), a área do aluno (abas cursos, pedidos, certificados e perfil), a aula, o quiz, a atividade e minha conta. O LMS legado (`/aluno/curso/...`, `/meus-cursos`, `/area-curso`) e as áreas de professor, revisor e admin MUST continuar fora do tema.

#### Scenario: Funil inteiro no mesmo tema
- **WHEN** um visitante sai do catálogo, entra no curso, faz login e conclui o checkout com `TEMA_PUBLICO=caderno`
- **THEN** todas as telas desse caminho são do tema caderno

#### Scenario: Estudo no mesmo tema
- **WHEN** o aluno abre `/v2/aluno`, entra num curso, abre uma aula, faz um quiz e envia uma atividade com `TEMA_PUBLICO=caderno`
- **THEN** todas essas telas são do tema caderno

#### Scenario: Área do aluno fora do escopo
- **WHEN** o aluno abre a área do aluno legada (`/meus-cursos`) com `TEMA_PUBLICO=caderno`
- **THEN** a página é a do LMS legado, sem o tema

### Requirement: Orçamento de desempenho
Cada página da vitrine, da autenticação e do checkout no tema MUST carregar no máximo 15 KB de JavaScript e 25 KB de CSS próprios do tema, medidos comprimidos, e atingir LCP de até 2,5 s e CLS de até 0,1 no Lighthouse em perfil celular (CPU 4× mais lenta, 4G lenta). As páginas da área do aluno, aula, quiz, atividade e minha conta MAY carregar adicionalmente um arquivo de JavaScript exclusivo dessas páginas, desde que o total de JavaScript do tema nelas fique em no máximo 25 KB comprimidos; esse arquivo MUST NOT ser carregado nas demais páginas. Fontes e ícones do tema MUST ser servidos pelo próprio site.

#### Scenario: Medição da home
- **WHEN** a home do tema é medida no Lighthouse em perfil celular
- **THEN** LCP ≤ 2,5 s, CLS ≤ 0,1, JS do tema ≤ 15 KB e CSS do tema ≤ 25 KB comprimidos

#### Scenario: JavaScript do aluno só onde é usado
- **WHEN** a home ou o catálogo do tema são carregados
- **THEN** o arquivo de JavaScript exclusivo das páginas do aluno não é requisitado

#### Scenario: Medição do quiz
- **WHEN** um quiz em andamento é carregado no tema
- **THEN** o JavaScript do tema somado fica em no máximo 25 KB comprimidos e o CSS do tema em no máximo 25 KB comprimidos

#### Scenario: Sem fontes de terceiros
- **WHEN** qualquer página do tema é carregada
- **THEN** nenhuma requisição é feita a Google Fonts ou ao CDN do Tabler Icons

## ADDED Requirements

### Requirement: Paridade da área do aluno
A área do aluno no tema SHALL exibir as mesmas abas, contagens, cursos (com progresso, situação, selo de certificado e acesso ao estudo), pedidos (com situação, total, retomada do pagamento e cancelamento com motivo quando permitido), certificados (versão online, PDF e validação) e perfil (dados mascarados e acesso à edição) que a V2, com os mesmos destinos e formulários.

#### Scenario: Pedido cancelável
- **WHEN** o aluno tem um pedido aguardando pagamento
- **THEN** a aba pedidos oferece retomar o pagamento e cancelar com motivo obrigatório, enviando para o mesmo endpoint da V2

#### Scenario: Aba inválida
- **WHEN** a URL traz `?aba=qualquer`
- **THEN** a aba cursos é exibida

### Requirement: Paridade da aula
A aula no tema SHALL exibir o sumário de módulos com o item atual, os concluídos e as etiquetas, o conteúdo de cada tipo com o mesmo tratamento de segurança da V2 (HTML sanitizado, conteúdo HTML e vídeo incorporado isolados em iframe com sandbox sem `allow-same-origin`), a navegação anterior e próxima, e a marcação e desmarcação de conclusão pelo mesmo endpoint, inclusive a conclusão automática de textos e HTML.

#### Scenario: Conteúdo HTML
- **WHEN** a aula é de conteúdo HTML
- **THEN** o conteúdo é exibido num iframe com `sandbox="allow-scripts allow-popups"` cuja altura se ajusta ao conteúdo

#### Scenario: Sumário no celular
- **WHEN** a aula é aberta num celular
- **THEN** o sumário fica acessível por um controle visível e a barra inferior oferece anterior, próxima e concluir, sem a barra de navegação geral sobreposta

#### Scenario: Item inacessível
- **WHEN** o item pedido está bloqueado ou não publicado
- **THEN** a página informa em texto que o conteúdo não está disponível, como a V2

### Requirement: Paridade do quiz
O quiz no tema SHALL oferecer os estados antes, em andamento, resultado e indisponível com as mesmas informações da V2; em andamento, os mesmos campos (`respostas[]`, `discursivas[]`, `tentativa_id` e o contexto da inscrição) e os mesmos endpoints de início, envio, salvamento automático, tempo e revisão. O gabarito e a rubrica MUST NOT aparecer no HTML enquanto a tentativa está em andamento.

#### Scenario: Salvamento automático
- **WHEN** o aluno marca uma alternativa durante a tentativa
- **THEN** a resposta é enviada ao endpoint de rascunho em até poucos segundos e a página informa em texto que foi salva

#### Scenario: Cronômetro
- **WHEN** o quiz tem duração e o tempo se esgota
- **THEN** a página consulta o endpoint de tempo, informa o fim do tempo em texto e recarrega para o estado definido pelo servidor

#### Scenario: Modo prova com discursiva pendente
- **WHEN** o aluno tenta enviar uma prova com questão discursiva sem resposta
- **THEN** o envio é interrompido e a revisão mostra as questões sem resposta

#### Scenario: Sem JavaScript
- **WHEN** o quiz em andamento é aberto sem JavaScript
- **THEN** todas as perguntas aparecem em sequência e o envio funciona pelo formulário

### Requirement: Paridade da atividade
A atividade no tema SHALL exibir enunciado, orientações, prazo, situação, a última entrega com nota, devolutiva e imagens, e o envio com texto e até 5 imagens pelo mesmo formulário e endpoint da V2; atividades externas SHALL levar ao mesmo destino da V2.

#### Scenario: Reenvio permitido
- **WHEN** a atividade foi devolvida para correção
- **THEN** a página mostra a devolutiva e oferece reenviar a resposta

### Requirement: Paridade de minha conta
Minha conta no tema SHALL oferecer os mesmos campos, validações, mensagens por campo e o carregamento das cidades por UF da V2, pelo mesmo formulário e endpoint.

#### Scenario: Erro de validação
- **WHEN** o aluno envia um e-mail inválido
- **THEN** a mensagem aparece em texto junto do campo e os demais valores digitados são mantidos
