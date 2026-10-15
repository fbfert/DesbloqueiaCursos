<?php

namespace App\Support;

/**
 * Mapa das rotas GET legadas (visual V1) do site público e da área do aluno para
 * o equivalente no tema caderno (mudança OpenSpec tema-caderno-unico).
 *
 * Páginas públicas estáveis usam 301; páginas com sessão, checkout e redefinição
 * de senha usam 302. Só os parâmetros que identificam o conteúdo são preservados.
 *
 * destino() devolve:
 *  - array('url' => ..., 'status' => 301|302) para um redirecionamento direto;
 *  - array('resolver' => 'conteudo'|'quiz_resultado', ...) quando o destino
 *    depende de dado do banco (tipo do item, contexto da tentativa);
 *  - null quando o caminho não é uma rota legada mapeada.
 */
class RotaLegada
{
    /** caminho => [destino, status, parâmetros preservados ('*' = todos)] */
    const MAPA = array(
        '/login' => array('/v2/login', 301, array('redirect', 'origem')),
        '/cadastro' => array('/v2/cadastro', 301, array('redirect')),
        '/recuperar-senha' => array('/v2/recuperar-senha', 301, array()),
        '/esqueci-minha-senha' => array('/v2/recuperar-senha', 301, array()),
        '/recuperar-senha/redefinir' => array('/v2/recuperar-senha/redefinir', 302, array('token')),
        '/esqueci-minha-senha/redefinir' => array('/v2/recuperar-senha/redefinir', 302, array('token')),
        '/cursos' => array('/v2/catalogo', 301, array('busca', 'categoria', 'pagina')),
        '/cursos/detalhe' => array('/v2/curso', 301, array('curso_id', 'turma_id', 'id')),
        '/categorias' => array('/v2/categorias', 301, array()),
        '/sobre' => array(V2Nav::QUEM_SOMOS, 301, array()),
        '/contato' => array(V2Nav::ONDE_ESTAMOS, 301, array()),
        '/como-funciona' => array(V2Nav::COMO_FUNCIONA_SALA, 301, array()),
        '/inscricao' => array('/v2/checkout/inscricao', 302, '*'),
        '/checkout/inscricao' => array('/v2/checkout/inscricao', 302, '*'),
        '/checkout/participantes' => array('/v2/checkout/participantes', 302, '*'),
        '/checkout/resumo' => array('/v2/checkout/resumo', 302, '*'),
        '/checkout/cupom' => array('/v2/checkout/resumo', 302, '*'),
        '/checkout/comprovante' => array('/v2/checkout/comprovante', 302, '*'),
        '/checkout/sucesso' => array('/v2/checkout/sucesso', 302, '*'),
        '/meus-cursos' => array('/v2/aluno/', 302, array()),
        '/aluno/meus-cursos' => array('/v2/aluno/', 302, array()),
        '/pedidos' => array('/v2/aluno/?aba=pedidos', 302, array()),
        '/minha-conta' => array('/v2/minha-conta', 302, array()),
        '/certificados' => array('/v2/certificados/validar', 301, array('codigo')),
        '/certificados/validar' => array('/v2/certificados/validar', 301, array('codigo')),
        '/certificados/show' => array('/v2/certificados/validar', 301, array('codigo')),
        '/certificados/versao-online' => array('/v2/certificados/versao-online', 301, array('codigo')),
    );

    /** Caminhos da área do curso que levam à aula (módulo opcional). */
    const AULA = array('/area-curso', '/aluno/cursos', '/area-curso/modulo', '/aluno/cursos/modulo');

    /** Caminhos de item de conteúdo: o destino depende do tipo do item. */
    const CONTEUDO = array('/area-curso/conteudo/item', '/aluno/cursos/conteudo/item');

