# Topo como módulo e imagens nos módulos de frontend

## Objetivo

Transformar o título público do topo do site, antes fixo como `Polo Rainbow`, em um módulo gerenciável pelo backoffice. O módulo pode exibir texto ou imagem. A infraestrutura de upload foi estendida para todos os módulos de frontend.

## Migration

Executar após as migrations anteriores:

```sql
sql/028_modulos_topo_imagens.sql
```

A migration:

- adiciona `imagem_caminho` em `frontend_modulos`, quando ainda não existir;
- adiciona `imagem_alt` em `frontend_modulos`, quando ainda não existir;
- cria o módulo `topo_site` com o título padrão `Polo Rainbow`;
- usa sintaxe compatível com MySQL 5.7, sem `ADD COLUMN IF NOT EXISTS`.

## Pasta pública de uploads

As imagens dos módulos são salvas em:

```text
public_html/assets/uploads/modulos
```

No servidor, essa pasta precisa permitir gravação pelo usuário do PHP/Apache. Sugestão operacional: `775` para a pasta, mantendo arquivos como `644` após upload.

## Regras de exibição do topo

O topo público busca o módulo:

```text
codigo: topo_site
posicao: topo_site
tipo: identidade_visual
```

Regras:

1. Se o módulo tiver `imagem_caminho`, o topo exibe a imagem.
2. Se não tiver imagem, o topo exibe texto.
3. O texto é escolhido nesta ordem: `titulo`, `subtitulo`, `conteudo`.
4. Se o módulo não existir ou ocorrer erro antes da migration, mantém o fallback institucional.

## Upload em módulos

A tela Admin > Frontend > Módulos agora aceita upload de imagem para qualquer módulo.

Validações aplicadas:

- extensões permitidas: JPG, JPEG, PNG, WEBP e GIF;
- MIME permitido: `image/jpeg`, `image/png`, `image/webp`, `image/gif`;
- tamanho máximo: 5 MB;
- nome físico seguro com prefixo `modulo-`.

## Arquivos alterados

- `app/Controllers/Admin/FrontendModuloController.php`
- `app/Controllers/HomeController.php`
- `app/Models/FrontendModulo.php`
- `app/Services/FrontendModuloService.php`
- `resources/views/admin/frontend/modulos/form.php`
- `resources/views/admin/frontend/modulos/index.php`
- `resources/views/layout.php`
- `resources/views/partials/public/header.php`
- `resources/views/partials/public/pre_footer.php`
- `resources/views/partials/public/footer.php`
- `resources/views/home.php`
- `public_html/assets/css/app.css`
- `public_html/assets/css/app-novo.css`
- `public_html/assets/css/frontend.css`
- `public_html/assets/css/admin.css`
- `sql/028_modulos_topo_imagens.sql`
- `public_html/assets/uploads/modulos/.gitkeep`

## Validação realizada

Foram validados com `php -l`:

- controllers alterados;
- service alterado;
- model alterado;
- views alteradas.
