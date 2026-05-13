# Capa: módulos editáveis de frontend — 2026-05-12

## Objetivo

Transformar textos fixos da capa em módulos editáveis no backoffice, sem criar estrutura paralela à tabela `frontend_modulos`.

## Módulos criados

- `chamada_principal_capa`: chamada principal da capa. O campo `titulo` é exibido como H1 e o campo `conteudo` é exibido como texto de apoio.
- `catalogo_publico_capa`: card textual da capa.
- `detalhe_seguro_capa`: card textual da capa.
- `inscricao_inicial_capa`: card textual da capa.

## Alterações de tela

- Removido o texto fixo “Portal de cursos” da chamada principal.
- Removidos os botões “Explorar cursos” e “Como funciona” da chamada principal.
- A área “Destaques” agora funciona como um card externo que engloba os cards de cursos destacados e o botão “Ver todos os cursos”.

## Migration

Executar `sql/027_capa_modulos_frontend.sql` após as migrations anteriores.
