<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Session;
use App\Models\ConfiguracaoCheckoutRapido;
use App\Models\CursoEvento;
use App\Services\AuditService;
use App\Services\CheckoutFunilService;
use App\Services\CheckoutRapidoConfigService;
use App\Services\Payments\AbacatePayService;
use Throwable;

/**
 * Painel do checkout rapido: liga, desliga e mostra o funil.
 *
 * Toda alteracao da chave passa por AuditService com a acao
 * `checkout_rapido.configuracao.atualizada` — e e dessa mesma auditoria que
 * sai o historico exibido na tela. Ninguem liga ou desliga sem deixar rastro.
 */
class CheckoutRapidoController extends Controller
{
    private $configModel;
    private $configService;
    private $funil;
    private $cursoModel;
    private $auditService;

    public function __construct()
    {
        $this->configModel = new ConfiguracaoCheckoutRapido();
        $this->configService = new CheckoutRapidoConfigService();
        $this->funil = new CheckoutFunilService();
        $this->cursoModel = new CursoEvento();
        $this->auditService = new AuditService();
    }

    public function index(Request $request)
    {
        $dias = (int) $request->query('dias', 30);
        if (!in_array($dias, array(7, 30, 90), true)) {
            $dias = 30;
        }

        $registro = $this->configModel->find();
        if (!$registro) {
            Session::flash('errors', array(
                'A tabela de configuração não existe. Aplique a migração sql/071_checkout_rapido_v1.sql antes de usar esta tela.',
            ));
        }

        $de = date('Y-m-d 00:00:00', strtotime('-' . ($dias - 1) . ' day'));

        $abacatePay = new AbacatePayService();

        return $this->view('admin/checkout-rapido/index', array(
            'title' => 'Checkout rápido',
            'configuracao' => $registro ?: array(),
            'dias' => $dias,
            'resumo' => $this->funil->resumo($de),
            'serie' => $this->funil->serieDiaria($dias),
            'campanhas' => $this->funil->porCampanha($dias),
            'pedidos' => $this->funil->ultimosPedidos(30),
            'historico' => $this->funil->historicoDaChave(20),
            'cursos' => $this->cursosDisponiveis(),
            'gatewayAtivo' => $abacatePay->isEnabled(),
            'errors' => Session::pullFlash('errors', array()),
            'success' => Session::pullFlash('success'),
        ));
    }

    public function salvar(Request $request)
    {
        $registro = $this->configModel->find();
        if (!$registro) {
            Session::flash('errors', array('Configuração não encontrada. Aplique a migração 071 antes.'));
            return $this->redirect('/admin/checkout-rapido');
        }

        try {
            $anterior = array(
                'ativo' => (int) $registro['ativo'],
                'cursos_habilitados' => (string) $registro['cursos_habilitados'],
            );

            $novo = array(
                'ativo' => $request->input('ativo', 0),
                'cursos_habilitados' => $this->normalizarCursos($request->input('cursos_habilitados', '')),
                'pix_expira_minutos' => $this->entreLimites($request->input('pix_expira_minutos', 30), 5, 1440, 30),
                'polling_intervalo_segundos' => $this->entreLimites($request->input('polling_intervalo_segundos', 3), 2, 60, 3),
                'token_acesso_validade_horas' => $this->entreLimites($request->input('token_acesso_validade_horas', 168), 1, 8760, 168),
                'google_ads_conversion_id' => trim((string) $request->input('google_ads_conversion_id', '')),
                'google_ads_conversion_label' => trim((string) $request->input('google_ads_conversion_label', '')),
                'google_ads_ativo' => $request->input('google_ads_ativo', 0),
                'whatsapp_provider' => trim((string) $request->input('whatsapp_provider', '')),
                'whatsapp_ativo' => $request->input('whatsapp_ativo', 0),
                'texto_lgpd' => trim((string) $request->input('texto_lgpd', '')),
            );

            $this->configModel->update($novo);

            $this->auditService->record(
                'checkout_rapido.configuracao.atualizada',
                'configuracoes_checkout_rapido',
                1,
                array(
                    'ativo_antes' => $anterior['ativo'],
                    'ativo_depois' => !empty($novo['ativo']) ? 1 : 0,
                    'cursos_antes' => $anterior['cursos_habilitados'],
                    'cursos_depois' => (string) $novo['cursos_habilitados'],
                    'google_ads_ativo' => !empty($novo['google_ads_ativo']) ? 1 : 0,
                    'whatsapp_ativo' => !empty($novo['whatsapp_ativo']) ? 1 : 0,
                ),
                Session::get('usuario_id'),
                $request->ip(),
                $request->userAgent()
            );

            Logger::info('checkout_rapido.configuracao.atualizada', array(
                'ativo' => !empty($novo['ativo']) ? 1 : 0,
                'cursos' => (string) $novo['cursos_habilitados'],
                'usuario_id' => Session::get('usuario_id'),
            ));

            Session::flash('success', !empty($novo['ativo'])
                ? 'Checkout rápido ligado.'
                : 'Checkout rápido desligado. O fluxo antigo voltou a ser o único caminho de compra.');

            return $this->redirect('/admin/checkout-rapido');
        } catch (Throwable $exception) {
            Logger::error('checkout_rapido.configuracao.falhou', array(
                'message' => $exception->getMessage(),
                'usuario_id' => Session::get('usuario_id'),
            ));

            Session::flash('errors', array('Não foi possível salvar. Verifique o log do sistema.'));
            return $this->redirect('/admin/checkout-rapido');
        }
    }

