<?php use App\Core\Helpers; ?>
<?php
$checkoutPassosAtual = isset($checkoutPassosAtual) ? (int) $checkoutPassosAtual : 1;
if ($checkoutPassosAtual < 1) {
    $checkoutPassosAtual = 1;
}
if ($checkoutPassosAtual > 6) {
    $checkoutPassosAtual = 6;
}

$checkoutPassosTexto = array(
    1 => 'Escolha do curso: confira a formação e a turma antes de continuar.',
    2 => 'Para continuar, entre na sua conta ou crie um cadastro gratuito.',
    3 => 'Confira os dados do participante. Eles serão usados para liberar o acesso ao curso.',
    4 => 'Revise o curso, a turma, o participante e o valor antes de seguir.',
    5 => 'Após a confirmação do pagamento, o acesso será liberado conforme as regras da inscrição.',
    6 => 'Tudo certo. Agora você pode acessar a área do aluno para acompanhar seu curso.',
);

$checkoutPassos = array(
    1 => 'Escolha do curso',
    2 => 'Cadastro ou login',
    3 => 'Dados da inscrição',
    4 => 'Resumo do pedido',
    5 => 'Pagamento',
    6 => 'Acesso ao curso',
);
?>

<section class="checkout-steps front-section" aria-label="Etapas da compra">
    <div class="checkout-steps__header">
        <p class="checkout-steps__eyebrow">Seu caminho até o acesso</p>
        <h2>Etapas da compra</h2>
    </div>

    <ol class="checkout-steps__list">
        <?php foreach ($checkoutPassos as $indice => $titulo): ?>
            <?php
            $estado = 'checkout-steps__item--pending';
            if ($indice < $checkoutPassosAtual) {
                $estado = 'checkout-steps__item--complete';
            } elseif ($indice === $checkoutPassosAtual) {
                $estado = 'checkout-steps__item--current';
            }
            ?>
            <li class="checkout-steps__item <?php echo Helpers::e($estado); ?>">
                <span class="checkout-steps__number"><?php echo (int) $indice; ?></span>
                <span class="checkout-steps__label"><?php echo Helpers::e($titulo); ?></span>
            </li>
        <?php endforeach; ?>
    </ol>

    <p class="checkout-steps__support"><?php echo Helpers::e($checkoutPassosTexto[$checkoutPassosAtual]); ?></p>
</section>
