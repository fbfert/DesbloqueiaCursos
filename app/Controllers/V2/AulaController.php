<?php

namespace App\Controllers\V2;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Core\Helpers;
use App\Services\AreaCursoService;
use App\Services\ConteudoCursoService;
use App\Support\VideoEmbedResolver;
use App\Support\NorminhaHints;

/**
 * LMS V2 (Fase 2.7) — estritamente LEITURA e NAVEGAÇÃO.
 *
 * Reutiliza a MESMA autorização do LMS atual:
 * - `AreaCursoService::carregarAluno($usuarioId, $inscricaoId, ...)` só retorna a
 *   inscrição quando ela pertence ao usuário da sessão (via
 *   `forUsuarioAprovadas`) e cruza curso/turma com a inscrição real;
 * - `ConteudoCursoService::listarConteudoPublicadoAluno(...)` e
 *   `buscarItemPublicadoParaAluno(...)` retornam apenas módulos/itens
 *   PUBLICADOS e permitidos.
 *
 * Esta fase NÃO escreve nada: não chama `registrarAcessoItem`,
 * `concluirItemAluno`, `registrarLogAluno`, nem qualquer método de progresso/
 * quiz/atividade. Apenas leitura. IDs de URL são meros localizadores; a
 * autorização ocorre sempre no backend com o usuário da sessão.
 */
class AulaController extends Controller
{
    /** @var AreaCursoService */
    private $areaCursoService;
    /** @var ConteudoCursoService */
    private $conteudoService;

    public function __construct()
    {
        $this->areaCursoService = new AreaCursoService();
        $this->conteudoService = new ConteudoCursoService();
    }

