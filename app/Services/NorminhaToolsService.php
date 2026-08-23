<?php

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Funções de LEITURA que alimentam as respostas determinísticas da Onda 0 e,
 * na Onda 1, as tool calls do modelo.
 *
 * INVARIANTES (qualquer violação é bug de segurança, não de comportamento):
 *
 * - NENHUMA função escreve. Provado em tests/Unit/norminha_tools.php por
 *   contador Com_insert/Com_update/Com_delete/Com_replace.
 * - É PROIBIDO chamar AptidaoCertificadoService::recalcularInscricao() e
 *   ProgressoService::recalcularInscricao(): ambos escrevem. Há teste que varre
 *   o código-fonte da Norminha atrás dessas chamadas.
 * - usuario_id NUNCA é parâmetro vindo do modelo. Ele chega da sessão, e o
 *   escopo é revalidado por NorminhaContextService antes de qualquer consulta.
 * - Nenhum retorno carrega gabarito, alternativa correta, chave de correção,
 *   campo administrativo ou nota de terceiro.
 * - Todo retorno tem teto de tamanho. Não é detalhe: calcularParaInscricao()
 *   devolveu 99 motivos e 64 KB em uma inscrição real do banco. Despejar isso
 *   num prompt seria caro e ilegível.
 *
 * PROGRESSO (correção C1): o percentual exibido vem de
 * inscricoes.percentual_progresso, a coluna materializada que a tela do aluno
 * mostra. ProgressoService::resumoAluno() NÃO é usado — lê o modelo legado de
 * `aulas`, vazio desde o Conteúdo Unificado, e devolve 0% para todo mundo.
 *
 * CERTIFICADO: a situação vem de LmsElegibilidadeService::calcularParaInscricao(),
 * que é o MESMO cálculo que define inscricoes.apto_certificado — por isso a
 * Norminha concorda com a tela. O service inteiro é somente leitura (zero
 * INSERT/UPDATE/DELETE). Ver a nota em docs/norminha/CONTEXTO-EXECUCAO.md.
 *
 * PRÉ-REQUISITOS: o projeto NÃO tem liberação progressiva nem pré-requisito
 * entre itens — não há coluna, tabela ou service para isso. O "próximo passo" é,
 * portanto, a ordem de módulo e item. Nenhuma regra de bloqueio foi inventada.
 */
class NorminhaToolsService
{
    /** Tetos de tamanho de retorno. */
    const MAX_MOTIVOS = 5;
    const MAX_TITULO = 200;
    const MAX_MOTIVO_TEXTO = 300;

    /** Itens que não são passo de estudo: separador visual. */
    const TIPO_SEPARADOR = 'etiqueta';

    private $contextService;
    private $elegibilidadeService;

    public function __construct(NorminhaContextService $contextService = null)
    {
        $this->contextService = $contextService ?: new NorminhaContextService();
        $this->elegibilidadeService = new LmsElegibilidadeService();
    }

    // =================================================================
    // Progresso
    // =================================================================

    public function getStudentProgress($usuarioId, $inscricaoId = null)
    {
        $contexto = $this->contexto($usuarioId, $inscricaoId);
        if (!$contexto['ok']) {
            return $contexto;
        }
        $ctx = $contexto['contexto'];

        $conteudo = $this->elegibilidadeService->calcularElegibilidadeConteudoUnificado(
            $ctx['curso_evento_id'],
            $ctx['turma_id'],
            $ctx['inscricao_id'],
            $usuarioId
        );

        $percentual = (float) $ctx['percentual_progresso'];
        $obrigatorios = (int) ($conteudo['total_itens_obrigatorios'] ?? 0);
        $concluidos = (int) ($conteudo['obrigatorios_concluidos'] ?? 0);

        return array(
            'ok' => true,
            'inscricao_id' => $ctx['inscricao_id'],
            'curso_titulo' => $ctx['curso_titulo'],
            'percentual' => $percentual,
            'itens_concluidos' => $concluidos,
            'itens_obrigatorios' => $obrigatorios,
            'curso_concluido' => (bool) $ctx['flags']['curso_concluido'],
            'label' => $this->rotuloPercentual($percentual),
        );
    }

