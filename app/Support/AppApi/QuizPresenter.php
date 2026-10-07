<?php

namespace App\Support\AppApi;

/**
 * JSON do quiz (contrato §Quiz). Gabarito, acerto e comentário só saem depois
 * do envio e só quando o quiz permite — a mesma regra de
 * ConteudoQuizService::montarPerguntasParaAluno, que já remove `correta` das
 * alternativas enquanto a tentativa está em andamento.
 */
class QuizPresenter
{
    /**
     * @param array $quiz       linha de conteudo_quizzes
     * @param array $estrutura  ConteudoQuizService::estruturaDoQuiz
     * @param array $tentativas listarTentativasAluno
     */
    public static function estado(array $quiz, array $estrutura, ?array $emAndamento, array $tentativas, $podeIniciar)
    {
        $lista = array();
        foreach ($tentativas as $tentativa) {
            $status = (string) ($tentativa['status'] ?? '');
            if ($status === 'em_andamento' || $status === 'cancelada') {
                continue;
            }
            $lista[] = array(
                'id' => (int) $tentativa['id'],
                'status' => self::statusTentativa($tentativa),
                'percentual' => self::mostraResultado($quiz) ? self::percentual($tentativa['percentual'] ?? null) : null,
                'aprovado' => self::mostraResultado($quiz) && isset($tentativa['aprovado']) && $tentativa['aprovado'] !== null ? (bool) $tentativa['aprovado'] : null,
                'enviada_em' => Tempo::iso($tentativa['enviada_em'] ?? null),
            );
        }

        return array(
            'regras' => array(
                'percentual_minimo' => self::percentual($quiz['percentual_minimo'] ?? 0),
                'exige_aprovacao' => !empty($quiz['exige_aprovacao']),
                'duracao_minutos' => isset($quiz['duracao_minutos']) && $quiz['duracao_minutos'] !== null ? (int) $quiz['duracao_minutos'] : null,
                'tentativas_maximas' => isset($quiz['tentativas_maximas']) && $quiz['tentativas_maximas'] !== null ? (int) $quiz['tentativas_maximas'] : null,
                'tentativas_usadas' => (int) ($estrutura['tentativas_usadas'] ?? 0),
                'mostra_resultado' => self::mostraResultado($quiz),
                'mostra_gabarito' => !empty($quiz['exibir_gabarito_apos_envio']),
            ),
            'tentativa_em_andamento' => $emAndamento ? array(
                'id' => (int) $emAndamento['id'],
                'expira_em' => Tempo::iso($emAndamento['expira_em'] ?? null),
            ) : null,
            'tentativas' => $lista,
            'pode_iniciar' => (bool) $podeIniciar,
        );
    }

    /** Tentativa em andamento com as perguntas sorteadas e o rascunho salvo. */
    public static function tentativaIniciada(array $tentativa, array $perguntas, ?array $tempo)
    {
        $saidaPerguntas = array();
        $respostas = array();
        foreach (array_values($perguntas) as $indice => $pergunta) {
            $saidaPerguntas[] = self::perguntaBase($pergunta, $indice);
            $resposta = self::respostaDoAluno($pergunta);
            if ($resposta !== null) {
                $respostas[(string) (int) $pergunta['id']] = $resposta;
            }
        }

        return array(
            'tentativa' => array(
                'id' => (int) $tentativa['id'],
                'expira_em' => Tempo::iso($tentativa['expira_em'] ?? null),
                'segundos_restantes' => $tempo !== null ? (int) $tempo['segundos_restantes'] : null,
            ),
            'perguntas' => $saidaPerguntas,
            'respostas' => empty($respostas) ? new \stdClass() : $respostas,
        );
    }

