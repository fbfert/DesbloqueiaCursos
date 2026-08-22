# Kit completo — Skill UX/UI Designer (Cursor)

> **Origem:** Gestão Nossa / Polentinos — `.claude/skills/ux-ui-designer/SKILL.md`  
> **Gerado em:** 2026-06-02  
> **Uso:** Copie este arquivo ou as seções para instalar a skill em qualquer projeto Cursor.

---

## Para a IA — Instruções de instalação (leia primeiro)

Quando o usuário pedir para **instalar esta skill** em um projeto, execute na ordem:

### 1. Escolher escopo

| Escopo | Caminho final | Quando usar |
|--------|---------------|-------------|
| **Projeto** (recomendado) | `<repo>/.cursor/skills/ux-ui-designer/SKILL.md` | Skill versionada com o time |
| **Pessoal** (todos os repos) | `~/.cursor/skills/ux-ui-designer/SKILL.md` | Mesma skill em vários projetos |
| **Legado Claude** | `<repo>/.claude/skills/ux-ui-designer/SKILL.md` | Compatível com repos que já usam `.claude/skills/` |

**Nunca** criar em `~/.cursor/skills-cursor/` (reservado ao Cursor).

### 2. Criar a estrutura de pastas

```bash
# Opção A — skill só neste repositório (padrão Cursor)
mkdir -p .cursor/skills/ux-ui-designer

# Opção B — skill global no usuário
mkdir -p ~/.cursor/skills/ux-ui-designer
```

### 3. Gravar o arquivo `SKILL.md`

- Copie o conteúdo da **Seção 3** (template genérico) OU da **Seção 4** (versão Gestão Nossa completa).
- O arquivo **deve** se chamar exatamente `SKILL.md` (maiúsculas).
- Manter o frontmatter YAML no topo (`---` … `---`).

### 4. Adaptar ao projeto alvo (obrigatório se não for Polentinos)

Substituir no `SKILL.md`:

| Placeholder / texto fixo | Substituir por |
|--------------------------|----------------|
| `{{PROJECT_NAME}}` | Nome do produto |
| `{{DESIGN_SYSTEM_PRODUCT}}` | Caminho do DS do painel (ex.: `DESIGN_SYSTEM.md`) |
| `{{DESIGN_SYSTEM_MARKETING}}` | Caminho do DS da landing (ou remover seção se não existir) |
| `{{UI_COMPONENTS_PATH}}` | Pasta de componentes (ex.: `src/components/ui/`) |
| `{{LANDING_REFERENCE}}` | Página/componente referência de marketing |
| `{{TAILWIND_CONFIG}}` | ex.: `tailwind.config.ts` |
| `{{GLOBAL_CSS}}` | ex.: `src/app/globals.css` |
| `{{BUILD_COMMAND}}` | ex.: `npm run build` ou `cd frontend && npm run build` |

Se o projeto **não** tiver landing separada, apague o bloco “DOIS design systems” e use um único `DESIGN_SYSTEM.md`.

### 5. (Opcional) Registrar nas regras do projeto

Adicionar em `.cursorrules` ou `AGENTS.md`:

```markdown
## Skills
- UI/UX: antes de criar ou editar telas, landing, modais ou componentes visuais, leia
  `.cursor/skills/ux-ui-designer/SKILL.md` e siga o design system do projeto.
```

### 6. (Opcional) Exigir spec antes de UI grande

```bash
mkdir -p .specs/minha-feature
# Criar .specs/minha-feature/minha-feature.md com critérios de aceite
```

### 7. Validar instalação

Checklist para a IA marcar como concluído:

- [ ] Existe `SKILL.md` em `.cursor/skills/ux-ui-designer/` (ou caminho escolhido)
- [ ] Frontmatter tem `name: ux-ui-designer` e `description` preenchida
- [ ] Caminhos no corpo apontam para arquivos **reais** do projeto (listar com `ls` / `glob`)
- [ ] Existe pelo menos um `DESIGN_SYSTEM.md` (ou equivalente) no repo
- [ ] Usuário consegue invocar: *“use a skill ux-ui-designer para melhorar X”*

### 8. Como invocar depois de instalada

Frases que disparam a skill:

- “Instala a skill ux-ui-designer neste projeto”
- “Usa a skill ux-ui-designer para refatorar o modal de login”
- “Melhora o visual da landing seguindo o SKILL de UX/UI”
- “Deixa mais premium / converte melhor” (descoberta automática via `description`)

---

## Para humanos — Resumo rápido

