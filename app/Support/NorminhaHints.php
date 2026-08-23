<?php

namespace App\Support;

/**
 * Contrato de pistas de contexto entregues ao componente da Norminha.
 *
 * O QUE ISTO É: um conjunto pequeno de LOCALIZADORES que dizem onde o aluno
 * está. Nada mais.
 *
 * O QUE ISTO NÃO É: autoridade. Todo id daqui é revalidado contra a sessão
 * por NorminhaContextService antes de qualquer consulta. Se o aluno editar o
 * HTML, ou trocar o item_id na URL, o servidor descarta o palpite — nunca o
 * corrige nem o obedece.
 *
 * POR QUE VEM DO CONTROLLER, E NÃO DA QUERY STRING
 * A query traz o que o navegador pediu; o controller traz o que ele
 * efetivamente RESOLVEU e está mostrando na tela. Numa URL adulterada os dois
 * divergem, e a Norminha precisa concordar com a página que o aluno vê.
 * A query continua servindo de reserva para rotas ainda não ligadas.
 *
 * PRIVACIDADE: nenhum campo pessoal entra aqui. Sem nome, e-mail, CPF, nota,
 * telefone. E, em avaliação, NENHUM dado da prova além do id do item — nada de
 * gabarito, alternativa correta ou marcação de acerto no DOM.
 */
class NorminhaHints
{
    const CONTEXTO_AREA_ALUNO = 'area_aluno';
    const CONTEXTO_CURSO = 'curso';
    const CONTEXTO_AULA = 'aula';
    const CONTEXTO_AVALIACAO = 'avaliacao';

    const CONTEXTOS = array(
        self::CONTEXTO_AREA_ALUNO,
        self::CONTEXTO_CURSO,
        self::CONTEXTO_AULA,
        self::CONTEXTO_AVALIACAO,
    );

    /**
     * @param string $contexto  area_aluno | curso | aula | avaliacao
     * @param array  $ids       inscricao_id, curso_id, turma_id, modulo_id, item_id
     * @param string $rota      caminho atual; sem querystring
     */
    public static function montar($contexto, array $ids = array(), $rota = null)
    {
        $inscricao = self::id($ids, 'inscricao_id');
        $curso = self::id($ids, 'curso_id');
        $turma = self::id($ids, 'turma_id');
        $modulo = self::id($ids, 'modulo_id');
        $item = self::id($ids, 'item_id');

        $contexto = in_array($contexto, self::CONTEXTOS, true) ? $contexto : self::CONTEXTO_AREA_ALUNO;

        // O contexto nunca afirma mais do que os ids sustentam.
        //
        // Sem item não há aula atual: a área geral do aluno é área geral, e a
        // Norminha precisa saber disso para não oferecer "tirar dúvida desta
        // aula" sobre coisa nenhuma. E sem curso não há nem página de curso —
        // caso descoberto por teste, quando uma query só com lixo ainda
        // produzia contexto 'curso'.
        if ($item === null && $contexto === self::CONTEXTO_AULA) {
            $contexto = self::CONTEXTO_CURSO;
        }
        if ($curso === null && $contexto === self::CONTEXTO_CURSO) {
            $contexto = self::CONTEXTO_AREA_ALUNO;
        }
        // Avaliação sem item também não se sustenta: é o item que diz o que
        // vale nota. Rebaixar aqui é mais seguro do que manter uma flag de
        // avaliação apontando para nada.
        if ($item === null && $contexto === self::CONTEXTO_AVALIACAO) {
            $contexto = $curso !== null ? self::CONTEXTO_CURSO : self::CONTEXTO_AREA_ALUNO;
        }

        return array(
            'contexto' => $contexto,
            'rota' => self::rota($rota),
            'inscricao_id' => $inscricao,
            'curso_id' => $curso,
            'turma_id' => $turma,
            'modulo_id' => $modulo,
            'item_id' => $item,
        );
    }

    /**
     * Reserva: extrai as pistas da query string.
     *
     * Usada só onde o controller ainda não publica o contexto. É mais fraca de
     * propósito — a query é o que o navegador pediu, não o que o servidor
     * resolveu.
     */
    public static function daQuery(array $query, $rota = null)
    {
        $item = null;
        foreach (array('conteudo_id', 'item_id', 'aula_id') as $chave) {
            $valor = self::id($query, $chave);
            if ($valor !== null) {
                $item = $valor;
                break;
            }
        }

        $caminho = self::rota($rota);
        $contexto = self::CONTEXTO_AREA_ALUNO;
        if ($caminho !== null) {
            if (strpos($caminho, '/v2/quiz') === 0 || strpos($caminho, '/v2/atividade') === 0) {
                $contexto = self::CONTEXTO_AVALIACAO;
            } elseif (strpos($caminho, '/v2/aula') === 0) {
                $contexto = $item !== null ? self::CONTEXTO_AULA : self::CONTEXTO_CURSO;
            }
        }

        return self::montar($contexto, array(
            'inscricao_id' => self::id($query, 'inscricao_id'),
            'curso_id' => self::id($query, 'curso_id'),
            'turma_id' => self::id($query, 'turma_id'),
            'modulo_id' => self::id($query, 'modulo_id'),
            'item_id' => $item,
        ), $rota);
    }

    /** Só inteiro positivo sobrevive; qualquer outra coisa vira null. */
    private static function id(array $origem, $chave)
    {
        if (!isset($origem[$chave])) {
            return null;
        }

        $valor = $origem[$chave];
        if (is_string($valor) && !ctype_digit($valor)) {
            return null;
        }
        if (!is_numeric($valor)) {
            return null;
        }

        $valor = (int) $valor;

        return $valor > 0 ? $valor : null;
    }

    private static function rota($rota)
    {
        if ($rota === null) {
            $rota = parse_url(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/', PHP_URL_PATH);
        }

        $rota = is_string($rota) ? trim($rota) : '';
        if ($rota === '' || $rota[0] !== '/') {
            return null;
        }

        // Sem querystring: ela não é contexto confiável e pode carregar lixo.
        $caminho = parse_url($rota, PHP_URL_PATH);

        return is_string($caminho) && $caminho !== '' ? substr($caminho, 0, 255) : null;
    }
}
