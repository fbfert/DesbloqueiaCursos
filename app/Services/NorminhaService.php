<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Models\NorminhaConversa;
use App\Models\NorminhaMensagem;
use App\Models\TutorConfiguracao;
use App\Models\TutorFala;
use PDO;

/**
 * Orquestrador da Norminha. Onda 0: 100% PHP, zero chamadas a provedor de IA.
 *
 * COMO A ONDA 1 SE PLUGA AQUI
 * Existe UM único ponto de decisão — precisaDeLinguagemLivre(). Quando ele diz
 * "sim" e não há gerador injetado, a resposta segue pelo caminho `unresolved`.
 * A Etapa 12 injeta o gerador nesse mesmo ponto; nada mais deste arquivo muda.
 * O contador chamadasIA() prova em teste que a Onda 0 nunca chama o modelo.
 *
 * O CAMINHO `unresolved` É A ENTREGA MAIS IMPORTANTE DESTA ETAPA
 * Toda pergunta livre que o PHP não resolve é gravada com resolved_by =
 * 'unresolved' e registrada no log com contexto acadêmico e sem PII. É esse
 * número que decide se a Onda 1 se paga. Sem ele, o Checkpoint 0 vira opinião.
 *
 * URLs
 * Nenhuma URL é escrita por texto livre. O servidor monta cada ação a partir de
 * um mapa de chaves e valida o resultado contra a área do aluno V2 antes de
 * devolver. Na Onda 1 o modelo poderá pedir uma AÇÃO por chave, nunca um link.
 *
 * VERDADE NO RÓTULO
 * A retomada só diz "você parou aqui" quando NorminhaToolsService devolve
 * origem = ultimo_acesso. Nos demais casos o texto é "seu próximo passo" —
 * inventar memória que o sistema não tem é o mesmo que mentir com educação.
 */
class NorminhaService
{
    const LIMITE_MENSAGEM = 2000;

    /** Ações aceitas na V1. `action` nunca é classificada por IA. */
    const ACOES = array(
        'resume_course',
        'show_progress',
        'next_step',
        'certificate_status',
        'explain_current_lesson',
        'summarize_current_lesson',
    );

    /** Prefixos de rota que uma ação pode apontar. Nada fora daqui é devolvido. */
    const PREFIXOS_PERMITIDOS = array('/v2/aula', '/v2/quiz', '/v2/atividade', '/v2/aluno');

    private $contextService;
    private $toolsService;
    private $conversaModel;
    private $mensagemModel;
    private $falaModel;

    /** Gerador de linguagem. Null na Onda 0 — é o que a Etapa 12 injeta. */
    private $geradorIA;

    /** Contador de chamadas ao gerador. Precisa ser 0 na Onda 0. */
    private $chamadasIA = 0;

    private $memoriaService;

    /** Conversa em curso, para a janela de memória saber de onde ler. */
    private $conversaAtual = null;

    private $configuracoesTutor = null;

    public function __construct(
        NorminhaContextService $contextService = null,
        NorminhaToolsService $toolsService = null,
        $geradorIA = null
    ) {
        $this->contextService = $contextService ?: new NorminhaContextService();
        $this->toolsService = $toolsService ?: new NorminhaToolsService($this->contextService);
        $this->conversaModel = new NorminhaConversa();
        $this->mensagemModel = new NorminhaMensagem();
        $this->falaModel = new TutorFala();
        $this->memoriaService = new NorminhaMemoriaService();

        // A camada de IA só é ligada quando REALMENTE disponível — provedor
        // habilitado e chave presente. Com OPENAI_ENABLED=false o gerador
        // continua null, e todo o comportamento da Onda 0 permanece idêntico,
        // inclusive o contador em zero. Ligar por engano seria transformar um
        // toggle de configuração em gasto.
        if ($geradorIA !== null) {
            $this->geradorIA = $geradorIA;
        } else {
            // DUAS CHAVES, INDEPENDENTES E AMBAS OBRIGATORIAS:
            //   OPENAI_ENABLED (.env)  — a integracao existe?  infraestrutura
            //   tutor_ia_ativo (banco) — a Norminha usa?       produto
            // Desligar qualquer uma devolve a Norminha ao comportamento da
            // Onda 0, e a do banco e reversivel em um clique, sem deploy.
            $ia = new NorminhaIaService();
            $this->geradorIA = ($ia->disponivel() && $this->iaLigadaNoAdmin()) ? $ia : null;
        }
    }

