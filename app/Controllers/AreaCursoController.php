<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\AtividadeService;
use App\Services\AreaCursoService;
use App\Services\ConteudoAvaliacaoTextualService;
use App\Services\ConteudoCursoService;
use App\Services\FileStorageService;
use App\Services\ProgressoService;

class AreaCursoController extends Controller
{
    // Cache-bust para sincronização de deploy.
    private $areaCursoService;
    private $atividadeService;
    private $progressoService;
    private $conteudoService;
    private $conteudoAvaliacaoTextualService;

    public function __construct()
    {
        $this->areaCursoService = new AreaCursoService();
        $this->atividadeService = new AtividadeService();
        $this->progressoService = new ProgressoService();
        $this->conteudoService = new ConteudoCursoService();
        $this->conteudoAvaliacaoTextualService = new ConteudoAvaliacaoTextualService();
    }

    public function index(Request $request)
    {
        $dados = $this->areaCursoService->carregarAluno(
            Session::get('usuario_id'),
            (int) $request->query('inscricao_id', 0),
            (int) $request->query('modulo_id', 0),
            (int) $request->query('aula_id', 0),
            (int) $request->query('curso_id', 0) > 0 ? (int) $request->query('curso_id', 0) : null,
            (int) $request->query('turma_id', 0) > 0 ? (int) $request->query('turma_id', 0) : null,
            (int) $request->query('atividade_id', 0) > 0 ? (int) $request->query('atividade_id', 0) : null
        );

        if (empty($dados['inscricao'])) {
            Session::flash('errors', array('Nenhuma inscrição válida foi encontrada para este usuário.'));
            return $this->redirect('/aluno/meus-cursos');
        }

        $inscricao = $dados['inscricao'];
        $conteudoAluno = $this->conteudoService->listarConteudoPublicadoAluno(
            (int) $inscricao['curso_evento_id'],
            (int) Session::get('usuario_id'),
            (int) $inscricao['id'],
            !empty($inscricao['turma_id']) ? (int) $inscricao['turma_id'] : null
        );
        $dados['conteudo_novo'] = !empty($conteudoAluno['ok']) ? $conteudoAluno : array('ok' => false, 'modulos' => array());

        return $this->view('area-curso/index', array_merge(
            array(
                'title' => 'Sala virtual',
                'success' => Session::pullFlash('success'),
                'errors' => Session::pullFlash('errors', array()),
            ),
            $dados
        ));
    }

    public function modulo(Request $request)
    {
        $cursoId = (int) $request->query('curso_id', 0);
        $turmaId = (int) $request->query('turma_id', 0);
        $dados = $this->areaCursoService->carregarModuloAluno(
            Session::get('usuario_id'),
            (int) $request->query('inscricao_id', 0),
            (int) $request->query('modulo_id', 0),
            (int) $request->query('aula_id', 0),
            $cursoId > 0 ? $cursoId : null,
            $turmaId > 0 ? $turmaId : null
        );

        if (empty($dados['inscricao']) || empty($dados['selected_modulo'])) {
            Session::flash('errors', array('Módulo não encontrado ou acesso negado.'));
            $redirect = '/aluno/cursos?inscricao_id=' . (int) $request->query('inscricao_id', 0);
            if ($cursoId > 0) {
                $redirect .= '&curso_id=' . $cursoId;
            }
            if ($turmaId > 0) {
                $redirect .= '&turma_id=' . $turmaId;
            }
            return $this->redirect($redirect);
        }

        return $this->view('area-curso/modulo', array_merge(
            array(
                'title' => 'Módulo do curso',
                'success' => Session::pullFlash('success'),
                'errors' => Session::pullFlash('errors', array()),
            ),
            $dados
        ));
    }

