<?php

namespace App\Core;

class Helpers
{
    public static function e($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    public static function normalizarTextoLms($value, $preservarQuebras = false)
    {
        $texto = (string) $value;

        for ($i = 0; $i < 3; $i++) {
            $decodificado = html_entity_decode($texto, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($decodificado === $texto) {
                break;
            }
            $texto = $decodificado;
        }

        $texto = str_replace(array("\xc2\xa0", '&#160;'), ' ', $texto);
        $texto = str_replace('Móduldo', 'Módulo', $texto);
        $texto = str_replace('Móduldoo', 'Módulo', $texto);
        $texto = str_replace('Moduldo', 'Módulo', $texto);

        if ($preservarQuebras) {
            $texto = preg_replace("/\r\n?/", "\n", $texto);
            $linhas = explode("\n", $texto);
            foreach ($linhas as &$linha) {
                $linha = preg_replace('/[ \t]+/u', ' ', trim($linha));
            }
            unset($linha);
            $texto = implode("\n", $linhas);
            $texto = preg_replace("/\n{3,}/", "\n\n", $texto);
            return trim($texto);
        }

        $texto = preg_replace('/\s+/u', ' ', $texto);
        return trim($texto);
    }

    public static function textoLms($value)
    {
        return htmlspecialchars(self::normalizarTextoLms($value, false), ENT_QUOTES, 'UTF-8');
    }

    public static function textoLmsMultilinha($value)
    {
        return htmlspecialchars(self::normalizarTextoLms($value, true), ENT_QUOTES, 'UTF-8');
    }

    public static function statusLms($status)
    {
        $status = (string) $status;
        $mapa = array(
            'nao_iniciado' => 'Não iniciado',
            'acessado' => 'Acessado',
            'em_andamento' => 'Em andamento',
            'concluido' => 'Concluído',
            'pendente' => 'Pendente',
            'aguardando_envio' => 'Aguardando envio',
            'reenviada' => 'Aguardando correção',
            'enviada' => 'Aguardando correção',
            'corrigido' => 'Corrigido',
            'corrigida' => 'Corrigida',
            'aguardando_correcao' => 'Aguardando correção',
            'pendente_correcao' => 'Aguardando correção',
            'reprovado' => 'Reprovado',
            'devolvida' => 'Devolvida',
            'aprovada' => 'Aprovada',
            'cancelada' => 'Cancelada',
            'publicado' => 'Publicado',
            'rascunho' => 'Rascunho',
            'oculto' => 'Oculto',
            'arquivado' => 'Arquivado',
            'ativo' => 'Ativo',
            'inativo' => 'Inativo',
            'certificado_emitido' => 'Certificado emitido',
        );

        if (isset($mapa[$status])) {
            return $mapa[$status];
        }

        $status = str_replace('_', ' ', $status);
        return $status !== '' ? ucwords($status) : '-';
    }

    public static function tipoConteudoLms($tipo)
    {
        $tipo = (string) $tipo;
        $mapa = array(
            'texto' => 'Texto',
            'arquivo' => 'Arquivo',
            'link' => 'Link',
            'video' => 'Vídeo',
            'avaliacao_textual' => 'Avaliação textual',
            'etiqueta' => 'Etiqueta',
        );

        if (isset($mapa[$tipo])) {
            return $mapa[$tipo];
        }

        $tipo = str_replace('_', ' ', $tipo);
        return $tipo !== '' ? ucwords($tipo) : '-';
    }

    public static function path($relativePath = '')
    {
        return BASE_PATH . ($relativePath ? '/' . ltrim($relativePath, '/\\') : '');
    }

    public static function url($path = '')
    {
        $config = require BASE_PATH . '/config/app.php';

        return rtrim($config['url'], '/') . '/' . ltrim($path, '/');
    }
}
