<?php

namespace App\Models;

use App\Core\Database;
use App\Support\AppApi\Tempo;
use PDO;

/**
 * Vínculo de um usuário com um provedor de identidade externo (migração 083).
 * Hoje só 'google'. A pessoa é identificada pelo `sub` do provedor, que não muda.
 */
class UsuarioIdentidade
{
    public function findByProvedorSub($provedor, $sub)
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM usuario_identidades WHERE provedor = :provedor AND sub = :sub LIMIT 1'
        );
        $stmt->execute(array('provedor' => (string) $provedor, 'sub' => (string) $sub));

        $linha = $stmt->fetch(PDO::FETCH_ASSOC);

        return $linha ?: null;
    }

    public function findByUsuario($usuarioId, $provedor)
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM usuario_identidades WHERE usuario_id = :usuario_id AND provedor = :provedor LIMIT 1'
        );
        $stmt->execute(array('usuario_id' => (int) $usuarioId, 'provedor' => (string) $provedor));

        $linha = $stmt->fetch(PDO::FETCH_ASSOC);

        return $linha ?: null;
    }

    /**
     * Cria o vínculo. Lança PDOException (23000) se o `sub` já estiver vinculado ou
     * se o usuário já tiver uma conta desse provedor — quem chama decide o que fazer.
     */
    public function vincular($usuarioId, $provedor, $sub, $email)
    {
        $agora = Tempo::sql();
        $stmt = Database::connection()->prepare(
            'INSERT INTO usuario_identidades (usuario_id, provedor, sub, email, created_at, ultimo_uso_em)
             VALUES (:usuario_id, :provedor, :sub, :email, :created_at, :ultimo_uso_em)'
        );
        $stmt->execute(array(
            'usuario_id' => (int) $usuarioId,
            'provedor' => (string) $provedor,
            'sub' => (string) $sub,
            'email' => $email !== null && $email !== '' ? mb_substr(strtolower(trim((string) $email)), 0, 191) : null,
            'created_at' => $agora,
            'ultimo_uso_em' => $agora,
        ));

        return (int) Database::connection()->lastInsertId();
    }

    /** Atualiza o último uso e o e-mail informado pelo provedor neste login. */
    public function tocarUso($id, $email)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE usuario_identidades
                SET ultimo_uso_em = :agora,
                    email = COALESCE(:email, email)
              WHERE id = :id'
        );
        $stmt->execute(array(
            'agora' => Tempo::sql(),
            'email' => $email !== null && $email !== '' ? mb_substr(strtolower(trim((string) $email)), 0, 191) : null,
            'id' => (int) $id,
        ));
    }

    public function remover($id)
    {
        $stmt = Database::connection()->prepare('DELETE FROM usuario_identidades WHERE id = :id');
        $stmt->execute(array('id' => (int) $id));

        return $stmt->rowCount() > 0;
    }
}
