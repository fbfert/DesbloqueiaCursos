## ADDED Requirements

### Requirement: Tema único
O sistema SHALL renderizar todas as páginas do site público e da área do aluno com o tema caderno, independentemente de configuração. `TEMA_PUBLICO`, `HOME_VERSION`, o parâmetro `?tema=` e a opção de template visual do portal MUST NOT mudar o visual dessas páginas.

#### Scenario: Chave antiga no .env
- **WHEN** o `.env` traz `TEMA_PUBLICO=v2` e um visitante abre `/v2/catalogo`
- **THEN** a página é renderizada no tema caderno

#### Scenario: Parâmetro de prévia
- **WHEN** um administrador abre `/v2?tema=v2`
- **THEN** a página é renderizada no tema caderno e o parâmetro é ignorado

#### Scenario: Home na raiz
- **WHEN** um visitante abre `/`, com qualquer valor de `HOME_VERSION`
- **THEN** ele vê a home do tema caderno

### Requirement: Rotas legadas redirecionam ao tema
Toda rota GET legada do site público e da área do aluno que tem equivalente no tema MUST redirecionar para ela, preservando os parâmetros que identificam o conteúdo (curso, turma, inscrição, módulo, item, pedido, código do certificado, categoria, busca). Páginas públicas MUST usar 301; páginas que exigem sessão ou fazem parte do checkout MUST usar 302.

#### Scenario: Login legado
- **WHEN** um visitante abre `/login`
- **THEN** recebe 301 para `/v2/login`

#### Scenario: Curso legado
- **WHEN** um visitante abre `/cursos/detalhe?curso_id=12`
- **THEN** recebe 301 para a página do curso 12 no tema

#### Scenario: Aula legada
- **WHEN** um aluno logado abre `/aluno/curso/5/12/30/modulo/7/conteudo/99`
- **THEN** recebe 302 para a aula do tema com inscrição 5, curso 12, turma 30, módulo 7 e item 99

#### Scenario: Área do aluno legada
- **WHEN** um aluno logado abre `/meus-cursos` ou `/pedidos`
- **THEN** recebe 302 para `/v2/aluno/`, na aba de pedidos no caso de `/pedidos`

#### Scenario: Validação por QR code antigo
- **WHEN** alguém lê o QR code de um certificado emitido antes desta mudança, que aponta para `/certificados/validar?codigo=ABC123`
- **THEN** recebe 301 para a validação no tema com o código `ABC123` e vê o resultado

#### Scenario: Rota legada sem sessão
- **WHEN** um visitante sem sessão abre `/minha-conta`
- **THEN** termina no login do tema, sem passar por nenhuma página com o visual legado

### Requirement: Links do tema não levam ao visual legado
Nenhum link, botão, formulário, redirecionamento ou URL de retorno gerado para o aluno nas páginas do tema, nos fluxos de pagamento ou nos certificados emitidos MUST apontar para uma página que renderize o visual legado.

#### Scenario: Abrir atividade
- **WHEN** o aluno toca em "Abrir atividade" numa aula do tema
- **THEN** a atividade abre no tema

#### Scenario: Categoria na home
- **WHEN** o visitante toca numa categoria da estante da home
- **THEN** abre o catálogo do tema filtrado por aquela categoria

#### Scenario: Curso gratuito
- **WHEN** o aluno conclui a inscrição num curso gratuito pelo checkout do tema
- **THEN** é levado à área do aluno do tema

#### Scenario: Termos no cadastro
- **WHEN** o visitante abre os termos de uso a partir do cadastro ou do aviso do botão do Google
- **THEN** os termos abrem no tema

#### Scenario: Certificado novo
- **WHEN** um certificado é emitido depois desta mudança
- **THEN** a URL de validação gravada nele e impressa no QR code aponta para a validação no tema

#### Scenario: Link de cupom promocional
- **WHEN** o visitante abre um link de cupom promocional de um curso
- **THEN** o cupom fica guardado e o visitante chega à página daquele curso no tema

### Requirement: Login exigido leva ao login do tema
Quando uma rota que exige sessão é acessada sem sessão, o sistema MUST levar o visitante ao login do tema e preservar o destino interno (página do tema ou do backoffice) para depois do login. Destinos externos MUST ser descartados.

#### Scenario: Sessão expirada na área de estudo
- **WHEN** a sessão do aluno expira e ele abre um item de aula pela URL legada
- **THEN** é levado ao login do tema

#### Scenario: Administrador sem sessão
- **WHEN** um visitante sem sessão abre `/admin/pedidos`
- **THEN** é levado ao login do tema e, depois de entrar com um perfil de administrador, chega a `/admin/pedidos`

