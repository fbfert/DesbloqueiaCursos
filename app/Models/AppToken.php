<?php

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Tokens opacos do app (tabela app_tokens, migração 082). Só o SHA-256 do token
 * é gravado. Horários vêm prontos do serviço (fuso da aplicação), nunca NOW().
 */
class AppToken
{
    private $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    private function db()
    {
        return $this->pdo ?: Database::connection();
    }

    public function create(array $dados)
    {
        $stmt = $this->db()->prepare(
            'INSERT INTO app_tokens
                (usuario_id, device_id, device_name, tipo, token_hash, familia, parent_id,
                 expira_em, ip, user_agent, created_at)
             VALUES
                (:usuario_id, :device_id, :device_name, :tipo, :token_hash, :familia, :parent_id,
                 :expira_em, :ip, :user_agent, :created_at)'
        );
        $stmt->execute(array(
            'usuario_id' => (int) $dados['usuario_id'],
            'device_id' => (string) $dados['device_id'],
            'device_name' => isset($dados['device_name']) ? $dados['device_name'] : null,
            'tipo' => (string) $dados['tipo'],
            'token_hash' => (string) $dados['token_hash'],
            'familia' => (string) $dados['familia'],
            'parent_id' => isset($dados['parent_id']) ? $dados['parent_id'] : null,
            'expira_em' => (string) $dados['expira_em'],
            'ip' => isset($dados['ip']) ? $dados['ip'] : null,
            'user_agent' => isset($dados['user_agent']) ? $dados['user_agent'] : null,
            'created_at' => (string) $dados['created_at'],
        ));

        return (int) $this->db()->lastInsertId();
    }

    public function findByHash($hash)
    {
        $stmt = $this->db()->prepare('SELECT * FROM app_tokens WHERE token_hash = :h LIMIT 1');
        $stmt->execute(array('h' => (string) $hash));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findById($id)
    {
        $stmt = $this->db()->prepare('SELECT * FROM app_tokens WHERE id = :id LIMIT 1');
        $stmt->execute(array('id' => (int) $id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Marca o refresh como consumido SÓ se ainda não foi (corrida entre duas
     * renovações simultâneas: apenas uma vence). Devolve true para quem venceu.
     */
    public function marcarConsumido($id, $agora)
    {
        $stmt = $this->db()->prepare(
            'UPDATE app_tokens SET consumido_em = :agora, ultimo_uso_em = :agora2
              WHERE id = :id AND consumido_em IS NULL AND revogado_em IS NULL'
        );
        $stmt->execute(array('agora' => $agora, 'agora2' => $agora, 'id' => (int) $id));

        return $stmt->rowCount() === 1;
    }

    public function definirSucessor($id, $sucessorId)
    {
        $stmt = $this->db()->prepare('UPDATE app_tokens SET substituido_por_id = :s WHERE id = :id');
        $stmt->execute(array('s' => (int) $sucessorId, 'id' => (int) $id));
    }

    public function tocarUso($id, $agora)
    {
        $stmt = $this->db()->prepare('UPDATE app_tokens SET ultimo_uso_em = :agora WHERE id = :id');
        $stmt->execute(array('agora' => $agora, 'id' => (int) $id));
    }

    public function revogarDispositivo($deviceId, $agora, $motivo)
    {
        $stmt = $this->db()->prepare(
            'UPDATE app_tokens SET revogado_em = :agora, revogado_motivo = :motivo
              WHERE device_id = :d AND revogado_em IS NULL'
        );
        $stmt->execute(array('agora' => $agora, 'motivo' => (string) $motivo, 'd' => (string) $deviceId));

        return $stmt->rowCount();
    }

    public function revogarFamilia($familia, $agora, $motivo)
    {
        $stmt = $this->db()->prepare(
            'UPDATE app_tokens SET revogado_em = :agora, revogado_motivo = :motivo
              WHERE familia = :f AND revogado_em IS NULL'
        );
        $stmt->execute(array('agora' => $agora, 'motivo' => (string) $motivo, 'f' => (string) $familia));

        return $stmt->rowCount();
    }

    /** Revoga os access tokens ainda válidos da família, exceto o recém-emitido. */
    public function revogarAccessDaFamiliaExceto($familia, $exceptoId, $agora, $motivo)
    {
        $stmt = $this->db()->prepare(
            'UPDATE app_tokens SET revogado_em = :agora, revogado_motivo = :motivo
              WHERE familia = :f AND tipo = "access" AND id <> :id AND revogado_em IS NULL'
        );
        $stmt->execute(array('agora' => $agora, 'motivo' => (string) $motivo, 'f' => (string) $familia, 'id' => (int) $exceptoId));

        return $stmt->rowCount();
    }

    public function revogarUsuarioExcetoDispositivo($usuarioId, $deviceIdAtual, $agora, $motivo)
    {
        $stmt = $this->db()->prepare(
            'UPDATE app_tokens SET revogado_em = :agora, revogado_motivo = :motivo
              WHERE usuario_id = :u AND device_id <> :d AND revogado_em IS NULL'
        );
        $stmt->execute(array('agora' => $agora, 'motivo' => (string) $motivo, 'u' => (int) $usuarioId, 'd' => (string) $deviceIdAtual));

        return $stmt->rowCount();
    }

    /** Limpeza: remove tokens vencidos ou revogados há mais de N dias. */
    public function limparAntigos($limite)
    {
        $stmt = $this->db()->prepare(
            'DELETE FROM app_tokens WHERE expira_em < :l1 OR (revogado_em IS NOT NULL AND revogado_em < :l2)'
        );
        $stmt->execute(array('l1' => $limite, 'l2' => $limite));

        return $stmt->rowCount();
    }
}
