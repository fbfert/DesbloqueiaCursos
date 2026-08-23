<?php

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Contadores de uso por aluno — base do rate limit.
 *
 * O projeto não tinha nenhuma infraestrutura de throttle, e o servidor não tem
 * Redis nem APCu: só MySQL. Uma linha por (usuario_id, dia), com duas janelas:
 * a curta (padrão 5 minutos) contra rajada, e a do dia contra abuso sustentado.
 *
 * IDENTIDADE: o aluno é o da sessão. IP não entra aqui — só no log de segurança.
 * Limitar por IP puniria uma escola inteira atrás do mesmo NAT.
 *
 * FUSO — o ponto crítico deste arquivo. O PHP desta aplicação roda em UTC e o
 * MySQL em UTC−3 (docs/2026-08-15-fuso-horario-php-mysql.md). O bloqueio de
 * login do projeto ficou INERTE por exatamente esse motivo: gravava o prazo com
 * NOW() e o comparava com time() do PHP, três horas à frente, então a condição
 * nunca era verdadeira. Aqui a janela é gravada E comparada dentro do SQL, no
 * mesmo relógio. Nenhum método deste Model recebe ou devolve timestamp de PHP
 * para decisão de tempo.
 */
class NorminhaUso
{
    const JANELA_SEGUNDOS_PADRAO = 300;

    /**
     * Registra uma mensagem e devolve o estado já atualizado.
     *
     * Atômico sob concorrência: uma única instrução resolve criação, incremento
     * e reinício de janela. Duas abas enviando ao mesmo tempo produzem dois
     * incrementos, nunca uma linha duplicada — o UNIQUE (usuario_id, dia) e o
     * ON DUPLICATE KEY UPDATE cuidam disso.
     *
     * A ORDEM das atribuições abaixo é significativa: o MySQL as avalia da
     * esquerda para a direita, então `mensagens_janela` precisa ser calculado
     * ANTES de `janela_inicio` ser reiniciado. Invertê-las faria o contador
     * comparar a janela já renovada consigo mesma e nunca reiniciar.
     *
     * O incremento acontece mesmo quando o limite já foi estourado: uma rajada
     * bloqueada continua contando, o que é o comportamento desejado — do
     * contrário, insistir sairia de graça.
     */
    public function registrar($usuarioId, $usouIa = false, $janelaSegundos = self::JANELA_SEGUNDOS_PADRAO)
    {
        $usuarioId = (int) $usuarioId;
        if ($usuarioId <= 0) {
            return null;
        }

        $janelaSegundos = $this->janelaValida($janelaSegundos);
        $ia = !empty($usouIa) ? 1 : 0;

        $stmt = Database::connection()->prepare(
            'INSERT INTO norminha_uso
                (usuario_id, dia, janela_inicio, mensagens_janela, mensagens_dia, mensagens_ia_dia,
                 created_at, updated_at)
             VALUES
                (:usuario_id, CURDATE(), NOW(), 1, 1, :ia_inicial, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                mensagens_janela = IF(janela_inicio <= DATE_SUB(NOW(), INTERVAL ' . $janelaSegundos . ' SECOND),
                                      1, mensagens_janela + 1),
                janela_inicio    = IF(janela_inicio <= DATE_SUB(NOW(), INTERVAL ' . $janelaSegundos . ' SECOND),
                                      NOW(), janela_inicio),
                mensagens_dia    = mensagens_dia + 1,
                mensagens_ia_dia = mensagens_ia_dia + :ia_incremento,
                updated_at       = NOW()'
        );

        $stmt->execute(array(
            'usuario_id' => $usuarioId,
            'ia_inicial' => $ia,
            'ia_incremento' => $ia,
        ));

        return $this->estado($usuarioId, $janelaSegundos);
    }

    /**
     * Estado atual, sem incrementar.
     *
     * A expiração da janela é aplicada na leitura, em SQL: se `janela_inicio` já
     * passou, o contador da janela é reportado como zero mesmo antes de a linha
     * ser reescrita. Assim a decisão nunca depende de a linha ter sido tocada.
     */
    public function estado($usuarioId, $janelaSegundos = self::JANELA_SEGUNDOS_PADRAO)
    {
        $usuarioId = (int) $usuarioId;
        if ($usuarioId <= 0) {
            return null;
        }

        $janelaSegundos = $this->janelaValida($janelaSegundos);

        $stmt = Database::connection()->prepare(
            'SELECT
                 IF(janela_inicio <= DATE_SUB(NOW(), INTERVAL ' . $janelaSegundos . ' SECOND),
                    0, mensagens_janela) AS mensagens_janela,
                 mensagens_dia,
                 mensagens_ia_dia,
                 GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(),
                     DATE_ADD(janela_inicio, INTERVAL ' . $janelaSegundos . ' SECOND))) AS segundos_para_liberar,
                 janela_inicio
             FROM norminha_uso
             WHERE usuario_id = :usuario_id
               AND dia = CURDATE()
             LIMIT 1'
        );
        $stmt->execute(array('usuario_id' => $usuarioId));

