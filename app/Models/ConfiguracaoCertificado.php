<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class ConfiguracaoCertificado
{
    public function current()
    {
        $stmt = Database::connection()->query(
            'SELECT *
             FROM configuracoes_certificados
             WHERE deleted_at IS NULL
             ORDER BY id DESC
             LIMIT 1'
        );

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function save(array $data)
    {
        $current = $this->current();
        $payload = array(
            'prefixo_certificado' => isset($data['prefixo_certificado']) && trim((string) $data['prefixo_certificado']) !== '' ? strtoupper(trim((string) $data['prefixo_certificado'])) : 'PRC',
            'titulo_padrao' => isset($data['titulo_padrao']) ? trim((string) $data['titulo_padrao']) : null,
            'texto_validacao_publica' => isset($data['texto_validacao_publica']) ? trim((string) $data['texto_validacao_publica']) : null,
        );

        if ($current) {
            $stmt = Database::connection()->prepare(
                'UPDATE configuracoes_certificados
                 SET prefixo_certificado = :prefixo_certificado,
                     titulo_padrao = :titulo_padrao,
                     texto_validacao_publica = :texto_validacao_publica,
                     updated_at = NOW()
                 WHERE id = :id'
            );

            $stmt->execute(array_merge($payload, array('id' => (int) $current['id'])));
            return (int) $current['id'];
        }

        $stmt = Database::connection()->prepare(
            'INSERT INTO configuracoes_certificados
             (prefixo_certificado, titulo_padrao, texto_validacao_publica, created_at, updated_at, deleted_at)
             VALUES
             (:prefixo_certificado, :titulo_padrao, :texto_validacao_publica, NOW(), NOW(), NULL)'
        );

        $stmt->execute($payload);

        return (int) Database::connection()->lastInsertId();
    }
}
