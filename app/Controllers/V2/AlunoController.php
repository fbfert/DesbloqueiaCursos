<?php

namespace App\Controllers\V2;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\Pedido;
use App\Services\AuthService;
use App\Services\InscricaoService;
use App\Services\PedidoService;
use App\Support\NorminhaHints;

/**
 * Área do Aluno V2 (Fase 2.6) — leitura, com dados reais do usuário
 * autenticado atual, mais uma única ação de escrita (cancelar o próprio
 * pedido, adicionada depois — ver `cancelarPedido()`).
 *
 * Segurança/isolamento:
 * - exige autenticação real reutilizando a MESMA sessão do sistema
 *   (`Session::get('usuario_id')`, igual ao AuthenticateMiddleware); visitante
 *   é redirecionado para `/v2/login?origem=v2_aluno` (lista branca interna);
 * - todos os dados são carregados por `usuario_id` da SESSÃO. Nenhum
 *   `usuario_id`/`inscricao_id`/`pedido_id`/`certificado_id` vindo de query
 *   string é usado para buscar dados;
 * - reutiliza services/models que já filtram pelo usuário
 *   (`InscricaoService::listarAprovadasDoUsuario`, `Pedido::forUsuario`,
 *   `AuthService::accountData`, `PedidoService::registrarStatus`);
 * - `cancelarPedido()` reutiliza a MESMA regra real de
 *   `MeusCursosController::cancelarPedido()` (V1): mesma lista de status
 *   cancelável, mesmo service, mesma checagem de posse do pedido.
 */
class AlunoController extends Controller
{
    /** @var InscricaoService */
    private $inscricaoService;
    /** @var Pedido */
    private $pedidoModel;
    /** @var AuthService */
    private $authService;
    /** @var PedidoService */
    private $pedidoService;

    private $abasValidas = array('cursos', 'pedidos', 'certificados', 'perfil');

    public function __construct()
    {
        $this->inscricaoService = new InscricaoService();
        $this->pedidoModel = new Pedido();
        $this->authService = new AuthService();
        $this->pedidoService = new PedidoService();
    }

    public function index(Request $request)
    {
        // --- Autenticação real (mesma sessão do sistema) ---
        $usuarioId = (int) Session::get('usuario_id', 0);
        if ($usuarioId <= 0) {
            // Origem interna fixa em lista branca; após login volta a /v2/aluno.
            return Response::redirect('/v2/login?origem=v2_aluno');
        }

        $usuarioNome = trim((string) Session::get('usuario_nome', ''));

        // --- Aba (padrão cursos; valor inválido -> cursos) ---
        $aba = (string) $request->query('aba', 'cursos');
        if (!in_array($aba, $this->abasValidas, true)) {
            $aba = 'cursos';
        }

        // --- Dados reais, sempre por usuario_id da SESSÃO ---
        $inscricoes = $this->carregarInscricoes($usuarioId);
        $cursos = $this->normalizarCursos($inscricoes);
        $certificados = $this->normalizarCertificados($inscricoes);
        $pedidos = $this->normalizarPedidos(
            $this->pedidoModel->forUsuario($usuarioId),
            $this->pedidoModel->cursosNomesPorUsuario($usuarioId)
        );
        $perfil = $this->normalizarPerfil($usuarioId, $usuarioNome);

        $abas = array(
            array('chave' => 'cursos', 'label' => 'Meus cursos', 'total' => count($cursos), 'href' => '/v2/aluno/?aba=cursos'),
            array('chave' => 'pedidos', 'label' => 'Pedidos', 'total' => count($pedidos), 'href' => '/v2/aluno/?aba=pedidos'),
            array('chave' => 'certificados', 'label' => 'Certificados', 'total' => count($certificados), 'href' => '/v2/aluno/?aba=certificados'),
            array('chave' => 'perfil', 'label' => 'Perfil', 'total' => null, 'href' => '/v2/aluno/?aba=perfil'),
        );

        $data = array_merge($this->dadosLayout($usuarioId, $usuarioNome), array(
            'title' => 'Minha área — Desbloqueia Cursos',
            'pageTitle' => 'Minha área — Desbloqueia Cursos',
            // Area geral: NAO se afirma que ha aula atual. Sem item, a Norminha
            // nao oferece "tirar duvida desta aula" sobre coisa nenhuma, e o
            // contexto fica em area_aluno.
            'norminhaContexto' => NorminhaHints::montar(NorminhaHints::CONTEXTO_AREA_ALUNO),
            'pageDescription' => 'Sua área do aluno: cursos, pedidos, certificados e perfil.',
            'abaAtiva' => $aba,
            'abas' => $abas,
            'cursos' => $cursos,
            'certificados' => $certificados,
            'pedidos' => $pedidos,
            'perfil' => $perfil,
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
            'coursePalette' => $this->coursePalette(),
        ));

        return new Response(View::render('v2/aluno', $data, false));
    }

