<?php

namespace App\Controllers\Admin\Promocionais;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Logger;
use App\Core\Session;
use App\Services\PresenteCampanhaService;

class PresentesController extends Controller
{
    private $presenteService;

    public function __construct()
    {
        $this->presenteService = new PresenteCampanhaService();
    }

    public function index(Request $request)
    {
        $dados = $this->presenteService->listarBackoffice(Session::get('usuario_id'), $request->queryAll());

        return $this->view('admin/promocionais/presentes/index', array_merge(
            array(
                'title' => 'Presentes',
                'success' => Session::pullFlash('success'),
                'errors' => Session::pullFlash('errors', array()),
            ),
            $dados
        ));
    }

    public function create(Request $request)
    {
        $filters = $request->queryAll();
        $page = (int) $request->query('page', 1);
        $perPage = (int) $request->query('per_page', 20);
        $selecionadosIds = $this->carregarSelecionadosIds();
        $selecionados = $this->presenteService->carregarUsuariosSelecionados($selecionadosIds);
        $selecionadosIds = array_map(function (array $usuario) {
            return (int) $usuario['id'];
        }, $selecionados);
        $this->salvarSelecionadosIds($selecionadosIds);

        return $this->view('admin/promocionais/presentes/create', array_merge(
            array(
                'title' => 'Novo presente',
                'success' => Session::pullFlash('success'),
                'errors' => Session::pullFlash('errors', array()),
                'old' => Session::pullFlash('old', array()),
                'selecionados' => $selecionados,
                'selecionados_ids' => $selecionadosIds,
            ),
            $this->presenteService->formData($filters, $page, $perPage)
        ));
    }

    public function configurar(Request $request)
    {
        $selecionadosIds = $this->carregarSelecionadosIds();
        if (empty($selecionadosIds)) {
            Session::flash('errors', array('Selecione ao menos um usuário antes de configurar a campanha.'));
            return $this->redirect('/admin/promocionais/presentes/criar');
        }

        $cursoId = (int) $request->query('curso_evento_id', 0);
        $turmaId = (int) $request->query('turma_id', 0);
        $contextoConfirmado = (int) $request->query('confirmar_contexto', 0) === 1;
        $dados = $this->presenteService->configData($selecionadosIds, $cursoId, $turmaId, $contextoConfirmado, Session::pullFlash('old', array()));

        if ($contextoConfirmado && (empty($dados['curso_selecionado']) || empty($dados['turma_selecionada']))) {
            Session::flash('errors', array('Selecione um curso ativo e uma turma válida antes de confirmar.'));
            return $this->redirect('/admin/promocionais/presentes/configurar?curso_evento_id=' . $cursoId . '&turma_id=' . $turmaId);
        }

        return $this->view('admin/promocionais/presentes/config', array_merge(
            array(
                'title' => 'Configuração da campanha',
                'success' => Session::pullFlash('success'),
                'errors' => Session::pullFlash('errors', array()),
            ),
            $dados
        ));
    }

    public function selecionar(Request $request)
    {
        $selecionadosNovos = $request->input('usuarios_selecionados', array());
        $selecionadosNovos = is_array($selecionadosNovos) ? array_map('intval', $selecionadosNovos) : array();
        $selecionadosAtuais = $this->carregarSelecionadosIds();
        $selecionados = array_values(array_unique(array_filter(array_merge($selecionadosAtuais, $selecionadosNovos))));

        if (empty($selecionadosNovos)) {
            Session::flash('errors', array('Selecione ao menos um usuário na lista para salvar a seleção.'));
            return $this->redirect('/admin/promocionais/presentes/criar?' . http_build_query($request->queryAll()));
        }

        $this->salvarSelecionadosIds($selecionados);

        Session::flash('success', 'Usuários adicionados à seleção.');
        return $this->redirect('/admin/promocionais/presentes/criar?' . http_build_query($request->queryAll()));
    }

    public function limparSelecao(Request $request)
    {
        Session::forget($this->selecionadosSessionKey());
        Session::flash('success', 'Seleção limpa com sucesso.');

        return $this->redirect('/admin/promocionais/presentes/criar?' . http_build_query($request->queryAll()));
    }

    public function removerSelecionado(Request $request)
    {
        $usuarioId = (int) $request->input('usuario_id', 0);
        if ($usuarioId <= 0) {
            Session::flash('errors', array('Usuário inválido.'));
            return $this->redirect('/admin/promocionais/presentes/criar?' . http_build_query($request->queryAll()));
        }

        $selecionados = array_values(array_filter($this->carregarSelecionadosIds(), function ($id) use ($usuarioId) {
            return (int) $id !== $usuarioId;
        }));

        $this->salvarSelecionadosIds($selecionados);
        Session::flash('success', 'Usuário removido da seleção.');

        return $this->redirect('/admin/promocionais/presentes/criar?' . http_build_query($request->queryAll()));
    }

