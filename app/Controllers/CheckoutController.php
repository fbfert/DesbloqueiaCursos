<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Core\Validator;
use App\Core\Logger;
use App\Models\InstrucoesCurso;
use App\Models\Pedido;
use App\Models\Cupom;
use App\Models\Usuario;
use App\Services\ComprovantePixService;
use App\Services\InscricaoService;
use App\Services\PedidoService;
use App\Services\CursoService;
use App\Services\Payments\AbacatePayService;

class CheckoutController extends Controller
{
    private $pedidoService;
    private $inscricaoService;
    private $comprovanteService;
    private $cursoService;
    private $instrucoesCursoModel;
    private $usuarioModel;
    private $pedidoModel;
    private $cupomModel;
    private $abacatePayService;

    public function __construct()
    {
        $this->pedidoService = new PedidoService();
        $this->inscricaoService = new InscricaoService();
        $this->comprovanteService = new ComprovantePixService();
        $this->cursoService = new CursoService();
        $this->instrucoesCursoModel = new InstrucoesCurso();
        $this->usuarioModel = new Usuario();
        $this->pedidoModel = new Pedido();
        $this->cupomModel = new Cupom();
        $this->abacatePayService = new AbacatePayService();
    }

    public function inscricao(Request $request)
    {
        if ($request->method() === 'POST') {
            return $this->processarInscricao($request);
        }

        $cursoId = (int) $request->query('curso_id', 0);
        $turmaId = (int) $request->query('turma_id', 0);
        $curso = $this->cursoService->showPublic($cursoId, $turmaId ?: null);

        if (empty($curso['curso'])) {
            return new Response(View::render('errors/404', array(
                'title' => 'Curso nao encontrado',
            )), 404);
        }

        if (!empty($curso['curso']['usar_turmas']) && empty($curso['curso']['turma_selecionada'])) {
            Session::flash('errors', array('turma' => 'Selecione uma turma aberta para iniciar a inscricao.'));
            return $this->redirect('/cursos/detalhe?curso_id=' . $cursoId);
        }

        $usuarioId = (int) Session::get('usuario_id', 0);
        $turmaSelecionadaId = !empty($curso['curso']['turma_selecionada']['id']) ? (int) $curso['curso']['turma_selecionada']['id'] : 0;
        $situacaoInscricao = $usuarioId > 0
            ? $this->inscricaoService->situacaoAlunoNoCurso($usuarioId, $cursoId, $turmaSelecionadaId ?: null)
            : array(
                'status_fluxo' => 'nao_inscrito',
                'bloquear_nova_inscricao' => false,
                'permitir_nova_inscricao' => true,
                'permitir_continuar_pagamento' => false,
                'pedido_id' => null,
                'checkout_url' => null,
            );

        $pagadorPrefill = $this->carregarPagadorPrefill($usuarioId);
        if (trim((string) $pagadorPrefill['cpf']) === '' && trim((string) Session::get('usuario_cpf', '')) !== '') {
            $pagadorPrefill['cpf'] = (string) Session::get('usuario_cpf');
        }
        if (trim((string) $pagadorPrefill['telefone']) === '' && trim((string) Session::get('usuario_telefone', '')) !== '') {
            $pagadorPrefill['telefone'] = (string) Session::get('usuario_telefone');
        }

        $statusFluxo = (string) $situacaoInscricao['status_fluxo'];
        if (in_array($statusFluxo, array('matriculado', 'pendente_pagamento'), true)) {
            $errors = Session::pullFlash('errors', array());

            return $this->view('checkout/inscricao', array(
                'title' => 'Inscricao',
                'curso' => $curso['curso'],
                'loggedIn' => Session::get('usuario_id') !== null,
                'usuarioNome' => Session::get('usuario_nome'),
                'usuarioEmail' => Session::get('usuario_email'),
                'pagadorPrefill' => $pagadorPrefill,
                'errors' => $errors,
                'inscricaoDuplicada' => false,
                'situacaoInscricao' => $situacaoInscricao,
                'inscricaoPendente' => $statusFluxo === 'pendente_pagamento',
                'inscricaoMatriculada' => $statusFluxo === 'matriculado',
                'success' => Session::pullFlash('success'),
            ));
        }


        if (trim((string) $pagadorPrefill['cpf']) === '' || trim((string) $pagadorPrefill['telefone']) === '') {
            Session::flash('errors', array('Atualize seu cadastro com CPF e telefone antes de iniciar a inscrição.'));
            return $this->redirect('/minha-conta');
        }

        $errors = Session::pullFlash('errors', array());

        return $this->view('checkout/inscricao', array(
            'title' => 'Inscricao',
            'curso' => $curso['curso'],
            'loggedIn' => Session::get('usuario_id') !== null,
            'usuarioNome' => Session::get('usuario_nome'),
            'usuarioEmail' => Session::get('usuario_email'),
            'pagadorPrefill' => $pagadorPrefill,
            'errors' => $errors,
            'inscricaoDuplicada' => false,
            'situacaoInscricao' => $situacaoInscricao,
            'inscricaoPendente' => false,
            'inscricaoMatriculada' => false,
            'success' => Session::pullFlash('success'),
        ));
    }

