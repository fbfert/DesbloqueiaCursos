<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class PagamentoProfessor
{
    public function create(array $data)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO pagamentos_professores
             (repasse_professor_id, data_pagamento, valor, metodo, comprovante_caminho, comprovante_nome_original, referencia_bancaria,
              status, created_at, updated_at, deleted_at)
             VALUES
             (:repasse_professor_id, :data_pagamento, :valor, :metodo, :comprovante_caminho, :comprovante_nome_original, :referencia_bancaria,
              :status, NOW(), NOW(), NULL)'
        );

        $stmt->execute(array(
            'repasse_professor_id' => $data['repasse_professor_id'],
            'data_pagamento' => isset($data['data_pagamento']) ? $data['data_pagamento'] : null,
            'valor' => $data['valor'],
            'metodo' => isset($data['metodo']) ? $data['metodo'] : 'outro',
            'comprovante_caminho' => isset($data['comprovante_caminho']) ? $data['comprovante_caminho'] : null,
            'comprovante_nome_original' => isset($data['comprovante_nome_original']) ? $data['comprovante_nome_original'] : null,
            'referencia_bancaria' => isset($data['referencia_bancaria']) ? $data['referencia_bancaria'] : null,
            'status' => isset($data['status']) ? $data['status'] : 'pago',
        ));

        return (int) Database::connection()->lastInsertId();
    }

    public function findById($id)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM pagamentos_professores
             WHERE id = :id
               AND deleted_at IS NULL
             LIMIT 1'
        );

        $stmt->execute(array('id' => $id));
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function forRepasse($repasseId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM pagamentos_professores
             WHERE repasse_professor_id = :repasse_professor_id
               AND deleted_at IS NULL
             ORDER BY id DESC'
        );

        $stmt->execute(array('repasse_professor_id' => $repasseId));

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
