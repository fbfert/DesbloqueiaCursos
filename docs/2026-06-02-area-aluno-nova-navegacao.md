# Área do aluno: navegação em três níveis

Data: 02/06/2026

## O que foi alterado

- A área do aluno passou a trabalhar em três níveis dentro do curso:
  - curso: lista de módulos;
  - módulo: lista de conteúdos do módulo;
  - conteúdo: modo de estudo focado, com navegação anterior/próximo.
- Foram criadas rotas limpas para a área do aluno:
  - `/aluno/curso/{inscricao_id}/{curso_id}/{turma_id}`
  - `/aluno/curso/{inscricao_id}/{curso_id}/{turma_id}/modulo/{modulo_id}`
  - `/aluno/curso/{inscricao_id}/{curso_id}/{turma_id}/modulo/{modulo_id}/conteudo/{conteudo_id}`
- A URL antiga `/aluno/cursos?inscricao_id=...&curso_id=...&turma_id=...` continua funcionando por compatibilidade.

## Base técnica

- O roteador recebeu suporte a rotas com parâmetros nomeados.
- A implementação preserva a prioridade das rotas literais sobre as dinâmicas.
- Os parâmetros de rota ficaram separados de `query()` e `input()` no objeto `Request`.

## Regras preservadas

- Apenas módulos e conteúdos com status `publicado` aparecem para o aluno.
- O admin e o professor não foram afetados por esta navegação.
- A área pública e o checkout continuam com suas rotas e regras existentes.
- As ações destrutivas continuam protegidas por `POST` e CSRF.

## Observações de validação

- `php -l` foi executado nos arquivos PHP alterados.
- `git diff --check` não apontou erros de conteúdo; restaram apenas avisos de LF/CRLF no worktree.