    public function participantes(Request $request)
    {
        $pedidoId = (int) $this->pedidoIdFromRequest($request);
        if ($pedidoId <= 0) {
            return $this->redirect('/cursos');
        }

        if ($request->method() === 'POST') {
            return $this->salvarParticipantes($request, $pedidoId);
        }

        $pedido = $this->pedidoService->detalharCheckout($pedidoId, Session::get('usuario_id'));
        if (empty($pedido['pedido'])) {
            return new Response(View::render('errors/404', array(
                'title' => 'Pedido nao encontrado',
            )), 404);
        }

        $quantidade = 1;
        if (!empty($pedido['pedido']['itens'][0]['quantidade'])) {
            $quantidade = max(1, (int) $pedido['pedido']['itens'][0]['quantidade']);
        }

        $isCompraPropria = $this->isCompraPropriaPedido(isset($pedido['pedido']['tipo_pedido']) ? $pedido['pedido']['tipo_pedido'] : '');
        if ($isCompraPropria) {
            $resultadoCompraPropria = $this->concluirCheckoutCompraPropria($pedidoId, $request, $pedido['pedido']);
            if (empty($resultadoCompraPropria['ok'])) {
                Session::flash('errors', array('pedido' => isset($resultadoCompraPropria['message']) ? $resultadoCompraPropria['message'] : 'Não foi possivel concluir a compra propria.'));
                return $this->redirect('/checkout/resumo?pedido_id=' . $pedidoId);
            }

            if (!empty($resultadoCompraPropria['auto_aprovado_zero_valor'])) {
                Session::flash('success', 'Curso gratuito liberado. Você foi direcionado para Minha Página.');
                return $this->redirect('/minha-pagina');
            }

            Session::flash('success', 'Compra própria concluída com participante automático.');
            return $this->redirect('/checkout/resumo?pedido_id=' . $pedidoId);
        }

        $usuarioPrefillId = $this->resolverUsuarioPrefillIdDoPedido($pedido['pedido']);
        $participantePrefill = $this->carregarParticipantePrefill($usuarioPrefillId);
        $pagadorPrefill = $this->carregarPagadorPrefill($usuarioPrefillId);
        if ($participantePrefill['cpf'] === '' && trim((string) Session::get('usuario_cpf', '')) !== '') {
            $participantePrefill['cpf'] = (string) Session::get('usuario_cpf');
        }
        if ($participantePrefill['telefone'] === '' && trim((string) Session::get('usuario_telefone', '')) !== '') {
            $participantePrefill['telefone'] = (string) Session::get('usuario_telefone');
        }
        if ($participantePrefill['cpf'] === '' && !empty($pagadorPrefill['cpf'])) {
            $participantePrefill['cpf'] = (string) $pagadorPrefill['cpf'];
        }
        if ($participantePrefill['telefone'] === '' && !empty($pagadorPrefill['telefone'])) {
            $participantePrefill['telefone'] = (string) $pagadorPrefill['telefone'];
        }

        return $this->view('checkout/participantes', array(
            'title' => 'Participantes',
            'pedido' => $pedido['pedido'],
            'quantidade' => $quantidade,
            'participantePrefill' => $participantePrefill,
            'loggedIn' => Session::get('usuario_id') !== null,
            'usuarioNome' => Session::get('usuario_nome'),
            'usuarioEmail' => Session::get('usuario_email'),
            'errors' => Session::pullFlash('errors', array()),
            'success' => Session::pullFlash('success'),
        ));
    }

    public function resumo(Request $request)
    {
        $pedidoId = (int) $this->pedidoIdFromRequest($request);
        if ($pedidoId <= 0) {
            return $this->redirect('/cursos');
        }

        $pedido = $this->pedidoService->detalharCheckout($pedidoId, Session::get('usuario_id'));
        if (empty($pedido['pedido'])) {
            return new Response(View::render('errors/404', array(
                'title' => 'Pedido nao encontrado',
            )), 404);
        }

        $pedidoGateway = strtolower(trim((string) ($pedido['pedido']['payment_gateway'] ?? '')));
        $abacatepayAtivo = $this->abacatePayService->isEnabled();
        $gatewayEscolhido = $abacatepayAtivo && $pedidoGateway !== 'pix_manual' ? 'abacatepay' : 'manual';

        Logger::info('checkout.resumo.gateway', array(
            'pedido_id' => $pedidoId,
            'gateway_escolhido' => $gatewayEscolhido,
            'abacatepay_ativo' => $abacatepayAtivo ? 1 : 0,
            'pedido_gateway_atual' => $pedidoGateway !== '' ? $pedidoGateway : null,
            'payment_provider_checkout_id' => isset($pedido['pedido']['payment_provider_checkout_id']) ? $pedido['pedido']['payment_provider_checkout_id'] : null,
            'payment_provider_payment_url' => isset($pedido['pedido']['payment_provider_payment_url']) ? $pedido['pedido']['payment_provider_payment_url'] : null,
        ));

        return $this->view('checkout/resumo', array(
            'title' => 'Resumo do pedido',
            'pedido' => $pedido['pedido'],
            'canSeePix' => !empty($pedido['can_see_pix']),
            'abacatepayEnabled' => $abacatepayAtivo,
            'pedidoSemCobranca' => ((float) $pedido['pedido']['total'] <= 0.0),
            'comprovanteAguardandoAprovacao' => !empty($pedido['pedido']['comprovante_aguardando_aprovacao']),
            'pedidoPagoOuAprovado' => in_array((string) $pedido['pedido']['status'], array('aprovado', 'pago'), true),
            'pedidoGateway' => $pedidoGateway,
            'loggedIn' => Session::get('usuario_id') !== null,
            'usuarioNome' => Session::get('usuario_nome'),
            'usuarioEmail' => Session::get('usuario_email'),
            'cupomPromocional' => Session::get('cupom_promocional_codigo', ''),
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
        ));
    }

