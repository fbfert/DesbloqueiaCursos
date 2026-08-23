<?php

namespace App\Services;

use App\Core\Database;
use App\Models\Inscricao;
use PDO;

/**
 * Traduz sessão + palpites do browser em contexto acadêmico VALIDADO.
 *
 * O que este service existe para impedir: que a Norminha responda sobre um
 * curso que o aluno não cursa. Todo id que chega do navegador é palpite. Um
 * palpite que não sobrevive à validação é DESCARTADO — nunca corrigido, nunca
 * forçado, nunca usado "só para consultar".
 *
 * REGRA DE MATRÍCULA: não é reimplementada aqui. Quem decide se o aluno tem
 * acesso é Inscricao::forUsuarioAprovadas(), a MESMA chamada que
 * AreaCursoService::carregarAluno() faz na linha 78 — status da inscrição,
 * pedido aprovado ou comprovante aceito, e prazo de acesso não vencido, tudo
 * em uma query. Duplicar essa regra aqui criaria duas verdades sobre quem pode
 * estudar o quê.
 *
 * CUSTO: a auditoria da Etapa 0 mediu carregarAluno() em 32 queries, 25 ms e
 * 12,8 KB — ele monta a tela inteira. Chamado a cada mensagem de chat seria
 * desproporcional para preencher um DTO de dez campos. Este service consulta
 * a mesma origem da regra e resolve módulo/item com consultas próprias:
 * 1 query sem palpite de conteúdo, 2 com.
 *
 * PROGRESSO: vem de inscricoes.percentual_progresso, a coluna materializada
 * que a tela do aluno exibe (AreaCursoService.php:125). NÃO se usa
 * ProgressoService::resumoAluno(): ele lê o modelo legado de `aulas`, vazio
 * desde o Conteúdo Unificado, e devolve 0% para todo mundo. Ver
 * docs/norminha/CONTEXTO-EXECUCAO.md § C1.
 *
 * PRIVACIDADE: forUsuarioAprovadas() traz pagador_nome, pagador_email,
 * pagador_telefone e participante_cpf. Nada disso entra no DTO — os campos são
 * escolhidos por lista branca, não por exclusão, para que uma coluna nova na
 * query não vaze por descuido.
 *
 * Nenhum método deste service escreve no banco.
 */
class NorminhaContextService
{
    const CONTEXTO_AREA_ALUNO = 'area_aluno';
    const CONTEXTO_CURSO = 'curso';
    const CONTEXTO_AULA = 'aula';
    const CONTEXTO_AVALIACAO = 'avaliacao';

    /** Tipos de item que valem nota. Guardam gabarito e disparam o guardrail. */
    const TIPOS_AVALIACAO = array('quiz', 'avaliacao_textual');

    /** Status de inscrição que significam curso terminado. */
    const STATUS_CONCLUIDOS = array('concluida', 'concluida_sem_certificado', 'certificado_emitido');

    private $inscricaoModel;

    public function __construct()
    {
        $this->inscricaoModel = new Inscricao();
    }

