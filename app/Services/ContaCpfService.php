<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Core\Session;
use App\Core\Validator;
use App\Models\Usuario;
use PDOException;

/**
 * CPF informado pelo próprio usuário (mudança login-google).
 *
 * Contas criadas pelo Google nascem sem CPF. Este serviço é o único ponto que grava
 * o CPF informado pelo aluno — no site (/minha-conta, /conta/completar), no app
 * (POST /me/cpf) e no checkout —, e só enquanto a conta estiver sem CPF: depois,
 * só a administração altera.
 *
 * Ao gravar, copia o CPF para os participantes de pedido da conta que estão sem CPF
 * (o certificado lê o CPF do participante) e libera os certificados retidos
 * aguardando CPF. A liberação roda depois do commit e nunca desfaz a gravação.
 */
class ContaCpfService
{
    const MSG_INVALIDO = 'Informe um CPF válido.';
    const MSG_EM_USO = 'Este CPF já está cadastrado em outra conta. Fale com o atendimento.';
    const MSG_JA_INFORMADO = 'O CPF desta conta já foi informado. Para alterá-lo, fale com o atendimento.';

    private $usuarios;

    public function __construct()
    {
        $this->usuarios = new Usuario();
    }

    /**
     * @param string $canal site | app | checkout
     * @return array ok => true, cpf, liberados | ok => false, erro (validacao|cpf_em_uso|cpf_ja_informado|nao_encontrado), message
     */
    public function informar($usuarioId, $cpf, $canal, $ipAddress = null, $userAgent = null)
    {
        $usuarioId = (int) $usuarioId;
        $cpf = Usuario::normalizarCpf($cpf);
        if ($cpf === null || !Validator::cpf($cpf)) {
            return $this->erro('validacao', self::MSG_INVALIDO);
        }

        $usuario = $this->usuarios->findById($usuarioId);
        if (!$usuario) {
            return $this->erro('nao_encontrado', 'Usuário não encontrado.');
        }
        if (!Usuario::semCpf($usuario)) {
            return $this->erro('cpf_ja_informado', self::MSG_JA_INFORMADO);
        }

        $dono = $this->usuarios->findByCpf($cpf);
        if ($dono && (int) $dono['id'] !== $usuarioId) {
            return $this->erro('cpf_em_uso', self::MSG_EM_USO);
        }

        $pdo = Database::connection();
        $transacaoPropria = !$pdo->inTransaction();
        if ($transacaoPropria) {
            $pdo->beginTransaction();
        }

        try {
            // UPDATE condicional: duas gravações simultâneas não sobrescrevem uma à outra.
            $stmt = $pdo->prepare(
                "UPDATE usuarios
                    SET cpf = :cpf,
                        cadastro_status = CASE WHEN cadastro_status = 'pendente' AND cadastro_origem = 'google' THEN 'completo' ELSE cadastro_status END,
                        updated_at = NOW()
                  WHERE id = :id
                    AND deleted_at IS NULL
                    AND (cpf IS NULL OR cpf = '')"
            );
            $stmt->execute(array('cpf' => $cpf, 'id' => $usuarioId));
            if ($stmt->rowCount() === 0) {
                if ($transacaoPropria) {
                    $pdo->rollBack();
                }
                return $this->erro('cpf_ja_informado', self::MSG_JA_INFORMADO);
            }

            $participantes = $this->propagarParaParticipantes($usuarioId, $cpf);

            (new AuditService())->record('conta.cpf_informado', 'usuario', $usuarioId, array(
                'canal' => (string) $canal,
                'participantes_atualizados' => $participantes,
            ), $usuarioId, $ipAddress, $userAgent);

            if ($transacaoPropria) {
                $pdo->commit();
            }
        } catch (PDOException $e) {
            if ($transacaoPropria && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ((string) $e->getCode() === '23000') {
                return $this->erro('cpf_em_uso', self::MSG_EM_USO);
            }
            throw $e;
        }

        if (isset($_SESSION) && (int) Session::get('usuario_id', 0) === $usuarioId) {
            Session::put('usuario_cpf', $cpf);
        }

        return array('ok' => true, 'cpf' => $cpf, 'liberados' => $this->liberarCertificados($usuarioId));
    }

    /**
     * Efeitos de uma conta passar a ter CPF por outro caminho (edição pelo admin):
     * participantes sem CPF recebem o da conta e os certificados retidos são liberados.
     */
    public function aposCpfDefinido($usuarioId, $cpf)
    {
        $cpf = Usuario::normalizarCpf($cpf);
        if ($cpf === null) {
            return 0;
        }
        $this->propagarParaParticipantes((int) $usuarioId, $cpf);

        return $this->liberarCertificados((int) $usuarioId);
    }

    private function propagarParaParticipantes($usuarioId, $cpf)
    {
        $stmt = Database::connection()->prepare(
            "UPDATE participantes_pedido
                SET cpf = :cpf, updated_at = NOW()
              WHERE usuario_id = :id
                AND deleted_at IS NULL
                AND (cpf IS NULL OR cpf = '')"
        );
        $stmt->execute(array('cpf' => $cpf, 'id' => (int) $usuarioId));

        return $stmt->rowCount();
    }

    /** Falha aqui fica registrada na retenção e no log; o CPF continua salvo. */
    private function liberarCertificados($usuarioId)
    {
        try {
            $resultado = (new CertificadoRetencaoService())->liberarDoUsuario((int) $usuarioId);
            return isset($resultado['emitidos']) ? (int) $resultado['emitidos'] : 0;
        } catch (\Throwable $e) {
            Logger::error('CPF informado: falha ao liberar certificados retidos.', array('usuario_id' => (int) $usuarioId, 'erro' => $e->getMessage()));
            return 0;
        }
    }

    private function erro($codigo, $mensagem)
    {
        return array('ok' => false, 'erro' => $codigo, 'message' => $mensagem);
    }
}
