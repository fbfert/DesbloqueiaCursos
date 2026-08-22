<?php

namespace App\Services;

use App\Core\Env;
use App\Models\ConfiguracaoCheckoutRapido;

/**
 * Feature flag e parametros do checkout rapido.
 *
 * Precedencia (mesmo padrao ja usado pelo gateway de pagamento):
 *   1. tabela configuracoes_checkout_rapido (editavel sem deploy)
 *   2. .env (CHECKOUT_RAPIDO_ATIVO=true)
 *   3. desligado
 *
 * Com a flag desligada NADA muda: o checkout antigo continua sendo o unico
 * caminho, e as rotas novas respondem 404.
 */
class CheckoutRapidoConfigService
{
    private $model;
    private $cache;

    public function __construct()
    {
        $this->model = new ConfiguracaoCheckoutRapido();
    }

    public function config()
    {
        if (is_array($this->cache)) {
            return $this->cache;
        }

        $registro = $this->model->find();

        if (is_array($registro)) {
            $this->cache = array(
                'origem' => 'database',
                'ativo' => !empty($registro['ativo']),
                'cursos_habilitados' => $this->listaDeCursos($registro['cursos_habilitados'] ?? ''),
                'pix_expira_minutos' => max(5, (int) ($registro['pix_expira_minutos'] ?? 30)),
                'polling_intervalo_segundos' => max(2, (int) ($registro['polling_intervalo_segundos'] ?? 3)),
                'token_acesso_validade_horas' => max(1, (int) ($registro['token_acesso_validade_horas'] ?? 168)),
                'google_ads_ativo' => !empty($registro['google_ads_ativo']),
                'google_ads_conversion_id' => trim((string) ($registro['google_ads_conversion_id'] ?? '')),
                'google_ads_conversion_label' => trim((string) ($registro['google_ads_conversion_label'] ?? '')),
                'whatsapp_ativo' => !empty($registro['whatsapp_ativo']),
                'whatsapp_provider' => trim((string) ($registro['whatsapp_provider'] ?? '')),
                'texto_lgpd' => trim((string) ($registro['texto_lgpd'] ?? '')),
            );

            return $this->cache;
        }

        // Sem tabela (migracao 071 nao aplicada) ou sem linha: cai no .env.
        $this->cache = array(
            'origem' => 'env',
            'ativo' => Env::get('CHECKOUT_RAPIDO_ATIVO', 'false') === 'true',
            'cursos_habilitados' => $this->listaDeCursos(Env::get('CHECKOUT_RAPIDO_CURSOS', '')),
            'pix_expira_minutos' => max(5, (int) Env::get('CHECKOUT_RAPIDO_PIX_EXPIRA_MINUTOS', '30')),
            'polling_intervalo_segundos' => max(2, (int) Env::get('CHECKOUT_RAPIDO_POLLING_SEGUNDOS', '3')),
            'token_acesso_validade_horas' => max(1, (int) Env::get('CHECKOUT_RAPIDO_TOKEN_HORAS', '168')),
            'google_ads_ativo' => Env::get('GOOGLE_ADS_ATIVO', 'false') === 'true',
            'google_ads_conversion_id' => trim((string) Env::get('GOOGLE_ADS_CONVERSION_ID', '')),
            'google_ads_conversion_label' => trim((string) Env::get('GOOGLE_ADS_CONVERSION_LABEL', '')),
            'whatsapp_ativo' => Env::get('WHATSAPP_ATIVO', 'false') === 'true',
            'whatsapp_provider' => trim((string) Env::get('WHATSAPP_PROVIDER', '')),
            'texto_lgpd' => '',
        );

        return $this->cache;
    }

    /**
     * A flag esta ligada? Quando $cursoId e informado e ha lista de cursos
     * habilitados, o curso precisa estar na lista — assim da para testar o
     * fluxo novo em um curso so, com trafego pago, antes de abrir para todos.
     */
    public function ativo($cursoId = null)
    {
        $config = $this->config();

        if (empty($config['ativo'])) {
            return false;
        }

        $cursoId = (int) $cursoId;
        if ($cursoId <= 0 || empty($config['cursos_habilitados'])) {
            return !empty($config['ativo']);
        }

        return in_array($cursoId, $config['cursos_habilitados'], true);
    }

    public function pixExpiraMinutos()
    {
        $config = $this->config();

        return (int) $config['pix_expira_minutos'];
    }

    public function pollingIntervaloSegundos()
    {
        $config = $this->config();

        return (int) $config['polling_intervalo_segundos'];
    }

    public function tokenAcessoValidadeHoras()
    {
        $config = $this->config();

        return (int) $config['token_acesso_validade_horas'];
    }

    public function textoLgpd()
    {
        $config = $this->config();
        $texto = trim((string) $config['texto_lgpd']);

        if ($texto !== '') {
            return $texto;
        }

        return 'Usamos seu e-mail e WhatsApp apenas para enviar o acesso ao curso e o comprovante da compra. Nada de spam.';
    }

    private function listaDeCursos($valor)
    {
        $valor = trim((string) $valor);

        if ($valor === '') {
            return array();
        }

        $ids = array();
        foreach (preg_split('/[,;\s]+/', $valor) as $parte) {
            $id = (int) trim($parte);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }
}