    /**
     * @param int   $usuarioId  SEMPRE de Session::get('usuario_id'). Nunca do payload.
     * @param array $hints      inscricao_id, curso_id, turma_id, modulo_id, item_id, rota
     */
    public function resolver($usuarioId, array $hints = array())
    {
        $usuarioId = (int) $usuarioId;
        if ($usuarioId <= 0) {
            return $this->semInscricao(0, $hints);
        }

        // 1 query: a regra de matrícula inteira.
        $inscricoes = $this->inscricaoModel->forUsuarioAprovadas($usuarioId);

        if (!$inscricoes) {
            return $this->semInscricao($usuarioId, $hints);
        }

        $descartados = array();
        $inscricao = $this->escolherInscricao($inscricoes, $hints, $descartados);

        // Mais de uma matrícula e nenhum palpite válido: quem desambigua é o
        // aluno, não o servidor. Escolher a mais recente daria resposta
        // confiante sobre o curso errado.
        if ($inscricao === null) {
            return $this->ambiguo($usuarioId, $inscricoes, $descartados, $hints);
        }

        $cursoEventoId = (int) $inscricao['curso_evento_id'];
        $turmaId = !empty($inscricao['turma_id']) ? (int) $inscricao['turma_id'] : null;

        // 0 ou 1 query: resolve módulo e item, validados contra ESTE curso.
        $conteudo = $this->resolverConteudo($cursoEventoId, $hints, $descartados);

        $itemAtual = $conteudo['item'];
        $moduloAtual = $conteudo['modulo'];

        $emAvaliacao = $itemAtual !== null
            && in_array($itemAtual['tipo'], self::TIPOS_AVALIACAO, true);

        return array(
            'estado' => 'ok',
            'usuario_id' => $usuarioId,
            'inscricao_id' => (int) $inscricao['id'],
            'curso_evento_id' => $cursoEventoId,
            'turma_id' => $turmaId,
            'curso_titulo' => $this->texto($inscricao, 'curso_nome'),
            'turma_nome' => $this->texto($inscricao, 'turma_nome'),
            'modulo_atual' => $moduloAtual,
            'item_atual' => $itemAtual,
            'contexto' => $this->normalizarContexto($hints, $itemAtual, $emAvaliacao),
            'rota' => $this->rota($hints),
            'assessment_context' => array(
                'em_avaliacao' => $emAvaliacao,
                'tipo' => $emAvaliacao ? $itemAtual['tipo'] : null,
                'item_id' => $emAvaliacao ? $itemAtual['id'] : null,
            ),
            'flags' => array(
                'tem_inscricao_ativa' => true,
                'tem_item_atual' => $itemAtual !== null,
                'curso_concluido' => $this->cursoConcluido($inscricao),
            ),
            'percentual_progresso' => $this->percentual($inscricao),
            'apto_certificado' => !empty($inscricao['apto_certificado']),
            'opcoes' => array(),
            'hints_descartados' => $descartados,
        );
    }

    /**
     * Remove o que o modelo de linguagem nunca deve ver.
     *
     * usuario_id é de uso interno: identidade vem da sessão, e mandá-la ao
     * provedor só criaria a ilusão de que o modelo pode escolher o usuário.
     * hints_descartados é diagnóstico de servidor.
     */
    public function paraModelo(array $contexto)
    {
        unset($contexto['usuario_id'], $contexto['hints_descartados']);

        return $contexto;
    }

    // -----------------------------------------------------------------
    // Escolha da inscrição
    // -----------------------------------------------------------------

    private function escolherInscricao(array $inscricoes, array $hints, array &$descartados)
    {
        $inscricaoHint = $this->inteiroPositivo(isset($hints['inscricao_id']) ? $hints['inscricao_id'] : null);
        $cursoHint = $this->inteiroPositivo(isset($hints['curso_id']) ? $hints['curso_id'] : null);
        $turmaHint = $this->inteiroPositivo(isset($hints['turma_id']) ? $hints['turma_id'] : null);

        if ($inscricaoHint !== null) {
            foreach ($inscricoes as $inscricao) {
                if ((int) $inscricao['id'] === $inscricaoHint) {
                    // Curso/turma incoerentes com a inscrição real são ruído do
                    // browser; a inscrição manda, os outros palpites caem.
                    if ($cursoHint !== null && (int) $inscricao['curso_evento_id'] !== $cursoHint) {
                        $descartados[] = array('campo' => 'curso_id', 'motivo' => 'nao_pertence_a_inscricao');
                    }

                    return $inscricao;
                }
            }

            // Não distinguimos "não existe" de "é de outra pessoa": a resposta
            // ao aluno não pode confirmar a existência de recurso alheio.
            $descartados[] = array('campo' => 'inscricao_id', 'motivo' => 'nao_autorizada');
        }

        if ($cursoHint !== null) {
            $candidatas = array();
            foreach ($inscricoes as $inscricao) {
                if ((int) $inscricao['curso_evento_id'] !== $cursoHint) {
                    continue;
                }
                if ($turmaHint !== null && (int) $inscricao['turma_id'] !== $turmaHint) {
                    continue;
                }
                $candidatas[] = $inscricao;
            }

            if (count($candidatas) === 1) {
                return $candidatas[0];
            }
            if (!$candidatas) {
                $descartados[] = array('campo' => 'curso_id', 'motivo' => 'sem_matricula');
            }
        }

        // Matrícula única: não há o que desambiguar.
        if (count($inscricoes) === 1) {
            return $inscricoes[0];
        }

        return null;
    }

