<?php

namespace App\Services;

use App\Core\Logger;
use App\Models\EmailEnvio;
use App\Services\TrashService;

class EmailAdminService
{
    private $emailModel;
    private $emailService;
    private $trashService;

    public function __construct()
    {
        $this->emailModel = new EmailEnvio();
        $this->emailService = new EmailService();
        $this->trashService = new TrashService();
    }

    public function resumo()
    {
        $todayStart = date('Y-m-d 00:00:00');
        $weekStart = date('Y-m-d 00:00:00', strtotime('monday this week'));
        $monthStart = date('Y-m-01 00:00:00');

        $pendentesEfalhas = $this->emailModel->countByStatus(array('pendente', 'falhou'));

        $falhasHoje = $this->emailModel->countByStatus(array('falhou'), 'falhou_em', $todayStart, null);
        $falhasSemana = $this->emailModel->countByStatus(array('falhou'), 'falhou_em', $weekStart, null);
        $falhasMes = $this->emailModel->countByStatus(array('falhou'), 'falhou_em', $monthStart, null);

        $enviadosHoje = $this->emailModel->countByStatus(array('enviado'), 'enviado_em', $todayStart, null);
        $enviadosSemana = $this->emailModel->countByStatus(array('enviado'), 'enviado_em', $weekStart, null);
        $enviadosMes = $this->emailModel->countByStatus(array('enviado'), 'enviado_em', $monthStart, null);

        return array(
            'pendentes' => $pendentesEfalhas,
            'falhas_hoje' => $falhasHoje,
            'falhas_semana' => $falhasSemana,
            'falhas_mes' => $falhasMes,
            'enviados_hoje' => $enviadosHoje,
            'enviados_semana' => $enviadosSemana,
            'enviados_mes' => $enviadosMes,
        );
    }

    public function listarFilaHistorico(array $filters = array(), $page = 1, $perPage = 100)
    {
        $page = (int) $page;
        if ($page <= 0) {
            $page = 1;
        }

        $perPage = (int) $perPage;
        if ($perPage <= 0) {
            $perPage = 100;
        }
        if ($perPage > 200) {
            $perPage = 200;
        }

        $total = $this->emailModel->countFiltered($filters);
        $offset = ($page - 1) * $perPage;
        $items = $this->emailModel->listFiltered($filters, $perPage, $offset);

        return array(
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => $perPage > 0 ? (int) ceil($total / $perPage) : 1,
        );
    }

    public function reenviarEmail($emailId, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $emailId = (int) $emailId;
        if ($emailId <= 0) {
            return array('ok' => false, 'message' => 'E-mail inválido.');
        }

        $email = $this->emailModel->findById($emailId);
        if (!$email) {
            return array('ok' => false, 'message' => 'E-mail não encontrado.');
        }

        $status = isset($email['status']) ? (string) $email['status'] : '';
        if ($status === 'enviado') {
            return $this->emailService->reenviarHistorico($emailId, $actorUserId, $ipAddress, $userAgent);
        }

        return $this->emailService->resendExisting($emailId, $actorUserId, $ipAddress, $userAgent);
    }

    public function reenviarSelecionados(array $ids, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $reenviados = 0;
        $ignorados = 0;
        $resultados = array();

        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id <= 0) {
                $ignorados++;
                $resultados[] = array(
                    'id' => $id,
                    'ok' => false,
                    'message' => 'ID inválido.',
                );
                continue;
            }

            $result = $this->reenviarEmail($id, $actorUserId, $ipAddress, $userAgent);
            if (!empty($result['ok'])) {
                $reenviados++;
                $resultados[] = array(
                    'id' => $id,
                    'ok' => true,
                    'email_id' => isset($result['email_id']) ? (int) $result['email_id'] : null,
                    'message' => isset($result['skipped']) && !empty($result['skipped']) ? (string) ($result['message'] ?? 'Envio ignorado.') : 'E-mail reenviado com sucesso.',
                );
            } else {
                $ignorados++;
                $resultados[] = array(
                    'id' => $id,
                    'ok' => false,
                    'message' => isset($result['message']) ? (string) $result['message'] : 'Não foi possível reenviar o e-mail.',
                );
            }
        }

        Logger::info('emails.reenvio_lote', array(
            'reenviados' => $reenviados,
            'ignorados' => $ignorados,
        ));

        return array('ok' => true, 'reenviados' => $reenviados, 'ignorados' => $ignorados, 'resultados' => $resultados);
    }

    public function reenviarPendentes($actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $ids = array();
        foreach (array('pendente', 'falhou') as $status) {
            $page = 1;
            do {
                $pageData = $this->listarFilaHistorico(array('status' => $status), $page, 200);
                foreach ($pageData['items'] as $item) {
                    $ids[] = (int) $item['id'];
                }
                $page++;
                $hasMore = $page <= (int) ($pageData['pages'] ?? 1);
            } while ($hasMore);
        }

        $ids = array_values(array_unique($ids));
        if (empty($ids)) {
            return array('ok' => true, 'reenviados' => 0, 'ignorados' => 0);
        }

        return $this->reenviarSelecionados($ids, $actorUserId, $ipAddress, $userAgent);
    }

    public function excluirFalha($emailId, $justificativa, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $emailId = (int) $emailId;
        if ($emailId <= 0) {
            return array('ok' => false, 'message' => 'E-mail não encontrado.');
        }

        $justificativa = trim((string) $justificativa);
        if ($justificativa === '') {
            return array('ok' => false, 'message' => 'A justificativa da lixeira é obrigatória.');
        }

        $email = $this->emailModel->findById($emailId);
        if (!$email) {
            return array('ok' => false, 'message' => 'E-mail não encontrado.');
        }

        $status = isset($email['status']) ? (string) $email['status'] : '';
        if ($status !== 'falhou') {
            return array('ok' => false, 'message' => 'Este e-mail não pode ser excluído.');
        }

        $this->trashService->record('email_envio', $emailId, $justificativa, $email, $actorUserId, $ipAddress, $userAgent);

        $deleted = $this->emailModel->deleteFailedById($emailId);
        if (!$deleted) {
            return array('ok' => false, 'message' => 'Este e-mail não pode ser excluído.');
        }

        Logger::info('emails.excluir_falha', array(
            'email_id' => $emailId,
            'actor_user_id' => $actorUserId,
        ));

        return array('ok' => true, 'message' => 'E-mail com falha excluído da fila.');
    }

    public function excluirFalhasSelecionadas(array $ids, $justificativa, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $excluidos = 0;
        $ignorados = 0;

        $justificativa = trim((string) $justificativa);
        if ($justificativa === '') {
            return array('ok' => false, 'message' => 'A justificativa da lixeira é obrigatória.');
        }

        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id <= 0) {
                $ignorados++;
                continue;
            }

            $result = $this->excluirFalha($id, $justificativa, $actorUserId, $ipAddress, $userAgent);
            if (!empty($result['ok'])) {
                $excluidos++;
            } else {
                $ignorados++;
            }
        }

        Logger::info('emails.excluir_falhas_selecionadas', array(
            'excluidos' => $excluidos,
            'ignorados' => $ignorados,
            'actor_user_id' => $actorUserId,
        ));

        return array('ok' => true, 'excluidos' => $excluidos, 'ignorados' => $ignorados);
    }
}