    public function preview(Request $request)
    {
        $result = $this->presenteService->prepararPreview($request->all(), Session::get('usuario_id'));
        if (empty($result['ok'])) {
            Session::flash('errors', isset($result['errors']) ? $result['errors'] : array('Não foi possível preparar a pré-visualização.'));
            Session::flash('old', $request->all());
            return $this->redirect('/admin/promocionais/presentes/configurar?' . http_build_query(array(
                'curso_evento_id' => (int) $request->input('curso_evento_id', 0),
                'turma_id' => (int) $request->input('turma_id', 0),
                'confirmar_contexto' => 1,
            )));
        }

        Session::put('presentes_preview_' . $result['token'], $result['payload']);

        return $this->view('admin/promocionais/presentes/preview', array(
            'title' => 'Confirmar presente',
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
            'preview' => $result['preview'],
            'payload' => $result['payload'],
            'token' => $result['token'],
        ));
    }

    public function store(Request $request)
    {
        $token = trim((string) $request->input('preview_token', ''));
        $payload = $token !== '' ? Session::get('presentes_preview_' . $token) : null;
        if (!is_array($payload)) {
            Session::flash('errors', array('Pré-visualização expirada. Refaça a confirmação.'));
            return $this->redirect('/admin/promocionais/presentes/configurar');
        }

        $result = $this->presenteService->executar(
            $payload,
            Session::get('usuario_id'),
            $request->ip(),
            $request->userAgent()
        );

        Session::forget('presentes_preview_' . $token);

        if (empty($result['ok'])) {
            Session::flash('errors', isset($result['errors']) ? $result['errors'] : array('Não foi possível conceder os presentes.'));
            Session::flash('old', $request->all());
            return $this->redirect('/admin/promocionais/presentes/configurar?' . http_build_query(array(
                'curso_evento_id' => (int) $request->input('curso_evento_id', 0),
                'turma_id' => (int) $request->input('turma_id', 0),
                'confirmar_contexto' => 1,
            )));
        }

        Session::flash('success', 'Presente concedido com sucesso.');
        return $this->redirect('/admin/promocionais/presentes/show?campanha_id=' . (int) $result['campanha_id']);
    }

    public function show(Request $request)
    {
        $campanhaId = (int) $request->query('campanha_id', 0);
        $dados = $this->presenteService->detalharCampanha($campanhaId, Session::get('usuario_id'));

        if (empty($dados['campanha'])) {
            Session::flash('errors', array('Campanha não encontrada.'));
            return $this->redirect('/admin/promocionais/presentes');
        }

        return $this->view('admin/promocionais/presentes/show', array_merge(
            array(
                'title' => 'Detalhe do presente',
                'success' => Session::pullFlash('success'),
                'errors' => Session::pullFlash('errors', array()),
            ),
            $dados
        ));
    }

    public function cancelar(Request $request)
    {
        return $this->handleCancelamento($request, false);
    }

    public function cancelarLote(Request $request)
    {
        return $this->handleCancelamento($request, true);
    }

    private function handleCancelamento(Request $request, $lote = false)
    {
        $campanhaId = (int) $request->input('campanha_id', 0);
        $cancelamentoTipo = trim((string) $request->input('cancelamento_tipo', ''));
        $justificativa = trim((string) $request->input('justificativa', ''));
        $ids = $lote
            ? (array) $request->input('beneficiario_ids', array())
            : array((int) $request->input('beneficiario_id', 0));

        $result = $this->presenteService->cancelarBeneficiarios(
            $campanhaId,
            $ids,
            $cancelamentoTipo,
            $justificativa,
            Session::get('usuario_id'),
            $request->ip(),
            $request->userAgent()
        );

        if (empty($result['ok'])) {
            Session::flash('errors', array(isset($result['message']) ? $result['message'] : 'Não foi possível cancelar os presentes.'));
            return $this->redirect('/admin/promocionais/presentes/show?campanha_id=' . $campanhaId);
        }

        Session::flash('success', 'Presentes atualizados com sucesso.');
        return $this->redirect('/admin/promocionais/presentes/show?campanha_id=' . $campanhaId);
    }

    private function selecionadosSessionKey()
    {
        return 'presentes_selecionados_' . (int) Session::get('usuario_id');
    }

    private function carregarSelecionadosIds()
    {
        $ids = Session::get($this->selecionadosSessionKey(), array());
        if (!is_array($ids)) {
            return array();
        }

        return array_values(array_unique(array_filter(array_map('intval', $ids))));
    }

    private function salvarSelecionadosIds(array $ids)
    {
        Session::put($this->selecionadosSessionKey(), array_values(array_unique(array_filter(array_map('intval', $ids)))));
    }
}