    public function index(Request $request)
    {
        // --- Autenticação real (mesma sessão do sistema) ---
        $usuarioId = (int) Session::get('usuario_id', 0);
        if ($usuarioId <= 0) {
            // Sem preservar nenhum ID de curso/turma/inscrição/módulo/item.
            return Response::redirect('/v2/login?origem=v2_aluno');
        }

        // --- Localizadores de navegação (autorização é no backend) ---
        $inscricaoId = (int) $request->query('inscricao_id', 0);
        $cursoIdParam = (int) $request->query('curso_id', 0);
        $turmaIdParam = (int) $request->query('turma_id', 0);
        $moduloIdParam = (int) $request->query('modulo_id', 0);
        $conteudoId = (int) $request->query('conteudo_id', $request->query('id', 0));

        $base = $this->dadosLayout($usuarioId);

        if ($inscricaoId <= 0) {
            return $this->estado($base, 'Selecione um curso', 'Escolha um curso na sua área para começar a estudar.', 200);
        }

        // --- Autorização por posse da inscrição (mesma lógica do LMS atual) ---
        $contexto = $this->areaCursoService->carregarAluno(
            $usuarioId,
            $inscricaoId,
            0,
            0,
            $cursoIdParam > 0 ? $cursoIdParam : null,
            $turmaIdParam > 0 ? $turmaIdParam : null,
            null
        );

        if (empty($contexto['inscricao'])) {
            return $this->estado($base, 'Conteúdo indisponível', 'Não encontramos uma inscrição válida sua para este curso. Verifique na sua área.', 404);
        }

        $inscricao = $contexto['inscricao'];
        $cursoId = (int) $inscricao['curso_evento_id'];
        $turmaId = !empty($inscricao['turma_id']) ? (int) $inscricao['turma_id'] : 0;

        // O curso/turma informados precisam corresponder à inscrição real.
        if ($cursoIdParam > 0 && $cursoIdParam !== $cursoId) {
            return $this->estado($base, 'Conteúdo indisponível', 'O curso informado não corresponde à sua inscrição.', 404);
        }
        if ($turmaIdParam > 0 && ($turmaId <= 0 || $turmaIdParam !== $turmaId)) {
            return $this->estado($base, 'Conteúdo indisponível', 'A turma informada não corresponde à sua inscrição.', 404);
        }

        // --- Conteúdo publicado e permitido (somente leitura) ---
        $conteudoAluno = $this->conteudoService->listarConteudoPublicadoAluno(
            $cursoId,
            $usuarioId,
            (int) $inscricao['id'],
            $turmaId > 0 ? $turmaId : null
        );
        $modulos = !empty($conteudoAluno['ok']) && !empty($conteudoAluno['modulos']) && is_array($conteudoAluno['modulos'])
            ? $conteudoAluno['modulos']
            : array();
        $resumo = !empty($conteudoAluno['ok']) && !empty($conteudoAluno['resumo']) && is_array($conteudoAluno['resumo'])
            ? $conteudoAluno['resumo']
            : array();

        $curso = isset($contexto['curso']) && is_array($contexto['curso']) ? $contexto['curso'] : array();
        $turma = isset($contexto['turma']) && is_array($contexto['turma']) ? $contexto['turma'] : array();

        $cabecalho = array(
            'curso_nome' => isset($curso['nome']) ? (string) $curso['nome'] : '',
            'turma_nome' => isset($turma['nome']) ? (string) $turma['nome'] : '',
            'progresso' => $this->progressoPercentual($inscricao, $resumo),
        );

        // --- Item atual (validado pelo backend) ---
        $itemAtual = null;
        $itemInacessivel = false;
        $moduloAtualId = $moduloIdParam;

        if ($conteudoId > 0) {
            $detalhe = $this->conteudoService->buscarItemPublicadoParaAluno(
                $conteudoId,
                $usuarioId,
                (int) $inscricao['id'],
                $cursoId,
                $turmaId > 0 ? $turmaId : null
            );
            if (empty($detalhe['ok'])) {
                // Não expõe nada do item bloqueado/indisponível.
                $itemInacessivel = true;
            } else {
                $itemAtual = $this->montarItem($detalhe['item'], $detalhe['detalhe'], $detalhe['modulo'], $inscricao, $cursoId, $turmaId);
                $moduloAtualId = (int) ($detalhe['modulo']['id'] ?? $moduloAtualId);
            }
        }
        // Sem item escolhido na URL: mostra a visão geral (lista de módulos e
        // conteúdos) em vez de abrir direto o primeiro conteúdo do curso.

        // Suprime a conclusão automática (auto_leitura) por uma única exibição,
        // logo após o aluno desmarcar manualmente este mesmo item — sem isso, o
        // próprio carregamento desta página remarcaria o item na hora.
        $itemSemAutoCompletarId = (int) Session::pullFlash('v2_aula_sem_autocompletar_item_id', 0);
        if ($itemAtual !== null && $itemSemAutoCompletarId > 0 && (int) $itemAtual['id'] === $itemSemAutoCompletarId) {
            $itemAtual['auto_leitura'] = false;
        }

        $arvore = $this->montarArvore($modulos, $inscricao, $cursoId, $turmaId, $moduloAtualId, $conteudoId);
        // Visão geral (nenhum módulo indicado na URL): abre o primeiro módulo por
        // padrão, em vez de mostrar a lista inteira fechada.
        if ($moduloAtualId <= 0 && $itemAtual === null && !empty($arvore)) {
            $arvore[0]['aberto'] = true;
        }
        $navegacao = $this->montarNavegacao($modulos, $inscricao, $cursoId, $turmaId, $conteudoId);

        // Contexto mínimo para o formulário POST de conclusão (localizadores;
        // a autorização é refeita no servidor ao concluir).
        $formCtx = array(
            'inscricao_id' => (int) $inscricao['id'],
            'curso_id' => $cursoId,
            'turma_id' => $turmaId,
            'modulo_id' => (int) $moduloAtualId,
            'item_id' => (int) $conteudoId,
            'concluir_action' => '/v2/aula/concluir',
        );

        // URL para voltar à visão geral (lista de módulos e conteúdos), preservando
        // o módulo atual para já abrir expandido quando o aluno voltar.
        $overviewUrl = $this->urlV2Overview($inscricao, $cursoId, $turmaId, $moduloAtualId);

        // Pistas para a Norminha.
        //
        // O item vem de $itemAtual, que é o RESOLVIDO — não de $formCtx, que
        // carrega o conteudo_id cru da URL. A diferença aparece quando alguém
        // troca o conteudo_id por um item de outro curso: a página ignora o
        // pedido e mostra a visão geral (o conteúdo alheio nunca é exibido),
        // mas $formCtx continuaria com o id inválido. A Norminha então
        // anunciaria uma aula que o aluno não está vendo, e ofereceria "tirar
        // dúvida desta aula" sobre coisa nenhuma.
        //
        // O backend revalida tudo de novo — isto é sugestão, não autorização.
        $norminhaIds = $formCtx;
        $norminhaIds['item_id'] = $itemAtual !== null ? (int) $formCtx['item_id'] : null;

        $norminhaContexto = NorminhaHints::montar(
            $norminhaIds['item_id'] !== null ? NorminhaHints::CONTEXTO_AULA : NorminhaHints::CONTEXTO_CURSO,
            $norminhaIds
        );

        $data = array_merge($base, array(
            'norminhaContexto' => $norminhaContexto,
            'title' => ($cabecalho['curso_nome'] !== '' ? $cabecalho['curso_nome'] : 'Aula') . ' — Desbloqueia Cursos',
            'pageTitle' => ($cabecalho['curso_nome'] !== '' ? $cabecalho['curso_nome'] : 'Aula') . ' — Desbloqueia Cursos',
            'pageDescription' => 'Ambiente de aprendizagem com seu conteúdo real.',
            'estado' => null,
            'cabecalho' => $cabecalho,
            'arvore' => $arvore,
            'navegacao' => $navegacao,
            'item' => $itemAtual,
            'itemInacessivel' => $itemInacessivel,
            'temConteudo' => !empty($modulos),
            'formCtx' => $formCtx,
            'overviewUrl' => $overviewUrl,
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
        ));

        return new Response(View::render('v2/aula', $data, false));
    }

