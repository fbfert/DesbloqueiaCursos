<?php

namespace App\Support;

use App\Core\Database;
use App\Core\Env;
use PDO;
use Throwable;

/**
 * De onde saem a chave, o modelo e o teto de gasto da IA.
 *
 * DUAS ORIGENS, COM PRECEDÊNCIA DECLARADA
 *
 * O `.env` vem primeiro; o banco é a segunda opção. Quem administra por arquivo
 * não é forçado a migrar, e — mais importante — uma chave no `.env` não pode ser
 * trocada por quem tem acesso ao painel administrativo. Para quem prefere esse
 * arranjo mais fechado, basta manter OPENAI_API_KEY definida: a tela passa a
 * informar que a chave vem do arquivo e não oferece edição.
 *
 * A CHAVE DO BANCO É CIFRADA
 *
 * Com App\Support\Crypto, cuja chave de cifragem mora no `.env` (APP_KEY). Um
 * dump do banco — que é exatamente o que vazou neste projeto em 2026 — não
 * entrega a chave da OpenAI, porque APP_KEY não está no banco.
 *
 * Sem APP_KEY, Crypto::encrypt() devolve null e o segredo seria descartado em
 * silêncio. Foi o que aconteceu com a credencial do gateway de pagamento, que
 * ficou meses parecendo salva e nunca esteve. Por isso `podeGuardarNoBanco()`
 * existe: a tela precisa poder dizer "não dá para salvar aqui" ANTES de aceitar
 * o que o administrador digitou.
 */
class NorminhaCredenciais
{
    const CHAVE_KEY = 'tutor_ia_openai_key';
    const CHAVE_MODELO = 'tutor_ia_modelo';
    const CHAVE_TETO = 'tutor_ia_teto_mensal_usd';

    /** A chave em texto puro, de onde estiver. Nunca vai para tela nem para log. */
    public static function chaveOpenAi()
    {
        $doEnv = trim((string) Env::get('OPENAI_API_KEY', ''));
        if ($doEnv !== '') {
            return $doEnv;
        }

        $cifrada = self::configuracao(self::CHAVE_KEY);
        if ($cifrada === '') {
            return '';
        }

        $aberta = Crypto::decrypt($cifrada);

        return $aberta === null ? '' : (string) $aberta;
    }

    /** 'env', 'banco' ou 'nenhuma' — para a tela explicar quem manda. */
    public static function origemDaChave()
    {
        if (trim((string) Env::get('OPENAI_API_KEY', '')) !== '') {
            return 'env';
        }

        $cifrada = self::configuracao(self::CHAVE_KEY);
        if ($cifrada === '') {
            return 'nenhuma';
        }

        // Cifrado presente mas ilegível: APP_KEY mudou depois de gravar. Dizer
        // 'banco' aqui faria a tela afirmar que está tudo certo enquanto a
        // integração não funciona.
        return Crypto::decrypt($cifrada) === null ? 'ilegivel' : 'banco';
    }

    public static function modelo()
    {
        $doEnv = trim((string) Env::get('OPENAI_MODEL', ''));
        if ($doEnv !== '') {
            return $doEnv;
        }

        return self::configuracao(self::CHAVE_MODELO);
    }

    public static function origemDoModelo()
    {
        return trim((string) Env::get('OPENAI_MODEL', '')) !== '' ? 'env' : 'banco';
    }

    /** Teto mensal em dólares. Só do banco: é decisão de produto, não de infra. */
    public static function tetoMensalUsd()
    {
        $v = self::configuracao(self::CHAVE_TETO);

        return $v === '' ? 0.0 : (float) $v;
    }

    /** Dá para cifrar? Sem APP_KEY, não — e a tela precisa saber antes de aceitar. */
    public static function podeGuardarNoBanco()
    {
        return Crypto::hasKey();
    }

    /**
     * Cifra para gravar. Devolve null se não for possível, e quem chama tem a
     * obrigação de tratar — nunca gravar o null por cima do que já existia.
     */
    public static function cifrar($chaveEmTextoPuro)
    {
        return Crypto::encrypt(trim((string) $chaveEmTextoPuro));
    }

    private static function configuracao($chave)
    {
        try {
            $pdo = Database::connection();
            $st = $pdo->prepare('SELECT valor FROM tutor_configuracoes WHERE chave = :c LIMIT 1');
            $st->execute(array('c' => $chave));
            $v = $st->fetchColumn();

            return $v === false || $v === null ? '' : trim((string) $v);
        } catch (Throwable $e) {
            // Banco indisponível não pode derrubar quem só queria montar a
            // configuração. Sem valor, a integração fica desligada.
            return '';
        }
    }
}
