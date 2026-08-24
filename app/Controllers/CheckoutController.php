<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Helpers;
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
use App\Support\V2ErrorPage;

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
            return V2ErrorPage::notFound(
                $request->path(),
                'Curso não encontrado',
                'O curso solicitado não está disponível para inscrição.'
            );
        }

        if (!empty($curso['curso']['usar_turmas']) && empty($curso['curso']['turma_selecionada'])) {
            Session::flash('errors', array('turma' => 'Selecione uma turma aberta para iniciar a inscricao.'));
            return $this->redirect($this->urlCursoDetalheCheckout($request, $cursoId));
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

            // Destino de continuidade do pagamento construído no BACKEND a partir
            // do pedido já autorizado por situacaoAlunoNoCurso() (mesma checagem
            // comprador/pagador de detalharCheckout). Em modo V2 aponta para a
            // etapa de resumo V2 (/v2/checkout/resumo?pedido_id=N), que revalida a
            // propriedade e faz o único encaminhamento controlado ao pagamento
            // legado. Caminho interno fixo; nunca usa redirect/next/return/URL.
            $pedidoPendenteId = (int) ($situacaoInscricao['pedido_id'] ?? 0);
            $continuarPagamentoUrl = $pedidoPendenteId > 0
                ? $this->urlResumo($request, $pedidoPendenteId)
                : '';

            return $this->renderCheckout($request, 'inscricao', array(
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
                'continuarPagamentoUrl' => $continuarPagamentoUrl,
                'success' => Session::pullFlash('success'),
            ));
        }


        // Cadastro incompleto (CPF/telefone) NÃO interrompe mais a compra: o
        // próprio formulário desta etapa pede esses dados, valida em
        // validateInscricao() no POST e devolve o que faltar ao cadastro via
        // sincronizarDadosDoPagadorNoCadastro(). Redirecionar para /minha-conta
        // aqui tirava o comprador do fluxo V2 sem caminho de volta.
        $errors = Session::pullFlash('errors', array());

        return $this->renderCheckout($request, 'inscricao', array(
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
            return $this->redirect($this->urlCatalogoCheckout($request));
        }

        if ($request->method() === 'POST') {
            return $this->salvarParticipantes($request, $pedidoId);
        }

        $pedido = $this->pedidoService->detalharCheckout($pedidoId, Session::get('usuario_id'));
        if (empty($pedido['pedido'])) {
            return V2ErrorPage::notFound(
                $request->path(),
                'Pedido não encontrado',
                'Este pedido não está disponível ou não pertence à sua conta.'
            );
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
                return $this->redirect($this->urlResumo($request, $pedidoId));
            }

            if (!empty($resultadoCompraPropria['auto_aprovado_zero_valor'])) {
                Session::flash('success', 'Curso gratuito liberado. Você foi direcionado para Minha Página.');
                return $this->redirect('/minha-pagina');
            }

            Session::flash('success', 'Compra própria concluída com participante automático.');
            return $this->redirect($this->urlResumo($request, $pedidoId));
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

        return $this->renderCheckout($request, 'participantes', array(
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
            return $this->redirect($this->urlCatalogoCheckout($request));
        }

        $pedido = $this->pedidoService->detalharCheckout($pedidoId, Session::get('usuario_id'));
        if (empty($pedido['pedido'])) {
            return V2ErrorPage::notFound(
                $request->path(),
                'Pedido não encontrado',
                'Este pedido não está disponível ou não pertence à sua conta.'
            );
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

        return $this->renderCheckout($request, 'resumo', array(
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
            // Destino de continuidade construído no BACKEND. Em modo V2 aponta para
            // a etapa V2 de pagamento (/v2/checkout/pagamento?pedido_id=N), que
            // revalida a propriedade do pedido e apresenta os meios reais. Fora do
            // V2 mantém a rota legada intacta. Caminho interno fixo; nunca usa
            // redirect/next/return/URL do usuário.
            'continuarPagamentoUrl' => $this->emModoV2($request)
                ? $this->urlPagamento($request, $pedidoId)
                : $this->urlContinuarPagamentoLegado($pedidoId),
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
        ));
    }

    // ------------------------------------------------------------------
    // Fase 2.12B — Etapa V2 de PAGAMENTO (apresentação e início).
    //
    // Casca visual V2 sobre o MESMO mecanismo de checkout. Somente leitura:
    // carrega o pedido pela MESMA autorização de detalharCheckout() (sessão +
    // comprador/pagador), apresenta status/total reais e os meios de pagamento
    // já habilitados no backend. Não cria transação, não gera PIX/QR, não
    // confirma pagamento e não altera regra alguma de pedido/preço.
    //
    // Início do AbacatePay: delegado por POST HTML nativo (CSRF automático) ao
    // endpoint real já existente (/aluno/pedidos/pagar/abacatepay), que decide
    // reutilizar ou criar o checkout e redireciona ao provider. PIX/manual:
    // encaminha à rota legada oficial de comprovante. GET nunca escreve.
    // ------------------------------------------------------------------
    public function pagamento(Request $request)
    {
        $pedidoId = (int) $this->pedidoIdFromRequest($request);
        if ($pedidoId <= 0) {
            return $this->redirect($this->urlCatalogoCheckout($request));
        }

        // Autorização real: mesma checagem de propriedade usada em todo o
        // checkout (sessão + comprador/pagador). A rota exige 'auth', então
        // usuário anônimo já foi redirecionado ao login pelo middleware.
        $usuarioId = Session::get('usuario_id');
        $pedido = $this->pedidoService->detalharCheckout($pedidoId, $usuarioId);
        if (empty($pedido['pedido'])) {
            return V2ErrorPage::notFound(
                $request->path(),
                'Pedido não encontrado',
                'Este pedido não está disponível ou não pertence à sua conta.'
            );
        }

        $pedidoGateway = strtolower(trim((string) ($pedido['pedido']['payment_gateway'] ?? '')));
        $abacatepayAtivo = $this->abacatePayService->isEnabled();

        Logger::info('checkout.pagamento.v2', array(
            'pedido_id' => $pedidoId,
            'abacatepay_ativo' => $abacatepayAtivo ? 1 : 0,
            'pedido_gateway_atual' => $pedidoGateway !== '' ? $pedidoGateway : null,
            'status' => (string) ($pedido['pedido']['status'] ?? ''),
        ));

        return $this->renderCheckout($request, 'pagamento', array(
            'title' => 'Pagamento',
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
            // Rotas/ação oficiais atuais construídas no BACKEND (caminhos internos
            // fixos, escapados na view). A V2 apenas as apresenta.
            'abacatepayActionUrl' => '/aluno/pedidos/pagar/abacatepay',
            // Fase 2.12C — no V2 o envio de comprovante passa a ser a etapa V2
            // dedicada; fora do V2, mantém a rota legada intacta.
            'comprovanteUrl' => $this->emModoV2($request)
                ? '/v2/checkout/comprovante?pedido_id=' . $pedidoId
                : '/checkout/comprovante?pedido_id=' . $pedidoId,
            'resumoUrl' => $this->urlResumo($request, $pedidoId),
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
        ));
    }

    public function pagarAbacatepay(Request $request)
    {
        // Endpoint compartilhado (V1 e V2) fora de /v2/checkout/ — emModoV2($request)
        // nunca reconheceria este próprio path. O formulário V2 (checkout-pagamento.php)
        // envia um campo oculto indicando a origem; sem ele, mantém o comportamento V1.
        $emV2 = (bool) $request->input('origem_v2', false);

        if (!Session::get('usuario_id')) {
            Session::flash('errors', array('auth' => 'Faça login para continuar.'));
            return $this->redirect($emV2 ? '/v2/login' : '/login');
        }

        $pedidoId = (int) $request->input('pedido_id', 0);
        if ($pedidoId <= 0) {
            Session::flash('errors', array('pedido' => 'Pedido inválido.'));
            return $this->redirect($emV2 ? '/v2/aluno/' : '/meus-cursos');
        }

        if (!$this->abacatePayService->isEnabled()) {
            Session::flash('errors', array('pagamento' => 'O pagamento online está desativado no momento.'));
            return $this->redirect($this->urlPagamento($request, $pedidoId, $emV2));
        }

        $detalhe = $this->pedidoService->detalharCheckout($pedidoId, Session::get('usuario_id'), true);
        if (empty($detalhe['pedido'])) {
            Session::flash('errors', array('pedido' => 'Você não tem permissão para acessar este pedido.'));
            return $this->redirect($emV2 ? '/v2/aluno/' : '/meus-cursos');
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
                return $this->redirect($this->urlPagamento($request, $pedidoId, $emV2));
            }

            $detalhe = $this->pedidoService->detalharCheckout($pedidoId, Session::get('usuario_id'), true);
            if (empty($detalhe['pedido'])) {
                Session::flash('errors', array('pedido' => 'Não foi possível recarregar o pedido.'));
                return $this->redirect($this->urlPagamento($request, $pedidoId, $emV2));
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
            return $this->redirect($this->urlPagamento($request, $pedidoId, $emV2));
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
            return $this->redirect($this->urlPagamento($request, $pedidoId, $emV2));
        }

        if (empty($checkout['checkout']['url'])) {
            Session::flash('errors', array('pagamento' => 'A URL de pagamento não foi retornada pela AbacatePay.'));
            return $this->redirect($this->urlPagamento($request, $pedidoId, $emV2));
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
        $emV2 = $this->emModoV2($request);

        $pedidoId = (int) $this->pedidoIdFromRequest($request);
        if ($pedidoId <= 0) {
            return $this->redirect($emV2 ? $this->urlCatalogoCheckout($request) : '/cursos');
        }

        // O POST só é processado no fluxo legado (rota /checkout/comprovante). No
        // V2 o envio tem rota dedicada (enviarComprovanteV2); aqui o método V2 é
        // sempre GET/leitura.
        if ($request->method() === 'POST') {
            return $this->enviarComprovante($request, $pedidoId);
        }

        // Autorização real: mesma checagem de propriedade de todo o checkout
        // (sessão + comprador/pagador). No V2 a rota exige 'auth'; anônimo já foi
        // redirecionado ao login pelo middleware.
        $pedido = $this->pedidoService->detalharCheckout($pedidoId, Session::get('usuario_id'));
        if (empty($pedido['pedido'])) {
            return V2ErrorPage::notFound(
                $request->path(),
                'Pedido não encontrado',
                'Este pedido não está disponível ou não pertence à sua conta.'
            );
        }

        if ($emV2) {
            // Casca V2: NÃO redireciona a pedido pago/cancelado para a tela legada
            // de sucesso; a própria view V2 apresenta o estado real permitido.
            return $this->renderCheckout($request, 'comprovante', $this->dadosComprovanteV2($request, $pedido, $pedidoId));
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

    /**
     * Envio V2 do comprovante PIX (Fase 2.12C). Rota fina e dedicada
     * (/v2/checkout/comprovante/enviar). Revalida a propriedade e delega
     * INTEGRALMENTE o arquivo/payload ao mesmo fluxo real do legado
     * (enviarComprovante → ComprovantePixService::enviarUpload). Só difere no
     * destino de retorno, que é uma URL interna fixa V2 (PRG). Nenhuma regra de
     * upload/validação/status/armazenamento é reimplementada aqui.
     */
    public function enviarComprovanteV2(Request $request)
    {
        // O formulário V2 envia pedido_id no CORPO (campo oculto), pois a action
        // não carrega query string. Resolve do corpo primeiro e só então recorre
        // ao localizador padrão (query/sessão). pedido_id é apenas localizador; a
        // propriedade é revalidada em enviarComprovante via detalharCheckout.
        $pedidoId = (int) $request->input('pedido_id', 0);
        if ($pedidoId <= 0) {
            $pedidoId = (int) $this->pedidoIdFromRequest($request);
        }
        if ($pedidoId <= 0) {
            // Sem localizador válido: volta à Minha Área V2 (namespace /v2/), nunca
            // ao catálogo nem ao fluxo V1.
            return $this->redirect('/v2/aluno/?aba=pedidos');
        }

        return $this->enviarComprovante($request, $pedidoId, true);
    }

    /**
     * Monta os dados de apresentação da casca V2 de comprovante a partir do
     * pedido JÁ autorizado por detalharCheckout(). Deriva os estados (enviar,
     * reenvio, em análise, pago, bloqueado) das MESMAS regras reais do backend;
     * não expõe caminho de arquivo, id de arquivo, MIME interno, hash nem dados
     * de gateway.
     */
    private function dadosComprovanteV2(Request $request, array $pedido, $pedidoId)
    {
        $p = $pedido['pedido'];
        $status = strtolower(trim((string) ($p['status'] ?? '')));
        $canSeePix = !empty($pedido['can_see_pix']);

        $pago = in_array($status, array('aprovado', 'pago'), true);
        $bloqueado = in_array($status, array('cancelado', 'expirado', 'reembolsado'), true);
        $aguardando = !empty($p['comprovante_aguardando_aprovacao']);
        $temComprovanteAtual = !empty($p['comprovante_atual']);

        // Gate real de upload (espelha ComprovantePixService::pedidoPodeReceberComprovante).
        $podeReceber = in_array($status, array('aguardando_pagamento', 'comprovante_enviado', 'pendencia', 'aguardando_reenvio'), true);

        // Estado de reenvio: pedido devolvido/pendente com comprovante anterior.
        $reenvio = in_array($status, array('aguardando_reenvio', 'pendencia'), true);

        // Formulário de upload apenas quando o backend realmente aceita e o pedido
        // não está pago/bloqueado/em análise. Em análise (aguardando) não renderiza
        // novo upload — o backend continua sendo a segunda barreira no POST.
        $podeEnviar = $canSeePix && $podeReceber && !$pago && !$bloqueado && !$aguardando;

        // Exige motivo do reenvio quando já há comprovante anterior (mesma regra do
        // service, que rejeita reenvio sem motivo).
        $exigeMotivoReenvio = $podeEnviar && ($temComprovanteAtual || $reenvio);

        // Instrução PIX real já presente no fluxo legado (mesma chave). Exibida só
        // quando o envio é possível e o usuário tem acesso ao pedido.
        $pixKey = 'cpeducacursos@gmail.com';

        return array(
            'title' => 'Enviar comprovante PIX',
            'pedido' => $p,
            'canSeePix' => $canSeePix,
            'loggedIn' => Session::get('usuario_id') !== null,
            'usuarioNome' => Session::get('usuario_nome'),
            'usuarioEmail' => Session::get('usuario_email'),
            'comprovanteStatus' => isset($p['comprovante_atual']['status']) ? (string) $p['comprovante_atual']['status'] : '',
            'pixKey' => $pixKey,
            'pedidoPago' => $pago,
            'pedidoBloqueado' => $bloqueado,
            'comprovanteAguardando' => $aguardando,
            'podeEnviarComprovante' => $podeEnviar,
            'exigeMotivoReenvio' => $exigeMotivoReenvio,
            'estadoReenvio' => $reenvio,
            // Ação e navegação: caminhos internos fixos, escapados na view. O
            // pedido_id vai na query string E no campo oculto (o handler resolve os
            // dois); segue apenas como localizador, com a propriedade revalidada.
            'enviarUrl' => '/v2/checkout/comprovante/enviar?pedido_id=' . (int) $pedidoId,
            'pagamentoUrl' => $this->urlPagamento($request, $pedidoId),
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
        );
    }

    /**
     * Confirmação V2 do envio de comprovante (pós-POST, somente leitura). Mantém o
     * aluno no namespace /v2/ com uma tela institucional própria. Consulta os dados
     * REAIS do pedido autenticado (detalharCheckout revalida a propriedade); pedido
     * inexistente/alheio → 404 sem dados. Não permite novo upload nem altera status.
     */
    public function comprovanteEnviadoV2(Request $request)
    {
        $pedidoId = (int) $this->pedidoIdFromRequest($request);
        if ($pedidoId <= 0) {
            return $this->redirect('/v2/aluno/?aba=pedidos');
        }

        $pedido = $this->pedidoService->detalharCheckout($pedidoId, Session::get('usuario_id'));
        if (empty($pedido['pedido'])) {
            return V2ErrorPage::notFound(
                $request->path(),
                'Pedido não encontrado',
                'Este pedido não está disponível ou não pertence à sua conta.'
            );
        }

        $p = $pedido['pedido'];
        $status = strtolower(trim((string) ($p['status'] ?? '')));

        // Pedido já pago/aprovado: não há comprovante em análise a confirmar → Minha Área.
        if (in_array($status, array('aprovado', 'pago'), true)) {
            return $this->redirect('/v2/aluno/?aba=pedidos');
        }

        // Sem comprovante real registrado: evita confirmar algo que não aconteceu.
        // Encaminha à etapa de envio V2 (mesmo pedido), sem expor dados fictícios.
        if (empty($p['comprovante_atual'])) {
            return $this->redirect('/v2/checkout/comprovante?pedido_id=' . $pedidoId);
        }

        return $this->renderCheckout($request, 'comprovante-enviado', $this->dadosComprovanteEnviadoV2($p, $pedidoId));
    }

    /**
     * Dados de apresentação da confirmação V2 a partir do pedido JÁ autorizado.
     * Expõe apenas: código público, curso(s), total, status "Em análise" e a
     * data/hora real do envio. Sem caminho de arquivo, id de arquivo, gateway,
     * PIX automático ou detalhe técnico de armazenamento.
     */
    private function dadosComprovanteEnviadoV2(array $p, $pedidoId)
    {
        $cursos = array();
        if (isset($p['itens']) && is_array($p['itens'])) {
            foreach ($p['itens'] as $item) {
                $nome = trim((string) ($item['curso_nome'] ?? ''));
                if ($nome !== '' && !in_array($nome, $cursos, true)) {
                    $cursos[] = $nome;
                }
            }
        }

        $enviadoEmRaw = isset($p['comprovante_atual']['enviado_em']) ? (string) $p['comprovante_atual']['enviado_em'] : '';
        $enviadoEm = '';
        if ($enviadoEmRaw !== '') {
            $ts = strtotime($enviadoEmRaw);
            $enviadoEm = $ts ? date('d/m/Y H:i', $ts) : '';
        }

        return array(
            'title' => 'Comprovante enviado com sucesso',
            'pedidoCodigo' => (string) ($p['codigo'] ?? ''),
            'cursos' => $cursos,
            'totalFormatado' => 'R$ ' . number_format((float) ($p['total'] ?? 0), 2, ',', '.'),
            'statusLabel' => 'Em análise',
            'enviadoEm' => $enviadoEm,
            // Caminhos internos fixos, escapados na view.
            'minhaAreaHref' => '/v2/aluno/?aba=pedidos',
            'success' => Session::pullFlash('success'),
        );
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
            return V2ErrorPage::notFound(
                $request->path(),
                'Pedido não encontrado',
                'Este pedido não está disponível ou não pertence à sua conta.'
            );
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
            return $this->redirect(Helpers::urlAcessoCursoAluno($situacaoInscricao));
        }

        if ((string) $situacaoInscricao['status_fluxo'] === 'pendente_pagamento') {
            $destinoPagamento = $this->emModoV2($request)
                ? $this->urlResumo($request, (int) $situacaoInscricao['pedido_id'])
                : (!empty($situacaoInscricao['checkout_url'])
                    ? (string) $situacaoInscricao['checkout_url']
                    : '/checkout/resumo?pedido_id=' . (int) $situacaoInscricao['pedido_id']);

            Session::flash('success', 'Você possui uma inscrição pendente para este curso. Continue o pagamento para concluir sua matrícula.');

            return $this->redirect($destinoPagamento);
        }

        $errors = $this->validateInscricao($request);
        if (!empty($errors)) {
            Session::flash('errors', $errors);
            Session::flash('old_input', $request->all());
            $cursoIdErro = (int) $request->input('curso_evento_id', 0);
            $turmaIdErro = (int) $request->input('turma_id', 0);
            return $this->redirect($this->urlInscricao($request, $cursoIdErro, $turmaIdErro));
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
        $pagadorCidadeSubmetida = trim((string) $request->input('pagador_cidade', ''));
        $pagadorEstadoSubmetida = trim((string) $request->input('pagador_estado', ''));
        $pagadorCidade = $pagadorCidadeSubmetida;
        if ($pagadorCidade === '' && !empty($pagadorPrefill['cidade'])) {
            $pagadorCidade = (string) $pagadorPrefill['cidade'];
        }
        $pagadorEstado = $pagadorEstadoSubmetida;
        if ($pagadorEstado === '' && !empty($pagadorPrefill['estado'])) {
            $pagadorEstado = (string) $pagadorPrefill['estado'];
        }

        // Telefone/cidade/estado digitados no checkout: se o cadastro do aluno
        // ainda não tem essa informação, aproveita e já preenche, para as
        // próximas compras.
        $this->sincronizarDadosDoPagadorNoCadastro(
            (int) Session::get('usuario_id', 0),
            $pagadorTelefone,
            $pagadorCidadeSubmetida,
            $pagadorEstadoSubmetida
        );

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
            Session::flash('old_input', $request->all());
            return $this->redirect($this->urlInscricao($request, $cursoId, $turmaId));
        }

        Session::put('checkout_pedido_id', $resultado['pedido_id']);

        if ($this->isCompraPropriaPedido($tipoPedido)) {
            $resultadoCompraPropria = $this->concluirCheckoutCompraPropria((int) $resultado['pedido_id'], $request);
            if (empty($resultadoCompraPropria['ok'])) {
                Session::flash('errors', array('pedido' => isset($resultadoCompraPropria['message']) ? $resultadoCompraPropria['message'] : 'Não foi possivel concluir a compra propria.'));
                return $this->redirect($this->urlResumo($request, (int) $resultado['pedido_id']));
            }

            Session::flash('success', 'Compra própria iniciada com sucesso. O participante foi gerado automaticamente.');
            return $this->redirect($this->urlResumo($request, (int) $resultado['pedido_id']));
        }

        return $this->redirect($this->urlParticipantes($request, (int) $resultado['pedido_id']));
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
            return $this->redirect($this->urlCatalogoCheckout($request));
        }

        if ($this->isCompraPropriaPedido(isset($pedido['pedido']['tipo_pedido']) ? $pedido['pedido']['tipo_pedido'] : '')) {
            $resultadoCompraPropria = $this->concluirCheckoutCompraPropria($pedidoId, $request, $pedido['pedido']);
            if (empty($resultadoCompraPropria['ok'])) {
                Session::flash('errors', array('pedido' => isset($resultadoCompraPropria['message']) ? $resultadoCompraPropria['message'] : 'Não foi possivel concluir a compra propria.'));
                return $this->redirect($this->urlResumo($request, $pedidoId));
            }

            if (!empty($resultadoCompraPropria['auto_aprovado_zero_valor'])) {
                Session::flash('success', 'Curso gratuito liberado. Você foi direcionado para Minha Página.');
                return $this->redirect('/minha-pagina');
            }

            Session::flash('success', 'Compra própria concluída com participante automático.');
            return $this->redirect($this->urlResumo($request, $pedidoId));
        }

        $participantes = $request->input('participantes', array());
        if (!is_array($participantes)) {
            $participantes = array();
        }

        $errors = $this->validateParticipantes($participantes);
        if (!empty($errors)) {
            Session::flash('errors', $errors);
            return $this->redirect($this->urlParticipantes($request, $pedidoId));
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
            return $this->redirect($this->urlParticipantes($request, $pedidoId));
        }

        $this->inscricaoService->gerarDoPedido($pedidoId, Session::get('usuario_id'), $request->ip(), $request->userAgent());
        $finalizacao = $this->pedidoService->finalizarCheckout($pedidoId, Session::get('usuario_id'), $request->ip(), $request->userAgent());
        if (empty($finalizacao['ok'])) {
            Session::flash('errors', array('pedido' => isset($finalizacao['message']) ? $finalizacao['message'] : 'Não foi possivel finalizar o pedido.'));
            return $this->redirect($this->urlResumo($request, $pedidoId));
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
        return $this->redirect($this->urlResumo($request, $pedidoId));
    }

    private function enviarComprovante(Request $request, $pedidoId, $v2 = false)
    {
        // Destinos de retorno: URLs internas fixas. No V2, sempre a própria etapa
        // de comprovante V2 (PRG); fora do V2, o fluxo legado inalterado. Nenhum
        // destino vem de redirect/next/return/URL do usuário.
        $comprovanteUrl = $v2
            ? '/v2/checkout/comprovante?pedido_id=' . (int) $pedidoId
            : '/checkout/comprovante?pedido_id=' . (int) $pedidoId;
        // Envio bem-sucedido: no V2, tela de confirmação própria (namespace /v2/);
        // fora do V2, o sucesso legado inalterado.
        $enviadoUrl = $v2
            ? '/v2/checkout/comprovante/enviado?pedido_id=' . (int) $pedidoId
            : '/checkout/sucesso?pedido_id=' . (int) $pedidoId;
        // Pedido já pago/aprovado (sem comprovante a enviar): no V2, Minha Área;
        // fora do V2, o sucesso legado.
        $pagoUrl = $v2
            ? '/v2/aluno/?aba=pedidos'
            : '/checkout/sucesso?pedido_id=' . (int) $pedidoId;

        if (!Session::get('usuario_id')) {
            Session::flash('errors', array('auth' => 'Faça login para enviar o comprovante.'));
            return $this->redirect($v2 ? '/v2/login' : '/login');
        }

        $pedido = $this->pedidoService->detalharCheckout($pedidoId, Session::get('usuario_id'));
        if (empty($pedido['pedido'])) {
            Session::flash('errors', array('pedido' => 'Você nao tem permissao para acessar este pedido.'));
            // Falha de autorização no V2 permanece no namespace /v2/ (Minha Área),
            // nunca catálogo nem V1.
            return $this->redirect($v2 ? '/v2/aluno/?aba=pedidos' : '/cursos');
        }

        if (in_array((string) $pedido['pedido']['status'], array('aprovado', 'pago'), true)) {
            Session::flash('success', 'Pedido aprovado automaticamente. Não há comprovante PIX para enviar.');
            return $this->redirect($pagoUrl);
        }

        if (!isset($_FILES['comprovante']) || empty($_FILES['comprovante']['tmp_name'])) {
            Session::flash('errors', array('comprovante' => 'Selecione um arquivo de comprovante.'));
            return $this->redirect($comprovanteUrl);
        }

        if ((int) $_FILES['comprovante']['size'] <= 0) {
            Session::flash('errors', array('comprovante' => 'O arquivo enviado nao e valido.'));
            return $this->redirect($comprovanteUrl);
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
            return $this->redirect($comprovanteUrl);
        }

        if (empty($resultado['ok'])) {
            Session::flash('errors', array('comprovante' => isset($resultado['message']) ? $resultado['message'] : 'Não foi possivel enviar o comprovante.'));
            return $this->redirect($comprovanteUrl);
        }

        Session::flash('success', $v2
            ? 'Comprovante enviado com sucesso. Aguarde a aprovação do administrador.'
            : 'Comprovante enviado. Agora acompanhe em Meus Cursos.');
        return $this->redirect($enviadoUrl);
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

        // Telefone do pagador é opcional: `pedidos.pagador_telefone` aceita NULL
        // e o gateway não usa esse dado. Exigir aqui só custava venda.

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

    /**
     * Completa o cadastro do aluno com os dados digitados no checkout.
     *
     * Só preenche campo que está VAZIO no cadastro — nunca sobrescreve dado já
     * informado pelo aluno. Como o telefone é opcional no cadastro (/cadastro)
     * mas obrigatório para o pedido, é aqui que ele volta para o perfil, em vez
     * de barrar a compra e mandar o comprador editar a conta.
     */
    private function sincronizarDadosDoPagadorNoCadastro($usuarioId, $telefone, $cidade, $estado)
    {
        $usuarioId = (int) $usuarioId;
        if ($usuarioId <= 0) {
            return;
        }

        $usuario = $this->usuarioModel->findById($usuarioId);
        if (!$usuario) {
            return;
        }

        $atualizacao = array();

        $telefone = preg_replace('/\D+/', '', (string) $telefone);
        if ($telefone !== '' && trim((string) (isset($usuario['telefone']) ? $usuario['telefone'] : '')) === '') {
            $atualizacao['telefone'] = $telefone;
        }

        $cidade = trim((string) $cidade);
        $estado = strtoupper(trim((string) $estado));
        if ($cidade !== '' && preg_match('/^[A-Z]{2}$/', $estado)
            && empty($usuario['cidade']) && empty($usuario['estado'])) {
            $atualizacao['cidade'] = $cidade;
            $atualizacao['estado'] = $estado;
        }

        if (empty($atualizacao)) {
            return;
        }

        $this->usuarioModel->updateProfile($usuarioId, array_merge(array(
            'nome' => $usuario['nome'],
            'email' => $usuario['email'],
            'cpf' => $usuario['cpf'],
            'telefone' => $usuario['telefone'],
            'cidade' => isset($usuario['cidade']) ? $usuario['cidade'] : null,
            'estado' => isset($usuario['estado']) ? $usuario['estado'] : null,
        ), $atualizacao));
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

    // ------------------------------------------------------------------
    // Variante de APRESENTAÇÃO V2 (Fase 2.12A) — pré-pagamento.
    //
    // É apenas uma casca visual V2 sobre as MESMAS ações deste controller.
    // A "origem" V2 é detectada estritamente pelo CAMINHO interno da rota
    // (`/v2/checkout...`), nunca por parâmetro do usuário/URL/redirect/next/
    // return. Quando ausente (rotas legadas), o comportamento é idêntico ao
    // atual: as views e os destinos legados ficam exatamente como estão.
    // Nenhuma regra de pedido/preço/desconto/participante é alterada aqui.
    // ------------------------------------------------------------------

    /** Entrada V2: redireciona para a inscrição V2 preservando apenas IDs. */
    public function entradaCheckoutV2(Request $request)
    {
        $cursoId = (int) $request->query('curso_id', 0);
        $turmaId = (int) $request->query('turma_id', 0);
        // Sem curso válido no contexto → catálogo V2 (caminho interno fixo).
        // Evita introduzir curso_id=0 e a 404 subsequente; não cria pedido nem
        // usa redirect/next/return/URL do usuário. A rota /v2/checkout/inscricao
        // preserva seu próprio comportamento seguro para curso_id inválido.
        if ($cursoId <= 0) {
            return $this->redirect($this->urlCatalogoCheckout($request));
        }
        return $this->redirect($this->urlInscricao($request, $cursoId, $turmaId));
    }

    private function emModoV2(Request $request)
    {
        // Correspondência estrita de segmento: apenas o próprio /v2/checkout ou
        // subcaminhos reais dentro de /v2/checkout/. Evita falsos positivos por
        // prefixo ambíguo (ex.: /v2/checkout-falso, /v2/checkoutx). Request::path()
        // já normaliza removendo a barra final. Nunca usa origem/redirect/next/URL.
        $path = (string) $request->path();
        return $path === '/v2/checkout' || strpos($path, '/v2/checkout/') === 0;
    }

    /** Renderiza a view legada OU a casca V2 (sem layout legado), conforme o modo. */
    private function renderCheckout(Request $request, $nome, array $data)
    {
        if ($this->emModoV2($request)) {
            $data = array_merge($this->dadosLayoutCheckoutV2(), $data);
            return new Response(View::render('v2/checkout/' . $nome, $data, false));
        }

        return $this->view('checkout/' . $nome, $data);
    }

    private function urlInscricao(Request $request, $cursoId, $turmaId = 0)
    {
        $base = $this->emModoV2($request) ? '/v2/checkout/inscricao' : '/inscricao';
        return $base . '?curso_id=' . (int) $cursoId . ((int) $turmaId > 0 ? '&turma_id=' . (int) $turmaId : '');
    }

    private function urlParticipantes(Request $request, $pedidoId)
    {
        $base = $this->emModoV2($request) ? '/v2/checkout/participantes' : '/checkout/participantes';
        return $base . '?pedido_id=' . (int) $pedidoId;
    }

    private function urlResumo(Request $request, $pedidoId)
    {
        $base = $this->emModoV2($request) ? '/v2/checkout/resumo' : '/checkout/resumo';
        return $base . '?pedido_id=' . (int) $pedidoId;
    }

    private function urlPagamento(Request $request, $pedidoId, $forcarModoV2 = null)
    {
        // Só existe a etapa dedicada de pagamento no fluxo V2. Fora do V2, o
        // destino oficial de pagamento continua sendo o resumo legado.
        // $forcarModoV2 permite decidir o modo sem depender do path da própria
        // requisição — necessário em endpoints compartilhados (ex.:
        // /aluno/pedidos/pagar/abacatepay) que nunca ficam sob /v2/checkout/.
        $emV2 = $forcarModoV2 !== null ? (bool) $forcarModoV2 : $this->emModoV2($request);
        $base = $emV2 ? '/v2/checkout/pagamento' : '/checkout/resumo';
        return $base . '?pedido_id=' . (int) $pedidoId;
    }

    private function urlCatalogoCheckout(Request $request)
    {
        return $this->emModoV2($request) ? '/v2/catalogo/' : '/cursos';
    }

    private function urlCursoDetalheCheckout(Request $request, $cursoId)
    {
        return $this->emModoV2($request)
            ? '/v2/curso/?curso_id=' . (int) $cursoId
            : '/cursos/detalhe?curso_id=' . (int) $cursoId;
    }

    /** Rota oficial atual para continuar o checkout/pagamento do rascunho. */
    private function urlContinuarPagamentoLegado($pedidoId)
    {
        return '/checkout/resumo?pedido_id=' . (int) $pedidoId;
    }

    private function dadosLayoutCheckoutV2()
    {
        $usuarioId = (int) Session::get('usuario_id', 0);
        $usuarioNome = trim((string) Session::get('usuario_nome', ''));
        $sessionPerfis = Session::get('usuario_perfis', array());
        $hasAdmin = (bool) Session::get('usuario_admin') || (bool) Session::get('is_admin') || in_array('admin', $sessionPerfis, true);
        $hasProf = (bool) Session::get('usuario_professor') || (bool) Session::get('is_professor') || in_array('professor', $sessionPerfis, true);

        $areaHref = '/v2/aluno';
        if ($hasAdmin) {
            $areaHref = '/admin';
        } elseif ($hasProf) {
            $areaHref = '/professor/dashboard';
        }

        $primeiro = 'aluno';
        if ($usuarioNome !== '') {
            $partes = preg_split('/\s+/', $usuarioNome);
            $primeiro = ($partes && !empty($partes[0])) ? (string) $partes[0] : $usuarioNome;
        }

        return array(
            'loggedIn' => $usuarioId > 0,
            'usuarioNome' => $usuarioNome,
            'usuarioPrimeiroNome' => $usuarioId > 0 ? $primeiro : '',
            'areaHref' => $areaHref,
            'loginHref' => '/v2/login',
            'registerHref' => '/v2/cadastro',
            'catalogoHref' => '/v2/catalogo/',
            'categoriasHref' => '/categorias',
            'certificadosHref' => '/v2/certificados/validar',
            'sobreHref' => '/sobre',
            'contatoHref' => '/contato',
            'homeHref' => '/v2/',
        );
    }
}


