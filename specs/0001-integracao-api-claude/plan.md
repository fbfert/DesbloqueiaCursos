# Plano técnico: Integração da API do Claude

## Estratégia

Implementar um cliente HTTP simples em `app/Services/` usando `curl`, reaproveitando o padrão do projeto para integrações externas. A leitura de JSON deve entrar na camada de request para permitir consumo por API sem quebrar rotas existentes.

## Artefatos a criar ou ajustar

- `config/ai.php`
- `.env.example`
- `app/Core/Request.php`
- `app/Core/Router.php`
- `app/Services/ClaudeService.php`
- `app/Controllers/Api/ClaudeController.php`
- `routes/api.php`
- `README.md`

## Decisões de arquitetura

- A integração será por Service, não por SDK externo
- A autenticação da API ficará por chave em ambiente
- A rota de teste será protegida por autenticação e permissão
- O retorno da API será normalizado antes de chegar ao controller

## Validação

- Verificar sintaxe PHP dos arquivos alterados
- Confirmar que `application/json` é parseado pela request layer
- Validar a resposta da rota interna em cenário sem chave e com chave
- Conferir logs e mensagens de erro em português brasileiro
