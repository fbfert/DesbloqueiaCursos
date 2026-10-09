<?php
/**
 * Lembrete fixo de CPF pendente na área do aluno (login-google).
 *
 *   require_once BASE_PATH . '/resources/views/partials/aviso-cpf.php';
 *   echo aviso_cpf_pendente('caderno');   // ou 'v2'
 *
 * Aparece enquanto a conta logada não tem CPF (contas criadas pelo Google) e some
 * assim que o CPF é salvo. Uma consulta por página, só para quem está logado.
 */

use App\Core\Helpers;
use App\Core\Session;
use App\Models\Usuario;

if (!function_exists('aviso_cpf_pendente')) {
    function aviso_cpf_pendente($tema)
    {
        $usuarioId = (int) Session::get('usuario_id', 0);
        if ($usuarioId <= 0) {
            return '';
        }

        try {
            $usuario = (new Usuario())->findById($usuarioId);
        } catch (\Throwable $e) {
            return '';
        }
        if (!$usuario || !Usuario::semCpf($usuario)) {
            return '';
        }

        $texto = 'Informe seu CPF para podermos emitir seus certificados.';
        $href = '/v2/minha-conta#cpf';

        if ($tema === 'caderno') {
            return '<div class="aviso-cpf"><div class="postit largo" role="status">'
                . '<b>Falta o seu CPF.</b> ' . Helpers::e($texto)
                . ' <a class="link" href="' . Helpers::e($href) . '">Informar CPF</a>'
                . '</div></div>';
        }

        return '<div class="v2-container aviso-cpf"><div class="v2-callout v2-callout-info" role="status">'
            . '<i class="ti ti-id"></i><span><strong>Falta o seu CPF.</strong> ' . Helpers::e($texto)
            . ' <a href="' . Helpers::e($href) . '">Informar CPF</a></span>'
            . '</div></div>';
    }
}