#### Scenario: Destino externo
- **WHEN** o login do tema recebe um destino de outro domínio
- **THEN** o destino é ignorado e vale o destino padrão do perfil

### Requirement: Destino padrão por perfil após o login do tema
Depois de um login pelo tema sem destino informado, o sistema MUST levar a pessoa à área do seu perfil: administrador ao painel administrativo, professor ao painel do professor, revisor à área do revisor e aluno à tela de escolha do tema. A regra MUST valer para login por senha e pelo Google.

#### Scenario: Professor entra pelo login do tema
- **WHEN** um professor entra pelo `/v2/login` sem destino
- **THEN** chega ao painel do professor

#### Scenario: Aluno entra pelo login do tema
- **WHEN** um aluno entra pelo `/v2/login` sem destino
- **THEN** vê a tela "Para onde você deseja ir?" do tema

### Requirement: Sair da conta no tema
A confirmação de saída (`GET /logout`) SHALL ser exibida no tema, com as ações de confirmar e de cancelar. Confirmar MUST encerrar a sessão e levar ao login do tema com a mensagem de saída. Cancelar MUST voltar à área do perfil da pessoa.

#### Scenario: Aluno confirma a saída
- **WHEN** um aluno logado abre `/logout` e confirma
- **THEN** a sessão é encerrada e ele vê o login do tema com a mensagem "Você saiu da sua conta."

#### Scenario: Cancelar a saída
- **WHEN** um aluno logado abre `/logout` e cancela
- **THEN** volta para a área do aluno do tema

#### Scenario: Saída sem sessão
- **WHEN** um visitante sem sessão abre `/logout`
- **THEN** é levado ao login do tema

### Requirement: Pagamento concluído no tema
Depois de pagar online, o aluno SHALL voltar a uma página do tema que mostre o código e a situação do pedido, informe se o curso já está liberado ou se o pagamento ainda está em confirmação, e ofereça o caminho para a área do aluno. Só o dono do pedido MUST ver a página.

#### Scenario: Retorno do pagamento online
- **WHEN** o gateway devolve o aluno depois de um pagamento aprovado
- **THEN** ele vê no tema a confirmação da inscrição e o acesso aos cursos

#### Scenario: Pagamento ainda em confirmação
- **WHEN** o aluno volta do gateway antes de a confirmação do pagamento chegar
- **THEN** a página informa em texto que o pagamento está sendo confirmado e que o curso será liberado em seguida

#### Scenario: Pedido de outra pessoa
- **WHEN** um usuário abre a página de pagamento concluído de um pedido que não é dele
- **THEN** recebe a página de erro do tema, sem dados do pedido

#### Scenario: Cobrança criada antes da mudança
- **WHEN** o gateway devolve o aluno para `/checkout/sucesso?pedido_id=40`
- **THEN** ele é redirecionado para a página de pagamento concluído do tema, para o mesmo pedido

### Requirement: Certificado na versão online no tema
A versão online de um certificado SHALL ser exibida no tema, com as páginas do certificado, a ação de baixar o PDF (quando a pessoa tem acesso) e o link de validação. As regras de exibição pública, código e acesso ao PDF MUST ser as atuais.

#### Scenario: Versão online pela área do aluno
- **WHEN** o aluno toca em "Versão online" num certificado da sua área
- **THEN** o certificado abre no tema, com a ação de baixar o PDF

#### Scenario: Código inexistente
- **WHEN** alguém abre a versão online com um código que não existe
- **THEN** recebe a página 404 do tema

### Requirement: Checkout rápido no tema
A página de compra rápida (`/comprar`) SHALL ser renderizada no tema, mantendo a URL, os campos, as validações, o acompanhamento do pagamento e a disponibilidade controlada pela configuração do checkout rápido.

#### Scenario: Compra rápida habilitada
- **WHEN** o checkout rápido está ativo para um curso e o visitante abre `/comprar` desse curso
- **THEN** a página é exibida no tema, com o mesmo formulário de hoje

#### Scenario: Compra rápida desligada
- **WHEN** o checkout rápido está desligado
- **THEN** `/comprar` responde com a página 404 do tema

### Requirement: Descadastro da recuperação de pedido no tema
A página de descadastro dos e-mails de recuperação de pedido SHALL ser renderizada no tema, na mesma URL dos e-mails já enviados, com o mesmo resultado de hoje.

#### Scenario: Link de descadastro
- **WHEN** a pessoa abre o link de descadastro de um e-mail de recuperação de pedido
- **THEN** vê no tema a confirmação de que não receberá mais esses lembretes

