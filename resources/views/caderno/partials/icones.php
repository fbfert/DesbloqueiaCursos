<?php
/**
 * Ícones do tema caderno: sprite SVG inline (traço de caneta, levemente
 * irregular) e o helper caderno_icone().
 *
 * O layout inclui este arquivo uma vez, logo após o <body>, o que imprime o
 * sprite e define a função. Views e partials só chamam caderno_icone('nome').
 * O tremor de mão está no próprio desenho dos paths, não em filtro SVG.
 */

if (!function_exists('caderno_icone')) {
    /**
     * @param string $nome   um dos símbolos do sprite (ex.: 'busca', 'seta-dir')
     * @param string $classe classes extras, separadas por espaço
     */
    function caderno_icone(string $nome, string $classe = ''): string
    {
        $nome = preg_replace('/[^a-z0-9-]/', '', strtolower($nome));
        $classes = trim('ico ' . $classe);

        return '<svg class="' . htmlspecialchars($classes, ENT_QUOTES, 'UTF-8') . '" aria-hidden="true" focusable="false"'
            . ' fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">'
            . '<use href="#i-' . $nome . '"/></svg>';
    }
}

if (!empty($cadernoIconesImpressos)) {
    return;
}
$cadernoIconesImpressos = true;
?>
<svg xmlns="http://www.w3.org/2000/svg" width="0" height="0" style="position:absolute;width:0;height:0;overflow:hidden" aria-hidden="true" focusable="false">
  <symbol id="i-menu" viewBox="0 0 24 24" stroke="currentColor"><path d="M4 7.5c5-.6 11-.4 16 0M4 12.2c6-.4 10-.2 16 .2M4 16.8c5 .3 11 .1 16-.3"/></symbol>
  <symbol id="i-fechar" viewBox="0 0 24 24" stroke="currentColor"><path d="M6.2 6c3.9 3.8 7.8 7.9 11.6 12.1M17.8 6.3c-4 3.7-7.9 7.6-11.7 11.5"/></symbol>
  <symbol id="i-busca" viewBox="0 0 24 24" stroke="currentColor"><path d="M10.6 4c3.6-.1 6.5 2.9 6.4 6.5s-3 6.5-6.6 6.4S3.9 14 4 10.4 7 4.1 10.6 4M15.5 15.6c1.7 1.6 3.3 3.2 4.8 4.9"/></symbol>
  <symbol id="i-filtro" viewBox="0 0 24 24" stroke="currentColor"><path d="M4 6.5c5-.3 11-.2 16 0M7 12c3.5-.2 7-.2 10 .1M10 17.5c1.5-.1 3-.1 4 0"/></symbol>
  <symbol id="i-seta-esq" viewBox="0 0 24 24" stroke="currentColor"><path d="M19 12.3c-4.5-.3-9.5-.2-14 .1M10 6.5c-2 2-3.6 3.7-5 5.8 1.5 2 3.1 3.6 5 5.4"/></symbol>
  <symbol id="i-seta-dir" viewBox="0 0 24 24" stroke="currentColor"><path d="M5 11.7c4.5.3 9.5.2 14-.1M14 6.3c2 2 3.6 3.7 5 5.6-1.5 2-3.1 3.8-5 5.6"/></symbol>
  <symbol id="i-inicio" viewBox="0 0 24 24" stroke="currentColor"><path d="M4 11.2L12 4.5l8 6.9M6 10v9.3c4 .3 8 .3 12 0V10"/></symbol>
  <symbol id="i-cursos" viewBox="0 0 24 24" stroke="currentColor"><path d="M5 4.5c4.5-.3 9.5-.2 14 0 .3 5 .3 10 0 15-4.5.3-9.5.2-14 0-.3-5-.3-10 0-15M9 4.6v14.9"/></symbol>
  <symbol id="i-certificado" viewBox="0 0 24 24" stroke="currentColor"><path d="M12 4.4c3.1 0 5.6 2.5 5.5 5.6s-2.6 5.5-5.6 5.4S6.5 12.8 6.5 9.8 9 4.3 12 4.4M8.5 14.5L7 21l5-2.5 5 2.5-1.5-6.5"/></symbol>
  <symbol id="i-usuario" viewBox="0 0 24 24" stroke="currentColor"><path d="M12 4.7c2.1 0 3.8 1.7 3.8 3.8s-1.7 3.9-3.8 3.8-3.8-1.7-3.8-3.8 1.7-3.8 3.8-3.8M5 20c.6-4 3.6-6 7-6s6.4 2 7 6"/></symbol>
  <symbol id="i-relogio" viewBox="0 0 24 24" stroke="currentColor"><path d="M12 3.4c4.8-.1 8.7 3.8 8.6 8.6s-3.9 8.6-8.7 8.5S3.4 16.6 3.5 11.9 7.3 3.5 12 3.4M12 7.2c-.1 1.8 0 3.5.1 5.1l3.4 2.1"/></symbol>
  <symbol id="i-ao-vivo" viewBox="0 0 24 24" stroke="currentColor"><path d="M12 11.1c.6 0 1 .4 1 1s-.5 1-1 1-1-.5-1-1 .4-1 1-1M8.6 8.3c-2.1 2.1-2.1 5.4-.1 7.5M15.4 8.4c2.1 2 2.1 5.4.1 7.4M5.8 5.6c-3.6 3.6-3.6 9.3-.1 12.9M18.2 5.6c3.6 3.6 3.6 9.3.1 12.8"/></symbol>
  <symbol id="i-sob-demanda" viewBox="0 0 24 24" stroke="currentColor"><path d="M3.6 5.4c5.6-.4 11.2-.3 16.8 0 .3 3.9.3 7.8 0 11.6-5.6.4-11.2.3-16.8 0-.3-3.9-.3-7.7 0-11.6M10.2 8.6l4.7 2.7-4.7 2.8zM8.4 20.4c2.4-.2 4.8-.2 7.2 0"/></symbol>
  <symbol id="i-presencial" viewBox="0 0 24 24" stroke="currentColor"><path d="M12 21c-3.6-4-6.1-7.3-6-10.6.1-3.4 2.7-6 6-6s6 2.7 6 6c0 3.3-2.4 6.6-6 10.6M12 8.3c1.2 0 2.1 1 2.1 2.1s-1 2.1-2.1 2.1-2.1-1-2.1-2.1 1-2.1 2.1-2.1"/></symbol>
  <symbol id="i-simulado" viewBox="0 0 24 24" stroke="currentColor"><path d="M6.4 4.6c3.7-.3 7.4-.3 11.1 0 .4 5 .3 10 0 15-3.7.3-7.4.3-11.1 0-.3-5-.3-10 0-15M9 9.5l1.6 1.6 3.1-3.3M9 15.1c2-.1 4.1-.1 6.2.1"/></symbol>
  <symbol id="i-calendario" viewBox="0 0 24 24" stroke="currentColor"><path d="M4.4 6.3c5.1-.4 10.1-.3 15.2 0 .3 4.5.3 9 0 13.4-5.1.4-10.1.3-15.2 0-.3-4.5-.3-8.9 0-13.4M4.5 10.3c5-.2 10-.2 15 .1M8.6 3.6v4.2M15.4 3.5v4.3"/></symbol>
  <symbol id="i-vagas" viewBox="0 0 24 24" stroke="currentColor"><path d="M9 5.2c1.8 0 3.2 1.4 3.2 3.2s-1.4 3.2-3.2 3.2-3.2-1.4-3.2-3.2S7.2 5.2 9 5.2M3.4 19.4c.5-3.4 2.9-5.3 5.6-5.3s5.1 1.9 5.6 5.3M15.6 5.6c1.6.2 2.7 1.5 2.7 3s-1.1 2.8-2.6 3M17.2 14.3c2 .5 3.2 2.3 3.5 5"/></symbol>
  <symbol id="i-cadeado" viewBox="0 0 24 24" stroke="currentColor"><path d="M5.4 10.6c4.4-.3 8.8-.3 13.2 0 .3 3.1.3 6.2 0 9.3-4.4.3-8.8.3-13.2 0-.3-3.1-.3-6.2 0-9.3M8.3 10.5V7.8c0-2.2 1.6-3.9 3.7-3.9s3.7 1.7 3.7 3.9v2.7M12 14.2v2.4"/></symbol>
  <symbol id="i-check" viewBox="0 0 24 24" stroke="currentColor"><path d="M4.8 12.6c1.7 1.6 3.3 3.3 4.8 5.1 3-4.4 6.2-8.4 9.7-11.9"/></symbol>
  <symbol id="i-grampo" viewBox="0 0 24 24" stroke="currentColor"><path d="M4.8 15.8V9.4c.1-1.6 1.2-2.7 2.8-2.7 2.9-.1 5.9-.1 8.8.1 1.6 0 2.7 1 2.8 2.6v6.3M4.6 15.9l.4.1M19.2 16.1l-.5-.1"/></symbol>
  <symbol id="i-copiar" viewBox="0 0 24 24" stroke="currentColor"><path d="M8.6 8.4c3.8-.2 7.6-.2 11.4 0 .2 3.8.2 7.6 0 11.4-3.8.2-7.6.2-11.4 0-.2-3.8-.2-7.6 0-11.4M15.4 4.4c-3.7-.2-7.3-.2-11 0-.2 3.7-.2 7.3 0 11"/></symbol>
  <symbol id="i-cartao" viewBox="0 0 24 24" stroke="currentColor"><path d="M3.5 6.2c5.7-.3 11.3-.3 17 0 .3 3.9.3 7.7 0 11.6-5.7.3-11.3.3-17 0-.3-3.9-.3-7.7 0-11.6M3.6 10.2c5.6-.2 11.2-.2 16.8.1M6.8 14.6c1.4-.1 2.8-.1 4.1 0"/></symbol>
  <symbol id="i-pix" viewBox="0 0 24 24" stroke="currentColor"><path d="M12 3.3c2.9 2.8 5.8 5.8 8.7 8.7-2.9 2.9-5.8 5.8-8.7 8.7-2.9-2.9-5.8-5.8-8.7-8.7 2.9-2.9 5.8-5.9 8.7-8.7M8.4 12c1.2-1.2 2.4-2.4 3.6-3.5 1.2 1.1 2.4 2.3 3.6 3.5-1.2 1.2-2.4 2.4-3.6 3.5-1.2-1.1-2.4-2.3-3.6-3.5"/></symbol>
  <symbol id="i-alerta" viewBox="0 0 24 24" stroke="currentColor"><path d="M12 3.8c3 5.1 5.9 10.2 8.8 15.5-5.9.4-11.7.4-17.6 0 2.9-5.3 5.8-10.4 8.8-15.5M12 9.4c-.1 1.7 0 3.3.1 4.9M12 17.1v.2"/></symbol>
  <symbol id="i-olho" viewBox="0 0 24 24" stroke="currentColor"><path d="M2.8 12.1c2.3-4 5.6-6.2 9.2-6.2s6.9 2.2 9.2 6.1c-2.3 3.9-5.6 6.1-9.2 6.1s-6.9-2.1-9.2-6M12 9.2c1.6 0 2.8 1.3 2.8 2.8s-1.3 2.8-2.9 2.8-2.7-1.3-2.7-2.9 1.2-2.7 2.8-2.7"/></symbol>
  <symbol id="i-olho-fechado" viewBox="0 0 24 24" stroke="currentColor"><path d="M2.9 10.4c2.4 3.3 5.5 5 9.1 5s6.8-1.7 9.1-5.1M5.6 13.6l-1.9 2.3M9.4 15.1l-.9 2.8M14.6 15.1l.9 2.8M18.4 13.5l1.9 2.4"/></symbol>
  <symbol id="i-whatsapp" viewBox="0 0 24 24" stroke="currentColor"><path d="M12 3.6c4.7 0 8.5 3.7 8.4 8.3s-3.8 8.3-8.5 8.3c-1.4 0-2.8-.3-4-.9l-4.3 1.1 1.2-4.1C4 15 3.6 13.6 3.6 12 3.6 7.4 7.3 3.6 12 3.6M9.2 8.6c-.5.5-.6 1.3-.2 2.2.9 1.8 2.3 3.2 4.1 4.1.9.4 1.7.3 2.2-.2l.4-.6-1.8-1.2-.9.7c-.9-.4-1.7-1.2-2.1-2.1l.7-.9-1.2-1.8z"/></symbol>
</svg>
