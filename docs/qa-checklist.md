# QA Checklist

Checklist funcional do portal Polo Rainbow para validacao antes de producao.

## 1. Ambiente

- [ ] `.env` configurado
- [ ] banco de dados aplicado
- [ ] storage com permissao de escrita
- [ ] SMTP configurado para homologacao
- [ ] dados ficticios de homologacao carregados
- [ ] usuario admin com permissao de dashboard, catalogo, pedidos, certificados e financeiro

## 2. Autenticacao

- [ ] cadastro com nome, CPF, e-mail e consentimentos
- [ ] login por e-mail
- [ ] login por CPF
- [ ] bloqueio temporario apos tentativas invalidas
- [ ] recuperacao de senha por token
- [ ] redefinicao de senha dentro da validade
- [ ] logout encerra sessao
- [ ] logs de falha e tentativa indevida registrados

## 3. Dashboards

- [ ] dashboard admin abre com cards e filtros
- [ ] dashboard professor abre apenas com escopo proprio
- [ ] professor nao ve totais globais do sistema
- [ ] cards de vendas, usuarios, pedidos, inscricoes e certificados carregam

## 4. Catalogo

- [ ] lista publica de cursos e eventos
- [ ] detalhe do curso/turma
- [ ] visualizacao mobile-first
- [ ] acesso publico sem sessao para navegacao
- [ ] professor ve apenas cursos/turmas atribuidos quando entra no painel dele

## 5. Checkout

- [ ] compra propria
- [ ] compra para terceiros
- [ ] compra em lote
- [ ] cupom aplicado com sucesso
- [ ] cupom rejeitado em curso em promocao
- [ ] pedido gerado com status correto
- [ ] comprovante PIX anexado pelo pagador
- [ ] um unico comprovante atual por pedido
- [ ] reenvio de comprovante gera nova versao
- [ ] aprovacao administrativa do pedido

## 6. Minha Conta / Meus Cursos

- [ ] lista de inscricoes aprovadas
- [ ] acesso restrito ao proprio usuario
- [ ] link para area do curso
- [ ] professor nao acessa dados de outro usuario

## 7. Area do Curso

- [ ] acesso somente com inscricao aprovada
- [ ] card de instrucoes visivel
- [ ] modulos exibidos na ordem
- [ ] aulas exibidas na ordem
- [ ] materiais para download funcionam
- [ ] links externos funcionam
- [ ] progresso inicial registrado
- [ ] conclusao de aula registrada
- [ ] conclusao de modulo registrada

## 8. Presenca e Avaliacao

- [ ] presenca registrada
- [ ] percentual de presenca calculado
- [ ] avaliacao cadastrada
- [ ] resposta do aluno registrada
- [ ] nota final calculada
- [ ] aptidao para certificado calculada

## 9. Certificados

- [ ] certificado emitido manualmente
- [ ] PDF gerado
- [ ] validacao publica sem login
- [ ] codigo alfanumerico exibido
- [ ] QR Code presente no PDF
- [ ] CPF parcial na validacao publica
- [ ] CPF completo no PDF
- [ ] reemissao validada
- [ ] revogacao e cancelamento registrados

## 10. Financeiro

- [ ] apuracao mensal por competencia
- [ ] rateio sobre receita liquida
- [ ] teto de 75% respeitado
- [ ] cupom reduz a base liquida
- [ ] PF gera espelho de RPA
- [ ] PJ exige nota fiscal
- [ ] professor ve apenas cursos/turmas atribuidos
- [ ] professor ve apenas seus proprios repasses e indicadores

## 11. Segurança e Auditoria

- [ ] CSRF em formularios sensiveis
- [ ] ownership validado em pedido, inscricao, certificado e area do curso
- [ ] professor sem acesso a comprovantes PIX
- [ ] acesso por troca de IDs bloqueado
- [ ] logs de falha e tentativa indevida
- [ ] auditoria gravada em mudancas relevantes
- [ ] exclusoes indo para lixeira com justificativa

## 12. Evidencias minimas

- [ ] captura de tela do login
- [ ] captura de tela do dashboard admin
- [ ] captura de tela do dashboard professor
- [ ] captura de tela do checkout
- [ ] captura de tela da area do curso
- [ ] captura de tela do certificado
- [ ] captura de tela do financeiro
- [ ] export dos logs de homologacao
