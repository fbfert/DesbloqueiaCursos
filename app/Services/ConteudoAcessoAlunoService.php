<?php

namespace App\Services;

use App\Core\Logger;

/**
 * Acesso do aluno ao conteúdo do curso — regras que estavam presas em
 * AreaCursoController (site) e agora são compartilhadas com a API do app.
 *
 * EXTRAÇÃO PURA: a ordem das leituras e gravações é a mesma do controller
 * original (resumo calculado antes do registro de acesso; auto-conclusão de
 * texto/HTML; log de "visualizou_feedback" da avaliação corrigida). O controller
 * HTML continua responsável só por URLs, flash e view.
 *
 * O usuário vem SEMPRE por parâmetro: no site, de Session::get('usuario_id');
 * no app, de App\Support\AppAuth. ConteudoCursoService não confere posse — por
 * isso todo acesso passa antes por carregarContexto() (AreaCursoService::carregarAluno).
 */
class ConteudoAcessoAlunoService
{
    /** Status de progresso que contam como item concluído para o aluno. */
    const STATUS_CONCLUIDO = array('concluido', 'aprovada', 'corrigida');

    private $areaCursoService;
    private $conteudoService;
    private $avaliacaoTextualService;

    public function __construct(
        ?AreaCursoService $areaCursoService = null,
        ?ConteudoCursoService $conteudoService = null,
        ?ConteudoAvaliacaoTextualService $avaliacaoTextualService = null
    ) {
        $this->areaCursoService = $areaCursoService ?: new AreaCursoService();
        $this->conteudoService = $conteudoService ?: new ConteudoCursoService();
        $this->avaliacaoTextualService = $avaliacaoTextualService ?: new ConteudoAvaliacaoTextualService();
    }

    /**
     * Carrega e valida a inscrição do aluno para a área de conteúdo.
     *
     * @param array $parametros inscricao_id, curso_id (opcional), turma_id (opcional)
     * @param bool  $exigirInscricaoExata quando true (API), a inscrição devolvida precisa
     *              ser exatamente a pedida. AreaCursoService::carregarAluno cai para a
     *              primeira inscrição acessível quando o id não confere; o site sempre
     *              aceitou isso, a API não pode.
     *
     * @return array ok=true: contexto, inscricao, curso_id, turma_id
     *               ok=false: message, motivo (inscricao_invalida|sem_inscricao|curso_divergente|turma_divergente|sem_acesso)
     *               e, nos divergentes, `redirecionar` (inscricao_id, curso_id, turma_id)
     */
    public function carregarContexto($usuarioId, array $parametros, $exigirInscricaoExata = false)
    {
        $inscricaoId = (int) ($parametros['inscricao_id'] ?? 0);
        $cursoId = (int) ($parametros['curso_id'] ?? 0);
        $turmaId = (int) ($parametros['turma_id'] ?? 0);

        if ($inscricaoId <= 0) {
            return array('ok' => false, 'motivo' => 'inscricao_invalida', 'message' => 'Inscrição inválida.');
        }

        $contexto = $this->areaCursoService->carregarAluno(
            $usuarioId,
            $inscricaoId,
            0,
            0,
            $cursoId > 0 ? $cursoId : null,
            $turmaId > 0 ? $turmaId : null,
            null
        );

        if (empty($contexto['inscricao'])) {
            return array('ok' => false, 'motivo' => 'sem_inscricao', 'message' => 'Nenhuma inscrição válida foi encontrada para este usuário.');
        }

        $inscricao = $contexto['inscricao'];
        if ($exigirInscricaoExata && (int) $inscricao['id'] !== $inscricaoId) {
            return array('ok' => false, 'motivo' => 'sem_acesso', 'message' => 'Você não tem acesso a esta inscrição.');
        }

        $cursoInscricaoId = (int) $inscricao['curso_evento_id'];
        $turmaInscricaoId = !empty($inscricao['turma_id']) ? (int) $inscricao['turma_id'] : 0;
        $redirecionar = array(
            'inscricao_id' => $inscricaoId,
            'curso_id' => $cursoInscricaoId,
            'turma_id' => $turmaInscricaoId,
        );

        if ($cursoId > 0 && $cursoId !== $cursoInscricaoId) {
            return array('ok' => false, 'motivo' => 'curso_divergente', 'message' => 'O curso informado não corresponde à inscrição selecionada.', 'redirecionar' => $redirecionar);
        }

        if ($turmaId > 0 && ($turmaInscricaoId <= 0 || $turmaId !== $turmaInscricaoId)) {
            return array('ok' => false, 'motivo' => 'turma_divergente', 'message' => 'A turma informada não corresponde à inscrição selecionada.', 'redirecionar' => $redirecionar);
        }

        return array(
            'ok' => true,
            'contexto' => $contexto,
            'inscricao' => $inscricao,
            'curso_id' => $cursoInscricaoId,
            'turma_id' => $turmaInscricaoId,
        );
    }

