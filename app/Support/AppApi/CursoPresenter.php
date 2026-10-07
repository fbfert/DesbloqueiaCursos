<?php

namespace App\Support\AppApi;

use App\Support\VideoEmbedResolver;

/**
 * JSON de inscrições, árvore de conteúdo e item aberto (contrato §Meus cursos).
 * Só formata: nenhuma regra de acesso mora aqui.
 */
class CursoPresenter
{
    /** Tipos em que o aluno conclui manualmente (texto/HTML/etiqueta concluem ao abrir; quiz/avaliação pela correção). */
    const TIPOS_CONCLUSAO_MANUAL = array('arquivo', 'link', 'video', 'video_incorporado');

    /**
     * @param array      $inscricao linha de Inscricao::forUsuarioAprovadas
     * @param array|null $curso     CursoEvento::findById
     * @param array|null $turma     Turma::findById
     */
    public static function inscricao(array $inscricao, ?array $curso = null, ?array $turma = null)
    {
        $cursoId = (int) $inscricao['curso_evento_id'];
        $turmaId = !empty($inscricao['turma_id']) ? (int) $inscricao['turma_id'] : 0;

        $certificadoCodigo = Formato::texto($inscricao['certificado_codigo'] ?? null);
        if ($certificadoCodigo !== null) {
            $certificadoStatus = 'emitido';
        } elseif (!empty($inscricao['apto_certificado'])) {
            $certificadoStatus = 'apto';
        } else {
            $certificadoStatus = 'nao_apto';
        }

        $thumbnail = $curso && !empty($curso['thumbnail']) ? $curso['thumbnail'] : ($inscricao['curso_thumbnail'] ?? null);

        return array(
            'id' => (int) $inscricao['id'],
            'status' => (string) $inscricao['status'],
            'curso' => array(
                'id' => $cursoId,
                'titulo' => (string) ($inscricao['curso_nome'] ?? ($curso['nome'] ?? '')),
                'slug' => (string) ($inscricao['curso_slug'] ?? ($curso['slug'] ?? '')),
                'imagem_url' => Formato::urlAbsoluta($thumbnail),
                'carga_horaria' => $curso ? Formato::int($curso['carga_horaria'] ?? null) : null,
            ),
            'turma' => $turmaId > 0 ? array(
                'id' => $turmaId,
                'nome' => (string) ($inscricao['turma_nome'] ?? ($turma['nome'] ?? '')),
                'inicio' => $turma ? Tempo::data($turma['data_inicio'] ?? null) : null,
                'fim' => $turma ? Tempo::data($turma['data_fim'] ?? null) : null,
            ) : null,
            'progresso_percentual' => (int) round((float) ($inscricao['percentual_progresso'] ?? 0)),
            'acesso_expira_em' => Tempo::iso($inscricao['acesso_expira_em'] ?? null),
            'certificado' => array(
                'status' => $certificadoStatus,
                'codigo' => $certificadoCodigo,
            ),
            'atualizado_em' => Tempo::iso(!empty($inscricao['updated_at']) ? $inscricao['updated_at'] : ($inscricao['created_at'] ?? null)),
        );
    }

    /** Árvore de módulos/itens publicados (resultado de listarConteudoPublicadoAluno). */
    public static function modulos(array $modulos)
    {
        $saida = array();
        foreach ($modulos as $modulo) {
            $itens = array();
            foreach ((array) ($modulo['itens'] ?? array()) as $item) {
                $itens[] = self::itemArvore($item);
            }
            $saida[] = array(
                'id' => (int) $modulo['id'],
                'titulo' => (string) $modulo['titulo'],
                'ordem' => (int) ($modulo['ordem'] ?? 0),
                'concluido' => (string) ($modulo['status_publico'] ?? '') === 'concluido',
                'itens' => $itens,
            );
        }

        return $saida;
    }

    /** Item no formato da árvore (enriquecido por ConteudoCursoService). */
    public static function itemArvore(array $item)
    {
        $progresso = !empty($item['progresso_aluno']) && is_array($item['progresso_aluno']) ? $item['progresso_aluno'] : null;
        $statusPublico = isset($item['status_publico']) ? (string) $item['status_publico'] : (string) ($progresso['status'] ?? 'nao_iniciado');

        return array(
            'id' => (int) $item['id'],
            'tipo' => (string) $item['tipo'],
            'titulo' => (string) $item['titulo'],
            'ordem' => (int) ($item['ordem'] ?? 0),
            'obrigatorio' => !empty($item['obrigatorio']),
            'status' => self::statusItem($statusPublico),
            'concluido_em' => $progresso ? Tempo::isoPhp($progresso['concluido_em'] ?? null) : null,
        );
    }

    /**
     * Status público do site → enum do contrato:
     * nao_iniciado|em_andamento|concluido|enviado|corrigido|aprovado|reprovado.
     */
    public static function statusItem($statusPublico)
    {
        switch ((string) $statusPublico) {
            case 'concluido':
                return 'concluido';
            case 'aguardando_correcao':
            case 'pendente_correcao':
                return 'enviado';
            case 'corrigida':
            case 'corrigido':
                return 'corrigido';
            case 'aprovada':
            case 'aprovado':
                return 'aprovado';
            case 'reprovada':
            case 'reprovado':
                return 'reprovado';
            case 'acessado':
            case 'em_andamento':
            case 'devolvida':
                return 'em_andamento';
            default:
                // nao_iniciado, aguardando_envio, pendente, cancelada
                return 'nao_iniciado';
        }
    }

