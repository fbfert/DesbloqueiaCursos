# Tarefas: Integração da API do Claude

## Fase 1: Base técnica

- [x] Criar a configuração de IA em `config/ai.php`
- [x] Adicionar variáveis do Claude em `.env.example`
- [x] Fazer `Request` entender JSON bruto do corpo
- [x] Garantir que o roteador preserve o corpo JSON ao refazer a request

## Fase 2: Cliente Claude

- [x] Criar o Service `ClaudeService`
- [x] Implementar chamada HTTP com tratamento de erros e logs
- [x] Normalizar o texto de resposta da API

## Fase 3: Exposição interna

- [x] Criar controller JSON para teste da integração
- [x] Adicionar rota protegida em `routes/api.php`
- [x] Documentar o uso básico no `README.md`

## Fase 4: Validação

- [x] Rodar `php -l` nos arquivos alterados
- [ ] Validar a resposta da rota com e sem configuração
- [x] Revisar mensagens e logs em português brasileiro
