# Contrato da API do App do Aluno — `/api/app/v1`

Fonte única de verdade entre o backend PHP (`backend-php/`, branch `feature/api-app-v1`)
e o app Android/KMP. Qualquer mudança aqui precisa ser refletida nos dois lados.

## Convenções

- Base: `https://desbloqueiacursos.com.br/api/app/v1`. JSON UTF-8, datas ISO-8601 com fuso
  (`2026-10-07T18:30:00-03:00`), dinheiro em **centavos** (inteiro), ids inteiros.
- Só **GET** e **POST** (o `Router` do site só conhece esses dois verbos).
- Autenticação: `Authorization: Bearer <access_token>`. Sem cookie, sem sessão persistida,
  sem CSRF (rotas registradas com `Router::postApp`, que não injeta `csrf`).
- Cabeçalhos enviados pelo app em toda requisição: `X-App-Version: 1.0.0 (1)`,
  `X-App-Platform: android`.
- Sucesso: `200`/`201` com `{"data": ..., "meta": {...opcional}}`.
- Erro: status HTTP adequado com
  `{"erro": {"codigo": "snake_case", "mensagem": "Texto PT-BR para exibir", "campos": {"campo": "msg"}}}`
  (`campos` só em `422`).
- Códigos de erro transversais: `401 nao_autenticado` (token ausente/inválido),
  `401 token_expirado` (app deve chamar `/auth/refresh` e repetir uma vez),
  `403 sem_acesso`, `404 nao_encontrado`, `422 validacao`, `429 muitas_tentativas`
  (com `Retry-After`), `426 atualizacao_obrigatoria`, `500 erro_interno`.
- Paginação (onde indicado): `?pagina=1&por_pagina=20` → `meta: {pagina, por_pagina, total}`.
- Uploads: `multipart/form-data`.

## Autenticação

### `POST /auth/login`
Corpo: `{"login": "email ou CPF", "senha": "...", "device_id": "uuid gerado no app", "device_name": "Samsung SM-A515F"}`
Mesma política do site (`politica_login`, bloqueio após N tentativas, `status='ativo'`).
`200`:
```json
{"data": {
  "access_token": "...", "access_expira_em": "...",
  "refresh_token": "...", "refresh_expira_em": "...",
  "usuario": {"id": 1, "nome": "...", "email": "...", "cpf": "123.***.***-00", "telefone": "...", "cidade": "...", "estado": "SP", "pendencias": []}
}}
```
`usuario.pendencias` (mudança `login-google`): lista de dados que a pessoa ainda precisa informar.
Hoje só `"cpf"` — conta criada pelo Google nasce sem CPF (`cpf: null`) e, enquanto ele faltar,
certificados pedidos pela secretaria ficam retidos. O app deve mostrar um aviso e levar a `POST /me/cpf`.
Erros: `401 credenciais_invalidas`, `423 conta_bloqueada` (mensagem com minutos), `403 conta_inativa`,
`429 muitas_tentativas`.

### `POST /auth/google` (mudança `login-google`)
Login com a conta Google. O app obtém o `id_token` pelo SDK nativo e o envia; nunca envia senha ou
segredo. Corpo: `{"id_token": "...", "device_id": "...", "device_name": "...", "nonce": "opcional"}`.

- **Android (Credential Manager / Sign in with Google):** use o **client ID web** do projeto como
  `serverClientId`; o `id_token` sai com `aud` = client ID web. Se o app gerar um `nonce` para a
  requisição do Google, envie o mesmo valor aqui — ele é conferido.
- **iOS (Google Sign-In):** o `aud` é o client ID iOS (configurado no backend em `GOOGLE_APP_CLIENT_IDS`).

