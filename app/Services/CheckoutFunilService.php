<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use Exception;

/**
 * Instrumentacao do funil do checkout rapido.
 *
 * Grava tres eventos em `auditoria_logs` (tabela que ja existe, com indice
 * em `acao` e em `created_at`):
 *
 *   checkout_iniciado  - tela carregada
 *   pix_gerado         - formulario enviado com sucesso, cobranca criada
 *   pix_pago           - pagamento confirmado pelo servidor do provedor
 *
 * Todos carregam pedido_id em `entidade_id` (exceto checkout_iniciado, que
 * ainda nao tem pedido) e detalhes em `metadados` (JSON).
 *
 * Consulta de queda entre etapas:
 *
 *   SELECT acao, COUNT(*) FROM auditoria_logs
 *    WHERE acao IN ('checkout_iniciado','pix_gerado','pix_pago')
 *      AND created_at >= '2026-08-01'
 *    GROUP BY acao;
 */
class CheckoutFunilService
{
    const EVENTO_INICIADO = 'checkout_iniciado';
    const EVENTO_PIX_GERADO = 'pix_gerado';
    const EVENTO_PIX_PAGO = 'pix_pago';

    /**
     * Tela de checkout carregada. Ainda nao ha pedido: o vinculo e feito
     * pela sessao, para dar para casar a visita com o pedido depois.
     */
    public function iniciado($cursoId, $sessaoId, array $origem = array(), $ipAddress = null, $userAgent = null)
    {
        return $this->registrar(self::EVENTO_INICIADO, 'curso_evento', (int) $cursoId, array(
            'curso_evento_id' => (int) $cursoId,
            'sessao' => $sessaoId,
            'origem' => $this->resumoOrigem($origem),
        ), null, $ipAddress, $userAgent);
    }

    public function pixGerado($pedidoId, $usuarioId, array $dados = array(), $ipAddress = null, $userAgent = null)
    {
        return $this->registrar(self::EVENTO_PIX_GERADO, 'pedido', (int) $pedidoId, array(
            'pedido_id' => (int) $pedidoId,
            'curso_evento_id' => isset($dados['curso_evento_id']) ? (int) $dados['curso_evento_id'] : null,
            'valor' => isset($dados['valor']) ? (float) $dados['valor'] : null,
            'usuario_novo' => !empty($dados['usuario_novo']),
            'expira_em' => isset($dados['expira_em']) ? $dados['expira_em'] : null,
            'tentativa' => isset($dados['tentativa']) ? (int) $dados['tentativa'] : 1,
            'origem' => $this->resumoOrigem(isset($dados['origem']) ? $dados['origem'] : array()),
        ), $usuarioId, $ipAddress, $userAgent);
    }

    public function pixPago($pedidoId, $usuarioId, array $dados = array(), $ipAddress = null, $userAgent = null)
    {
        return $this->registrar(self::EVENTO_PIX_PAGO, 'pedido', (int) $pedidoId, array(
            'pedido_id' => (int) $pedidoId,
            'curso_evento_id' => isset($dados['curso_evento_id']) ? (int) $dados['curso_evento_id'] : null,
            'valor' => isset($dados['valor']) ? (float) $dados['valor'] : null,
            'gateway' => isset($dados['gateway']) ? $dados['gateway'] : null,
            'event_id' => isset($dados['event_id']) ? $dados['event_id'] : null,
            'segundos_ate_pagar' => isset($dados['segundos_ate_pagar']) ? (int) $dados['segundos_ate_pagar'] : null,
        ), $usuarioId, $ipAddress, $userAgent);
    }

