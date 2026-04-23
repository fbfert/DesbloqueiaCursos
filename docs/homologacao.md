# Homologacao

Guia objetivo para validar o portal antes de subir para producao.

## 1. Pre-requisitos

- Aplicar todas as migrations em `sql/`
- Configurar `.env`
- Configurar SMTP de homologacao
- Garantir permissao de escrita em `storage/`
- Ter usuarios de admin, atendimento, financeiro e professor
- Carregar a massa ficticia de `tests/Fixtures/homologacao.md`

## 2. Massa minima

Use os dados ficticios em `tests/Fixtures/homologacao.md`.

### Registros minimos

- 1 admin
- 1 usuario de atendimento
- 1 usuario financeiro
- 1 professor PF
- 1 professor PJ
- 1 aluno comprador
- 1 empresa compradora
- 1 curso com promocao
- 1 curso sem promocao
- 2 turmas por curso
- 1 cupom publico
- 1 cupom privado
- 1 template de certificado
- 1 configuracao global institucional
- 1 configuracao global financeira

## 3. Sequencia de homologacao

### Autenticacao

1. Cadastrar usuario novo.
2. Entrar com CPF.
3. Entrar com e-mail.
4. Forcar erro de senha e validar bloqueio temporario.
5. Recuperar senha por token.

### Dashboards

1. Entrar no dashboard admin.
2. Validar cards, filtros e indicadores comerciais/academicos/financeiros.
3. Entrar no dashboard do professor.
4. Validar que ve apenas cursos, turmas, repasses e indicadores proprios.

### Compra

1. Abrir detalhe do curso.
2. Iniciar checkout para compra propria.
3. Repetir para terceiros.
4. Repetir em lote.
5. Aplicar cupom.
6. Enviar comprovante PIX.
7. Confirmar redirecionamento para `Meus Cursos`.

### Operacao interna

1. Aprovar pedido.
2. Aprovar comprovante.
3. Validar inscricoes.
4. Abrir area do curso.
5. Registrar progresso e presenca.
6. Emitir certificado manualmente.

### Financeiro

1. Fechar competencia.
2. Gerar apuracao.
3. Gerar repasse.
4. Validar acesso do professor apenas aos cursos vinculados.

## 4. Criticos de aceite

- Nenhum formulario sensivel pode aceitar POST sem CSRF.
- Professor nao pode ver comprovante PIX.
- Cupom nao pode entrar em curso com promocao.
- Pedido com comprovante reenviado deve manter historico.
- Certificado deve ter validacao publica.
- Somatorio de rateio nao pode passar de 75%.
- Professor nao pode acessar dados financeiros globais.
- Troca de IDs na URL nao pode expor pedido, inscricao, certificado ou area do curso de outro usuario.

## 5. Evidencias

- URL acessada
- usuario utilizado
- data e hora
- resultado observado
- captura de tela
- log relevante gerado
