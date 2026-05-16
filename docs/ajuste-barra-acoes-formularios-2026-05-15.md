# Ajuste da barra de ações dos formulários — 2026-05-15

## Objetivo

Padronizar os formulários administrativos com a barra de ações:

- Salvar
- Salvar e novo
- Salvar e sair
- Salvar como cópia
- Cancelar

## Escopo aplicado

Foram revisados e ajustados os CRUDs gerenciais em:

- permissões e perfis
- turmas
- professores fiscais

Também permanecem padronizados os módulos já tratados anteriormente:

- categorias
- cursos e eventos
- cupons
- páginas
- menus e módulos do frontend
- modelos de e-mail

## Regras implementadas

- `Salvar` permanece na tela de edição após gravar.
- `Salvar e novo` grava e volta para o cadastro novo.
- `Salvar e sair` grava e vai para a listagem do módulo.
- `Salvar como cópia` cria um novo registro e redireciona para a edição da cópia.
- `Cancelar` segue como link para a listagem segura do módulo.

## Observação técnica

O fluxo de cópia de `professores fiscais` foi mantido com restrição de unicidade de `usuario_id`.
Isso significa que a cópia só é criada se o professor selecionado for diferente do registro original e ainda não tiver perfil fiscal.

## Validação

- Validação de sintaxe com `php -l` nos arquivos alterados.
- Publicação por FTP concluída para os arquivos modificados.

