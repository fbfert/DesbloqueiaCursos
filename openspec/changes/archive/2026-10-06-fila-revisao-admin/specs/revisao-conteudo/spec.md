# Spec Delta

## Purpose

Permitir que especialistas externos revisem o conteúdo dos cursos atribuídos sem poder alterá-lo, e que o gestor de conteúdo trate cada apontamento registrado, com resposta rastreável.

## ADDED Requirements

### Requirement: Fila de revisão restrita a gestores de conteúdo
O sistema SHALL disponibilizar no admin uma fila com os apontamentos de revisão de todos os cursos, acessível apenas a usuários com a permissão `conteudo.gerenciar`.

#### Scenario: Gestor acessa a fila
- **WHEN** um usuário com `conteudo.gerenciar` abre `/admin/revisoes`
- **THEN** o sistema exibe a fila de apontamentos de todos os cursos

#### Scenario: Revisor tenta acessar a fila
- **WHEN** um usuário apenas com o perfil Revisor abre `/admin/revisoes`
- **THEN** o sistema nega o acesso e não exibe nenhum apontamento

#### Scenario: Visitante sem sessão
- **WHEN** alguém sem sessão abre `/admin/revisoes`
- **THEN** o sistema redireciona para o login

### Requirement: Ordem de tratamento da fila
A fila SHALL listar primeiro os apontamentos em aberto e, dentro de cada situação, os mais graves primeiro (erro, impreciso, dúvida, sugestão), e depois os mais antigos. Apontamentos excluídos MUST NOT aparecer.

#### Scenario: Erro aberto no topo
- **WHEN** existem um apontamento `sugestao` aberto antigo e um `erro` aberto recente
- **THEN** o `erro` aparece antes da `sugestao`

#### Scenario: Triados depois dos abertos
- **WHEN** existem apontamentos abertos e apontamentos já aceitos
- **THEN** todos os abertos aparecem antes de qualquer aceito

#### Scenario: Apontamento excluído pelo revisor
- **WHEN** o revisor excluiu um apontamento
- **THEN** ele não aparece na fila

### Requirement: Filtros da fila
A fila SHALL permitir filtrar por curso, por severidade e por situação, combinando os filtros informados. Valores de filtro inválidos MUST ser ignorados, sem erro.

#### Scenario: Filtro por curso e severidade
- **WHEN** o gestor filtra pelo curso 125 e severidade `erro`
- **THEN** a fila mostra apenas os apontamentos `erro` do curso 125

#### Scenario: Filtro inválido
- **WHEN** a URL traz `severidade=qualquer`
- **THEN** a fila é exibida como se o filtro de severidade não tivesse sido informado

### Requirement: Identificação do apontamento
Cada apontamento da fila SHALL exibir o curso, o tipo e a descrição legível do alvo (título do item ou módulo, ou início do enunciado da pergunta ou do texto da alternativa), o trecho citado, o comentário, a severidade, o autor e a data. Se o alvo não existir mais, o sistema SHALL indicar que ele foi removido, sem falhar.

#### Scenario: Apontamento em pergunta de quiz
- **WHEN** o alvo é uma pergunta de quiz
- **THEN** a fila mostra o início do enunciado dessa pergunta junto do comentário

#### Scenario: Alvo removido
- **WHEN** o item de conteúdo comentado foi excluído depois do apontamento
- **THEN** a fila mostra o apontamento com a indicação de alvo removido

### Requirement: Triagem de apontamento em aberto
O gestor SHALL poder aceitar, recusar ou marcar como resolvido um apontamento em aberto. A triagem MUST registrar quem triou, quando, a resposta e gerar registro de auditoria. Apontamento já triado MUST NOT ser triado de novo.

#### Scenario: Aceitar com resposta opcional
- **WHEN** o gestor aceita um apontamento aberto sem escrever resposta
- **THEN** o apontamento passa a `aceito`, com o gestor e a data da triagem registrados

#### Scenario: Recusar sem resposta
- **WHEN** o gestor tenta recusar um apontamento sem escrever resposta
- **THEN** o sistema não altera o apontamento e pede a justificativa da recusa

#### Scenario: Recusar com resposta
- **WHEN** o gestor recusa um apontamento informando a resposta
- **THEN** o apontamento passa a `recusado` e a resposta fica visível na fila e para o revisor

#### Scenario: Triagem repetida
- **WHEN** o gestor tenta triar um apontamento que já está `resolvido`
- **THEN** o sistema não altera o apontamento e informa que ele já foi triado

#### Scenario: Requisição sem token CSRF
- **WHEN** chega uma triagem sem token CSRF válido
- **THEN** o sistema rejeita a requisição sem alterar o apontamento

### Requirement: Alerta de erros abertos na tela do curso
As telas de edição e de detalhe do curso no admin SHALL exibir quantos apontamentos de severidade `erro` estão em aberto para aquele curso, com link para a fila filtrada pelo curso, a usuários com `conteudo.gerenciar`. Sem erros abertos, ou para quem não pode abrir a fila, o alerta MUST NOT aparecer.

#### Scenario: Curso com erros abertos
- **WHEN** o curso 125 tem 3 apontamentos `erro` em aberto e o gestor abre sua edição
- **THEN** a tela mostra o alerta com o número 3 e um link para `/admin/revisoes` filtrado pelo curso 125

#### Scenario: Curso sem erros abertos
- **WHEN** o curso não tem apontamento `erro` em aberto
- **THEN** nenhum alerta de revisão é exibido

#### Scenario: Usuário só com leitura de conteúdo
- **WHEN** um usuário com `conteudo.ver` e sem `conteudo.gerenciar` abre o detalhe de um curso com erros abertos
- **THEN** nenhum alerta de revisão é exibido

### Requirement: Acesso pelo menu do admin
O menu lateral do admin SHALL exibir o item "Revisões" apenas para usuários com `conteudo.gerenciar`.

#### Scenario: Menu para gestor
- **WHEN** um gestor com `conteudo.gerenciar` abre qualquer tela do admin
- **THEN** o menu exibe o item "Revisões" apontando para `/admin/revisoes`

#### Scenario: Menu para quem não gerencia
- **WHEN** um usuário do admin sem `conteudo.gerenciar` abre o admin
- **THEN** o item "Revisões" não aparece
