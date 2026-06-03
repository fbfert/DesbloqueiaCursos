# Catálogo público: CTA de acesso para alunos matriculados

Data: 02/06/2026

## O que foi ajustado

- Na página pública `/cursos`, o card de um curso agora troca o CTA quando o usuário já possui acesso válido ao curso.
- O botão passa a mostrar:
  - `Você já tem esse curso, acesse aqui!`
- O destino preferencial é a sala virtual limpa do aluno:
  - `/aluno/curso/{inscricao_id}/{curso_id}/{turma_id}`

## Regra aplicada

- Visitante deslogado continua vendo `Inscreva-se já`.
- Usuário logado sem matrícula válida continua vendo `Inscreva-se já`.
- Usuário logado com inscrição ativa/aprovada para o curso vê o botão de acesso ao curso.
- A verificação foi feita em lote, sem consulta individual por card.

## Base técnica

- O controller do catálogo monta um mapa de acessos por `curso_evento_id`.
- O mapa reutiliza as inscrições aprovadas do aluno.
- A view apenas troca o texto e a URL do botão quando o mapa contém acesso para o curso atual.

## Observações

- Não houve alteração na regra de cursos sem turma aberta.
- Não houve alteração em checkout, admin, professor ou área do aluno.
- A navegação para a sala virtual continua usando a rota limpa já implementada.