    /** Quantas vezes o gerador foi acionado. Usado por teste. */
    public function chamadasIA()
    {
        return $this->chamadasIA;
    }

    // =================================================================
    // Entrada única
    // =================================================================

    public function processar($usuarioId, array $payload)
    {
        $usuarioId = (int) $usuarioId;
        if ($usuarioId <= 0) {
            return $this->falha('nao_autenticado', 'Faça login para conversar com a Norminha.', 401);
        }

        $validacao = $this->validarPayload($payload);
        if (!$validacao['ok']) {
            return $validacao;
        }
        $acao = $validacao['acao'];
        $mensagem = $validacao['mensagem'];

        // A POSSE DA CONVERSA É VERIFICADA ANTES DE QUALQUER OUTRA COISA.
        //
        // Ela não pode depender do estado do contexto: um aluno sem matrícula,
        // ou com várias e nenhum palpite, sai por um retorno antecipado logo
        // abaixo — e se a checagem viesse depois, um uuid alheio atravessaria
        // sem ser examinado. Hoje isso não vazaria nada, porque esses retornos
        // não carregam dado da conversa; mas fazer a segurança depender do
        // conteúdo de outra resposta é o tipo de acoplamento que quebra na
        // primeira vez que alguém acrescentar um campo ali.
        $conversaExistente = null;
        $uuidPedido = isset($payload['conversation_id']) ? trim((string) $payload['conversation_id']) : '';
        if ($uuidPedido !== '') {
            $conversaExistente = $this->conversaModel->buscarPorUuid($uuidPedido, $usuarioId);
            if (!$conversaExistente) {
                return $this->falha('conversa_invalida', 'Não consegui recuperar esta conversa.', 403);
            }
        }

        $contexto = $this->contextService->resolver($usuarioId, $this->hints($payload));

        // Sem matrícula não há sobre o que conversar; a mensagem não revela nada.
        if ($contexto['estado'] === 'sem_inscricao') {
            return $this->respostaSimples(
                $usuarioId, $payload, $contexto,
                'Não encontrei uma matrícula ativa sua. Se você acabou de se inscrever, o acesso '
                . 'aparece aqui assim que o pagamento for confirmado.',
                null, 'attention', array()
            );
        }

        // Ambiguidade não se resolve chutando: pergunta-se.
        if ($contexto['estado'] === 'ambiguo') {
            return $this->respostaSimples(
                $usuarioId, $payload, $contexto,
                $this->textoDesambiguacao($contexto),
                null, 'doubt', array()
            );
        }

        $conversa = $this->abrirConversa($usuarioId, $conversaExistente, $contexto);
        $this->conversaAtual = array(
            'id' => $conversa['id'],
            'usuario_id' => $usuarioId,
            'resumo' => $conversaExistente && isset($conversaExistente['resumo']) ? $conversaExistente['resumo'] : null,
        );

        // A mensagem do aluno é persistida UMA vez, antes de qualquer resolução.
        $this->mensagemModel->inserir(
            $conversa['id'],
            'user',
            $mensagem !== null ? $mensagem : ('[ação] ' . $acao),
            array('usuario_id' => $usuarioId)
        );

        if ($acao !== null) {
            $resposta = $this->executarAcao($acao, $usuarioId, $contexto);
        } else {
            $intencao = $this->classificarIntencao($mensagem);

            if ($intencao !== null) {
                $resposta = $this->executarAcao($intencao, $usuarioId, $contexto);
            } elseif ($this->precisaDeLinguagemLivre(null, $mensagem, null)) {
                $resposta = $this->resolverComLinguagem($mensagem, $usuarioId, $contexto);
            } else {
                $resposta = $this->naoResolvida($contexto);
            }
        }

        return $this->finalizar($conversa, $usuarioId, $contexto, $resposta);
    }

    // =================================================================
    // O ÚNICO ponto de decisão sobre linguagem livre
    // =================================================================

