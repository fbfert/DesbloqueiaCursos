# Go-live 30 Min

Roteiro executivo para publicar o Polo Rainbow em producao em cerca de 30 minutos, com controle de risco, manutencao e rollback simples.

## Objetivo

Publicar o portal em Linux/cPanel com o menor risco operacional possivel, validando o essencial antes de sair da manutencao:

- autenticacao
- dashboard admin e professor
- checkout
- cupons
- comprovantes PIX
- certificados
- area do curso
- financeiro

## Pre-requisitos imediatos

Antes de iniciar:

- acesso ao cPanel
- acesso ao FTP
- acesso ao banco MySQL
- acesso ao `.env` de producao
- backup recente do banco
- backup recente dos arquivos
- versao de codigo pronta para publicar
- lista de migrations pendentes ja conferida
- janela de manutencao combinada

## Cronograma

### T-30 a T-20

1. Ative o modo manutencao.
2. Confirme a versao de codigo que sera publicada.
3. Confirme a versao do banco atual.
4. Confirme o backup do banco.
5. Confirme o backup dos arquivos.
6. Confirme o `.env` de producao.
7. Confirme se o storage privado fora da `public_html` existe e esta gravavel.
8. Confirme se as credenciais SMTP estao validas.

### T-20 a T-10

1. Verifique o ambiente PHP.
2. Verifique extensoes obrigatorias.
3. Verifique timezone.
4. Verifique `post_max_size`, `upload_max_filesize`, `max_execution_time` e `memory_limit`.
5. Publique o codigo no FTP.
6. Publique apenas os arquivos previstos para producao.
7. Nao sobrescreva storage, logs ou `.env`.
8. Prepare a aplicacao das migrations pendentes.

### T-10 a T-0

1. Aplique apenas as migrations pendentes.
2. Nao rode toda a cadeia como rotina se o banco ja existe.
3. Registre quais arquivos SQL foram aplicados.
4. Valide o resultado das migrations.
5. Valide o smoke test basico em rotas publicas e protegidas.
6. Valide o SMTP com um envio real.
7. Valide upload privado.
8. Valide checkout.

### T+0 a T+10

1. Valide login por CPF e e-mail.
2. Valide dashboard admin.
3. Valide dashboard professor.
4. Valide area do curso.
5. Valide certificado publico por codigo.
6. Valide comprovante PIX por pedido.
7. Valide permissao por perfil.
8. Se qualquer passo critico falhar, interrompa e prepare rollback.

### T+10 a T+30

1. Valide fluxo comercial completo.
2. Valide fluxo academico minimo.
3. Valide financeiro do professor.
4. Valide logs e auditoria.
5. Registre o snapshot final do go-live.
6. Remova a manutencao somente se os checkpoints criticos passarem.

## Ordem cronologica exata

1. Colocar manutencao no ar.
2. Fazer backup do banco.
3. Fazer backup dos arquivos.
4. Conferir PHP, extensoes e limites.
5. Conferir storage fora da area publica.
6. Conferir `.env` e SMTP.
7. Publicar codigo.
8. Aplicar migrations pendentes.
9. Validar rotas criticas.
10. Validar SMTP.
11. Validar upload privado.
12. Validar checkout.
13. Validar certificado.
14. Validar dashboard admin.
15. Validar dashboard professor.
16. Registrar snapshot final.
17. Sair da manutencao.

## Checkpoints obrigatorios

- [ ] manutencao ativada antes de mexer
- [ ] backup do banco confirmado
- [ ] backup dos arquivos confirmado
- [ ] PHP conferido
- [ ] storage conferido
- [ ] SMTP validado
- [ ] migrations pendentes aplicadas
- [ ] smoke test rodado
- [ ] checkout validado
- [ ] certificado validado
- [ ] dashboard admin validado
- [ ] dashboard professor validado
- [ ] snapshot final preenchido

## Critérios para seguir ou abortar

### Seguir

Continue apenas se:

- backup concluido
- publish concluido
- migrations aplicadas sem erro
- rotas criticas respondendo
- SMTP enviando
- upload privado funcionando
- dashboards abrindo

### Abortar

Pare e acione rollback se ocorrer:

- erro 500 em rota critica
- falha de migration
- falha de autenticacao ou permissao
- falha de upload privado
- falha de certificado
- falha de checkout
- falha de dashboard admin ou professor
- inexistencia de log em acao sensivel

## Modo manutencao

Ative o modo manutencao:

- antes do backup, se o ambiente estiver em janela de mudanca
- obrigatoriamente antes de aplicar migrations
- obrigatoriamente antes de restaurar banco
- obrigatoriamente antes de trocar codigo

Desative somente apos validacao dos checkpoints criticos.

## Momento de backup

O backup deve acontecer antes de qualquer alteracao em producao:

- backup do banco primeiro
- backup dos arquivos depois
- se possivel, confira se o backup abre e nao esta corrompido

## Momento de aplicar migrations

Aplicar migrations somente depois de:

- manutencao ativa
- backup confirmado
- codigo publicado
- lista de migrations pendentes revisada

Em banco ja existente, aplicar apenas o que estiver pendente.

## Momento de validar rotas criticas

Validar imediatamente apos publicar codigo e aplicar migrations:

- `/`
- `/cursos`
- `/login`
- `/cadastro`
- `/recuperar-senha`
- `/api/health`
- `/admin`
- `/admin/dashboard`
- `/professor`
- `/professor/dashboard`
- `/certificados/validar`

## Momento de validar SMTP

Validar SMTP apos publicar codigo e antes de sair da manutencao:

- cadastro
- recuperacao de senha
- pedido criado
- comprovante enviado
- pedido aprovado
- certificado disponivel

## Momento de validar upload privado

Validar uploads privados antes de sair da manutencao:

- escrita em `storage/private_uploads`
- escrita em `storage/private_uploads/certificados`
- upload real de comprovante
- leitura protegida de comprovante
- leitura protegida de certificado

## Momento de validar checkout

Validar checkout apos confirmar login, permissao e SMTP:

- compra propria
- compra para terceiros
- compra em lote
- cupom valido
- cupom 100 por cento
- comprovante PIX
- pedido aprovado
- pedido em pendencia e reenvio

## Momento de validar certificado

Validar certificado depois de checkout e area do curso:

- emissao manual
- abertura do PDF
- QR Code
- busca por codigo
- CPF parcial na validacao publica
- CPF integral no PDF

## Momento de validar dashboard admin/professor

Validar dashboards antes de sair da manutencao:

- admin ve os cards e filtros
- professor ve somente seus dados
- professor nao ve comprovantes PIX
- professor nao ve dados globais

## Momento de registrar snapshot final

No final do deploy, registre:

- commit publicado
- horario de inicio
- horario de fim
- migrations executadas
- responsavel
- resultado do smoke test
- resultado da homologacao rapida
- decisao final: manter ou rollback