    /**
     * POST /v2/aula/concluir — marca/desmarca a conclusão de um item.
     *
     * Segurança: CSRF (middleware automático do POST), autenticação por sessão,
     * e posse da inscrição revalidada no servidor (mesma lógica do LMS atual).
     * A REGRA DE NEGÓCIO é 100% delegada ao service real
     * (`ConteudoCursoService::concluirItemAluno` / `desmarcarItemComoConcluido`),
     * que valida publicação/tipo (rejeita quiz e avaliação), é idempotente e
     * recalcula o progresso. Nada é reimplementado aqui. Retorno SEMPRE para um
     * caminho interno fixo da própria aula V2 (nunca URL vinda do navegador).
     */
    public function concluir(Request $request)
    {
        $usuarioId = (int) Session::get('usuario_id', 0);
        if ($usuarioId <= 0) {
            return Response::redirect('/v2/login?origem=v2_aluno');
        }

        $inscricaoId = (int) $request->input('inscricao_id', 0);
        $cursoIdParam = (int) $request->input('curso_id', 0);
        $turmaIdParam = (int) $request->input('turma_id', 0);
        $moduloIdParam = (int) $request->input('modulo_id', 0);
        $itemId = (int) $request->input('item_id', 0);
        $acao = trim((string) $request->input('acao', 'marcar'));
        $acao = $acao === 'desmarcar' ? 'desmarcar' : 'marcar';

        // Posse da inscrição (mesma checagem real do LMS atual).
        $contexto = $this->areaCursoService->carregarAluno(
            $usuarioId,
            $inscricaoId,
            0,
            0,
            $cursoIdParam > 0 ? $cursoIdParam : null,
            $turmaIdParam > 0 ? $turmaIdParam : null,
            null
        );

        if (empty($contexto['inscricao'])) {
            Session::flash('errors', array('Acesso negado para concluir o item.'));
            return Response::redirect('/v2/aluno/');
        }

        $inscricao = $contexto['inscricao'];
        $cursoId = (int) $inscricao['curso_evento_id'];
        $turmaId = !empty($inscricao['turma_id']) ? (int) $inscricao['turma_id'] : 0;

        // Curso/turma informados precisam corresponder à inscrição real.
        if (($cursoIdParam > 0 && $cursoIdParam !== $cursoId)
            || ($turmaIdParam > 0 && ($turmaId <= 0 || $turmaIdParam !== $turmaId))) {
            Session::flash('errors', array('Acesso negado para concluir o item.'));
            return Response::redirect('/v2/aluno/');
        }

        if ($itemId <= 0) {
            Session::flash('errors', array('Conteúdo inválido.'));
            return Response::redirect($this->urlV2Curso($inscricao, $cursoId, $turmaId));
        }

        $payload = array(
            'curso_evento_id' => $cursoId,
            'turma_id' => $turmaId > 0 ? $turmaId : null,
            'inscricao_id' => (int) $inscricao['id'],
            'aluno_id' => $usuarioId,
            'item_id' => $itemId,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        );

        // Regra de negócio 100% no service real (publicação, tipo, idempotência,
        // recálculo de progresso). Quiz/avaliação são rejeitados pelo próprio service.
        if ($acao === 'desmarcar') {
            $resultado = $this->conteudoService->desmarcarItemComoConcluido($payload);
        } else {
            $resultado = $this->conteudoService->concluirItemAluno($payload);
        }

        if (empty($resultado['ok'])) {
            Session::flash('errors', array(isset($resultado['message']) ? $resultado['message'] : 'Não foi possível atualizar a conclusão do item.'));
        } else {
            Session::flash('success', $acao === 'desmarcar' ? 'Conclusão desmarcada.' : 'Item marcado como concluído.');
            if ($acao === 'desmarcar') {
                // Textos/HTML concluem sozinhos ao abrir a página (auto_leitura); sem isso,
                // o próximo carregamento desta mesma aula remarcaria o item na hora,
                // escondendo o "Conclusão desmarcada." atrás de "Item marcado como concluído.".
                Session::flash('v2_aula_sem_autocompletar_item_id', $itemId);
            }
        }

        // Módulo de retorno (revalidado pelo backend).
        $moduloRet = $moduloIdParam;
        if ($moduloRet <= 0) {
            $detalhe = $this->conteudoService->buscarItemPublicadoParaAluno(
                $itemId,
                $usuarioId,
                (int) $inscricao['id'],
                $cursoId,
                $turmaId > 0 ? $turmaId : null
            );
            if (!empty($detalhe['ok'])) {
                $moduloRet = (int) ($detalhe['modulo']['id'] ?? 0);
            }
        }

        return Response::redirect($this->urlV2($inscricao, $cursoId, $turmaId, $moduloRet, $itemId));
    }

