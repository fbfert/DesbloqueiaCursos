# QA Checklist

Checklist funcional do portal Polo Rainbow para validação antes de produção.

## Ambiente

- [ ] `.env` configurado
- [ ] banco de dados aplicado
- [ ] storage com permissão de escrita
- [ ] acesso administrativo validado
- [ ] dados fictícios de homologação carregados

## Autenticação

- [ ] cadastro com nome, CPF, e-mail e consentimentos
- [ ] login por e-mail
- [ ] login por CPF
- [ ] bloqueio temporário após tentativas inválidas
- [ ] recuperação de senha por token
- [ ] redefinição de senha dentro da validade
- [ ] logout encerra sessão

## Catálogo

- [ ] lista pública de cursos e eventos
- [ ] detalhe do curso/turma
- [ ] visualização mobile-first
- [ ] acesso público sem sessão para navegação

## Checkout

- [ ] compra própria
- [ ] compra para terceiros
- [ ] compra em lote
- [ ] cupom aplicado com sucesso
- [ ] cupom rejeitado em curso em promoção
- [ ] pedido gerado com status correto
- [ ] comprovante PIX anexado pelo pagador
- [ ] um único comprovante atual por pedido
- [ ] reenvio de comprovante gera nova versão
- [ ] aprovação administrativa do pedido

## Minha Conta / Meus Cursos

- [ ] lista de inscrições aprovadas
- [ ] acesso restrito ao próprio usuário
- [ ] link para área do curso

## Área do Curso

- [ ] acesso somente com inscrição aprovada
- [ ] card de instruções visível
- [ ] módulos exibidos na ordem
- [ ] aulas exibidas na ordem
- [ ] materiais para download funcionam
- [ ] links externos funcionam
- [ ] progresso inicial registrado
- [ ] conclusão de aula registrada
- [ ] conclusão de módulo registrada

## Presença e Avaliação

- [ ] presença registrada
- [ ] percentual de presença calculado
- [ ] avaliação cadastrada
- [ ] resposta do aluno registrada
- [ ] nota final calculada
- [ ] aptidão para certificado calculada

## Certificados

- [ ] certificado emitido manualmente
- [ ] PDF gerado
- [ ] validação pública sem login
- [ ] código alfanumérico exibido
- [ ] QR Code presente no PDF
- [ ] CPF parcial na validação pública
- [ ] CPF completo no PDF
- [ ] reemissão validada
- [ ] revogação e cancelamento registrados

## Financeiro

- [ ] apuração mensal por competência
- [ ] rateio sobre receita líquida
- [ ] teto de 75% respeitado
- [ ] cupom reduz a base líquida
- [ ] PF gera espelho de RPA
- [ ] PJ exige nota fiscal
- [ ] professor vê apenas cursos/turmas atribuídos

## Segurança e Auditoria

- [ ] CSRF em formulários sensíveis
- [ ] ownership validado em pedido, inscrição e certificado
- [ ] professor sem acesso a comprovantes PIX
- [ ] logs de falha e tentativa indevida
- [ ] auditoria gravada em mudanças relevantes
- [ ] exclusões indo para lixeira com justificativa

## Evidências mínimas

- [ ] captura de tela do login
- [ ] captura de tela do checkout
- [ ] captura de tela da área do curso
- [ ] captura de tela do certificado
- [ ] captura de tela do financeiro
- [ ] export dos logs de homologação
