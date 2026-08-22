<?php

namespace App\Services;

use App\Core\Logger;
use App\Core\Validator;
use App\Models\CursoEvento;
use App\Models\ParticipantePedido;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\Turma;
use App\Models\Usuario;
use App\Support\OrigemTrafego;
use App\Support\Whatsapp;
use Exception;

/**
 * Checkout rapido: uma tela, tres campos (e-mail, WhatsApp, CPF), sem senha.
 *
 * Este servico cobre da submissao do formulario ate o pedido em
 * `aguardando_pagamento` com a origem de trafego gravada. A geracao da
 * cobranca Pix e etapa seguinte e vive em outro servico.
 *
 * Reaproveita integralmente PedidoService e InscricaoService — os mesmos
 * usados pelo checkout antigo. Nada aqui altera aquele fluxo.
 */
class CheckoutRapidoService
{
    private $usuarioModel;
    private $pedidoModel;
    private $pedidoItemModel;
    private $participanteModel;
    private $cursoModel;
    private $turmaModel;
    private $pedidoService;
    private $inscricaoService;
    private $cursoService;
    private $funil;
    private $config;

    public function __construct()
    {
        $this->usuarioModel = new Usuario();
        $this->pedidoModel = new Pedido();
        $this->pedidoItemModel = new PedidoItem();
        $this->participanteModel = new ParticipantePedido();
        $this->cursoModel = new CursoEvento();
        $this->turmaModel = new Turma();
        $this->pedidoService = new PedidoService();
        $this->inscricaoService = new InscricaoService();
        $this->cursoService = new CursoService();
        $this->funil = new CheckoutFunilService();
        $this->config = new CheckoutRapidoConfigService();
    }

    // -----------------------------------------------------------------
    // Validacao
    // -----------------------------------------------------------------

    /**
     * Valida o formulario. Devolve array de erros por campo (vazio = ok).
     * Espelha exatamente o que o JS valida no cliente — o cliente e
     * conveniencia, esta e a validacao que vale.
     */
    public function validar(array $input)
    {
        $errors = array();

        $email = strtolower(trim((string) ($input['email'] ?? '')));
        if ($email === '') {
            $errors['email'] = 'Informe seu e-mail.';
        } elseif (!Validator::email($email)) {
            $errors['email'] = 'Esse e-mail não parece válido. Confira e tente de novo.';
        }

        $whatsapp = (string) ($input['whatsapp'] ?? '');
        if (trim($whatsapp) === '') {
            $errors['whatsapp'] = 'Informe seu WhatsApp com DDD.';
        } elseif (!Whatsapp::valido($whatsapp)) {
            $errors['whatsapp'] = 'Informe um celular brasileiro com DDD, no formato (11) 98888-7777.';
        }

        $cpf = (string) ($input['cpf'] ?? '');
        if (trim($cpf) === '') {
            $errors['cpf'] = 'Informe seu CPF.';
        } elseif (!Validator::cpf($cpf)) {
            $errors['cpf'] = 'Esse CPF não é válido. Confira os números.';
        }

        return $errors;
    }

    /**
     * O curso existe, esta publicado e tem turma aberta para inscricao?
     */
    public function validarCurso($cursoId, $turmaId = null)
    {
        $cursoId = (int) $cursoId;
        $curso = $this->cursoModel->findPublicById($cursoId);

        if (!$curso) {
            return array('ok' => false, 'message' => 'Curso não encontrado ou indisponível.');
        }

        $contexto = $this->cursoService->showPublic($cursoId, $turmaId ?: null);
        $turmaSelecionada = !empty($contexto['curso']['turma_selecionada'])
            ? $contexto['curso']['turma_selecionada']
            : null;

        $usarTurmas = isset($curso['usar_turmas']) ? (int) $curso['usar_turmas'] : 1;
        if ($usarTurmas === 1 && empty($turmaSelecionada)) {
            return array('ok' => false, 'message' => 'Este curso ainda não tem turma aberta para inscrição.');
        }

        return array(
            'ok' => true,
            'curso' => $contexto['curso'],
            'turma' => $turmaSelecionada,
            'valor' => (float) $this->cursoService->calcularValorEfetivoCurso($curso),
        );
    }