    /**
     * Resumo do funil por periodo, para a tela de admin. Uma linha por etapa.
     */
    public function resumo($de = null, $ate = null, $cursoId = null)
    {
        $sql = 'SELECT acao, COUNT(*) AS total
                  FROM auditoria_logs
                 WHERE acao IN (:e1, :e2, :e3)';
        $params = array(
            'e1' => self::EVENTO_INICIADO,
            'e2' => self::EVENTO_PIX_GERADO,
            'e3' => self::EVENTO_PIX_PAGO,
        );

        if ($de !== null) {
            $sql .= ' AND created_at >= :de';
            $params['de'] = $de;
        }
        if ($ate !== null) {
            $sql .= ' AND created_at <= :ate';
            $params['ate'] = $ate;
        }
        if ((int) $cursoId > 0) {
            // metadados e JSON em TEXT; LIKE resolve sem exigir MySQL 8.
            $sql .= ' AND metadados LIKE :curso';
            $params['curso'] = '%"curso_evento_id":' . (int) $cursoId . '%';
        }

        $sql .= ' GROUP BY acao';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        $base = array(
            self::EVENTO_INICIADO => 0,
            self::EVENTO_PIX_GERADO => 0,
            self::EVENTO_PIX_PAGO => 0,
        );

        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $linha) {
            $base[$linha['acao']] = (int) $linha['total'];
        }

        $iniciado = $base[self::EVENTO_INICIADO];
        $gerado = $base[self::EVENTO_PIX_GERADO];
        $pago = $base[self::EVENTO_PIX_PAGO];

