# Registro de rollback — área do aluno

Data: 2026-05-24

## Contexto
Durante ajustes na tela `/aluno/cursos`, a sequência de mudanças que incluiu o card de progresso colapsável e alterações no rodapé/menu inferior gerou regressões visuais no desktop.

## Ação executada
Os arquivos principais foram restaurados para o ponto estável anterior, correspondente ao commit `d880284`.

## Arquivos restaurados
- `resources/views/layout.php`
- `resources/views/area-curso/index.php`
- `resources/views/partials/public/pre_footer.php`
- `resources/views/partials/public/footer.php`
- `public_html/assets/css/frontend.css`
- `public_html/assets/css/app.css`

## Resultado
- A estrutura da sala virtual volta ao comportamento anterior ao problema.
- O rodapé e o menu antes do rodapé retornam ao fluxo normal do layout.
- O card de progresso geral não permanece colapsado nesta restauração.
