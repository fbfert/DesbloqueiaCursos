<?php

namespace App\Support\AppApi;

/**
 * Catálogo público (contrato §Catálogo) e certificados/notificações — objetos
 * pequenos, só leitura.
 */
class CatalogoPresenter
{
    /** Curso na listagem (linha hidratada por CursoService::listPublic). */
    public static function cursoResumo(array $curso)
    {
        $valor = (float) ($curso['valor'] ?? 0);
        $promocional = null;
        if (!empty($curso['em_promocao']) && isset($curso['valor_promocional']) && $curso['valor_promocional'] !== null && $curso['valor_promocional'] !== ''
            && (float) $curso['valor_promocional'] < $valor) {
            $promocional = Formato::centavos($curso['valor_promocional']);
        }

        return array(
            'id' => (int) $curso['id'],
            'titulo' => (string) $curso['nome'],
            'slug' => (string) $curso['slug'],
            'resumo' => Formato::texto($curso['descricao_curta'] ?? null),
            'imagem_url' => Formato::urlAbsoluta($curso['thumbnail'] ?? null),
            'preco_centavos' => Formato::centavos($valor),
            'preco_promocional_centavos' => $promocional,
            'carga_horaria' => Formato::int($curso['carga_horaria'] ?? null),
            'modalidade' => (string) ($curso['modalidade'] ?? ''),
            'categoria' => Formato::texto($curso['categoria_nome'] ?? null),
        );
    }

    /**
     * Curso detalhado (CursoService::showPublic).
     *
     * @param array $vagasRestantes turma_id => int|null
     */
    public static function cursoDetalhe(array $curso, array $vagasRestantes = array())
    {
        $dados = self::cursoResumo($curso);
        $dados['descricao_html'] = Formato::htmlSeguro($curso['descricao_completa'] ?? '', 'full');

        $turmas = array();
        foreach ((array) ($curso['turmas_abertas'] ?? array()) as $turma) {
            $id = (int) $turma['id'];
            $turmas[] = array(
                'id' => $id,
                'nome' => (string) $turma['nome'],
                'inicio' => Tempo::data($turma['data_inicio'] ?? null),
                'vagas_restantes' => array_key_exists($id, $vagasRestantes) ? $vagasRestantes[$id] : null,
            );
        }
        $dados['turmas_abertas'] = $turmas;
        $dados['url_compra'] = Formato::urlSite() . '/cursos/detalhe?curso_id=' . (int) $curso['id'];

        return $dados;
    }

    public static function certificado(array $certificado)
    {
        $codigo = (string) $certificado['codigo'];

        return array(
            'codigo' => $codigo,
            'curso_titulo' => (string) ($certificado['curso_nome'] ?? ($certificado['titulo'] ?? '')),
            'emitido_em' => Tempo::iso($certificado['emitido_em'] ?? null),
            'carga_horaria' => Formato::int($certificado['curso_carga_horaria'] ?? null),
            'pdf_url' => '/api/app/v1/certificados/' . rawurlencode($codigo) . '/pdf',
            'validacao_url' => (string) ($certificado['validacao_url'] ?? (Formato::urlSite() . '/certificados/validar?codigo=' . urlencode($codigo))),
        );
    }

    public static function notificacao(array $notificacao)
    {
        $dados = json_decode((string) ($notificacao['dados'] ?? ''), true);

        return array(
            'id' => (int) $notificacao['id'],
            'tipo' => (string) $notificacao['tipo'],
            'titulo' => (string) $notificacao['titulo'],
            'corpo' => (string) $notificacao['corpo'],
            'dados' => is_array($dados) && !empty($dados) ? $dados : new \stdClass(),
            'lida' => !empty($notificacao['lida_em']),
            'criada_em' => Tempo::iso($notificacao['created_at'] ?? null),
        );
    }
}