    public function concluirAula(Request $request)
    {
        $resultado = $this->progressoService->concluirAula(
            (int) $request->input('inscricao_id', 0),
            (int) $request->input('aula_id', 0),
            Session::get('usuario_id'),
            Session::get('usuario_id'),
            $request->ip(),
            $request->userAgent()
        );

        if (empty($resultado['ok'])) {
            Session::flash('errors', array(isset($resultado['message']) ? $resultado['message'] : 'Não foi possível concluir a aula.'));
        } else {
            Session::flash('success', 'Aula marcada como concluída.');
        }

        return $this->redirect('/aluno/cursos/modulo?inscricao_id=' . (int) $request->input('inscricao_id', 0) . '&modulo_id=' . (int) $request->input('modulo_id', 0) . '&curso_id=' . (int) $request->input('curso_id', 0) . '&turma_id=' . (int) $request->input('turma_id', 0));
    }

    public function concluirModulo(Request $request)
    {
        Session::flash('errors', array('O módulo é concluído automaticamente ao finalizar as aulas publicadas.'));

        return $this->redirect('/aluno/cursos/modulo?inscricao_id=' . (int) $request->input('inscricao_id', 0) . '&modulo_id=' . (int) $request->input('modulo_id', 0) . '&curso_id=' . (int) $request->input('curso_id', 0) . '&turma_id=' . (int) $request->input('turma_id', 0));
    }

    public function material(Request $request)
    {
        $materialId = (int) $request->query('material_id', 0);
        $material = $this->areaCursoService->materialAutorizado(Session::get('usuario_id'), $materialId, 'aluno');

        if (!$material) {
            return new Response(View::render('errors/404', array('title' => 'Material não encontrado')), 404);
        }

        $acesso = $this->areaCursoService->prepararAcessoMaterial($material);
        if (!$acesso) {
            return new Response(View::render('errors/404', array('title' => 'Material indisponível')), 404);
        }

        if ($acesso['tipo'] === 'url') {
            return $this->redirect($acesso['url']);
        }

        if (!is_file($acesso['absolute_path'])) {
            return new Response(View::render('errors/404', array('title' => 'Arquivo não encontrado')), 404);
        }

        $content = file_get_contents($acesso['absolute_path']);
        return new Response($content, 200, array(
            'Content-Type' => $acesso['content_type'],
            'Content-Disposition' => 'inline; filename="' . $acesso['filename'] . '"',
        ));
    }

    public function enviarAtividade(Request $request)
    {
        $resultado = $this->atividadeService->enviarEntrega(
            $request->all(),
            isset($_FILES['arquivo']) ? $_FILES['arquivo'] : null,
            Session::get('usuario_id'),
            $request->ip(),
            $request->userAgent()
        );

        if (empty($resultado['ok'])) {
            Session::flash('errors', array(isset($resultado['message']) ? $resultado['message'] : 'Não foi possível enviar a atividade.'));
        } else {
            Session::flash('success', 'Atividade enviada com sucesso.');
        }

        return $this->redirect('/aluno/cursos?inscricao_id=' . (int) $request->input('inscricao_id', 0) . '&curso_id=' . (int) $request->input('curso_id', 0) . '&turma_id=' . (int) $request->input('turma_id', 0) . '&modulo_id=' . (int) $request->input('modulo_id', 0) . '&aula_id=' . (int) $request->input('aula_id', 0) . '&atividade_id=' . (int) $request->input('atividade_id', 0));
    }