    /**
     * Isto precisa de linguagem livre?
     *
     * Na Onda 0 a resposta é sempre "sim" para mensagem não classificada — e,
     * como não há gerador, o fluxo cai em `unresolved`. Manter a pergunta aqui,
     * separada da resposta, é o que permite plugar a IA na Etapa 12 sem tocar no
     * resto do orquestrador.
     */
    private function precisaDeLinguagemLivre($acao, $mensagem, $intencao)
    {
        if ($acao !== null || $intencao !== null) {
            return false;
        }

        return is_string($mensagem) && trim($mensagem) !== '';
    }

    /**
     * Caminho de linguagem. Sem gerador injetado (Onda 0), devolve `unresolved`.
     * A Etapa 12 substitui o corpo do `if` por uma chamada real.
     */
    private function resolverComLinguagem($mensagem, $usuarioId, array $contexto)
    {
        if ($this->geradorIA === null) {
            return $this->naoResolvida($contexto);
        }

        $this->chamadasIA++;

        // O contexto vai em duas formas: a pública (sem usuario_id, sem
        // diagnóstico) é o que pode chegar ao modelo; a interna fica em
        // `opcoes` para o guardrail e as ferramentas, que precisam do escopo
        // real e nunca o expõem.
        $gerado = $this->geradorIA->gerar(
            $mensagem,
            $this->contextService->paraModelo($contexto),
            array(
                'usuario_id' => (int) $usuarioId,
                'contexto' => $contexto,
                'historico' => $this->historicoParaModelo($contexto),
                'prompt_complementar' => $this->promptComplementar(),
            )
        );

        if (!is_array($gerado) || empty($gerado['message'])) {
            // Provedor indisponível ou resposta vazia: não se inventa texto.
            return $this->naoResolvida($contexto);
        }

        return array(
            'message' => (string) $gerado['message'],
            'intent' => isset($gerado['intent']) ? $gerado['intent'] : null,
            'resolved_by' => isset($gerado['resolved_by']) ? $gerado['resolved_by'] : 'ai',
            'avatar_state' => 'explaining',
            'sources' => isset($gerado['sources']) ? $gerado['sources'] : array(),
            'actions' => $this->acoesPadrao($contexto),
        );
    }

    /**
     * Janela curta de histórico (Etapa 13).
     *
     * A conversa corrente é guardada em $conversaAtual durante processar(); sem
     * ela não há histórico a enviar — que é o caso da primeira mensagem.
     */
    protected function historicoParaModelo(array $contexto)
    {
        if (!$this->conversaAtual) {
            return array();
        }

        return $this->memoriaService->janelaComResumo(
            $this->conversaAtual,
            (int) $this->conversaAtual['usuario_id'],
            NorminhaMemoriaService::MAX_MENSAGENS
        );
    }

    /** Complemento de prompt definido no admin (Etapa 14). */
    protected function promptComplementar()
    {
        $cfg = $this->configuracoesTutor();

        return isset($cfg['tutor_ia_prompt_complementar']) ? $cfg['tutor_ia_prompt_complementar'] : null;
    }

    /** A camada de IA está ligada na tela de configurações? */
    protected function iaLigadaNoAdmin()
    {
        $cfg = $this->configuracoesTutor();

        return !empty($cfg['tutor_ia_ativo']);
    }

    /** Configurações do tutor, lidas uma vez por instância. */
    private function configuracoesTutor()
    {
        if ($this->configuracoesTutor === null) {
            try {
                $this->configuracoesTutor = (new TutorConfiguracao())->allIndexed();
            } catch (\Throwable $e) {
                $this->configuracoesTutor = array();
            }
        }

        return $this->configuracoesTutor;
    }

    // =================================================================
    // Fast-paths determinísticos
    // =================================================================

    private function executarAcao($acao, $usuarioId, array $contexto)
    {
        switch ($acao) {
            case 'resume_course':
                return $this->acaoRetomar($usuarioId, $contexto);
            case 'show_progress':
                return $this->acaoProgresso($usuarioId, $contexto);
            case 'next_step':
                return $this->acaoProximoPasso($usuarioId, $contexto);
            case 'certificate_status':
                return $this->acaoCertificado($usuarioId, $contexto);
            case 'explain_current_lesson':
                return $this->acaoConteudoAula($usuarioId, $contexto, 'explicar');
            case 'summarize_current_lesson':
                return $this->acaoConteudoAula($usuarioId, $contexto, 'resumir');
        }

        return $this->naoResolvida($contexto);
    }

