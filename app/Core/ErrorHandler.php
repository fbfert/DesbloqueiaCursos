<?php

namespace App\Core;

class ErrorHandler
{
    private static $debug = false;
    private static $json = false;
    private static $renderizando = false;

    /**
     * API do app (/api/app/*): erro do PHP vira `500 {"erro": {"codigo": "erro_interno"}}`,
     * sem mensagem, arquivo nem pilha — mesmo com APP_DEBUG ligado. O detalhe vai
     * só para o log.
     */
    public static function usarRespostaJson($json = true)
    {
        self::$json = (bool) $json;
    }

    public static function register($debug = false)
    {
        self::$debug = (bool) $debug;

        error_reporting(E_ALL);
        ini_set('display_errors', self::$debug ? '1' : '0');

        set_exception_handler(array(__CLASS__, 'handleException'));
        set_error_handler(array(__CLASS__, 'handleError'));
    }

    public static function handleError($severity, $message, $file, $line)
    {
        if (!(error_reporting() & $severity)) {
            return false;
        }

        self::render(500, $message, $file, $line);
        return true;
    }

    public static function handleException($exception)
    {
        self::render(500, $exception->getMessage(), $exception->getFile(), $exception->getLine());
    }

    private static function render($status, $message, $file, $line)
    {
        Logger::error('app.error', array(
            'message' => $message,
            'file' => $file,
            'line' => $line,
        ));

        if (self::$json) {
            \App\Support\AppApi\Resposta::enviarErroInterno();
            exit;
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        http_response_code($status);

        // Um erro dentro da própria página de erro não pode reentrar aqui em laço.
        if (self::$renderizando) {
            echo self::paginaMinima();
            exit;
        }
        self::$renderizando = true;

        $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);

        try {
            if (\App\Support\V2ErrorPage::usaTemaPublico($path)) {
                $detalhe = self::$debug ? $message . ' em ' . $file . ':' . $line : null;
                \App\Support\V2ErrorPage::serverError($path, $detalhe)->send();
                exit;
            }

            echo View::render('errors/500', array(
                'title' => 'Erro interno',
                'debug' => self::$debug,
                'message' => $message,
                'file' => $file,
                'line' => $line,
            ));
        } catch (\Throwable $e) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            echo self::paginaMinima();
        }

        exit;
    }

    private static function paginaMinima()
    {
        return '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1"><title>Erro interno</title></head>'
            . '<body style="font-family:sans-serif;max-width:560px;margin:48px auto;padding:0 16px">'
            . '<h1>Erro interno</h1><p>Não foi possível concluir a solicitação. Tente de novo em instantes.</p>'
            . '<p><a href="/v2/">Voltar ao início</a></p></body></html>';
    }
}
