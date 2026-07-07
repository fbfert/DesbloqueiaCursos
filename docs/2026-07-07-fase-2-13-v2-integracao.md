# Fase 2.13 - Integração V2

Data: 2026-07-07

## Resumo

Esta fase consolida o ambiente `/v2/` como experiência integrada ao backend em
pontos críticos da navegação e do checkout, mantendo compatibilidade com o
fluxo legado onde ainda for necessário.

## O que entrou nesta fase

- Home da raiz `/` selecionável por configuração via `HOME_VERSION` em
  `config/app.php` e `.env.example`.
- Middleware de autenticação próprio para rotas V2, com retorno seguro para
  `/v2/login?redirect=...`.
- Fluxo de login V2 com preservação do destino original, tela pós-login e
  logout dedicado para o namespace `/v2/`.
- Checkout V2 com etapas de inscrição, participantes, resumo, pagamento e
  comprovante, reutilizando as regras reais do backend.
- Páginas institucionais do V2 passando a consultar o conteúdo publicado no
  backend e a sanitizar HTML antes da renderização.
- Navegação V2 centralizada para evitar vazamento de links para o fluxo legado.
- Páginas de erro V2 para 403 e 404, mantendo a interface consistente quando a
  requisição já está no namespace `/v2/`.

## Segurança e compatibilidade

- Redirecionamentos de retorno validam apenas caminhos internos iniciados em
  `/v2/`.
- Rotas legadas continuam disponíveis para compatibilidade.
- O conteúdo editorial passa por sanitização antes de ser exibido.
- O envio de comprovante continua delegando ao fluxo real do backend; a V2 só
  altera a apresentação e o ponto de entrada.

## Ajustes de interface

- Cabeçalho, rodapé e navegação inferior foram ajustados para permanecer no
  ambiente V2.
- Cards de pedido passaram a priorizar o nome do curso, e não apenas o código.
- Cursos e páginas institucionais agora podem renderizar HTML rico sanitizado.
- Estilos novos foram adicionados para telas de pós-login, institucional e
  logout em desktop e mobile.

## Observação operacional

Para alternar a home principal sem alterar código, ajuste:

```dotenv
HOME_VERSION=v1
```

Use `v2` para servir a Home V2 na raiz `/` após homologação.