    private function acaoRetomar($usuarioId, array $contexto)
    {
        $r = $this->toolsService->getResumePoint($usuarioId, $contexto['inscricao_id']);
        if (empty($r['ok'])) {
            return $this->falhaDeFerramenta($contexto);
        }

        if ($r['origem'] === 'curso_concluido') {
            return array(
                'message' => 'Você concluiu todos os itens de "' . $r['curso_titulo'] . '". '
                    . 'Não há nada pendente para retomar.',
                'intent' => 'resume_course',
                'resolved_by' => 'php',
                'avatar_state' => 'celebrating',
                'sources' => array(),
                'actions' => array($this->acao('view_certificate', $contexto)),
            );
        }

        // O rótulo obedece à origem. Só há retomada quando houve acesso real.
        $abertura = $r['origem'] === 'ultimo_acesso'
            ? 'Você parou em'
            : 'Seu próximo passo é';

        return array(
            'message' => $abertura . ' "' . $r['item']['titulo'] . '", no módulo "'
                . $r['modulo']['titulo'] . '".',
            'intent' => 'resume_course',
            'resolved_by' => 'php',
            'avatar_state' => 'explaining',
            'sources' => array(),
            'actions' => array(
                $this->acao('continue_course', $contexto, array('item' => $r['item'], 'modulo' => $r['modulo'])),
            ),
        );
    }

    private function acaoProgresso($usuarioId, array $contexto)
    {
        $r = $this->toolsService->getStudentProgress($usuarioId, $contexto['inscricao_id']);
        if (empty($r['ok'])) {
            return $this->falhaDeFerramenta($contexto);
        }

        $texto = 'Em "' . $r['curso_titulo'] . '" você está com ' . $r['label'] . '.';
        if ($r['itens_obrigatorios'] > 0) {
            $texto .= ' São ' . $r['itens_concluidos'] . ' de ' . $r['itens_obrigatorios']
                . ' itens obrigatórios concluídos.';
        }

        return array(
            'message' => $texto,
            'intent' => 'show_progress',
            'resolved_by' => 'php',
            'avatar_state' => $r['curso_concluido'] ? 'celebrating' : 'explaining',
            'sources' => array(),
            'actions' => $this->acoesPadrao($contexto),
        );
    }

    private function acaoProximoPasso($usuarioId, array $contexto)
    {
        $r = $this->toolsService->getNextLearningItem($usuarioId, $contexto['inscricao_id']);
        if (empty($r['ok'])) {
            return $this->falhaDeFerramenta($contexto);
        }

        if (empty($r['item'])) {
            return array(
                'message' => 'Não há itens pendentes em "' . $r['curso_titulo'] . '". Você concluiu tudo.',
                'intent' => 'next_step',
                'resolved_by' => 'php',
                'avatar_state' => 'celebrating',
                'sources' => array(),
                'actions' => array($this->acao('view_certificate', $contexto)),
            );
        }

        $texto = 'Seu próximo passo é "' . $r['item']['titulo'] . '", no módulo "'
            . $r['modulo']['titulo'] . '".';
        if ($r['obrigatorios_pendentes'] > 0) {
            $texto .= ' Ainda faltam ' . $r['obrigatorios_pendentes'] . ' '
                . ($r['obrigatorios_pendentes'] === 1 ? 'item obrigatório' : 'itens obrigatórios') . '.';
        }

        return array(
            'message' => $texto,
            'intent' => 'next_step',
            'resolved_by' => 'php',
            'avatar_state' => 'explaining',
            'sources' => array(),
            'actions' => array(
                $this->acao('open_item', $contexto, array('item' => $r['item'], 'modulo' => $r['modulo'])),
            ),
        );
    }

