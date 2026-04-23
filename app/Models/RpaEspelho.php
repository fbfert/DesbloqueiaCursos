<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class RpaEspelho
{
    public function create(array $data)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO rpa_espelhos
             (repasse_professor_id, usuario_id, competencia, valor_bruto, valor_retenido, valor_liquido, cpf, nome,
              arquivo_caminho, arquivo_nome_original, status, created_at, updated_at, deleted_at)
             VALUES
             (:repasse_professor_id, :usuario_id, :competencia, :valor_bruto, :valor_retenido, :valor_liquido, :cpf, :nome,
              :arquivo_caminho, :arquivo_nome_original, :status, NOW(), NOW(), NULL)'
        );

        $stmt->execute(array(
            'repasse_professor_id' => $data['repasse_professor_id'],
            'usuario_id' => $data['usuario_id'],
            'competencia' => $data['competencia'],
            'valor_bruto' => $data['valor_bruto'],
            'valor_retenido' => $data['valor_retenido'],
            'valor_liquido' => $data['valor_liquido'],
            'cpf' => isset($data['cpf']) ? $data['cpf'] : null,
            'nome' => $data['nome'],
            'arquivo_caminho' => $data['arquivo_caminho'],
            'arquivo_nome_original' => $data['arquivo_nome_original'],
            'status' => isset($data['status']) ? $data['status'] : 'gerado',
        ));

        return (int) Database::connection()->lastInsertId();
    }

    public function forProfessor($usuarioId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM rpa_espelhos
             WHERE usuario_id = :usuario_id
               AND deleted_at IS NULL
             ORDER BY id DESC'
        );

        $stmt->execute(array('usuario_id' => $usuarioId));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