    private function urlV2Curso(array $inscricao, $cursoId, $turmaId)
    {
        return '/v2/aula/?inscricao_id=' . (int) $inscricao['id']
            . '&curso_id=' . (int) $cursoId
            . '&turma_id=' . (int) $turmaId;
    }

    private function progressoPercentual(array $inscricao, array $resumo)
    {
        if (isset($resumo['percentual']) && is_numeric($resumo['percentual'])) {
            $p = (float) $resumo['percentual'];
        } elseif (isset($inscricao['percentual_progresso']) && is_numeric($inscricao['percentual_progresso'])) {
            $p = (float) $inscricao['percentual_progresso'];
        } else {
            $p = 0.0;
        }
        if ($p < 0) { $p = 0.0; }
        if ($p > 100) { $p = 100.0; }
        return (int) round($p);
    }

    private function montarItem(array $item, $detalhe, $modulo, array $inscricao, $cursoId, $turmaId)
    {
        $detalhe = is_array($detalhe) ? $detalhe : array();
        $tipo = (string) ($item['tipo'] ?? '');

        // Conteúdos textuais já passam pelo sanitizador oficial.
        $texto = $this->valorDetalhe($detalhe, array('conteudo', 'texto', 'descricao', 'corpo', 'html'));
        if ($tipo === 'texto') {
            $texto = $this->removerTituloDuplicadoDoTexto($texto, (string) ($item['titulo'] ?? ''));
        }
        $videoEmbed = $this->valorDetalhe($detalhe, array('embed_html', 'embed', 'html'));
        $videoUrl = $this->valorDetalhe($detalhe, array('url', 'video_url', 'link'));
        $videoEmbedResolvido = $videoEmbed === '' ? VideoEmbedResolver::resolve($videoUrl) : null;
        $videoIncorporadoConteudo = $tipo === 'video_incorporado' ? $this->valorDetalhe($detalhe, array('conteudo')) : '';

        $arquivoExtensao = trim((string) $this->valorDetalhe($detalhe, array('extensao')), '. ');
        $arquivoNome = trim((string) $this->valorDetalhe($detalhe, array('nome_original', 'nome_arquivo')));
        $arquivoTamanho = Helpers::formatarTamanhoArquivo($this->valorDetalhe($detalhe, array('tamanho_bytes')));
        $arquivoIcone = Helpers::iconeArquivo($arquivoExtensao);

        $linkUrlRaw = (string) $this->valorDetalhe($detalhe, array('url'));
        $linkHost = $linkUrlRaw !== '' ? (string) preg_replace('/^www\./', '', (string) parse_url($linkUrlRaw, PHP_URL_HOST)) : '';
        $descricaoCurta = trim((string) ($item['descricao_curta'] ?? ''));

        $tiposInterativos = array('quiz', 'avaliacao_textual', 'atividade');
        $ehInterativo = in_array($tipo, $tiposInterativos, true);
        // Conclusão manual só para os tipos que o LMS atual permite concluir
        // (o service `concluirItemAluno` rejeita quiz e avaliacao_textual).
        $podeConcluir = in_array($tipo, array('texto', 'video', 'arquivo', 'link', 'html', 'video_incorporado'), true);

        return array(
            'id' => (int) ($item['id'] ?? 0),
            'titulo' => (string) ($item['titulo'] ?? ''),
            'tipo' => $tipo,
            'tipo_label' => (string) ($item['tipo_label'] ?? $tipo),
            'status_label' => (string) ($item['status_label'] ?? ''),
            'status_class' => (string) ($item['status_class'] ?? ''),
            'concluido' => !empty($item['concluido_aluno']),
            'pode_concluir' => $podeConcluir,
            'auto_leitura' => $tipo === 'texto' || $tipo === 'html',
            'modulo_titulo' => is_array($modulo) ? (string) ($modulo['titulo'] ?? '') : '',
            'texto_html' => $texto,
            'video_embed' => $videoEmbed,
            'video_url' => $videoUrl,
            'video_embed_resolvido' => $videoEmbedResolvido,
            'video_incorporado_conteudo' => $videoIncorporadoConteudo,
            'arquivo_extensao' => $arquivoExtensao,
            'arquivo_nome' => $arquivoNome,
            'arquivo_tamanho' => $arquivoTamanho,
            'arquivo_icone' => $arquivoIcone,
            'link_host' => $linkHost,
            'descricao_curta' => $descricaoCurta,
            'acao_url' => (string) ($item['acao_url'] ?? ''),
            'eh_interativo' => $ehInterativo,
            // Rota oficial atual do item no LMS (para abrir a atividade/quiz real).
            'oficial_url' => $this->urlOficialItem($inscricao, $cursoId, $turmaId, (int) (is_array($modulo) ? ($modulo['id'] ?? 0) : 0), (int) ($item['id'] ?? 0)),
            // Quiz objetivo: passa a abrir na própria V2 (Fase 2.9).
            'quiz_url' => $tipo === 'quiz'
                ? $this->urlQuizV2($inscricao, $cursoId, $turmaId, (int) (is_array($modulo) ? ($modulo['id'] ?? 0) : 0), (int) ($item['id'] ?? 0))
                : '',
            // Atividade discursiva textual: passa a abrir na própria V2 (Fase 2.10).
            'atividade_url' => $tipo === 'avaliacao_textual'
                ? $this->urlAtividadeV2($inscricao, $cursoId, $turmaId, (int) (is_array($modulo) ? ($modulo['id'] ?? 0) : 0), (int) ($item['id'] ?? 0))
                : '',
        );
    }