    private function acaoCertificado($usuarioId, array $contexto)
    {
        $r = $this->toolsService->getCertificateStatus($usuarioId, $contexto['inscricao_id']);
        if (empty($r['ok'])) {
            return $this->falhaDeFerramenta($contexto);
        }

        if ($r['certificado_emitido']) {
            $texto = 'Seu certificado de "' . $r['curso_titulo'] . '" já foi emitido.';
            $estado = 'celebrating';
        } elseif ($r['apto']) {
            $texto = 'Você já cumpriu os requisitos do certificado de "' . $r['curso_titulo'] . '".';
            $estado = 'celebrating';
        } else {
            $texto = 'Ainda não é possível emitir o certificado de "' . $r['curso_titulo'] . '".';
            if ($r['motivos']) {
                $texto .= ' Motivos:' . "\n" . '— ' . implode("\n" . '— ', $r['motivos']);
                if ($r['motivos_omitidos'] > 0) {
                    $texto .= "\n" . '(e mais ' . $r['motivos_omitidos'] . ' '
                        . ($r['motivos_omitidos'] === 1 ? 'pendência' : 'pendências') . ')';
                }
            }
            $estado = 'attention';
        }

        return array(
            'message' => $texto,
            'intent' => 'certificate_status',
            'resolved_by' => 'php',
            'avatar_state' => $estado,
            'sources' => array(),
            'actions' => array($this->acao('view_certificate', $contexto)),
        );
    }

    /**
     * Explicar/resumir na Onda 0: devolve a ESTRUTURA oficial do item, sem
     * parafrasear e sem gerar. É honesto sobre o limite — prometer explicação
     * que não existe ainda seria pior do que dizer que ela vem depois.
     */
    private function acaoConteudoAula($usuarioId, array $contexto, $modo)
    {
        $aula = $this->toolsService->getCurrentLessonContext(
            $usuarioId,
            array('inscricao_id' => $contexto['inscricao_id'], 'item_id' => $contexto['item_atual']['id'] ?? null)
        );

        if (empty($aula['ok']) || empty($aula['tem_aula_atual'])) {
            return array(
                'message' => 'Abra uma aula para eu falar sobre ela. Pela área do curso você chega a qualquer item.',
                'intent' => $modo === 'explicar' ? 'explain_current_lesson' : 'summarize_current_lesson',
                'resolved_by' => 'php',
                'avatar_state' => 'doubt',
                'sources' => array(),
                'actions' => $this->acoesPadrao($contexto),
            );
        }

        $estrutura = $this->estruturaDoItem((int) $aula['item']['id']);

        $texto = 'Aula atual: "' . $aula['item']['titulo'] . '".';
        if ($estrutura['topicos']) {
            $texto .= "\n" . 'Tópicos do conteúdo oficial:' . "\n" . '— '
                . implode("\n" . '— ', $estrutura['topicos']);
        } else {
            $texto .= "\n" . 'Este item não tem texto estruturado para listar.';
        }
        $texto .= "\n\n" . 'Por enquanto eu mostro a estrutura oficial da aula. A explicação com '
            . 'outras palavras estará disponível em breve.';

        return array(
            'message' => $texto,
            'intent' => $modo === 'explicar' ? 'explain_current_lesson' : 'summarize_current_lesson',
            'resolved_by' => 'php',
            'avatar_state' => 'explaining',
            'sources' => $estrutura['fontes'],
            'actions' => array(
                $this->acao('open_item', $contexto, array('item' => $aula['item'], 'modulo' => $aula['modulo'])),
            ),
        );
    }

    // =================================================================
    // Classificação conservadora de intenção
    // =================================================================

    /**
     * Só reconhece frases inequívocas. Na dúvida NÃO classifica: uma
     * classificação errada responde com confiança sobre a pergunta errada, o
     * que é pior do que admitir que não entendeu.
     */
    private function classificarIntencao($mensagem)
    {
        if (!is_string($mensagem) || trim($mensagem) === '') {
            return null;
        }

        $t = $this->normalizar($mensagem);

        $regras = array(
            'resume_course' => array('onde parei', 'onde eu parei', 'de onde parei', 'continuar de onde',
                                     'continuar o curso', 'continuar estudando', 'retomar o curso'),
            'show_progress' => array('meu progresso', 'ver meu progresso', 'quanto falta', 'quantos por cento',
                                     'qual meu progresso', 'quanto ja fiz'),
            'next_step' => array('o que faco agora', 'proximo passo', 'proxima aula', 'qual a proxima',
                                 'o que estudar agora'),
            'certificate_status' => array('certificado', 'ja posso emitir', 'emitir certificado'),
        );

        foreach ($regras as $intencao => $padroes) {
            foreach ($padroes as $padrao) {
                if (strpos($t, $padrao) !== false) {
                    return $intencao;
                }
            }
        }

        return null;
    }

