# Checklist de go-live — V2 como versão oficial

Este checklist consolida o levantamento feito ao longo das fases 2.1–2.14
(ver `docs-v2-interno/00` a `22`) e o trabalho de preparação final. Ele cobre
especificamente **"tornar a V2 a experiência padrão do site"** — não é um
checklist de deploy genérico (isso está em `docs/deploy.md`).

Marque cada item conforme for concluído/confirmado. Itens com ☐ ainda
dependem de uma ação humana (credencial, decisão de negócio, teste manual em
navegador) que não pode ser feita apenas editando código.

## 1. Bloqueadores diretos

- [x] Bug do botão "Pagar com AbacatePay" não enviar o formulário
  (`data-native-submit` ausente) — corrigido.
- [x] Erros no pagamento V2 caindo na tela V1 (`pagarAbacatepay` não
  reconhecia a própria origem V2) — corrigido via campo oculto `origem_v2`.
- ☐ **Chave de API da AbacatePay válida** em produção — sem isso, nenhum
  pagamento online (V1 ou V2) funciona. Configurar em `/admin` →
  configurações de pagamento.
- ☐ **Homologação manual do checkout de ponta a ponta** (inscrição →
  participantes → resumo → pagamento → comprovante PIX), com a chave válida
  acima. As fases 2.12A/B/C terminaram sem confirmação final registrada.
- [x] Validação pública de certificado (`/v2/certificados/validar`) —
  testada de ponta a ponta com certificado real: válido, CPF divergente e
  código inexistente todos tratados corretamente.

## 2. Fluxos que hoje só existem no V1 (decisão + implementação)

Cada um precisa de uma decisão: constrói o equivalente V2, ou aceita que o
V1 continua no ar permanentemente só para esse fluxo (documentar a decisão
aqui quando tomada).

- [ ] Editar cadastro (`/minha-conta`) — V2 hoje só lê, link "editar" manda
      pro V1.
- [ ] Redefinir senha pelo link do e-mail (`/recuperar-senha/redefinir?token=`)
      — nunca teve tela V2.
- [ ] Cancelar pedido — sem rota V2.
- [ ] Atividade discursiva **com upload de arquivo** — V2 só suporta texto;
      upload cai no V1.
- [ ] Quiz V2 sem salvamento automático de rascunho (regressão funcional
      conhecida vs. V1 — se o aluno fechar o navegador no meio, perde a
      tentativa).

## 3. E-mails transacionais

- [ ] `EmailModeloService` (defaults de `login_url`/`meus_cursos_url`/etc.),
      `PedidoRecuperacaoService` (recuperação de carrinho abandonado) e
      `emails/presente_concedido.php` apontam pro V1 mesmo quando a compra
      foi feita pela V2. Atualizar os links padrão **e** os templates já
      salvos em `emails_modelos` (mudar só o PHP não retroalimenta o que já
      está no banco).

## 4. SEO / técnico

- [x] `robots.txt` criado (bloqueia áreas autenticadas/admin/checkout).
- [x] `sitemap.xml` dinâmico criado (`/sitemap.xml`, gerado a partir do
      catálogo/categorias reais).
- [x] Tag `<link rel="canonical">` adicionada ao layout V2 (auto-referencial:
      cada página aponta pra própria URL atual).
- [ ] Considerar `RewriteRule`/redirects 301 de URLs V1 antigas já indexadas
      pelo Google para as equivalentes V2, quando fizer sentido (evita perder
      posição de busca na virada).

## 5. Segurança / limpeza (fora do escopo V1↔V2, mas achado durante o preparo)

- [x] **Removidos 10 scripts de diagnóstico** (`qa_dump.php`, `remote_diag*.php`,
      `remote_set_test_password.php` etc.) que estavam na raiz pública com a
      senha do banco em texto puro e, num caso, um endpoint de reset de senha
      sem autenticação real. Ver `docs-v2-interno/` (fase de preparo do
      go-live) para o relato completo.
- ☐ **Trocar a senha do usuário do MySQL** (ficou exposta em texto puro por
      ~2 meses nesses scripts) e atualizar `.env`.
- ☐ **Trocar a senha da conta administrativa** afetada, por precaução (não
      há evidência de uso indevido nos logs disponíveis, mas o log de acesso
      atual não cobre todo o período em que os arquivos ficaram expostos).
- [ ] `app/`, `config/`, `resources/`, `routes/`, `sql/`, `storage/` ainda
      vivem dentro da raiz pública (`public_html/`) neste ambiente, ao invés
      de fora dela como o restante da documentação do projeto assume. Mover
      isso é uma mudança estrutural maior (requer ajustar o document root do
      servidor) — não feito neste checklist, mas é a causa raiz de por que
      scripts soltos com extensão não-`.php` (como os `.bak`/`.tmp` ainda
      presentes) continuam potencialmente baixáveis por URL direta.

## 6. Decisões cosméticas pendentes

- [ ] Rodapé V2: links "Termos de Uso / Política de Privacidade / Remova-me"
      aparecem duplicados (coluna Institucional + faixa legal). Decidir qual
      remover.

## 7. Cobertura de testes

- Não há suíte automatizada confiável hoje (`tests/Unit/quiz_system.php`
  falha localmente por ambiente, sem cobertura de checkout/pagamento). Toda
  validação até aqui foi manual ou via simulação direta de controller
  (ver `docs-v2-interno/22-...md` §11 para o padrão usado). Antes de
  confiar 100% na virada, um teste manual real em navegador — não só
  simulado — do fluxo de compra é fortemente recomendado.

## 8. A virada em si

`HOME_VERSION=v2` no `.env` de produção é a única mudança necessária para
trocar a Home. **Isso é uma ação com efeito imediato em todos os visitantes
do site — exige confirmação explícita antes de ser feita**, e só faz
sentido depois que os itens ☐ acima (principalmente §1 e §2) estiverem
resolvidos ou conscientemente aceitos como "ficam no V1 mesmo".

Ver `docs/rollback.md` para como reverter rapidamente se algo der errado
após a virada.