    /**
     * Alguns conteúdos do tipo "texto" começam com um heading (h1-h3) que
     * apenas repete o título do item — a página já exibe o título separado,
     * então isso aparece como um título duplicado para o aluno. Remove esse
     * heading só quando o texto dele é exatamente igual ao título do item
     * (evita remover headings legítimos que fazem parte do conteúdo).
     */
    private function removerTituloDuplicadoDoTexto($texto, $titulo)
    {
        $texto = (string) $texto;
        $titulo = trim((string) $titulo);
        if ($texto === '' || $titulo === '') {
            return $texto;
        }

        if (preg_match('/^\s*<h([1-3])[^>]*>(.*?)<\/h\1>\s*/is', $texto, $m)) {
            $headingTexto = trim(html_entity_decode(strip_tags($m[2]), ENT_QUOTES, 'UTF-8'));
            if ($headingTexto !== '' && mb_strtolower($headingTexto) === mb_strtolower($titulo)) {
                return substr($texto, strlen($m[0]));
            }
        }

        return $texto;
    }

    private function urlQuizV2(array $inscricao, $cursoId, $turmaId, $moduloId, $itemId)
    {
        return '/v2/quiz?inscricao_id=' . (int) $inscricao['id']
            . '&curso_id=' . (int) $cursoId
            . '&turma_id=' . (int) $turmaId
            . '&modulo_id=' . (int) $moduloId
            . '&conteudo_id=' . (int) $itemId;
    }