Regras de conta iguais às do site: conta Google já vinculada entra; senão, conta existente com o
mesmo e-mail **verificado** é vinculada (a pessoa recebe e-mail de aviso); senão, cria conta de aluno
sem senha e sem CPF. `200`: mesmo formato do `/auth/login`, mais `"usuario_novo": true|false`.
Erros: `401 google_token_invalido` (assinatura, `aud`, `iss`, `exp` ou `nonce`), `409 email_nao_verificado`,
`403 conta_inativa`, `409 login_google_recusado` (conta já vinculada a outra conta Google ou e-mail
indisponível), `422 validacao`, `429 muitas_tentativas` (mesmo limite por IP do login),
`404 nao_encontrado` quando o login com Google não está configurado no servidor.

### `POST /auth/refresh`
Corpo: `{"refresh_token": "...", "device_id": "..."}` → mesmo formato do login (sem `usuario`).
Rotativo: o refresh usado é invalidado. Reuso de refresh já usado ⇒ revoga **todos** os tokens
daquele `device_id` e responde `401 sessao_revogada`.
Access token: 1 h. Refresh token: 60 dias.

### `POST /auth/logout` (autenticado)
Revoga os tokens do dispositivo atual e remove o registro de push dele. `200 {"data": {"ok": true}}`.

### `POST /auth/cadastro`
Corpo: `{"nome","email","cpf","telefone","senha","senha_confirmacao","aceite_termos":true,"aceite_privacidade":true}`
Reusa `AuthService::register`. `201 {"data": {"ok": true}}` (não faz login; o app chama `/auth/login` em seguida).
Erros: `422 validacao`.

### `POST /auth/recuperar-senha`
Corpo: `{"login": "email ou CPF"}` → sempre `200 {"data": {"ok": true}}` (não revela se existe).
O link do e-mail abre o site.

Limite de taxa em login/cadastro/recuperar: por IP e por login (`429`).

## Sistema

### `GET /config` (público)
```json
{"data": {"versao_minima_android": 1, "versao_atual_android": 1, "url_site": "https://desbloqueiacursos.com.br",
          "url_catalogo": "https://desbloqueiacursos.com.br/cursos", "url_suporte": "...", "url_termos": "...", "url_privacidade": "..."}}
```
Se `X-App-Version` (build) < mínima, toda rota autenticada responde `426 atualizacao_obrigatoria`.

## Perfil

- `GET /me` → `{"data": usuario}` (mesmo objeto do login).
- `POST /me` corpo `{"telefone","cidade","estado"}` → usuário atualizado.
- `POST /me/cpf` corpo `{"cpf": "000.000.000-00"}` (mudança `login-google`) → usuário atualizado, com
  `pendencias: []`. Só enquanto a conta não tem CPF; os certificados retidos aguardando CPF são emitidos.
  Erros: `422 validacao` (`campos.cpf`), `409 cpf_em_uso` (CPF de outra conta), `409 cpf_ja_informado`.
- `POST /me/senha` corpo `{"senha_atual","senha_nova","senha_nova_confirmacao"}` → `{"ok": true}`
  e revoga os tokens dos **outros** dispositivos.

## Meus cursos

### `GET /inscricoes`
Inscrições com acesso (mesma regra de `Inscricao::forUsuarioAprovadas`).
```json
{"data": [{
  "id": 10, "status": "ativa",
  "curso": {"id": 3, "titulo": "...", "slug": "...", "imagem_url": "https://...|null", "carga_horaria": 40},
  "turma": {"id": 7, "nome": "...", "inicio": "...|null", "fim": "...|null"},
  "progresso_percentual": 42, "acesso_expira_em": "...|null",
  "certificado": {"status": "nao_apto|apto|emitido", "codigo": "ABC123|null"},
  "atualizado_em": "..."
}]}
```

### `GET /inscricoes/{id}`
Inscrição + árvore de conteúdo publicado.
```json
{"data": {
  "inscricao": { ...mesmo objeto da lista... },
  "modulos": [{
    "id": 1, "titulo": "...", "ordem": 1, "concluido": false,
    "itens": [{
      "id": 100, "tipo": "texto|html|arquivo|link|video|video_incorporado|quiz|avaliacao_textual|etiqueta",
      "titulo": "...", "ordem": 1, "obrigatorio": true,
      "status": "nao_iniciado|em_andamento|concluido|enviado|corrigido|aprovado|reprovado",
      "concluido_em": "...|null"
    }]
  }]
}}
```
`403 sem_acesso` se a inscrição não é do usuário ou perdeu acesso.

