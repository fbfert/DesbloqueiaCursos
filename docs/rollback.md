# Rollback

Plano operacional para reverter o portal com seguranca e sem improviso.

## 1. Quando usar

### Rollback A - somente codigo

Use quando o problema estiver em:

- view
- controller
- rota
- CSS
- bug de logica sem impacto de schema

Ação:

- reverter o codigo
- manter o banco
- manter as migrations ja aplicadas
- validar rotas criticas novamente

### Rollback B - codigo + banco

Use quando houver:

- migration problemática
- alteracao de dados incompatível
- incompatibilidade entre codigo e schema

Ação:

- ativar modo manutencao
- restaurar banco
- restaurar codigo
- restaurar `.env` se necessario
- validar rotas criticas novamente

## 2. Rollback A - somente codigo

### Passo a passo

1. Ative o modo manutencao.
2. Identifique o ultimo commit estavel.
3. Refaça o deploy desse commit no FTP.
4. Preserve o codigo problemático para analise.
5. Valide:
   - home
   - login
   - dashboard admin
   - dashboard professor
   - checkout
   - certificados

### Observacoes

- Nao toque no banco.
- Nao remova dados de producao.
- Nao aplique novas migrations.

## 3. Rollback B - codigo + banco

### Passo a passo

1. Ative o modo manutencao.
2. Bloqueie novos acessos sensiveis, se necessario.
3. Restaure o backup do banco anterior ao deploy.
4. Refaça o deploy do codigo estavel.
5. Restaure o `.env` anterior, se houve alteracao de configuracao.
6. Revalide:
   - autenticação
   - dashboard admin
   - dashboard professor
   - checkout
   - comprovante PIX
   - area do curso
   - certificado
   - financeiro

### Observacoes

- Use este cenário quando schema e codigo estao desalinhados.
- Se houve migration nova, reverta com o backup correto do banco.
- Se houver controle de versao do schema, registre exatamente qual versao foi restaurada.

## 4. O que nao fazer

- Nao apagar `storage/` sem backup.
- Nao remover logs antes de encerrar a analise.
- Nao misturar rollback de codigo com alteracao manual do banco sem documentar.
- Nao reabrir o portal antes da validacao minima das rotas criticas.

## 5. Ordem recomendada no rollback B

1. Modo manutencao.
2. Banco.
3. Codigo.
4. Configuracao.
5. Validacao.
6. Saida da manutencao.

## 6. Validacao apos rollback

- [ ] login responde
- [ ] dashboard admin responde
- [ ] dashboard professor responde
- [ ] checkout responde
- [ ] certificado responde
- [ ] uploads privados funcionam
- [ ] logs continuam gravando

## 7. Registro do evento

Ao finalizar o rollback, anote:

- commit restaurado
- horario da reversao
- versao do banco restaurada
- responsavel
- motivo
- resultado das validacoes

