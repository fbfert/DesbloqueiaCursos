<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class RepasseDocumento
{
    public function create(array $data)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO repasses_documentos
             (repasse_professor_id, tipo_documento, numero_documento, arquivo_caminho, arquivo_nome_original, arquivo_tipo,
              status, observacao, enviado_por_usuario_id, validado_por_usuario_id, validado_em, created_at, updated_at, deleted_at)
             VALUES
             (:repasse_professor_id, :tipo_documento, :numero_documento, :arquivo_caminho, :arquivo_nome_original, :arquivo_tipo,
              :status, :observacao, :enviado_por_usuario_id, :validado_por_usuario_id, :validado_em, NOW(), NOW(), NULL)'
        );

        $stmt->execute(array(
            'repasse_professor_id' => $data['repasse_professor_id'],
            'tipo_documento' => isset($data['tipo_documento']) ? $data['tipo_documento'] : 'outro',
            'numero_documento' => isset($data['numero_documento']) ? $data['numero_documento'] : null,
            'arquivo_caminho' => $data['arquivo_caminho'],
            'arquivo_nome_original' => $data['arquivo_nome_original'],
            'arquivo_tipo' => isset($data['arquivo_tipo']) ? $data['arquivo_tipo'] : null,
            'status' => isset($data['status']) ? $data['status'] : 'recebido',
            'observacao' => isset($data['observacao']) ? $data['observacao'] : null,
            'enviado_por_usuario_id' => isset($data['enviado_por_usuario_id']) ? $data['enviado_por_usuario_id'] : null,
            'validado_por_usuario_id' => isset($data['validado_por_usuario_id']) ? $data['validado_por_usuario_id'] : null,
            'validado_em' => isset($data['validado_em']) ? $data['validado_em'] : null,
        ));

        return (int) Database::connection()->lastInsertId();
    }

    public function forRepasse($repasseId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM repasses_documentos
             WHERE repasse_professor_id = :repasse_professor_id
               AND deleted_at IS NULL
             ORDER BY id DESC'
        );

        $stmt->execute(array('repasse_professor_id' => $repasseId));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