    // -----------------------------------------------------------------
    // Resolucao de usuario
    // -----------------------------------------------------------------

    /**
     * Encontra ou cria o usuario a partir de e-mail + CPF, SEM pedir login.
     *
     * CPF e e-mail sao ambos UNIQUE em `usuarios`, entao ha combinacoes que
     * precisam de regra explicita:
     *
     *   1. CPF ja existe          -> reaproveita aquele usuario. Se o e-mail
     *                                digitado for diferente do cadastrado, o
     *                                e-mail da CONTA nao e sobrescrito (seria
     *                                vetor de tomada de conta); o digitado vai
     *                                para o pedido e recebe as mensagens.
     *   2. CPF novo, e-mail existe -> se aquele usuario nao tem CPF, preenche.
     *                                Se tem CPF diferente, recusa com mensagem
     *                                clara: sao duas pessoas disputando o mesmo
     *                                e-mail e o banco nao permite.
     *   3. Nada existe            -> cria usuario pendente (sem nome, sem senha).
     */
    public function resolverUsuario($email, $whatsappE164, $cpf)
    {
        $email = strtolower(trim((string) $email));
        $cpfDigitos = preg_replace('/\D+/', '', (string) $cpf);

        $porCpf = $this->usuarioModel->findByCpf($cpfDigitos);
        if ($porCpf) {
            $this->usuarioModel->preencherWhatsappSeVazio((int) $porCpf['id'], $whatsappE164);

            return array(
                'ok' => true,
                'usuario_id' => (int) $porCpf['id'],
                'usuario' => $porCpf,
                'novo' => false,
                'email_conta' => (string) $porCpf['email'],
                'email_divergente' => strtolower((string) $porCpf['email']) !== $email,
            );
        }

        $porEmail = $this->usuarioModel->findByEmail($email);
        if ($porEmail) {
            $cpfExistente = preg_replace('/\D+/', '', (string) ($porEmail['cpf'] ?? ''));

            if ($cpfExistente !== '' && $cpfExistente !== $cpfDigitos) {
                return array(
                    'ok' => false,
                    'campo' => 'cpf',
                    'message' => 'Este e-mail já está cadastrado com outro CPF. '
                        . 'Use o CPF daquele cadastro ou informe outro e-mail.',
                );
            }

            if ($cpfExistente === '') {
                $this->usuarioModel->preencherCpfSeVazio((int) $porEmail['id'], $cpfDigitos);
            }

            $this->usuarioModel->preencherWhatsappSeVazio((int) $porEmail['id'], $whatsappE164);

            return array(
                'ok' => true,
                'usuario_id' => (int) $porEmail['id'],
                'usuario' => $this->usuarioModel->findById((int) $porEmail['id']),
                'novo' => false,
                'email_conta' => $email,
                'email_divergente' => false,
            );
        }

        try {
            $usuarioId = $this->usuarioModel->createPendente(array(
                'email' => $email,
                'cpf' => $cpfDigitos,
                'whatsapp' => $whatsappE164,
                'cadastro_origem' => 'checkout_rapido',
            ));
        } catch (Exception $exception) {
            // Corrida: outro request criou o mesmo e-mail/CPF entre a consulta
            // e o INSERT. Reconsulta em vez de estourar para o visitante.
            Logger::error('checkout_rapido.usuario.criacao_falhou', array(
                'email' => $email,
                'message' => $exception->getMessage(),
            ));

            $existente = $this->usuarioModel->findByCpf($cpfDigitos);
            if (!$existente) {
                $existente = $this->usuarioModel->findByEmail($email);
            }

            if (!$existente) {
                return array(
                    'ok' => false,
                    'campo' => 'email',
                    'message' => 'Não foi possível iniciar sua compra agora. Tente novamente em instantes.',
                );
            }

            return array(
                'ok' => true,
                'usuario_id' => (int) $existente['id'],
                'usuario' => $existente,
                'novo' => false,
                'email_conta' => (string) $existente['email'],
                'email_divergente' => strtolower((string) $existente['email']) !== $email,
            );
        }

        Logger::info('checkout_rapido.usuario.criado', array('usuario_id' => $usuarioId));

        return array(
            'ok' => true,
            'usuario_id' => (int) $usuarioId,
            'usuario' => $this->usuarioModel->findById($usuarioId),
            'novo' => true,
            'email_conta' => $email,
            'email_divergente' => false,
        );
    }

