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

    private function parametrosConteudoAluno(Request $request)
    {
        return array(
            'inscricao_id' => (int) $request->route('inscricao_id', $request->query('inscricao_id', 0)),
            'curso_id' => (int) $request->route('curso_id', $request->query('curso_id', 0)),
            'turma_id' => (int) $request->route('turma_id', $request->query('turma_id', 0)),
            'modulo_id' => (int) $request->route('modulo_id', $request->query('modulo_id', 0)),
            'conteudo_id' => (int) $request->route('conteudo_id', $request->query('conteudo_id', $request->query('id', 0))),
        );
    }

    private function urlCursoAluno($inscricaoId, $cursoId, $turmaId = 0)
    {
        return '/aluno/curso/' . (int) $inscricaoId . '/' . (int) $cursoId . '/' . (int) $turmaId;
    }

    private function urlModuloAluno($inscricaoId, $cursoId, $turmaId = 0, $moduloId = 0)
    {
        return $this->urlCursoAluno($inscricaoId, $cursoId, $turmaId) . '/modulo/' . (int) $moduloId;
    }

    private function urlConteudoAluno($inscricaoId, $cursoId, $turmaId = 0, $moduloId = 0, $conteudoId = 0)
    {
        return $this->urlModuloAluno($inscricaoId, $cursoId, $turmaId, $moduloId) . '/conteudo/' . (int) $conteudoId;
    }

    private function carregarContextoAlunoConteudo(array $parametros)
    {
        $inscricaoId = (int) ($parametros['inscricao_id'] ?? 0);
        $cursoId = (int) ($parametros['curso_id'] ?? 0);
        $turmaId = (int) ($parametros['turma_id'] ?? 0);

        if ($inscricaoId <= 0) {
            return array('ok' => false, 'message' => 'Inscrição inválida.');
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
            return array('ok' => false, 'message' => 'Nenhuma inscrição válida foi encontrada para este usuário.');
        }

        $inscricao = $contexto['inscricao'];
        $cursoInscricaoId = (int) $inscricao['curso_evento_id'];
        $turmaInscricaoId = !empty($inscricao['turma_id']) ? (int) $inscricao['turma_id'] : 0;

        if ($cursoId > 0 && $cursoId !== $cursoInscricaoId) {
            return array('ok' => false, 'message' => 'O curso informado não corresponde à inscrição selecionada.', 'redirect' => $this->urlCursoAluno($inscricaoId, $cursoInscricaoId, $turmaInscricaoId));
        }

        if ($turmaId > 0 && ($turmaInscricaoId <= 0 || $turmaId !== $turmaInscricaoId)) {
            return array('ok' => false, 'message' => 'A turma informada não corresponde à inscrição selecionada.', 'redirect' => $this->urlCursoAluno($inscricaoId, $cursoInscricaoId, $turmaInscricaoId));
        }

        return array(
            'ok' => true,
            'contexto' => $contexto,
            'inscricao' => $inscricao,
            'curso_id' => $cursoInscricaoId,
            'turma_id' => $turmaInscricaoId,
        );
    }

    private function localizarModuloConteudo(array $modulos, $moduloId)
    {
        $moduloId = (int) $moduloId;
        foreach ($modulos as $modulo) {
            if ((int) ($modulo['id'] ?? 0) === $moduloId) {
                return $modulo;
            }
        }

        return null;
    }

    private function montarSequenciaConteudosAluno(array $modulos)
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

    private function montarNavegacaoConteudoAluno(array $modulos, $inscricaoId, $cursoId, $turmaId, $moduloId, $conteudoId)
    {
        $sequencia = $this->montarSequenciaConteudosAluno($modulos);
        $indiceAtual = null;

        foreach ($sequencia as $indice => $registro) {
            if ((int) ($registro['item']['id'] ?? 0) === (int) $conteudoId) {
                $indiceAtual = $indice;
                break;
            }
        }

        if ($indiceAtual === null) {
            return array(
                'anterior_url' => null,
                'anterior_label' => null,
                'proximo_url' => null,
                'proximo_label' => null,
            );
        }

        $anterior = isset($sequencia[$indiceAtual - 1]) ? $sequencia[$indiceAtual - 1] : null;
        $proximo = isset($sequencia[$indiceAtual + 1]) ? $sequencia[$indiceAtual + 1] : null;

        return array(
            'anterior_url' => $anterior !== null ? $this->urlConteudoAluno($inscricaoId, $cursoId, $turmaId, (int) ($anterior['modulo']['id'] ?? $moduloId), (int) ($anterior['item']['id'] ?? 0)) : null,
            'anterior_label' => $anterior !== null ? (string) ($anterior['item']['titulo'] ?? 'Conteúdo anterior') : null,
            'proximo_url' => $proximo !== null ? $this->urlConteudoAluno($inscricaoId, $cursoId, $turmaId, (int) ($proximo['modulo']['id'] ?? $moduloId), (int) ($proximo['item']['id'] ?? 0)) : null,
            'proximo_label' => $proximo !== null ? (string) ($proximo['item']['titulo'] ?? 'Próximo conteúdo') : null,
        );
    }

    public function index(Request $request)
    {
        $parametros = $this->parametrosConteudoAluno($request);
        $contexto = $this->carregarContextoAlunoConteudo($parametros);

        if (empty($contexto['ok'])) {
            Session::flash('errors', array(isset($contexto['message']) ? $contexto['message'] : 'Nenhuma inscrição válida foi encontrada para este usuário.'));
            return $this->redirect(isset($contexto['redirect']) ? $contexto['redirect'] : '/aluno/meus-cursos');
        }

        $inscricao = $contexto['inscricao'];
        $cursoId = (int) $contexto['curso_id'];
        $turmaId = (int) $contexto['turma_id'];
        $moduloId = (int) $parametros['modulo_id'];
        $conteudoId = (int) $parametros['conteudo_id'];

        if ($conteudoId > 0) {
            return $this->redirect($this->urlConteudoAluno((int) $inscricao['id'], $cursoId, $turmaId, $moduloId, $conteudoId));
        }

        if ($moduloId > 0) {
            return $this->redirect($this->urlModuloAluno((int) $inscricao['id'], $cursoId, $turmaId, $moduloId));
        }

        if (strpos($request->path(), '/aluno/curso/') !== 0) {
            return $this->redirect($this->urlCursoAluno((int) $inscricao['id'], $cursoId, $turmaId));
        }

        $conteudoAluno = $this->conteudoService->listarConteudoPublicadoAluno(
            $cursoId,
            (int) Session::get('usuario_id'),
            (int) $inscricao['id'],
            $turmaId > 0 ? $turmaId : null
        );

        if (empty($conteudoAluno['ok'])) {
            Session::flash('errors', array(isset($conteudoAluno['message']) ? $conteudoAluno['message'] : 'Não foi possível carregar o conteúdo do curso.'));
            return $this->redirect('/aluno/meus-cursos');
        }

        return $this->view('aluno/curso/index', array(
            'title' => 'Módulos do curso',
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
            'inscricao' => $inscricao,
            'curso' => isset($contexto['contexto']['curso']) ? $contexto['contexto']['curso'] : null,
            'turma' => isset($contexto['contexto']['turma']) ? $contexto['contexto']['turma'] : null,
            'conteudo_curso' => $conteudoAluno,
            'conteudo_resumo' => !empty($conteudoAluno['resumo']) ? $conteudoAluno['resumo'] : array(),
            'conteudo_modulos' => !empty($conteudoAluno['modulos']) ? $conteudoAluno['modulos'] : array(),
            'conteudo_geral_url' => $this->urlCursoAluno((int) $inscricao['id'], $cursoId, $turmaId),
            'hide_pre_footer_menu' => true,
        ));
    }

    public function modulo(Request $request)
    {
        $parametros = $this->parametrosConteudoAluno($request);
        $contexto = $this->carregarContextoAlunoConteudo($parametros);

        if (empty($contexto['ok'])) {
            Session::flash('errors', array(isset($contexto['message']) ? $contexto['message'] : 'Nenhuma inscrição válida foi encontrada para este usuário.'));
            return $this->redirect(isset($contexto['redirect']) ? $contexto['redirect'] : '/aluno/meus-cursos');
        }

        $inscricao = $contexto['inscricao'];
        $cursoId = (int) $contexto['curso_id'];
        $turmaId = (int) $contexto['turma_id'];
        $moduloId = (int) $parametros['modulo_id'];

        if ($moduloId <= 0) {
            Session::flash('errors', array('Módulo não encontrado ou acesso negado.'));
            return $this->redirect($this->urlCursoAluno((int) $inscricao['id'], $cursoId, $turmaId));
        }

        $conteudoAluno = $this->conteudoService->listarConteudoPublicadoAluno(
            $cursoId,
            (int) Session::get('usuario_id'),
            (int) $inscricao['id'],
            $turmaId > 0 ? $turmaId : null
        );
        if (empty($conteudoAluno['ok'])) {
            Session::flash('errors', array(isset($conteudoAluno['message']) ? $conteudoAluno['message'] : 'Não foi possível carregar o conteúdo do curso.'));
            return $this->redirect($this->urlCursoAluno((int) $inscricao['id'], $cursoId, $turmaId));
        }

        $modulo = $this->localizarModuloConteudo(!empty($conteudoAluno['modulos']) ? $conteudoAluno['modulos'] : array(), $moduloId);
        if (empty($modulo)) {
            Session::flash('errors', array('Módulo não encontrado ou acesso negado.'));
            return $this->redirect($this->urlCursoAluno((int) $inscricao['id'], $cursoId, $turmaId));
        }

        if (strpos($request->path(), '/aluno/curso/') !== 0) {
            return $this->redirect($this->urlModuloAluno((int) $inscricao['id'], $cursoId, $turmaId, $moduloId));
        }

        return $this->view('aluno/curso/modulo', array(
            'title' => 'Conteúdos do módulo',
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
            'inscricao' => $inscricao,
            'curso' => isset($contexto['contexto']['curso']) ? $contexto['contexto']['curso'] : null,
            'turma' => isset($contexto['contexto']['turma']) ? $contexto['contexto']['turma'] : null,
            'conteudo_curso' => $conteudoAluno,
            'conteudo_modulo' => $modulo,
            'conteudo_resumo' => !empty($conteudoAluno['resumo']) ? $conteudoAluno['resumo'] : array(),
            'conteudo_geral_url' => $this->urlCursoAluno((int) $inscricao['id'], $cursoId, $turmaId),
            'conteudo_modulo_url' => $this->urlModuloAluno((int) $inscricao['id'], $cursoId, $turmaId, $moduloId),
            'hide_pre_footer_menu' => true,
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
        $parametros = $this->parametrosConteudoAluno($request);
        $contexto = $this->carregarContextoAlunoConteudo($parametros);

        if (empty($contexto['ok'])) {
            Session::flash('errors', array(isset($contexto['message']) ? $contexto['message'] : 'Acesso negado.'));
            return $this->redirect(isset($contexto['redirect']) ? $contexto['redirect'] : '/aluno/meus-cursos');
        }

        $inscricao = $contexto['inscricao'];
        $cursoId = (int) $contexto['curso_id'];
        $turmaId = (int) $contexto['turma_id'];
        $moduloId = (int) $parametros['modulo_id'];
        $itemId = (int) $parametros['conteudo_id'];

        if ($itemId <= 0) {
            Session::flash('errors', array('Conteúdo inválido.'));
            return $this->redirect($this->urlCursoAluno((int) $inscricao['id'], $cursoId, $turmaId));
        }

        $conteudoAluno = $this->conteudoService->listarConteudoPublicadoAluno(
            $cursoId,
            (int) Session::get('usuario_id'),
            (int) $inscricao['id'],
            $turmaId > 0 ? $turmaId : null
        );
        if (empty($conteudoAluno['ok'])) {
            Session::flash('errors', array(isset($conteudoAluno['message']) ? $conteudoAluno['message'] : 'Não foi possível carregar o conteúdo do curso.'));
            return $this->redirect($this->urlCursoAluno((int) $inscricao['id'], $cursoId, $turmaId));
        }

        $modulo = $this->localizarModuloConteudo(!empty($conteudoAluno['modulos']) ? $conteudoAluno['modulos'] : array(), $moduloId);
        if (empty($modulo)) {
            Session::flash('errors', array('Módulo não encontrado ou acesso negado.'));
            return $this->redirect($this->urlCursoAluno((int) $inscricao['id'], $cursoId, $turmaId));
        }

        $detalhe = $this->conteudoService->buscarItemPublicadoParaAluno(
            $itemId,
            (int) Session::get('usuario_id'),
            (int) $inscricao['id'],
            $cursoId,
            $turmaId > 0 ? $turmaId : null
        );

        if (empty($detalhe['ok'])) {
            Session::flash('errors', array(isset($detalhe['message']) ? $detalhe['message'] : 'Conteúdo não encontrado.'));
            return $this->redirect($this->urlModuloAluno((int) $inscricao['id'], $cursoId, $turmaId, $moduloId));
        }

        $moduloIdDetalhe = (int) ($detalhe['modulo']['id'] ?? 0);
        if ($moduloId <= 0) {
            $moduloId = $moduloIdDetalhe;
        }

        if ($moduloId <= 0) {
            Session::flash('errors', array('Módulo não encontrado ou acesso negado.'));
            return $this->redirect($this->urlCursoAluno((int) $inscricao['id'], $cursoId, $turmaId));
        }

        if ($moduloIdDetalhe !== $moduloId) {
            Session::flash('errors', array('Este conteúdo não pertence ao módulo informado.'));
            return $this->redirect($this->urlModuloAluno((int) $inscricao['id'], $cursoId, $turmaId, $moduloIdDetalhe));
        }

        if (strpos($request->path(), '/aluno/curso/') !== 0) {
            return $this->redirect($this->urlConteudoAluno((int) $inscricao['id'], $cursoId, $turmaId, $moduloId, $itemId));
        }

        $item = $detalhe['item'];
        $progressoItem = isset($detalhe['progresso']) && is_array($detalhe['progresso']) ? $detalhe['progresso'] : null;
        $itemJaConcluido = !empty($progressoItem) && in_array((string) ($progressoItem['status'] ?? ''), array('concluido', 'aprovada', 'corrigida'), true);
        $resumo = $this->conteudoService->obterResumoProgressoAluno(
            $cursoId,
            (int) Session::get('usuario_id'),
            (int) $inscricao['id'],
            $turmaId > 0 ? $turmaId : null
        );
        $navegacao = $this->montarNavegacaoConteudoAluno(
            !empty($conteudoAluno['modulos']) ? $conteudoAluno['modulos'] : array(),
            (int) $inscricao['id'],
            $cursoId,
            $turmaId,
            $moduloId,
            $itemId
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
            'aluno_id' => (int) Session::get('usuario_id'),
            'modulo_id' => $moduloId,
            'item_id' => (int) $item['id'],
            'obrigatorio' => !empty($item['obrigatorio']) ? 1 : 0,
            'acao' => $acao,
            'status' => (string) $item['tipo'] === 'etiqueta' ? 'concluido' : 'em_andamento',
            'percentual' => (string) $item['tipo'] === 'etiqueta' ? 100 : 10,
            'concluido_em' => (string) $item['tipo'] === 'etiqueta' ? date('Y-m-d H:i:s') : null,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ));

        if (in_array((string) ($item['tipo'] ?? ''), array('texto', 'html'), true) && !$itemJaConcluido) {
            try {
                $resultadoConclusao = $this->conteudoService->concluirItemAluno(array(
                    'curso_evento_id' => $cursoId,
                    'turma_id' => $turmaId > 0 ? $turmaId : null,
                    'inscricao_id' => (int) $inscricao['id'],
                    'aluno_id' => (int) Session::get('usuario_id'),
                    'item_id' => (int) $item['id'],
                    'modulo_id' => $moduloId,
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ));

                if (empty($resultadoConclusao['ok'])) {
                    Logger::warning('conteudo.texto.auto_conclusao_nao_aplicada', array(
                        'inscricao_id' => (int) $inscricao['id'],
                        'item_id' => (int) $item['id'],
                        'message' => isset($resultadoConclusao['message']) ? $resultadoConclusao['message'] : null,
                    ));
                } else {
                    $detalhe = $this->conteudoService->buscarItemPublicadoParaAluno(
                        $itemId,
                        (int) Session::get('usuario_id'),
                        (int) $inscricao['id'],
                        $cursoId,
                        $turmaId > 0 ? $turmaId : null
                    );
                    if (!empty($detalhe['ok'])) {
                        $item = $detalhe['item'];
                        $progressoItem = isset($detalhe['progresso']) && is_array($detalhe['progresso']) ? $detalhe['progresso'] : null;
                        $itemJaConcluido = !empty($progressoItem) && in_array((string) ($progressoItem['status'] ?? ''), array('concluido', 'aprovada', 'corrigida'), true);
                    }
                    $resumo = $this->conteudoService->obterResumoProgressoAluno(
                        $cursoId,
                        (int) Session::get('usuario_id'),
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
            $entregasAvaliacao = $this->conteudoAvaliacaoTextualService->listarEntregasAluno((int) ($detalhe['detalhe']['id'] ?? 0), (int) Session::get('usuario_id'), (int) $inscricao['id']);
            $ultimaEntrega = !empty($entregasAvaliacao) ? $entregasAvaliacao[0] : null;
            $avaliacaoPodeEnviar = $this->conteudoAvaliacaoTextualService->podeReenviar((int) ($detalhe['detalhe']['id'] ?? 0), (int) Session::get('usuario_id'), (int) $inscricao['id']);
            if (!empty($ultimaEntrega['status']) && in_array((string) $ultimaEntrega['status'], array('corrigida', 'aprovada', 'reprovada'), true)) {
                $this->conteudoService->registrarLogAluno(array(
                    'curso_evento_id' => $cursoId,
                    'turma_id' => $turmaId > 0 ? $turmaId : null,
                    'inscricao_id' => (int) $inscricao['id'],
                    'aluno_id' => (int) Session::get('usuario_id'),
                    'modulo_id' => $moduloId,
                    'item_id' => (int) $item['id'],
                    'acao' => 'visualizou_feedback',
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ));
            }
        }

        return $this->view('aluno/curso/conteudo', array(
            'title' => 'Conteúdo do módulo',
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
            'inscricao' => $inscricao,
            'curso' => isset($contexto['contexto']['curso']) ? $contexto['contexto']['curso'] : null,
            'turma' => isset($contexto['contexto']['turma']) ? $contexto['contexto']['turma'] : null,
            'conteudo_item' => $item,
            'conteudo_modulo' => $detalhe['modulo'],
            'conteudo_detalhe' => $detalhe['detalhe'],
            'conteudo_progresso' => $detalhe['progresso'],
            'conteudo_avaliacao_entregas' => $entregasAvaliacao,
            'conteudo_avaliacao_pode_enviar' => $avaliacaoPodeEnviar,
            'conteudo_resumo' => !empty($resumo['ok']) ? $resumo : null,
            'conteudo_voltar_modulo_url' => $this->urlModuloAluno((int) $inscricao['id'], $cursoId, $turmaId, $moduloId),
            'conteudo_anterior_url' => $navegacao['anterior_url'],
            'conteudo_anterior_label' => $navegacao['anterior_label'],
            'conteudo_proximo_url' => $navegacao['proximo_url'],
            'conteudo_proximo_label' => $navegacao['proximo_label'],
            'hide_public_chrome' => true,
            'hide_pre_footer_menu' => true,
        ));
    }

    public function enviarConteudoAvaliacao(Request $request)
    {
        $itemId = (int) $request->input('item_id', 0);
        $cursoId = (int) $request->input('curso_id', 0);
        $turmaId = (int) $request->input('turma_id', 0);
        $moduloId = (int) $request->input('modulo_id', 0);
        $inscricaoId = (int) $request->input('inscricao_id', 0);

        $contexto = $this->carregarContextoAlunoConteudo(array(
            'inscricao_id' => $inscricaoId,
            'curso_id' => $cursoId,
            'turma_id' => $turmaId,
        ));
        if (empty($contexto['ok'])) {
            Session::flash('errors', array('Acesso negado para envio da avaliação textual.'));
            return $this->redirect(isset($contexto['redirect']) ? $contexto['redirect'] : '/aluno/meus-cursos');
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
            if ($moduloId > 0) {
                return $this->redirect($this->urlModuloAluno((int) $inscricao['id'], (int) $inscricao['curso_evento_id'], !empty($inscricao['turma_id']) ? (int) $inscricao['turma_id'] : 0, $moduloId));
            }

            return $this->redirect($this->urlCursoAluno((int) $inscricao['id'], (int) $inscricao['curso_evento_id'], !empty($inscricao['turma_id']) ? (int) $inscricao['turma_id'] : 0));
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

        return $this->redirect($this->urlConteudoAluno(
            (int) $inscricao['id'],
            (int) $inscricao['curso_evento_id'],
            !empty($inscricao['turma_id']) ? (int) $inscricao['turma_id'] : 0,
            (int) ($detalhe['modulo']['id'] ?? $moduloId),
            (int) $detalhe['item']['id']
        ));
    }

    public function concluirConteudoItem(Request $request)
    {
        $itemId = (int) $request->input('item_id', 0);
        $cursoId = (int) $request->input('curso_id', 0);
        $turmaId = (int) $request->input('turma_id', 0);
        $moduloId = (int) $request->input('modulo_id', 0);
        $inscricaoId = (int) $request->input('inscricao_id', 0);
        $acao = trim((string) $request->input('acao', 'marcar'));

        $contexto = $this->carregarContextoAlunoConteudo(array(
            'inscricao_id' => $inscricaoId,
            'curso_id' => $cursoId,
            'turma_id' => $turmaId,
        ));

        if (empty($contexto['ok'])) {
            Session::flash('errors', array('Acesso negado para concluir item.'));
            return $this->redirect(isset($contexto['redirect']) ? $contexto['redirect'] : '/aluno/meus-cursos');
        }

        $inscricao = $contexto['inscricao'];
        $cursoId = (int) $inscricao['curso_evento_id'];
        $turmaId = !empty($inscricao['turma_id']) ? (int) $inscricao['turma_id'] : 0;
        $payload = array(
            'curso_evento_id' => $cursoId,
            'turma_id' => $turmaId > 0 ? $turmaId : null,
            'inscricao_id' => (int) $inscricao['id'],
            'aluno_id' => (int) Session::get('usuario_id'),
            'item_id' => $itemId,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        );

        if ($acao === 'desmarcar') {
            $resultado = $this->conteudoService->desmarcarItemComoConcluido($payload);
        } else {
            $resultado = $this->conteudoService->concluirItemAluno($payload);
        }

        if (empty($resultado['ok'])) {
            Session::flash('errors', array(isset($resultado['message']) ? $resultado['message'] : 'Não foi possível alterar a conclusão do item.'));
        } else {
            Session::flash('success', $acao === 'desmarcar' ? 'Conclusão desmarcada.' : 'Item marcado como concluído.');
        }

        $moduloRedirecionar = $moduloId;
        if ($moduloRedirecionar <= 0) {
            $detalheRedirecionar = $this->conteudoService->buscarItemPublicadoParaAluno(
                $itemId,
                (int) Session::get('usuario_id'),
                (int) $inscricao['id'],
                $cursoId,
                $turmaId > 0 ? $turmaId : null
            );
            if (!empty($detalheRedirecionar['ok'])) {
                $moduloRedirecionar = (int) ($detalheRedirecionar['modulo']['id'] ?? 0);
            }
        }

        if ($moduloRedirecionar <= 0) {
            return $this->redirect($this->urlCursoAluno((int) $inscricao['id'], $cursoId, $turmaId));
        }

        return $this->redirect($this->urlConteudoAluno(
            (int) $inscricao['id'],
            $cursoId,
            $turmaId,
            $moduloRedirecionar,
            $itemId
        ));
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

