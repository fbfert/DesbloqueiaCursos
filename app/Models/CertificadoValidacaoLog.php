<?php

namespace App\Models;

use App\Core\Database;

class CertificadoValidacaoLog
{
    public function create(array $data)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO certificados_validacao_logs
             (certificado_id, codigo, cpf_informado, resultado, ip_address, user_agent, created_at)
             VALUES
             (:certificado_id, :codigo, :cpf_informado, :resultado, :ip_address, :user_agent, NOW())'
        );
        $stmt->execute(array(
            'certificado_id' => isset($data['certificado_id']) ? $data['certificado_id'] : null,
            'codigo' => $data['codigo'],
            'cpf_informado' => isset($data['cpf_informado']) ? $data['cpf_informado'] : null,
            'resultado' => $data['resultado'],
            'ip_address' => isset($data['ip_address']) ? $data['ip_address'] : null,
            'user_agent' => isset($data['user_agent']) ? $data['user_agent'] : null,
        ));

        return (int) Database::connection()->lastInsertId();
    }
}