    // -----------------------------------------------------------------
    // Pedido
    // -----------------------------------------------------------------

    /**
     * Do formulario ao pedido em `aguardando_pagamento`, com origem gravada.
     *
     * Nao abre transacao propria: PedidoService e InscricaoService ja abrem a
     * sua, e o PDO nao aninha. Cada passo e idempotente ou reconsultado.
     */
    public function iniciarPedido(array $dados, $ipAddress = null, $userAgent = null)
    {
        $cursoId = (int) $dados['curso_evento_id'];
        $turmaId = isset($dados['turma_id']) ? (int) $dados['turma_id'] : 0;
        $usuarioId = (int) $dados['usuario_id'];
        $email = strtolower(trim((string) $dados['email']));
        $whatsapp = (string) $dados['whatsapp'];
        $cpf = preg_replace('/\D+/', '', (string) $dados['cpf']);
        $origem = isset($dados['origem']) && is_array($dados['origem']) ? $dados['origem'] : OrigemTrafego::atual();

        // Ja matriculado? Nao cobra de novo.
        $situacao = $this->inscricaoService->situacaoAlunoNoCurso($usuarioId, $cursoId, $turmaId ?: null);
        if ((string) $situacao['status_fluxo'] === 'matriculado') {
            return array(
                'ok' => false,
                'ja_matriculado' => true,
                'situacao' => $situacao,
                'message' => 'Você já tem acesso a este curso.',
            );
        }

        // Recarregou a tela? Reaproveita o pedido pendente em vez de criar outro.
        $pendente = $this->pedidoModel->findAguardandoPagamentoDoUsuarioCurso($usuarioId, $cursoId, $turmaId ?: null);
        if ($pendente) {
            $this->pedidoModel->gravarOrigemTrafego((int) $pendente['id'], $origem);

            return array(
                'ok' => true,
                'pedido_id' => (int) $pendente['id'],
                'pedido' => $this->pedidoModel->findById((int) $pendente['id']),
                'reaproveitado' => true,
            );
        }

        $draft = $this->pedidoService->criarCheckoutDraft(array(
            'curso_evento_id' => $cursoId,
            'turma_id' => $turmaId ?: null,
            'quantidade' => 1,
            'tipo_pedido' => 'propria',
            'comprador_usuario_id' => $usuarioId,
            'pagador_usuario_id' => $usuarioId,
            'pagador_nome' => null,
            'pagador_cpf' => $cpf,
            'pagador_email' => $email,
            'pagador_telefone' => $whatsapp,
            'canal_origem' => 'checkout_rapido',
        ), $usuarioId, $ipAddress, $userAgent);

        if (empty($draft['ok'])) {
            return array(
                'ok' => false,
                'message' => isset($draft['message']) ? $draft['message'] : 'Não foi possível iniciar sua compra.',
            );
        }

        $pedidoId = (int) $draft['pedido_id'];

        $this->pedidoModel->gravarOrigemTrafego($pedidoId, $origem);

        // Participante da compra propria. Sem nome: ele e coletado depois do
        // pagamento, para o certificado. adicionarParticipantesAoPedido() do
        // PedidoService descarta participante sem nome, por isso gravamos aqui.
        $itens = $this->pedidoItemModel->forPedido($pedidoId);
        if (empty($itens)) {
            return array('ok' => false, 'message' => 'Pedido criado sem itens. Tente novamente.');
        }

        $this->participanteModel->create(array(
            'pedido_id' => $pedidoId,
            'pedido_item_id' => (int) $itens[0]['id'],
            'usuario_id' => $usuarioId,
            'nome' => null,
            'cpf' => $cpf,
            'email' => $email,
            'telefone' => $whatsapp,
            'ordem' => 1,
            'status' => 'ativo',
        ));

        $inscricoes = $this->inscricaoService->gerarDoPedido($pedidoId, $usuarioId, $ipAddress, $userAgent);
        if (empty($inscricoes['ok'])) {
            return array(
                'ok' => false,
                'message' => isset($inscricoes['message']) ? $inscricoes['message'] : 'Não foi possível registrar sua inscrição.',
            );
        }

        $finalizacao = $this->pedidoService->finalizarCheckout($pedidoId, $usuarioId, $ipAddress, $userAgent);
        if (empty($finalizacao['ok'])) {
            return array(
                'ok' => false,
                'message' => isset($finalizacao['message']) ? $finalizacao['message'] : 'Não foi possível fechar seu pedido.',
            );
        }

        $pedido = $this->pedidoModel->findById($pedidoId);

        Logger::info('checkout_rapido.pedido.criado', array(
            'pedido_id' => $pedidoId,
            'usuario_id' => $usuarioId,
            'curso_evento_id' => $cursoId,
            'gclid' => isset($origem['gclid']) ? $origem['gclid'] : null,
        ));

        return array(
            'ok' => true,
            'pedido_id' => $pedidoId,
            'pedido' => $pedido,
            'reaproveitado' => false,
            'auto_aprovado_zero_valor' => !empty($finalizacao['auto_aprovado_zero_valor']),
        );
    }