    /** Caixa baixa, sem acento e sem pontuação, para comparar frase de aluno. */
    private function normalizar($texto)
    {
        $texto = mb_strtolower(trim((string) $texto), 'UTF-8');

        $de = array('á','à','â','ã','ä','é','è','ê','ë','í','ì','î','ï','ó','ò','ô','õ','ö','ú','ù','û','ü','ç','ñ');
        $para = array('a','a','a','a','a','e','e','e','e','i','i','i','i','o','o','o','o','o','u','u','u','u','c','n');
        $texto = str_replace($de, $para, $texto);

        $texto = preg_replace('/[^a-z0-9\s]/', ' ', $texto);

        return trim(preg_replace('/\s+/', ' ', $texto));
    }

    // =================================================================
    // Caminho unresolved — a entrega central da Onda 0
    // =================================================================

    private function naoResolvida(array $contexto)
    {
        return array(
            'message' => 'Ainda não consigo responder isso. Por enquanto eu ajudo com o seu progresso, '
                . 'onde você parou, qual é o próximo passo e a situação do seu certificado. '
                . 'Quer ver algum desses?',
            'intent' => null,
            'resolved_by' => 'unresolved',
            'avatar_state' => 'doubt',
            'sources' => array(),
            'actions' => $this->acoesPadrao($contexto),
        );
    }

    /**
     * Registra a pergunta não resolvida com contexto acadêmico e SEM PII.
     * Sem nome, e-mail, CPF ou o texto da pergunta — o texto fica no banco, que
     * é auditável; o log carrega só o que permite agregar por curso e aula.
     */
    private function registrarNaoResolvida($usuarioId, array $contexto, $mensagem)
    {
        Logger::info('norminha.unresolved', array(
            'usuario_id' => (int) $usuarioId,
            'inscricao_id' => $contexto['inscricao_id'],
            'curso_evento_id' => $contexto['curso_evento_id'],
            'modulo_id' => isset($contexto['modulo_atual']['id']) ? $contexto['modulo_atual']['id'] : null,
            'item_id' => isset($contexto['item_atual']['id']) ? $contexto['item_atual']['id'] : null,
            'contexto' => $contexto['contexto'],
            'rota' => $contexto['rota'],
            'em_avaliacao' => (bool) $contexto['assessment_context']['em_avaliacao'],
            'tamanho_mensagem' => is_string($mensagem) ? mb_strlen($mensagem, 'UTF-8') : 0,
        ));
    }

    // =================================================================
    // Ações: mapa de chaves, nunca URL livre
    // =================================================================

    private function acoesPadrao(array $contexto)
    {
        return array(
            $this->acao('view_progress', $contexto),
            $this->acao('continue_course', $contexto),
        );
    }

    /**
     * Monta a ação a partir da chave. Devolve null se a URL final não pertencer
     * à área do aluno V2 — nem por engano do próprio servidor.
     */
    private function acao($chave, array $contexto, array $extra = array())
    {
        $base = 'inscricao_id=' . (int) $contexto['inscricao_id']
            . '&curso_id=' . (int) $contexto['curso_evento_id']
            . '&turma_id=' . (int) $contexto['turma_id'];

        $item = isset($extra['item']) ? $extra['item'] : null;
        $modulo = isset($extra['modulo']) ? $extra['modulo'] : null;

        switch ($chave) {
            case 'continue_course':
            case 'open_item':
                if ($item) {
                    $rota = $this->rotaDoTipo($item['tipo']);
                    $url = $rota . '?' . $base
                        . '&modulo_id=' . (int) ($modulo['id'] ?? 0)
                        . '&conteudo_id=' . (int) $item['id'];
                    $label = $chave === 'continue_course' ? 'Continuar estudando' : 'Abrir esta aula';
                } else {
                    $url = '/v2/aula/?' . $base;
                    $label = 'Ir para o curso';
                }
                break;

            case 'open_module':
                $url = '/v2/aula/?' . $base . '&modulo_id=' . (int) ($modulo['id'] ?? 0);
                $label = 'Abrir o módulo';
                break;

            case 'view_progress':
                $url = '/v2/aluno/';
                $label = 'Ver meus cursos';
                break;

            case 'view_certificate':
                $url = '/v2/aluno/?aba=certificados';
                $label = 'Ver meus certificados';
                break;

            default:
                return null;
        }

        if (!$this->urlPermitida($url)) {
            Logger::warning('norminha.acao.url_rejeitada', array('chave' => $chave));
            return null;
        }

        return array('type' => 'link', 'key' => $chave, 'label' => $label, 'url' => $url);
    }

