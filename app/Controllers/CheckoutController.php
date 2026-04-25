<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Core\Validator;
use App\Services\ComprovantePixService;
use App\Services\InscricaoService;
use App\Services\PedidoService;
use App\Services\CursoService;

class CheckoutController extends Controller
{
    private $pedidoService;
    private $inscricaoService;
    private $comprovanteService;
    private $cursoService;

    public function __construct()
    {
        $this->pedidoService = new PedidoService();
        $this->inscricaoService = new InscricaoService();
        $this->comprovanteService = new ComprovantePixService();
        $this->cursoService = new CursoService();
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

        return $this->view('checkout/inscricao', array(
            'title' => 'Inscricao',
            'curso' => $curso['curso'],
            'loggedIn' => Session::get('usuario_id') !== null,
            'usuarioNome' => Session::get('usuario_nome'),
            'usuarioEmail' => Session::get('usuario_email'),
            'errors' => Session::pullFlash('errors', array()),
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

        return $this->view('checkout/participantes', array(
            'title' => 'Participantes',
            'pedido' => $pedido['pedido'],
            'quantidade' => $quantidade,
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

        return $this->view('checkout/resumo', array(
            'title' => 'Resumo do pedido',
            'pedido' => $pedido['pedido'],
            'canSeePix' => !empty($pedido['can_see_pix']),
            'loggedIn' => Session::get('usuario_id') !== null,
            'usuarioNome' => Session::get('usuario_nome'),
            'usuarioEmail' => Session::get('usuario_email'),
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
        ));
    }

    public function cupom(Request $request)
    {
        $pedidoId = (int) $this->pedidoIdFromRequest($request);
        if ($pedidoId <= 0) {
            return $this->redirect('/cursos');
        }

        $pedidoAcesso = $this->pedidoService->detalharCheckout($pedidoId, Session::get('usuario_id'));
        if (empty($pedidoAcesso['pedido'])) {
            Session::flash('errors', array('pedido' => 'Voce nao tem permissao para acessar este pedido.'));
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
            Session::flash('errors', array('cupom_codigo' => isset($resultado['message']) ? $resultado['message'] : 'Nao foi possivel aplicar o cupom.'));
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
        $pedido = $pedidoId > 0 ? $this->pedidoService->detalharCheckout($pedidoId, Session::get('usuario_id')) : array('pedido' => null);

        return $this->view('checkout/sucesso', array(
            'title' => 'Pedido recebido',
            'pedido' => $pedido['pedido'],
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

        $errors = $this->validateInscricao($request);
        if (!empty($errors)) {
            Session::flash('errors', $errors);
            $cursoIdErro = (int) $request->input('curso_evento_id', 0);
            $turmaIdErro = (int) $request->input('turma_id', 0);
            return $this->redirect('/cursos/detalhe?curso_id=' . $cursoIdErro . ($turmaIdErro ? '&turma_id=' . $turmaIdErro : ''));
        }

        $cursoId = (int) $request->input('curso_evento_id', 0);
        $turmaId = (int) $request->input('turma_id', 0);
        $quantidade = max(1, (int) $request->input('quantidade', 1));
        $pagadorNome = trim((string) $request->input('pagador_nome', ''));
        if ($pagadorNome === '' && Session::get('usuario_nome')) {
            $pagadorNome = Session::get('usuario_nome');
        }
        $pagadorEmail = trim((string) $request->input('pagador_email', ''));
        if ($pagadorEmail === '' && Session::get('usuario_email')) {
            $pagadorEmail = Session::get('usuario_email');
        }

        $resultado = $this->pedidoService->criarCheckoutDraft(array(
            'curso_evento_id' => $cursoId,
            'turma_id' => $turmaId ?: null,
            'quantidade' => $quantidade,
            'tipo_pedido' => $request->input('tipo_pedido', 'propria'),
            'comprador_usuario_id' => Session::get('usuario_id'),
            'pagador_usuario_id' => Session::get('usuario_id'),
            'pagador_nome' => $pagadorNome,
            'pagador_cpf' => $request->input('pagador_cpf'),
            'pagador_email' => $pagadorEmail,
            'pagador_telefone' => $request->input('pagador_telefone'),
            'pagador_cidade' => $request->input('pagador_cidade'),
            'pagador_estado' => $request->input('pagador_estado'),
            'pagador_empresa_nome' => $request->input('pagador_empresa_nome'),
            'pagador_empresa_documento' => $request->input('pagador_empresa_documento'),
            'observacoes_publicas' => $request->input('observacoes_publicas'),
            'canal_origem' => 'checkout',
        ), Session::get('usuario_id'), $request->ip(), $request->userAgent());

        if (empty($resultado['ok'])) {
            Session::flash('errors', array('pedido' => isset($resultado['message']) ? $resultado['message'] : 'Nao foi possivel iniciar o checkout.'));
            return $this->redirect('/cursos/detalhe?curso_id=' . $cursoId . ($turmaId ? '&turma_id=' . $turmaId : ''));
        }

        Session::put('checkout_pedido_id', $resultado['pedido_id']);

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
            Session::flash('errors', array('pedido' => 'Voce nao tem permissao para alterar este pedido.'));
            return $this->redirect('/cursos');
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
            Session::flash('errors', array('participantes' => isset($resultado['message']) ? $resultado['message'] : 'Nao foi possivel salvar os participantes.'));
            return $this->redirect('/checkout/participantes?pedido_id=' . $pedidoId);
        }

        $this->inscricaoService->gerarDoPedido($pedidoId, Session::get('usuario_id'), $request->ip(), $request->userAgent());
        $finalizacao = $this->pedidoService->finalizarCheckout($pedidoId, Session::get('usuario_id'), $request->ip(), $request->userAgent());
        if (empty($finalizacao['ok'])) {
            Session::flash('errors', array('pedido' => isset($finalizacao['message']) ? $finalizacao['message'] : 'Nao foi possivel finalizar o pedido.'));
            return $this->redirect('/checkout/resumo?pedido_id=' . $pedidoId);
        }

        Session::flash('success', 'Participantes salvos. Revise o pedido e siga para o comprovante PIX.');
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
            Session::flash('errors', array('pedido' => 'Voce nao tem permissao para acessar este pedido.'));
            return $this->redirect('/cursos');
        }

        if (!isset($_FILES['comprovante']) || empty($_FILES['comprovante']['tmp_name'])) {
            Session::flash('errors', array('comprovante' => 'Selecione um arquivo de comprovante.'));
            return $this->redirect('/checkout/comprovante?pedido_id=' . $pedidoId);
        }

        if ((int) $_FILES['comprovante']['size'] <= 0) {
            Session::flash('errors', array('comprovante' => 'O arquivo enviado nao e valido.'));
            return $this->redirect('/checkout/comprovante?pedido_id=' . $pedidoId);
        }

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

        if (empty($resultado['ok'])) {
            Session::flash('errors', array('comprovante' => isset($resultado['message']) ? $resultado['message'] : 'Nao foi possivel enviar o comprovante.'));
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

        if ((int) $request->input('quantidade', 0) <= 0) {
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
}