    /**
     * Estado do pedido para o polling da tela.
     *
     * Identificado pelo par (id, codigo) e comparado em tempo constante: nao
     * exige sessao, porque o visitante ainda nao fez login, e nao devolve
     * NENHUM dado pessoal — so o que a tela precisa para decidir se redireciona.
     *
     * A fonte da verdade e o nosso banco, alimentado pelo webhook do provedor.
     * O cliente nunca decide se pagou.
     */
    public function pedidoParaPolling($pedidoId, $codigo)
    {
        $pedido = $this->pedidoModel->findById((int) $pedidoId);

        if (!$pedido || !hash_equals((string) $pedido['codigo'], (string) $codigo)) {
            return null;
        }

        $status = (string) $pedido['status'];
        $pago = in_array($status, array('pago', 'aprovado'), true);

        $expiraEm = !empty($pedido['data_expiracao_pagamento']) ? (string) $pedido['data_expiracao_pagamento'] : null;
        $expirado = false;
        if (!$pago && $expiraEm !== null) {
            $expirado = strtotime($expiraEm) < time();
        }

        $destino = null;
        if ($pago) {
            $usuarioId = (int) ($pedido['comprador_usuario_id'] ?? 0);
            $itens = $this->pedidoItemModel->forPedido((int) $pedido['id']);
            $cursoId = !empty($itens[0]['curso_evento_id']) ? (int) $itens[0]['curso_evento_id'] : 0;
            $turmaId = !empty($itens[0]['turma_id']) ? (int) $itens[0]['turma_id'] : null;

            if ($usuarioId > 0 && $cursoId > 0) {
                $situacao = $this->inscricaoService->situacaoAlunoNoCurso($usuarioId, $cursoId, $turmaId);
                $destino = \App\Core\Helpers::urlAcessoCursoAluno($situacao);
            }
        }

        return array(
            'ok' => true,
            'pago' => $pago,
            'expirado' => $expirado,
            'status' => $pago ? 'pago' : ($expirado ? 'expirado' : 'aguardando'),
            'expira_em' => $expiraEm,
            'destino' => $destino,
        );
    }

    public function funil()
    {
        return $this->funil;
    }

    public function config()
    {
        return $this->config;
    }
}
