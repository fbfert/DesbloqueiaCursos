<?php

namespace App\Models;

use App\Core\Database;

class CertificadoHistorico
{
    public function create(array $data)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO certificados_historico
             (certificado_id, status_anterior, status_novo, observacao, alterado_por_usuario_id, created_at)
             VALUES
             (:certificado_id, :status_anterior, :status_novo, :observacao, :alterado_por_usuario_id, NOW())'
        );
        $stmt->execute(array(
            'certificado_id' => $data['certificado_id'],
            'status_anterior' => isset($data['status_anterior']) ? $data['status_anterior'] : null,
            'status_novo' => $data['status_novo'],
            'observacao' => isset($data['observacao']) ? $data['observacao'] : null,
            'alterado_por_usuario_id' => isset($data['alterado_por_usuario_id']) ? $data['alterado_por_usuario_id'] : null,
        ));

        return (int) Database::connection()->lastInsertId();
    }
}