    public function pagarAbacatepay(Request $request)
    {
        if (!Session::get('usuario_id')) {
            Session::flash('errors', array('auth' => 'Faça login para continuar.'));
            return $this->redirect('/login');
        }

        $pedidoId = (int) $request->input('pedido_id', 0);
        if ($pedidoId <= 0) {
            Session::flash('errors', array('pedido' => 'Pedido inválido.'));
            return $this->redirect('/meus-cursos');
        }

        if (!$this->abacatePayService->isEnabled()) {
            Session::flash('errors', array('pagamento' => 'O pagamento online está desativado no momento.'));
            return $this->redirect('/checkout/resumo?pedido_id=' . $pedidoId);
        }

        $detalhe = $this->pedidoService->detalharCheckout($pedidoId, Session::get('usuario_id'), true);
        if (empty($detalhe['pedido'])) {
            Session::flash('errors', array('pedido' => 'Você não tem permissão para acessar este pedido.'));
            return $this->redirect('/meus-cursos');
        }

        $pedido = $detalhe['pedido'];
        if (strtolower(trim((string) ($pedido['payment_gateway'] ?? ''))) === 'abacatepay' && !empty($pedido['payment_provider_payment_url'])) {
            Logger::info('checkout.abacatepay.reaproveitando_checkout', array(
                'pedido_id' => $pedidoId,
                'payment_provider_checkout_id' => isset($pedido['payment_provider_checkout_id']) ? $pedido['payment_provider_checkout_id'] : null,
                'payment_provider_payment_url' => $pedido['payment_provider_payment_url'],
            ));

            return $this->redirect((string) $pedido['payment_provider_payment_url']);
        }
        if (in_array((string) $pedido['status'], array('pago', 'aprovado'), true)) {
            Session::flash('success', 'Este pedido já está pago.');
            return $this->redirect('/checkout/sucesso?pedido_id=' . $pedidoId);
        }

        if ((float) $pedido['total'] <= 0.0) {
            Session::flash('success', 'Este pedido não possui cobrança. A liberação segue o fluxo gratuito.');
            return $this->redirect('/checkout/sucesso?pedido_id=' . $pedidoId);
        }

        if ((string) $pedido['status'] === 'rascunho') {
            $finalizacao = $this->pedidoService->finalizarCheckout($pedidoId, Session::get('usuario_id'), $request->ip(), $request->userAgent());
            if (empty($finalizacao['ok'])) {
                Session::flash('errors', array('pedido' => isset($finalizacao['message']) ? $finalizacao['message'] : 'Não foi possível preparar o pedido para pagamento.'));
                return $this->redirect('/checkout/resumo?pedido_id=' . $pedidoId);
            }

            $detalhe = $this->pedidoService->detalharCheckout($pedidoId, Session::get('usuario_id'), true);
            if (empty($detalhe['pedido'])) {
                Session::flash('errors', array('pedido' => 'Não foi possível recarregar o pedido.'));
                return $this->redirect('/checkout/resumo?pedido_id=' . $pedidoId);
            }

            $pedido = $detalhe['pedido'];
        }

        $aluno = array(
            'id' => Session::get('usuario_id'),
            'nome' => isset($pedido['pagador_nome']) && trim((string) $pedido['pagador_nome']) !== '' ? (string) $pedido['pagador_nome'] : (string) Session::get('usuario_nome'),
            'email' => isset($pedido['pagador_email']) ? (string) $pedido['pagador_email'] : (string) Session::get('usuario_email'),
            'cpf' => isset($pedido['pagador_cpf']) ? (string) $pedido['pagador_cpf'] : (string) Session::get('usuario_cpf'),
            'telefone' => isset($pedido['pagador_telefone']) ? (string) $pedido['pagador_telefone'] : (string) Session::get('usuario_telefone'),
            'cidade' => isset($pedido['pagador_cidade']) ? (string) $pedido['pagador_cidade'] : '',
            'estado' => isset($pedido['pagador_estado']) ? (string) $pedido['pagador_estado'] : '',
        );

        $checkout = $this->abacatePayService->createCheckout($pedido, $aluno, isset($pedido['itens']) && is_array($pedido['itens']) ? $pedido['itens'] : array());
        if (empty($checkout['ok'])) {
            Session::flash('errors', array('pagamento' => isset($checkout['message']) ? $checkout['message'] : 'Não foi possível iniciar o checkout da AbacatePay.'));
            return $this->redirect('/checkout/resumo?pedido_id=' . $pedidoId);
        }

        $registrado = $this->abacatePayService->registrarCheckoutNoPedido(
            $pedido,
            $checkout,
            Session::get('usuario_id'),
            $request->ip(),
            $request->userAgent()
        );

        if (empty($registrado['ok'])) {
            Session::flash('errors', array('pagamento' => isset($registrado['message']) ? $registrado['message'] : 'Não foi possível registrar o checkout.'));
            return $this->redirect('/checkout/resumo?pedido_id=' . $pedidoId);
        }

        if (empty($checkout['checkout']['url'])) {
            Session::flash('errors', array('pagamento' => 'A URL de pagamento não foi retornada pela AbacatePay.'));
            return $this->redirect('/checkout/resumo?pedido_id=' . $pedidoId);
        }

        return $this->redirect($checkout['checkout']['url']);
    }

    public function cupomPromocional(Request $request)
    {
        $codigo = trim((string) $request->query('codigo', ''));
        if ($codigo === '') {
            return $this->redirect('/cursos');
        }

        $cupom = $this->cupomModel->findByCodigo($codigo);
        if (!$cupom || ($cupom['status'] ?? '') !== 'ativo') {
            Session::flash('errors', array('cupom' => 'Cupom promocional indisponivel.'));
            return $this->redirect('/cursos');
        }

        Session::put('cupom_promocional_codigo', $codigo);
        Session::flash('success', 'Cupom promocional salvo para uso no checkout.');

        return $this->redirect('/cursos');
    }