### `GET /inscricoes/{id}/itens/{item}`
Abre o item, aplicando as mesmas regras do site (texto/html são concluídos ao abrir, etc.).
```json
{"data": {
  "item": { ...mesmo objeto da árvore..., "atualizado_em": "..." },
  "conteudo": {
    "html": "<p>HTML já sanitizado</p>|null",
    "video": {"provedor": "youtube|vimeo|outro", "video_id": "abc|null", "url": "https://..."} ,
    "link": {"url": "https://...", "abrir_externo": true},
    "arquivo": {"nome": "apostila.pdf", "mime": "application/pdf", "tamanho_bytes": 123456,
                "download_url": "/api/app/v1/inscricoes/10/itens/100/arquivo"}
  },
  "anterior": {"id": 99, "titulo": "..."} ,
  "proximo": {"id": 101, "titulo": "..."},
  "pode_concluir_manualmente": true
}}
```
Campos de `conteudo` ausentes/`null` quando não se aplicam ao tipo. `anterior`/`proximo` podem ser `null`.

- `POST /inscricoes/{id}/itens/{item}/concluir` → `{"data": {"item": {...}, "progresso_percentual": 45}}`.
  Idempotente. `422 nao_concluivel` para quiz/avaliação.
- `GET /inscricoes/{id}/itens/{item}/arquivo` → binário com `Content-Type`, `Content-Length`,
  `Content-Disposition`. Exige Bearer (o app baixa via WorkManager com o cabeçalho).

## Quiz (`item.tipo = quiz`)

- `GET /inscricoes/{id}/itens/{item}/quiz` →
  ```json
  {"data": {"regras": {"percentual_minimo": 70, "exige_aprovacao": true, "duracao_minutos": 30|null,
            "tentativas_maximas": 3|null, "tentativas_usadas": 1, "mostra_resultado": true, "mostra_gabarito": false},
            "tentativa_em_andamento": {"id": 5, "expira_em": "...|null"}|null,
            "tentativas": [{"id": 4, "status": "enviada|corrigida|aguardando_correcao", "percentual": 60, "aprovado": false, "enviada_em": "..."}],
            "pode_iniciar": true}}
  ```
- `POST .../quiz/iniciar` → tentativa com perguntas:
  ```json
  {"data": {"tentativa": {"id": 5, "expira_em": "...|null", "segundos_restantes": 1700|null},
            "perguntas": [{"id": 1, "tipo": "unica|multipla|discursiva", "enunciado_html": "...", "ordem": 1,
                           "alternativas": [{"id": 11, "texto_html": "..."}]}],
            "respostas": {"1": {"alternativas": [11], "texto": null}}}}
  ```
- `POST .../quiz/rascunho` corpo `{"tentativa_id": 5, "respostas": {"1": {"alternativas": [11]}, "2": {"texto": "..."}}}` → `{"ok": true, "segundos_restantes": ...}`
- `GET .../quiz/tempo?tentativa_id=5` → `{"segundos_restantes": 1600|null, "expirada": false}`
- `POST .../quiz/enviar` corpo igual ao rascunho → resultado (formato abaixo).
- `GET .../quiz/tentativas/{tentativa}` → `{"data": {"tentativa": {...}, "percentual": 80, "aprovado": true,
  "aguardando_correcao": false, "perguntas": [{... , "correta": true|null, "gabarito": [11]|null, "comentario_html": "...|null"}]}}`
  (gabarito/comentário só quando as regras do quiz permitem).

## Avaliação textual (`item.tipo = avaliacao_textual`)

