# Minha Página e home logada — ajuste de layout desktop

Data: 2026-05-13

## Objetivo

Ajustar a experiência visual da área do aluno em telas desktop sem perder o comportamento mobile-first já existente.

## O que foi ajustado

- A página `Minha Página` passou a usar um layout em duas colunas mais equilibrado no desktop.
- O bloco de `Avisos` ficou na coluna lateral, com comportamento sticky em telas maiores.
- A área principal dos cursos passou a usar uma grade mais flexível para evitar cartões espremidos em telas largas.
- A home logada mantém o mesmo padrão visual da área do aluno, com destaque personalizado para o usuário autenticado.

## Arquivo principal

- `public_html/assets/css/frontend.css`

## Validação

- O ajuste foi aplicado sem alterar rotas, autenticação ou regras de negócio.
- O comportamento continua responsivo e progressivo: mobile primeiro, desktop com expansão de layout.