    // -----------------------------------------------------------------
    // Resolução de módulo e item
    // -----------------------------------------------------------------

    /**
     * Resolve item e módulo em UMA query, já validados contra o curso da
     * inscrição. É aqui que morre a tentativa de ler a aula de outro curso
     * trocando o item_id na URL: o WHERE exige curso_evento_id.
     */
    private function resolverConteudo($cursoEventoId, array $hints, array &$descartados)
    {
        $itemHint = $this->inteiroPositivo(isset($hints['item_id']) ? $hints['item_id'] : null);
        $moduloHint = $this->inteiroPositivo(isset($hints['modulo_id']) ? $hints['modulo_id'] : null);

        if ($itemHint !== null) {
            $stmt = Database::connection()->prepare(
                'SELECT i.id, i.titulo, i.tipo, i.obrigatorio,
                        m.id AS modulo_id, m.titulo AS modulo_titulo
                 FROM conteudo_itens i
                 INNER JOIN conteudo_modulos m
                         ON m.id = i.modulo_id
                        AND m.deleted_at IS NULL
                        AND m.status = "publicado"
                 WHERE i.id = :item_id
                   AND i.curso_evento_id = :curso_evento_id
                   AND i.deleted_at IS NULL
                   AND i.status = "publicado"
                 LIMIT 1'
            );
            $stmt->execute(array('item_id' => $itemHint, 'curso_evento_id' => (int) $cursoEventoId));
            $linha = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($linha) {
                return array(
                    'item' => array(
                        'id' => (int) $linha['id'],
                        'titulo' => (string) $linha['titulo'],
                        'tipo' => (string) $linha['tipo'],
                        'obrigatorio' => !empty($linha['obrigatorio']),
                    ),
                    'modulo' => array(
                        'id' => (int) $linha['modulo_id'],
                        'titulo' => (string) $linha['modulo_titulo'],
                    ),
                );
            }

            $descartados[] = array('campo' => 'item_id', 'motivo' => 'fora_do_curso_ou_nao_publicado');
        }

        if ($moduloHint !== null) {
            $stmt = Database::connection()->prepare(
                'SELECT m.id, m.titulo
                 FROM conteudo_modulos m
                 WHERE m.id = :modulo_id
                   AND m.curso_evento_id = :curso_evento_id
                   AND m.deleted_at IS NULL
                   AND m.status = "publicado"
                 LIMIT 1'
            );
            $stmt->execute(array('modulo_id' => $moduloHint, 'curso_evento_id' => (int) $cursoEventoId));
            $linha = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($linha) {
                return array(
                    'item' => null,
                    'modulo' => array('id' => (int) $linha['id'], 'titulo' => (string) $linha['titulo']),
                );
            }

            $descartados[] = array('campo' => 'modulo_id', 'motivo' => 'fora_do_curso_ou_nao_publicado');
        }

        return array('item' => null, 'modulo' => null);
    }

    // -----------------------------------------------------------------
    // Estados especiais
    // -----------------------------------------------------------------

    private function semInscricao($usuarioId, array $hints)
    {
        return array(
            'estado' => 'sem_inscricao',
            'usuario_id' => (int) $usuarioId,
            'inscricao_id' => null,
            'curso_evento_id' => null,
            'turma_id' => null,
            'curso_titulo' => null,
            'turma_nome' => null,
            'modulo_atual' => null,
            'item_atual' => null,
            'contexto' => self::CONTEXTO_AREA_ALUNO,
            'rota' => $this->rota($hints),
            'assessment_context' => array('em_avaliacao' => false, 'tipo' => null, 'item_id' => null),
            'flags' => array(
                'tem_inscricao_ativa' => false,
                'tem_item_atual' => false,
                'curso_concluido' => false,
            ),
            'percentual_progresso' => 0.0,
            'apto_certificado' => false,
            'opcoes' => array(),
            'hints_descartados' => array(),
        );
    }

