# Spec: Integração da API do Claude no Portal de Cursos

## Objetivo

Adicionar uma camada reutilizável de integração com a API do Claude para consumo interno do portal, com suporte a requisições JSON, configuração por ambiente e validação por rota protegida.

## Contexto

O projeto já está estruturado em PHP MVC, com Services concentrando regras de negócio e rotas de API preparadas para evolução. A integração com Claude deve seguir esse padrão sem criar dependência desnecessária de framework ou SDK externo.

## Problema

Hoje não existe uma camada padronizada para conversar com a API do Claude. Isso dificulta reuso, testes e futura automação em áreas como atendimento, geração assistida de conteúdo e apoio operacional.

## Solução esperada

- Criar um Service dedicado para chamadas à API do Claude
- Suportar leitura de payload JSON nas rotas da aplicação
- Disponibilizar uma rota protegida de teste para validar a integração
- Centralizar configuração em `config/` e `.env`
- Registrar logs das chamadas e falhas de integração

## Escopo

- Cliente HTTP para a API do Claude
- Configurações por ambiente via `.env`
- Suporte a `application/json` na camada de request
- Endpoint interno de teste para validação
- Documentação mínima de uso

## Fora de escopo

- Interface pública de chat
- Persistência de histórico de conversas
- Streaming em tempo real
- Orquestração de agentes ou automações complexas

## Usuários afetados

- Administrador do sistema
- Desenvolvedor
- Futuras rotinas internas que consumam IA

## Critérios de aceite

- A aplicação consegue enviar um prompt para a API do Claude
- A resposta é processada e devolvida em JSON
- Requisições JSON passam a ser interpretadas corretamente
- Falhas de configuração ou comunicação são tratadas com mensagens claras
- As chamadas relevantes ficam registradas em log

## Riscos

- Exposição indevida da chave de API se a configuração não for mantida apenas no servidor
- Uso acidental da rota de teste por usuários sem permissão
- Respostas longas ou prompts grandes impactarem tempo de resposta e custo