    public function cupom(Request $request)
    {
        $pedidoId = (int) $this->pedidoIdFromRequest($request);
        if ($pedidoId <= 0) {
            return $this->redirect('/cursos');
        }

        $pedidoAcesso = $this->pedidoService->detalharCheckout($pedidoId, Session::get('usuario_id'));
        if (empty($pedidoAcesso['pedido'])) {
            Session::flash('errors', array('pedido' => 'Você nao tem permissao para acessar este pedido.'));
            return $this->redirect('/cursos');
        }

        if ($request->method() !== 'POST') {
            return $this->view('checkout/cupom', array(
                'title' => 'Aplicar cupom',
                'pedido' => $pedidoAcesso['pedido'],
                'loggedIn' => Session::get('usuario_id') !== null,
                'usuarioNome' => Session::get('usuario_nome'),
            ));
        }

        if (!Session::get('usuario_id')) {
            Session::flash('errors', array('auth' => 'Faça login para aplicar o cupom.'));
            return $this->redirect('/login');
        }

        $cupomCodigo = trim((string) $request->input('cupom_codigo', ''));

        if ($pedidoId <= 0 || $cupomCodigo === '') {
            Session::flash('errors', array('cupom_codigo' => 'Informe um cupom valido.'));
            return $this->redirect('/checkout/resumo?pedido_id=' . $pedidoId);
        }

        $resultado = $this->pedidoService->aplicarCupomAoPedido(
            $pedidoId,
            $cupomCodigo,
            Session::get('usuario_id'),
            $request->ip(),
            $request->userAgent()
        );

        if (empty($resultado['ok'])) {
            Session::flash('errors', array('cupom_codigo' => isset($resultado['message']) ? $resultado['message'] : 'Não foi possivel aplicar o cupom.'));
        } else {
            Session::flash('success', 'Cupom aplicado com sucesso.');
        }

        return $this->redirect('/checkout/resumo?pedido_id=' . $pedidoId);
    }

    public function comprovante(Request $request)
    {
        $pedidoId = (int) $this->pedidoIdFromRequest($request);
        if ($pedidoId <= 0) {
            return $this->redirect('/cursos');
        }

        if ($request->method() === 'POST') {
            return $this->enviarComprovante($request, $pedidoId);
        }

        $pedido = $this->pedidoService->detalharCheckout($pedidoId, Session::get('usuario_id'));
        if (empty($pedido['pedido'])) {
            return new Response(View::render('errors/404', array(
                'title' => 'Pedido nao encontrado',
            )), 404);
        }

        if (in_array((string) $pedido['pedido']['status'], array('aprovado', 'pago'), true)) {
            return $this->redirect('/checkout/sucesso?pedido_id=' . $pedidoId);
        }

        return $this->view('checkout/comprovante', array(
            'title' => 'Enviar comprovante PIX',
            'pedido' => $pedido['pedido'],
            'canSeePix' => !empty($pedido['can_see_pix']),
            'loggedIn' => Session::get('usuario_id') !== null,
            'usuarioNome' => Session::get('usuario_nome'),
            'usuarioEmail' => Session::get('usuario_email'),
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
        ));
    }

    public function sucesso(Request $request)
    {
        $pedidoId = (int) $this->pedidoIdFromRequest($request);
        $usuarioId = Session::get('usuario_id');
        if (!$usuarioId) {
            Session::flash('errors', array('auth' => 'Faça login para acessar este pedido.'));
            return $this->redirect('/login');
        }

        $pedido = $pedidoId > 0 ? $this->pedidoService->detalharCheckout($pedidoId, $usuarioId, true) : array('pedido' => null);
        if (empty($pedido['pedido'])) {
            return new Response(View::render('errors/404', array(
                'title' => 'Pedido nao encontrado',
            )), 404);
        }
        $proximasAcoes = !empty($pedido['pedido']) ? $this->carregarProximasAcoesCheckout($pedido['pedido']) : array();

        return $this->view('checkout/sucesso', array(
            'title' => 'Pedido recebido',
            'pedido' => $pedido['pedido'],
            'proximas_acoes' => $proximasAcoes,
            'comprovanteAguardandoAprovacao' => !empty($pedido['pedido']['comprovante_aguardando_aprovacao']),
            'pedidoSemCobranca' => ((float) $pedido['pedido']['total'] <= 0.0),
            'loggedIn' => Session::get('usuario_id') !== null,
            'usuarioNome' => Session::get('usuario_nome'),
            'usuarioEmail' => Session::get('usuario_email'),
            'success' => Session::pullFlash('success'),
        ));
    }

