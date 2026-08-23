<?php

namespace App\Services;

use App\Core\Logger;
use App\Models\NorminhaUso;
use App\Models\TutorConfiguracao;

/**
 * Rate limit da Norminha. Primeira infraestrutura de throttle do projeto.
 *
 * A auditoria confirmou que não existia nenhuma: sem Redis, sem APCu, sem
 * middleware. O servidor tem apenas curl, json, mbstring e pdo_mysql, então a
 * contagem vive no MySQL, na tabela norminha_uso.
 *
 * DUAS JANELAS
 *   curta  — 20 mensagens por 5 minutos, contra rajada
 *   diária — 200 mensagens por dia, contra abuso sustentado
 * Mais um contador separado para mensagens que consomem IA, que a Onda 1 usa.
 *
 * IDENTIDADE: o aluno da sessão. IP nunca é a identidade — limitar por IP
 * puniria uma escola inteira atrás do mesmo NAT. IP entra só no log.
 *
 * A CONTAGEM E A DECISÃO VIVEM NO MESMO RELÓGIO. Todo cálculo de tempo acontece
 * em SQL. É o cuidado que faltou no bloqueio de login do projeto, que gravava o
 * prazo com NOW() e o comparava com time() do PHP — três horas à frente — e por
 * isso nunca bloqueou ninguém. Ver docs/2026-08-15-fuso-horario-php-mysql.md.
 *
 * O INCREMENTO ACONTECE ANTES DA DECISÃO, e é atômico. Uma rajada bloqueada
 * continua contando: insistir não sai de graça. O efeito colateral é que a
 * requisição que estoura o limite também é contabilizada, o que é o
 * comportamento desejado.
 */
class NorminhaRateLimitService
{
    const JANELA_SEGUNDOS = 300;
    const LIMITE_JANELA = 20;
    const LIMITE_DIARIO = 200;
    const LIMITE_IA_DIARIO = 100;

    /** Chaves lidas do admin (Etapa 14). Ausentes, valem os defaults acima. */
    const CHAVE_JANELA = 'tutor_ia_limite_5min';
    const CHAVE_DIARIO = 'tutor_ia_limite_diario';

    private $usoModel;
    private $configuracaoModel;
    private $configuracoes;

    public function __construct(NorminhaUso $usoModel = null, TutorConfiguracao $configuracaoModel = null)
    {
        $this->usoModel = $usoModel ?: new NorminhaUso();
        $this->configuracaoModel = $configuracaoModel ?: new TutorConfiguracao();
    }

    /**
     * Registra a mensagem e decide se ela pode seguir.
     *
     * @return array permitido, motivo, retry_after, limites e contadores
     */
    public function registrarEVerificar($usuarioId, $usouIa = false)
    {
        $usuarioId = (int) $usuarioId;
        if ($usuarioId <= 0) {
            return $this->bloqueado('sem_usuario', 60, array());
        }

        $limiteJanela = $this->limite(self::CHAVE_JANELA, self::LIMITE_JANELA);
        $limiteDiario = $this->limite(self::CHAVE_DIARIO, self::LIMITE_DIARIO);

        $estado = $this->usoModel->registrar($usuarioId, $usouIa, self::JANELA_SEGUNDOS);
        if (!is_array($estado)) {
            // Falha de contagem não pode virar porta aberta nem porta fechada
            // demais: registra e deixa passar, porque o limite do provedor e o
            // hard cap de gasto são a última linha, não esta.
            Logger::warning('norminha.ratelimit.indisponivel', array('usuario_id' => $usuarioId));

            return $this->permitido(array(), $limiteJanela, $limiteDiario);
        }

        if ($estado['mensagens_janela'] > $limiteJanela) {
            $retry = max(1, (int) $estado['segundos_para_liberar']);
            $this->registrarBloqueio($usuarioId, 'janela', $estado, $retry);

            return $this->bloqueado('janela', $retry, $estado, $limiteJanela, $limiteDiario);
        }

        if ($estado['mensagens_dia'] > $limiteDiario) {
            $retry = $this->usoModel->segundosAteFimDoDia();
            $this->registrarBloqueio($usuarioId, 'diario', $estado, $retry);

            return $this->bloqueado('diario', $retry, $estado, $limiteJanela, $limiteDiario);
        }

        return $this->permitido($estado, $limiteJanela, $limiteDiario);
    }

    /** Consulta sem incrementar, para diagnóstico e telemetria. */
    public function estado($usuarioId)
    {
        return $this->usoModel->estado((int) $usuarioId, self::JANELA_SEGUNDOS);
    }

    /** Mensagem em PT-BR para o aluno, coerente com o motivo do bloqueio. */
    public function mensagemDeBloqueio(array $resultado)
    {
        if (($resultado['motivo'] ?? '') === 'diario') {
            return 'Você atingiu o limite de mensagens de hoje. Amanhã podemos continuar.';
        }

        $segundos = (int) ($resultado['retry_after'] ?? 60);
        if ($segundos <= 90) {
            return 'Você enviou muitas mensagens seguidas. Aguarde alguns instantes e tente de novo.';
        }

        return 'Você enviou muitas mensagens seguidas. Tente de novo em cerca de '
            . (int) ceil($segundos / 60) . ' minutos.';
    }

    // -----------------------------------------------------------------

    private function limite($chave, $padrao)
    {
        if ($this->configuracoes === null) {
            try {
                $this->configuracoes = $this->configuracaoModel->allIndexed();
            } catch (\Throwable $e) {
                $this->configuracoes = array();
            }
        }

        if (!isset($this->configuracoes[$chave]) || !is_numeric($this->configuracoes[$chave])) {
            return $padrao;
        }

        // Faixa sã: o admin não pode desligar a proteção nem torná-la absurda.
        return max(1, min(10000, (int) $this->configuracoes[$chave]));
    }

    private function permitido(array $estado, $limiteJanela, $limiteDiario)
    {
        return array(
            'permitido' => true,
            'motivo' => null,
            'retry_after' => 0,
            'mensagens_janela' => (int) ($estado['mensagens_janela'] ?? 0),
            'mensagens_dia' => (int) ($estado['mensagens_dia'] ?? 0),
            'limite_janela' => $limiteJanela,
            'limite_diario' => $limiteDiario,
        );
    }

    private function bloqueado($motivo, $retryAfter, array $estado, $limiteJanela = self::LIMITE_JANELA, $limiteDiario = self::LIMITE_DIARIO)
    {
        return array(
            'permitido' => false,
            'motivo' => $motivo,
            'retry_after' => (int) $retryAfter,
            'mensagens_janela' => (int) ($estado['mensagens_janela'] ?? 0),
            'mensagens_dia' => (int) ($estado['mensagens_dia'] ?? 0),
            'limite_janela' => $limiteJanela,
            'limite_diario' => $limiteDiario,
        );
    }

    /**
     * Registra o bloqueio em dois lugares, porque servem a coisas diferentes:
     * a coluna bloqueios_dia alimenta o painel de telemetria (consultável por
     * período); o log guarda a evidência de segurança, com IP — que entra aqui
     * como evidência, nunca como identidade.
     */
    private function registrarBloqueio($usuarioId, $motivo, array $estado, $retry)
    {
        $this->usoModel->registrarBloqueio($usuarioId);

        Logger::warning('norminha.ratelimit.bloqueado', array(
            'usuario_id' => $usuarioId,
            'motivo' => $motivo,
            'mensagens_janela' => (int) $estado['mensagens_janela'],
            'mensagens_dia' => (int) $estado['mensagens_dia'],
            'retry_after' => (int) $retry,
            'ip_address' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null,
        ));
    }
}
