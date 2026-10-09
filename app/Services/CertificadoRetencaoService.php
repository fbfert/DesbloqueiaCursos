<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Helpers;
use App\Core\Logger;
use App\Models\CertificadoRetido;
use App\Models\Usuario;
use App\Support\AppApi\Tempo;

/**
 * Certificados retidos aguardando CPF (mudança login-google).
 *
 * A emissão é sempre pedida pela administração. Quando o participante não tem CPF e
 * a conta ligada a ele também não (aluno que entrou pelo Google e não informou o
 * CPF), CertificadoService::emitirInterno chama reter(): nada é emitido, a
 * solicitação fica guardada com os parâmetros escolhidos e o aluno recebe um e-mail
 * pedindo o CPF (no máximo um a cada 24 h). Quando o CPF chega (ContaCpfService),
 * liberarDoUsuario() emite os retidos em nome de quem pediu — com as permissões de
 * quem pediu: se essa pessoa perdeu a permissão, a emissão falha e fica registrada.
 */
class CertificadoRetencaoService
{
    const MSG_RETIDO = 'Certificado retido: o aluno ainda não informou o CPF. Ele será emitido automaticamente quando o CPF for informado.';
    const LIMITE_POR_CHAMADA = 5;
    const INTERVALO_AVISO = 86400;

    private $retidos;
    private $emissor;
    private $emails;

    /**
     * @param callable|null $emissor function (array $retencao) → resultado de emissão (testes)
     */
    public function __construct(?callable $emissor = null, ?EmailService $emails = null)
    {
        $this->retidos = new CertificadoRetido();
        $this->emissor = $emissor;
        $this->emails = $emails;
    }