    private function urlAtividadeV2(array $inscricao, $cursoId, $turmaId, $moduloId, $itemId)
    {
        return '/v2/atividade?inscricao_id=' . (int) $inscricao['id']
            . '&curso_id=' . (int) $cursoId
            . '&turma_id=' . (int) $turmaId
            . '&modulo_id=' . (int) $moduloId
            . '&conteudo_id=' . (int) $itemId;
    }

    private function montarArvore(array $modulos, array $inscricao, $cursoId, $turmaId, $moduloAtualId, $conteudoId)
    {
        $arvore = array();
        foreach ($modulos as $modulo) {
            $moduloId = (int) ($modulo['id'] ?? 0);
            $itens = !empty($modulo['itens']) && is_array($modulo['itens']) ? $modulo['itens'] : array();
            $itensView = array();
            foreach ($itens as $item) {
                $itemId = (int) ($item['id'] ?? 0);
                $tipo = (string) ($item['tipo'] ?? '');
                $itensView[] = array(
                    'id' => $itemId,
                    'titulo' => (string) ($item['titulo'] ?? ''),
                    'tipo' => $tipo,
                    'tipo_label' => (string) ($item['tipo_label'] ?? $tipo),
                    'concluido' => !empty($item['concluido_aluno']),
                    'atual' => $itemId === (int) $conteudoId,
                    'etiqueta' => $tipo === 'etiqueta',
                    'url' => $this->urlV2($inscricao, $cursoId, $turmaId, $moduloId, $itemId),
                );
            }
            $arvore[] = array(
                'id' => $moduloId,
                'titulo' => (string) ($modulo['titulo'] ?? ''),
                'status_label' => (string) ($modulo['status_label'] ?? ''),
                'total_itens' => (int) ($modulo['total_itens'] ?? count($itensView)),
                'concluidos_itens' => (int) ($modulo['concluidos_itens'] ?? 0),
                'aberto' => $moduloId === (int) $moduloAtualId,
                'itens' => $itensView,
            );
        }
        return $arvore;
    }

    private function montarNavegacao(array $modulos, array $inscricao, $cursoId, $turmaId, $conteudoId)
    {
        $sequencia = array();
        foreach ($modulos as $modulo) {
            $moduloId = (int) ($modulo['id'] ?? 0);
            $itens = !empty($modulo['itens']) && is_array($modulo['itens']) ? $modulo['itens'] : array();
            foreach ($itens as $item) {
                if ((string) ($item['tipo'] ?? '') === 'etiqueta') {
                    continue;
                }
                $sequencia[] = array('modulo_id' => $moduloId, 'item_id' => (int) ($item['id'] ?? 0), 'titulo' => (string) ($item['titulo'] ?? ''));
            }
        }

        $indice = null;
        foreach ($sequencia as $i => $reg) {
            if ($reg['item_id'] === (int) $conteudoId) {
                $indice = $i;
                break;
            }
        }
        if ($indice === null) {
            return array('anterior' => null, 'proximo' => null);
        }

        $ant = isset($sequencia[$indice - 1]) ? $sequencia[$indice - 1] : null;
        $prox = isset($sequencia[$indice + 1]) ? $sequencia[$indice + 1] : null;

        return array(
            'anterior' => $ant ? array('label' => $ant['titulo'], 'url' => $this->urlV2($inscricao, $cursoId, $turmaId, $ant['modulo_id'], $ant['item_id'])) : null,
            'proximo' => $prox ? array('label' => $prox['titulo'], 'url' => $this->urlV2($inscricao, $cursoId, $turmaId, $prox['modulo_id'], $prox['item_id'])) : null,
        );
    }