    /**
     * Item aberto (contrato `GET /inscricoes/{id}/itens/{item}`).
     *
     * @param array      $item        item enriquecido (buscarItemPublicadoParaAluno)
     * @param array|null $detalhe     detalhe por tipo (texto, vídeo, arquivo...)
     * @param array      $navegacao   ConteudoAcessoAlunoService::navegacao
     */
    public static function itemAberto($inscricaoId, array $item, ?array $detalhe, array $navegacao)
    {
        $tipo = (string) $item['tipo'];
        $detalhe = is_array($detalhe) ? $detalhe : array();

        $dadosItem = self::itemArvore($item);
        $dadosItem['atualizado_em'] = Tempo::iso(!empty($item['updated_at']) ? $item['updated_at'] : ($item['created_at'] ?? null));

        return array(
            'item' => $dadosItem,
            'conteudo' => array(
                'html' => self::html($tipo, $detalhe),
                'video' => self::video($tipo, $detalhe),
                'link' => self::link($tipo, $detalhe),
                'arquivo' => self::arquivo($tipo, $detalhe, (int) $inscricaoId, (int) $item['id']),
            ),
            'anterior' => self::vizinho($navegacao['anterior'] ?? null),
            'proximo' => self::vizinho($navegacao['proximo'] ?? null),
            'pode_concluir_manualmente' => in_array($tipo, self::TIPOS_CONCLUSAO_MANUAL, true),
        );
    }

    private static function vizinho($registro)
    {
        if (!is_array($registro) || empty($registro['item_id'])) {
            return null;
        }
        return array('id' => (int) $registro['item_id'], 'titulo' => (string) $registro['titulo']);
    }

    private static function html($tipo, array $detalhe)
    {
        switch ($tipo) {
            case 'texto':
            case 'html':
            case 'etiqueta':
                return Formato::htmlSeguro($detalhe['conteudo'] ?? '', 'full');
            case 'avaliacao_textual':
                $html = trim((string) ($detalhe['enunciado'] ?? ''));
                if (trim((string) ($detalhe['orientacoes'] ?? '')) !== '') {
                    $html .= "\n" . (string) $detalhe['orientacoes'];
                }
                return Formato::htmlSeguro($html, 'full');
            case 'quiz':
                return Formato::htmlSeguro($detalhe['instrucoes'] ?? '', 'full');
            default:
                // vídeo, vídeo incorporado, link e arquivo não têm corpo de texto próprio.
                return null;
        }
    }

    private static function video($tipo, array $detalhe)
    {
        if ($tipo === 'video') {
            $url = trim((string) ($detalhe['url'] ?? ''));
            if ($url === '' && !empty($detalhe['embed_html'])) {
                $url = self::srcDoEmbed((string) $detalhe['embed_html']);
            }
            return $url === '' ? null : self::videoDeUrl($url);
        }

        if ($tipo === 'video_incorporado') {
            $src = self::srcDoEmbed((string) ($detalhe['conteudo'] ?? ''));
            return $src === '' ? null : self::videoDeUrl($src);
        }

        return null;
    }

    /** {provedor: youtube|vimeo|outro, video_id, url} a partir da URL (whitelist do site). */
    public static function videoDeUrl($url)
    {
        $url = trim((string) $url);
        if (strpos($url, '//') === 0) {
            $url = 'https:' . $url;
        }
        $resolvido = VideoEmbedResolver::resolve($url);
        $provedor = 'outro';
        $videoId = null;
        if (is_array($resolvido) && in_array($resolvido['provider'], array('youtube', 'vimeo'), true)) {
            $provedor = $resolvido['provider'];
            $partes = explode('/', rtrim((string) $resolvido['embedUrl'], '/'));
            $videoId = end($partes) ?: null;
        }

        return array('provedor' => $provedor, 'video_id' => $videoId, 'url' => $url);
    }

    private static function srcDoEmbed($html)
    {
        if (preg_match('/<(?:iframe|video|source|embed)[^>]+src\s*=\s*["\']([^"\']+)["\']/i', (string) $html, $m)) {
            $src = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (preg_match('#^(https?:)?//#i', $src)) {
                return $src;
            }
        }
        return '';
    }

    private static function link($tipo, array $detalhe)
    {
        if ($tipo !== 'link') {
            return null;
        }
        $url = trim((string) ($detalhe['url'] ?? ''));
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }
        return array('url' => $url, 'abrir_externo' => true);
    }

    private static function arquivo($tipo, array $detalhe, $inscricaoId, $itemId)
    {
        if ($tipo !== 'arquivo' || empty($detalhe['caminho'])) {
            return null;
        }
        $nome = Formato::texto($detalhe['nome_original'] ?? null) ?: Formato::texto($detalhe['nome_arquivo'] ?? null) ?: 'arquivo';

        return array(
            'nome' => ArquivoResposta::nomeSeguro($nome),
            'mime' => Formato::texto($detalhe['mime_type'] ?? null) ?: 'application/octet-stream',
            'tamanho_bytes' => Formato::int($detalhe['tamanho_bytes'] ?? null),
            'download_url' => '/api/app/v1/inscricoes/' . (int) $inscricaoId . '/itens/' . (int) $itemId . '/arquivo',
        );
    }
}