    /**
     * POST /v2/aluno/pedidos/cancelar — mesma regra real de
     * MeusCursosController::cancelarPedido() (V1): exige motivo, só permite
     * cancelar pedidos em status ainda não confirmado/finalizado, e a posse
     * do pedido é revalidada dentro de PedidoService::registrarStatus().
     */
    public function cancelarPedido(Request $request)
    {
        $usuarioId = (int) Session::get('usuario_id', 0);
        if ($usuarioId <= 0) {
            return Response::redirect('/v2/login?origem=v2_aluno');
        }

        $pedidoId = (int) $request->input('pedido_id', 0);
        $motivo = trim((string) $request->input('motivo_cancelamento', ''));
        $voltar = '/v2/aluno/?aba=pedidos';

        if ($pedidoId <= 0) {
            Session::flash('errors', array('Pedido inválido para cancelamento.'));
            return Response::redirect($voltar);
        }

        if ($motivo === '') {
            Session::flash('errors', array('Informe o motivo do cancelamento do pedido.'));
            return Response::redirect($voltar);
        }

        $pedido = $this->pedidoModel->findById($pedidoId);
        if (!$pedido) {
            Session::flash('errors', array('Pedido não encontrado.'));
            return Response::redirect($voltar);
        }

        if (!$this->pedidoPodeSerCanceladoPeloAluno((string) $pedido['status'])) {
            Session::flash('errors', array('Este pedido não pode mais ser cancelado pelo aluno.'));
            return Response::redirect($voltar);
        }

        $observacao = 'Cancelamento solicitado pelo aluno. Motivo: ' . $motivo;
        $resultado = $this->pedidoService->registrarStatus(
            $pedidoId,
            'cancelado',
            $observacao,
            $usuarioId,
            $request->ip(),
            $request->userAgent()
        );

        if (empty($resultado['ok'])) {
            Session::flash('errors', array(isset($resultado['message']) ? $resultado['message'] : 'Não foi possível cancelar o pedido.'));
            return Response::redirect($voltar);
        }

        Session::flash('success', 'Pedido cancelado com sucesso.');
        return Response::redirect($voltar);
    }

    private function carregarInscricoes($usuarioId)
    {
        $resultado = $this->inscricaoService->listarAprovadasDoUsuario($usuarioId);
        $inscricoes = isset($resultado['inscricoes']) && is_array($resultado['inscricoes']) ? $resultado['inscricoes'] : array();

        if (empty($inscricoes)) {
            $todas = $this->inscricaoService->listarDoUsuario($usuarioId);
            $inscricoes = isset($todas['inscricoes']) && is_array($todas['inscricoes']) ? $todas['inscricoes'] : array();
        }

        return $inscricoes;
    }

