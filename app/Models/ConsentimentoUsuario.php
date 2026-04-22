<?php

namespace App\Models;

use App\Core\Database;

class ConsentimentoUsuario
{
    public function createForUser($usuarioId, array $consentimentos)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO usuario_consentimentos
             (usuario_id, tipo, versao, obrigatorio, consentido, ip_address, user_agent, created_at)
             VALUES
             (:usuario_id, :tipo, :versao, :obrigatorio, :consentido, :ip_address, :user_agent, NOW())'
        );

        foreach ($consentimentos as $consentimento) {
            $stmt->execute(array(
                'usuario_id' => $usuarioId,
                'tipo' => $consentimento['tipo'],
                'versao' => $consentimento['versao'],
                'obrigatorio' => !empty($consentimento['obrigatorio']) ? 1 : 0,
                'consentido' => !empty($consentimento['consentido']) ? 1 : 0,
                'ip_address' => isset($consentimento['ip_address']) ? $consentimento['ip_address'] : null,
                'user_agent' => isset($consentimento['user_agent']) ? $consentimento['user_agent'] : null,
            ));
        }
    }
}
