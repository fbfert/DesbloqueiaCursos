<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class ProfessorFiscal
{
    public function findById($id)
    {
        $stmt = Database::connection()->prepare(
            'SELECT pf.*, u.nome AS usuario_nome, u.email AS usuario_email
             FROM professores_fiscal pf
             INNER JOIN usuarios u ON u.id = pf.usuario_id
             WHERE pf.id = :id
               AND pf.deleted_at IS NULL
             LIMIT 1'
        );

        $stmt->execute(array('id' => $id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findByUsuarioId($usuarioId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM professores_fiscal
             WHERE usuario_id = :usuario_id
               AND deleted_at IS NULL
             LIMIT 1'
        );

        $stmt->execute(array('usuario_id' => $usuarioId));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function allActive()
    {
        $stmt = Database::connection()->query(
            'SELECT pf.*, u.nome AS usuario_nome, u.email AS usuario_email
             FROM professores_fiscal pf
             INNER JOIN usuarios u ON u.id = pf.usuario_id
             WHERE pf.deleted_at IS NULL
             ORDER BY u.nome ASC'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function save(array $data)
    {
        $current = $this->findByUsuarioId($data['usuario_id']);

        $payload = array(
            'usuario_id' => (int) $data['usuario_id'],
            'tipo_pessoa' => isset($data['tipo_pessoa']) && in_array($data['tipo_pessoa'], array('pf', 'pj'), true) ? $data['tipo_pessoa'] : 'pf',
            'cpf' => isset($data['cpf']) ? preg_replace('/\D+/', '', (string) $data['cpf']) : null,
            'cnpj' => isset($data['cnpj']) ? preg_replace('/\D+/', '', (string) $data['cnpj']) : null,
            'razao_social' => isset($data['razao_social']) ? trim((string) $data['razao_social']) : null,
            'nome_fantasia' => isset($data['nome_fantasia']) ? trim((string) $data['nome_fantasia']) : null,
            'inscricao_municipal' => isset($data['inscricao_municipal']) ? trim((string) $data['inscricao_municipal']) : null,
            'aliquota_retencao' => isset($data['aliquota_retencao']) ? (float) $data['aliquota_retencao'] : 0.00,
            'exige_nota_fiscal' => !empty($data['exige_nota_fiscal']) ? 1 : 0,
            'email_financeiro' => isset($data['email_financeiro']) ? trim((string) $data['email_financeiro']) : null,
            'observacao' => isset($data['observacao']) ? trim((string) $data['observacao']) : null,
            'status' => isset($data['status']) && in_array($data['status'], array('ativo', 'inativo'), true) ? $data['status'] : 'ativo',
        );

        if ($current) {
            $stmt = Database::connection()->prepare(
                'UPDATE professores_fiscal
                 SET tipo_pessoa = :tipo_pessoa,
                     cpf = :cpf,
                     cnpj = :cnpj,
                     razao_social = :razao_social,
                     nome_fantasia = :nome_fantasia,
                     inscricao_municipal = :inscricao_municipal,
                     aliquota_retencao = :aliquota_retencao,
                     exige_nota_fiscal = :exige_nota_fiscal,
                     email_financeiro = :email_financeiro,
                     observacao = :observacao,
                     status = :status,
                     updated_at = NOW()
                 WHERE id = :id'
            );

            $stmt->execute(array_merge($payload, array('id' => (int) $current['id'])));
            return (int) $current['id'];
        }

        $stmt = Database::connection()->prepare(
            'INSERT INTO professores_fiscal
             (usuario_id, tipo_pessoa, cpf, cnpj, razao_social, nome_fantasia, inscricao_municipal, aliquota_retencao, exige_nota_fiscal, email_financeiro, observacao, status, created_at, updated_at, deleted_at)
             VALUES
             (:usuario_id, :tipo_pessoa, :cpf, :cnpj, :razao_social, :nome_fantasia, :inscricao_municipal, :aliquota_retencao, :exige_nota_fiscal, :email_financeiro, :observacao, :status, NOW(), NOW(), NULL)'
        );

        $stmt->execute($payload);

        return (int) Database::connection()->lastInsertId();
    }

    public function softDelete($id)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE professores_fiscal
             SET deleted_at = NOW(),
                 updated_at = NOW()
             WHERE id = :id'
        );

        $stmt->execute(array('id' => $id));
    }
}