    private function normalizarCursos(array $inscricoes)
    {
        $cursos = array();
        foreach ($inscricoes as $i) {
            if (!is_array($i)) {
                continue;
            }
            $inscricaoId = isset($i['id']) ? (int) $i['id'] : 0;
            $cursoId = isset($i['curso_evento_id']) ? (int) $i['curso_evento_id'] : 0;
            $turmaId = isset($i['turma_id']) ? (int) $i['turma_id'] : 0;
            $progresso = isset($i['percentual_progresso']) ? (float) $i['percentual_progresso'] : 0.0;
            if ($progresso < 0) { $progresso = 0.0; }
            if ($progresso > 100) { $progresso = 100.0; }
            $status = isset($i['status']) ? (string) $i['status'] : '';

            $cursos[] = array(
                'inscricao_id' => $inscricaoId,
                'curso_id' => $cursoId,
                'turma_id' => $turmaId,
                'nome' => isset($i['curso_nome']) ? (string) $i['curso_nome'] : '',
                'thumbnail' => isset($i['curso_thumbnail']) ? trim((string) $i['curso_thumbnail']) : '',
                'turma' => isset($i['turma_nome']) ? (string) $i['turma_nome'] : '',
                'turma_codigo' => isset($i['turma_codigo']) ? (string) $i['turma_codigo'] : '',
                'modalidade' => $this->modalidadeLabel(isset($i['curso_modalidade']) ? (string) $i['curso_modalidade'] : ''),
                'progresso' => (int) round($progresso),
                'status' => $status,
                'status_label' => $this->statusInscricaoLabel($status),
                'status_classe' => $this->statusInscricaoClasse($status),
                'concluida' => $progresso >= 100 || in_array($status, array('concluida', 'concluida_sem_certificado', 'certificado_emitido'), true),
                'tem_certificado' => !empty($i['certificado_codigo']),
                // LMS V2 (somente leitura/navegação) — valida posse da inscrição no
                // servidor e tem fallback seguro. Parâmetros mínimos do LMS atual.
                'lms_href' => ($inscricaoId > 0 && $cursoId > 0)
                    ? '/v2/aula/?inscricao_id=' . $inscricaoId . '&curso_id=' . $cursoId . '&turma_id=' . $turmaId
                    : '/v2/aluno/',
            );
        }

        return $cursos;
    }

    private function normalizarCertificados(array $inscricoes)
    {
        $certs = array();
        foreach ($inscricoes as $i) {
            if (!is_array($i) || empty($i['certificado_codigo'])) {
                continue;
            }
            $codigo = (string) $i['certificado_codigo'];
            $certs[] = array(
                'curso' => isset($i['curso_nome']) ? (string) $i['curso_nome'] : '',
                'codigo' => $codigo,
                'emitido_em' => isset($i['certificado_emitido_em']) ? $this->formatarData($i['certificado_emitido_em']) : '',
                'status' => isset($i['certificado_status']) ? (string) $i['certificado_status'] : '',
                // Online/PDF: rotas públicas/oficiais atuais (inalteradas).
                // Validação: tela V2 real (Fase 2.11), que reutiliza o mesmo
                // serviço público de validação — NUNCA o validador demonstrativo.
                'online_href' => '/certificados/show?codigo=' . rawurlencode($codigo),
                'validar_href' => '/v2/certificados/validar?codigo=' . rawurlencode($codigo),
                'pdf_href' => '/certificados/pdf?codigo=' . rawurlencode($codigo),
            );
        }

        return $certs;
    }

