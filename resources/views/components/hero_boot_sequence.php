<?php
if (!empty($loggedIn)) {
    return;
}
?>

<section class="home-boot-section front-section" id="home-boot-sequence" aria-labelledby="home-boot-title" data-home-boot-root>
    <div class="home-boot-grid" aria-hidden="true"></div>

    <div class="home-boot-shell">
        <h1 class="sr-only" id="home-boot-title">Página inicial</h1>
        <div class="home-boot-terminal" data-home-boot-terminal aria-hidden="true">
            <div class="home-boot-terminal__frame"></div>
        </div>
    </div>
</section>
