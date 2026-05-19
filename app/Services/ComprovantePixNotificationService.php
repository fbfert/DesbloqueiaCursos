<?php

namespace App\Services;

use App\Core\Helpers;
use App\Core\Logger;
use App\Core\Validator;
use App\Models\ComprovantePix;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\Usuario;

class ComprovantePixNotificationService
{
    private $comprovanteModel;
    private $pedidoModel;
    private $pedidoItemModel;
    private $usuarioModel;
    private $globalConfigService;
    private $emailService;

    public function __construct()
    {
        $this->comprovanteModel = new ComprovantePix();
        $this->pedidoModel = new Pedido();
        $this->pedidoItemModel = new PedidoItem();
        $this->usuarioModel = new Usuario();
        $this->globalConfigService = new ConfiguracaoGlobalService();
        $this->emailService = new EmailService();
    }

    public function notificarNovoComprovantePix($comprovanteId, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        try {
            $comprovante = $this->comprovanteModel->findById((int) $comprovanteId);
            if (!$comprovante) {
                Logger::info('comprovante_pix.notificacao.nao_encontrado', array('comprovante_pix_id' => (int) $comprovanteId));
                return array('ok' => false, 'message' => 'Comprovante não encontrado.');
            }

            $pedido = $this->pedidoModel->findById((int) $comprovante['pedido_id']);
            if (!$pedido) {
                Logger::info('comprovante_pix.notificacao.pedido_nao_encontrado', array(
                    'comprovante_pix_id' => (int) $comprovanteId,
                    'pedido_id' => (int) $comprovante['pedido_id'],
                ));
                return array('ok' => false, 'message' => 'Pedido do comprovante não encontrado.');
            }

            $destinatarios = $this->resolveFinanceRecipients();
            if (empty($destinatarios)) {
                Logger::info('comprovante_pix.notificacao.sem_destinatario_financeiro', array(
                    'comprovante_pix_id' => (int) $comprovanteId,
                    'pedido_id' => (int) $pedido['id'],
                ));
                return array('ok' => true, 'skipped' => true, 'message' => 'E-mail financeiro não configurado.');
            }

            $usuarioId = $actorUserId ? (int) $actorUserId : (!empty($comprovante['usuario_id']) ? (int) $comprovante['usuario_id'] : null);
            $aluno = $usuarioId ? $this->usuarioModel->findById($usuarioId) : null;

            $itens = $this->pedidoItemModel->forPedido((int) $pedido['id']);
            $primeiroItem = !empty($itens) ? $itens[0] : array();
            $cursoNome = isset($primeiroItem['curso_nome']) ? (string) $primeiroItem['curso_nome'] : '';
            $turmaNome = isset($primeiroItem['turma_nome']) ? (string) $primeiroItem['turma_nome'] : '';

            if (count($itens) > 1 && $cursoNome !== '') {
                $cursoNome .= ' (+' . (count($itens) - 1) . ')';
            }

            $adminPedidoUrl = Helpers::url('admin/pedidos/show?pedido_id=' . (int) $pedido['id']);
            $adminComprovantesUrl = Helpers::url('admin/comprovantes-pix');
            $adminComprovanteArquivoUrl = Helpers::url('admin/pedidos/comprovante?pedido_id=' . (int) $pedido['id'] . '&comprovante_id=' . (int) $comprovanteId);
            $adminFinanceiroUrl = Helpers::url('admin/financeiro');

            $institucional = $this->globalConfigService->institucional();
            $nomePlataforma = !empty($institucional['nome_fantasia']) ? (string) $institucional['nome_fantasia'] : 'Desbloqueia Cursos';
            $urlSite = rtrim((string) (require BASE_PATH . '/config/app.php')['url'], '/');
            if ($urlSite === '') {
                $urlSite = Helpers::url('/');
            }

            $data = array(
                'aluno_id' => $aluno ? (int) $aluno['id'] : '',
                'aluno_nome' => $aluno ? (string) $aluno['nome'] : '',
                'aluno_email' => $aluno ? (string) $aluno['email'] : '',

                'pedido_id' => (int) $pedido['id'],
                'pedido_codigo' => (string) $pedido['codigo'],
                'pedido_valor' => isset($pedido['total']) ? $pedido['total'] : '',
                'pedido_status' => isset($pedido['status']) ? (string) $pedido['status'] : '',
                'pedido_data' => isset($pedido['created_at']) ? (string) $pedido['created_at'] : '',

                'curso_id' => isset($primeiroItem['curso_evento_id']) ? (int) $primeiroItem['curso_evento_id'] : '',
                'curso_nome' => $cursoNome,

                'turma_id' => isset($primeiroItem['turma_id']) ? (int) $primeiroItem['turma_id'] : '',
                'turma_nome' => $turmaNome,

                'comprovante_id' => (int) $comprovanteId,
                'comprovante_status' => isset($comprovante['status']) ? (string) $comprovante['status'] : '',
                'comprovante_data_envio' => isset($comprovante['enviado_em']) ? (string) $comprovante['enviado_em'] : '',
                'comprovante_arquivo_nome' => isset($comprovante['arquivo_nome_original']) ? (string) $comprovante['arquivo_nome_original'] : '',
                'comprovante_arquivo_url' => $adminComprovanteArquivoUrl,

                'admin_comprovante_url' => $adminComprovantesUrl,
                'admin_pedido_url' => $adminPedidoUrl,
                'admin_financeiro_url' => $adminFinanceiroUrl,

                'nome_plataforma' => $nomePlataforma,
                'url_site' => $urlSite,
                'data_atual' => date('d/m/Y'),
            );

            $nomeDestinatario = 'Financeiro';
            $results = array();

            foreach ($destinatarios as $destEmail) {
                $results[] = $this->emailService->sendTemplate(
                    'email.comprovante_pix_novo_admin_financeiro',
                    'comprovante_pix_novo_admin_financeiro',
                    $destEmail,
                    $nomeDestinatario,
                    'Novo comprovante PIX enviado - Pedido {pedido_codigo}',
                    $data,
                    'comprovante_pix',
                    (int) $comprovanteId,
                    $actorUserId,
                    $ipAddress,
                    $userAgent
                );
            }

            Logger::info('comprovante_pix.notificacao_financeiro.criada', array(
                'comprovante_pix_id' => (int) $comprovanteId,
                'pedido_id' => (int) $pedido['id'],
                'destinatarios' => $destinatarios,
            ));

            return array('ok' => true, 'results' => $results);
        } catch (\Throwable $exception) {
            Logger::error('comprovante_pix.notificacao_financeiro.falhou', array(
                'comprovante_pix_id' => (int) $comprovanteId,
                'message' => $exception->getMessage(),
            ));

            return array('ok' => false, 'message' => 'Falha ao notificar financeiro.');
        }
    }

    private function resolveFinanceRecipients()
    {
        $institucional = $this->globalConfigService->institucional();
        $raw = isset($institucional['email_financeiro']) ? trim((string) $institucional['email_financeiro']) : '';
        if ($raw === '') {
            return array();
        }

        $emails = array();
        foreach (preg_split('/\\s*,\\s*/', $raw) as $email) {
            $email = trim((string) $email);
            if ($email === '') {
                continue;
            }
            if (!Validator::email($email)) {
                continue;
            }
            $emails[] = strtolower($email);
        }

        return array_values(array_unique($emails));
    }
}
