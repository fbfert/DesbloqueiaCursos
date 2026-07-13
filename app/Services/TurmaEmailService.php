<?php

namespace App\Services;

use App\Core\Helpers;
use App\Core\Logger;
use App\Models\EmailEnvio;
use App\Models\Inscricao;
use App\Models\Turma;
use App\Models\TurmaEmail;
use App\Support\HtmlSanitizer;

/**
 * Comunicados por e-mail para os alunos matriculados em uma turma.
 *
 * O assunto e o corpo sao escritos na hora do envio. Cada comunicado e um lote
 * (turma_emails) e cada destinatario vira uma linha em emails_envios com
 * entidade_tipo = "turma_email", o que reaproveita a fila, o reenvio e a auditoria
 * de e-mail que ja existem no admin.
 *
 * O disparo e feito em lotes pequenos (processarLote) porque nao ha worker de fila
 * no servidor: enviar dezenas de e-mails SMTP dentro de um unico POST estouraria o
 * tempo limite do PHP.
 */
class TurmaEmailService
{
    const ENTIDADE_TIPO = 'turma_email';
    const EVENTO = 'turma.comunicado';
    const TEMPLATE = 'turma_comunicado';
    const LOTE_PADRAO = 10;

    private $turmaEmailModel;
    private $turmaModel;
    private $inscricaoModel;
    private $emailEnvioModel;
    private $emailService;
    private $auditService;

    public function __construct()
    {
        $this->turmaEmailModel = new TurmaEmail();
        $this->turmaModel = new Turma();
        $this->inscricaoModel = new Inscricao();
        $this->emailEnvioModel = new EmailEnvio();
        $this->emailService = new EmailService();
        $this->auditService = new AuditService();
    }

    public function turma($turmaId)
    {
        return $this->turmaModel->findAdminById((int) $turmaId);
    }

    /**
     * Alunos matriculados que receberao o comunicado, ja sem e-mails invalidos ou repetidos.
     */
    public function destinatarios($turmaId)
    {
        $inscricoes = $this->inscricaoModel->forTurmaMatriculados((int) $turmaId);

        $destinatarios = array();
        $vistos = array();

        foreach ($inscricoes as $inscricao) {
            $email = isset($inscricao['email']) ? strtolower(trim((string) $inscricao['email'])) : '';
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            if (isset($vistos[$email])) {
                continue;
            }

            $vistos[$email] = true;
            $destinatarios[] = array(
                'usuario_id' => isset($inscricao['usuario_id']) ? (int) $inscricao['usuario_id'] : null,
                'nome' => isset($inscricao['nome']) ? trim((string) $inscricao['nome']) : '',
                'email' => $email,
            );
        }

        return $destinatarios;
    }

    public function historico($turmaId)
    {
        return $this->turmaEmailModel->listByTurma((int) $turmaId);
    }

    public function lote($loteId)
    {
        return $this->turmaEmailModel->findById((int) $loteId);
    }

    public function resumoLote($loteId)
    {
        return $this->emailEnvioModel->resumoByEntidade(self::ENTIDADE_TIPO, (int) $loteId);
    }

    public function destinatariosDoLote($loteId)
    {
        return $this->emailEnvioModel->listByEntidade(self::ENTIDADE_TIPO, (int) $loteId);
    }

