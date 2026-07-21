<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Helpers;
use App\Core\Logger;
use App\Models\Cupom;
use App\Models\EmailOptout;
use App\Models\EmailModelo;
use App\Models\EmailEnvio;
use App\Models\Inscricao;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\PedidoRecuperacaoExecucao;
use App\Models\PagamentoGatewayTransacao;
use App\Models\PedidoRecuperacaoLog;
use App\Models\Usuario;
use Exception;

class PedidoRecuperacaoService
{
    private $pedidoModel;
    private $pedidoItemModel;
    private $inscricaoModel;
    private $usuarioModel;
    private $cupomModel;
    private $pagamentoGatewayTransacaoModel;
    private $emailModeloService;
    private $emailService;
    private $logModel;
    private $execucaoModel;
    private $optoutModel;
    private $globalConfigService;

    public function __construct()
    {
        $this->pedidoModel = new Pedido();
        $this->pedidoItemModel = new PedidoItem();
        $this->inscricaoModel = new Inscricao();
        $this->usuarioModel = new Usuario();
        $this->cupomModel = new Cupom();
        $this->pagamentoGatewayTransacaoModel = new PagamentoGatewayTransacao();
        $this->emailModeloService = new EmailModeloService();
        $this->emailService = new EmailService();
        $this->logModel = new PedidoRecuperacaoLog();
        $this->execucaoModel = new PedidoRecuperacaoExecucao();
        $this->optoutModel = new EmailOptout();
        $this->globalConfigService = new ConfiguracaoGlobalService();
    }