    /** Sequência linear de itens (módulo a módulo), na ordem da árvore publicada. */
    public function sequencia(array $modulos)
    {
        $sequencia = array();
        foreach ($modulos as $modulo) {
            $itens = !empty($modulo['itens']) && is_array($modulo['itens']) ? $modulo['itens'] : array();
            foreach ($itens as $item) {
                $sequencia[] = array(
                    'modulo' => $modulo,
                    'item' => $item,
                );
            }
        }

        return $sequencia;
    }

    /**
     * Item anterior e próximo do item atual.
     *
     * @return array anterior/proximo: null ou {modulo_id, item_id, titulo}
     */
    public function navegacao(array $modulos, $itemId, $moduloIdPadrao = 0)
    {
        $sequencia = $this->sequencia($modulos);
        $indiceAtual = null;

        foreach ($sequencia as $indice => $registro) {
            if ((int) ($registro['item']['id'] ?? 0) === (int) $itemId) {
                $indiceAtual = $indice;
                break;
            }
        }

        if ($indiceAtual === null) {
            return array('anterior' => null, 'proximo' => null);
        }

        $anterior = isset($sequencia[$indiceAtual - 1]) ? $sequencia[$indiceAtual - 1] : null;
        $proximo = isset($sequencia[$indiceAtual + 1]) ? $sequencia[$indiceAtual + 1] : null;

        return array(
            'anterior' => $anterior !== null ? array(
                'modulo_id' => (int) ($anterior['modulo']['id'] ?? $moduloIdPadrao),
                'item_id' => (int) ($anterior['item']['id'] ?? 0),
                'titulo' => (string) ($anterior['item']['titulo'] ?? 'Conteúdo anterior'),
            ) : null,
            'proximo' => $proximo !== null ? array(
                'modulo_id' => (int) ($proximo['modulo']['id'] ?? $moduloIdPadrao),
                'item_id' => (int) ($proximo['item']['id'] ?? 0),
                'titulo' => (string) ($proximo['item']['titulo'] ?? 'Próximo conteúdo'),
            ) : null,
        );
    }

