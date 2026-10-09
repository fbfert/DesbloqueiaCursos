<?php
namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\CertificadoRetencaoService;
use App\Services\CertificadoService;

class CertificadosController extends Controller
{
    private $certificadoService;

    public function __construct()
    {
        $this->certificadoService = new CertificadoService();
    }

    private function configCertificados()
    {
        return $this->certificadoService->configuracaoCertificados();
    }

    public function index(Request $request)
    {
        return $this->view('admin/certificados/index', array(
            'title' => 'Certificados',
            'certificados' => $this->certificadoService->listarCertificados(),
            'aptos' => $this->certificadoService->listarAptos(),
            'retidosCount' => count((new CertificadoRetencaoService())->listarAguardando()),
            'configCertificados' => $this->configCertificados(),
            'errors' => Session::pullFlash('errors', array()),
            'success' => Session::pullFlash('success'),
        ));
    }

    /** Certificados retidos aguardando o aluno informar o CPF (login-google). */
    public function retidos(Request $request)
    {
        return $this->view('admin/certificados/retidos', array(
            'title' => 'Certificados aguardando CPF',
            'retidos' => (new CertificadoRetencaoService())->listarAguardando(),
            'errors' => Session::pullFlash('errors', array()),
            'success' => Session::pullFlash('success'),
        ));
    }

    public function cancelarRetencao(Request $request)
    {
        $resultado = (new CertificadoRetencaoService())->cancelar(
            (int) $request->input('retencao_id', 0),
            trim((string) $request->input('justificativa', '')),
            Session::get('usuario_id'),
            $request->ip(),
            $request->userAgent()
        );

        if (empty($resultado['ok'])) {
            Session::flash('errors', array($resultado['message']));
        } else {
            Session::flash('success', 'Retenção cancelada. O certificado não será emitido automaticamente.');
        }

        return $this->redirect('/admin/certificados/retidos');
    }

    public function emissaoManual(Request $request)
    {
        $filtros = $this->extrairFiltrosEmissaoManual($request);
        $cursoId = isset($filtros['curso_evento_id']) ? (int) $filtros['curso_evento_id'] : 0;

        return $this->view('admin/certificados/emissao_manual', array(
            'title' => 'Emissão Manual de Certificados',
            'cursos' => $this->certificadoService->listarCursosEmissaoManual(),
            'turmas' => $this->certificadoService->listarTurmasEmissaoManual($cursoId > 0 ? $cursoId : null),
            'templates' => $this->certificadoService->listarTemplates(),
            'candidatos' => $this->certificadoService->buscarCandidatosEmissaoManual(array_merge($filtros, array('limit' => 100))),
            'filtros' => $filtros,
            'configCertificados' => $this->configCertificados(),
            'resultado_emissao_manual' => Session::pullFlash('resultado_emissao_manual'),
            'errors' => Session::pullFlash('errors', array()),
            'success' => Session::pullFlash('success'),
        ));
    }

