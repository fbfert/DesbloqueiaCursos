<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Env;
use PDO;
use Throwable;

/**
 * Freio do atendimento público da Norminha.
 *
 * Sem sessão não há a quem responsabilizar por excesso, então conta-se por
 * origem. O endereço nunca é gravado em claro: guarda-se um HASH dele com a
 * APP_KEY como sal. Isso basta para contar e não serve para identificar — sem a
 * APP_KEY, que não está no banco, um dump desta tabela não diz de quem são as
 * linhas.
 *
 * TODA JANELA DE TEMPO É DECIDIDA PELO BANCO
 *
 * O PHP roda em UTC e o MySQL em UTC−3. Uma janela calculada em PHP e comparada
 * contra coluna gravada pelo banco erra o dia das 21h à meia-noite; este projeto
 * já teve um bloqueio de login inerte por exatamente isso.
 *
 * A ORDEM DAS ATRIBUIÇÕES NO `ON DUPLICATE KEY` É SIGNIFICATIVA
 *
 * O MySQL avalia da esquerda para a direita. `mensagens_janela` precisa ser
 * decidida ANTES de `janela_inicio` ser reescrita, senão a comparação já usaria
 * o instante novo e a janela nunca reiniciaria.
 */
class NorminhaPublicoLimiteService
{
    /** Mensagens por janela curta, e o tamanho dela. */
    const LIMITE_JANELA = 15;
    const JANELA_SEGUNDOS = 300;

    /** Teto diário por origem. */
    const LIMITE_DIA = 120;

    private $pdo;

    public function __construct(PDO $pdo = null)
    {
        $this->pdo = $pdo ?: Database::connection();
    }

    /**
     * Registra uma mensagem e diz se ela pode ser atendida.
     *
     * FALHA ABRINDO, ao contrário do teto de gasto. Aqui não há dinheiro em
     * jogo: se o contador quebrar, recusar atendimento a quem está tentando
     * criar uma conta é o pior dos dois erros.
     *
     * @return array permitido, motivo
     */
    public function registrar($ip)
    {
        $hash = $this->hash($ip);
        if ($hash === null) {
            return array('permitido' => true, 'motivo' => 'sem_origem');
        }

        try {
            $sql = 'INSERT INTO norminha_uso_publico
                        (ip_hash, dia, janela_inicio, mensagens_janela, mensagens_dia, created_at)
                    VALUES (:h, CURDATE(), NOW(), 1, 1, NOW())
                    ON DUPLICATE KEY UPDATE
                        mensagens_janela = IF(janela_inicio <= DATE_SUB(NOW(), INTERVAL '
                            . (int) self::JANELA_SEGUNDOS . ' SECOND), 1, mensagens_janela + 1),
                        janela_inicio = IF(janela_inicio <= DATE_SUB(NOW(), INTERVAL '
                            . (int) self::JANELA_SEGUNDOS . ' SECOND), NOW(), janela_inicio),
                        mensagens_dia = mensagens_dia + 1,
                        updated_at = NOW()';

            $st = $this->pdo->prepare($sql);
            $st->execute(array('h' => $hash));

            $leitura = $this->pdo->prepare(
                'SELECT mensagens_janela, mensagens_dia FROM norminha_uso_publico
                  WHERE ip_hash = :h AND dia = CURDATE() LIMIT 1'
            );
            $leitura->execute(array('h' => $hash));
            $linha = $leitura->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return array('permitido' => true, 'motivo' => 'contador_indisponivel');
        }

        if (!$linha) {
            return array('permitido' => true, 'motivo' => 'sem_registro');
        }

        if ((int) $linha['mensagens_dia'] > self::LIMITE_DIA) {
            return array('permitido' => false, 'motivo' => 'limite_diario');
        }

        if ((int) $linha['mensagens_janela'] > self::LIMITE_JANELA) {
            return array('permitido' => false, 'motivo' => 'limite_janela');
        }

        return array('permitido' => true, 'motivo' => 'dentro');
    }

    /**
     * Hash do IP com a APP_KEY como sal.
     *
     * Sem APP_KEY não há sal, e um sha256 de IP sem sal é reversível por força
     * bruta em segundos — o espaço de endereços é pequeno. Nesse caso é melhor
     * não contar do que gravar algo que se passa por anônimo e não é.
     */
    private function hash($ip)
    {
        $ip = trim((string) $ip);
        if ($ip === '') {
            return null;
        }

        $sal = trim((string) Env::get('APP_KEY', ''));
        if ($sal === '') {
            return null;
        }

        return hash('sha256', $sal . '|norminha-publico|' . $ip);
    }
}