    private function normalizarPedidos(array $pedidos, array $cursosNomesPorPedido = array())
    {
        $itens = array();
        foreach ($pedidos as $p) {
            if (!is_array($p)) {
                continue;
            }

            $pedidoId = isset($p['id']) ? (int) $p['id'] : 0;
            $status = isset($p['status']) ? (string) $p['status'] : '';
            $total = isset($p['total']) ? (float) $p['total'] : 0.0;

            // Nomes reais dos cursos vêm SEMPRE dos itens reais do pedido
            // autorizado (mesma regra de propriedade de forUsuario). Nunca de
            // query string, JS ou nome de participante.
            $cursosNomes = ($pedidoId > 0 && isset($cursosNomesPorPedido[$pedidoId]) && is_array($cursosNomesPorPedido[$pedidoId]))
                ? array_values($cursosNomesPorPedido[$pedidoId])
                : array();
            $qtdCursos = count($cursosNomes);

            if ($qtdCursos === 1) {
                $tituloCurso = (string) $cursosNomes[0];
            } elseif ($qtdCursos > 1) {
                $tituloCurso = 'Cursos deste pedido';
            } else {
                // Fallback honesto: nenhum item de curso disponível para o pedido.
                $tituloCurso = 'Pedido sem curso identificado';
            }

            // Link para o Resumo V2 gerado no BACKEND apenas quando o pedido
            // pertence ao usuário (garantido por forUsuario), possui id válido,
            // tem cobrança e está em estado que o fluxo atual permite retomar o
            // checkout (mesma regra de PedidoService::pedidoPodeSerFinalizado).
            // Caminho interno fixo, codificado com http_build_query; a rota de
            // resumo revalida a propriedade no clique.
            $podeRetomar = $this->pedidoPodeRetomarCheckout($status) && $total > 0.0 && $pedidoId > 0;
            $resumoHref = $podeRetomar
                ? '/v2/checkout/resumo?' . http_build_query(array('pedido_id' => $pedidoId))
                : '';

            $itens[] = array(
                'id' => $pedidoId,
                'codigo' => isset($p['codigo']) ? (string) $p['codigo'] : '',
                'data' => isset($p['created_at']) ? $this->formatarData($p['created_at']) : '',
                'total' => $total,
                'total_formatado' => $this->formatarMoeda($total),
                'status' => $status,
                'status_label' => $this->statusPedidoLabel($status),
                'status_classe' => $this->statusPedidoClasse($status),
                'total_itens' => isset($p['total_itens']) ? (int) $p['total_itens'] : 0,
                'cursos_nomes' => $cursosNomes,
                'titulo_curso' => $tituloCurso,
                'multiplos_cursos' => $qtdCursos > 1,
                'resumo_v2_href' => $resumoHref,
                'pode_cancelar' => $pedidoId > 0 && $this->pedidoPodeSerCanceladoPeloAluno($status),
            );
        }

        return $itens;
    }

    /**
     * Estados em que o fluxo atual permite retomar/continuar o checkout.
     * Espelha PedidoService::pedidoPodeSerFinalizado() (fonte de verdade do
     * backend). Estados como pago, aprovado, cancelado, expirado, em_analise e
     * comprovante_enviado NÃO retomam pagamento e não recebem link de resumo.
     */
    /**
     * Espelha a mesma lista usada por MeusCursosController::cancelarPedido()
     * (V1) — estados em que o aluno ainda pode cancelar o próprio pedido.
     */
    private function pedidoPodeSerCanceladoPeloAluno($status)
    {
        return in_array(
            (string) $status,
            array('rascunho', 'aguardando_pagamento', 'pendencia', 'aguardando_reenvio', 'comprovante_enviado', 'em_analise'),
            true
        );
    }

    private function pedidoPodeRetomarCheckout($status)
    {
        return in_array(
            (string) $status,
            array('rascunho', 'pendencia', 'aguardando_reenvio', 'aguardando_pagamento'),
            true
        );
    }

    private function normalizarPerfil($usuarioId, $usuarioNome)
    {
        $conta = $this->authService->accountData($usuarioId);
        if (!is_array($conta)) {
            $conta = array();
        }

        $nome = isset($conta['nome']) && $conta['nome'] !== '' ? (string) $conta['nome'] : $usuarioNome;
        $editarHref = '/v2/minha-conta';

        return array(
            'nome' => $nome,
            'email' => isset($conta['email']) ? (string) $conta['email'] : '',
            'cpf_mascarado' => $this->mascararCpf(isset($conta['cpf']) ? (string) $conta['cpf'] : ''),
            'telefone_mascarado' => $this->mascararTelefone(isset($conta['telefone']) ? (string) $conta['telefone'] : ''),
            'editar_href' => $editarHref,
        );
    }

    private function primeiroNome($nome)
    {
        $nome = trim((string) $nome);
        if ($nome === '') {
            return '';
        }
        $partes = preg_split('/\s+/', $nome);
        return ($partes && !empty($partes[0])) ? (string) $partes[0] : $nome;
    }

    private function mascararCpf($cpf)
    {
        $digitos = preg_replace('/\D/', '', (string) $cpf);
        if (strlen($digitos) < 11) {
            return '';
        }
        // Revela apenas os 2 últimos dígitos.
        return '***.***.***-' . substr($digitos, -2);
    }

