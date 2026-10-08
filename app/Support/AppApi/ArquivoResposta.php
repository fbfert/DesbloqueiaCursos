<?php

namespace App\Support\AppApi;

use App\Core\Response;

/**
 * Resposta binária lida do disco em blocos (PDF, imagem, material do curso),
 * com Content-Type, Content-Length e Content-Disposition — sem carregar o
 * arquivo inteiro na memória e sem expor o caminho de armazenamento.
 *
 * Para conteúdo gerado em memória (PDF de certificado) use `deConteudo()`.
 */
class ArquivoResposta extends Response
{
    private $caminho;
    private $conteudo;
    private $mime;
    private $nome;
    private $disposicao;

    public function __construct($caminho, $mime, $nome, $disposicao = 'attachment', $conteudo = null)
    {
        parent::__construct('', 200, array());
        $this->caminho = (string) $caminho;
        $this->conteudo = $conteudo;
        $this->mime = trim((string) $mime) !== '' ? (string) $mime : 'application/octet-stream';
        $this->nome = self::nomeSeguro((string) $nome !== '' ? $nome : basename($this->caminho));
        $this->disposicao = $disposicao === 'inline' ? 'inline' : 'attachment';
    }

    public static function deConteudo($conteudo, $mime, $nome, $disposicao = 'attachment')
    {
        return new self('', $mime, $nome, $disposicao, (string) $conteudo);
    }

    public function tamanho()
    {
        if ($this->conteudo !== null) {
            return strlen($this->conteudo);
        }
        return is_file($this->caminho) ? (int) filesize($this->caminho) : 0;
    }

    public function cabecalhos()
    {
        $ascii = preg_replace('/[^A-Za-z0-9._-]+/', '_', $this->nome);
        return array(
            'Content-Type' => $this->mime,
            'Content-Length' => (string) $this->tamanho(),
            'Content-Disposition' => $this->disposicao . '; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($this->nome),
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        );
    }

    public function send()
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code(200);
        foreach ($this->cabecalhos() as $nome => $valor) {
            header($nome . ': ' . $valor);
        }

        if ($this->conteudo !== null) {
            echo $this->conteudo;
            return;
        }

        $fp = @fopen($this->caminho, 'rb');
        if ($fp === false) {
            return;
        }
        while (!feof($fp)) {
            echo fread($fp, 65536);
            flush();
        }
        fclose($fp);
    }

    public static function nomeSeguro($nome)
    {
        $nome = basename(str_replace('\\', '/', (string) $nome));
        $nome = preg_replace('/[\x00-\x1F\x7F"]+/u', '', $nome);
        return $nome !== '' && $nome !== null ? $nome : 'arquivo';
    }
}