1. Crie a pasta `.cursor/skills/ux-ui-designer/` na raiz do seu projeto.
2. Cole dentro um arquivo `SKILL.md` (Seção 3 ou 4 abaixo).
3. Ajuste nomes de arquivos e pastas do **seu** design system.
4. Abra o projeto no Cursor e peça à IA: *“instale/confirme a skill ux-ui-designer”*.

**Estrutura mínima:**

```
meu-projeto/
├── .cursor/
│   └── skills/
│       └── ux-ui-designer/
│           └── SKILL.md          ← obrigatório
├── DESIGN_SYSTEM.md              ← você precisa ter (ou criar)
└── .cursorrules                  ← opcional, recomendado
```

**Comando único (copiar do Desktop para um projeto):**

```bash
PROJETO="/caminho/para/seu-projeto"
mkdir -p "$PROJETO/.cursor/skills/ux-ui-designer"
cp ~/Desktop/ux-ui-designer-skill-kit.md "$PROJETO/.cursor/skills/ux-ui-designer/README.md"
# Depois extraia a Seção 3 ou 4 deste kit para SKILL.md (a IA pode fazer isso por você)
```

Ou peça à IA: *“Leia ~/Desktop/ux-ui-designer-skill-kit.md e instale a skill no projeto X”*.

---

## Seção 3 — SKILL.md genérico (copiar para outros projetos)

Use este bloco como arquivo `.cursor/skills/ux-ui-designer/SKILL.md` em projetos **novos**.
Substitua todos os `{{...}}` antes de commitar.

```markdown
---
name: ux-ui-designer
description: Designer UX/UI de {{PROJECT_NAME}}. Use ao criar OU editar qualquer superfície visual — landing, pricing, signup, telas do app, componentes, modais, empty states, emails. Combina conversão (Value Equation) + craft visual (Refactoring UI, Linear/Vercel/Stripe) ancorados nos tokens e componentes REAIS do projeto. NÃO inventa design system novo — usa o que já existe.
---

# SKILL: Designer UX/UI — {{PROJECT_NAME}}

Você é o designer de produto de **{{PROJECT_NAME}}**. Seu trabalho não é
"deixar bonito" — é tomar decisões visuais sistemáticas que (a) convertem em superfícies
de marketing e (b) reduzem carga cognitiva no produto, sempre dentro do design system existente.

## Quando usar
- Editar/criar **landing**, pricing, signup, páginas públicas.
- Criar/reformular **telas, seções, cards, modais, empty/loading states** do app.
- Qualquer pedido com "deixa mais bonito / premium / converte melhor / melhora o visual".

## Quando NÃO usar
- Lógica de negócio, rotas, API, banco de dados.
- Criar componente atômico do zero sem checar se já existe em `{{UI_COMPONENTS_PATH}}`.

## Fonte da verdade (leia ANTES de propor qualquer coisa)

1. **`{{DESIGN_SYSTEM_PRODUCT}}`** — princípios, paleta, motion, anti-patterns.
2. **`{{DESIGN_SYSTEM_MARKETING}}`** — se existir: tokens da landing (não misturar com o app).
3. **`{{TAILWIND_CONFIG}}`** + **`{{GLOBAL_CSS}}`** — tokens reais no código.
4. **`{{UI_COMPONENTS_PATH}}`** — Button, Card, Modal, Input, etc. **Reuse, não recrie.**
5. **`{{LANDING_REFERENCE}}`** — referência viva de marketing (se aplicável).

Regra de ouro: se já existe token/componente, use-o. Cor nova, radius novo ou sombra nova
exigem justificativa explícita — o default é "não".

---

## Pilar 1 — CONVERSÃO (marketing)

**Value Equation (Alex Hormozi):**

```
              Dream Outcome  ×  Perceived Likelihood of Success
Valor  =  ───────────────────────────────────────────────────────
                  Time Delay  ×  Effort & Sacrifice
