# Admin — listagens de pedidos e inscrições — 2026-05-23

## Objetivo

Padronizar as telas administrativas de pedidos e inscrições com busca, filtros, ordenação e paginação.

## O que foi alterado

### Pedidos

- `app/Controllers/Admin/PedidosController.php`
  - leitura dos parâmetros de filtro e paginação via `GET`
  - encaminhamento dos filtros para o serviço
- `app/Services/PedidoService.php`
  - listagem administrativa paginada
  - retorno com metadados de paginação
- `app/Models/Pedido.php`
  - consulta filtrada com busca, ordenação e contagem total
- `resources/views/admin/pedidos/index.php`
  - card de filtros com campo de busca
  - cabeçalhos ordenáveis
  - paginação no rodapé da listagem

### Inscrições

- `app/Controllers/Admin/InscricoesController.php`
  - leitura dos parâmetros de filtro e paginação via `GET`
- `app/Services/InscricaoService.php`
  - listagem administrativa paginada
  - retorno com metadados de paginação e opções de cursos/turmas
- `app/Models/Inscricao.php`
  - consulta filtrada com busca, filtros e ordenação
- `resources/views/admin/inscricoes/index.php`
  - card de filtros com busca
  - cabeçalhos ordenáveis
  - paginação no rodapé da listagem

## Validação

- `php -l` executado nos arquivos alterados.
- Publicação concluída no FTP do ambiente.
