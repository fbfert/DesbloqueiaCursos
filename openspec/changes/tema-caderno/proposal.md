# Proposal

## Why

O site público (V2) funciona, mas tem cara de template genérico — fundo branco, laranja, grade de cards — sem nada que diga "Desbloqueia". A home lista quase o catálogo inteiro e chega a travar o navegador. O público é majoritariamente classe C/D, no celular, muitas vezes Android de entrada com dados pré-pagos: o visual precisa encantar sem pesar. A marca já carrega uma metáfora forte e própria (desbloquear, próxima fase); esta mudança a assume com um sistema visual autêntico — a trilha de estudo desenhada à caneta num caderno — e animações de alto padrão, leves o bastante para o aparelho do aluno.

## What Changes

- Novo **tema público "caderno"**, selecionável por configuração (`TEMA_PUBLICO=caderno|v2`), com **prévia em produção só para administradores** (`?tema=caderno`, sessão de quem tem `conteudo.gerenciar`) e retorno imediato à V2 trocando a chave.
- Páginas redesenhadas no tema: **home, catálogo, curso, categorias, institucionais, validação de certificado, página de erro, login, cadastro, recuperação de senha e as etapas do checkout** (inscrição, participantes, resumo, pagamento, comprovante, comprovante enviado).
- Sistema visual próprio (papel pautado, caneta azul, marca-texto, post-it, fita crepe, carimbo, estante de cadernos, fotos coladas) com papéis fixos por elemento.
- Sistema de movimento nativo (Web Animations API, View Transitions, IntersectionObserver), sem biblioteca externa: um momento orquestrado por página, transição "a foto vira capa" entre catálogo e curso, modo leve automático e respeito a movimento reduzido.
- A home deixa de listar o catálogo completo: abertura com a trilha, estante de categorias, até 6 cursos em destaque, "o que você leva", ranking dos mais procurados e chamada final.
- Fontes e ícones hospedados no próprio servidor (sem Google Fonts e sem Tabler `@latest` por CDN nas páginas do tema).
- **Campo de cupom no resumo do pedido** (a V2 hoje só exibe cupom já aplicado): rota nova `POST /v2/checkout/cupom` que reaproveita a aplicação de cupom existente e mantém o aluno no tema; mensagens desse caminho passam a ter acentuação correta.

**Fora de escopo:**
- Área do aluno, aula, quiz, atividade e minha conta — continuam na V2 nesta fase (próxima fase do redesign).
- Admin/backoffice e área do professor/revisor.
- Troca das capas dos cursos (permanecem as atuais por decisão do produto).
- Regras de negócio, models, banco e gateway de pagamento. Em controllers, só a linha de seleção de view e a ação fina de cupom V2.
- Remoção das views V2 das páginas migradas — só depois que todo o site público estiver no tema.
- GSAP ou qualquer biblioteca de animação.

## Capabilities

### New Capabilities
- `tema-publico`: seleção e comportamento do tema visual do site público — chave de configuração, prévia administrativa, retorno à V2, cobertura de páginas, contrato de movimento (cenas, movimento reduzido, modo leve, conteúdo nunca escondido) e critérios de desempenho e acessibilidade.

### Modified Capabilities
(nenhuma — não há specs em `openspec/specs/` ainda)

## Impact

- **Código novo:** `app/Support/TemaPublico.php` (seletor), `resources/views/caderno/` (layout, partials, páginas), `assets/caderno/` (CSS, JS, fontes, sprite de ícones).
- **Código alterado:** uma linha de seleção de view nos controllers V2 do escopo (`Home`, `Catalogo`, `Categorias`, `Curso`, `Institucional`, `CertificadoValidacao`, `Login` — inclusive a escolha pós-login —, `Cadastro`, `RecuperarSenha`), em `App\Support\V2ErrorPage` e em `CheckoutController::renderCheckout()`; `CheckoutController::aplicarCupomV2()` (novo) e rota `POST /v2/checkout/cupom` em `routes/web.php`; acentuação das mensagens de aplicação de cupom em `PedidoService` e `CupomService`; `config/app.php` (chave); `.env.example`; `tests/Smoke/rotas.php`; testes unitários novos.
- **Banco:** nenhuma migration.
- **Produção:** deploy com `TEMA_PUBLICO=v2` (nada muda para o aluno); validação pela prévia de administrador; virada para `caderno` com aprovação do produto. Rollback: `TEMA_PUBLICO=v2`.
- **Dependências:** nenhuma nova. Fontes Instrument Serif, Geist e Kalam (licença SIL OFL) hospedadas localmente.