    public function listarRecuperaveis(array $filters = array(), $page = 1, $perPage = 20)
    {
        $page = max(1, (int) $page);
        $perPage = max(1, min(100, (int) $perPage));
        $offset = ($page - 1) * $perPage;

        $where = array(
            'p.deleted_at IS NULL',
            'p.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)',
            'p.status NOT IN ("pago", "aprovado", "cancelado", "expirado", "comprovante_enviado", "em_analise")',
            'COALESCE(p.payment_provider_status, "") NOT IN ("paid", "pago", "aprovado", "approved", "confirmed", "success", "succeeded", "completed")',
            'NOT (COALESCE(p.payment_provider_paid_amount, 0) > 0 AND COALESCE(p.payment_provider_paid_amount, 0) >= COALESCE(p.payment_provider_amount, p.total, p.subtotal, 0))',
            'NOT EXISTS (
                SELECT 1
                FROM inscricoes i_ativa
                WHERE i_ativa.deleted_at IS NULL
                  AND i_ativa.usuario_id = COALESCE(p.pagador_usuario_id, p.comprador_usuario_id)
                  AND i_ativa.curso_evento_id = pi.curso_evento_id
                  AND i_ativa.status IN ("ativa", "em_andamento", "concluida", "concluida_sem_certificado", "certificado_emitido")
                  AND (i_ativa.acesso_expira_em IS NULL OR i_ativa.acesso_expira_em >= NOW())
            )',
            'NOT EXISTS (
                SELECT 1
                FROM pagamentos_gateway_transacoes pgt
                WHERE pgt.pedido_id = p.id
                  AND pgt.status IN ("paid", "pago", "aprovado", "approved", "confirmed", "success", "succeeded", "completed")
            )',
            'NOT EXISTS (
                SELECT 1
                FROM email_optouts eo
                WHERE eo.tipo = "recuperacao_pedido"
                  AND eo.optout_em IS NOT NULL
                  AND (
                        eo.email = COALESCE(NULLIF(p.pagador_email, ""), NULLIF(u.email, ""))
                        OR (COALESCE(p.pagador_usuario_id, p.comprador_usuario_id) IS NOT NULL AND eo.aluno_id = COALESCE(p.pagador_usuario_id, p.comprador_usuario_id))
                  )
            )',
        );

        $params = array();

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(p.codigo LIKE :q OR p.pagador_nome LIKE :q OR p.pagador_email LIKE :q OR p.pagador_cpf LIKE :q OR ce.nome LIKE :q)';
            $params['q'] = '%' . $q . '%';
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            if ($status === 'abacatepay') {
                $where[] = 'p.payment_gateway = "abacatepay"';
            } elseif ($status === 'pix_manual') {
                $where[] = '(p.payment_gateway IS NULL OR p.payment_gateway = "" OR p.payment_gateway = "manual")';
            } elseif ($status === 'pedido_incompleto') {
                $where[] = '1 = 1';
            } else {
                $where[] = 'p.status = :status';
                $params['status'] = $status;
            }
        }

        $curso = trim((string) ($filters['curso'] ?? ''));
        if ($curso !== '') {
            $where[] = 'ce.nome LIKE :curso';
            $params['curso'] = '%' . $curso . '%';
        }

        $de = trim((string) ($filters['de'] ?? ''));
        if ($de !== '') {
            $where[] = 'DATE(p.created_at) >= :de';
            $params['de'] = $de;
        }

        $ate = trim((string) ($filters['ate'] ?? ''));
        if ($ate !== '') {
            $where[] = 'DATE(p.created_at) <= :ate';
            $params['ate'] = $ate;
        }

        $sqlBase = 'FROM pedidos p
                    INNER JOIN pedido_itens pi ON pi.pedido_id = p.id AND pi.deleted_at IS NULL
                    INNER JOIN cursos_eventos ce ON ce.id = pi.curso_evento_id AND ce.deleted_at IS NULL
                    LEFT JOIN usuarios u ON u.id = COALESCE(p.pagador_usuario_id, p.comprador_usuario_id)
                    LEFT JOIN (
                        SELECT pedido_id,
                               MAX(COALESCE(enviado_em, created_at)) AS ultimo_envio_em,
                               SUM(CASE WHEN tipo_envio = "manual" AND status = "enviado" THEN 1 ELSE 0 END) AS envios_manuais,
                               SUM(CASE WHEN tipo_envio = "automatico" AND status = "enviado" THEN 1 ELSE 0 END) AS envios_automaticos,
                               SUM(CASE WHEN status = "enviado" THEN 1 ELSE 0 END) AS total_envios
                        FROM pedido_recuperacao_logs
                        GROUP BY pedido_id
                    ) rl ON rl.pedido_id = p.id
                    LEFT JOIN (
                        SELECT l1.pedido_id,
                               l1.etapa AS ultimo_envio_automatico_etapa,
                               COALESCE(l1.enviado_em, l1.created_at) AS ultimo_envio_automatico_em
                        FROM pedido_recuperacao_logs l1
                        INNER JOIN (
                            SELECT pedido_id, MAX(id) AS ultimo_id
                            FROM pedido_recuperacao_logs
                            WHERE tipo_envio = "automatico"
                              AND status = "enviado"
                            GROUP BY pedido_id
                        ) l2 ON l2.pedido_id = l1.pedido_id
                             AND l2.ultimo_id = l1.id
                        WHERE l1.tipo_envio = "automatico"
                          AND l1.status = "enviado"
                    ) rl_auto ON rl_auto.pedido_id = p.id
                    WHERE ' . implode(' AND ', $where);

        $sqlCount = 'SELECT COUNT(DISTINCT p.id) AS total ' . $sqlBase;
        $stmt = Database::connection()->prepare($sqlCount);
        $stmt->execute($params);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $total = $row ? (int) $row['total'] : 0;

        $sql = 'SELECT p.id,
                       p.codigo,
                       p.status,
                       p.total,
                       p.subtotal,
                       p.desconto_total,
                       p.payment_gateway,
                       p.payment_external_id,
                       p.payment_provider_checkout_id,
                       p.payment_provider_payment_url,
                       p.payment_provider_status,
                       p.payment_provider_amount,
                       p.payment_provider_paid_amount,
                       p.payment_provider_method,
                       p.created_at,
                       p.updated_at,
                       p.pagador_usuario_id,
                       p.comprador_usuario_id,
                       p.pagador_nome,
                       p.pagador_email,
                       p.pagador_cpf,
                       ce.id AS curso_id,
                       ce.nome AS curso_nome,
                       u.nome AS aluno_nome,
                       u.email AS aluno_email,
                       COALESCE(rl.total_envios, 0) AS total_envios,
                       COALESCE(rl.envios_manuais, 0) AS envios_manuais,
                       COALESCE(rl.envios_automaticos, 0) AS envios_automaticos,
                       rl.ultimo_envio_em,
                       rl_auto.ultimo_envio_automatico_em,
                       rl_auto.ultimo_envio_automatico_etapa,
                       DATEDIFF(CURDATE(), DATE(p.created_at)) AS dias_desde_criacao
                ' . $sqlBase . '
                GROUP BY p.id
                ORDER BY p.created_at ASC, p.id ASC
                LIMIT :limit OFFSET :offset';

        $stmt = Database::connection()->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        $pedidos = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($pedidos as &$pedido) {
            $pedido['valor_total'] = $this->resolverValorTotal($pedido);
            $pedido['valor_pago'] = $this->resolverValorPago($pedido);
            $pedido['valor_pendente'] = max(0.0, (float) $pedido['valor_total'] - (float) $pedido['valor_pago']);
            $pedido['valor_total_formatado'] = $this->formatarMoeda($pedido['valor_total']);
            $pedido['valor_pago_formatado'] = $this->formatarMoeda($pedido['valor_pago']);
            $pedido['valor_pendente_formatado'] = $this->formatarMoeda($pedido['valor_pendente']);
            $pedido['optout_ativo'] = $this->optoutModel->findActiveByAlunoOrEmail(
                !empty($pedido['pagador_usuario_id']) ? (int) $pedido['pagador_usuario_id'] : (!empty($pedido['comprador_usuario_id']) ? (int) $pedido['comprador_usuario_id'] : null),
                !empty($pedido['pagador_email']) ? $pedido['pagador_email'] : ($pedido['aluno_email'] ?? '')
            ) ? 1 : 0;
            $pedido['pedido_recuperacao_bloqueado'] = 0;
        }
        unset($pedido);

        $pages = max(1, (int) ceil($total / $perPage));

        return array(
            'pedidos' => $pedidos,
            'pagination' => array(
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'pages' => $pages,
            ),
        );
    }

    public function statusAutomacao()
    {
        $config = $this->globalConfigService->seguranca();
        $ultimaExecucao = $this->execucaoModel->latest();

        return array(
            'ativa' => !empty($config['recuperacao_pedidos_automatica_ativa']),
            'limite_processamento' => isset($config['recuperacao_pedidos_processamento_limite']) ? (int) $config['recuperacao_pedidos_processamento_limite'] : 50,
            'ultima_execucao' => $ultimaExecucao,
            'comando_recomendado' => $this->comandoCronRecomendado(isset($config['recuperacao_pedidos_processamento_limite']) ? (int) $config['recuperacao_pedidos_processamento_limite'] : 50),
            'frequencia_recomendada' => 'A cada 30 minutos ou a cada 1 hora.',
            'observacao' => 'A régua interna controla 24h, 3 dias, 7 dias e 20 dias. A Cron pode rodar com frequência sem duplicar envios.',
        );
    }

    public function resumoRecuperacao(array $filters = array())
    {
        $where = array(
            'p.deleted_at IS NULL',
            'p.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)',
            'p.status NOT IN ("pago", "aprovado", "cancelado", "expirado", "comprovante_enviado", "em_analise")',
            'COALESCE(p.payment_provider_status, "") NOT IN ("paid", "pago", "aprovado", "approved", "confirmed", "success", "succeeded", "completed")',
            'NOT (COALESCE(p.payment_provider_paid_amount, 0) > 0 AND COALESCE(p.payment_provider_paid_amount, 0) >= COALESCE(p.payment_provider_amount, p.total, p.subtotal, 0))',
            'NOT EXISTS (
                SELECT 1
                FROM inscricoes i_ativa
                WHERE i_ativa.deleted_at IS NULL
                  AND i_ativa.usuario_id = COALESCE(p.pagador_usuario_id, p.comprador_usuario_id)
                  AND i_ativa.curso_evento_id = pi.curso_evento_id
                  AND i_ativa.status IN ("ativa", "em_andamento", "concluida", "concluida_sem_certificado", "certificado_emitido")
                  AND (i_ativa.acesso_expira_em IS NULL OR i_ativa.acesso_expira_em >= NOW())
            )',
            'NOT EXISTS (
                SELECT 1
                FROM pagamentos_gateway_transacoes pgt
                WHERE pgt.pedido_id = p.id
                  AND pgt.status IN ("paid", "pago", "aprovado", "approved", "confirmed", "success", "succeeded", "completed")
            )',
            'NOT EXISTS (
                SELECT 1
                FROM email_optouts eo
                WHERE eo.tipo = "recuperacao_pedido"
                  AND eo.optout_em IS NOT NULL
                  AND (
                        eo.email = COALESCE(NULLIF(p.pagador_email, ""), NULLIF(u.email, ""))
                        OR (COALESCE(p.pagador_usuario_id, p.comprador_usuario_id) IS NOT NULL AND eo.aluno_id = COALESCE(p.pagador_usuario_id, p.comprador_usuario_id))
                  )
            )',
        );

        $params = array();

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(p.codigo LIKE :q OR p.pagador_nome LIKE :q OR p.pagador_email LIKE :q OR p.pagador_cpf LIKE :q OR ce.nome LIKE :q)';
            $params['q'] = '%' . $q . '%';
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            if ($status === 'abacatepay') {
                $where[] = 'p.payment_gateway = "abacatepay"';
            } elseif ($status === 'pix_manual') {
                $where[] = '(p.payment_gateway IS NULL OR p.payment_gateway = "" OR p.payment_gateway = "manual")';
            } elseif ($status !== 'pedido_incompleto') {
                $where[] = 'p.status = :status';
                $params['status'] = $status;
            }
        }

        $curso = trim((string) ($filters['curso'] ?? ''));
        if ($curso !== '') {
            $where[] = 'ce.nome LIKE :curso';
            $params['curso'] = '%' . $curso . '%';
        }

        $de = trim((string) ($filters['de'] ?? ''));
        if ($de !== '') {
            $where[] = 'DATE(p.created_at) >= :de';
            $params['de'] = $de;
        }

        $ate = trim((string) ($filters['ate'] ?? ''));
        if ($ate !== '') {
            $where[] = 'DATE(p.created_at) <= :ate';
            $params['ate'] = $ate;
        }

        $sqlBase = 'FROM pedidos p
                    INNER JOIN pedido_itens pi ON pi.pedido_id = p.id AND pi.deleted_at IS NULL
                    INNER JOIN cursos_eventos ce ON ce.id = pi.curso_evento_id AND ce.deleted_at IS NULL
                    LEFT JOIN usuarios u ON u.id = COALESCE(p.pagador_usuario_id, p.comprador_usuario_id)
                    WHERE ' . implode(' AND ', $where);

        $stmt = Database::connection()->prepare('SELECT COUNT(DISTINCT p.id) AS total ' . $sqlBase);
        $stmt->execute($params);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $recuperaveis = $row ? (int) $row['total'] : 0;

        $stmt = Database::connection()->query(
            'SELECT COUNT(*) AS total
             FROM pedido_recuperacao_logs
             WHERE status = "enviado"
               AND COALESCE(enviado_em, created_at) >= CURDATE()'
        );
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $enviadosHoje = $row ? (int) $row['total'] : 0;

        $stmt = Database::connection()->query(
            'SELECT COUNT(*) AS total
             FROM pedido_recuperacao_logs
             WHERE status = "bloqueado"'
        );
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $bloqueados = $row ? (int) $row['total'] : 0;

        $stmt = Database::connection()->query(
            'SELECT COUNT(*) AS total
             FROM pedido_recuperacao_logs
             WHERE status = "erro"'
        );
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $erros = $row ? (int) $row['total'] : 0;

        $stmt = Database::connection()->query(
            'SELECT COUNT(*) AS total
             FROM email_optouts
             WHERE tipo = "recuperacao_pedido"
               AND optout_em IS NOT NULL'
        );
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $optouts = $row ? (int) $row['total'] : 0;

        return array(
            'recuperaveis' => $recuperaveis,
            'enviados_hoje' => $enviadosHoje,
            'bloqueados' => $bloqueados,
            'erros' => $erros,
            'optouts' => $optouts,
        );
    }

    public function executarAutomacao($limit = null, $dryRun = false, array $options = array())
    {
        $config = $this->globalConfigService->seguranca();
        if (empty($config['recuperacao_pedidos_automatica_ativa'])) {
            return array(
                'ok' => false,
                'status' => 'inativa',
                'message' => 'Recuperação automática desativada nas configurações.',
                'stats' => array(
                    'analisados' => 0,
                    'processados' => 0,
                    'enviados' => 0,
                    'ignorados' => 0,
                    'bloqueados' => 0,
                    'erros' => 0,
                ),
            );
        }

        $limiteConfigurado = isset($config['recuperacao_pedidos_processamento_limite']) ? (int) $config['recuperacao_pedidos_processamento_limite'] : 50;
        $limite = (int) $limit;
        if ($limite <= 0) {
            $limite = $limiteConfigurado > 0 ? $limiteConfigurado : 50;
        }

        $execucaoId = $this->execucaoModel->create(array(
            'status' => 'running',
            'modo' => 'cron',
            'dry_run' => $dryRun ? 1 : 0,
            'limite_processamento' => $limite,
            'started_at' => date('Y-m-d H:i:s'),
        ));

        $stats = array(
            'analisados' => 0,
            'processados' => 0,
            'enviados' => 0,
            'ignorados' => 0,
            'bloqueados' => 0,
            'erros' => 0,
        );

        try {
            $lista = $this->listarRecuperaveis(array(), 1, $limite);
            $pedidos = isset($lista['pedidos']) && is_array($lista['pedidos']) ? $lista['pedidos'] : array();
            $stats['analisados'] = count($pedidos);

            foreach ($pedidos as $pedidoLinha) {
                $stats['processados']++;

                $pedidoLinha['ultimo_envio_automatico'] = !empty($pedidoLinha['ultimo_envio_automatico_em']) || !empty($pedidoLinha['ultimo_envio_automatico_etapa'])
                    ? array(
                        'etapa' => !empty($pedidoLinha['ultimo_envio_automatico_etapa']) ? $pedidoLinha['ultimo_envio_automatico_etapa'] : null,
                        'enviado_em' => !empty($pedidoLinha['ultimo_envio_automatico_em']) ? $pedidoLinha['ultimo_envio_automatico_em'] : null,
                    )
                    : null;

                $avaliacaoEtapa = $this->resolverEtapaAutomatica($pedidoLinha);
                if (empty($avaliacaoEtapa['ok'])) {
                    $status = isset($avaliacaoEtapa['status']) ? (string) $avaliacaoEtapa['status'] : 'ignorado';
                    if ($status === 'bloqueado') {
                        $stats['bloqueados']++;
                    } elseif ($status === 'erro') {
                        $stats['erros']++;
                    } else {
                        $stats['ignorados']++;
                    }

                    $this->logModel->create(array(
                        'pedido_id' => (int) $pedidoLinha['id'],
                        'aluno_id' => !empty($pedidoLinha['pagador_usuario_id']) ? (int) $pedidoLinha['pagador_usuario_id'] : (!empty($pedidoLinha['comprador_usuario_id']) ? (int) $pedidoLinha['comprador_usuario_id'] : null),
                        'curso_id' => !empty($pedidoLinha['curso_id']) ? (int) $pedidoLinha['curso_id'] : null,
                        'admin_user_id' => null,
                        'canal' => 'email',
                        'modelo_chave' => isset($avaliacaoEtapa['modelo_chave']) ? $avaliacaoEtapa['modelo_chave'] : 'pedido_recuperacao_primeiro_lembrete',
                        'tipo_envio' => 'automatico',
                        'etapa' => isset($avaliacaoEtapa['etapa']) ? $avaliacaoEtapa['etapa'] : null,
                        'status' => $status,
                        'email_destino' => !empty($pedidoLinha['aluno_email']) ? $pedidoLinha['aluno_email'] : (isset($pedidoLinha['pagador_email']) ? $pedidoLinha['pagador_email'] : null),
                        'valor_pendente' => isset($pedidoLinha['valor_pendente']) ? (float) $pedidoLinha['valor_pendente'] : null,
                        'cupom_codigo' => null,
                        'motivo_bloqueio' => isset($avaliacaoEtapa['motivo_bloqueio']) ? $avaliacaoEtapa['motivo_bloqueio'] : 'etapa_automatico_nao_disponivel',
                        'erro' => isset($avaliacaoEtapa['erro']) ? $avaliacaoEtapa['erro'] : null,
                        'execucao_id' => $execucaoId,
                        'enviado_em' => null,
                    ));

                    continue;
                }

                $etapa = $avaliacaoEtapa['etapa'];
                $modelo = $this->mapearEtapaParaModelo($etapa);
                $input = array_merge($options, array(
                    'etapa' => $etapa,
                    'modelo_chave' => $modelo,
                ));

                if ($dryRun) {
                    $resultado = $this->logAndReturn(
                        (int) $pedidoLinha['id'],
                        !empty($pedidoLinha['pagador_usuario_id']) ? (int) $pedidoLinha['pagador_usuario_id'] : (!empty($pedidoLinha['comprador_usuario_id']) ? (int) $pedidoLinha['comprador_usuario_id'] : null),
                        !empty($pedidoLinha['curso_id']) ? (int) $pedidoLinha['curso_id'] : null,
                        'automatico',
                        'ignorado',
                        'Execução simulada. Nenhum e-mail foi enviado.',
                        'dry_run',
                        !empty($pedidoLinha['aluno_email']) ? $pedidoLinha['aluno_email'] : (isset($pedidoLinha['pagador_email']) ? $pedidoLinha['pagador_email'] : null),
                        isset($pedidoLinha['valor_pendente']) ? (float) $pedidoLinha['valor_pendente'] : null,
                        null,
                        null,
                        null,
                        array(
                            'modelo_chave' => $modelo,
                            'etapa' => $etapa,
                            'execucao_id' => $execucaoId,
                        )
                    );
                    $stats['ignorados']++;
                    continue;
                }

                $resultado = $this->enviarAutomatico((int) $pedidoLinha['id'], $input, null, isset($options['ip_address']) ? $options['ip_address'] : null, isset($options['user_agent']) ? $options['user_agent'] : null, $execucaoId);
                if (!empty($resultado['ok'])) {
                    $stats['enviados']++;
                } elseif (($resultado['status'] ?? '') === 'bloqueado') {
                    $stats['bloqueados']++;
                } elseif (($resultado['status'] ?? '') === 'erro') {
                    $stats['erros']++;
                } else {
                    $stats['ignorados']++;
                }
            }

            $this->execucaoModel->update($execucaoId, array(
                'status' => 'completed',
                'modo' => 'cron',
                'dry_run' => $dryRun ? 1 : 0,
                'limite_processamento' => $limite,
                'total_analisados' => $stats['analisados'],
                'total_processados' => $stats['processados'],
                'total_enviados' => $stats['enviados'],
                'total_ignorados' => $stats['ignorados'],
                'total_bloqueados' => $stats['bloqueados'],
                'total_erros' => $stats['erros'],
                'finished_at' => date('Y-m-d H:i:s'),
                'error_message' => null,
            ));

            return array(
                'ok' => true,
                'status' => 'completed',
                'execucao_id' => $execucaoId,
                'stats' => $stats,
                'dry_run' => $dryRun ? 1 : 0,
                'limite' => $limite,
            );
        } catch (Exception $exception) {
            $this->execucaoModel->update($execucaoId, array(
                'status' => 'error',
                'modo' => 'cron',
                'dry_run' => $dryRun ? 1 : 0,
                'limite_processamento' => $limite,
                'total_analisados' => $stats['analisados'],
                'total_processados' => $stats['processados'],
                'total_enviados' => $stats['enviados'],
                'total_ignorados' => $stats['ignorados'],
                'total_bloqueados' => $stats['bloqueados'],
                'total_erros' => $stats['erros'] + 1,
                'finished_at' => date('Y-m-d H:i:s'),
                'error_message' => $exception->getMessage(),
            ));

            Logger::error('pedido_recuperacao.cron.erro', array(
                'execucao_id' => $execucaoId,
                'message' => $exception->getMessage(),
            ));

            return array(
                'ok' => false,
                'status' => 'error',
                'execucao_id' => $execucaoId,
                'message' => $exception->getMessage(),
                'stats' => $stats,
            );
        }
    }

    public function resumoPedido($pedidoId)
    {
        $pedido = $this->pedidoModel->findById($pedidoId);
        if (!$pedido) {
            return null;
        }

        $item = $this->pedidoItemModel->forPedido($pedidoId);
        $item = !empty($item[0]) ? $item[0] : array();
        $alunoId = !empty($pedido['pagador_usuario_id']) ? (int) $pedido['pagador_usuario_id'] : (!empty($pedido['comprador_usuario_id']) ? (int) $pedido['comprador_usuario_id'] : null);
        $aluno = $alunoId ? $this->usuarioModel->findAlunoById($alunoId) : null;

        $regras = $this->avaliarPedido($pedido, $item, $aluno, array());
        if (empty($regras['ok'])) {
            return array_merge($pedido, $regras);
        }

        return array_merge($pedido, $regras, array(
            'pedido' => $pedido,
            'aluno' => $aluno,
            'item' => $item,
            'valor_total' => $regras['valor_total'],
            'valor_pago' => $regras['valor_pago'],
            'valor_pendente' => $regras['valor_pendente'],
            'curso_id' => $regras['curso_id'],
            'curso_nome' => $regras['curso_nome'],
            'optout_ativo' => $regras['optout_ativo'],
            'ultimo_envio' => $regras['ultimo_envio'],
            'total_envios' => $regras['total_envios'],
            'envios_manuais' => $regras['envios_manuais'],
            'envios_automaticos' => $regras['envios_automaticos'],
        ));
    }

    public function enviarManual($pedidoId, array $input, $adminUserId = null, $ipAddress = null, $userAgent = null)
    {
        return $this->enviar($pedidoId, $input, $adminUserId, $ipAddress, $userAgent, 'manual');
    }

    public function enviarAutomatico($pedidoId, array $input = array(), $adminUserId = null, $ipAddress = null, $userAgent = null, $execucaoId = null)
    {
        return $this->enviar($pedidoId, $input, $adminUserId, $ipAddress, $userAgent, 'automatico', $execucaoId);
    }

    public function enviarLote(array $pedidoIds, array $input, $adminUserId = null, $ipAddress = null, $userAgent = null)
    {
        $resultados = array();
        foreach ($pedidoIds as $pedidoId) {
            $pedidoId = (int) $pedidoId;
            if ($pedidoId <= 0) {
                continue;
            }
            $resultados[] = $this->enviarManual($pedidoId, $input, $adminUserId, $ipAddress, $userAgent);
        }

        return $resultados;
    }

    public function confirmarDescadastro($token, $tipo = 'recuperacao_pedido')
    {
        $token = trim((string) $token);
        if ($token === '') {
            return array('ok' => false, 'message' => 'Link inválido ou expirado.');
        }

        $hash = hash('sha256', $token);
        $registro = $this->optoutModel->findByTokenHash($hash, $tipo);
        if (!$registro) {
            return array('ok' => false, 'message' => 'Link inválido ou expirado.');
        }

        $this->optoutModel->confirmByTokenHash($hash, $tipo);

        Logger::info('pedido_recuperacao.optout.confirmado', array(
            'email' => $registro['email'],
            'aluno_id' => !empty($registro['aluno_id']) ? (int) $registro['aluno_id'] : null,
        ));

        return array('ok' => true);
    }

    public function gerarLinkDescadastro(array $contexto)
    {
        $alunoId = !empty($contexto['aluno_id']) ? (int) $contexto['aluno_id'] : null;
        $email = strtolower(trim((string) ($contexto['email_destino'] ?? '')));
        $token = bin2hex(random_bytes(32));
        $this->optoutModel->createToken(array(
            'aluno_id' => $alunoId,
            'email' => $email,
            'tipo' => 'recuperacao_pedido',
            'token_hash' => hash('sha256', $token),
        ));

        return Helpers::url('pedido-recuperacao/descadastrar?token=' . urlencode($token));
    }

    private function enviar($pedidoId, array $input, $adminUserId, $ipAddress, $userAgent, $tipoEnvio, $execucaoId = null)
    {
        $pedido = $this->pedidoModel->findById($pedidoId);
        if (!$pedido) {
            return $this->logAndReturn($pedidoId, null, null, $tipoEnvio, 'bloqueado', 'Pedido não encontrado.', 'pedido_nao_encontrado', null, null, $adminUserId, $ipAddress, $userAgent, array('execucao_id' => $execucaoId));
        }

        $item = $this->pedidoItemModel->forPedido($pedidoId);
        $item = !empty($item[0]) ? $item[0] : array();
        $alunoId = !empty($pedido['pagador_usuario_id']) ? (int) $pedido['pagador_usuario_id'] : (!empty($pedido['comprador_usuario_id']) ? (int) $pedido['comprador_usuario_id'] : null);
        $aluno = $alunoId ? $this->usuarioModel->findAlunoById($alunoId) : null;
        $modeloSolicitado = trim((string) ($input['modelo_chave'] ?? ''));
        $cupomCodigo = trim((string) ($input['cupom_codigo'] ?? ''));
        $etapaAutomatica = isset($input['etapa']) ? trim((string) $input['etapa']) : '';
        $dryRun = !empty($input['dry_run']);

        $regras = $this->avaliarPedido($pedido, $item, $aluno, $input);
        if (empty($regras['ok'])) {
            return $this->logAndReturn(
                $pedidoId,
                $alunoId,
                $regras['curso_id'] ?? null,
                $tipoEnvio,
                isset($regras['status']) ? $regras['status'] : 'bloqueado',
                isset($regras['message']) ? $regras['message'] : 'Pedido não elegível.',
                isset($regras['motivo_bloqueio']) ? $regras['motivo_bloqueio'] : 'pedido_nao_elegivel',
                !empty($regras['email_destino']) ? $regras['email_destino'] : null,
                isset($regras['valor_pendente']) ? $regras['valor_pendente'] : null,
                $adminUserId,
                $ipAddress,
                $userAgent,
                array(
                    'modelo_chave' => $modeloSolicitado,
                    'cupom_codigo' => !empty($regras['cupom_codigo']) ? $regras['cupom_codigo'] : null,
                    'etapa' => $etapaAutomatica !== '' ? $etapaAutomatica : null,
                    'execucao_id' => $execucaoId,
                )
            );
        }

        if ($tipoEnvio === 'automatico') {
            $avaliacaoEtapa = $this->resolverEtapaAutomatica($regras);
            if (empty($avaliacaoEtapa['ok'])) {
                return $this->logAndReturn(
                    $pedidoId,
                    $alunoId,
                    $regras['curso_id'],
                    $tipoEnvio,
                    isset($avaliacaoEtapa['status']) ? $avaliacaoEtapa['status'] : 'ignorado',
                    isset($avaliacaoEtapa['message']) ? $avaliacaoEtapa['message'] : 'Etapa automática não disponível.',
                    isset($avaliacaoEtapa['motivo_bloqueio']) ? $avaliacaoEtapa['motivo_bloqueio'] : 'etapa_automatica_nao_disponivel',
                    $regras['email_destino'],
                    $regras['valor_pendente'],
                    $adminUserId,
                    $ipAddress,
                    $userAgent,
                    array(
                        'modelo_chave' => isset($avaliacaoEtapa['modelo_chave']) ? $avaliacaoEtapa['modelo_chave'] : $modeloSolicitado,
                        'etapa' => isset($avaliacaoEtapa['etapa']) ? $avaliacaoEtapa['etapa'] : null,
                        'execucao_id' => $execucaoId,
                    )
                );
            }

            $etapaAutomatica = $avaliacaoEtapa['etapa'];
            if ($modeloSolicitado === '') {
                $modeloSolicitado = $this->mapearEtapaParaModelo($etapaAutomatica);
            }
        }

        $confirmarDuplicado = !empty($input['confirmar_envio']);

        $ultimoEnvio = $this->logModel->latestSentByEmail($regras['email_destino'], $alunoId);
        if (!$confirmarDuplicado && $ultimoEnvio && !empty($ultimoEnvio['enviado_em']) && strtotime((string) $ultimoEnvio['enviado_em']) >= (time() - 86400)) {
            return $this->logAndReturn(
                $pedidoId,
                $alunoId,
                $regras['curso_id'],
                $tipoEnvio,
                'bloqueado',
                'Já existe um lembrete enviado nas últimas 24 horas.',
                'lembrete_recente_24h',
                $regras['email_destino'],
                $regras['valor_pendente'],
                $adminUserId,
                $ipAddress,
                $userAgent,
                array(
                    'modelo_chave' => $modeloSolicitado,
                    'cupom_codigo' => $cupomCodigo !== '' ? $cupomCodigo : null,
                    'etapa' => $etapaAutomatica !== '' ? $etapaAutomatica : null,
                    'execucao_id' => $execucaoId,
                )
            );
        }

        $modelo = $this->resolverModelo($modeloSolicitado, $cupomCodigo, $tipoEnvio, $etapaAutomatica);
        if ($tipoEnvio === 'automatico' && $etapaAutomatica === '') {
            return $this->logAndReturn(
                $pedidoId,
                $alunoId,
                $regras['curso_id'],
                $tipoEnvio,
                'ignorado',
                'Etapa automática não encontrada para este pedido.',
                'etapa_automatica_nao_encontrada',
                $regras['email_destino'],
                $regras['valor_pendente'],
                $adminUserId,
                $ipAddress,
                $userAgent,
                array(
                    'modelo_chave' => $modelo,
                    'cupom_codigo' => $cupomCodigo !== '' ? $cupomCodigo : null,
                    'execucao_id' => $execucaoId,
                )
            );
        }

        if ($cupomCodigo !== '' && $tipoEnvio !== 'automatico') {
            $cupom = $this->cupomModel->findByCodigo($cupomCodigo);
            if (!$cupom) {
                return $this->logAndReturn(
                    $pedidoId,
                    $alunoId,
                    $regras['curso_id'],
                    $tipoEnvio,
                    'bloqueado',
                    'Cupom informado não encontrado.',
                    'cupom_inexistente',
                    $regras['email_destino'],
                    $regras['valor_pendente'],
                    $adminUserId,
                    $ipAddress,
                    $userAgent,
                    array(
                        'modelo_chave' => $modelo,
                        'cupom_codigo' => $cupomCodigo !== '' ? $cupomCodigo : null,
                        'etapa' => $etapaAutomatica !== '' ? $etapaAutomatica : null,
                        'execucao_id' => $execucaoId,
                    )
                );
            }
        }

        $pedidoResumoUrl = Helpers::url('v2/checkout/resumo?pedido_id=' . (int) $pedidoId);
        $linkPagamento = $this->resolverLinkPagamento($pedido, $pedidoResumoUrl);
        $linkDescadastro = $this->gerarLinkDescadastro(array(
            'aluno_id' => $alunoId,
            'email_destino' => $regras['email_destino'],
        ));

        $contexto = $this->montarContexto($pedido, $item, $aluno, array(
            'cupom_codigo' => $cupomCodigo,
            'link_pedido' => $pedidoResumoUrl,
            'link_pagamento' => $linkPagamento,
            'link_descadastro_recuperacao' => $linkDescadastro,
        ));

        // Mescla o contexto textual já montado (aluno_nome, aluno_email,
        // pedido_codigo, curso_nome, etc.) com os dados estruturados do envio.
        // Sem isto, {{aluno_nome}}, {{pedido_codigo}} e {{curso_nome}} chegavam vazios.
        $emailData = array_merge($contexto, array(
            'pedido' => $pedido,
            'aluno' => $aluno,
            'link_pedido' => $pedidoResumoUrl,
            'link_pagamento' => $linkPagamento,
            'link_descadastro_recuperacao' => $linkDescadastro,
            'cupom_codigo' => $cupomCodigo,
            'valor_total' => $this->formatarMoeda($regras['valor_total']),
            'valor_pago' => $this->formatarMoeda($regras['valor_pago']),
            'valor_pendente' => $this->formatarMoeda($regras['valor_pendente']),
            'data_pedido' => !empty($pedido['created_at']) ? date('d/m/Y', strtotime((string) $pedido['created_at'])) : '',
            'data_expiracao' => !empty($pedido['data_expiracao_pagamento']) ? date('d/m/Y', strtotime((string) $pedido['data_expiracao_pagamento'])) : '',
            'whatsapp_atendimento' => !empty($contexto['whatsapp_atendimento']) ? $contexto['whatsapp_atendimento'] : '',
        ));

        if ($dryRun) {
            return $this->logAndReturn(
                $pedidoId,
                $alunoId,
                $regras['curso_id'],
                $tipoEnvio,
                'ignorado',
                'Execução simulada. Nenhum e-mail foi enviado.',
                'dry_run',
                $regras['email_destino'],
                $regras['valor_pendente'],
                $adminUserId,
                $ipAddress,
                $userAgent,
                array(
                    'cupom_codigo' => $cupomCodigo !== '' ? $cupomCodigo : null,
                    'modelo_chave' => $modelo,
                    'etapa' => $etapaAutomatica !== '' ? $etapaAutomatica : null,
                    'execucao_id' => $execucaoId,
                )
            );
        }

        $resultadoEmail = $this->emailService->sendTemplate(
            $modelo,
            $modelo,
            $regras['email_destino'],
            !empty($aluno['nome']) ? $aluno['nome'] : ($pedido['pagador_nome'] ?? null),
            '',
            $emailData,
            'pedido',
            $pedidoId,
            $adminUserId,
            $ipAddress,
            $userAgent,
            true
        );

        if (empty($resultadoEmail['ok'])) {
            return $this->logAndReturn(
                $pedidoId,
                $alunoId,
                $regras['curso_id'],
                $tipoEnvio,
                'erro',
                'Falha ao enviar o e-mail de recuperação.',
                'envio_falhou',
                $regras['email_destino'],
                $regras['valor_pendente'],
                $adminUserId,
                $ipAddress,
                $userAgent,
                array(
                    'cupom_codigo' => $cupomCodigo !== '' ? $cupomCodigo : null,
                    'erro' => isset($resultadoEmail['message']) ? $resultadoEmail['message'] : null,
                    'modelo_chave' => $modelo,
                    'etapa' => $etapaAutomatica !== '' ? $etapaAutomatica : null,
                    'execucao_id' => $execucaoId,
                ),
                isset($resultadoEmail['email_id']) ? (int) $resultadoEmail['email_id'] : null
            );
        }

        $this->logModel->create(array(
            'pedido_id' => (int) $pedidoId,
            'aluno_id' => $alunoId,
            'curso_id' => $regras['curso_id'],
            'admin_user_id' => $adminUserId,
            'canal' => 'email',
            'modelo_chave' => $modelo,
            'tipo_envio' => $tipoEnvio,
            'etapa' => $tipoEnvio === 'automatico' ? $etapaAutomatica : null,
            'status' => 'enviado',
            'email_destino' => $regras['email_destino'],
            'valor_pendente' => $regras['valor_pendente'],
            'cupom_codigo' => $cupomCodigo !== '' ? $cupomCodigo : null,
            'email_envio_id' => isset($resultadoEmail['email_id']) ? (int) $resultadoEmail['email_id'] : null,
            'execucao_id' => $execucaoId,
            'enviado_em' => date('Y-m-d H:i:s'),
        ));

        Logger::info('pedido_recuperacao.enviado', array(
            'pedido_id' => (int) $pedidoId,
            'email_destino' => $regras['email_destino'],
            'modelo' => $modelo,
            'tipo_envio' => $tipoEnvio,
            'etapa' => $tipoEnvio === 'automatico' ? $etapaAutomatica : null,
            'execucao_id' => $execucaoId,
        ));

        return array(
            'ok' => true,
            'status' => 'enviado',
            'message' => 'Recuperação enviada com sucesso.',
            'pedido_id' => (int) $pedidoId,
            'email_id' => isset($resultadoEmail['email_id']) ? (int) $resultadoEmail['email_id'] : null,
            'etapa' => $tipoEnvio === 'automatico' ? $etapaAutomatica : null,
        );
    }

    private function resolverModelo($modeloSolicitado, $cupomCodigo = '', $tipoEnvio = 'manual', $etapa = '')
    {
        $modeloSolicitado = trim((string) $modeloSolicitado);
        $modeloPadrao = 'pedido_recuperacao_primeiro_lembrete';
        $modeloComCupom = 'pedido_recuperacao_com_cupom';

        if ($tipoEnvio === 'automatico') {
            $modeloEtapa = $this->mapearEtapaParaModelo($etapa);
            return $modeloEtapa !== '' ? $modeloEtapa : $modeloPadrao;
        }

        if ($cupomCodigo !== '' && ($modeloSolicitado === '' || $modeloSolicitado === $modeloPadrao)) {
            return $modeloComCupom;
        }

        return $modeloSolicitado !== '' ? $modeloSolicitado : $modeloPadrao;
    }

    private function resolverEtapaAutomatica(array $regras)
    {
        $pedidoId = isset($regras['pedido_id']) ? (int) $regras['pedido_id'] : 0;
        $diasDesdeCriacao = isset($regras['dias_desde_criacao']) ? (int) $regras['dias_desde_criacao'] : 0;
        $ultimoAuto = isset($regras['ultimo_envio_automatico']) && is_array($regras['ultimo_envio_automatico']) ? $regras['ultimo_envio_automatico'] : null;
        $etapas = array(
            '24h' => 1,
            '3d' => 3,
            '7d' => 7,
            '20d' => 20,
        );
        $ordem = array('24h' => 1, '3d' => 2, '7d' => 3, '20d' => 4);
        $proxima = '';

        $ultimaEtapa = !empty($ultimoAuto['etapa']) ? (string) $ultimoAuto['etapa'] : '';
        foreach ($etapas as $etapa => $diasMinimos) {
            if ($diasDesdeCriacao >= $diasMinimos) {
                if ($ultimaEtapa !== '' && isset($ordem[$ultimaEtapa]) && isset($ordem[$etapa]) && $ordem[$etapa] <= $ordem[$ultimaEtapa]) {
                    continue;
                }
                $proxima = $etapa;
                break;
            }
        }

        if ($proxima === '') {
            return array(
                'ok' => false,
                'status' => 'ignorado',
                'motivo_bloqueio' => 'etapa_ainda_nao_atingiu',
                'message' => 'Pedido ainda não atingiu a próxima etapa automática.',
                'pedido_id' => $pedidoId,
            );
        }

        if (!empty($ultimoAuto) && !empty($ultimoAuto['etapa']) && $ultimoAuto['etapa'] === $proxima) {
            return array(
                'ok' => false,
                'status' => 'ignorado',
                'motivo_bloqueio' => 'etapa_automatica_ja_enviada',
                'message' => 'Esta etapa automática já foi enviada anteriormente.',
                'pedido_id' => $pedidoId,
                'etapa' => $proxima,
                'modelo_chave' => $this->mapearEtapaParaModelo($proxima),
            );
        }

        return array(
            'ok' => true,
            'etapa' => $proxima,
            'modelo_chave' => $this->mapearEtapaParaModelo($proxima),
            'pedido_id' => $pedidoId,
        );
    }

    private function mapearEtapaParaModelo($etapa)
    {
        $etapa = trim((string) $etapa);
        $mapa = array(
            '24h' => 'pedido_recuperacao_primeiro_lembrete',
            '3d' => 'pedido_recuperacao_segundo_lembrete',
            '7d' => 'pedido_recuperacao_terceiro_lembrete',
            '20d' => 'pedido_recuperacao_ultimo_lembrete',
        );

        return isset($mapa[$etapa]) ? $mapa[$etapa] : 'pedido_recuperacao_primeiro_lembrete';
    }

    private function comandoCronRecomendado($limite)
    {
        $limite = max(1, (int) $limite);
        $basePath = str_replace('\\', '/', BASE_PATH);
        $script = $basePath . '/scripts/cron_recuperacao_pedidos.php';
        $log = $basePath . '/storage/logs/cron-recuperacao-pedidos.log';

        return '/usr/bin/php "' . $script . '" --limit=' . $limite . ' >> "' . $log . '" 2>&1';
    }

    private function avaliarPedido(array $pedido, array $item, ?array $aluno, array $input)
    {
        $alunoId = !empty($pedido['pagador_usuario_id']) ? (int) $pedido['pagador_usuario_id'] : (!empty($pedido['comprador_usuario_id']) ? (int) $pedido['comprador_usuario_id'] : null);
        $email = strtolower(trim((string) ($pedido['pagador_email'] ?? ($aluno['email'] ?? ''))));
        $status = strtolower(trim((string) ($pedido['status'] ?? '')));
        $createdAt = !empty($pedido['created_at']) ? strtotime((string) $pedido['created_at']) : 0;
        $cursoId = !empty($item['curso_evento_id']) ? (int) $item['curso_evento_id'] : null;
        $valorTotal = $this->resolverValorTotal($pedido);
        $valorPago = $this->resolverValorPago($pedido);
        $valorPendente = max(0.0, $valorTotal - $valorPago);
        $ultimoAutomatico = !empty($pedido['id']) ? $this->logModel->latestAutomaticByPedido((int) $pedido['id']) : null;

        if ($email === '') {
            return array('ok' => false, 'status' => 'bloqueado', 'motivo_bloqueio' => 'sem_email', 'message' => 'Pedido sem e-mail de contato válido.', 'curso_id' => $cursoId, 'email_destino' => $email, 'valor_pendente' => $valorPendente);
        }

        if ($createdAt > 0 && $createdAt < strtotime('-30 days')) {
            return array('ok' => false, 'status' => 'bloqueado', 'motivo_bloqueio' => 'pedido_com_mais_de_30_dias', 'message' => 'Não é possível enviar recuperação para pedidos com mais de 30 dias.', 'curso_id' => $cursoId, 'email_destino' => $email, 'valor_pendente' => $valorPendente);
        }

        if (in_array($status, array('pago', 'aprovado', 'cancelado', 'expirado', 'comprovante_enviado', 'em_analise'), true)) {
            return array('ok' => false, 'status' => 'bloqueado', 'motivo_bloqueio' => 'status_finalizado', 'message' => 'Pedido já finalizado ou bloqueado.', 'curso_id' => $cursoId, 'email_destino' => $email, 'valor_pendente' => $valorPendente);
        }

        if ($this->pagamentoGatewayAprovado($pedido)) {
            return array('ok' => false, 'status' => 'bloqueado', 'motivo_bloqueio' => 'pagamento_aprovado_gateway', 'message' => 'Pagamento já aprovado no gateway.', 'curso_id' => $cursoId, 'email_destino' => $email, 'valor_pendente' => $valorPendente);
        }

        if ($alunoId && $cursoId && $this->inscricaoModel->findAcessoAtivoPorUsuarioCurso($alunoId, $cursoId)) {
            return array('ok' => false, 'status' => 'bloqueado', 'motivo_bloqueio' => 'aluno_com_inscricao_ativa_no_curso', 'message' => 'Este aluno já possui inscrição ativa neste curso.', 'curso_id' => $cursoId, 'email_destino' => $email, 'valor_pendente' => $valorPendente);
        }

        $optout = $this->optoutModel->findActiveByAlunoOrEmail($alunoId, $email, 'recuperacao_pedido');
        if ($optout) {
            return array('ok' => false, 'status' => 'bloqueado', 'motivo_bloqueio' => 'optout_ativo', 'message' => 'O aluno optou por não receber lembretes de recuperação.', 'curso_id' => $cursoId, 'email_destino' => $email, 'valor_pendente' => $valorPendente);
        }

        if ($valorPendente <= 0) {
            return array('ok' => false, 'status' => 'ignorado', 'motivo_bloqueio' => 'sem_valor_pendente', 'message' => 'Pedido sem valor pendente para recuperação.', 'curso_id' => $cursoId, 'email_destino' => $email, 'valor_pendente' => $valorPendente);
        }

        if (!empty($pedido['payment_provider_status'])) {
            $providerStatus = strtolower(trim((string) $pedido['payment_provider_status']));
            if (in_array($providerStatus, array('paid', 'pago', 'aprovado', 'approved', 'confirmed', 'success', 'succeeded', 'completed'), true)) {
                return array('ok' => false, 'status' => 'bloqueado', 'motivo_bloqueio' => 'pagamento_aprovado', 'message' => 'Pagamento já aprovado.', 'curso_id' => $cursoId, 'email_destino' => $email, 'valor_pendente' => $valorPendente);
            }
        }

        $pedidoGateway = strtolower(trim((string) ($pedido['payment_gateway'] ?? '')));
        if ($pedidoGateway === 'abacatepay') {
            $paymentUrl = trim((string) ($pedido['payment_provider_payment_url'] ?? ''));
            if ($paymentUrl === '') {
                Logger::info('pedido_recuperacao.abacatepay.sem_link_direto', array(
                    'pedido_id' => (int) $pedido['id'],
                    'payment_external_id' => isset($pedido['payment_external_id']) ? (string) $pedido['payment_external_id'] : null,
                ));
            }
        }

        $recente = $this->logModel->latestSentByEmail($email, $alunoId);
        if ($recente && !empty($recente['enviado_em']) && strtotime((string) $recente['enviado_em']) >= (time() - 86400)) {
            $confirmar = !empty($input['confirmar_envio']);
            if (!$confirmar) {
                return array('ok' => false, 'status' => 'bloqueado', 'motivo_bloqueio' => 'lembrete_recente_24h', 'message' => 'Há um lembrete de recuperação enviado nas últimas 24 horas. Confirme o envio para continuar.', 'curso_id' => $cursoId, 'email_destino' => $email, 'valor_pendente' => $valorPendente);
            }
        }

        return array(
            'ok' => true,
            'curso_id' => $cursoId,
            'email_destino' => $email,
            'valor_total' => $valorTotal,
            'valor_pago' => $valorPago,
            'valor_pendente' => $valorPendente,
            'optout_ativo' => $optout ? 1 : 0,
            'ultimo_envio' => $recente,
            'ultimo_envio_automatico' => $ultimoAutomatico,
            'total_envios' => 0,
            'envios_manuais' => 0,
            'envios_automaticos' => 0,
            'pedido_id' => (int) $pedido['id'],
            'dias_desde_criacao' => !empty($pedido['created_at']) ? max(0, (int) floor((time() - strtotime((string) $pedido['created_at'])) / 86400)) : 0,
            'status' => 'pronto',
        );
    }

    private function montarContexto(array $pedido, array $item, ?array $aluno, array $input = array())
    {
        $pedidoResumoUrl = Helpers::url('v2/checkout/resumo?pedido_id=' . (int) $pedido['id']);
        $valorTotal = $this->resolverValorTotal($pedido);
        $valorPago = $this->resolverValorPago($pedido);
        $valorPendente = max(0.0, $valorTotal - $valorPago);
        $linkPagamento = isset($input['link_pagamento']) ? (string) $input['link_pagamento'] : $pedidoResumoUrl;
        $linkDescadastro = isset($input['link_descadastro_recuperacao']) ? (string) $input['link_descadastro_recuperacao'] : '';

        return array(
            'aluno_nome' => !empty($aluno['nome']) ? (string) $aluno['nome'] : (string) ($pedido['pagador_nome'] ?? ''),
            'aluno_email' => !empty($aluno['email']) ? (string) $aluno['email'] : (string) ($pedido['pagador_email'] ?? ''),
            'pedido_codigo' => (string) ($pedido['codigo'] ?? ''),
            'curso_nome' => !empty($item['curso_nome']) ? (string) $item['curso_nome'] : 'Curso não informado',
            'valor_total' => $this->formatarMoeda($valorTotal),
            'valor_pago' => $this->formatarMoeda($valorPago),
            'valor_pendente' => $this->formatarMoeda($valorPendente),
            'link_pagamento' => $linkPagamento,
            'link_pedido' => $pedidoResumoUrl,
            'data_pedido' => !empty($pedido['created_at']) ? date('d/m/Y', strtotime((string) $pedido['created_at'])) : '',
            'data_expiracao' => !empty($pedido['data_expiracao_pagamento']) ? date('d/m/Y', strtotime((string) $pedido['data_expiracao_pagamento'])) : '',
            'cupom_codigo' => isset($input['cupom_codigo']) ? (string) $input['cupom_codigo'] : '',
            'whatsapp_atendimento' => Helpers::url('v2/contato'),
            'link_descadastro_recuperacao' => $linkDescadastro,
        );
    }

    private function resolverValorTotal(array $pedido)
    {
        if (isset($pedido['total']) && is_numeric($pedido['total'])) {
            return max(0.0, (float) $pedido['total']);
        }

        if (isset($pedido['subtotal']) && is_numeric($pedido['subtotal'])) {
            return max(0.0, (float) $pedido['subtotal']);
        }

        return 0.0;
    }

    private function resolverValorPago(array $pedido)
    {
        $valorTotal = $this->resolverValorTotal($pedido);
        if (isset($pedido['payment_provider_paid_amount']) && is_numeric($pedido['payment_provider_paid_amount'])) {
            $valorPago = (float) $pedido['payment_provider_paid_amount'];
        } elseif (!empty($pedido['payment_provider_status'])) {
            $statusGateway = strtolower(trim((string) $pedido['payment_provider_status']));
            $valorPago = in_array($statusGateway, array('paid', 'pago', 'aprovado', 'approved', 'confirmed', 'success', 'succeeded', 'completed'), true)
                ? $valorTotal
                : 0.0;
        } else {
            $valorPago = 0.0;
        }

        $gateway = strtolower(trim((string) ($pedido['payment_gateway'] ?? '')));
        if ($valorPago <= 0.0 && $gateway === 'abacatepay' && !empty($pedido['id'])) {
            $transacoes = $this->pagamentoGatewayTransacaoModel->listByPedido((int) $pedido['id']);
            if (!empty($transacoes[0]['status'])) {
                $statusGateway = strtolower(trim((string) $transacoes[0]['status']));
                if (in_array($statusGateway, array('paid', 'pago', 'aprovado', 'approved', 'confirmed', 'success', 'succeeded', 'completed'), true)) {
                    $valorPago = $valorTotal;
                }
            }
        }

        if ($valorPago < 0.0) {
            $valorPago = 0.0;
        }

        if ($valorPago > $valorTotal) {
            $valorPago = $valorTotal;
        }

        return $valorPago;
    }

    private function pagamentoGatewayAprovado(array $pedido)
    {
        $status = strtolower(trim((string) ($pedido['payment_provider_status'] ?? '')));
        if (in_array($status, array('paid', 'pago', 'aprovado', 'approved', 'confirmed', 'success', 'succeeded', 'completed'), true)) {
            return true;
        }

        if (empty($pedido['id'])) {
            return false;
        }

        $transacoes = $this->pagamentoGatewayTransacaoModel->listByPedido((int) $pedido['id']);
        if (empty($transacoes[0]['status'])) {
            return false;
        }

        $statusTransacao = strtolower(trim((string) $transacoes[0]['status']));
        return in_array($statusTransacao, array('paid', 'pago', 'aprovado', 'approved', 'confirmed', 'success', 'succeeded', 'completed'), true);
    }

    private function resolverLinkPagamento(array $pedido, $fallback)
    {
        $gateway = strtolower(trim((string) ($pedido['payment_gateway'] ?? '')));
        $paymentUrl = trim((string) ($pedido['payment_provider_payment_url'] ?? ''));

        if ($gateway === 'abacatepay' && $paymentUrl !== '') {
            return $paymentUrl;
        }

        if ($gateway === 'abacatepay') {
            Logger::info('pedido_recuperacao.abacatepay.fallback_resumo', array(
                'pedido_id' => isset($pedido['id']) ? (int) $pedido['id'] : null,
                'payment_external_id' => isset($pedido['payment_external_id']) ? (string) $pedido['payment_external_id'] : null,
            ));
        }

        return $fallback;
    }

    private function logAndReturn($pedidoId, $alunoId, $cursoId, $tipoEnvio, $status, $message, $motivoBloqueio, $emailDestino, $valorPendente, $adminUserId, $ipAddress, $userAgent, array $extra = array(), $emailEnvioId = null)
    {
        $this->logModel->create(array(
            'pedido_id' => $pedidoId,
            'aluno_id' => $alunoId,
            'curso_id' => $cursoId,
            'admin_user_id' => $adminUserId,
            'canal' => 'email',
            'modelo_chave' => isset($extra['modelo_chave']) ? $extra['modelo_chave'] : ($extra['modelo'] ?? 'pedido_recuperacao_primeiro_lembrete'),
            'tipo_envio' => $tipoEnvio,
            'status' => $status,
            'email_destino' => $emailDestino,
            'valor_pendente' => $valorPendente,
            'cupom_codigo' => isset($extra['cupom_codigo']) ? $extra['cupom_codigo'] : null,
            'motivo_bloqueio' => $motivoBloqueio,
            'erro' => isset($extra['erro']) ? $extra['erro'] : null,
            'email_envio_id' => $emailEnvioId,
            'etapa' => isset($extra['etapa']) ? $extra['etapa'] : null,
            'execucao_id' => isset($extra['execucao_id']) ? $extra['execucao_id'] : null,
            'enviado_em' => $status === 'enviado' ? date('Y-m-d H:i:s') : null,
        ));

        Logger::info('pedido_recuperacao.registro', array(
            'pedido_id' => $pedidoId,
            'status' => $status,
            'motivo_bloqueio' => $motivoBloqueio,
        ));

        return array(
            'ok' => $status === 'enviado',
            'status' => $status,
            'message' => $message,
            'motivo_bloqueio' => $motivoBloqueio,
        );
    }

    private function formatarMoeda($valor)
    {
        return 'R$ ' . number_format(max(0.0, (float) $valor), 2, ',', '.');
    }
}
