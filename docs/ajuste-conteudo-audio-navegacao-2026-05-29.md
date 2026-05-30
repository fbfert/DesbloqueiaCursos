# Ajuste do recurso de leitura em voz alta

Data: 2026-05-29

## Escopo
- Leitura em voz alta de conteúdos do tipo `texto` na área do aluno.
- Navegação por trechos com avanço e retorno.
- Ajuste visual para botões somente com ícones.

## Arquivos alterados
- `resources/views/layout.php`
- `resources/views/area-curso/conteudo_item.php`
- `resources/views/area-curso/index.php`
- `public_html/assets/js/conteudo-audio.js`
- `public_html/assets/css/conteudo-audio.css`

## O que foi implementado
- Divisão do texto em trechos navegáveis.
- Controles:
  - ouvir
  - pausar
  - continuar
  - parar
  - voltar trecho
  - avançar trecho
- Indicador discreto de trecho atual.
- Leitura em `pt-BR` quando disponível.
- Extração de texto visível com ignorância de controles de interface.

## Ajuste visual
- Os botões ficaram apenas com ícones.
- Cada ação recebeu cor própria.
- A barra ficou em uma única linha, com overflow horizontal se necessário.

## Validação
- `php -l` nos arquivos PHP alterados.
- `node --check` em `public_html/assets/js/conteudo-audio.js`.
- Publicação por FTP com conferência por hash remoto.
