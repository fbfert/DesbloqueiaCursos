<?php
/**
 * Botão mostrar/ocultar senha das telas de autenticação do tema caderno.
 * Só aparece com JS ativo (html.js); sem script, o campo segue normal.
 */

if (!function_exists('caderno_ver_senha')) {
    function caderno_ver_senha(string $inputId): string
    {
        return '<button type="button" class="ver-senha" data-ver-senha="' . htmlspecialchars($inputId, ENT_QUOTES, 'UTF-8') . '"'
            . ' aria-controls="' . htmlspecialchars($inputId, ENT_QUOTES, 'UTF-8') . '" aria-pressed="false" aria-label="Mostrar senha">'
            . caderno_icone('olho', 'olho-aberto') . caderno_icone('olho-fechado', 'olho-fechado') . '</button>';
    }
}