    private function processarInscricao(Request $request)
    {
        if (!Session::get('usuario_id')) {
            Session::flash('errors', array('auth' => 'Faça login ou crie sua conta para continuar.'));
            return $this->redirect('/login');
        }

        $cursoIdSolicitado = (int) $request->input('curso_evento_id', 0);
        $turmaIdSolicitada = (int) $request->input('turma_id', 0);
        $situacaoInscricao = $cursoIdSolicitado > 0
            ? $this->inscricaoService->situacaoAlunoNoCurso((int) Session::get('usuario_id'), $cursoIdSolicitado, $turmaIdSolicitada ?: null)
            : array(
                'status_fluxo' => 'nao_inscrito',
                'pedido_id' => null,
                'checkout_url' => null,
            );

        if ((string) $situacaoInscricao['status_fluxo'] === 'matriculado') {
            Session::flash('success', 'Você já está matriculado neste curso.');
            return $this->redirect('/minha-pagina');
        }

        if ((string) $situacaoInscricao['status_fluxo'] === 'pendente_pagamento') {
            $destinoPagamento = !empty($situacaoInscricao['checkout_url'])
                ? (string) $situacaoInscricao['checkout_url']
                : '/checkout/resumo?pedido_id=' . (int) $situacaoInscricao['pedido_id'];

            Session::flash('success', 'Você possui uma inscrição pendente para este curso. Continue o pagamento para concluir sua matrícula.');

            return $this->redirect($destinoPagamento);
        }

        $errors = $this->validateInscricao($request);
        if (!empty($errors)) {
            Session::flash('errors', $errors);
            $cursoIdErro = (int) $request->input('curso_evento_id', 0);
            $turmaIdErro = (int) $request->input('turma_id', 0);
            return $this->redirect('/inscricao?curso_id=' . $cursoIdErro . ($turmaIdErro ? '&turma_id=' . $turmaIdErro : ''));
        }

        $cursoId = (int) $request->input('curso_evento_id', 0);
        $turmaId = (int) $request->input('turma_id', 0);
        $tipoPedido = (string) $request->input('tipo_pedido', 'propria');
        $quantidade = $tipoPedido === 'propria' ? 1 : max(1, (int) $request->input('quantidade', 1));
        $pagadorPrefill = $this->carregarPagadorPrefill((int) Session::get('usuario_id', 0));
        $pagadorNome = trim((string) $request->input('pagador_nome', ''));
        if ($pagadorNome === '' && Session::get('usuario_nome')) {
            $pagadorNome = Session::get('usuario_nome');
        }
        if ($pagadorNome === '' && !empty($pagadorPrefill['nome'])) {
            $pagadorNome = (string) $pagadorPrefill['nome'];
        }
        $pagadorEmail = trim((string) $request->input('pagador_email', ''));
        if ($pagadorEmail === '' && Session::get('usuario_email')) {
            $pagadorEmail = Session::get('usuario_email');
        }
        if ($pagadorEmail === '' && !empty($pagadorPrefill['email'])) {
            $pagadorEmail = (string) $pagadorPrefill['email'];
        }
        $pagadorCpf = trim((string) $request->input('pagador_cpf', ''));
        if ($pagadorCpf === '' && !empty($pagadorPrefill['cpf'])) {
            $pagadorCpf = (string) $pagadorPrefill['cpf'];
        }
        $pagadorTelefone = trim((string) $request->input('pagador_telefone', ''));
        if ($pagadorTelefone === '' && !empty($pagadorPrefill['telefone'])) {
            $pagadorTelefone = (string) $pagadorPrefill['telefone'];
        }
        $pagadorCidade = trim((string) $request->input('pagador_cidade', ''));
        if ($pagadorCidade === '' && !empty($pagadorPrefill['cidade'])) {
            $pagadorCidade = (string) $pagadorPrefill['cidade'];
        }
        $pagadorEstado = trim((string) $request->input('pagador_estado', ''));
        if ($pagadorEstado === '' && !empty($pagadorPrefill['estado'])) {
            $pagadorEstado = (string) $pagadorPrefill['estado'];
        }

        $resultado = $this->pedidoService->criarCheckoutDraft(array(
            'curso_evento_id' => $cursoId,
            'turma_id' => $turmaId ?: null,
            'quantidade' => $quantidade,
            'tipo_pedido' => $tipoPedido,
            'comprador_usuario_id' => Session::get('usuario_id'),
            'pagador_usuario_id' => Session::get('usuario_id'),
            'pagador_nome' => $pagadorNome,
            'pagador_cpf' => $pagadorCpf,
            'pagador_email' => $pagadorEmail,
            'pagador_telefone' => $pagadorTelefone,
            'pagador_cidade' => $pagadorCidade !== '' ? $pagadorCidade : null,
            'pagador_estado' => $pagadorEstado !== '' ? $pagadorEstado : null,
            'pagador_empresa_nome' => $request->input('pagador_empresa_nome'),
            'pagador_empresa_documento' => $request->input('pagador_empresa_documento'),
            'observacoes_publicas' => $request->input('observacoes_publicas'),
            'canal_origem' => 'checkout',
        ), Session::get('usuario_id'), $request->ip(), $request->userAgent());

        if (empty($resultado['ok'])) {
            Session::flash('errors', array('pedido' => isset($resultado['message']) ? $resultado['message'] : 'Não foi possivel iniciar o checkout.'));
            return $this->redirect('/inscricao?curso_id=' . $cursoId . ($turmaId ? '&turma_id=' . $turmaId : ''));
        }

        Session::put('checkout_pedido_id', $resultado['pedido_id']);

        if ($this->isCompraPropriaPedido($tipoPedido)) {
            $resultadoCompraPropria = $this->concluirCheckoutCompraPropria((int) $resultado['pedido_id'], $request);
            if (empty($resultadoCompraPropria['ok'])) {
                Session::flash('errors', array('pedido' => isset($resultadoCompraPropria['message']) ? $resultadoCompraPropria['message'] : 'Não foi possivel concluir a compra propria.'));
                return $this->redirect('/checkout/resumo?pedido_id=' . (int) $resultado['pedido_id']);
            }

            Session::flash('success', 'Compra própria iniciada com sucesso. O participante foi gerado automaticamente.');
            return $this->redirect('/checkout/resumo?pedido_id=' . (int) $resultado['pedido_id']);
        }

        return $this->redirect('/checkout/participantes?pedido_id=' . $resultado['pedido_id']);
    }

