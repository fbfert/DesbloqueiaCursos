<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\CheckoutRapidoConfigService;
use App\Services\CheckoutRapidoService;
use App\Services\ConfiguracaoGlobalService;
use App\Support\OrigemTrafego;
use App\Support\Whatsapp;
use Exception;

/**
 * Checkout rapido — tela unica, sem senha, sem troca de pagina.
 *
 * Rotas proprias, isoladas do checkout antigo. Com a feature flag desligada
 * todas respondem 404, e o unico caminho de compra continua sendo o de hoje.
 */
class CheckoutRapidoController extends Controller
{
    private $service;
    private $config;
    private $configGlobal;

    public function __construct()
    {
        $this->service = new CheckoutRapidoService();
        $this->config = new CheckoutRapidoConfigService();
        $this->configGlobal = new ConfiguracaoGlobalService();
    }

    /**
     * GET /comprar?curso_id=N[&turma_id=N]
     * A tela. Um formulario, tres campos, um botao.
     */
    public function mostrar(Request $request)
    {
        $cursoId = (int) $request->query('curso_id', 0);
        $turmaId = (int) $request->query('turma_id', 0);

        if (!$this->config->ativo($cursoId)) {
            return $this->naoEncontrado();
        }

        OrigemTrafego::capturar($request);

        $validacao = $this->service->validarCurso($cursoId, $turmaId ?: null);
        if (empty($validacao['ok'])) {
            Session::flash('errors', array('curso' => $validacao['message']));
            return $this->redirect('/cursos/detalhe?curso_id=' . $cursoId);
        }

        $curso = $validacao['curso'];
        $turma = $validacao['turma'];
        $origem = OrigemTrafego::atual();

        $this->service->funil()->iniciado(
            $cursoId,
            session_id(),
            $origem,
            $request->ip(),
            $request->userAgent()
        );

        // Aluno logado ja tem os dados: prefill, mas os campos seguem editaveis.
        $usuarioId = (int) Session::get('usuario_id', 0);
        $prefill = array('email' => '', 'whatsapp' => '', 'cpf' => '');
        if ($usuarioId > 0) {
            $prefill['email'] = (string) Session::get('usuario_email', '');
            $prefill['cpf'] = (string) Session::get('usuario_cpf', '');
        }

        return $this->view('checkout-rapido/comprar', array(
            'title' => 'Garantir minha vaga · ' . ($curso['nome'] ?? 'Curso'),
            'curso' => $curso,
            'turma' => $turma,
            'valor' => $validacao['valor'],
            'prefill' => $prefill,
            'loggedIn' => $usuarioId > 0,
            'textoLgpd' => $this->config->textoLgpd(),
            'pollingSegundos' => $this->config->pollingIntervaloSegundos(),
            'origem' => $origem,
            'frontend_template' => $this->configGlobal->templateVisualPortal(),
            'useLayout' => false,
        ));
    }

