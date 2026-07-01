<?php

use App\Core\Helpers;

// Hero da home do template V4 - Claude.
// Reaproveita as variáveis já passadas pelo HomeController:
// $chamadaPrincipalCapa (array), $credibilidade (array|null), $loggedIn (bool).
$chamadaPrincipalCapa = isset($chamadaPrincipalCapa) && is_array($chamadaPrincipalCapa) ? $chamadaPrincipalCapa : array();
$credibilidade = isset($credibilidade) && is_array($credibilidade) ? $credibilidade : array();
$estaLogado = !empty($loggedIn);

$slogan = 'Quando aprende de verdade, desbloqueia.';
$subtitulo = !empty($chamadaPrincipalCapa['conteudo'])
    ? (string) $chamadaPrincipalCapa['conteudo']
    : 'Cursos com turmas abertas, inscrição guiada e certificado ao concluir. Aprenda no seu ritmo, pelo celular.';

$formatarNumero = function ($valor) {
    return number_format((float) $valor, 0, ',', '.');
};

$stats = array();
if (isset($credibilidade['alunos']) && (int) $credibilidade['alunos'] > 0) {
    $stats[] = array('valor' => $formatarNumero($credibilidade['alunos']), 'rotulo' => 'Alunos');
}
if (isset($credibilidade['cursos']) && (int) $credibilidade['cursos'] > 0) {
    $stats[] = array('valor' => $formatarNumero($credibilidade['cursos']), 'rotulo' => 'Cursos');
}
if (isset($credibilidade['certificados']) && (int) $credibilidade['certificados'] > 0) {
    $stats[] = array('valor' => $formatarNumero($credibilidade['certificados']), 'rotulo' => 'Certificados');
}
$stats[] = array('valor' => '100%', 'rotulo' => 'Online');
?>
<section class="dc-hero">
    <div class="dc-hero__card">
        <span class="dc-hero__eyebrow">Desbloqueia Cursos</span>
        <h1 class="dc-hero__title"><?php echo Helpers::e($slogan); ?></h1>
        <p class="dc-hero__subtitle"><?php echo Helpers::e($subtitulo); ?></p>
        <div class="dc-hero__actions">
            <a class="dc-btn dc-btn--primary" href="/cursos">Explorar cursos</a>
            <a class="dc-btn dc-btn--ghost" href="<?php echo $estaLogado ? '/meus-cursos' : '/cadastro'; ?>"><?php echo $estaLogado ? 'Meus cursos' : 'Criar conta'; ?></a>
        </div>
    </div>

    <?php if (!empty($stats)): ?>
        <div class="dc-hero__stats" role="list">
            <?php foreach ($stats as $stat): ?>
                <div class="dc-stat" role="listitem">
                    <strong class="dc-stat__value"><?php echo Helpers::e($stat['valor']); ?></strong>
                    <span class="dc-stat__label"><?php echo Helpers::e($stat['rotulo']); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