    // =================================================================
    // Retomada — a regra de três casos da auditoria
    // =================================================================

    /**
     * Onde o aluno parou.
     *
     * O campo `origem` NÃO é decorativo: ele é o que autoriza o texto exibido.
     * Só se diz "você parou aqui" quando origem = ultimo_acesso, porque só aí
     * existe registro real de acesso. Nos outros casos a verdade é "seu próximo
     * passo é" — chamar isso de retomada seria inventar memória que o sistema
     * não tem.
     */
    public function getResumePoint($usuarioId, $inscricaoId = null)
    {
        $contexto = $this->contexto($usuarioId, $inscricaoId);
        if (!$contexto['ok']) {
            return $contexto;
        }
        $ctx = $contexto['contexto'];

        // CASO 1 — maior ultimo_acesso_em ainda não concluído.
        $stmt = Database::connection()->prepare(
            'SELECT i.id, i.titulo, i.tipo, i.obrigatorio,
                    m.id AS modulo_id, m.titulo AS modulo_titulo,
                    p.status AS progresso_status, p.ultimo_acesso_em
             FROM conteudo_progresso_aluno p
             INNER JOIN conteudo_itens i
                     ON i.id = p.item_id
                    AND i.deleted_at IS NULL
                    AND i.status = "publicado"
                    AND i.tipo <> "' . self::TIPO_SEPARADOR . '"
             INNER JOIN conteudo_modulos m
                     ON m.id = i.modulo_id
                    AND m.deleted_at IS NULL
                    AND m.status = "publicado"
             WHERE p.aluno_id = :aluno_id
               AND p.inscricao_id = :inscricao_id
               AND p.deleted_at IS NULL
               AND p.status <> "concluido"
               AND p.ultimo_acesso_em IS NOT NULL
             -- Desempate deterministico: com dois itens no mesmo segundo, vale o
             -- primeiro na ordem do curso. Sem isto a resposta poderia variar
             -- entre chamadas identicas.
             ORDER BY p.ultimo_acesso_em DESC, m.ordem ASC, i.ordem ASC, i.id ASC
             LIMIT 1'
        );
        $stmt->execute(array('aluno_id' => (int) $usuarioId, 'inscricao_id' => $ctx['inscricao_id']));
        $linha = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($linha) {
            return $this->pontoRetomada($ctx, $linha, 'ultimo_acesso', $linha['ultimo_acesso_em']);
        }

        // CASO 2 — primeiro item publicado ainda não concluído, na ordem.
        $proximo = $this->consultarProximoItem($ctx, (int) $usuarioId, false);
        if ($proximo) {
            return $this->pontoRetomada($ctx, $proximo, 'proximo_item', null);
        }

        // CASO 3 — nada pendente.
        return array(
            'ok' => true,
            'inscricao_id' => $ctx['inscricao_id'],
            'curso_titulo' => $ctx['curso_titulo'],
            'origem' => 'curso_concluido',
            'item' => null,
            'modulo' => null,
        );
    }

    // =================================================================
    // Próximo item
    // =================================================================