        $linha = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$linha) {
            return array(
                'mensagens_janela' => 0,
                'mensagens_dia' => 0,
                'mensagens_ia_dia' => 0,
                'segundos_para_liberar' => 0,
                'janela_inicio' => null,
            );
        }

        return array(
            'mensagens_janela' => (int) $linha['mensagens_janela'],
            'mensagens_dia' => (int) $linha['mensagens_dia'],
            'mensagens_ia_dia' => (int) $linha['mensagens_ia_dia'],
            'segundos_para_liberar' => (int) $linha['segundos_para_liberar'],
            'janela_inicio' => $linha['janela_inicio'],
        );
    }

    /**
     * Segundos até a virada do dia, pelo relógio do BANCO.
     *
     * É o Retry-After correto quando o limite estourado é o diário. Calcular
     * isto em PHP erraria por três horas: o PHP roda em UTC e o MySQL em UTC−3,
     * então das 21h à meia-noite o PHP já virou o dia e devolveria um prazo
     * negativo ou quase zero — liberando quem deveria estar bloqueado.
     */
    public function segundosAteFimDoDia()
    {
        $stmt = Database::connection()->query(
            'SELECT GREATEST(1, TIMESTAMPDIFF(SECOND, NOW(), DATE_ADD(CURDATE(), INTERVAL 1 DAY))) AS restante'
        );
        $linha = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;

        return $linha ? (int) $linha['restante'] : 60;
    }

    /**
     * Rotação: a tabela não pode crescer para sempre.
     *
     * Uma linha por aluno por dia. Chamado pela Etapa 15; o corte usa CURDATE(),
     * o relógio do banco, coerente com a gravação.
     */
    public function limparAnteriores($diasRetidos = 90)
    {
        $diasRetidos = max(1, min(3650, (int) $diasRetidos));

        $stmt = Database::connection()->prepare(
            'DELETE FROM norminha_uso
             WHERE dia < DATE_SUB(CURDATE(), INTERVAL ' . $diasRetidos . ' DAY)'
        );
        $stmt->execute();

        return $stmt->rowCount();
    }

    /**
     * Quantos alunos bateram o limite nos últimos N dias.
     *
     * `dia` é gravado com CURDATE(), o relógio do banco. Comparar com uma data
     * calculada por date() do PHP erraria o dia inteiro toda noite. A janela
     * fica em SQL.
     */
    public function contarNoLimiteUltimosDias($dias, $limiteDiario)
    {
        $dias = max(1, min(365, (int) $dias));

        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) AS total
             FROM norminha_uso
             WHERE dia >= DATE_SUB(CURDATE(), INTERVAL ' . $dias . ' DAY)
               AND mensagens_dia >= :limite'
        );
        $stmt->execute(array('limite' => (int) $limiteDiario));

        $linha = $stmt->fetch(PDO::FETCH_ASSOC);

        return $linha ? (int) $linha['total'] : 0;
    }

    /**
     * Quantos alunos bateram o limite em período explícito.
     * ⚠️ $de e $ate são datas de parede locais; nunca calcule com date() do PHP.
     */
    public function contarNoLimite($de, $ate, $limiteDiario)
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) AS total
             FROM norminha_uso
             WHERE dia >= :de
               AND dia <= :ate
               AND mensagens_dia >= :limite'
        );
        $stmt->execute(array(
            'de' => (string) $de,
            'ate' => (string) $ate,
            'limite' => (int) $limiteDiario,
        ));

        $linha = $stmt->fetch(PDO::FETCH_ASSOC);

        return $linha ? (int) $linha['total'] : 0;
    }

    /**
     * A janela vem da configuração, nunca do usuário, mas é interpolada no SQL
     * (INTERVAL não aceita placeholder), então é forçada a inteiro dentro de
     * uma faixa sã antes de qualquer coisa.
     */
    private function janelaValida($segundos)
    {
        return max(10, min(86400, (int) $segundos));
    }
}
