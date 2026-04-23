# Go-live Checklist

Checklist pratico para liberar o portal em producao com seguranca.

## 1. Pre-liberacao

### Banco e codigo

- [ ] backup completo do banco realizado
- [ ] backup completo dos arquivos realizado
- [ ] ambiente de manutencao ativado antes de qualquer alteracao
- [ ] versao de codigo confirmada
- [ ] migrations pendentes identificadas
- [ ] migrations pendentes aplicadas
- [ ] dominio/`APP_URL` conferido
- [ ] `.env` de producao conferido

### PHP e ambiente

- [ ] versao do PHP compativel validada
- [ ] `pdo_mysql` ativo
- [ ] `openssl` ativo
- [ ] `mbstring` ativo
- [ ] `json` ativo
- [ ] `fileinfo` ativo
- [ ] timezone em `America/Sao_Paulo`
- [ ] `post_max_size` validado
- [ ] `upload_max_filesize` validado
- [ ] `max_execution_time` validado
- [ ] `memory_limit` validado

### Storage

- [ ] `storage/` fora de `public_html`
- [ ] `storage/logs` gravavel
- [ ] `storage/tmp` gravavel
- [ ] `storage/private_uploads` gravavel
- [ ] `storage/private_uploads/certificados` gravavel
- [ ] teste real de upload privado executado
- [ ] teste real de leitura por rota protegida executado

### SMTP e logs

- [ ] SMTP de producao testado
- [ ] e-mail de cadastro validado
- [ ] e-mail de recuperacao de senha validado
- [ ] e-mail de pedido criado validado
- [ ] e-mail de comprovante enviado validado
- [ ] e-mail de pedido aprovado validado
- [ ] e-mail de certificado disponivel validado
- [ ] logs gravando normalmente

## 2. Rotas criticas

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

## 3. Permissoes por perfil

- [ ] admin ve tudo
- [ ] professor ve apenas seus cursos/turmas
- [ ] professor nao ve comprovantes PIX
- [ ] aluno ve apenas os proprios cursos
- [ ] usuario nao autenticado nao acessa area protegida

## 4. Fluxo comercial

- [ ] cadastro concluindo com consentimentos
- [ ] login por CPF
- [ ] login por e-mail
- [ ] recuperacao de senha por token
- [ ] compra propria
- [ ] compra para terceiros
- [ ] compra em lote
- [ ] cupom aplicado com sucesso
- [ ] cupom 100% aceito quando permitido
- [ ] cupom valido em pedido sem promocao
- [ ] cupom expirado rejeitado
- [ ] cupom esgotado rejeitado
- [ ] cupom em curso em promocao rejeitado
- [ ] cupom em compra em lote aplicado ao pedido inteiro
- [ ] comprovante PIX enviado
- [ ] comprovante PIX analisado
- [ ] pedido aprovado
- [ ] comprovante atual por pedido mantido em apenas uma versao vigente

## 5. Fluxo academico

- [ ] acesso a area do curso com inscricao aprovada
- [ ] progresso visivel
- [ ] presenca calculada
- [ ] avaliacao visivel
- [ ] nota final calculada
- [ ] aptidao para certificado calculada
- [ ] certificado emitido manualmente
- [ ] PDF do certificado abre corretamente
- [ ] QR Code presente no PDF
- [ ] busca por codigo funciona
- [ ] validacao publica mostra CPF parcial
- [ ] documento emitido mostra CPF integral

## 6. Fluxo financeiro

- [ ] dashboard financeiro abre
- [ ] dashboard do professor abre
- [ ] professor ve apenas dados proprios
- [ ] professor ve apenas repasses proprios
- [ ] apuracao por competencia carrega
- [ ] rateio sobre receita liquida validado
- [ ] teto de 75% respeitado
- [ ] cupom reduz a base liquida
- [ ] PF gera espelho de RPA
- [ ] PJ exige nota fiscal

## 7. Dashboards

- [ ] cards principais carregam no dashboard admin
- [ ] filtros do dashboard admin funcionam
- [ ] cards principais carregam no dashboard professor
- [ ] dashboard professor nao expõe dados globais

## 8. Critérios de aceite

- [ ] sem erro 500 nas rotas criticas
- [ ] sem erro de permissao indevido
- [ ] sem acesso por troca de id na URL
- [ ] sem bypass de ownership em pedido, inscricao, certificado e area do curso
- [ ] CSRF validado nos formularios sensiveis
- [ ] auditoria gravando em mudancas relevantes
- [ ] logs de falha e tentativa indevida gerados
- [ ] exclusoes indo para lixeira com justificativa

## 9. Snapshot pos go-live

Registrar ao final:

- [ ] commit publicado
- [ ] horario do deploy
- [ ] migrations executadas
- [ ] responsavel
- [ ] resultado do smoke test
- [ ] resultado da homologacao rapida
- [ ] observacoes finais