    public function entregaArquivo(Request $request)
    {
        $entregaId = (int) $request->query('entrega_id', 0);
        $entrega = $this->atividadeService->entregaAutorizada(Session::get('usuario_id'), $entregaId, 'aluno');

        if (!$entrega) {
            Logger::info('atividade.entrega.bloqueio_acesso', array(
                'contexto' => 'aluno',
                'entrega_id' => $entregaId,
                'usuario_id' => Session::get('usuario_id'),
            ));
            return new Response(View::render('errors/404', array('title' => 'Entrega nao encontrada')), 404);
        }

        $acesso = $this->atividadeService->prepararAcessoEntrega($entrega);
        if (!$acesso) {
            return new Response(View::render('errors/404', array('title' => 'Arquivo indisponivel')), 404);
        }

        if (!is_file($acesso['absolute_path'])) {
            return new Response(View::render('errors/404', array('title' => 'Arquivo nao encontrado')), 404);
        }

        Logger::info('atividade.entrega.download', array(
            'contexto' => 'aluno',
            'entrega_id' => $entregaId,
            'usuario_id' => Session::get('usuario_id'),
        ));

        $content = file_get_contents($acesso['absolute_path']);
        return new Response($content, 200, array(
            'Content-Type' => $acesso['content_type'],
            'Content-Disposition' => 'inline; filename="' . $acesso['filename'] . '"',
        ));
    }

    public function conteudoItem(Request $request)
    {
        $itemId = (int) $request->query('id', 0);
        $cursoId = (int) $request->query('curso_id', 0);
        $turmaId = (int) $request->query('turma_id', 0);
        $inscricaoId = (int) $request->query('inscricao_id', 0);

        if ($itemId <= 0) {
            return new Response(View::render('errors/404', array('title' => 'Item nao encontrado')), 404);
        }

        $contexto = $this->areaCursoService->carregarAluno(
            Session::get('usuario_id'),
            $inscricaoId,
            0,
            0,
            $cursoId > 0 ? $cursoId : null,
            $turmaId > 0 ? $turmaId : null,
            null
        );
        if (empty($contexto['inscricao'])) {
            return new Response(View::render('errors/403', array('title' => 'Acesso negado')), 403);
        }

        $inscricao = $contexto['inscricao'];
        $detalhe = $this->conteudoService->buscarItemPublicadoParaAluno(
            $itemId,
            (int) Session::get('usuario_id'),
            (int) $inscricao['id'],
            (int) $inscricao['curso_evento_id'],
            !empty($inscricao['turma_id']) ? (int) $inscricao['turma_id'] : null
        );

        if (empty($detalhe['ok'])) {
            return new Response(View::render('errors/404', array('title' => 'Item nao encontrado')), 404);
        }

        $item = $detalhe['item'];
        $acao = 'visualizou_item';
        if ((string) $item['tipo'] === 'texto') {
            $acao = 'abriu_texto';
        } elseif ((string) $item['tipo'] === 'video') {
            $acao = 'abriu_video';
        } elseif ((string) $item['tipo'] === 'avaliacao_textual') {
            $acao = 'visualizou_avaliacao_textual';
        }

        $this->conteudoService->registrarAcessoItem(array(
            'curso_evento_id' => (int) $inscricao['curso_evento_id'],
            'turma_id' => !empty($inscricao['turma_id']) ? (int) $inscricao['turma_id'] : null,
            'inscricao_id' => (int) $inscricao['id'],
            'aluno_id' => (int) Session::get('usuario_id'),
            'modulo_id' => (int) $detalhe['modulo']['id'],
            'item_id' => (int) $item['id'],
            'obrigatorio' => !empty($item['obrigatorio']) ? 1 : 0,
            'acao' => $acao,
            'status' => (string) $item['tipo'] === 'etiqueta' ? 'concluido' : 'em_andamento',
            'percentual' => (string) $item['tipo'] === 'etiqueta' ? 100 : 10,
            'concluido_em' => (string) $item['tipo'] === 'etiqueta' ? date('Y-m-d H:i:s') : null,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ));

        $resumo = $this->conteudoService->obterResumoProgressoAluno(
            (int) $inscricao['curso_evento_id'],
            (int) Session::get('usuario_id'),
            (int) $inscricao['id'],
            !empty($inscricao['turma_id']) ? (int) $inscricao['turma_id'] : null
        );

        $entregasAvaliacao = array();
        $avaliacaoPodeEnviar = null;
        if ((string) ($item['tipo'] ?? '') === 'avaliacao_textual') {
            $entregasAvaliacao = $this->conteudoAvaliacaoTextualService->listarEntregasAluno((int) ($detalhe['detalhe']['id'] ?? 0), (int) Session::get('usuario_id'), (int) $inscricao['id']);
            $ultimaEntrega = !empty($entregasAvaliacao) ? $entregasAvaliacao[0] : null;
            $avaliacaoPodeEnviar = $this->conteudoAvaliacaoTextualService->podeReenviar((int) ($detalhe['detalhe']['id'] ?? 0), (int) Session::get('usuario_id'), (int) $inscricao['id']);
            if (!empty($ultimaEntrega['status']) && in_array((string) $ultimaEntrega['status'], array('corrigida', 'aprovada', 'reprovada'), true)) {
                $this->conteudoService->registrarLogAluno(array(
                    'curso_evento_id' => (int) $inscricao['curso_evento_id'],
                    'turma_id' => !empty($inscricao['turma_id']) ? (int) $inscricao['turma_id'] : null,
                    'inscricao_id' => (int) $inscricao['id'],
                    'aluno_id' => (int) Session::get('usuario_id'),
                    'modulo_id' => (int) $detalhe['modulo']['id'],
                    'item_id' => (int) $item['id'],
                    'acao' => 'visualizou_feedback',
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ));
            }
        }

        return $this->view('area-curso/conteudo_item', array(
            'title' => 'Conteudo do curso',
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
            'inscricao' => $inscricao,
            'curso' => isset($contexto['curso']) ? $contexto['curso'] : null,
            'turma' => isset($contexto['turma']) ? $contexto['turma'] : null,
            'conteudo_item' => $item,
            'conteudo_modulo' => $detalhe['modulo'],
            'conteudo_detalhe' => $detalhe['detalhe'],
            'conteudo_progresso' => $detalhe['progresso'],
            'conteudo_avaliacao_entregas' => $entregasAvaliacao,
            'conteudo_avaliacao_pode_enviar' => $avaliacaoPodeEnviar,
            'conteudo_resumo' => !empty($resumo['ok']) ? $resumo : null,
        ));
    }

