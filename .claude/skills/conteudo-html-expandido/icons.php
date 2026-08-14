<?php
// Biblioteca de icones SVG (stroke=currentColor, viewBox 24x24, minimalista,
// mesmo estilo do "feather icons"). Fonte de verdade unica - nao redesenhe
// icones por curso, reuse estes. Para adicionar um icone novo, siga o mesmo
// estilo (stroke-width consistente, sem preenchimento solido exceto pontos
// pequenos) e acrescente aqui, nunca inline num script avulso.

$ICONES = array(
    'target'    => '<circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="4.2"/><circle cx="12" cy="12" r="0.9" fill="currentColor" stroke="none"/>',
    'flag'      => '<path d="M5 3v18"/><path d="M5 4.2h12l-2.6 4L17 12.2H5"/>',
    'search'    => '<circle cx="10.3" cy="10.3" r="6.3"/><path d="M15.2 15.2 21 21"/>',
    'users'     => '<circle cx="8.7" cy="8.2" r="3.1"/><path d="M3.3 19.2c0-3.3 2.5-5.6 5.4-5.6s5.4 2.3 5.4 5.6"/><circle cx="17" cy="9" r="2.5"/><path d="M15.3 13.8c2.5 0.3 4 2.2 4 5.4"/>',
    'message'   => '<path d="M21 11.4a8.3 8.3 0 0 1-8.4 8.3h-.5L4 21l1.3-3.9A8.4 8.4 0 1 1 21 11.4Z"/>',
    'building'  => '<rect x="4" y="3" width="10" height="18" rx="1"/><path d="M14 8h6v13h-6"/><path d="M7.3 6.8h1.2M10.5 6.8h1.2M7.3 10.6h1.2M10.5 10.6h1.2M7.3 14.4h1.2M10.5 14.4h1.2"/>',
    'check'     => '<circle cx="12" cy="12" r="9"/><path d="M8 12.4l2.6 2.6L16.2 9.4"/>',
    'route'     => '<circle cx="6" cy="18" r="2.1"/><circle cx="18" cy="6" r="2.1"/><path d="M8 18h4.7a4 4 0 0 0 4-4V9"/>',
    'calendar'  => '<rect x="3.5" y="5" width="17" height="16" rx="2"/><path d="M3.5 10h17"/><path d="M8 3v4M16 3v4"/>',
    'alert'     => '<path d="M12 3.4 22 20.2H2Z"/><path d="M12 9.6v4.8"/><circle cx="12" cy="17" r="0.95" fill="currentColor" stroke="none"/>',
    'book'      => '<path d="M12 6.4c-2-1.3-4.5-2-7-2v13c2.5 0 5 .7 7 2 2-1.3 4.5-2 7-2v-13c-2.5 0-5 .7-7 2Z"/><path d="M12 6.4v13"/>',
    'file'      => '<path d="M6 3h8l4 4v14H6Z"/><path d="M14 3v4h4"/><path d="M9 12.3h6M9 15.7h6M9 8.9h2"/>',
    'edit'      => '<path d="M4 20.5h4L19.3 9.2a2.1 2.1 0 0 0-3-3L4.9 17.5Z"/><path d="M14.6 7.8 17 10.2"/>',
    'sparkles'  => '<path d="M12 3l1.4 4.6L18 9l-4.6 1.4L12 15l-1.4-4.6L6 9l4.6-1.4Z"/><path d="M19 15l.6 2 2 .6-2 .6-.6 2-.6-2-2-.6 2-.6Z"/>',
    'compass'   => '<circle cx="12" cy="12" r="9"/><path d="M15 9l-2 6-6 2 2-6Z"/>',
    'briefcase' => '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M3 12h18"/>',
    'mail'      => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 6.5l9 6 9-6"/>',
    'network'   => '<circle cx="6" cy="6" r="2.2"/><circle cx="18" cy="6" r="2.2"/><circle cx="12" cy="18" r="2.2"/><path d="M7.8 7.4 10.5 16.2M16.2 7.4 13.5 16.2M8.2 6h7.6"/>',
    'mic'       => '<rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0"/><path d="M12 18v3"/>',
    'star'      => '<path d="M12 3l2.6 5.6 6 .7-4.5 4.1 1.2 6-5.3-3-5.3 3 1.2-6L3.4 9.3l6-.7Z"/>',
    'trending'  => '<path d="M3 17l6-6 4 4 8-9"/><path d="M15 6h6v6"/>',
    'shield'    => '<path d="M12 3l7 3v6c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6Z"/>',
    'clipboard' => '<rect x="5" y="4" width="14" height="17" rx="2"/><rect x="8.5" y="2.5" width="7" height="3" rx="1"/><path d="M8.5 11h7M8.5 15h7"/>',
    'monitor'   => '<rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/>',
    'clock'     => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/>',
    'award'     => '<circle cx="12" cy="8" r="5"/><path d="M8.5 12.5 7 21l5-3 5 3-1.5-8.5"/>',
    'smile'     => '<circle cx="12" cy="12" r="9"/><path d="M8.5 10h.01M15.5 10h.01"/><path d="M8 14.5c1 1.3 2.4 2 4 2s3-.7 4-2"/>',
    'send'      => '<path d="M4 12 20 4l-6 16-3-7-7-3Z"/>',
);

function icone($nome)
{
    global $ICONES;
    $path = isset($ICONES[$nome]) ? $ICONES[$nome] : $ICONES['sparkles'];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">' . $path . '</svg>';
}