### Requirement: Páginas institucionais dinâmicas no tema
Toda página publicada no cadastro de páginas e acessada pela própria rota SHALL ser exibida no tema, com o mesmo tratamento de conteúdo das institucionais do tema: HTML sanitizado e título único.

#### Scenario: Página cadastrada no admin
- **WHEN** existe uma página publicada com a rota `/regulamento` e um visitante a abre
- **THEN** a página é exibida no tema, com o título e o conteúdo cadastrados

#### Scenario: Página não publicada
- **WHEN** a rota corresponde a uma página não publicada
- **THEN** o visitante recebe a página 404 do tema

### Requirement: Páginas de erro no tema
As respostas 403, 404 e 500 de qualquer rota fora de `/admin`, `/professor`, `/revisor` e `/api` MUST usar a página de erro do tema, sem expor caminho de arquivo, SQL ou rastreamento de pilha, e com saídas para o catálogo e para a área da pessoa.

#### Scenario: Endereço inexistente
- **WHEN** um visitante abre `/pagina-que-nao-existe`
- **THEN** recebe 404 com a página de erro do tema

#### Scenario: Erro interno
- **WHEN** uma página pública gera uma exceção não tratada
- **THEN** a resposta é 500 com a página de erro do tema e o erro é registrado no log

#### Scenario: Material indisponível
- **WHEN** o aluno abre um material da aula ao qual não tem acesso
- **THEN** recebe 403 com a página de erro do tema

#### Scenario: Erro no admin
- **WHEN** uma rota de `/admin` responde com erro
- **THEN** o tratamento atual do admin é mantido

## MODIFIED Requirements

### Requirement: Mesmas rotas, dados e regras
O tema caderno MUST usar as mesmas rotas, os mesmos dados entregues pelos controllers e as mesmas regras de negócio da V2. As rotas novas são a de aplicar cupom no checkout, a de pagamento concluído e a da versão online do certificado. Nenhuma informação ou ação disponível na V2 ou no visual legado de uma página do escopo MUST deixar de existir no tema.

#### Scenario: Paridade da página do curso
- **WHEN** um curso tem turmas abertas, conteúdo programático e preço promocional
- **THEN** a página do curso no tema exibe as turmas, o conteúdo programático, o preço atual, o preço original e a ação de inscrição

#### Scenario: Paridade do checkout
- **WHEN** o aluno percorre o checkout no tema caderno
- **THEN** estão disponíveis compra para si, para terceiros e em lote, aplicação de cupom, pagamento online quando habilitado e PIX manual com envio de comprovante

### Requirement: Páginas do escopo
O tema caderno SHALL cobrir todo o site público e toda a área do aluno: vitrine (home, catálogo, categorias, curso, institucionais fixas e dinâmicas, validação e versão online de certificado, erro), autenticação (login, pós-login, cadastro, completar cadastro, recuperação e redefinição de senha, saída), todas as etapas do checkout (inclusive pagamento concluído e checkout rápido), descadastro de lembretes e o estudo (área do aluno, minha conta, aula, quiz e atividade). As áreas de professor, revisor e admin MUST continuar fora do tema.

#### Scenario: Funil inteiro no mesmo tema
- **WHEN** um visitante sai do catálogo, entra no curso, faz login, conclui o checkout e paga online
- **THEN** todas as telas desse caminho, inclusive o retorno do pagamento, são do tema caderno

#### Scenario: Estudo no mesmo tema
- **WHEN** o aluno abre `/v2/aluno`, entra num curso, abre uma aula, faz um quiz e envia uma atividade
- **THEN** todas essas telas são do tema caderno

#### Scenario: Área do aluno fora do escopo
- **WHEN** o aluno abre a área do aluno legada (`/meus-cursos`)
- **THEN** é redirecionado para a área do aluno do tema

#### Scenario: Backoffice fora do escopo
- **WHEN** um administrador abre `/admin/dashboard`
- **THEN** a página continua no layout do backoffice

## REMOVED Requirements

### Requirement: Seleção do tema por configuração
**Reason**: O caderno passa a ser o único visual do site público e da área do aluno (decisão de 15/10/2026). A alternância com a V2 deixa de existir.
**Migration**: Nada a fazer no `.env`. `TEMA_PUBLICO` pode ser apagado, porque não tem mais efeito. Para voltar à V2, é preciso reverter o código.

### Requirement: Prévia restrita a administradores
**Reason**: Sem alternância de tema, não há o que pré-visualizar.
**Migration**: Links com `?tema=` continuam funcionando e o parâmetro é ignorado.