    /**
     * Abre um item já validado (buscarItemPublicadoParaAluno ok) para o aluno:
     * registra o acesso, conclui texto/HTML automaticamente e carrega as entregas
     * da avaliação textual — exatamente como a página do site sempre fez.
     *
     * @return array detalhe (resultado de buscarItemPublicadoParaAluno, recarregado após
     *               auto-conclusão), item, progresso, item_concluido, resumo,
     *               entregas_avaliacao, avaliacao_pode_enviar
     */
    public function abrirItem($usuarioId, array $inscricao, $cursoId, $turmaId, $moduloId, $itemId, array $detalhe, $ip, $userAgent)
    {
        $usuarioId = (int) $usuarioId;
        $cursoId = (int) $cursoId;
        $turmaId = (int) $turmaId;
        $moduloId = (int) $moduloId;
        $itemId = (int) $itemId;

        $item = $detalhe['item'];
        $progressoItem = isset($detalhe['progresso']) && is_array($detalhe['progresso']) ? $detalhe['progresso'] : null;
        $itemJaConcluido = !empty($progressoItem) && in_array((string) ($progressoItem['status'] ?? ''), self::STATUS_CONCLUIDO, true);
        $resumo = $this->conteudoService->obterResumoProgressoAluno(
            $cursoId,
            $usuarioId,
            (int) $inscricao['id'],
            $turmaId > 0 ? $turmaId : null
        );

        $acao = 'visualizou_item';
        if ((string) $item['tipo'] === 'texto') {
            $acao = 'abriu_texto';
        } elseif ((string) $item['tipo'] === 'html') {
            $acao = 'abriu_html';
        } elseif ((string) $item['tipo'] === 'video') {
            $acao = 'abriu_video';
        } elseif ((string) $item['tipo'] === 'video_incorporado') {
            $acao = 'abriu_video_incorporado';
        } elseif ((string) $item['tipo'] === 'avaliacao_textual') {
            $acao = 'visualizou_avaliacao_textual';
        }

        $this->conteudoService->registrarAcessoItem(array(
            'curso_evento_id' => $cursoId,
            'turma_id' => $turmaId > 0 ? $turmaId : null,
            'inscricao_id' => (int) $inscricao['id'],
            'aluno_id' => $usuarioId,
            'modulo_id' => $moduloId,
            'item_id' => (int) $item['id'],
            'obrigatorio' => !empty($item['obrigatorio']) ? 1 : 0,
            'acao' => $acao,
            'status' => (string) $item['tipo'] === 'etiqueta' ? 'concluido' : 'em_andamento',
            'percentual' => (string) $item['tipo'] === 'etiqueta' ? 100 : 10,
            'concluido_em' => (string) $item['tipo'] === 'etiqueta' ? date('Y-m-d H:i:s') : null,
            'ip' => $ip,
            'user_agent' => $userAgent,
        ));

        if (in_array((string) ($item['tipo'] ?? ''), array('texto', 'html'), true) && !$itemJaConcluido) {
            try {
                $resultadoConclusao = $this->conteudoService->concluirItemAluno(array(
                    'curso_evento_id' => $cursoId,
                    'turma_id' => $turmaId > 0 ? $turmaId : null,
                    'inscricao_id' => (int) $inscricao['id'],
                    'aluno_id' => $usuarioId,
                    'item_id' => (int) $item['id'],
                    'modulo_id' => $moduloId,
                    'ip' => $ip,
                    'user_agent' => $userAgent,
                ));

                if (empty($resultadoConclusao['ok'])) {
                    Logger::warning('conteudo.texto.auto_conclusao_nao_aplicada', array(
                        'inscricao_id' => (int) $inscricao['id'],
                        'item_id' => (int) $item['id'],
                        'message' => isset($resultadoConclusao['message']) ? $resultadoConclusao['message'] : null,
                    ));
                } else {
                    $recarregado = $this->conteudoService->buscarItemPublicadoParaAluno(
                        $itemId,
                        $usuarioId,
                        (int) $inscricao['id'],
                        $cursoId,
                        $turmaId > 0 ? $turmaId : null
                    );
                    if (!empty($recarregado['ok'])) {
                        $detalhe = $recarregado;
                        $item = $detalhe['item'];
                        $progressoItem = isset($detalhe['progresso']) && is_array($detalhe['progresso']) ? $detalhe['progresso'] : null;
                        $itemJaConcluido = !empty($progressoItem) && in_array((string) ($progressoItem['status'] ?? ''), self::STATUS_CONCLUIDO, true);
                    }
                    $resumo = $this->conteudoService->obterResumoProgressoAluno(
                        $cursoId,
                        $usuarioId,
                        (int) $inscricao['id'],
                        $turmaId > 0 ? $turmaId : null
                    );
                }
            } catch (\Exception $exception) {
                Logger::warning('conteudo.texto.auto_conclusao_falhou', array(
                    'inscricao_id' => (int) $inscricao['id'],
                    'item_id' => (int) $item['id'],
                    'message' => $exception->getMessage(),
                ));
            }
        }

        $entregasAvaliacao = array();
        $avaliacaoPodeEnviar = null;
        if ((string) ($item['tipo'] ?? '') === 'avaliacao_textual') {
            $avaliacaoId = (int) ($detalhe['detalhe']['id'] ?? 0);
            $entregasAvaliacao = $this->avaliacaoTextualService->listarEntregasAluno($avaliacaoId, $usuarioId, (int) $inscricao['id']);
            $ultimaEntrega = !empty($entregasAvaliacao) ? $entregasAvaliacao[0] : null;
            if ($ultimaEntrega) {
                $ultimaEntrega['imagens'] = $this->avaliacaoTextualService->imagensEntrega((int) $ultimaEntrega['id']);
                $entregasAvaliacao[0] = $ultimaEntrega;
            }
            $avaliacaoPodeEnviar = $this->avaliacaoTextualService->podeReenviar($avaliacaoId, $usuarioId, (int) $inscricao['id']);
            if (!empty($ultimaEntrega['status']) && in_array((string) $ultimaEntrega['status'], array('corrigida', 'aprovada', 'reprovada'), true)) {
                $this->conteudoService->registrarLogAluno(array(
                    'curso_evento_id' => $cursoId,
                    'turma_id' => $turmaId > 0 ? $turmaId : null,
                    'inscricao_id' => (int) $inscricao['id'],
                    'aluno_id' => $usuarioId,
                    'modulo_id' => $moduloId,
                    'item_id' => (int) $item['id'],
                    'acao' => 'visualizou_feedback',
                    'ip' => $ip,
                    'user_agent' => $userAgent,
                ));
            }
        }

        return array(
            'detalhe' => $detalhe,
            'item' => $item,
            'progresso' => $progressoItem,
            'item_concluido' => $itemJaConcluido,
            'resumo' => $resumo,
            'entregas_avaliacao' => $entregasAvaliacao,
            'avaliacao_pode_enviar' => $avaliacaoPodeEnviar,
        );
    }