    public function emissaoManualEmitir(Request $request)
    {
        $filtros = $this->extrairFiltrosEmissaoManual($request);
        $inscricaoIds = $request->input('inscricao_ids', array());
        if (!is_array($inscricaoIds)) {
            $inscricaoIds = array($inscricaoIds);
        }
        $inscricaoIds = array_values(array_filter(array_map('intval', $inscricaoIds)));
        $templateId = (int) $request->input('template_id', 0);
        $permitirExcecao = !empty($request->input('permitir_excecao'));
        $justificativaExcecao = trim((string) $request->input('justificativa_excecao', ''));
        $actorUserId = (int) Session::get('usuario_id', 0);
        $ipAddress = $request->ip();
        $userAgent = $request->userAgent();

        Logger::info('certificados.emissao_manual.post', array(
            'inscricao_ids' => $inscricaoIds,
            'template_id' => $templateId,
            'permitir_excecao' => $permitirExcecao ? 1 : 0,
            'justificativa_len' => function_exists('mb_strlen') ? mb_strlen($justificativaExcecao) : strlen($justificativaExcecao),
            'actor_user_id' => $actorUserId,
        ));

        if (empty($inscricaoIds)) {
            Session::flash('errors', array('Selecione ao menos uma inscrição para emitir.'));
            return $this->redirect($this->urlEmissaoManual($filtros));
        }

        try {
            $result = $this->certificadoService->emitirLoteManual(
                $inscricaoIds,
                array(
                    'template_id' => $templateId,
                    'emissao_manual' => true,
                    'permitir_pendencias' => true,
                    'permitir_excecao' => $permitirExcecao,
                    'justificativa_excecao' => $justificativaExcecao,
                ),
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            Session::flash('resultado_emissao_manual', $result);

            if (empty($result['ok'])) {
                Session::flash('errors', array(isset($result['message']) ? $result['message'] : 'A emissão de certificados está desativada nas configurações globais.'));
                return $this->redirect($this->urlEmissaoManual($filtros));
            }

            $resumo = isset($result['resumo']) && is_array($result['resumo']) ? $result['resumo'] : array();
            $avisosCpf = $this->avisosCpf($resumo);
            if ((int) ($resumo['emitidos'] ?? 0) > 0 || (int) ($resumo['emitidos_excecao'] ?? 0) > 0) {
                Session::flash('success', trim('Processamento da emissão manual concluído. ' . $avisosCpf));
            } elseif ((int) ($resumo['retidos'] ?? 0) > 0 && (int) ($resumo['erros'] ?? 0) === 0 && (int) ($resumo['bloqueados'] ?? 0) === 0) {
                Session::flash('success', $avisosCpf);
            } else {
                $mensagens = array();
                if (!empty($result['resultados']) && is_array($result['resultados'])) {
                    foreach ($result['resultados'] as $item) {
                        if (!empty($item['message'])) {
                            $mensagens[] = (string) $item['message'];
                        }
                    }
                }

                if (!empty($mensagens)) {
                    Session::flash('errors', $mensagens);
                } else {
                    Session::flash('errors', array('Nenhum certificado foi emitido.'));
                }
            }
        } catch (\Throwable $e) {
            Logger::error('certificados.emissao_manual.falhou', array(
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'actor_user_id' => $actorUserId,
                'template_id' => $templateId,
                'permitir_excecao' => $permitirExcecao ? 1 : 0,
            ));

            Session::flash('errors', array('Falha na emissão manual: ' . $e->getMessage()));
        }

        return $this->redirect($this->urlEmissaoManual($filtros));
    }

    public function emissaoRapidaIndividual(Request $request)
    {
        $busca = trim((string) $request->query('q', ''));
        $usuarioIdBruto = $request->query('usuario_id', null);
        $usuarioId = 0;
        $alunosEncontrados = array();
        $errosBoundary = array();

        // Aceita apenas inteiro positivo em usuario_id; qualquer outro valor é ignorado
        // com aviso amigável, sem gerar notice/erro de conversão.
        if ($usuarioIdBruto !== null && $usuarioIdBruto !== '') {
            if (is_numeric($usuarioIdBruto) && (int) $usuarioIdBruto > 0 && (string) (int) $usuarioIdBruto === (string) $usuarioIdBruto) {
                $usuarioId = (int) $usuarioIdBruto;
            } else {
                $errosBoundary[] = 'Identificador de aluno inválido.';
            }
        }

        $alunoSelecionado = null;
        $inscricoesAluno = array();

        try {
            if ($usuarioId <= 0 && $busca !== '') {
                $alunosEncontrados = $this->certificadoService->buscarAlunosEmissaoRapida($busca, 20);
                if (count($alunosEncontrados) === 1) {
                    $usuarioId = (int) $alunosEncontrados[0]['id'];
                    $alunosEncontrados = array();
                }
            }

            if ($usuarioId > 0) {
                $alunoSelecionado = $this->certificadoService->buscarAlunoEmissaoRapidaPorId($usuarioId);
                if ($alunoSelecionado) {
                    $inscricoesAluno = $this->certificadoService->carregarInscricoesAlunoEmissaoRapida($usuarioId);
                } else {
                    $errosBoundary[] = 'Aluno não encontrado.';
                }
            }
        } catch (\Throwable $exception) {
            // Não mascara a causa raiz: registra a exceção real com contexto suficiente
            // para manutenção e exibe apenas mensagem genérica segura ao administrador.
            Logger::error('admin.certificados.emissao_rapida_individual.falhou', array(
                'usuario_id' => $usuarioId,
                'busca_preenchida' => $busca !== '' ? 1 : 0,
                'rota' => '/admin/certificados/emissao-rapida-individual',
                'actor_user_id' => (int) Session::get('usuario_id', 0),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ));

            $alunoSelecionado = null;
            $inscricoesAluno = array();
            $errosBoundary[] = 'Não foi possível carregar as inscrições do aluno no momento. Tente novamente ou contate o suporte técnico.';
        }

        if (!empty($errosBoundary)) {
            Session::flash('errors', $errosBoundary);
        }

        return $this->view('admin/certificados/emissao_rapida_individual', array(
            'title' => 'Emissão Rápida Individual',
            'busca' => $busca,
            'alunosEncontrados' => $alunosEncontrados,
            'alunoSelecionado' => $alunoSelecionado,
            'inscricoesAluno' => $inscricoesAluno,
            'resultado_emissao_rapida' => Session::pullFlash('resultado_emissao_rapida'),
            'configCertificados' => $this->configCertificados(),
            'errors' => Session::pullFlash('errors', array()),
            'success' => Session::pullFlash('success'),
        ));
    }

    public function emissaoRapidaIndividualEmitir(Request $request)
    {
        $usuarioId = (int) $request->input('usuario_id', 0);
        $inscricaoIds = $request->input('inscricao_ids', array());
        if (!is_array($inscricaoIds)) {
            $inscricaoIds = array($inscricaoIds);
        }
        $inscricaoIds = array_values(array_filter(array_map('intval', $inscricaoIds)));
        $actorUserId = (int) Session::get('usuario_id', 0);

        $resultado = $this->certificadoService->emitirRapidaIndividual(
            $usuarioId,
            $inscricaoIds,
            array(),
            $actorUserId,
            $request->ip(),
            $request->userAgent()
        );

        Session::flash('resultado_emissao_rapida', $resultado);

        if (empty($resultado['ok'])) {
            Session::flash('errors', array(isset($resultado['message']) ? $resultado['message'] : 'Não foi possível concluir a emissão rápida.'));
        } else {
            $resumo = isset($resultado['resumo']) && is_array($resultado['resumo']) ? $resultado['resumo'] : array();
            $emitidos = (int) ($resumo['emitidos'] ?? 0);
            $falhas = (int) ($resumo['falhas'] ?? 0);
            $emailsFalhos = (int) ($resumo['emails_falhos'] ?? 0);

            $retidos = (int) ($resumo['retidos'] ?? 0);
            if ($emitidos > 0 || $retidos > 0) {
                Session::flash('success', trim(($emitidos > 0 ? 'Certificados emitidos com sucesso. ' : '') . $this->avisosCpf($resumo)));
            }

            if ($falhas > 0 || $emailsFalhos > 0) {
                Session::flash('errors', array('Alguns certificados não puderam ser emitidos ou enviados.'));
            }
        }

        return $this->redirect('/admin/certificados/emissao-rapida-individual?usuario_id=' . $usuarioId);
    }

    public function show(Request $request)
    {
        $certificadoId = (int) $request->query('certificado_id', 0);
        try {
            $detalhe = $this->certificadoService->detalhar($certificadoId);
        } catch (\Throwable $exception) {
            Logger::error('admin.certificados.show.falhou', array(
                'certificado_id' => $certificadoId,
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ));

            Session::flash('errors', array('Não foi possível abrir o certificado.'));
            return $this->redirect('/admin/certificados');
        }

        if (empty($detalhe['certificado'])) {
            Session::flash('errors', array('Certificado não encontrado.'));
            return $this->redirect('/admin/certificados');
        }

        return $this->view('admin/certificados/show', array_merge(
            array(
                'title' => 'Certificado #' . (int) $detalhe['certificado']['id'],
                'configCertificados' => $this->configCertificados(),
                'errors' => Session::pullFlash('errors', array()),
                'success' => Session::pullFlash('success'),
            ),
            $detalhe
        ));
    }

    public function emitir(Request $request)
    {
        if ($request->method() === 'GET') {
            $inscricaoId = (int) $request->query('inscricao_id', 0);
            return $this->view('admin/certificados/emitir', array(
                'title' => 'Emitir certificado',
                'aptos' => $this->certificadoService->listarAptos(),
                'templates' => $this->certificadoService->listarTemplates(),
                'selectedInscricaoId' => $inscricaoId,
                'configCertificados' => $this->configCertificados(),
                'errors' => Session::pullFlash('errors', array()),
                'success' => Session::pullFlash('success'),
            ));
        }

        $result = $this->certificadoService->emitir(
            (int) $request->input('inscricao_id', 0),
            array(
                'template_id' => (int) $request->input('template_id', 0),
                'manter_codigo' => !empty($request->input('manter_codigo')),
            ),
            Session::get('usuario_id'),
            $request->ip(),
            $request->userAgent()
        );

        if (!empty($result['retido'])) {
            Session::flash('success', $result['message']);
            return $this->redirect('/admin/certificados/retidos');
        }

        if (empty($result['ok'])) {
            Session::flash('errors', array(isset($result['message']) ? $result['message'] : 'Não foi possível emitir o certificado.'));
            return $this->redirect('/admin/certificados/emitir?inscricao_id=' . (int) $request->input('inscricao_id', 0));
        }

        Session::flash('success', !empty($result['aviso_sem_cpf']) ? 'Certificado emitido com sucesso. Certificado emitido sem CPF do participante.' : 'Certificado emitido com sucesso.');
        return $this->redirect('/admin/certificados/show?certificado_id=' . (int) $result['certificado_id']);
    }

    public function reemitir(Request $request)
    {
        $result = $this->certificadoService->reemitir(
            (int) $request->input('certificado_id', 0),
            !empty($request->input('manter_codigo')),
            Session::get('usuario_id'),
            $request->ip(),
            $request->userAgent()
        );

        if (!empty($result['retido'])) {
            Session::flash('success', $result['message']);
            return $this->redirect('/admin/certificados/retidos');
        }

        if (empty($result['ok'])) {
            Session::flash('errors', array(isset($result['message']) ? $result['message'] : 'Não foi possível reemitir o certificado.'));
            return $this->redirect('/admin/certificados/show?certificado_id=' . (int) $request->input('certificado_id', 0));
        }

        Session::flash('success', 'Certificado reemitido com sucesso.');
        return $this->redirect('/admin/certificados/show?certificado_id=' . (int) $result['certificado_id']);
    }

    public function cancelar(Request $request)
    {
        $result = $this->certificadoService->cancelar(
            (int) $request->input('certificado_id', 0),
            trim((string) $request->input('observacao', '')),
            Session::get('usuario_id'),
            $request->ip(),
            $request->userAgent()
        );

        if (empty($result['ok'])) {
            Session::flash('errors', array(isset($result['message']) ? $result['message'] : 'Não foi possível cancelar o certificado.'));
            return $this->redirect('/admin/certificados/show?certificado_id=' . (int) $request->input('certificado_id', 0));
        }

        Session::flash('success', 'Certificado cancelado.');
        return $this->redirect('/admin/certificados/show?certificado_id=' . (int) $request->input('certificado_id', 0));
    }

    public function revogar(Request $request)
    {
        $result = $this->certificadoService->revogar(
            (int) $request->input('certificado_id', 0),
            trim((string) $request->input('observacao', '')),
            Session::get('usuario_id'),
            $request->ip(),
            $request->userAgent()
        );

        if (empty($result['ok'])) {
            Session::flash('errors', array(isset($result['message']) ? $result['message'] : 'Não foi possível revogar o certificado.'));
            return $this->redirect('/admin/certificados/show?certificado_id=' . (int) $request->input('certificado_id', 0));
        }

        Session::flash('success', 'Certificado revogado.');
        return $this->redirect('/admin/certificados/show?certificado_id=' . (int) $request->input('certificado_id', 0));
    }

    public function pdf(Request $request)
    {
        $codigo = strtoupper(trim((string) $request->query('codigo', '')));
        $config = $this->configCertificados();
        if (empty($config['certificados_permitir_download'])) {
            return new Response('O download do certificado não está disponível no momento.', 403, array('Content-Type' => 'text/plain; charset=UTF-8'));
        }
        try {
            $pdf = $this->certificadoService->pdfBytesByCodigo($codigo);
        } catch (\Throwable $exception) {
            Logger::error('admin.certificados.pdf.falhou', array(
                'codigo' => $codigo,
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ));

            return new Response('Não foi possível gerar o certificado.', 500, array('Content-Type' => 'text/plain; charset=UTF-8'));
        }

        if ($pdf === null) {
            return new Response(View::render('errors/404', array('title' => 'Certificado não encontrado')), 404);
        }

        return new Response($pdf, 200, array(
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="certificado-' . $codigo . '.pdf"',
        ));
    }

    /** Frases do resumo sobre CPF (login-google): retidos aguardando CPF e emitidos sem CPF. */
    private function avisosCpf(array $resumo)
    {
        $frases = array();
        $retidos = (int) ($resumo['retidos'] ?? 0);
        if ($retidos > 0) {
            $frases[] = $retidos === 1
                ? '1 certificado retido aguardando CPF: será emitido automaticamente quando o aluno informar o CPF.'
                : $retidos . ' certificados retidos aguardando CPF: serão emitidos automaticamente quando os alunos informarem o CPF.';
        }
        $semCpf = (int) ($resumo['sem_cpf'] ?? 0);
        if ($semCpf > 0) {
            $frases[] = $semCpf === 1 ? 'Certificado emitido sem CPF do participante.' : $semCpf . ' certificados emitidos sem CPF do participante.';
        }

        return implode(' ', $frases);
    }

    private function extrairFiltrosEmissaoManual(Request $request)
    {
        return array(
            'curso_evento_id' => (int) $request->input('curso_evento_id', $request->query('curso_evento_id', 0)),
            'turma_id' => (int) $request->input('turma_id', $request->query('turma_id', 0)),
            'busca' => trim((string) $request->input('busca', $request->query('busca', ''))),
            'status_inscricao' => trim((string) $request->input('status_inscricao', $request->query('status_inscricao', ''))),
            'certificado' => trim((string) $request->input('certificado', $request->query('certificado', 'todos'))),
            'aptidao' => trim((string) $request->input('aptidao', $request->query('aptidao', 'todos'))),
        );
    }

    private function urlEmissaoManual(array $filtros)
    {
        $query = array();
        foreach ($filtros as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if (($key === 'curso_evento_id' || $key === 'turma_id') && (int) $value === 0) {
                continue;
            }

            $query[$key] = $value;
        }

        $url = '/admin/certificados/emissao-manual';
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        return $url;
    }
}



