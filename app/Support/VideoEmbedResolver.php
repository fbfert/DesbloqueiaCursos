<?php

namespace App\Support;

/**
 * Resolve uma URL de video (YouTube, Vimeo ou Google Drive) para uma URL de
 * embed segura, sem depender de HTML bruto salvo no banco. So gera o iframe
 * para dominios conhecidos e com ID extraido por regex (whitelist), nunca a
 * partir de HTML livre - por isso nao precisa passar por App\Support\HtmlSanitizer.
 */
class VideoEmbedResolver
{
    public static function resolve($url)
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }

        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?: ''));
        $host = preg_replace('/^www\./', '', $host);

        if ($host === 'youtube.com' || $host === 'youtu.be' || $host === 'm.youtube.com') {
            $id = self::extrairIdYoutube($url);
            if ($id !== null) {
                return array(
                    'provider' => 'youtube',
                    'embedUrl' => 'https://www.youtube-nocookie.com/embed/' . $id,
                );
            }
        }

        if ($host === 'vimeo.com' || $host === 'player.vimeo.com') {
            $id = self::extrairIdVimeo($url);
            if ($id !== null) {
                return array(
                    'provider' => 'vimeo',
                    'embedUrl' => 'https://player.vimeo.com/video/' . $id,
                );
            }
        }

        if ($host === 'drive.google.com') {
            $id = self::extrairIdGoogleDrive($url);
            if ($id !== null) {
                return array(
                    'provider' => 'drive',
                    'embedUrl' => 'https://drive.google.com/file/d/' . $id . '/preview',
                );
            }
        }

        return null;
    }

    private static function extrairIdYoutube($url)
    {
        $padroes = array(
            '/[?&]v=([a-zA-Z0-9_-]{6,20})/',
            '#youtu\.be/([a-zA-Z0-9_-]{6,20})#',
            '#/embed/([a-zA-Z0-9_-]{6,20})#',
            '#/shorts/([a-zA-Z0-9_-]{6,20})#',
        );

        foreach ($padroes as $padrao) {
            if (preg_match($padrao, $url, $m)) {
                return $m[1];
            }
        }

        return null;
    }

    private static function extrairIdVimeo($url)
    {
        if (preg_match('#vimeo\.com/(?:video/)?(\d{6,12})#', $url, $m)) {
            return $m[1];
        }

        return null;
    }

    private static function extrairIdGoogleDrive($url)
    {
        if (preg_match('#/file/d/([a-zA-Z0-9_-]{10,80})#', $url, $m)) {
            return $m[1];
        }

        if (preg_match('#[?&]id=([a-zA-Z0-9_-]{10,80})#', $url, $m)) {
            return $m[1];
        }

        return null;
    }
}
