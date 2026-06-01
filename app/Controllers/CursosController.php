<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\CursoService;
use App\Services\InscricaoService;

class CursosController extends Controller
{
    private $cursoService;
    private $inscricaoService;

    public function __construct()
    {
        $this->cursoService = new CursoService();
        $this->inscricaoService = new InscricaoService();
    }

    public function index(Request $request)
    {
        return $this->view('cursos/index', array_merge(
            array(
                'title' => 'Cursos e eventos',
                'success' => Session::pullFlash('success'),
                'errors' => Session::pullFlash('errors', array()),
            ),
            $this->cursoService->listPublic()
        ));
    }

    public function show(Request $request)
    {
        $cursoId = (int) $request->query('curso_id', 0);
        $turmaId = (int) $request->query('turma_id', 0);
        $cupomPromocional = trim((string) $request->query('cupom', ''));
        if ($cupomPromocional !== '') {
            Session::put('cupom_promocional_codigo', $cupomPromocional);
        }
        $contexto = $this->cursoService->showPublic($cursoId, $turmaId ?: null);

        if (empty($contexto['curso'])) {
            return new Response(View::render('errors/404', array(
                'title' => 'Curso nao encontrado',
            )), 404);
        }

        $usuarioId = (int) Session::get('usuario_id', 0);
        if ($usuarioId > 0) {
            $contexto = $this->anexarSituacoesInscricaoPublica($contexto, $usuarioId, $cursoId);
        }

        return $this->view('cursos/show', array_merge(
            array(
                'title' => $contexto['curso']['nome'],
                'loggedIn' => Session::get('usuario_id') !== null,
                'usuarioNome' => Session::get('usuario_nome'),
                'success' => Session::pullFlash('success'),
                'errors' => Session::pullFlash('errors', array()),
            ),
            $contexto
        ));
    }

    private function anexarSituacoesInscricaoPublica(array $contexto, $usuarioId, $cursoId)
    {
        if (empty($contexto['curso'])) {
            return $contexto;
        }

        if (!empty($contexto['curso']['turmas_abertas']) && is_array($contexto['curso']['turmas_abertas'])) {
            $turmas = array();
            foreach ($contexto['curso']['turmas_abertas'] as $turma) {
                if (!is_array($turma) || empty($turma['id'])) {
                    $turmas[] = $turma;
                    continue;
                }

                $situacao = $this->inscricaoService->situacaoAlunoNoCurso($usuarioId, $cursoId, (int) $turma['id']);
                $turma['situacao_inscricao'] = $situacao;
                $turma['acao_inscricao'] = $this->montarAcaoInscricaoPublica($cursoId, (int) $turma['id'], $situacao);
                $turmas[] = $turma;
            }

            $contexto['curso']['turmas_abertas'] = $turmas;
        }

        if (!empty($contexto['curso']['turma_selecionada']['id'])) {
            $situacaoSelecionada = $this->inscricaoService->situacaoAlunoNoCurso($usuarioId, $cursoId, (int) $contexto['curso']['turma_selecionada']['id']);
            $contexto['curso']['turma_selecionada']['situacao_inscricao'] = $situacaoSelecionada;
            $contexto['curso']['turma_selecionada']['acao_inscricao'] = $this->montarAcaoInscricaoPublica($cursoId, (int) $contexto['curso']['turma_selecionada']['id'], $situacaoSelecionada);
        }

        return $contexto;
    }

    private function montarAcaoInscricaoPublica($cursoId, $turmaId, array $situacao)
    {
        $statusFluxo = isset($situacao['status_fluxo']) ? (string) $situacao['status_fluxo'] : 'nao_inscrito';
        $baseUrl = '/inscricao?curso_id=' . (int) $cursoId . '&turma_id=' . (int) $turmaId;

        if ($statusFluxo === 'matriculado') {
            return array(
                'label' => 'Acessar curso',
                'href' => '/minha-pagina',
                'classe' => 'button-link',
            );
        }

        if ($statusFluxo === 'pendente_pagamento') {
            return array(
                'label' => 'Continuar pagamento',
                'href' => !empty($situacao['checkout_url']) ? (string) $situacao['checkout_url'] : '/checkout/resumo?pedido_id=' . (int) ($situacao['pedido_id'] ?? 0),
                'classe' => 'button-link',
            );
        }

        if (in_array($statusFluxo, array('cancelado', 'expirado', 'falhou', 'reprovado'), true)) {
            return array(
                'label' => 'Inscrever-se novamente',
                'href' => $baseUrl,
                'classe' => 'button-link',
            );
        }

        return array(
            'label' => 'Inscrever nesta turma',
            'href' => $baseUrl,
            'classe' => 'button-link',
        );
    }
}
