<?php

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Histórico de notificações do aluno (tabela app_notificacoes, migração 082).
 * Toda notificação é gravada antes de qualquer tentativa de envio por FCM.
 */
class AppNotificacao
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

    public function create($usuarioId, $tipo, $titulo, $corpo, array $dados, $agora)
    {
        $stmt = $this->db()->prepare(
            'INSERT INTO app_notificacoes (usuario_id, tipo, titulo, corpo, dados, envio_status, tentativas, created_at)
             VALUES (:u, :t, :ti, :c, :d, "pendente", 0, :a)'
        );
        $stmt->execute(array(
            'u' => (int) $usuarioId,
            't' => mb_substr((string) $tipo, 0, 40),
            'ti' => mb_substr((string) $titulo, 0, 150),
            'c' => mb_substr((string) $corpo, 0, 500),
            'd' => json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'a' => $agora,
        ));

        return (int) $this->db()->lastInsertId();
    }

    public function findById($id)
    {
        $stmt = $this->db()->prepare('SELECT * FROM app_notificacoes WHERE id = :id LIMIT 1');
        $stmt->execute(array('id' => (int) $id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function contarDoUsuario($usuarioId)
    {
        $stmt = $this->db()->prepare('SELECT COUNT(*) FROM app_notificacoes WHERE usuario_id = :u');
        $stmt->execute(array('u' => (int) $usuarioId));

        return (int) $stmt->fetchColumn();
    }

    public function listarDoUsuario($usuarioId, $limite, $offset)
    {
        $stmt = $this->db()->prepare(
            'SELECT * FROM app_notificacoes WHERE usuario_id = :u ORDER BY id DESC
              LIMIT ' . max(1, (int) $limite) . ' OFFSET ' . max(0, (int) $offset)
        );
        $stmt->execute(array('u' => (int) $usuarioId));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function marcarLida($id, $usuarioId, $agora)
    {
        $stmt = $this->db()->prepare(
            'UPDATE app_notificacoes SET lida_em = COALESCE(lida_em, :a) WHERE id = :id AND usuario_id = :u'
        );
        $stmt->execute(array('a' => $agora, 'id' => (int) $id, 'u' => (int) $usuarioId));
    }

    public function atualizarEnvio($id, $status, $erro, $agora)
    {
        $stmt = $this->db()->prepare(
            'UPDATE app_notificacoes
                SET envio_status = :s, tentativas = tentativas + 1, ultimo_erro = :e,
                    enviado_em = CASE WHEN :s2 = "enviado" THEN :a ELSE enviado_em END
              WHERE id = :id'
        );
        $stmt->execute(array(
            's' => (string) $status,
            's2' => (string) $status,
            'e' => $erro !== null ? mb_substr((string) $erro, 0, 500) : null,
            'a' => $agora,
            'id' => (int) $id,
        ));
    }

    /** Pendentes e falhas abaixo do limite de tentativas, criadas depois de $desde. */
    public function paraReenvio($maxTentativas, $desde, $limite)
    {
        $stmt = $this->db()->prepare(
            'SELECT * FROM app_notificacoes
              WHERE envio_status IN ("pendente", "falhou") AND tentativas < :m AND created_at >= :d
              ORDER BY id ASC
              LIMIT ' . max(1, (int) $limite)
        );
        $stmt->execute(array('m' => (int) $maxTentativas, 'd' => $desde));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Pendentes antigas demais para ainda fazer sentido: viram falha sem envio. */
    public function expirarPendentes($antesDe)
    {
        $stmt = $this->db()->prepare(
            'UPDATE app_notificacoes SET envio_status = "falhou", ultimo_erro = "expirada_sem_envio"
              WHERE envio_status IN ("pendente", "falhou") AND created_at < :d AND (ultimo_erro IS NULL OR ultimo_erro <> "expirada_sem_envio")'
        );
        $stmt->execute(array('d' => $antesDe));

        return $stmt->rowCount();
    }

    /**
     * Vários destinatários de uma vez (conteúdo novo): só grava, o cron envia.
     *
     * @param array $destinos usuario_id => dados (JSON) daquele destinatário
     */
    public function criarEmLote(array $destinos, $tipo, $titulo, $corpo, $agora)
    {
        $total = 0;
        foreach (array_chunk($destinos, 200, true) as $bloco) {
            $valores = array();
            $params = array();
            $i = 0;
            foreach ($bloco as $usuarioId => $dados) {
                if ((int) $usuarioId <= 0) {
                    continue;
                }
                $valores[] = '(:u' . $i . ', :t, :ti, :c, :d' . $i . ', "pendente", 0, :a)';
                $params['u' . $i] = (int) $usuarioId;
                $params['d' . $i] = json_encode((array) $dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $i++;
            }
            if (empty($valores)) {
                continue;
            }
            $params['t'] = mb_substr((string) $tipo, 0, 40);
            $params['ti'] = mb_substr((string) $titulo, 0, 150);
            $params['c'] = mb_substr((string) $corpo, 0, 500);
            $params['a'] = $agora;

            $stmt = $this->db()->prepare(
                'INSERT INTO app_notificacoes (usuario_id, tipo, titulo, corpo, dados, envio_status, tentativas, created_at) VALUES '
                . implode(', ', $valores)
            );
            $stmt->execute($params);
            $total += $stmt->rowCount();
        }

        return $total;
    }
}