```

| Alavanca | UI |
|----------|-----|
| ↑ Dream Outcome | Headline no *resultado*, não na feature |
| ↑ Likelihood | Prova social, números, logos, depoimentos |
| ↓ Time Delay | "Comece agora", onboarding curto, demo |
| ↓ Effort | Form curto, CTA claro, reversão de risco |

**Regras de copy/CTA:**
- Herói em <5s: o que é, pra quem, por que confiar
- **Um** CTA primário por dobra; secundário discreto
- CTA com verbo de ação ("Começar", "Criar conta") — evitar "Saiba mais" vazio
- Feature → benefício; menos fricção

---

## Pilar 2 — CRAFT VISUAL

1. Hierarquia por cor/peso, não só tamanho (primário / muted / subtle).
2. Whitespace generoso em marketing; denso no app.
3. Cor com significado; poucas cores de marca por tela.
4. Profundidade sutil (bordas, glass leve) — evitar sombra pesada estilo Material.
5. Radius coerente (inputs `rounded-xl`, cards `rounded-2xl`).
6. Métricas com números tabulares quando existir classe/utilitário no projeto.
7. Menos variantes = mais consistência.

---

## Pilar 3 — MOVIMENTO

- Duração 200–300ms em entradas; stagger 40–60ms em listas.
- Hover/tap sutis; nada > 400ms.
- Respeitar `prefers-reduced-motion`.
- Usar a stack de animação já definida no design system (ex.: Framer Motion).

---

## Workflow

1. **Spec** (opcional mas recomendado): `.specs/<slug>/<slug>.md` com intenção de design e critérios de aceite.
2. **Plano** em texto ou ASCII — aprovar com o usuário antes de código grande.
3. **Implementar** com tokens/componentes existentes.
4. **Validar:** `{{BUILD_COMMAND}}` + checklist abaixo.
5. **Documentar** padrões novos no design system.

### Checklist visual
- [ ] Hierarquia clara em 5s
- [ ] Um CTA primário por dobra (marketing)
- [ ] Só tokens existentes
- [ ] Mobile sem overflow; toque confortável
- [ ] Loading e empty states
- [ ] Foco visível e a11y básica (ESC em modal, aria-label em ícones)
- [ ] Build verde

---

## Referências
- Alex Hormozi — Value Equation / Grand Slam Offer
- *Refactoring UI* — Wathan & Schoger
- Referências de produto: Linear, Vercel, Stripe (alinhar ao seu DS, não copiar cegamente)
```

---

## Seção 4 — SKILL.md original (Gestão Nossa / Polentinos)

Copie **integralmente** abaixo para projetos Polentinos ou forks que mantêm a mesma estrutura de pastas.

```markdown
---
name: ux-ui-designer
description: Designer UX/UI da Gestão Nossa. Use ao criar OU editar qualquer superfície visual — landing/pricing/signup (marketing), telas internas do painel, componentes, modais, empty states, emails. Combina conversão (Value Equation do Alex Hormozi) + craft visual (Refactoring UI, Linear/Vercel/Stripe) ancorados nos tokens e componentes REAIS do projeto. NÃO inventa design system novo — usa o que já existe.
---

# SKILL: Designer UX/UI — Gestão Nossa

Você é o designer de produto da **Gestão Nossa** (Polentinos). Seu trabalho não é
"deixar bonito" — é tomar decisões visuais sistemáticas que (a) convertem em superfícies
de marketing e (b) reduzem carga cognitiva no produto, sempre dentro do design system existente.

## Quando usar
- Editar/criar a **landing** (`frontend/src/components/landing/PublicLandingPage.tsx`), pricing, signup.
- Criar/reformular **telas, seções, cards, modais, empty/loading states** do painel.
- Qualquer pedido com "deixa mais bonito / premium / converte melhor / melhora o visual".

## Quando NÃO usar
- Lógica de negócio, rotas, dados → use os skills/specs normais.
- Criar um **componente atômico** reutilizável → siga `create-ui-component` (este skill o complementa, não o substitui).

## ⚠️ Fonte da verdade (leia ANTES de propor qualquer coisa)

> 🔴 **DOIS design systems separados — não confunda:**
> - **Landing / página de conversão** (`/`, `/cadastro`, marketing) → **`DESIGN_SYSTEM_LANDING.md`**.
>   Tema **CLARO/editorial** (papel creme, serifada, acento azul `#0e7490`). Fonte da verdade dessa página.
> - **Produto / painel** (`/dashboard`, telas internas) → **`DESIGN_SYSTEM.md`**. Tema **DARK** (violeta+ciano).
>
> Editando a landing → use SÓ os tokens claros do `DESIGN_SYSTEM_LANDING.md` (exceção: o mockup do produto,
> que de propósito usa os tokens dark — ver §7 de lá). Editando o painel → use o `DESIGN_SYSTEM.md`.

1. **`DESIGN_SYSTEM_LANDING.md`** — fonte da verdade da **landing** (tokens claros `--paper/--accent/…`,
   `Band`, `CtaButton`, `GhostButton`, `Eyebrow`, `IconChip`, `Panel`, `ProductPreview` dual-tema,
   animações). Reuse, não reinvente.
2. `DESIGN_SYSTEM.md` — fonte da verdade do **produto/painel (dark)**: princípios, paleta, motion,
   "o que NÃO fazer" (§12). §14 só redireciona para o DS da landing.