    public function enviarConteudoAvaliacao(Request $request)
    {
        $itemId = (int) $request->input('item_id', 0);
        $cursoId = (int) $request->input('curso_id', 0);
        $turmaId = (int) $request->input('turma_id', 0);
        $inscricaoId = (int) $request->input('inscricao_id', 0);

        $contexto = $this->areaCursoService->carregarAluno(
            Session::get('usuario_id'),
            $inscricaoId,
            0,
            0,
            $cursoId > 0 ? $cursoId : null,
            $turmaId > 0 ? $turmaId : null,
            null
        );
        if (empty($contexto['inscricao'])) {
            Session::flash('errors', array('Acesso negado para envio da avaliação textual.'));
            return $this->redirect('/aluno/cursos');
        }

        $inscricao = $contexto['inscricao'];
        $detalhe = $this->conteudoService->buscarItemPublicadoParaAluno(
            $itemId,
            (int) Session::get('usuario_id'),
            (int) $inscricao['id'],
            (int) $inscricao['curso_evento_id'],
            !empty($inscricao['turma_id']) ? (int) $inscricao['turma_id'] : null
        );
        if (empty($detalhe['ok']) || (string) ($detalhe['item']['tipo'] ?? '') !== 'avaliacao_textual') {
            Session::flash('errors', array('Avaliação textual não encontrada.'));
            return $this->redirect('/aluno/cursos');
        }

        $resultado = $this->conteudoAvaliacaoTextualService->enviarResposta(array(
            'item_id' => (int) $detalhe['item']['id'],
            'avaliacao_id' => (int) ($detalhe['detalhe']['id'] ?? 0),
            'curso_evento_id' => (int) $inscricao['curso_evento_id'],
            'turma_id' => !empty($inscricao['turma_id']) ? (int) $inscricao['turma_id'] : null,
            'inscricao_id' => (int) $inscricao['id'],
            'aluno_id' => (int) Session::get('usuario_id'),
            'resposta' => (string) $request->input('resposta', ''),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ));

        if (empty($resultado['ok'])) {
            Session::flash('errors', array($resultado['message'] ?? 'Não foi possível enviar a resposta.'));
        } else {
            Session::flash('success', (string) ($resultado['status'] ?? '') === 'reenviada' ? 'Resposta reenviada com sucesso.' : 'Resposta enviada com sucesso.');
        }

        return $this->redirect('/aluno/cursos/conteudo/item?id=' . (int) $detalhe['item']['id'] . '&inscricao_id=' . (int) $inscricao['id'] . '&curso_id=' . (int) $inscricao['curso_evento_id'] . (!empty($inscricao['turma_id']) ? '&turma_id=' . (int) $inscricao['turma_id'] : ''));
    }