    private function mascararTelefone($telefone)
    {
        $digitos = preg_replace('/\D/', '', (string) $telefone);
        if (strlen($digitos) < 4) {
            return '';
        }
        // Revela apenas os 4 últimos dígitos.
        return '(**) *****-' . substr($digitos, -4);
    }

    private function modalidadeLabel($modalidade)
    {
        return $modalidade !== '' ? \App\Core\Helpers::modalidadeCurso($modalidade) : '';
    }

    private function statusInscricaoLabel($status)
    {
        $map = array(
            'ativa' => 'Em andamento',
            'em_andamento' => 'Em andamento',
            'aprovado' => 'Liberado',
            'pago' => 'Liberado',
            'concluida' => 'Concluído',
            'concluida_sem_certificado' => 'Concluído',
            'certificado_emitido' => 'Concluído',
            'pendente' => 'Pendente',
        );
        return isset($map[$status]) ? $map[$status] : ($status !== '' ? ucfirst(str_replace('_', ' ', $status)) : 'Matriculado');
    }

    private function statusInscricaoClasse($status)
    {
        if (in_array($status, array('concluida', 'concluida_sem_certificado', 'certificado_emitido'), true)) {
            return 'v2-badge-novo';
        }
        if (in_array($status, array('ativa', 'em_andamento', 'aprovado', 'pago'), true)) {
            return 'v2-badge-gratis';
        }
        return 'v2-badge-destaque';
    }

    private function statusPedidoLabel($status)
    {
        $map = array(
            'rascunho' => 'Rascunho',
            'aguardando_pagamento' => 'Aguardando pagamento',
            'pendencia' => 'Pendência',
            'aguardando_reenvio' => 'Aguardando reenvio',
            'comprovante_enviado' => 'Comprovante enviado',
            'em_analise' => 'Em análise',
            'aprovado' => 'Aprovado',
            'pago' => 'Pago',
            'cancelado' => 'Cancelado',
            'expirado' => 'Expirado',
        );
        return isset($map[$status]) ? $map[$status] : ($status !== '' ? ucfirst(str_replace('_', ' ', $status)) : '');
    }

    private function statusPedidoClasse($status)
    {
        if (in_array($status, array('aprovado', 'pago'), true)) {
            return 'v2-badge-novo';
        }
        if (in_array($status, array('cancelado', 'expirado'), true)) {
            return 'v2-badge-destaque';
        }
        return 'v2-badge-gratis';
    }

    private function formatarData($valor)
    {
        $valor = trim((string) $valor);
        if ($valor === '') {
            return '';
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $valor, $m)) {
            return $m[3] . '/' . $m[2] . '/' . $m[1];
        }
        return $valor;
    }

    private function formatarMoeda($valor)
    {
        $valor = (float) $valor;
        if ($valor <= 0) {
            return 'Gratuito';
        }
        return 'R$ ' . number_format($valor, 2, ',', '.');
    }

    private function dadosLayout($usuarioId, $usuarioNome)
    {
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

    private function coursePalette()
    {
        return array(
            array('icon' => 'ti-scale', 'g1' => '#fff4ec', 'g2' => '#ffe4d3', 'cor' => '#cc5500'),
            array('icon' => 'ti-speakerphone', 'g1' => '#f0e8ff', 'g2' => '#e4d6ff', 'cor' => '#4B008E'),
            array('icon' => 'ti-chart-bar', 'g1' => '#d1faf5', 'g2' => '#b8f2ea', 'cor' => '#007a6a'),
            array('icon' => 'ti-device-laptop', 'g1' => '#e3f0ff', 'g2' => '#cfe4ff', 'cor' => '#1d4ed8'),
            array('icon' => 'ti-microphone', 'g1' => '#ffe9f0', 'g2' => '#ffd6e3', 'cor' => '#c00057'),
            array('icon' => 'ti-briefcase', 'g1' => '#eef7df', 'g2' => '#dcefc0', 'cor' => '#3b6d11'),
        );
    }
}
