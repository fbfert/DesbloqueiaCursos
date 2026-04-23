# Post Deploy Validation

Checklist curto para confirmar se a publicacao subiu limpa.

## Rotas criticas

- [ ] `/`
- [ ] `/cursos`
- [ ] `/login`
- [ ] `/cadastro`
- [ ] `/recuperar-senha`
- [ ] `/api/health`
- [ ] `/admin`
- [ ] `/admin/dashboard`
- [ ] `/professor`
- [ ] `/professor/dashboard`
- [ ] `/certificados/validar`

## Fluxos minimos

- [ ] login por CPF
- [ ] login por e-mail
- [ ] compra propria
- [ ] compra para terceiros
- [ ] compra em lote
- [ ] cupom valido
- [ ] comprovante PIX enviado
- [ ] pedido aprovado
- [ ] area do curso acessivel com inscricao aprovada
- [ ] progresso sendo exibido
- [ ] certificado validando por codigo
- [ ] financeiro do professor abrindo com escopo proprio

## Permissoes

- [ ] admin ve tudo
- [ ] professor ve apenas seus cursos e turmas
- [ ] professor nao ve comprovantes PIX
- [ ] aluno ve somente os proprios cursos
- [ ] usuario nao autenticado nao acessa area protegida

## Storage e arquivos privados

- [ ] `storage/logs` gravando
- [ ] `storage/tmp` gravando
- [ ] `storage/private_uploads` gravando
- [ ] `storage/private_uploads/certificados` gravando
- [ ] upload real de comprovante funcionando
- [ ] abertura protegida de comprovante funcionando
- [ ] abertura protegida de certificado funcionando

## SMTP

- [ ] cadastro enviando
- [ ] reset de senha enviando
- [ ] pedido criado enviando
- [ ] comprovante enviado enviando
- [ ] pedido aprovado enviando
- [ ] certificado disponivel enviando

## Critérios de sucesso

Considerar publicado com sucesso somente se:

- nenhuma rota critica retornar 500
- permissao estiver correta por perfil
- upload privado estiver funcional
- checkout estiver funcionando
- certificado estiver validando
- dashboards estiverem abrindo
- logs e auditoria estiverem registrando

## Sinais de rollback imediato

Acionar rollback se ocorrer qualquer um dos pontos abaixo:

- erro 500 em rota critica
- falha de login
- acesso indevido por troca de id
- professor vendo dado que nao deveria
- upload privado falhando
- checkout travando
- certificado nao validando
- SMTP falhando
- logs parados
- migration com erro ou schema desalinhado