    private function salvarParticipantes(Request $request, $pedidoId)
    {
        if (!Session::get('usuario_id')) {
            Session::flash('errors', array('auth' => 'Faça login para continuar.'));
            return $this->redirect('/login');
        }

        $pedido = $this->pedidoService->detalharCheckout($pedidoId, Session::get('usuario_id'));
        if (empty($pedido['pedido'])) {
            Session::flash('errors', array('pedido' => 'Você nao tem permissao para alterar este pedido.'));
            return $this->redirect('/cursos');
        }

        if ($this->isCompraPropriaPedido(isset($pedido['pedido']['tipo_pedido']) ? $pedido['pedido']['tipo_pedido'] : '')) {
            $resultadoCompraPropria = $this->concluirCheckoutCompraPropria($pedidoId, $request, $pedido['pedido']);
            if (empty($resultadoCompraPropria['ok'])) {
                Session::flash('errors', array('pedido' => isset($resultadoCompraPropria['message']) ? $resultadoCompraPropria['message'] : 'Não foi possivel concluir a compra propria.'));
                return $this->redirect('/checkout/resumo?pedido_id=' . $pedidoId);
            }

            if (!empty($resultadoCompraPropria['auto_aprovado_zero_valor'])) {
                Session::flash('success', 'Curso gratuito liberado. Você foi direcionado para Minha Página.');
                return $this->redirect('/minha-pagina');
            }

            Session::flash('success', 'Compra própria concluída com participante automático.');
            return $this->redirect('/checkout/resumo?pedido_id=' . $pedidoId);
        }

        $participantes = $request->input('participantes', array());
        if (!is_array($participantes)) {
            $participantes = array();
        }

        $errors = $this->validateParticipantes($participantes);
        if (!empty($errors)) {
            Session::flash('errors', $errors);
            return $this->redirect('/checkout/participantes?pedido_id=' . $pedidoId);
        }

        $resultado = $this->pedidoService->adicionarParticipantesAoPedido(
            $pedidoId,
            $participantes,
            Session::get('usuario_id'),
            $request->ip(),
            $request->userAgent()
        );

        if (empty($resultado['ok'])) {
            Session::flash('errors', array('participantes' => isset($resultado['message']) ? $resultado['message'] : 'Não foi possivel salvar os participantes.'));
            return $this->redirect('/checkout/participantes?pedido_id=' . $pedidoId);
        }

        $this->inscricaoService->gerarDoPedido($pedidoId, Session::get('usuario_id'), $request->ip(), $request->userAgent());
        $finalizacao = $this->pedidoService->finalizarCheckout($pedidoId, Session::get('usuario_id'), $request->ip(), $request->userAgent());
        if (empty($finalizacao['ok'])) {
            Session::flash('errors', array('pedido' => isset($finalizacao['message']) ? $finalizacao['message'] : 'Não foi possivel finalizar o pedido.'));
            return $this->redirect('/checkout/resumo?pedido_id=' . $pedidoId);
        }

        if (!empty($finalizacao['auto_aprovado_zero_valor'])) {
            Session::flash('success', 'Curso gratuito liberado. Você foi direcionado para Minha Página.');
            return $this->redirect('/minha-pagina');
        }

        if ($this->abacatePayService->isEnabled()) {
            Session::flash('success', 'Participantes salvos. Agora siga para o pagamento online.');
        } else {
            Session::flash('success', 'Participantes salvos. Revise o pedido e siga para o comprovante PIX.');
        }
        return $this->redirect('/checkout/resumo?pedido_id=' . $pedidoId);
    }

    private function enviarComprovante(Request $request, $pedidoId)
    {
        if (!Session::get('usuario_id')) {
            Session::flash('errors', array('auth' => 'Faça login para enviar o comprovante.'));
            return $this->redirect('/login');
        }

        $pedido = $this->pedidoService->detalharCheckout($pedidoId, Session::get('usuario_id'));
        if (empty($pedido['pedido'])) {
            Session::flash('errors', array('pedido' => 'Você nao tem permissao para acessar este pedido.'));
            return $this->redirect('/cursos');
        }

        if (in_array((string) $pedido['pedido']['status'], array('aprovado', 'pago'), true)) {
            Session::flash('success', 'Pedido aprovado automaticamente. Não há comprovante PIX para enviar.');
            return $this->redirect('/checkout/sucesso?pedido_id=' . $pedidoId);
        }

        if (!isset($_FILES['comprovante']) || empty($_FILES['comprovante']['tmp_name'])) {
            Session::flash('errors', array('comprovante' => 'Selecione um arquivo de comprovante.'));
            return $this->redirect('/checkout/comprovante?pedido_id=' . $pedidoId);
        }

        if ((int) $_FILES['comprovante']['size'] <= 0) {
            Session::flash('errors', array('comprovante' => 'O arquivo enviado nao e valido.'));
            return $this->redirect('/checkout/comprovante?pedido_id=' . $pedidoId);
        }

        try {
            $resultado = $this->comprovanteService->enviarUpload(
                $pedidoId,
                $_FILES['comprovante'],
                array(
                    'valor_informado' => $request->input('valor_informado'),
                    'motivo_reenvio' => $request->input('motivo_reenvio'),
                ),
                Session::get('usuario_id'),
                $request->ip(),
                $request->userAgent()
            );
        } catch (\Throwable $exception) {
            Logger::error('checkout.comprovante_upload_falhou', array(
                'pedido_id' => $pedidoId,
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ));
            Session::flash('errors', array('Não foi possível salvar o comprovante. Verifique o arquivo enviado e tente novamente.'));
            return $this->redirect('/checkout/comprovante?pedido_id=' . $pedidoId);
        }

        if (empty($resultado['ok'])) {
            Session::flash('errors', array('comprovante' => isset($resultado['message']) ? $resultado['message'] : 'Não foi possivel enviar o comprovante.'));
            return $this->redirect('/checkout/comprovante?pedido_id=' . $pedidoId);
        }

        Session::flash('success', 'Comprovante enviado. Agora acompanhe em Meus Cursos.');
        return $this->redirect('/checkout/sucesso?pedido_id=' . $pedidoId);
    }

    private function pedidoIdFromRequest(Request $request)
    {
        $pedidoId = (int) $request->query('pedido_id', 0);
        if ($pedidoId > 0) {
            return $pedidoId;
        }

        return (int) Session::get('checkout_pedido_id', 0);
    }