    public function concluirConteudoItem(Request $request)
    {
        $itemId = (int) $request->input('item_id', 0);
        $cursoId = (int) $request->input('curso_id', 0);
        $turmaId = (int) $request->input('turma_id', 0);
        $inscricaoId = (int) $request->input('inscricao_id', 0);

        $contexto = $this->areaCursoService->carregarAluno(
            Session::get('usuario_id'),
            $inscricaoId,
            0,
            0,
            $cursoId > 0 ? $cursoId : null,
            $turmaId > 0 ? $turmaId : null,
            null
        );

        if (empty($contexto['inscricao'])) {
            Session::flash('errors', array('Acesso negado para concluir item.'));
            return $this->redirect('/aluno/cursos');
        }

        $inscricao = $contexto['inscricao'];
        $resultado = $this->conteudoService->concluirItemAluno(array(
            'curso_evento_id' => (int) $inscricao['curso_evento_id'],
            'turma_id' => !empty($inscricao['turma_id']) ? (int) $inscricao['turma_id'] : null,
            'inscricao_id' => (int) $inscricao['id'],
            'aluno_id' => (int) Session::get('usuario_id'),
            'item_id' => $itemId,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ));

        if (empty($resultado['ok'])) {
            Session::flash('errors', array(isset($resultado['message']) ? $resultado['message'] : 'Nao foi possivel concluir o item.'));
        } else {
            Session::flash('success', 'Item marcado como concluido.');
        }

        return $this->redirect('/aluno/cursos/conteudo/item?id=' . $itemId . '&inscricao_id=' . (int) $inscricao['id'] . '&curso_id=' . (int) $inscricao['curso_evento_id'] . (!empty($inscricao['turma_id']) ? '&turma_id=' . (int) $inscricao['turma_id'] : ''));
    }

