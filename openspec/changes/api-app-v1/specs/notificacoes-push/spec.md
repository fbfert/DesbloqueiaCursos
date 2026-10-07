# Spec Delta

## ADDED Requirements

### Requirement: Registro de dispositivos
O app SHALL registrar o token FCM por `POST /dispositivos` (upsert por `device_id`) e removê-lo por
`POST /dispositivos/remover`. O logout MUST remover o registro de push do dispositivo atual.

#### Scenario: Troca de token FCM
- **WHEN** o app reenvia o mesmo `device_id` com outro `fcm_token`
- **THEN** o registro existente é atualizado, sem duplicar o dispositivo

### Requirement: Histórico de notificações
Toda notificação ao aluno SHALL ser gravada em `app_notificacoes` antes de qualquer tentativa de
envio, e o app MUST conseguir listá-la (`GET /notificacoes`, paginado) e marcá-la como lida, só as
do próprio usuário.

#### Scenario: Notificação de outro usuário
- **WHEN** o aluno tenta marcar como lida uma notificação de outra pessoa
- **THEN** a resposta é `404 nao_encontrado`

### Requirement: Envio por FCM sem quebrar a ação de origem
O envio SHALL usar FCM HTTP v1 com conta de serviço (JWT RS256 assinado com OpenSSL), ficar desligado
por padrão (`FCM_ENABLED=false`) e usar timeouts curtos (conexão 2 s, total 4 s). Falha de push MUST
NOT lançar exceção para quem chamou nem desfazer a ação de origem. Token inválido ou não registrado
(`UNREGISTERED`/`INVALID_ARGUMENT`) MUST desativar o dispositivo.

#### Scenario: FCM fora do ar
- **WHEN** o administrador aprova um pedido e o FCM não responde
- **THEN** o pedido fica aprovado, a notificação fica gravada com `envio_status = falhou` e o erro vai para o log

#### Scenario: Token morto
- **WHEN** o FCM responde `UNREGISTERED` para um dispositivo
- **THEN** o dispositivo é marcado inativo e não recebe novas tentativas

### Requirement: Eventos que notificam o aluno
O sistema SHALL notificar, com título e corpo em PT-BR, nos eventos: pedido aprovado ou pago,
comprovante PIX reprovado, avaliação textual corrigida, atividade corrigida ou devolvida, quiz com
discursivas corrigidas, certificado emitido e conteúdo novo publicado num curso em que o aluno tem
acesso. O payload FCM MUST ser uma mensagem `data` com `tipo`, `titulo`, `corpo`, `notificacao_id` e
apenas as chaves de contexto relevantes ao tipo.

#### Scenario: Conteúdo novo em curso grande
- **WHEN** um item é publicado num curso com muitos alunos
- **THEN** as notificações são apenas gravadas como pendentes na requisição do administrador, e o envio acontece pelo cron

### Requirement: Reenvio por cron
`scripts/push_reenviar_pendentes.php` SHALL enviar notificações pendentes e reenviar as que falharam,
com limite de tentativas, em lotes que caibam numa execução de cron.

#### Scenario: Retentativa
- **WHEN** o cron roda com uma notificação `falhou` abaixo do limite de tentativas
- **THEN** ela é reenviada e o status é atualizado
