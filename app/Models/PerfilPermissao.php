<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class PerfilPermissao
{
    public function forPerfil($perfilId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT per.*
             FROM permissoes per
             INNER JOIN perfil_permissoes pp ON pp.permissao_id = per.id
             WHERE pp.perfil_id = :perfil_id
             ORDER BY per.modulo ASC, per.acao ASC, per.id ASC'
        );
        $stmt->execute(array('perfil_id' => $perfilId));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function sync($perfilId, array $permissaoIds)
    {
        $permissaoIds = array_values(array_unique(array_map('intval', $permissaoIds)));
        $pdo = Database::connection();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $delete = $pdo->prepare('DELETE FROM perfil_permissoes WHERE perfil_id = :perfil_id');
            $delete->execute(array('perfil_id' => $perfilId));

            if ($permissaoIds) {
                $insert = $pdo->prepare(
                    'INSERT INTO perfil_permissoes (perfil_id, permissao_id, created_at)
                     VALUES (:perfil_id, :permissao_id, NOW())'
                );

                foreach ($permissaoIds as $permissaoId) {
                    $insert->execute(array(
                        'perfil_id' => $perfilId,
                        'permissao_id' => $permissaoId,
                    ));
                }
            }

            if ($ownsTransaction) {
                $pdo->commit();
            }
        } catch (\Exception $exception) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }
}
