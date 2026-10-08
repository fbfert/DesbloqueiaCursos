<?php

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Dispositivos do app com token FCM (tabela app_dispositivos, migração 082).
 * Um device_id pertence a um usuário por vez: o upsert move o aparelho para
 * quem fez login nele por último.
 */
class AppDispositivo
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

    public function upsert($usuarioId, $deviceId, $fcmToken, $plataforma, $appVersao, $agora)
    {
        $stmt = $this->db()->prepare(
            'INSERT INTO app_dispositivos
                (usuario_id, device_id, fcm_token, plataforma, app_versao, ativo, desativado_motivo, created_at, updated_at)
             VALUES (:u, :d, :t, :p, :v, 1, NULL, :c, :a)
             ON DUPLICATE KEY UPDATE
                usuario_id = VALUES(usuario_id),
                fcm_token = VALUES(fcm_token),
                plataforma = VALUES(plataforma),
                app_versao = VALUES(app_versao),
                ativo = 1,
                desativado_motivo = NULL,
                updated_at = VALUES(updated_at)'
        );
        $stmt->execute(array(
            'u' => (int) $usuarioId,
            'd' => (string) $deviceId,
            't' => $fcmToken !== null ? (string) $fcmToken : null,
            'p' => (string) $plataforma,
            'v' => $appVersao !== null ? (string) $appVersao : null,
            'c' => $agora,
            'a' => $agora,
        ));
    }

    /** Remove o registro de push do aparelho (só do próprio usuário). */
    public function removerDoUsuario($usuarioId, $deviceId)
    {
        $stmt = $this->db()->prepare('DELETE FROM app_dispositivos WHERE usuario_id = :u AND device_id = :d');
        $stmt->execute(array('u' => (int) $usuarioId, 'd' => (string) $deviceId));

        return $stmt->rowCount();
    }

    /** Aparelhos que recebem push, do mais recente para o mais antigo. */
    public function ativosDoUsuario($usuarioId, $limite = 5)
    {
        $stmt = $this->db()->prepare(
            'SELECT * FROM app_dispositivos
              WHERE usuario_id = :u AND ativo = 1 AND fcm_token IS NOT NULL AND fcm_token <> ""
              ORDER BY updated_at DESC, id DESC
              LIMIT ' . max(1, (int) $limite)
        );
        $stmt->execute(array('u' => (int) $usuarioId));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function desativar($id, $motivo, $agora)
    {
        $stmt = $this->db()->prepare('UPDATE app_dispositivos SET ativo = 0, desativado_motivo = :m, updated_at = :a WHERE id = :id');
        $stmt->execute(array('m' => mb_substr((string) $motivo, 0, 60), 'a' => $agora, 'id' => (int) $id));
    }

    public function registrarEnvio($id, $agora)
    {
        $stmt = $this->db()->prepare('UPDATE app_dispositivos SET ultimo_envio_em = :a WHERE id = :id');
        $stmt->execute(array('a' => $agora, 'id' => (int) $id));
    }

    public function findByDeviceId($deviceId)
    {
        $stmt = $this->db()->prepare('SELECT * FROM app_dispositivos WHERE device_id = :d LIMIT 1');
        $stmt->execute(array('d' => (string) $deviceId));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}
