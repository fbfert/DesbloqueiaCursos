# tema-publico Specification

## Purpose
Definir como o site público escolhe e apresenta seu tema visual — o tema "caderno" e o retorno à V2 — e o contrato de movimento, desempenho e acessibilidade que o tema deve cumprir no aparelho real do aluno.

## Requirements

### Requirement: Seleção do tema por configuração
O sistema SHALL renderizar as páginas públicas do escopo com o tema definido em `TEMA_PUBLICO`. Com `caderno`, MUST usar a view do tema caderno quando ela existir para a página; com `v2`, valor ausente ou inválido, MUST usar a view V2.

#### Scenario: Chave em caderno
- **WHEN** `TEMA_PUBLICO=caderno` e um visitante abre `/v2/catalogo`
- **THEN** a página é renderizada com o layout e as views do tema caderno

#### Scenario: Chave ausente ou inválida
- **WHEN** `TEMA_PUBLICO` não está definida ou vale `qualquer`
- **THEN** todas as páginas são renderizadas exatamente como na V2

#### Scenario: Página ainda sem versão no tema
- **WHEN** `TEMA_PUBLICO=caderno` e a página não tem view no tema caderno
- **THEN** a página é renderizada com a view V2, sem erro

### Requirement: Mesmas rotas, dados e regras
O tema caderno MUST usar as mesmas rotas, os mesmos dados entregues pelos controllers e as mesmas regras de negócio da V2; a única rota nova é a de aplicar cupom no checkout. Nenhuma informação ou ação disponível na V2 de uma página do escopo MUST deixar de existir no tema.

#### Scenario: Paridade da página do curso
- **WHEN** um curso tem turmas abertas, conteúdo programático e preço promocional
- **THEN** a página do curso no tema exibe as turmas, o conteúdo programático, o preço atual, o preço original e a ação de inscrição

#### Scenario: Paridade do checkout
- **WHEN** o aluno percorre o checkout no tema caderno
- **THEN** estão disponíveis compra para si, para terceiros e em lote, aplicação de cupom, pagamento online quando habilitado e PIX manual com envio de comprovante

### Requirement: Aplicar cupom no checkout
O resumo do pedido no tema SHALL oferecer um campo para o aluno digitar e aplicar um cupom ao próprio pedido, enquanto o pedido ainda aceita cupom. A aplicação MUST seguir as regras de cupom já existentes (inclusive: cupom não se aplica a curso em promoção) e MUST devolver o aluno ao resumo do pedido no tema, com o resultado em texto.

#### Scenario: Cupom válido
- **WHEN** o aluno digita um cupom ativo e elegível no resumo do pedido e aplica
- **THEN** o aluno volta ao resumo no tema, com o cupom aplicado, o desconto e o novo total visíveis e uma mensagem de confirmação

#### Scenario: Cupom inválido
- **WHEN** o aluno aplica um código que não existe
- **THEN** o pedido não muda e o resumo mostra, junto do campo, uma mensagem em português com acentuação correta explicando o motivo

#### Scenario: Curso em promoção
- **WHEN** o pedido contém curso em promoção e o aluno aplica um cupom
- **THEN** o pedido não muda e o resumo informa que curso em promoção não aceita cupom

#### Scenario: Cupom promocional guardado
- **WHEN** o aluno chegou ao site por um link de cupom promocional e abre o resumo do pedido
- **THEN** o campo de cupom já vem preenchido com aquele código

#### Scenario: Pedido de outra pessoa
- **WHEN** um usuário envia um cupom para um pedido que não é dele
- **THEN** o pedido não muda e a tentativa é registrada como acesso negado

#### Scenario: Requisição sem CSRF
- **WHEN** chega uma aplicação de cupom sem token CSRF válido
- **THEN** a requisição é rejeitada sem alterar o pedido

### Requirement: Prévia restrita a administradores
O sistema SHALL permitir que um usuário com `conteudo.gerenciar` ative o tema caderno só na própria sessão, por `?tema=caderno`, e o desative por `?tema=v2`, independentemente de `TEMA_PUBLICO`. Para os demais visitantes, o parâmetro MUST ser ignorado.

#### Scenario: Administrador ativa a prévia
- **WHEN** um usuário com `conteudo.gerenciar` abre `/v2?tema=caderno` com `TEMA_PUBLICO=v2`
- **THEN** ele navega pelo tema caderno até desativar a prévia ou encerrar a sessão

#### Scenario: Visitante tenta a prévia
- **WHEN** um visitante sem sessão abre `/v2?tema=caderno` com `TEMA_PUBLICO=v2`
- **THEN** a página é renderizada na V2

#### Scenario: Prévia não vaza para outros usuários
- **WHEN** um administrador está com a prévia ativa
- **THEN** outros visitantes continuam vendo o tema definido por `TEMA_PUBLICO`