    private function urlV2(array $inscricao, $cursoId, $turmaId, $moduloId, $itemId)
    {
        return '/v2/aula/?inscricao_id=' . (int) $inscricao['id']
            . '&curso_id=' . (int) $cursoId
            . '&turma_id=' . (int) $turmaId
            . '&modulo_id=' . (int) $moduloId
            . '&conteudo_id=' . (int) $itemId;
    }

    // Visão geral do curso (lista de módulos e conteúdos), sem conteúdo_id.
    private function urlV2Overview(array $inscricao, $cursoId, $turmaId, $moduloId = 0)
    {
        $url = '/v2/aula/?inscricao_id=' . (int) $inscricao['id']
            . '&curso_id=' . (int) $cursoId
            . '&turma_id=' . (int) $turmaId;
        if ((int) $moduloId > 0) {
            $url .= '&modulo_id=' . (int) $moduloId;
        }
        return $url;
    }

    private function urlOficialItem(array $inscricao, $cursoId, $turmaId, $moduloId, $itemId)
    {
        return '/aluno/curso/' . (int) $inscricao['id'] . '/' . (int) $cursoId . '/' . (int) $turmaId
            . '/modulo/' . (int) $moduloId . '/conteudo/' . (int) $itemId;
    }

    private function valorDetalhe($detalhe, array $chaves)
    {
        if (!is_array($detalhe)) {
            return '';
        }
        foreach ($chaves as $chave) {
            if (isset($detalhe[$chave]) && trim((string) $detalhe[$chave]) !== '') {
                return (string) $detalhe[$chave];
            }
        }
        return '';
    }

    private function estado(array $base, $titulo, $mensagem, $status)
    {
        $data = array_merge($base, array(
            'title' => $titulo . ' — Desbloqueia Cursos',
            'pageTitle' => $titulo . ' — Desbloqueia Cursos',
            'pageDescription' => $mensagem,
            'estado' => array('titulo' => $titulo, 'mensagem' => $mensagem),
            'cabecalho' => null,
            'arvore' => array(),
            'navegacao' => array('anterior' => null, 'proximo' => null),
            'item' => null,
            'itemInacessivel' => false,
            'temConteudo' => false,
        ));

        return new Response(View::render('v2/aula', $data, false), (int) $status);
    }

    private function dadosLayout($usuarioId)
    {
        $usuarioNome = trim((string) Session::get('usuario_nome', ''));
        $sessionPerfis = Session::get('usuario_perfis', array());
        $hasAdminAccess = (bool) Session::get('usuario_admin') || (bool) Session::get('is_admin') || in_array('admin', $sessionPerfis, true);
        $hasProfessorAccess = (bool) Session::get('usuario_professor') || (bool) Session::get('is_professor') || in_array('professor', $sessionPerfis, true);

        $areaHref = '/v2/aluno';
        if ($hasAdminAccess) {
            $areaHref = '/admin';
        } elseif ($hasProfessorAccess) {
            $areaHref = '/professor/dashboard';
        }

        return array(
            'loggedIn' => true,
            'usuarioNome' => $usuarioNome,
            'usuarioPrimeiroNome' => $this->primeiroNome($usuarioNome),
            'areaHref' => $areaHref,
            'alunoHref' => '/v2/aluno/',
            'loginHref' => '/v2/login',
            'registerHref' => '/v2/cadastro',
            'catalogoHref' => '/v2/catalogo/',
            'categoriasHref' => '/categorias',
            'certificadosHref' => '/v2/certificados/validar/',
            'sobreHref' => '/sobre',
            'contatoHref' => '/contato',
            'homeHref' => '/v2/',
        );
    }

    private function primeiroNome($nome)
    {
        $nome = trim((string) $nome);
        if ($nome === '') {
            return 'aluno';
        }
        $partes = preg_split('/\s+/', $nome);
        return ($partes && !empty($partes[0])) ? (string) $partes[0] : $nome;
    }
}
