# Capa: Top 5 Cursos, Top 5 Avaliações e Depoimentos

## Alterações

- Reduzido o espaçamento entre os cards de destaque e o botão **Ver todos os cursos**.
- Inserido, após o botão, o bloco **Top 5 Cursos**.
- Inserido o bloco **Top 5 Avaliações** como módulo preparado para integração futura.
- Inserido o bloco **Depoimentos** com carrossel horizontal mobile-first.

## Top 5 Cursos

O ranking usa dados reais do banco. Entram apenas cursos ativos com venda confirmada por uma das condições abaixo:

- `pedidos.status IN ('aprovado', 'pago')`; ou
- `comprovantes_pix.status = 'aprovado'`.

A contagem usa `pedido_itens.quantidade`, considerando apenas itens ativos e não excluídos.

## Top 5 Avaliações

O bloco foi criado como módulo editável, mas ainda não exibe ranking real porque o projeto ainda não possui uma base pública consolidada de avaliações de curso. A tela mostra um aviso operacional até essa base existir.

## Depoimentos

O bloco principal usa o módulo `depoimentos_capa`.

Cada depoimento deve ser cadastrado como módulo com:

- `posicao`: `depoimentos_capa_item`
- `tipo`: `depoimento`
- `titulo`: nome do participante ou identificação pública
- `subtitulo`: curso ou informação complementar
- `conteudo`: texto do depoimento
- `imagem`: opcional, usando o upload de imagens dos módulos

A migration cria dois modelos inativos para orientar o cadastro sem publicar depoimentos fictícios.

## Migration

Execute após as migrations anteriores da capa e do topo:

```sql
sql/029_capa_top_cursos_avaliacoes_depoimentos.sql
```