    private function validateInscricao(Request $request)
    {
        $errors = array();

        if ((int) $request->input('curso_evento_id', 0) <= 0) {
            $errors[] = 'Selecione um curso valido.';
        }

        $cursoId = (int) $request->input('curso_evento_id', 0);
        $turmaId = (int) $request->input('turma_id', 0);
        if ($cursoId > 0) {
            $validacaoTurma = $this->cursoService->validarTurmaPublicaParaInscricao($cursoId, $turmaId ?: null);
            if (empty($validacaoTurma['ok'])) {
                $errors[] = isset($validacaoTurma['message']) ? $validacaoTurma['message'] : 'A turma selecionada nao esta disponivel para inscricao.';
            }
        }

        $tipoPedido = (string) $request->input('tipo_pedido', 'propria');
        if ($tipoPedido !== 'propria' && (int) $request->input('quantidade', 0) <= 0) {
            $errors[] = 'Informe uma quantidade valida de vagas.';
        }

        $pagadorNome = trim((string) $request->input('pagador_nome', ''));
        if ($pagadorNome === '' && Session::get('usuario_nome')) {
            $pagadorNome = Session::get('usuario_nome');
        }
        if ($pagadorNome === '') {
            $errors[] = 'Informe o nome do pagador.';
        }

        $pagadorCpf = $request->input('pagador_cpf', '');
        if (!Validator::cpf($pagadorCpf)) {
            $errors[] = 'Informe um CPF valido do pagador.';
        }

        $pagadorEmail = trim((string) $request->input('pagador_email', ''));
        if ($pagadorEmail === '' && Session::get('usuario_email')) {
            $pagadorEmail = Session::get('usuario_email');
        }
        if (!Validator::email($pagadorEmail)) {
            $errors[] = 'Informe um e-mail valido do pagador.';
        }

        $pagadorTelefone = trim((string) $request->input('pagador_telefone', ''));
        if ($pagadorTelefone === '') {
            $errors[] = 'Informe o telefone do pagador.';
        }

        return $errors;
    }

    private function validateParticipantes(array $participantes)
    {
        $errors = array();

        foreach ($participantes as $indice => $participante) {
            if (trim((string) (isset($participante['nome']) ? $participante['nome'] : '')) === '') {
                $errors[] = 'O participante #' . ($indice + 1) . ' precisa de nome.';
            }

            if (!empty($participante['email']) && !Validator::email($participante['email'])) {
                $errors[] = 'O e-mail do participante #' . ($indice + 1) . ' e invalido.';
            }

            if (!empty($participante['cpf']) && !Validator::cpf($participante['cpf'])) {
                $errors[] = 'O CPF do participante #' . ($indice + 1) . ' e invalido.';
            }
        }

        return $errors;
    }

    private function concluirCheckoutCompraPropria($pedidoId, Request $request, ?array $pedido = null)
    {
        if ($pedido === null) {
            $pedidoDetalhado = $this->pedidoService->detalharCheckout($pedidoId, Session::get('usuario_id'));
            if (empty($pedidoDetalhado['pedido'])) {
                return array('ok' => false, 'message' => 'Pedido nao encontrado.');
            }

            $pedido = $pedidoDetalhado['pedido'];
        }

        $participante = array(
            'usuario_id' => Session::get('usuario_id'),
            'nome' => isset($pedido['pagador_nome']) && trim((string) $pedido['pagador_nome']) !== '' ? (string) $pedido['pagador_nome'] : (string) Session::get('usuario_nome'),
            'cpf' => isset($pedido['pagador_cpf']) ? (string) $pedido['pagador_cpf'] : '',
            'email' => isset($pedido['pagador_email']) ? (string) $pedido['pagador_email'] : (string) Session::get('usuario_email'),
            'telefone' => isset($pedido['pagador_telefone']) ? (string) $pedido['pagador_telefone'] : '',
        );

        if (trim((string) $participante['nome']) === '') {
            return array('ok' => false, 'message' => 'Informe os dados do pagador antes de continuar.');
        }

        $resultadoParticipante = $this->pedidoService->sincronizarParticipanteCompraPropria(
            $pedidoId,
            $participante,
            Session::get('usuario_id'),
            $request->ip(),
            $request->userAgent()
        );
        if (empty($resultadoParticipante['ok'])) {
            return $resultadoParticipante;
        }

        $resultadoInscricoes = $this->inscricaoService->gerarDoPedido($pedidoId, Session::get('usuario_id'), $request->ip(), $request->userAgent());
        if (empty($resultadoInscricoes['ok'])) {
            return $resultadoInscricoes;
        }

        $finalizacao = $this->pedidoService->finalizarCheckout($pedidoId, Session::get('usuario_id'), $request->ip(), $request->userAgent());
        if (empty($finalizacao['ok'])) {
            return $finalizacao;
        }

        return array(
            'ok' => true,
            'auto_aprovado_zero_valor' => !empty($finalizacao['auto_aprovado_zero_valor']),
        );
    }