### Requirement: Páginas do escopo
O tema caderno SHALL cobrir home, catálogo, categorias, curso, páginas institucionais, validação de certificado, página de erro, login, escolha pós-login, cadastro, recuperação e redefinição de senha, e as etapas do checkout (inscrição, participantes, resumo, pagamento, comprovante e comprovante enviado). Área do aluno, aula, quiz, atividade e minha conta MUST continuar na V2.

#### Scenario: Funil inteiro no mesmo tema
- **WHEN** um visitante sai do catálogo, entra no curso, faz login e conclui o checkout com `TEMA_PUBLICO=caderno`
- **THEN** todas as telas desse caminho são do tema caderno

#### Scenario: Área do aluno fora do escopo
- **WHEN** o aluno abre `/v2/aluno` com `TEMA_PUBLICO=caderno`
- **THEN** a página é a da V2

### Requirement: Home sem o catálogo completo
A home no tema SHALL exibir abertura com a trilha, a estante de categorias, no máximo 6 cursos em destaque, os mais procurados, "o que você leva" e a chamada final, com acesso visível ao catálogo completo.

#### Scenario: Muitos cursos ativos
- **WHEN** existem 20 cursos públicos
- **THEN** a home exibe no máximo 6 em destaque e um caminho para o catálogo

### Requirement: Conteúdo nunca depende da animação
Todo conteúdo e toda ação das páginas do tema MUST estar presentes e utilizáveis no HTML entregue pelo servidor, sem depender de JavaScript. Estados iniciais ocultos para animação MUST ser removidos em no máximo 2,5 segundos, mesmo se o JavaScript falhar.

#### Scenario: JavaScript desativado
- **WHEN** a home é aberta com JavaScript desativado
- **THEN** título, números, cursos, categorias e botões aparecem no estado final e funcionam

#### Scenario: Script de movimento falha
- **WHEN** o script do tema lança erro antes de iniciar as animações
- **THEN** em até 2,5 segundos nenhum elemento permanece oculto

### Requirement: Movimento reduzido
Quando o visitante pede movimento reduzido (`prefers-reduced-motion: reduce`), o tema MUST exibir todos os elementos no estado final, sem animações de entrada, sem cenas e sem transições entre páginas.

#### Scenario: Celular com movimento reduzido
- **WHEN** a preferência de movimento reduzido está ativa e a home é aberta
- **THEN** a trilha aparece desenhada, o carimbo aparece aplicado e nada se move

### Requirement: Modo leve
Em aparelhos com economia de dados ativa ou com até 2 núcleos de processamento, o tema SHALL manter só as cenas principais de cada página, sem efeitos de revelação ao rolar nem efeitos secundários.

#### Scenario: Economia de dados
- **WHEN** o navegador informa economia de dados ativa
- **THEN** a cena da trilha acontece em versão curta e as seções não animam ao entrar na tela

### Requirement: Transição do curso
Em navegadores com suporte a View Transitions, ao abrir um curso a partir de um card, a capa do card SHALL transicionar para a capa da página do curso. Sem suporte, a navegação MUST funcionar normalmente.

#### Scenario: Chrome no Android
- **WHEN** o aluno toca no card de um curso no catálogo
- **THEN** a capa se desloca e cresce até a posição da capa na página do curso

#### Scenario: Navegador sem suporte
- **WHEN** o navegador não suporta View Transitions
- **THEN** a página do curso abre como uma navegação comum

### Requirement: Orçamento de desempenho
Cada página do tema MUST carregar no máximo 15 KB de JavaScript e 25 KB de CSS próprios do tema, medidos comprimidos, e atingir LCP de até 2,5 s e CLS de até 0,1 no Lighthouse em perfil celular (CPU 4× mais lenta, 4G lenta). Fontes e ícones do tema MUST ser servidos pelo próprio site.

#### Scenario: Medição da home
- **WHEN** a home do tema é medida no Lighthouse em perfil celular
- **THEN** LCP ≤ 2,5 s, CLS ≤ 0,1, JS do tema ≤ 15 KB e CSS do tema ≤ 25 KB comprimidos

#### Scenario: Sem fontes de terceiros
- **WHEN** qualquer página do tema é carregada
- **THEN** nenhuma requisição é feita a Google Fonts ou ao CDN do Tabler Icons

### Requirement: Acessibilidade
As páginas do tema MUST atender WCAG 2.1 AA: contraste dos pares de texto e fundo, foco visível, operação completa por teclado (inclusive estante, divisórias de categoria e folha de filtros), rótulos visíveis em campos e erros em texto. Anotações manuscritas MUST NOT ser a única fonte de nenhuma informação.

#### Scenario: Estante pelo teclado
- **WHEN** o visitante navega a estante com Tab e Enter
- **THEN** cada lombada recebe foco visível e abre a categoria correspondente

#### Scenario: Erro de formulário
- **WHEN** o CPF informado no checkout é inválido
- **THEN** o campo é marcado e uma mensagem em texto explica o erro, junto do campo
