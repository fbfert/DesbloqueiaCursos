<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Env;
use App\Core\Logger;
use App\Support\AppApi\Tempo;
use PDO;

/**
 * Limite de taxa das rotas públicas sensíveis do app (login, cadastro,
 * recuperação de senha), por IP e por login.
 *
 * A chave gravada é um HASH (sha256 com a APP_KEY como sal) de escopo + valor:
 * conta tentativas sem guardar IP, e-mail ou CPF em claro (mesma ideia de
 * NorminhaPublicoLimiteService). A janela é fixa e decidida no PHP, no fuso da
 * aplicação.
 *
 * FALHA ABRINDO: se o contador quebrar (tabela ausente, banco instável), a
 * tentativa segue — o bloqueio por senha errada do AuthService continua valendo
 * por baixo. Recusar login legítimo por defeito do contador é o pior dos erros.
 */
class AppLimiteTaxaService
{
    /** escopo => [limite, janela em segundos] */
    const REGRAS = array(
        'login_ip' => array(30, 900),
        'login_conta' => array(10, 900),
        'cadastro_ip' => array(10, 3600),
        'recuperar_ip' => array(10, 3600),
        'recuperar_conta' => array(5, 3600),
    );

    private $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    private function db()
    {
        return $this->pdo ?: Database::connection();
    }

    /**
     * Registra uma tentativa e diz se ela pode seguir.
     *
     * @return array permitido (bool), retry_after (segundos, quando bloqueado)
     */
    public function registrar($escopo, $valor)
    {
        $escopo = (string) $escopo;
        $valor = trim(mb_strtolower((string) $valor));
        if ($valor === '' || !isset(self::REGRAS[$escopo])) {
            return array('permitido' => true, 'retry_after' => 0);
        }

        list($limite, $janela) = self::REGRAS[$escopo];
        $chave = $this->hash($escopo, $valor);
        $agora = Tempo::agoraTs();

        try {
            $db = $this->db();
            $leitura = $db->prepare('SELECT janela_inicio, contagem FROM app_limites_taxa WHERE chave_hash = :c LIMIT 1');
            $leitura->execute(array('c' => $chave));
            $linha = $leitura->fetch(PDO::FETCH_ASSOC);

            $inicio = $linha ? Tempo::timestamp($linha['janela_inicio']) : null;
            if (!$linha || $inicio === null || ($agora - $inicio) >= $janela) {
                $inicio = $agora;
                $contagem = 1;
                $stmt = $db->prepare(
                    'INSERT INTO app_limites_taxa (chave_hash, escopo, janela_inicio, contagem, updated_at)
                     VALUES (:c, :e, :j, 1, :u)
                     ON DUPLICATE KEY UPDATE janela_inicio = VALUES(janela_inicio), contagem = 1, updated_at = VALUES(updated_at)'
                );
                $stmt->execute(array('c' => $chave, 'e' => $escopo, 'j' => Tempo::sql($inicio), 'u' => Tempo::sql($agora)));
            } else {
                $stmt = $db->prepare('UPDATE app_limites_taxa SET contagem = contagem + 1, updated_at = :u WHERE chave_hash = :c');
                $stmt->execute(array('u' => Tempo::sql($agora), 'c' => $chave));
                $contagem = (int) $linha['contagem'] + 1;
            }
        } catch (\Throwable $e) {
            Logger::warning('app.limite_taxa.indisponivel', array('escopo' => $escopo, 'exception' => get_class($e)));
            return array('permitido' => true, 'retry_after' => 0);
        }

        if ($contagem > $limite) {
            return array('permitido' => false, 'retry_after' => max(1, ($inicio + $janela) - $agora));
        }

        return array('permitido' => true, 'retry_after' => 0);
    }

    /** Remove contadores parados há mais de um dia (chamado pelo cron de push). */
    public function limparAntigos()
    {
        try {
            $stmt = $this->db()->prepare('DELETE FROM app_limites_taxa WHERE updated_at < :l');
            $stmt->execute(array('l' => Tempo::sql(Tempo::agoraTs() - 86400)));
            return $stmt->rowCount();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function hash($escopo, $valor)
    {
        $sal = trim((string) Env::get('APP_KEY', ''));
        if ($sal === '') {
            // Sem APP_KEY o hash fica sem sal (reversível por força bruta para IP),
            // mas o limite de login não pode simplesmente desligar. A APP_KEY é
            // obrigatória em produção (.env.example).
            $sal = 'desbloqueia-app-sem-app-key';
        }

        return hash('sha256', $sal . '|app-limite|' . $escopo . '|' . $valor);
    }
}