    private function carregarPagadorPrefill($usuarioId)
    {
        $prefill = array(
            'nome' => '',
            'cpf' => '',
            'email' => '',
            'telefone' => '',
            'cidade' => '',
            'estado' => '',
        );

        if ($usuarioId <= 0) {
            $usuario = $this->buscarUsuarioDaSessaoPorEmail();
            if ($usuario) {
                $usuarioId = (int) $usuario['id'];
                $prefill['nome'] = isset($usuario['nome']) ? (string) $usuario['nome'] : '';
                $prefill['cpf'] = isset($usuario['cpf']) ? (string) $usuario['cpf'] : '';
                $prefill['email'] = isset($usuario['email']) ? (string) $usuario['email'] : '';
                $prefill['telefone'] = isset($usuario['telefone']) ? (string) $usuario['telefone'] : '';
                $prefill['cidade'] = isset($usuario['cidade']) ? (string) $usuario['cidade'] : '';
                $prefill['estado'] = isset($usuario['estado']) ? strtoupper((string) $usuario['estado']) : '';
            } else {
                return $prefill;
            }
        }

        $usuario = $this->usuarioModel->findById($usuarioId);
        if ($usuario) {
            $prefill['nome'] = isset($usuario['nome']) ? (string) $usuario['nome'] : '';
            $prefill['cpf'] = isset($usuario['cpf']) ? (string) $usuario['cpf'] : '';
            $prefill['email'] = isset($usuario['email']) ? (string) $usuario['email'] : '';
            $prefill['telefone'] = isset($usuario['telefone']) ? (string) $usuario['telefone'] : '';
            $prefill['cidade'] = isset($usuario['cidade']) ? (string) $usuario['cidade'] : '';
            $prefill['estado'] = isset($usuario['estado']) ? strtoupper((string) $usuario['estado']) : '';
        }

        $ultimoPedido = $this->pedidoModel->findLatestByUsuarioForPrefill($usuarioId);
        if ($ultimoPedido) {
            if ($prefill['nome'] === '' && !empty($ultimoPedido['pagador_nome'])) {
                $prefill['nome'] = (string) $ultimoPedido['pagador_nome'];
            }
            if ($prefill['cpf'] === '' && !empty($ultimoPedido['pagador_cpf'])) {
                $prefill['cpf'] = (string) $ultimoPedido['pagador_cpf'];
            }
            if ($prefill['email'] === '' && !empty($ultimoPedido['pagador_email'])) {
                $prefill['email'] = (string) $ultimoPedido['pagador_email'];
            }
            if ($prefill['telefone'] === '' && !empty($ultimoPedido['pagador_telefone'])) {
                $prefill['telefone'] = (string) $ultimoPedido['pagador_telefone'];
            }

            if ($prefill['cidade'] === '' && !empty($ultimoPedido['pagador_cidade'])) {
                $prefill['cidade'] = (string) $ultimoPedido['pagador_cidade'];
            }
            if ($prefill['estado'] === '' && !empty($ultimoPedido['pagador_estado'])) {
                $prefill['estado'] = strtoupper((string) $ultimoPedido['pagador_estado']);
            }
        }

        return $prefill;
    }

    private function carregarParticipantePrefill($usuarioId)
    {
        $prefill = array(
            'nome' => '',
            'cpf' => '',
            'email' => '',
            'telefone' => '',
        );

        if ($usuarioId <= 0) {
            $usuario = $this->buscarUsuarioDaSessaoPorEmail();
            if ($usuario) {
                $prefill['nome'] = isset($usuario['nome']) ? (string) $usuario['nome'] : '';
                $prefill['cpf'] = isset($usuario['cpf']) ? (string) $usuario['cpf'] : '';
                $prefill['email'] = isset($usuario['email']) ? (string) $usuario['email'] : '';
                $prefill['telefone'] = isset($usuario['telefone']) ? (string) $usuario['telefone'] : '';
            }
            return $prefill;
        }

        $usuario = $this->usuarioModel->findById($usuarioId);
        if (!$usuario) {
            return $prefill;
        }

        $prefill['nome'] = isset($usuario['nome']) ? (string) $usuario['nome'] : '';
        $prefill['cpf'] = isset($usuario['cpf']) ? (string) $usuario['cpf'] : '';
        $prefill['email'] = isset($usuario['email']) ? (string) $usuario['email'] : '';
        $prefill['telefone'] = isset($usuario['telefone']) ? (string) $usuario['telefone'] : '';

        return $prefill;
    }

    private function buscarUsuarioDaSessaoPorEmail()
    {
        $email = trim((string) Session::get('usuario_email', ''));
        if ($email === '') {
            return null;
        }

        return $this->usuarioModel->findByEmail($email);
    }

    private function isCompraPropriaPedido($tipoPedido)
    {
        $normalizado = function_exists('mb_strtolower')
            ? mb_strtolower(trim((string) $tipoPedido), 'UTF-8')
            : strtolower(trim((string) $tipoPedido));

        return in_array($normalizado, array(
            'propria',
            'própria',
            'compra_propria',
            'compra_própria',
        ), true);
    }

    private function resolverUsuarioPrefillIdDoPedido(array $pedido)
    {
        $pagadorUsuarioId = isset($pedido['pagador_usuario_id']) ? (int) $pedido['pagador_usuario_id'] : 0;
        if ($pagadorUsuarioId > 0) {
            return $pagadorUsuarioId;
        }

        $compradorUsuarioId = isset($pedido['comprador_usuario_id']) ? (int) $pedido['comprador_usuario_id'] : 0;
        if ($compradorUsuarioId > 0) {
            return $compradorUsuarioId;
        }

        return (int) Session::get('usuario_id', 0);
    }

    private function carregarProximasAcoesCheckout(array $pedido)
    {
        if (empty($pedido['itens']) || !is_array($pedido['itens'])) {
            return array();
        }

        $primeiroItem = $pedido['itens'][0];
        $cursoId = !empty($primeiroItem['curso_evento_id']) ? (int) $primeiroItem['curso_evento_id'] : 0;
        if ($cursoId <= 0) {
            return array();
        }

        $turmaId = !empty($primeiroItem['turma_id']) ? (int) $primeiroItem['turma_id'] : null;
        $acoes = $this->instrucoesCursoModel->listForContext($cursoId, $turmaId);
        if (empty($acoes)) {
            return array();
        }

        $acoesVisiveis = array();
        foreach ($acoes as $acao) {
            if (!empty($acao['visivel'])) {
                $acoesVisiveis[] = $acao;
            }
        }

        return $acoesVisiveis;
    }
}