    public static function destino(string $path, array $query): ?array
    {
        $path = $path === '' ? '/' : $path;

        if (isset(self::MAPA[$path])) {
            list($alvo, $status, $preservar) = self::MAPA[$path];
            return array('url' => self::montar($alvo, self::filtrar($query, $preservar)), 'status' => $status);
        }

        if (preg_match('#^/categorias/([^/]+)/cursos$#', $path, $m)) {
            return array('url' => '/v2/catalogo/?categoria=' . rawurlencode(rawurldecode($m[1])), 'status' => 301);
        }

        if ($path === '/aluno/cursos/quiz/resultado') {
            return array('resolver' => 'quiz_resultado', 'tentativa_id' => (int) ($query['tentativa_id'] ?? 0));
        }

        // /aluno/curso/{inscricao}/{curso}/{turma}[/modulo/{modulo}[/conteudo/{item}]]
        if (preg_match('#^/aluno/curso/(\d+)/(\d+)/(\d+)(?:/modulo/(\d+)(?:/conteudo/(\d+))?)?$#', $path, $m)) {
            $ctx = array(
                'inscricao_id' => (int) $m[1],
                'curso_id' => (int) $m[2],
                'turma_id' => (int) $m[3],
                'modulo_id' => isset($m[4]) ? (int) $m[4] : 0,
            );
            if (!empty($m[5])) {
                return array('resolver' => 'conteudo', 'contexto' => $ctx, 'conteudo_id' => (int) $m[5]);
            }
            return array('url' => self::urlEstudo('aula', $ctx, 0), 'status' => 302);
        }

        $ctx = array(
            'inscricao_id' => (int) ($query['inscricao_id'] ?? 0),
            'curso_id' => (int) ($query['curso_id'] ?? 0),
            'turma_id' => (int) ($query['turma_id'] ?? 0),
            'modulo_id' => (int) ($query['modulo_id'] ?? 0),
        );

        if (in_array($path, self::AULA, true)) {
            return array('url' => self::urlEstudo('aula', $ctx, 0), 'status' => 302);
        }

        if (in_array($path, self::CONTEUDO, true)) {
            $conteudoId = (int) ($query['conteudo_id'] ?? ($query['id'] ?? 0));
            return array('resolver' => 'conteudo', 'contexto' => $ctx, 'conteudo_id' => $conteudoId);
        }

        return null;
    }

    /**
     * URL de estudo no tema conforme o tipo do item: quiz, atividade discursiva ou aula.
     * Sem inscrição não há como abrir o estudo: leva à área do aluno.
     */
    public static function urlEstudo(string $tipo, array $ctx, int $conteudoId): string
    {
        if ((int) ($ctx['inscricao_id'] ?? 0) <= 0) {
            return V2Nav::ALUNO;
        }

        $base = $tipo === 'quiz' ? '/v2/quiz' : ($tipo === 'avaliacao_textual' ? '/v2/atividade' : '/v2/aula/');
        $params = array(
            'inscricao_id' => (int) $ctx['inscricao_id'],
            'curso_id' => (int) ($ctx['curso_id'] ?? 0),
            'turma_id' => (int) ($ctx['turma_id'] ?? 0),
        );
        if ((int) ($ctx['modulo_id'] ?? 0) > 0) {
            $params['modulo_id'] = (int) $ctx['modulo_id'];
        }
        if ($conteudoId > 0) {
            $params['conteudo_id'] = $conteudoId;
        }

        return $base . '?' . http_build_query($params);
    }

    private static function filtrar(array $query, $preservar): array
    {
        if ($preservar === '*') {
            return $query;
        }

        $saida = array();
        foreach ($preservar as $chave) {
            if (isset($query[$chave]) && is_scalar($query[$chave]) && (string) $query[$chave] !== '') {
                $saida[$chave] = (string) $query[$chave];
            }
        }

        return $saida;
    }

    private static function montar(string $alvo, array $query): string
    {
        if (empty($query)) {
            return $alvo;
        }

        return $alvo . (strpos($alvo, '?') === false ? '?' : '&') . http_build_query($query);
    }
}