    /** Resultado (envio e `GET .../quiz/tentativas/{id}`), a partir de obterTentativaParaAluno. */
    public static function resultado(array $dados)
    {
        $tentativa = $dados['tentativa'];
        $quiz = $dados['quiz'];
        $enviada = in_array((string) ($tentativa['status'] ?? ''), array('corrigida', 'enviada'), true);
        $mostraResultado = $enviada && self::mostraResultado($quiz);
        $mostraGabarito = $enviada && !empty($quiz['exibir_gabarito_apos_envio']);
        $mostraComentario = $enviada && !empty($quiz['exibir_comentarios_apos_envio']);

        $perguntas = array();
        foreach (array_values((array) $dados['perguntas']) as $indice => $pergunta) {
            $base = self::perguntaBase($pergunta, $indice);
            $discursiva = (string) ($pergunta['tipo'] ?? '') === 'discursiva';
            $resposta = isset($pergunta['resposta']) && is_array($pergunta['resposta']) ? $pergunta['resposta'] : null;

            $gabarito = null;
            if ($mostraGabarito && !$discursiva) {
                $gabarito = array();
                foreach ((array) ($pergunta['alternativas'] ?? array()) as $alternativa) {
                    if (!empty($alternativa['correta'])) {
                        $gabarito[] = (int) $alternativa['id'];
                    }
                }
            }

            $base['resposta'] = self::respostaDoAluno($pergunta) ?: array('alternativas' => array(), 'texto' => null);
            $base['correta'] = ($mostraResultado && !$discursiva && $resposta !== null) ? !empty($resposta['correta']) : null;
            $base['gabarito'] = $gabarito;
            $base['comentario_html'] = $mostraComentario ? Formato::htmlSeguro($pergunta['explicacao'] ?? '', 'full') : null;
            $perguntas[] = $base;
        }

        return array(
            'tentativa' => array(
                'id' => (int) $tentativa['id'],
                'numero' => (int) ($tentativa['numero_tentativa'] ?? 0),
                'status' => self::statusTentativa($tentativa),
                'enviada_em' => Tempo::iso($tentativa['enviada_em'] ?? null),
                'corrigida_em' => Tempo::iso($tentativa['corrigida_em'] ?? null),
                'encerrada_por_tempo' => !empty($tentativa['encerrada_por_tempo']),
            ),
            'percentual' => $mostraResultado ? self::percentual($tentativa['percentual'] ?? null) : null,
            'aprovado' => $mostraResultado && isset($tentativa['aprovado']) && $tentativa['aprovado'] !== null ? (bool) $tentativa['aprovado'] : null,
            'aguardando_correcao' => (string) ($tentativa['discursiva_status'] ?? '') === 'pendente',
            'perguntas' => $perguntas,
        );
    }

    /**
     * Respostas do app → formato de ConteudoQuizService
     * (`respostas[pergunta] = alternativa` e `discursivas[pergunta] = texto`).
     *
     * Entrada: {"1": {"alternativas": [11]}, "2": {"texto": "..."}}
     */
    public static function respostasParaServico($respostas)
    {
        $objetivas = array();
        $discursivas = array();
        if (!is_array($respostas)) {
            return array($objetivas, $discursivas);
        }

        foreach ($respostas as $perguntaId => $resposta) {
            $perguntaId = (int) $perguntaId;
            if ($perguntaId <= 0 || !is_array($resposta)) {
                continue;
            }
            if (array_key_exists('texto', $resposta) && $resposta['texto'] !== null) {
                $discursivas[$perguntaId] = (string) $resposta['texto'];
            }
            if (isset($resposta['alternativas']) && is_array($resposta['alternativas'])) {
                $primeira = reset($resposta['alternativas']);
                $objetivas[$perguntaId] = ($primeira !== false && $primeira !== null && $primeira !== '') ? (int) $primeira : '';
            }
        }

        return array($objetivas, $discursivas);
    }

    public static function statusTentativa(array $tentativa)
    {
        $status = (string) ($tentativa['status'] ?? '');
        if ($status === 'corrigida' && (string) ($tentativa['discursiva_status'] ?? '') === 'pendente') {
            return 'aguardando_correcao';
        }
        if ($status === 'corrigida') {
            return 'corrigida';
        }
        return 'enviada';
    }

    private static function perguntaBase(array $pergunta, $indice)
    {
        $discursiva = (string) ($pergunta['tipo'] ?? 'multipla_escolha') === 'discursiva';
        $alternativas = array();
        if (!$discursiva) {
            foreach ((array) ($pergunta['alternativas'] ?? array()) as $alternativa) {
                $alternativas[] = array(
                    'id' => (int) $alternativa['id'],
                    'texto_html' => Formato::htmlSeguro($alternativa['texto'] ?? '', 'basic') ?: '',
                );
            }
        }
        $ordem = (int) ($pergunta['ordem_apresentacao'] ?? 0);

        return array(
            'id' => (int) $pergunta['id'],
            // "multipla_escolha" no banco é escolha ÚNICA (uma alternativa por questão).
            'tipo' => $discursiva ? 'discursiva' : 'unica',
            'enunciado_html' => Formato::htmlSeguro($pergunta['enunciado'] ?? '', 'full') ?: '',
            'ordem' => $ordem > 0 ? $ordem : ($indice + 1),
            'alternativas' => $alternativas,
        );
    }

    private static function respostaDoAluno(array $pergunta)
    {
        $resposta = isset($pergunta['resposta']) && is_array($pergunta['resposta']) ? $pergunta['resposta'] : null;
        if ($resposta === null) {
            return null;
        }
        $alternativaId = (int) ($resposta['alternativa_id'] ?? 0);
        $texto = isset($resposta['texto_resposta']) && trim((string) $resposta['texto_resposta']) !== '' ? (string) $resposta['texto_resposta'] : null;
        if ($alternativaId <= 0 && $texto === null) {
            return null;
        }

        return array(
            'alternativas' => $alternativaId > 0 ? array($alternativaId) : array(),
            'texto' => $texto,
        );
    }

    private static function mostraResultado(array $quiz)
    {
        return !array_key_exists('exibir_resultado_apos_envio', $quiz) || !empty($quiz['exibir_resultado_apos_envio']);
    }

    private static function percentual($valor)
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        return (int) round((float) $valor);
    }
}