    /**
     * Atalho do topo da tela: liga ou desliga sem abrir o formulário inteiro.
     */
    public function alternar(Request $request)
    {
        $registro = $this->configModel->find();
        if (!$registro) {
            Session::flash('errors', array('Configuração não encontrada. Aplique a migração 071 antes.'));
            return $this->redirect('/admin/checkout-rapido');
        }

        $ativoDepois = empty($registro['ativo']) ? 1 : 0;

        $dados = $registro;
        $dados['ativo'] = $ativoDepois;
        $this->configModel->update($dados);

        $this->auditService->record(
            'checkout_rapido.configuracao.atualizada',
            'configuracoes_checkout_rapido',
            1,
            array(
                'ativo_antes' => (int) $registro['ativo'],
                'ativo_depois' => $ativoDepois,
                'cursos_antes' => (string) $registro['cursos_habilitados'],
                'cursos_depois' => (string) $registro['cursos_habilitados'],
                'via' => 'atalho',
            ),
            Session::get('usuario_id'),
            $request->ip(),
            $request->userAgent()
        );

        Logger::info('checkout_rapido.configuracao.atualizada', array(
            'ativo' => $ativoDepois,
            'via' => 'atalho',
            'usuario_id' => Session::get('usuario_id'),
        ));

        Session::flash('success', $ativoDepois
            ? 'Checkout rápido ligado.'
            : 'Checkout rápido desligado. O fluxo antigo voltou a ser o único caminho de compra.');

        return $this->redirect('/admin/checkout-rapido');
    }

    /**
     * Cursos ativos com turma aberta — os que o checkout rápido consegue vender.
     */
    private function cursosDisponiveis()
    {
        try {
            $stmt = \App\Core\Database::connection()->query(
                'SELECT ce.id, ce.nome
                   FROM cursos_eventos ce
                  INNER JOIN turmas t ON t.curso_evento_id = ce.id
                        AND t.status = "aberta" AND t.deleted_at IS NULL
                  WHERE ce.status = "ativo" AND ce.deleted_at IS NULL
                  GROUP BY ce.id, ce.nome
                  ORDER BY ce.nome'
            );

            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (Throwable $exception) {
            return array();
        }
    }

    private function normalizarCursos($valor)
    {
        $ids = array();
        foreach (preg_split('/[,;\s]+/', (string) $valor) as $parte) {
            $id = (int) trim($parte);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        $ids = array_values(array_unique($ids));

        return empty($ids) ? null : implode(',', $ids);
    }

    private function entreLimites($valor, $minimo, $maximo, $padrao)
    {
        $valor = (int) $valor;

        if ($valor < $minimo || $valor > $maximo) {
            return $padrao;
        }

        return $valor;
    }
}