    /**
     * Cria o comunicado e enfileira um e-mail por destinatario, sem enviar.
     * O envio acontece em processarLote().
     */
    public function criarLote($turmaId, $assunto, $corpoHtml, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $turmaId = (int) $turmaId;
        $assunto = trim((string) $assunto);
        $errors = array();

        $turma = $this->turma($turmaId);
        if (!$turma) {
            return array('ok' => false, 'errors' => array('Turma não encontrada.'));
        }

        if ($assunto === '') {
            $errors[] = 'Informe o assunto do e-mail.';
        } elseif (mb_strlen($assunto) > 255) {
            $errors[] = 'O assunto deve ter no máximo 255 caracteres.';
        }

        $corpoLimpo = HtmlSanitizer::clean(Helpers::decodeEditorHtml($corpoHtml), 'full');
        if (trim(strip_tags($corpoLimpo)) === '') {
            $errors[] = 'Escreva o conteúdo do e-mail.';
        }

        $destinatarios = $this->destinatarios($turmaId);
        if (empty($destinatarios)) {
            $errors[] = 'Esta turma não tem alunos matriculados com e-mail válido.';
        }

        if (!empty($errors)) {
            return array('ok' => false, 'errors' => $errors);
        }

        $loteId = $this->turmaEmailModel->create(array(
            'turma_id' => $turmaId,
            'assunto' => $assunto,
            'corpo_html' => $corpoLimpo,
            'total_destinatarios' => count($destinatarios),
            'criado_por_usuario_id' => $actorUserId,
        ));

        $enfileirados = 0;
        foreach ($destinatarios as $destinatario) {
            $resultado = $this->emailService->queueCustomHtml(
                self::EVENTO,
                self::TEMPLATE,
                $destinatario['email'],
                $destinatario['nome'],
                $assunto,
                $this->contextoDoDestinatario($destinatario, $turma),
                self::ENTIDADE_TIPO,
                $loteId,
                $actorUserId
            );

            if (!empty($resultado['ok'])) {
                $enfileirados++;
            }
        }

        if ($enfileirados !== count($destinatarios)) {
            $this->turmaEmailModel->updateTotalDestinatarios($loteId, $enfileirados);
        }

        $this->auditService->record(
            'turmas.email.criado',
            'turma_email',
            $loteId,
            array(
                'turma_id' => $turmaId,
                'assunto' => $assunto,
                'destinatarios' => $enfileirados,
            ),
            $actorUserId,
            $ipAddress,
            $userAgent
        );

        Logger::info('turmas.email.criado', array(
            'lote_id' => $loteId,
            'turma_id' => $turmaId,
            'destinatarios' => $enfileirados,
        ));

        if ($enfileirados === 0) {
            return array('ok' => false, 'errors' => array('Nenhum destinatário pôde ser enfileirado.'));
        }

        return array('ok' => true, 'lote_id' => $loteId, 'total' => $enfileirados);
    }

    /**
     * Envia o proximo lote de pendentes do comunicado. Chamado repetidamente pela tela
     * de progresso ate nao restar nenhum pendente.
     */
    public function processarLote($loteId, $limite = self::LOTE_PADRAO, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $loteId = (int) $loteId;

        $lote = $this->lote($loteId);
        if (!$lote) {
            return array('ok' => false, 'message' => 'Comunicado não encontrado.');
        }

        $pendentes = $this->emailEnvioModel->pendentesByEntidade(self::ENTIDADE_TIPO, $loteId, $limite);

        $enviadosAgora = 0;
        $falhasAgora = 0;

        foreach ($pendentes as $pendente) {
            $resultado = $this->emailService->sendQueuedCustomHtml(
                (int) $pendente['id'],
                (string) $lote['corpo_html'],
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            if (!empty($resultado['ok'])) {
                $enviadosAgora++;
            } else {
                $falhasAgora++;
            }
        }

        $resumo = $this->resumoLote($loteId);

        if ((int) $resumo['pendentes'] === 0) {
            $this->auditService->record(
                'turmas.email.concluido',
                'turma_email',
                $loteId,
                array(
                    'turma_id' => (int) $lote['turma_id'],
                    'enviados' => (int) $resumo['enviados'],
                    'falhas' => (int) $resumo['falhas'],
                ),
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            Logger::info('turmas.email.concluido', array(
                'lote_id' => $loteId,
                'enviados' => (int) $resumo['enviados'],
                'falhas' => (int) $resumo['falhas'],
            ));
        }

        return array(
            'ok' => true,
            'enviados_agora' => $enviadosAgora,
            'falhas_agora' => $falhasAgora,
            'concluido' => (int) $resumo['pendentes'] === 0,
            'resumo' => $resumo,
        );
    }

    /**
     * Dados disponiveis como {placeholders} no assunto e no corpo.
     */
    private function contextoDoDestinatario(array $destinatario, array $turma)
    {
        return array(
            'usuario' => array(
                'nome' => $destinatario['nome'],
                'email' => $destinatario['email'],
            ),
            'turma' => array(
                'nome' => isset($turma['nome']) ? (string) $turma['nome'] : '',
                'codigo' => isset($turma['codigo']) ? (string) $turma['codigo'] : '',
                'data_inicio' => $this->formatarData(isset($turma['data_inicio']) ? $turma['data_inicio'] : null),
                'data_fim' => $this->formatarData(isset($turma['data_fim']) ? $turma['data_fim'] : null),
            ),
            'curso' => array(
                'nome' => isset($turma['curso_nome']) ? (string) $turma['curso_nome'] : '',
            ),
        );
    }

    private function formatarData($valor)
    {
        $valor = trim((string) $valor);
        if ($valor === '' || $valor === '0000-00-00') {
            return '';
        }

        $timestamp = strtotime($valor);

        return $timestamp ? date('d/m/Y', $timestamp) : '';
    }
}
