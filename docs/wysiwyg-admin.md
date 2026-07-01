# WYSIWYG no admin

Infraestrutura reutilizável para campos com HTML formatado no backoffice.

Base atual:

- `public_html/assets/vendor/quill/quill.min.js`
- `public_html/assets/vendor/quill/quill.snow.css`

## Como ativar

Marque o `<textarea>` explicitamente com uma classe ou atributo:

```php
<textarea
    name="conteudo_html"
    class="js-wysiwyg"
    data-wysiwyg="full"
    rows="12"
><?php echo Helpers::e($conteudoHtml ?? ''); ?></textarea>
```

Modos disponíveis:

- `minimal`
- `basic`
- `full`

## Regras de uso

- Não aplicar automaticamente em todos os textareas.
- Usar apenas nos campos que realmente precisam de HTML formatado.
- O editor é progressivo: se o JavaScript falhar, o textarea continua funcionando.

## Sanitização no backend

HTML vindo do editor deve ser sanitizado antes de salvar.

Exemplo:

```php
use App\Support\HtmlSanitizer;

$conteudoHtml = HtmlSanitizer::clean($request->input('conteudo_html', ''), 'full');
```

Perfis:

- `minimal`: texto pequeno formatado
- `basic`: texto formatado comum
- `full`: conteúdo completo com títulos, citações e alinhamento

## Renderização

Dentro do `<textarea>`, continue escapando com `Helpers::e()`.

Quando o conteúdo já tiver sido sanitizado no salvamento, ele pode ser renderizado como HTML controlado na página pública ou administrativa.

## Segurança

O sanitizador remove:

- `script`
- `iframe`
- `object`
- `embed`
- `form`
- `input`
- `button`
- atributos `on*`
- `javascript:` em links

Links com `target="_blank"` recebem `rel="noopener noreferrer"`.

## Campo piloto

O campo piloto inicial é `conteudo_html` em `admin/paginas/form.php`.

Esse campo foi escolhido porque já armazena HTML e permite validar:

- carregamento do editor;
- submit;
- persistência;
- sanitização;
- renderização pública.

## Observação sobre tabelas

O Quill padrão não traz suporte robusto a tabelas sem extensão adicional. Nesta base, o foco é:

- texto rico seguro;
- títulos;
- listas;
- links;
- citações;
- alinhamento.

Se no futuro for necessário suporte forte a tabelas, a base pode ser estendida com plugin específico.
