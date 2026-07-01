<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class ConfiguracaoGlobal
{
    private $schemaEnsured = false;

    public function current()
    {
        $stmt = Database::connection()->query(
            'SELECT *
             FROM configuracoes_globais
             WHERE deleted_at IS NULL
             ORDER BY id DESC
             LIMIT 1'
        );

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function save(array $data)
    {
        $this->ensureSchema();

        $current = $this->current();

        $payload = array(
            'nome_fantasia' => trim((string) $data['nome_fantasia']),
            'razao_social' => isset($data['razao_social']) ? trim((string) $data['razao_social']) : null,
            'cnpj' => isset($data['cnpj']) ? preg_replace('/\D+/', '', (string) $data['cnpj']) : null,
            'cidade' => isset($data['cidade']) ? trim((string) $data['cidade']) : null,
            'uf' => isset($data['uf']) ? strtoupper(substr(trim((string) $data['uf']), 0, 2)) : null,
            'email_institucional' => isset($data['email_institucional']) ? trim((string) $data['email_institucional']) : null,
            'email_financeiro' => isset($data['email_financeiro']) ? trim((string) $data['email_financeiro']) : null,
            'email_suporte' => isset($data['email_suporte']) ? trim((string) $data['email_suporte']) : null,
            'email_certificados' => isset($data['email_certificados']) ? trim((string) $data['email_certificados']) : null,
            'telefone' => isset($data['telefone']) ? trim((string) $data['telefone']) : null,
            'logo_caminho' => isset($data['logo_caminho']) ? trim((string) $data['logo_caminho']) : null,
            'favicon_caminho' => isset($data['favicon_caminho']) ? trim((string) $data['favicon_caminho']) : null,
        );

        if ($current) {
            $stmt = Database::connection()->prepare(
                'UPDATE configuracoes_globais
                 SET nome_fantasia = :nome_fantasia,
                     razao_social = :razao_social,
                     cnpj = :cnpj,
                     cidade = :cidade,
                     uf = :uf,
                     email_institucional = :email_institucional,
                     email_financeiro = :email_financeiro,
                     email_suporte = :email_suporte,
                     email_certificados = :email_certificados,
                     telefone = :telefone,
                     logo_caminho = :logo_caminho,
                     favicon_caminho = :favicon_caminho,
                     updated_at = NOW()
                 WHERE id = :id'
            );

            $stmt->execute(array_merge($payload, array('id' => (int) $current['id'])));

            return (int) $current['id'];
        }

        $stmt = Database::connection()->prepare(
            'INSERT INTO configuracoes_globais
             (nome_fantasia, razao_social, cnpj, cidade, uf, email_institucional, email_financeiro, email_suporte, email_certificados, telefone, logo_caminho, favicon_caminho, created_at, updated_at, deleted_at)
             VALUES
             (:nome_fantasia, :razao_social, :cnpj, :cidade, :uf, :email_institucional, :email_financeiro, :email_suporte, :email_certificados, :telefone, :logo_caminho, :favicon_caminho, NOW(), NOW(), NULL)'
        );

        $stmt->execute($payload);

        return (int) Database::connection()->lastInsertId();
    }

    private function ensureSchema()
    {
        if ($this->schemaEnsured) {
            return;
        }

        $this->schemaEnsured = true;

        if (!$this->columnExists('favicon_caminho')) {
            $afterColumn = $this->columnExists('logo_caminho') ? 'logo_caminho' : 'telefone';
            Database::connection()->exec(
                'ALTER TABLE configuracoes_globais ADD COLUMN favicon_caminho VARCHAR(255) NULL AFTER ' . $afterColumn
            );
        }
    }

    private function columnExists($column)
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*)
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table_name
               AND COLUMN_NAME = :column_name'
        );

        $stmt->execute(array(
            'table_name' => 'configuracoes_globais',
            'column_name' => $column,
        ));

        return (int) $stmt->fetchColumn() > 0;
    }
}