    /**
     * POST /comprar/pix  (JSON)
     * Cria/reaproveita o usuario, abre o pedido e devolve o que a tela precisa
     * para exibir o Pix — sem recarregar a pagina.
     */
    public function gerarPix(Request $request)
    {
        $cursoId = (int) $request->input('curso_evento_id', 0);
        $turmaId = (int) $request->input('turma_id', 0);

        if (!$this->config->ativo($cursoId)) {
            return $this->json(array('ok' => false, 'message' => 'Indisponível.'), 404);
        }

        $input = array(
            'email' => (string) $request->input('email', ''),
            'whatsapp' => (string) $request->input('whatsapp', ''),
            'cpf' => (string) $request->input('cpf', ''),
        );

        $errors = $this->service->validar($input);
        if (!empty($errors)) {
            return $this->json(array('ok' => false, 'errors' => $errors), 422);
        }

        $validacaoCurso = $this->service->validarCurso($cursoId, $turmaId ?: null);
        if (empty($validacaoCurso['ok'])) {
            return $this->json(array('ok' => false, 'message' => $validacaoCurso['message']), 422);
        }

        $turmaEfetivaId = !empty($validacaoCurso['turma']['id']) ? (int) $validacaoCurso['turma']['id'] : 0;
        $whatsappE164 = Whatsapp::normalizar($input['whatsapp']);

        try {
            $usuario = $this->service->resolverUsuario($input['email'], $whatsappE164, $input['cpf']);

            if (empty($usuario['ok'])) {
                return $this->json(array(
                    'ok' => false,
                    'errors' => array($usuario['campo'] => $usuario['message']),
                ), 422);
            }

            $resultado = $this->service->iniciarPedido(array(
                'curso_evento_id' => $cursoId,
                'turma_id' => $turmaEfetivaId,
                'usuario_id' => $usuario['usuario_id'],
                'email' => $input['email'],
                'whatsapp' => $whatsappE164,
                'cpf' => $input['cpf'],
                'origem' => OrigemTrafego::atual(),
            ), $request->ip(), $request->userAgent());

            if (!empty($resultado['ja_matriculado'])) {
                // NAO redirecionamos para a area do aluno e NAO abrimos sessao.
                // Quem preencheu o formulario provou apenas que sabe um CPF e um
                // e-mail — isso nao pode valer como autenticacao, senao qualquer
                // um entraria na conta alheia. A pessoa e avisada na propria tela
                // e entra pelo caminho normal de login.
                return $this->json(array(
                    'ok' => false,
                    'ja_matriculado' => true,
                    'message' => 'Você já tem este curso. Entre na sua conta para continuar de onde parou.',
                    'login_url' => '/login',
                ), 409);
            }

            if (empty($resultado['ok'])) {
                return $this->json(array('ok' => false, 'message' => $resultado['message']), 422);
            }

            $pedido = $resultado['pedido'];
            $pedidoId = (int) $resultado['pedido_id'];

            // Curso gratuito: nao ha Pix a gerar, o acesso ja saiu liberado.
            if (!empty($resultado['auto_aprovado_zero_valor'])) {
                return $this->json(array(
                    'ok' => true,
                    'gratuito' => true,
                    'pedido_id' => $pedidoId,
                    'message' => 'Sua vaga está garantida. Enviamos o acesso para o seu e-mail.',
                ));
            }

            $this->service->funil()->pixGerado(
                $pedidoId,
                $usuario['usuario_id'],
                array(
                    'curso_evento_id' => $cursoId,
                    'valor' => (float) $pedido['total'],
                    'usuario_novo' => !empty($usuario['novo']),
                    'origem' => OrigemTrafego::atual(),
                    'tentativa' => !empty($resultado['reaproveitado']) ? 2 : 1,
                ),
                $request->ip(),
                $request->userAgent()
            );

            // A cobranca Pix propriamente dita entra aqui na proxima etapa.
            // Ate la o pedido ja existe, ja esta em aguardando_pagamento e ja
            // aparece na tela de recuperacao de pedidos do admin.
            return $this->json(array(
                'ok' => true,
                'pedido_id' => $pedidoId,
                'pedido_codigo' => (string) $pedido['codigo'],
                'valor' => (float) $pedido['total'],
                'email_conta' => $usuario['email_conta'],
                'email_divergente' => !empty($usuario['email_divergente']),
                'pix' => null,
                'pendente_integracao' => true,
                'message' => 'Pedido aberto. A cobrança Pix será exibida aqui.',
            ));
        } catch (Exception $exception) {
            Logger::error('checkout_rapido.gerar_pix.falhou', array(
                'curso_evento_id' => $cursoId,
                'message' => $exception->getMessage(),
            ));

            return $this->json(array(
                'ok' => false,
                'message' => 'Não foi possível gerar seu Pix agora. Tente novamente em instantes.',
            ), 500);
        }
    }

    /**
     * GET /comprar/status?pedido_id=N  (JSON)
     * Consultado pelo polling da tela. Le SEMPRE o nosso banco: o cliente
     * nunca decide se pagou. Nao exige sessao — o pedido e identificado pelo
     * par (id, codigo), e a resposta nao expoe dado pessoal.
     */
    public function status(Request $request)
    {
        $pedidoId = (int) $request->query('pedido_id', 0);
        $codigo = trim((string) $request->query('codigo', ''));

        if ($pedidoId <= 0 || $codigo === '') {
            return $this->json(array('ok' => false, 'message' => 'Consulta inválida.'), 422);
        }

        $pedido = $this->service->pedidoParaPolling($pedidoId, $codigo);
        if (!$pedido) {
            return $this->json(array('ok' => false, 'message' => 'Pedido não encontrado.'), 404);
        }

        return $this->json($pedido);
    }

    private function naoEncontrado()
    {
        return new Response(View::render('errors/404', array(
            'title' => 'Página não encontrada',
        )), 404);
    }
}