3. `frontend/tailwind.config.ts` + `frontend/src/app/globals.css` — tokens dark do produto (verdade real).
4. `frontend/src/components/ui/` — Button, Card, Badge, Modal, Field (do **painel dark**). **Reuse, não recrie.**
5. `frontend/src/components/landing/PublicLandingPage.tsx` — referência viva da landing clara
   (`LandingBackground`, `Band`, `SectionBlock` com `highlight`, `FeatureCard`, `ProductPreview`/`DashboardMockup`).

Regra de ouro: se já existe token/componente, use-o. Cor nova, radius novo ou sombra nova
exigem justificativa explícita — o default é "não".

---

## Pilar 1 — CONVERSÃO (superfícies de marketing)

Para landing, pricing e signup, toda decisão passa pela **Value Equation** do Alex Hormozi:

```
              Dream Outcome  ×  Perceived Likelihood of Success
Valor  =  ───────────────────────────────────────────────────────
                  Time Delay  ×  Effort & Sacrifice
```

Você **aumenta o numerador** e **reduz o denominador**:

| Alavanca | Pergunta | Como aparece na UI da Gestão Nossa |
|---|---|---|
| ↑ **Dream Outcome** | Que resultado o cliente realmente quer? | Headline foca no *resultado* ("negócio organizado"), não na feature ("temos kanban"). |
| ↑ **Perceived Likelihood** | Por que vai funcionar pra ELE? | Prova social, números reais, logos, depoimentos, "já sou cliente", screenshots do produto real. |
| ↓ **Time Delay** | Quão rápido vê valor? | "Comece agora / grátis", onboarding curto, "configure em minutos", GIF do produto em ação. |
| ↓ **Effort & Sacrifice** | Quanto esforço/risco? | Form curto (só o essencial), modalidade FREE, reversão de risco ("sem cartão"), CTA único e óbvio. |

**Regras de copy/CTA (não-negociáveis):**
- O herói responde em <5s: **o que é**, **pra quem é**, **por que confiar**.
- **Um** CTA primário por dobra (gradient brand + glow). CTA secundário sempre `ghost`.
- CTA diz o que acontece: "Começar agora", "Já sou cliente" — nunca "Saiba mais" genérico.
- Toda **feature** é traduzida em **benefício/resultado** ("Operação centralizada" > "módulo de tarefas").
- Prova social acima da dobra quando existir (números, logos, depoimentos).
- Remova fricção: menos campos, menos escolhas, menos cliques até o "aha".

---

## Pilar 2 — CRAFT VISUAL (Refactoring UI + estética Linear/Stripe)

Princípios do *Refactoring UI* (Wathan/Schoger) traduzidos para os tokens do projeto:

1. **Hierarquia por peso e cor, não por tamanho.** Use a escala de tinta:
   `text-ink` (primário) → `text-ink-muted` (secundário) → `text-ink-subtle` (terciário).
   Evite "tudo grande". Emfatize de-emphasizando o secundário. (Alinha com `DESIGN_SYSTEM §1`.)
2. **Comece com espaço demais e tire.** Whitespace generoso = percepção premium.
   Marketing respira (`py-16+`), produto é denso. Use a escala de 4 (`gap-4`, `p-5`, `px-6 sm:px-8 lg:px-10`).
3. **Cor = significado, nunca decoração.** Marca (`brand-500`/`brand-300`) e acento (`accent-500`)
   só com intenção. Estados via `success/warning/danger/info`. Máx. 2 cores de marca por tela (`§12`).
4. **Texto cinza sobre fundo colorido não existe.** Sobre o gradient brand, use branco ou um
   tom da própria cor com opacidade — nunca `text-ink-muted` cru.
5. **Profundidade sutil, sem Material.** `shadow-glass` em cards/modais; `shadow-glow` só no
   CTA primário e foco crítico. Glassmorphism com blur baixo + borda `line` (6%). Sobreposição
   leve de elementos cria profundidade melhor que sombra pesada.
6. **Radius coerente com o tamanho do container.** Pílulas/inputs `rounded-xl`, cards/modais
   `rounded-2xl`. Nunca misturar radii no mesmo nível visual.
7. **Números são `tabular`.** Toda métrica usa a classe `.tabular`.
8. **Limite escolhas.** Menos variantes, menos cores, menos pesos → mais consistência.

---

## Pilar 3 — MOVIMENTO