        return array(
            'checkout_iniciado' => $iniciado,
            'pix_gerado' => $gerado,
            'pix_pago' => $pago,
            'queda_formulario' => $iniciado > 0 ? round(($iniciado - $gerado) / $iniciado * 100, 1) : null,
            'queda_pagamento' => $gerado > 0 ? round(($gerado - $pago) / $gerado * 100, 1) : null,
            'conversao_total' => $iniciado > 0 ? round($pago / $iniciado * 100, 1) : null,
        );
    }

    /**
     * Serie diaria dos tres eventos, para o grafico do admin.
     * Devolve uma linha por dia no periodo, inclusive dias sem evento.
     */
    public function serieDiaria($dias = 30)
    {
        $dias = max(1, min(180, (int) $dias));

        $stmt = Database::connection()->prepare(
            'SELECT DATE(created_at) AS dia, acao, COUNT(*) AS total
               FROM auditoria_logs
              WHERE acao IN (:e1, :e2, :e3)
                AND created_at >= DATE_SUB(CURDATE(), INTERVAL :dias DAY)
              GROUP BY DATE(created_at), acao
              ORDER BY dia'
        );

        $stmt->bindValue(':e1', self::EVENTO_INICIADO);
        $stmt->bindValue(':e2', self::EVENTO_PIX_GERADO);
        $stmt->bindValue(':e3', self::EVENTO_PIX_PAGO);
        $stmt->bindValue(':dias', $dias - 1, \PDO::PARAM_INT);
        $stmt->execute();

        $porDia = array();
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $linha) {
            $porDia[$linha['dia']][$linha['acao']] = (int) $linha['total'];
        }

        $serie = array();
        for ($i = $dias - 1; $i >= 0; $i--) {
            $dia = date('Y-m-d', strtotime('-' . $i . ' day'));
            $serie[] = array(
                'dia' => $dia,
                'iniciado' => isset($porDia[$dia][self::EVENTO_INICIADO]) ? $porDia[$dia][self::EVENTO_INICIADO] : 0,
                'gerado' => isset($porDia[$dia][self::EVENTO_PIX_GERADO]) ? $porDia[$dia][self::EVENTO_PIX_GERADO] : 0,
                'pago' => isset($porDia[$dia][self::EVENTO_PIX_PAGO]) ? $porDia[$dia][self::EVENTO_PIX_PAGO] : 0,
            );
        }

        return $serie;
    }

    /**
     * Desempenho por campanha de anuncio: quem trouxe pedido e quem trouxe venda.
     */
    public function porCampanha($dias = 30, $limite = 20)
    {
        $dias = max(1, min(365, (int) $dias));

        $stmt = Database::connection()->prepare(
            'SELECT COALESCE(NULLIF(p.utm_campaign, ""), "(sem campanha)") AS campanha,
                    COALESCE(NULLIF(p.utm_source, ""), "(direto)") AS origem,
                    COUNT(*) AS pedidos,
                    SUM(CASE WHEN p.status IN ("pago", "aprovado") THEN 1 ELSE 0 END) AS pagos,
                    SUM(CASE WHEN p.status IN ("pago", "aprovado") THEN p.total ELSE 0 END) AS receita,
                    SUM(CASE WHEN p.gclid IS NOT NULL AND p.gclid <> "" THEN 1 ELSE 0 END) AS com_gclid
               FROM pedidos p
              WHERE p.deleted_at IS NULL
                AND p.canal_origem = "checkout_rapido"
                AND p.created_at >= DATE_SUB(CURDATE(), INTERVAL :dias DAY)
              GROUP BY campanha, origem
              ORDER BY pedidos DESC
              LIMIT ' . (int) $limite
        );

        $stmt->bindValue(':dias', $dias - 1, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Ultimos pedidos abertos pelo checkout rapido, com origem e situacao.
     * E daqui que sai a lista para recuperacao por WhatsApp.
     */
    public function ultimosPedidos($limite = 30)
    {
        $limite = max(1, min(200, (int) $limite));

        $stmt = Database::connection()->query(
            'SELECT p.id, p.codigo, p.status, p.total, p.created_at,
                    p.pagador_email, p.pagador_telefone,
                    p.utm_source, p.utm_campaign, p.gclid,
                    p.conversao_enviada_em, p.conversao_status,
                    u.cadastro_status,
                    ce.nome AS curso_nome
               FROM pedidos p
               LEFT JOIN usuarios u ON u.id = p.comprador_usuario_id
               LEFT JOIN pedido_itens pi ON pi.pedido_id = p.id AND pi.deleted_at IS NULL
               LEFT JOIN cursos_eventos ce ON ce.id = pi.curso_evento_id
              WHERE p.deleted_at IS NULL
                AND p.canal_origem = "checkout_rapido"
              GROUP BY p.id
              ORDER BY p.id DESC
              LIMIT ' . $limite
        );

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Historico de quem ligou e desligou a chave, lido da auditoria.
     */
    public function historicoDaChave($limite = 20)
    {
        $limite = max(1, min(100, (int) $limite));

        $stmt = Database::connection()->query(
            'SELECT a.acao, a.metadados, a.created_at, a.ip_address, u.nome AS usuario_nome, u.email AS usuario_email
               FROM auditoria_logs a
               LEFT JOIN usuarios u ON u.id = a.usuario_id
              WHERE a.acao = "checkout_rapido.configuracao.atualizada"
              ORDER BY a.id DESC
              LIMIT ' . $limite
        );

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function registrar($acao, $entidadeTipo, $entidadeId, array $metadados, $usuarioId, $ipAddress, $userAgent)
    {
        try {
            $stmt = Database::connection()->prepare(
                'INSERT INTO auditoria_logs
                 (usuario_id, acao, entidade_tipo, entidade_id, ip_address, user_agent, metadados, created_at)
                 VALUES (:usuario_id, :acao, :entidade_tipo, :entidade_id, :ip_address, :user_agent, :metadados, NOW())'
            );

            $stmt->execute(array(
                'usuario_id' => $usuarioId ? (int) $usuarioId : null,
                'acao' => $acao,
                'entidade_tipo' => $entidadeTipo,
                'entidade_id' => $entidadeId > 0 ? $entidadeId : null,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent !== null ? substr((string) $userAgent, 0, 255) : null,
                'metadados' => json_encode($metadados, JSON_UNESCAPED_UNICODE),
            ));

            return true;
        } catch (Exception $exception) {
            // Instrumentacao nunca pode derrubar a venda.
            Logger::error('checkout_rapido.funil.falhou', array(
                'acao' => $acao,
                'entidade_id' => $entidadeId,
                'message' => $exception->getMessage(),
            ));

            return false;
        }
    }

    private function resumoOrigem(array $origem)
    {
        $resumo = array();

        foreach (array('utm_source', 'utm_medium', 'utm_campaign', 'gclid') as $campo) {
            if (!empty($origem[$campo])) {
                $resumo[$campo] = $origem[$campo];
            }
        }

        return $resumo;
    }
}
