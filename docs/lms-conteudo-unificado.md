# LMS — Conteúdo Unificado

## Objetivo
A aba `Conteúdo` centraliza o gerenciamento pedagógico do curso e substitui o fluxo visual antigo de `Módulos e aulas`, `Materiais` e `Atividades`.

## Hierarquia
Curso → Módulos → Itens de conteúdo.

## Tipos de item
- Etiqueta
- Texto
- Arquivo
- Link
- Vídeo
- Avaliação textual

## Como criar um módulo
1. Acesse `Admin > Área do curso > Conteúdo`.
2. No bloco **Novo módulo**, preencha título, descrição, status e ordem.
3. Salve e confirme o módulo no card/lista de módulos.

## Como criar um item
1. No módulo desejado, clique para adicionar conteúdo.
2. Escolha o tipo do item.
3. Preencha os campos do tipo escolhido (texto, arquivo, link, vídeo ou avaliação).
4. Defina status, obrigatoriedade e ordem.
5. Salve e valide a exibição no módulo.

## Como funciona item obrigatório
- Itens marcados como obrigatórios entram no cálculo de conclusão do conteúdo.
- Itens opcionais não bloqueiam elegibilidade/certificado.

## Como funciona progresso do aluno
- O progresso é registrado por item no `conteudo_progresso_aluno`.
- Itens concluíveis (texto/arquivo/link/vídeo/etiqueta) avançam progresso por marcação de conclusão.
- Avaliação textual depende de envio/correção para refletir conclusão conforme regra.

## Como funciona avaliação textual
- O aluno envia resposta no item de avaliação textual.
- Pode haver reenvio conforme configuração.
- Professor/Admin corrige, atribui status, nota e feedback.

## Como corrigir avaliação textual
1. Acesse a listagem de pendências de avaliações textuais.
2. Abra a entrega.
3. Defina status (`corrigida/aprovada/reprovada/devolvida`), nota e feedback.
4. Salve e confirme atualização de pendência/progresso.

## Como funciona aptidão para certificado
- Mantém critérios legados (progresso/presença/avaliações/inscrição etc.).
- Adiciona critério de conteúdo obrigatório do novo LMS.
- Bloqueia emissão se houver item obrigatório pendente ou avaliação textual obrigatória pendente/reprovada.

## Como funciona migração do legado
- Migração controlada por `conteudo_migracao_legado`.
- Script: `storage/scripts/migrar_conteudo_legado.php`.
- Modos:
  - `--diagnosticar`
  - `--dry-run`
  - `--curso=<id> --executar`
- Idempotente: reexecução não duplica itens já migrados.

## O que não deve ser apagado ainda
- Tabelas legadas (`modulos`, `aulas`, `materiais`, `links_externos`, `atividades`, etc.).
- Rotas, controllers e views legadas.
- Scripts/migrations de migração e rastreabilidade.

## Pendências conhecidas
- Bug legado em `/admin/academico` (fora do escopo atual).
- PDF público com retorno 403 em cenário pendente específico.
- Entregas antigas não migradas automaticamente (quando existirem em outros ambientes, tratar em etapa própria).

