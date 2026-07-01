<?php use App\Core\Helpers; ?>
<?php
$orientacaoLoggedIn = !empty($loggedIn);
?>

<section class="user-orientation<?php echo $orientacaoLoggedIn ? ' user-orientation--logged' : ''; ?> front-section" id="orientacao-novo-usuario" aria-labelledby="orientacao-novo-usuario-title">
    <?php if ($orientacaoLoggedIn): ?>
        <header class="user-orientation__header">
            <p class="user-orientation__eyebrow">Bem-vindo de volta</p>
            <h2 id="orientacao-novo-usuario-title">O que você quer fazer agora?</h2>
            <p>Escolha o próximo passo mais rápido para continuar sua jornada no portal.</p>
        </header>

        <div class="user-orientation__grid user-orientation__grid--logged">
            <article class="user-orientation-card user-orientation-card--highlight">
                <strong>Área do aluno</strong>
                <p>Acesse seus cursos, acompanhe inscrições e continue seu progresso.</p>
                <a class="button-link" href="/minha-pagina">Ir para minha página</a>
            </article>

            <article class="user-orientation-card">
                <strong>Meus cursos</strong>
                <p>Veja seus cursos e pedidos em andamento com acesso rápido.</p>
                <a class="button-link button-link--ghost" href="/meus-cursos">Ver meus cursos</a>
            </article>

            <article class="user-orientation-card">
                <strong>Explorar catálogo</strong>
                <p>Continue conhecendo a oferta pública de cursos e turmas abertas.</p>
                <a class="button-link button-link--ghost" href="/cursos">Ver cursos</a>
            </article>
        </div>
    <?php else: ?>
        <header class="user-orientation__header">
            <h2 id="orientacao-novo-usuario-title">Como você quer começar?</h2>
            <p>Escolha a opção que melhor combina com o seu momento. Vamos orientar você no caminho mais simples.</p>
        </header>

        <div class="user-orientation__grid">
            <article class="user-orientation-card user-orientation-card--highlight">
                <strong>Já tenho cadastro</strong>
                <p>Entre na sua conta para acessar seus cursos, acompanhar sua inscrição ou continuar sua compra.</p>
                <a class="button-link" href="/login">Entrar na minha conta</a>
            </article>

            <article class="user-orientation-card">
                <strong>Quero me cadastrar</strong>
                <p>Crie sua conta gratuita para comprar cursos, acompanhar inscrições e acessar seus conteúdos.</p>
                <a class="button-link button-link--ghost" href="/cadastro">Criar cadastro</a>
            </article>

            <article class="user-orientation-card">
                <strong>Quero apenas conhecer</strong>
                <p>Explore os cursos, conheça a proposta da plataforma e decida com calma antes de se cadastrar.</p>
                <p class="user-orientation-card__note">Você pode conhecer os cursos sem cadastro. Quando decidir participar, nós vamos orientar você passo a passo.</p>
                <a class="button-link button-link--ghost" href="/cursos">Ver cursos</a>
            </article>
        </div>
    <?php endif; ?>
</section>
