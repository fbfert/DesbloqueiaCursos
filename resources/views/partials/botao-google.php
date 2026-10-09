<?php
/**
 * Botão "Entrar com Google" + aviso de consentimento (mudança login-google).
 *
 *   require BASE_PATH . '/resources/views/partials/botao-google.php';
 *   echo botao_google(array('origem' => 'v2', 'redirect' => $redirectSeguro,
 *                           'termos' => '/termos-de-uso', 'privacidade' => '/politica-de-privacidade'));
 *
 * Não renderiza nada enquanto o login com Google estiver desligado (sem
 * GOOGLE_CLIENT_ID/SECRET). Logo em SVG local: nenhum script do Google na página.
 * Estilo: .botao-google* em assets/caderno/caderno.css e v2/assets/css/v2-main.css.
 */

use App\Core\Helpers;
use App\Services\Google\GoogleConfig;

if (!function_exists('botao_google')) {
    function botao_google(array $opcoes = array())
    {
        if (!GoogleConfig::siteAtivo()) {
            return '';
        }

        $origem = isset($opcoes['origem']) && in_array($opcoes['origem'], array('v2', 'v2_aluno'), true) ? $opcoes['origem'] : 'v2';
        $query = array('origem' => $origem);
        $redirect = isset($opcoes['redirect']) ? \App\Support\SafeRedirect::v2Path($opcoes['redirect']) : null;
        if ($redirect !== null) {
            $query['redirect'] = $redirect;
        }
        $href = '/login/google?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        $termos = isset($opcoes['termos']) && $opcoes['termos'] !== '' ? (string) $opcoes['termos'] : '/termos-de-uso';
        $privacidade = isset($opcoes['privacidade']) && $opcoes['privacidade'] !== '' ? (string) $opcoes['privacidade'] : '/politica-de-privacidade';

        $logo = '<svg class="botao-google-logo" viewBox="0 0 48 48" width="20" height="20" aria-hidden="true" focusable="false">'
            . '<path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>'
            . '<path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>'
            . '<path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>'
            . '<path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>'
            . '</svg>';

        return '<div class="botao-google-bloco">'
            . '<p class="botao-google-sep"><span>ou</span></p>'
            . '<a class="botao-google" href="' . Helpers::e($href) . '" rel="nofollow">' . $logo . '<span>Entrar com Google</span></a>'
            . '<p class="botao-google-aviso">Ao continuar com o Google, você concorda com os '
            . '<a href="' . Helpers::e($termos) . '" target="_blank" rel="noopener">Termos de Uso</a> e a '
            . '<a href="' . Helpers::e($privacidade) . '" target="_blank" rel="noopener">Política de Privacidade</a>.</p>'
            . '</div>';
    }
}