    /** Quiz e avaliação textual têm tela própria na V2. */
    private function rotaDoTipo($tipo)
    {
        if ($tipo === 'quiz') {
            return '/v2/quiz';
        }
        if ($tipo === 'avaliacao_textual') {
            return '/v2/atividade';
        }

        return '/v2/aula/';
    }

    /**
     * Última barreira antes de um link chegar ao browser: precisa ser caminho
     * interno, sem esquema, e começar por um prefixo da área do aluno V2.
     */
    private function urlPermitida($url)
    {
        $url = (string) $url;

        if ($url === '' || $url[0] !== '/' || strpos($url, '//') === 0) {
            return false;
        }
        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $url)) {
            return false;
        }

        $caminho = parse_url($url, PHP_URL_PATH);
        if (!is_string($caminho) || $caminho === '') {
            return false;
        }

        foreach (self::PREFIXOS_PERMITIDOS as $prefixo) {
            if (strpos($caminho, $prefixo) === 0) {
                return true;
            }
        }

        return false;
    }

    // =================================================================
    // Conversa e persistência
    // =================================================================

    /**
     * Reaproveita a conversa já validada, ou cria uma nova.
     * A verificação de posse aconteceu em processar(), antes do contexto.
     */
    private function abrirConversa($usuarioId, $conversaExistente, array $contexto)
    {
        if ($conversaExistente) {
            $this->conversaModel->atualizarContexto((int) $conversaExistente['id'], $usuarioId, $contexto);

            return array('id' => (int) $conversaExistente['id'], 'uuid' => $conversaExistente['uuid']);
        }

        return $this->conversaModel->criar($usuarioId, $contexto);
    }

    private function finalizar(array $conversa, $usuarioId, array $contexto, array $resposta)
    {
        if ($resposta['resolved_by'] === 'unresolved') {
            $this->registrarNaoResolvida($usuarioId, $contexto, null);
        }

        $mensagemId = $this->mensagemModel->inserir(
            $conversa['id'],
            'assistant',
            $resposta['message'],
            array(
                'intencao' => $resposta['intent'],
                'resolved_by' => $resposta['resolved_by'],
            )
        );

        $this->conversaModel->tocarUltimaMensagem($conversa['id']);

        // Conversa longa ganha resumo, para a janela seguinte não perder o fio
        // sem carregar tudo. O resumo guarda ASSUNTO, nunca número.
        if ($this->memoriaService->precisaResumir($conversa['id'], $usuarioId)) {
            $this->memoriaService->atualizarResumo($conversa['id'], $usuarioId);
        }

        return array(
            'ok' => true,
            'conversation_id' => $conversa['uuid'],
            'message_id' => (int) $mensagemId,
            'message' => $resposta['message'],
            'intent' => $resposta['intent'],
            'resolved_by' => $resposta['resolved_by'],
            'avatar_state' => $resposta['avatar_state'],
            'sources' => $resposta['sources'],
            'actions' => array_values(array_filter($resposta['actions'])),
        );
    }

    /**
     * Resposta que não abre conversa: estados em que ainda não há contexto
     * acadêmico (sem matrícula, ambiguidade). Persistir aqui criaria conversas
     * órfãs sem inscrição.
     */
    private function respostaSimples($usuarioId, array $payload, array $contexto, $texto, $intent, $estado, array $acoes)
    {
        $retorno = array(
            'ok' => true,
            'conversation_id' => isset($payload['conversation_id']) ? $payload['conversation_id'] : null,
            'message_id' => null,
            'message' => $texto,
            'intent' => $intent,
            'resolved_by' => 'php',
            'avatar_state' => $estado,
            'sources' => array(),
            'actions' => array_values(array_filter($acoes)),
        );

        if ($contexto['estado'] === 'ambiguo') {
            $retorno['opcoes'] = $contexto['opcoes'];
        }

        return $retorno;
    }

    // =================================================================
    // Auxiliares
    // =================================================================

    private function validarPayload(array $payload)
    {
        $acao = isset($payload['action']) && $payload['action'] !== null
            ? trim((string) $payload['action']) : null;
        $mensagem = isset($payload['message']) && $payload['message'] !== null
            ? trim((string) $payload['message']) : null;

        if ($acao !== null && $acao !== '') {
            if (!in_array($acao, self::ACOES, true)) {
                return $this->falha('acao_invalida', 'Ação não reconhecida.', 422);
            }
            // `action` tem precedência e nunca é classificada.
            return array('ok' => true, 'acao' => $acao, 'mensagem' => null);
        }

        if ($mensagem === null || $mensagem === '') {
            return $this->falha('mensagem_vazia', 'Escreva sua dúvida para eu poder ajudar.', 422);
        }

        if (mb_strlen($mensagem, 'UTF-8') > self::LIMITE_MENSAGEM) {
            return $this->falha(
                'mensagem_longa',
                'Sua mensagem passou de ' . self::LIMITE_MENSAGEM . ' caracteres. Pode resumir?',
                422
            );
        }

        return array('ok' => true, 'acao' => null, 'mensagem' => $mensagem);
    }

    private function hints(array $payload)
    {
        $contexto = isset($payload['context']) && is_array($payload['context']) ? $payload['context'] : array();

        $hints = array();
        foreach (array('inscricao_id', 'curso_id', 'turma_id', 'modulo_id', 'item_id') as $chave) {
            if (isset($contexto[$chave])) {
                $hints[$chave] = $contexto[$chave];
            }
        }
        if (isset($contexto['route'])) {
            $hints['rota'] = $contexto['route'];
        }

        return $hints;
    }

    private function textoDesambiguacao(array $contexto)
    {
        $titulos = array();
        foreach (array_slice($contexto['opcoes'], 0, 5) as $opcao) {
            $titulos[] = $opcao['curso_titulo'];
        }

        $texto = 'Você tem mais de um curso ativo. Sobre qual deles quer falar?';
        if ($titulos) {
            $texto .= "\n" . '— ' . implode("\n" . '— ', $titulos);
        }
        if (count($contexto['opcoes']) > 5) {
            $texto .= "\n" . '(e outros ' . (count($contexto['opcoes']) - 5) . ')';
        }

        return $texto;
    }

    /** Falha de ferramenta: informa o limite, não inventa dado. */
    private function falhaDeFerramenta(array $contexto)
    {
        return array(
            'message' => 'Não consegui consultar essa informação agora. Você pode ver direto na área do curso.',
            'intent' => null,
            'resolved_by' => 'php',
            'avatar_state' => 'attention',
            'sources' => array(),
            'actions' => $this->acoesPadrao($contexto),
        );
    }

    /**
     * Estrutura do conteúdo oficial do item: títulos e itens de lista.
     * Extração, não geração. O corpo completo e o filtro de gabarito são do
     * NorminhaKnowledgeService, na Onda 1.
     */
    private function estruturaDoItem($itemId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT t.conteudo AS texto, h.conteudo AS html
             FROM conteudo_itens i
             LEFT JOIN conteudo_textos t ON t.item_id = i.id
             LEFT JOIN conteudo_htmls  h ON h.item_id = i.id
             WHERE i.id = :id AND i.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(array('id' => (int) $itemId));
        $linha = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$linha) {
            return array('topicos' => array(), 'fontes' => array());
        }

        $bruto = (string) ($linha['html'] !== null ? $linha['html'] : ($linha['texto'] ?? ''));
        if (trim($bruto) === '') {
            return array('topicos' => array(), 'fontes' => array());
        }

        $topicos = array();
        if (preg_match_all('#<(h[1-4]|li)[^>]*>(.*?)</\1>#is', $bruto, $achados)) {
            foreach ($achados[2] as $trecho) {
                $limpo = trim(preg_replace('/\s+/u', ' ', strip_tags($trecho)));
                if ($limpo !== '' && mb_strlen($limpo, 'UTF-8') <= 160) {
                    $topicos[] = $limpo;
                }
                if (count($topicos) >= 12) {
                    break;
                }
            }
        }

        return array(
            'topicos' => array_values(array_unique($topicos)),
            'fontes' => $topicos ? array(array('tipo' => 'conteudo_oficial', 'item_id' => (int) $itemId)) : array(),
        );
    }

    private function falha($codigo, $mensagem, $status)
    {
        return array('ok' => false, 'erro' => $codigo, 'mensagem' => $mensagem, 'status' => $status);
    }
}