- `GET /inscricoes/{id}/itens/{item}/avaliacao` →
  ```json
  {"data": {"enunciado_html": "...", "nota_minima": 7.0|null, "pode_enviar": true, "pode_reenviar": false,
            "limites": {"max_caracteres": 50000, "max_imagens": 5, "max_bytes_imagem": 1572864, "mimes": ["image/jpeg","image/png","image/webp"]},
            "entregas": [{"id": 1, "status": "enviada|reenviada|corrigida|devolvida|aprovada|reprovada",
                          "texto": "...", "imagens": [{"id": 1, "url": "/api/app/v1/avaliacoes/imagens/1"}],
                          "nota": 8.5|null, "feedback_html": "...|null", "enviada_em": "...", "corrigida_em": "...|null"}]}}
  ```
- `POST .../avaliacao` multipart: `texto`, `imagens[]` → `201` com a entrega criada.
- `GET /avaliacoes/imagens/{id}` → binário (só do próprio aluno).

## Pedidos

- `GET /pedidos` →
  ```json
  {"data": [{"id": 50, "numero": "PED-0050", "status": "rascunho|pendente|aguardando_pagamento|em_analise|aprovado|pago|cancelado|...",
             "status_rotulo": "Aguardando pagamento", "total_centavos": 19900, "criado_em": "...",
             "itens": [{"titulo": "...", "valor_centavos": 19900}],
             "pode_cancelar": true, "pode_enviar_comprovante": true, "pode_pagar_online": true,
             "comprovante": {"status": "em_analise|aprovado|reprovado", "motivo": "...|null"}|null,
             "pix": {"chave": "...", "copia_e_cola": "...|null"}|null}]}
  ```
- `POST /pedidos/{id}/cancelar` → pedido atualizado.
- `POST /pedidos/{id}/comprovante` multipart `arquivo` (pdf/jpg/png/webp, 10 MB) → pedido atualizado.
- `POST /pedidos/{id}/abacatepay` → `{"data": {"url": "https://..."}}` (o app abre no navegador/Custom Tab).

## Certificados

- `GET /certificados` → `{"data": [{"codigo": "ABC123", "curso_titulo": "...", "emitido_em": "...", "carga_horaria": 40,
  "pdf_url": "/api/app/v1/certificados/ABC123/pdf", "validacao_url": "https://.../certificados/validar?codigo=ABC123"}]}`
- `GET /certificados/{codigo}/pdf` → binário, só se o certificado é do usuário.

## Catálogo (público, só leitura)

- `GET /catalogo?pagina=&por_pagina=&categoria=&busca=` → `{"data": [{"id","titulo","slug","resumo","imagem_url",
  "preco_centavos","preco_promocional_centavos|null","carga_horaria","modalidade","categoria"}], "meta": {...}}`
- `GET /catalogo/{slug}` → curso com `descricao_html`, `turmas_abertas: [{id, nome, inicio, vagas_restantes|null}]`,
  `url_compra` (URL do site; o app abre no navegador).

## Push

- `POST /dispositivos` corpo `{"device_id","fcm_token","plataforma":"android","app_versao"}` → `{"ok": true}` (upsert).
- `POST /dispositivos/remover` corpo `{"device_id"}` → `{"ok": true}`.
- `GET /notificacoes?pagina=` → histórico `[{"id","tipo","titulo","corpo","dados":{...},"lida":false,"criada_em"}]`.
- `POST /notificacoes/{id}/lida` → `{"ok": true}`.

Payload FCM (`data` message, o app monta a notificação):
`{"tipo": "pedido_aprovado|comprovante_reprovado|avaliacao_corrigida|atividade_corrigida|quiz_corrigido|certificado_emitido|conteudo_novo",
  "titulo": "...", "corpo": "...", "notificacao_id": "123", "inscricao_id": "10", "item_id": "100", "pedido_id": "50", "certificado_codigo": "ABC123"}`
(apenas as chaves relevantes ao tipo). Deep link no app por `tipo`.