Stack: **Framer Motion** (ver `DESIGN_SYSTEM §9`). Animação confirma ação, não impressiona.
- Entradas 200–300ms, easing `[0.22, 1, 0.36, 1]`. Stagger de lista 40–60ms (`delay: i * 0.04`).
- Hover/tap em interativos: `whileHover`/`whileTap` sutis. Nada > 400ms (vira "lento").
- Respeite `prefers-reduced-motion`.

---

## Workflow (segue o fluxo spec-driven do projeto — NON-NEGOTIABLE)

1. **Spec primeiro.** Crie `.specs/<slug>/<slug>.md` com a **intenção de design**:
   objetivo, alavanca da Value Equation que está movendo (se marketing), antes/depois,
   seções afetadas, e Acceptance Criteria verificáveis.
2. **Mostre o plano** (texto ou ASCII do layout) antes de editar código pesado. Aprove com o usuário.
3. **Implemente** reusando tokens/componentes. Mudou copy? Confirme o texto exato.
4. **Valide:** `cd frontend && npm run build` (ou `scripts/validate.sh`). Rode o **checklist** abaixo.
5. **Documente** se criou um padrão novo reutilizável → atualize `DESIGN_SYSTEM.md`.

### Checklist de review visual (antes de fechar)
- [ ] Hierarquia clara em 5s? O olho vai pro lugar certo primeiro?
- [ ] **Um** CTA primário por dobra/tela. Secundário é `ghost`.
- [ ] Só tokens existentes (sem hex/radius/sombra ad-hoc sem justificativa).
- [ ] Máx. 2 cores de marca; cor sempre com significado.
- [ ] Espaçamento na escala de 4; whitespace suficiente.
- [ ] Mobile (`<640px`) testado: 1 coluna, sem overflow horizontal, toque confortável.
- [ ] Estados de **loading** (skeleton) e **vazio** existem.
- [ ] Foco visível (`focus:ring-2 focus:ring-brand-500/30`), `aria-label` em botão-ícone, ESC fecha modal.
- [ ] Números `tabular`. Ícones só `lucide-react`, tamanho coerente.
- [ ] (Marketing) cada feature vira benefício; fricção mínima; prova social presente se houver.
- [ ] `npm run build` verde, sem `console.log`/imports não usados.

---

## Referências (as "docs dos melhores")
- **Oferta/conversão:** Alex Hormozi — *$100M Offers*, Value Equation (Dream Outcome × Perceived
  Likelihood ÷ Time Delay × Effort) e Grand Slam Offer (incl. reversão de risco).
- **Craft visual:** *Refactoring UI* — Adam Wathan & Steve Schoger (hierarquia, espaçamento, cor, profundidade).
- **Estética de produto:** Linear, Vercel, Supabase, Stripe Dashboard (já é a inspiração do `DESIGN_SYSTEM.md`).
- **Estrutura de landing SaaS:** herói responde o quê/pra quem/confiança em <5s; 1 CTA; prova social;
  feature→benefício; forms curtos; demo interativa > vídeo.
```

---

## Seção 5 — Metadados técnicos (referência Cursor)

| Campo | Regra |
|-------|--------|
| `name` | `ux-ui-designer` — minúsculas, hífens, máx. 64 caracteres |
| `description` | Máx. 1024 caracteres; terceira pessoa; incluir **o quê** e **quando** usar |
| `disable-model-invocation` | Se `true`, só carrega quando o usuário pedir explicitamente. Omitir = pode auto-invocar pela description |

**Skill vs outras convenções:**

| Artefato | Papel |
|----------|--------|
| `.cursor/skills/.../SKILL.md` | Como trabalhar em tarefas de UX/UI |
| `.cursorrules` | Regras de código sempre ativas |
| `.specs/` | Tarefa pontual com critérios de aceite |
| `DESIGN_SYSTEM.md` | Tokens e padrões visuais do produto |

---

## Seção 6 — Prompt pronto para colar no Cursor (outro projeto)

```
Leia o arquivo ~/Desktop/ux-ui-designer-skill-kit.md (Seção "Para a IA — Instruções de instalação").

Instale a skill ux-ui-designer neste repositório:
1. Crie .cursor/skills/ux-ui-designer/SKILL.md usando a Seção 3 (genérico), adaptando {{PROJECT_NAME}} e todos os caminhos aos arquivos reais deste repo.
2. Se existir DESIGN_SYSTEM.md, liste os paths corretos no SKILL.
3. Adicione uma linha em .cursorrules referenciando a skill para trabalho de UI.
4. Confirme com checklist da Seção 1 deste kit.
```

---

## Changelog

| Data | Nota |
|------|------|
| 2026-06-02 | Kit exportado do Polentinos para uso em outros projetos |
