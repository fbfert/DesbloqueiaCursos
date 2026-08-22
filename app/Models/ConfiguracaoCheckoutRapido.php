<?php

namespace App\Models;

use App\Core\Database;
use Exception;

/**
 * Linha unica de configuracao do checkout rapido (id = 1), no mesmo padrao
 * das demais tabelas `configuracoes_*` do projeto.
 *
 * Se a tabela ainda nao existe (migracao 071 nao aplicada), find() devolve
 * null em vez de estourar — assim o sistema segue no fluxo antigo.
 */
class ConfiguracaoCheckoutRapido
{
    public function find()
    {
        try {
            $stmt = Database::connection()->query(
                'SELECT * FROM configuracoes_checkout_rapido WHERE id = 1 LIMIT 1'
            );

            $registro = $stmt->fetch(\PDO::FETCH_ASSOC);

            return $registro ?: null;
        } catch (Exception $exception) {
            return null;
        }
    }

    public function update(array $data)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE configuracoes_checkout_rapido
                SET ativo = :ativo,
                    cursos_habilitados = :cursos_habilitados,
                    pix_expira_minutos = :pix_expira_minutos,
                    polling_intervalo_segundos = :polling_intervalo_segundos,
                    token_acesso_validade_horas = :token_acesso_validade_horas,
                    google_ads_conversion_id = :google_ads_conversion_id,
                    google_ads_conversion_label = :google_ads_conversion_label,
                    google_ads_ativo = :google_ads_ativo,
                    whatsapp_provider = :whatsapp_provider,
                    whatsapp_ativo = :whatsapp_ativo,
                    texto_lgpd = :texto_lgpd,
                    updated_at = NOW()
              WHERE id = 1'
        );

        return $stmt->execute(array(
            'ativo' => !empty($data['ativo']) ? 1 : 0,
            'cursos_habilitados' => isset($data['cursos_habilitados']) ? $data['cursos_habilitados'] : null,
            'pix_expira_minutos' => (int) ($data['pix_expira_minutos'] ?? 30),
            'polling_intervalo_segundos' => (int) ($data['polling_intervalo_segundos'] ?? 3),
            'token_acesso_validade_horas' => (int) ($data['token_acesso_validade_horas'] ?? 168),
            'google_ads_conversion_id' => isset($data['google_ads_conversion_id']) ? $data['google_ads_conversion_id'] : null,
            'google_ads_conversion_label' => isset($data['google_ads_conversion_label']) ? $data['google_ads_conversion_label'] : null,
            'google_ads_ativo' => !empty($data['google_ads_ativo']) ? 1 : 0,
            'whatsapp_provider' => isset($data['whatsapp_provider']) ? $data['whatsapp_provider'] : null,
            'whatsapp_ativo' => !empty($data['whatsapp_ativo']) ? 1 : 0,
            'texto_lgpd' => isset($data['texto_lgpd']) ? $data['texto_lgpd'] : null,
        ));
    }
}