    /**
     * Guarda a solicitação de emissão. Uma retenção ativa por inscrição: nova
     * solicitação atualiza os parâmetros da existente.
     *
     * @return array ok => false, retido => true, retencao_id, message (formato de falha de emissão)
     */
    public function reter($inscricaoId, $usuarioId, $modo, array $opcoes, array $contexto, $solicitadoPor, $ipAddress = null, $userAgent = null)
    {
        $dados = array(
            'inscricao_id' => (int) $inscricaoId,
            'usuario_id' => (int) $usuarioId,
            'modo' => (string) $modo,
            'opcoes' => $opcoes,
            'contexto' => $contexto,
            'solicitado_por' => $solicitadoPor,
        );

        $pdo = Database::connection();
        $transacaoPropria = !$pdo->inTransaction();
        if ($transacaoPropria) {
            $pdo->beginTransaction();
        }
        try {
            $ativa = $this->retidos->findAtivaPorInscricaoParaAtualizar($inscricaoId);
            if ($ativa) {
                $this->retidos->atualizarSolicitacao((int) $ativa['id'], $dados);
                $retencaoId = (int) $ativa['id'];
            } else {
                $retencaoId = $this->retidos->criar($dados);
            }
            if ($transacaoPropria) {
                $pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($transacaoPropria && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        (new AuditService())->record('certificado.retido_aguardando_cpf', 'inscricao', (int) $inscricaoId, array(
            'retencao_id' => $retencaoId,
            'usuario_id' => (int) $usuarioId,
            'modo' => (string) $modo,
        ), $solicitadoPor, $ipAddress, $userAgent);

        $this->avisarAluno((int) $usuarioId, $retencaoId, (int) $inscricaoId);

        return array('ok' => false, 'retido' => true, 'retencao_id' => $retencaoId, 'message' => self::MSG_RETIDO);
    }

    /**
     * Emite os certificados retidos de uma conta que já tem CPF (até 5 por chamada;
     * os demais saem na próxima abertura da área do aluno ou de "Meus certificados").
     *
     * @return array emitidos, falhas, restantes
     */
    public function liberarDoUsuario($usuarioId, $limite = self::LIMITE_POR_CHAMADA)
    {
        $usuario = (new Usuario())->findById((int) $usuarioId);
        if (!$usuario || Usuario::semCpf($usuario)) {
            return array('emitidos' => 0, 'falhas' => 0, 'restantes' => $usuario ? $this->retidos->contarAtivasDoUsuario($usuarioId) : 0);
        }

        $emitidos = 0;
        $falhas = 0;
        foreach ($this->retidos->ativasDoUsuario((int) $usuarioId, $limite) as $retencao) {
            try {
                $resultado = $this->emitir($retencao);
            } catch (\Throwable $e) {
                $resultado = array('ok' => false, 'message' => $e->getMessage());
            }

            if (!empty($resultado['ok'])) {
                $this->retidos->marcarEmitido((int) $retencao['id'], $resultado['certificado_id'] ?? null);
                $emitidos++;
                continue;
            }

            $mensagem = (string) ($resultado['message'] ?? 'Falha ao emitir o certificado retido.');
            if (strpos($mensagem, 'já possui certificado emitido') !== false) {
                // Alguém emitiu por fora nesse meio-tempo: a retenção cumpriu o papel.
                $this->retidos->marcarEmitido((int) $retencao['id'], $resultado['certificado_id'] ?? null);
                continue;
            }

            $this->retidos->registrarFalha((int) $retencao['id'], $mensagem);
            Logger::error('Certificado retido: emissão automática falhou.', array('retencao_id' => (int) $retencao['id'], 'erro' => $mensagem));
            $falhas++;
        }

        return array('emitidos' => $emitidos, 'falhas' => $falhas, 'restantes' => $this->retidos->contarAtivasDoUsuario($usuarioId));
    }

    /** Liberação oportunista: barata quando não há retenção (uma contagem). */
    public function liberarSePendente($usuarioId)
    {
        if ((int) $usuarioId <= 0 || $this->retidos->contarAtivasDoUsuario($usuarioId) === 0) {
            return null;
        }
        try {
            return $this->liberarDoUsuario($usuarioId);
        } catch (\Throwable $e) {
            Logger::error('Certificado retido: liberação falhou.', array('usuario_id' => (int) $usuarioId, 'erro' => $e->getMessage()));
            return null;
        }
    }

    public function cancelar($retencaoId, $justificativa, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $retencao = $this->retidos->findById($retencaoId);
        if (!$retencao || $retencao['status'] !== CertificadoRetido::AGUARDANDO) {
            return array('ok' => false, 'message' => 'Retenção não encontrada ou já concluída.');
        }
        if (trim((string) $justificativa) === '') {
            return array('ok' => false, 'message' => 'Informe a justificativa do cancelamento.');
        }

        (new TrashService())->record('certificado_retido', (int) $retencaoId, $justificativa, $retencao, $actorUserId, $ipAddress, $userAgent);
        $this->retidos->cancelar($retencaoId);
        (new AuditService())->record('certificado.retencao_cancelada', 'inscricao', (int) $retencao['inscricao_id'], array(
            'retencao_id' => (int) $retencaoId,
            'justificativa' => (string) $justificativa,
        ), $actorUserId, $ipAddress, $userAgent);

        return array('ok' => true);
    }

    public function listarAguardando()
    {
        return $this->retidos->listarAguardando();
    }

    private function emitir(array $retencao)
    {
        if ($this->emissor !== null) {
            return call_user_func($this->emissor, $retencao);
        }

        return (new CertificadoService())->emitirRetencao(
            (int) $retencao['inscricao_id'],
            (array) json_decode((string) $retencao['opcoes'], true),
            (array) json_decode((string) $retencao['contexto'], true),
            !empty($retencao['solicitado_por']) ? (int) $retencao['solicitado_por'] : null
        );
    }

    private function avisarAluno($usuarioId, $retencaoId, $inscricaoId)
    {
        $ultimo = $this->retidos->ultimoAvisoDoUsuario($usuarioId);
        if ($ultimo !== null && Tempo::agoraTs() - $ultimo < self::INTERVALO_AVISO) {
            return;
        }

        $usuario = (new Usuario())->findById($usuarioId);
        if (!$usuario) {
            return;
        }

        $stmt = Database::connection()->prepare(
            'SELECT c.nome FROM inscricoes i JOIN cursos_eventos c ON c.id = i.curso_evento_id WHERE i.id = :id LIMIT 1'
        );
        $stmt->execute(array('id' => (int) $inscricaoId));
        $cursoNome = (string) $stmt->fetchColumn();

        try {
            if ($this->emails === null) {
                $this->emails = new EmailService();
            }
            $this->emails->certificadoRetido($usuario, $cursoNome, Helpers::url('v2/minha-conta#cpf'));
            $this->retidos->marcarAviso($retencaoId);
        } catch (\Throwable $e) {
            Logger::error('Certificado retido: falha ao avisar o aluno.', array('usuario_id' => $usuarioId, 'erro' => $e->getMessage()));
        }
    }
}
