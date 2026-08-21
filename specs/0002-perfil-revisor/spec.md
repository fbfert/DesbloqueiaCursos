# Spec: Perfil Revisor com comentários de revisão

## Objetivo

Permitir que um especialista externo — advogado, professor da área, revisor técnico — leia o conteúdo de cursos que lhe forem atribuídos e registre apontamentos por escrito, vinculados ao ponto exato do material, **sem qualquer poder de alteração direta**. Os apontamentos entram numa fila que o responsável pelo conteúdo trata uma a uma, com resposta registrada.

## Contexto

O portal tem oito cursos preparatórios produzidos em escala — os sete da coleção PND (118 a 124) e o de OAB 1ª Fase (125). Todos os documentos de entrega desses cursos terminam com a mesma pendência em aberto: *"revisão por professor da área antes de divulgar"*. Nenhum deles tem por onde essa revisão acontecer.

Hoje há dois caminhos, e os dois falham:

- **vincular o revisor como professor** dá acesso de leitura à área do curso, mas a tela de edição de conteúdo HTML só existe no admin, e não há tela de professor para o banco de questões. O revisor não enxerga as questões, que são justamente o que mais precisa de revisão;
- **dar acesso de admin** resolve o acesso e cria o problema oposto: um revisor externo passa a poder alterar conteúdo publicado, sem trilha do que mudou nem de quem pediu.

O RBAC do projeto (`perfis`, `perfil_permissoes`, `usuario_perfis`) e o vínculo pessoa-curso (`curso_pessoas_vinculadas`) já suportam o que falta. O que não existe é o perfil, o escopo e o lugar para guardar o apontamento.

## Problema

Não há como um especialista externo revisar conteúdo do portal e registrar o que encontrou de forma rastreável, sem receber junto o poder de editar o que revisa.

A consequência prática, hoje: a revisão ou não acontece, ou acontece por fora — em conversa, e-mail ou planilha solta — sem vínculo com o item revisado, sem histórico e sem garantia de que o apontamento foi tratado.

## Solução esperada

- Criar o perfil **Revisor**, com permissões de leitura e de comentário, e **sem** permissão de gestão de conteúdo
- Escopar o acesso do revisor aos cursos em que ele estiver vinculado, reaproveitando `curso_pessoas_vinculadas`
- Criar uma área do revisor que exiba módulos, aulas e bancos de questões dos cursos atribuídos, com gabarito e explicação visíveis
- Permitir comentário vinculado a um item de conteúdo, a uma questão ou a uma alternativa, classificado por severidade
- Oferecer ao responsável pelo conteúdo uma fila no admin para aceitar, recusar ou marcar como resolvido, com resposta registrada
- Manter o revisor informado do desfecho de cada apontamento

## Escopo

- Perfil `revisor` e permissões `area_curso.revisor.ver` e `area_curso.revisor.comentar`
- Novo valor `revisor` no enum `tipo_pessoa` de `curso_pessoas_vinculadas`
- Service de escopo acadêmico do revisor, no mesmo desenho do `ProfessorAcademicScopeService`
- Tabela `revisao_comentarios`, com alvo polimórfico, severidade, status e resposta
- Área do revisor: painel de cursos atribuídos, árvore do curso, leitura de aula e leitura de banco de questões
- Fila de revisão no admin, com triagem e resposta
- Exclusão de comentário por `TrashService`, com justificativa, e registro em `AuditService`

## Fora de escopo

- Seleção de trecho dentro do iframe da aula por `postMessage` — o comentário se prende ao item, e o revisor cola o trecho num campo. Fica para uma segunda versão
- Notificação por e-mail de comentário novo ou respondido
- Edição de conteúdo pelo revisor, em qualquer hipótese
- Aplicação automática do apontamento ao conteúdo
- Exportação da fila para planilha ou PDF
- Revisão de cursos por convite público ou link sem autenticação

## Usuários afetados

- **Revisor**: perfil novo. Lê os cursos atribuídos e registra apontamentos
- **Gestor de conteúdo / administrador**: recebe a fila, triagem e responde
- **Professor**: não é afetado. As permissões e telas de professor permanecem como estão
- **Aluno**: não é afetado. Nada do que o revisor escreve é exibido ao aluno

## Regras de negócio

1. O revisor só enxerga cursos em que exista vínculo ativo com `tipo_pessoa = 'revisor'`. Sem vínculo, o curso não aparece e a rota é barrada
2. O revisor **nunca** grava em `conteudo_*` nem em `conteudo_quiz_*`. A garantia é de permissão, não de interface
3. Todo comentário tem severidade: `erro`, `impreciso`, `sugestao` ou `duvida`
4. Todo comentário nasce com status `aberto` e só o gestor de conteúdo pode mudá-lo para `aceito`, `recusado` ou `resolvido`
5. Comentário de severidade `erro` com status `aberto` é impedimento de publicação declarado — o curso pode ser publicado assim, mas a tela avisa quantos existem
6. O revisor lê o banco de questões com gabarito e explicação à vista. Isso é intencional: é o gabarito que ele precisa julgar
7. Comentário excluído passa por `TrashService::record()` com justificativa, como toda exclusão do projeto
8. O revisor pode editar o próprio comentário enquanto ele estiver `aberto`; depois de triado, não

## Critérios de aceite

- Um usuário com o perfil Revisor, vinculado ao curso 125, acessa a área do revisor e vê apenas esse curso
- O mesmo usuário recebe 403 ao tentar qualquer rota de gestão de conteúdo, de admin ou de professor
- O revisor abre uma aula `tipo=html` e a vê renderizada como o aluno veria
- O revisor abre o banco de questões de um quiz e vê enunciado, quatro alternativas, gabarito e explicação
- O revisor registra um comentário em uma questão, com severidade, e ele aparece na fila do admin
- O gestor de conteúdo recusa o comentário com uma resposta, e o revisor passa a ver o status e a resposta
- Um comentário `erro` em aberto aparece contabilizado na tela do curso no admin
- Nenhuma gravação em tabela de conteúdo é possível a partir de qualquer rota do revisor
- A migration é compatível com MySQL 5.7

## Riscos

- **Escopo mal aplicado**: um erro na validação de contexto exporia cursos não atribuídos. Mitigação: espelhar o desenho já testado do `ProfessorAcademicScopeService`, e cobrir com teste unitário como em `tests/Unit/professor_academic_scope.php`
- **Perfil com permissão a mais**: conceder ao Revisor qualquer permissão terminada em `gerenciar` anula a premissa da feature. Mitigação: teste que afirma quais permissões o perfil tem, e que falha se aparecer uma nova
- **Gabarito à vista**: o revisor enxerga respostas corretas de todo o banco. É necessário para o trabalho dele e é um vazamento aceitável, porque o vínculo é nominal e auditado — mas é um poder que o perfil Aluno jamais pode receber por engano. Mitigação: o acesso ao banco depende de `area_curso.revisor.ver`, que nunca é concedida ao perfil Aluno
- **Fila abandonada**: comentários que ninguém trata transformam a feature em teatro. Mitigação: contador visível de comentários abertos na tela do curso no admin
- **Desvio de escopo para "sistema de revisão colaborativa"**: a constituição do projeto pede mudanças incrementais e veda overengineering. Mitigação: a lista de "fora de escopo" acima é parte do acordo, não sugestão

## Sucesso

A feature terá cumprido seu papel quando a pendência *"revisão por professor da área antes de divulgar"* puder ser fechada em um curso — com os apontamentos registrados, respondidos e rastreáveis dentro do portal, e não numa troca de e-mails.