    /**
     * Arquivo físico de um item do tipo `arquivo`, já conferido contra a
     * inscrição. Registra o download (como o site) quando o arquivo existe.
     *
     * @return array ok=true: caminho, nome, mime, detalhe | ok=false: motivo (nao_encontrado|arquivo_ausente)
     */
    public function arquivoDoItem($usuarioId, array $inscricao, $itemId, $ip, $userAgent)
    {
        $detalhe = $this->conteudoService->buscarItemPublicadoParaAluno(
            (int) $itemId,
            (int) $usuarioId,
            (int) $inscricao['id'],
            (int) $inscricao['curso_evento_id'],
            !empty($inscricao['turma_id']) ? (int) $inscricao['turma_id'] : null
        );
        if (empty($detalhe['ok']) || (string) $detalhe['item']['tipo'] !== 'arquivo') {
            return array('ok' => false, 'motivo' => 'nao_encontrado');
        }

        $arquivo = $this->conteudoService->obterArquivoDoItem((int) $detalhe['item']['id'], (int) $inscricao['curso_evento_id']);
        if (empty($arquivo['ok'])) {
            return array('ok' => false, 'motivo' => 'nao_encontrado');
        }

        $storage = new FileStorageService();
        $absolutePath = $storage->privatePath((string) $arquivo['arquivo']['caminho']);
        if (!is_file($absolutePath)) {
            $relativePath = ltrim((string) $arquivo['arquivo']['caminho'], '/\\');
            $alternativos = array(
                BASE_PATH . '/storage/private_uploads/' . $relativePath,
                dirname(BASE_PATH) . '/storage/private_uploads/' . $relativePath,
                BASE_PATH . '/public_html/storage/private_uploads/' . $relativePath,
            );

            foreach ($alternativos as $caminhoAlternativo) {
                if (is_file($caminhoAlternativo)) {
                    $absolutePath = $caminhoAlternativo;
                    break;
                }
            }
        }
        if (!is_file($absolutePath)) {
            Logger::error('conteudo.arquivo.download_arquivo_ausente', array('contexto' => 'aluno', 'item_id' => (int) $detalhe['item']['id'], 'caminho' => (string) $arquivo['arquivo']['caminho']));
            return array('ok' => false, 'motivo' => 'arquivo_ausente');
        }

        $this->conteudoService->registrarDownloadArquivoAluno(array(
            'curso_evento_id' => (int) $inscricao['curso_evento_id'],
            'turma_id' => !empty($inscricao['turma_id']) ? (int) $inscricao['turma_id'] : null,
            'inscricao_id' => (int) $inscricao['id'],
            'aluno_id' => (int) $usuarioId,
            'modulo_id' => (int) $detalhe['modulo']['id'],
            'item_id' => (int) $detalhe['item']['id'],
            'obrigatorio' => !empty($detalhe['item']['obrigatorio']) ? 1 : 0,
            'ip' => $ip,
            'user_agent' => $userAgent,
        ));

        return array(
            'ok' => true,
            'caminho' => $absolutePath,
            'nome' => !empty($arquivo['arquivo']['nome_original']) ? (string) $arquivo['arquivo']['nome_original'] : basename($absolutePath),
            'mime' => !empty($arquivo['arquivo']['mime_type']) ? (string) $arquivo['arquivo']['mime_type'] : 'application/octet-stream',
            'detalhe' => $detalhe,
        );
    }
}