    public function downloadConteudoArquivo(Request $request)
    {
        $itemId = (int) $request->query('id', 0);
        $cursoId = (int) $request->query('curso_id', 0);
        $turmaId = (int) $request->query('turma_id', 0);
        $inscricaoId = (int) $request->query('inscricao_id', 0);

        $contexto = $this->areaCursoService->carregarAluno(
            Session::get('usuario_id'),
            $inscricaoId,
            0,
            0,
            $cursoId > 0 ? $cursoId : null,
            $turmaId > 0 ? $turmaId : null,
            null
        );
        if (empty($contexto['inscricao'])) {
            return new Response(View::render('errors/403', array('title' => 'Acesso negado')), 403);
        }

        $inscricao = $contexto['inscricao'];
        $detalhe = $this->conteudoService->buscarItemPublicadoParaAluno(
            $itemId,
            (int) Session::get('usuario_id'),
            (int) $inscricao['id'],
            (int) $inscricao['curso_evento_id'],
            !empty($inscricao['turma_id']) ? (int) $inscricao['turma_id'] : null
        );
        if (empty($detalhe['ok']) || (string) $detalhe['item']['tipo'] !== 'arquivo') {
            return new Response(View::render('errors/404', array('title' => 'Arquivo nao encontrado')), 404);
        }

        $arquivo = $this->conteudoService->obterArquivoDoItem((int) $detalhe['item']['id'], (int) $inscricao['curso_evento_id']);
        if (empty($arquivo['ok'])) {
            return new Response(View::render('errors/404', array('title' => 'Arquivo nao encontrado')), 404);
        }

        $storage = new FileStorageService();
        $absolutePath = $storage->privatePath((string) $arquivo['arquivo']['caminho']);
        if (!is_file($absolutePath)) {
            return new Response(View::render('errors/404', array('title' => 'Arquivo nao encontrado')), 404);
        }

        $this->conteudoService->registrarDownloadArquivoAluno(array(
            'curso_evento_id' => (int) $inscricao['curso_evento_id'],
            'turma_id' => !empty($inscricao['turma_id']) ? (int) $inscricao['turma_id'] : null,
            'inscricao_id' => (int) $inscricao['id'],
            'aluno_id' => (int) Session::get('usuario_id'),
            'modulo_id' => (int) $detalhe['modulo']['id'],
            'item_id' => (int) $detalhe['item']['id'],
            'obrigatorio' => !empty($detalhe['item']['obrigatorio']) ? 1 : 0,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ));

        $content = file_get_contents($absolutePath);
        $fileName = !empty($arquivo['arquivo']['nome_original']) ? (string) $arquivo['arquivo']['nome_original'] : basename($absolutePath);
        return new Response($content, 200, array(
            'Content-Type' => !empty($arquivo['arquivo']['mime_type']) ? (string) $arquivo['arquivo']['mime_type'] : 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ));
    }

    public function acessarConteudoLink(Request $request)
    {
        $itemId = (int) $request->query('id', 0);
        $cursoId = (int) $request->query('curso_id', 0);
        $turmaId = (int) $request->query('turma_id', 0);
        $inscricaoId = (int) $request->query('inscricao_id', 0);

        $contexto = $this->areaCursoService->carregarAluno(
            Session::get('usuario_id'),
            $inscricaoId,
            0,
            0,
            $cursoId > 0 ? $cursoId : null,
            $turmaId > 0 ? $turmaId : null,
            null
        );
        if (empty($contexto['inscricao'])) {
            return new Response(View::render('errors/403', array('title' => 'Acesso negado')), 403);
        }
        $inscricao = $contexto['inscricao'];

        $detalhe = $this->conteudoService->buscarItemPublicadoParaAluno(
            $itemId,
            (int) Session::get('usuario_id'),
            (int) $inscricao['id'],
            (int) $inscricao['curso_evento_id'],
            !empty($inscricao['turma_id']) ? (int) $inscricao['turma_id'] : null
        );
        if (empty($detalhe['ok']) || (string) $detalhe['item']['tipo'] !== 'link') {
            return new Response(View::render('errors/404', array('title' => 'Link nao encontrado')), 404);
        }

        $url = isset($detalhe['detalhe']['url']) ? trim((string) $detalhe['detalhe']['url']) : '';
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            return new Response(View::render('errors/404', array('title' => 'Link invalido')), 404);
        }

        $this->conteudoService->registrarAcessoLinkAluno(array(
            'curso_evento_id' => (int) $inscricao['curso_evento_id'],
            'turma_id' => !empty($inscricao['turma_id']) ? (int) $inscricao['turma_id'] : null,
            'inscricao_id' => (int) $inscricao['id'],
            'aluno_id' => (int) Session::get('usuario_id'),
            'modulo_id' => (int) $detalhe['modulo']['id'],
            'item_id' => (int) $detalhe['item']['id'],
            'obrigatorio' => !empty($detalhe['item']['obrigatorio']) ? 1 : 0,
            'dados_json' => array('url' => $url),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ));

        return $this->redirect($url);
    }
}