    /**
     * Próximo passo na ordem de módulo e item.
     *
     * Não existe pré-requisito no projeto, então "próximo" é literalmente o
     * próximo da ordem que ainda não foi concluído. Devolve também quantos
     * obrigatórios seguem pendentes, porque é isso que separa "falta pouco" de
     * "falta o curso inteiro".
     */
    public function getNextLearningItem($usuarioId, $inscricaoId = null)
    {
        $contexto = $this->contexto($usuarioId, $inscricaoId);
        if (!$contexto['ok']) {
            return $contexto;
        }
        $ctx = $contexto['contexto'];

        $item = $this->consultarProximoItem($ctx, (int) $usuarioId, false);
        $obrigatorio = $this->consultarProximoItem($ctx, (int) $usuarioId, true);

        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) AS pendentes
             FROM conteudo_itens i
             INNER JOIN conteudo_modulos m
                     ON m.id = i.modulo_id AND m.deleted_at IS NULL AND m.status = "publicado"
             LEFT JOIN conteudo_progresso_aluno p
                    ON p.item_id = i.id
                   AND p.inscricao_id = :inscricao_id
                   AND p.aluno_id = :aluno_id
                   AND p.deleted_at IS NULL
             WHERE i.curso_evento_id = :curso_evento_id
               AND i.deleted_at IS NULL
               AND i.status = "publicado"
               AND i.obrigatorio = 1
               AND i.tipo <> "' . self::TIPO_SEPARADOR . '"
               AND (p.id IS NULL OR p.status <> "concluido")'
        );
        $stmt->execute(array(
            'inscricao_id' => $ctx['inscricao_id'],
            'aluno_id' => (int) $usuarioId,
            'curso_evento_id' => $ctx['curso_evento_id'],
        ));
        $pendentes = (int) ($stmt->fetch(PDO::FETCH_ASSOC)['pendentes'] ?? 0);

        return array(
            'ok' => true,
            'inscricao_id' => $ctx['inscricao_id'],
            'curso_titulo' => $ctx['curso_titulo'],
            'item' => $item ? $this->resumirItem($item) : null,
            'modulo' => $item ? $this->resumirModulo($item) : null,
            'proximo_obrigatorio' => $obrigatorio ? $this->resumirItem($obrigatorio) : null,
            'obrigatorios_pendentes' => $pendentes,
            'curso_concluido' => $item === null,
        );
    }

    // =================================================================
    // Certificado
    // =================================================================

    /**
     * Situação do certificado, com os motivos vindos do service real.
     *
     * Usa calcularParaInscricao(), que produz a MESMA `situacao` de onde sai
     * inscricoes.apto_certificado — é o que faz a Norminha concordar com a tela
     * do aluno. Custa 8 queries contra 1 da versão só de conteúdo, mas a versão
     * barata não devolve `situacao`, e concordar com a tela é requisito.
     *
     * Os motivos são truncados: o service devolveu 99 deles numa inscrição real.
     */
    public function getCertificateStatus($usuarioId, $inscricaoId = null)
    {
        $contexto = $this->contexto($usuarioId, $inscricaoId);
        if (!$contexto['ok']) {
            return $contexto;
        }
        $ctx = $contexto['contexto'];

        $inscricao = $this->inscricaoAutorizada((int) $usuarioId, $ctx['inscricao_id']);
        if (!$inscricao) {
            return $this->erro('sem_acesso');
        }

        $elegibilidade = $this->elegibilidadeService->calcularParaInscricao($inscricao);

        $situacao = isset($elegibilidade['situacao']) ? (string) $elegibilidade['situacao'] : 'indefinida';
        $apto = in_array($situacao, array('apto', 'certificado_emitido'), true);

        $motivos = isset($elegibilidade['motivos']) && is_array($elegibilidade['motivos'])
            ? $elegibilidade['motivos'] : array();
        $totalMotivos = count($motivos);

        $motivosLimitados = array();
        foreach (array_slice($motivos, 0, self::MAX_MOTIVOS) as $motivo) {
            $motivosLimitados[] = $this->limitar((string) $motivo, self::MAX_MOTIVO_TEXTO);
        }

        return array(
            'ok' => true,
            'inscricao_id' => $ctx['inscricao_id'],
            'curso_titulo' => $ctx['curso_titulo'],
            'situacao' => $situacao,
            'apto' => $apto,
            'certificado_emitido' => $situacao === 'certificado_emitido',
            'motivos' => $motivosLimitados,
            'motivos_total' => $totalMotivos,
            'motivos_omitidos' => max(0, $totalMotivos - count($motivosLimitados)),
            'obrigatorios_concluidos' => (int) ($elegibilidade['conteudo_obrigatorios_concluidos'] ?? 0),
            'obrigatorios_total' => (int) ($elegibilidade['conteudo_total_itens_obrigatorios'] ?? 0),
        );
    }

    // =================================================================
    // Aula atual
    // =================================================================

    /**
     * Metadados da aula atual. Sem corpo do conteúdo: isso é do
     * NorminhaKnowledgeService, na Onda 1, que aplica o filtro de gabarito.
     */
    public function getCurrentLessonContext($usuarioId, array $hints = array())
    {
        $ctx = $this->contextService->resolver($usuarioId, $hints);

        if ($ctx['estado'] !== 'ok') {
            return $this->erro($ctx['estado'] === 'ambiguo' ? 'ambiguo' : 'sem_inscricao', $ctx);
        }

        if (empty($ctx['flags']['tem_item_atual'])) {
            return array(
                'ok' => true,
                'inscricao_id' => $ctx['inscricao_id'],
                'curso_titulo' => $ctx['curso_titulo'],
                'tem_aula_atual' => false,
                'item' => null,
                'modulo' => $ctx['modulo_atual'],
                'em_avaliacao' => false,
            );
        }

        return array(
            'ok' => true,
            'inscricao_id' => $ctx['inscricao_id'],
            'curso_titulo' => $ctx['curso_titulo'],
            'tem_aula_atual' => true,
            'item' => array(
                'id' => $ctx['item_atual']['id'],
                'titulo' => $this->limitar((string) $ctx['item_atual']['titulo'], self::MAX_TITULO),
                'tipo' => $ctx['item_atual']['tipo'],
                'obrigatorio' => !empty($ctx['item_atual']['obrigatorio']),
            ),
            'modulo' => $ctx['modulo_atual'],
            // O guardrail pedagógico depende disto. Quem consome precisa saber
            // que está diante de algo que vale nota ANTES de gerar texto.
            'em_avaliacao' => (bool) $ctx['assessment_context']['em_avaliacao'],
            'tipo_avaliacao' => $ctx['assessment_context']['tipo'],
        );
    }

    // =================================================================
    // Internos
    // =================================================================

    /** Resolve e valida o contexto, ou devolve o erro já formatado. */
    private function contexto($usuarioId, $inscricaoId)
    {
        $hints = array();
        if ($inscricaoId !== null && (int) $inscricaoId > 0) {
            $hints['inscricao_id'] = (int) $inscricaoId;
        }

        $ctx = $this->contextService->resolver($usuarioId, $hints);

        if ($ctx['estado'] === 'ok') {
            return array('ok' => true, 'contexto' => $ctx);
        }

        return $this->erro($ctx['estado'] === 'ambiguo' ? 'ambiguo' : 'sem_inscricao', $ctx);
    }



    /**
     * Próximo item na ordem canônica.
     * `$somenteObrigatorios` separa "o que vem agora" de "o que ainda trava a
     * conclusão do curso".
     */
    private function consultarProximoItem(array $ctx, $usuarioId, $somenteObrigatorios)
    {
        $filtro = $somenteObrigatorios ? ' AND i.obrigatorio = 1' : '';

        $stmt = Database::connection()->prepare(
            'SELECT i.id, i.titulo, i.tipo, i.obrigatorio,
                    m.id AS modulo_id, m.titulo AS modulo_titulo
             FROM conteudo_itens i
             INNER JOIN conteudo_modulos m
                     ON m.id = i.modulo_id AND m.deleted_at IS NULL AND m.status = "publicado"
             LEFT JOIN conteudo_progresso_aluno p
                    ON p.item_id = i.id
                   AND p.inscricao_id = :inscricao_id
                   AND p.aluno_id = :aluno_id
                   AND p.deleted_at IS NULL
             WHERE i.curso_evento_id = :curso_evento_id
               AND i.deleted_at IS NULL
               AND i.status = "publicado"
               AND i.tipo <> "' . self::TIPO_SEPARADOR . '"' . $filtro . '
               AND (p.id IS NULL OR p.status <> "concluido")
             ORDER BY m.ordem ASC, i.ordem ASC, i.id ASC
             LIMIT 1'
        );
        $stmt->execute(array(
            'inscricao_id' => $ctx['inscricao_id'],
            'aluno_id' => (int) $usuarioId,
            'curso_evento_id' => $ctx['curso_evento_id'],
        ));

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * A linha da inscrição vem do NorminhaContextService, que já a carregou para
     * validar o escopo e a memoriza na instância. Buscá-la de novo aqui repetiria
     * a mesma query na mesma chamada.
     */
    private function inscricaoAutorizada($usuarioId, $inscricaoId)
    {
        return $this->contextService->inscricaoAutorizadaBruta($usuarioId, $inscricaoId);
    }

    private function pontoRetomada(array $ctx, array $linha, $origem, $ultimoAcesso)
    {
        return array(
            'ok' => true,
            'inscricao_id' => $ctx['inscricao_id'],
            'curso_titulo' => $ctx['curso_titulo'],
            'origem' => $origem,
            'item' => $this->resumirItem($linha),
            'modulo' => $this->resumirModulo($linha),
            'ultimo_acesso_em' => $origem === 'ultimo_acesso' ? $ultimoAcesso : null,
            'status_progresso' => isset($linha['progresso_status']) ? (string) $linha['progresso_status'] : null,
        );
    }

    private function resumirItem(array $linha)
    {
        return array(
            'id' => (int) $linha['id'],
            'titulo' => $this->limitar((string) $linha['titulo'], self::MAX_TITULO),
            'tipo' => (string) $linha['tipo'],
            'obrigatorio' => !empty($linha['obrigatorio']),
        );
    }

    private function resumirModulo(array $linha)
    {
        return array(
            'id' => (int) $linha['modulo_id'],
            'titulo' => $this->limitar((string) $linha['modulo_titulo'], self::MAX_TITULO),
        );
    }

    /** Percentual em PT-BR: vírgula decimal, como no resto do portal. */
    private function rotuloPercentual($percentual)
    {
        return number_format((float) $percentual, 2, ',', '.') . '% concluído';
    }

    private function limitar($texto, $maximo)
    {
        $texto = trim($texto);
        if (function_exists('mb_strlen') && mb_strlen($texto, 'UTF-8') > $maximo) {
            return mb_substr($texto, 0, $maximo - 1, 'UTF-8') . '…';
        }
        if (!function_exists('mb_strlen') && strlen($texto) > $maximo) {
            return substr($texto, 0, $maximo - 1) . '...';
        }

        return $texto;
    }

    /**
     * Erro com mensagem segura: nunca revela existência de recurso alheio.
     * O estado "ambiguo" carrega as opções, porque a interface precisa perguntar.
     */
    private function erro($codigo, array $ctx = array())
    {
        $mensagens = array(
            'sem_inscricao' => 'Não encontrei uma matrícula ativa sua.',
            'ambiguo' => 'Você tem mais de um curso ativo. Sobre qual deles quer falar?',
            'sem_acesso' => 'Não encontrei uma matrícula ativa sua.',
        );

        $erro = array(
            'ok' => false,
            'erro' => $codigo,
            'mensagem' => isset($mensagens[$codigo]) ? $mensagens[$codigo] : $mensagens['sem_inscricao'],
        );

        if ($codigo === 'ambiguo' && !empty($ctx['opcoes'])) {
            $erro['opcoes'] = $ctx['opcoes'];
        }

        return $erro;
    }
}