    /**
     * Devolve a lista mínima para a interface perguntar de qual curso se trata.
     * Só id, título e turma — nada de progresso ou certificado antes de o aluno
     * dizer sobre o que quer falar.
     */
    private function ambiguo($usuarioId, array $inscricoes, array $descartados, array $hints)
    {
        $opcoes = array();
        foreach ($inscricoes as $inscricao) {
            $opcoes[] = array(
                'inscricao_id' => (int) $inscricao['id'],
                'curso_titulo' => $this->texto($inscricao, 'curso_nome'),
                'turma_nome' => $this->texto($inscricao, 'turma_nome'),
            );
        }

        $base = $this->semInscricao($usuarioId, $hints);
        $base['estado'] = 'ambiguo';
        $base['flags']['tem_inscricao_ativa'] = true;
        $base['opcoes'] = $opcoes;
        $base['hints_descartados'] = $descartados;

        return $base;
    }

    // -----------------------------------------------------------------
    // Auxiliares
    // -----------------------------------------------------------------

    /**
     * O tipo do item manda sobre a rota: uma URL de aula que carrega um quiz é
     * contexto de avaliação, e o guardrail precisa disso mesmo que o browser
     * tenha dito outra coisa.
     */
    private function normalizarContexto(array $hints, $itemAtual, $emAvaliacao)
    {
        if ($emAvaliacao) {
            return self::CONTEXTO_AVALIACAO;
        }
        if ($itemAtual !== null) {
            return self::CONTEXTO_AULA;
        }

        $rota = $this->rota($hints);
        if ($rota === null) {
            return self::CONTEXTO_AREA_ALUNO;
        }

        // Rotas V2 e legadas. TutorVirtualService::resolverContexto() ainda não
        // conhece /v2/ (ver CONTEXTO-EXECUCAO.md § C6); aqui já conhece.
        if (strpos($rota, '/v2/quiz') === 0 || strpos($rota, '/v2/atividade') === 0) {
            return self::CONTEXTO_AVALIACAO;
        }
        if (strpos($rota, '/v2/aula') === 0 || strpos($rota, '/aluno/curso/') === 0
            || strpos($rota, '/area-curso/conteudo') === 0) {
            return self::CONTEXTO_AULA;
        }
        if (strpos($rota, '/area-curso/modulo') === 0 || strpos($rota, '/aluno/cursos/modulo') === 0) {
            return self::CONTEXTO_CURSO;
        }

        return self::CONTEXTO_AREA_ALUNO;
    }

    private function cursoConcluido(array $inscricao)
    {
        $status = isset($inscricao['status']) ? (string) $inscricao['status'] : '';

        return in_array($status, self::STATUS_CONCLUIDOS, true);
    }

    private function percentual(array $inscricao)
    {
        if (!isset($inscricao['percentual_progresso']) || !is_numeric($inscricao['percentual_progresso'])) {
            return 0.0;
        }

        return round(max(0.0, min(100.0, (float) $inscricao['percentual_progresso'])), 2);
    }

    private function rota(array $hints)
    {
        $rota = isset($hints['rota']) ? trim((string) $hints['rota']) : '';
        if ($rota === '' || $rota[0] !== '/') {
            return null;
        }

        // Só o caminho: querystring do browser não é contexto confiável.
        $caminho = parse_url($rota, PHP_URL_PATH);
        if (!is_string($caminho) || $caminho === '') {
            return null;
        }

        return substr($caminho, 0, 255);
    }

    private function texto(array $linha, $chave)
    {
        if (!isset($linha[$chave])) {
            return null;
        }

        $valor = trim((string) $linha[$chave]);

        return $valor !== '' ? $valor : null;
    }

    private function inteiroPositivo($valor)
    {
        if ($valor === null || $valor === '' || !is_numeric($valor)) {
            return null;
        }

        $valor = (int) $valor;

        return $valor > 0 ? $valor : null;
    }
}
