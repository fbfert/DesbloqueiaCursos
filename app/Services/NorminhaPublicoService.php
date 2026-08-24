<?php

namespace App\Services;

use App\Support\NorminhaModelos;

/**
 * A Norminha para quem ainda não tem conta.
 *
 * POR QUE ESTE SERVIÇO É SEPARADO
 *
 * O NorminhaService existente responde sobre progresso, retomada e certificado
 * — tudo derivado da matrícula de um aluno identificado pela sessão. Nada disso
 * existe aqui. Um visitante anônimo não tem matrícula, e a resposta certa para
 * "qual é o meu progresso?" nesse contexto é explicar que é preciso entrar.
 *
 * A separação não é organizacional, é de segurança. Este serviço **nunca**
 * consulta `usuarios`, `inscricoes`, `pedidos`, `certificados` nem qualquer
 * tabela `norminha_*` de conversa. Se um dia alguém tentar, o teste de
 * arquitetura reprova. Fosse um `if` dentro do serviço do aluno, bastaria um
 * caminho esquecido para um anônimo cair no ramo autenticado.
 *
 * NADA DO QUE O VISITANTE ESCREVE É GUARDADO
 *
 * Não há persistência de conversa aqui. Quem não tem conta não consentiu com
 * nada, e guardar o que essa pessoa digita criaria um acervo de dado pessoal
 * sem base legal, sem política de retenção e sem titular identificável para
 * exercer direito nenhum. O único registro é um contador por IP **com hash**,
 * para conter abuso — e nem o IP em claro fica gravado.
 *
 * SEM MODELO DE LINGUAGEM, POR ENQUANTO
 *
 * Endpoint público e sem sessão é a superfície mais fácil de abusar que existe:
 * qualquer robô gastaria a cota da OpenAI. As respostas aqui são determinísticas
 * e escritas à mão. Se um dia a IA entrar neste caminho, precisa vir com teto
 * próprio e desafio anti-robô — decisão de produto, não detalhe técnico.
 */
class NorminhaPublicoService
{
    /** Quantos cursos citar antes de mandar para o catálogo. */
    const CURSOS_NA_RESPOSTA = 4;

    private $cursoService;

    public function __construct(CursoService $cursoService = null)
    {
        $this->cursoService = $cursoService ?: new CursoService();
    }

    /**
     * Atalhos que abrem a conversa.
     *
     * São exatamente as três dúvidas que travam o visitante antes de virar
     * aluno: se já tem conta, como criar uma, e qual curso escolher.
     */
    public function atalhos()
    {
        return array(
            array('acao' => 'ja_tenho_conta', 'label' => 'Já tenho cadastro'),
            array('acao' => 'quero_me_cadastrar', 'label' => 'Quero me cadastrar'),
            array('acao' => 'escolher_curso', 'label' => 'Vamos escolher um curso?'),
            array('acao' => 'como_comprar', 'label' => 'Como faço para comprar?'),
        );
    }

    /** Saudação de abertura do chat público. */
    public function saudacao()
    {
        return 'Oi! Eu sou a Norminha. Posso te ajudar a criar sua conta, entrar '
            . 'ou escolher um curso. Por onde começamos?';
    }

    /**
     * Responde a um atalho ou a um texto livre.
     *
     * @param  string|null $acao
     * @param  string|null $mensagem
     * @return array  ok, message, actions, avatar_state, intent
     */
    public function responder($acao, $mensagem)
    {
        $acao = trim((string) $acao);
        $mensagem = trim((string) $mensagem);

        if ($acao === '' && $mensagem === '') {
            return $this->resposta('duvida_generica', $this->saudacao(), array(), 'speaking', true);
        }

        $intencao = $acao !== '' ? $acao : $this->classificar($mensagem);

        switch ($intencao) {
            case 'ja_tenho_conta':
                return $this->jaTenhoConta();
            case 'quero_me_cadastrar':
                return $this->queroMeCadastrar();
            case 'escolher_curso':
                return $this->escolherCurso();
            case 'como_comprar':
                return $this->comoComprar();
            case 'esqueci_senha':
                return $this->esqueciSenha();
            case 'preco':
                return $this->preco();
            case 'certificado':
                return $this->certificado();
            case 'contato':
                return $this->contato();
            default:
                return $this->naoEntendi();
        }
    }

    // -----------------------------------------------------------------
    // Respostas
    // -----------------------------------------------------------------

    private function jaTenhoConta()
    {
        return $this->resposta(
            'ja_tenho_conta',
            'Ótimo! Entre com o e-mail e a senha que você cadastrou. '
            . 'Se não lembrar a senha, dá para criar uma nova em um minuto.',
            array(
                $this->link('Entrar na minha conta', '/v2/login'),
                $this->link('Esqueci minha senha', '/v2/recuperar-senha'),
            ),
            'speaking'
        );
    }

    private function queroMeCadastrar()
    {
        return $this->resposta(
            'quero_me_cadastrar',
            "Vamos lá, leva menos de dois minutos. Você vai precisar de:\n"
            . "— nome completo, do jeito que deve sair no certificado;\n"
            . "— CPF;\n"
            . "— um e-mail que você acessa, porque é por ele que a gente fala com você;\n"
            . "— WhatsApp (opcional);\n"
            . "— e uma senha fácil de lembrar.\n\n"
            . 'No fim é só aceitar os termos. Qualquer coisa, me chame aqui.',
            array(
                $this->link('Criar minha conta', '/v2/cadastro'),
                $this->link('Já tenho conta, quero entrar', '/v2/login'),
            ),
            'explaining'
        );
    }

    private function escolherCurso()
    {
        $cursos = $this->cursosPublicos();

        if (!$cursos) {
            return $this->resposta(
                'escolher_curso',
                'Dê uma olhada no catálogo: lá dá para filtrar por área e ver a carga horária de cada curso.',
                array($this->link('Ver o catálogo', '/v2/catalogo')),
                'speaking'
            );
        }

        $linhas = array();
        foreach ($cursos as $curso) {
            $linhas[] = '— ' . $curso['nome'] . $this->sufixoDoCurso($curso);
        }

        return $this->resposta(
            'escolher_curso',
            "Alguns dos nossos cursos:\n" . implode("\n", $linhas)
            . "\n\nNo catálogo você vê todos, com o conteúdo e a carga horária de cada um.",
            array($this->link('Ver o catálogo completo', '/v2/catalogo')),
            'explaining'
        );
    }

    private function comoComprar()
    {
        return $this->resposta(
            'como_comprar',
            "São quatro passos:\n"
            . "1. escolha o curso no catálogo;\n"
            . "2. crie sua conta (ou entre, se já tiver);\n"
            . "3. confirme os dados da inscrição;\n"
            . "4. faça o pagamento.\n\n"
            . 'O acesso ao conteúdo abre assim que o pagamento é confirmado.',
            array(
                $this->link('Ver o catálogo', '/v2/catalogo'),
                $this->link('Criar minha conta', '/v2/cadastro'),
            ),
            'explaining'
        );
    }

    private function esqueciSenha()
    {
        return $this->resposta(
            'esqueci_senha',
            'Sem problema. Informe o e-mail do cadastro e você recebe um link para criar uma senha nova.',
            array($this->link('Recuperar minha senha', '/v2/recuperar-senha')),
            'speaking'
        );
    }

    private function preco()
    {
        $cursos = $this->cursosPublicos();
        $texto = 'Cada curso tem o seu valor, e ele aparece na página do curso, no catálogo.';

        if ($cursos) {
            $valores = array();
            foreach ($cursos as $curso) {
                $valor = $this->valorDoCurso($curso);
                if ($valor !== null) {
                    $valores[] = $valor;
                }
            }
            if ($valores) {
                $texto .= ' Hoje eles vão de ' . $this->emReais(min($valores))
                    . ' a ' . $this->emReais(max($valores)) . '.';
            }
        }

        return $this->resposta('preco', $texto, array($this->link('Ver preços no catálogo', '/v2/catalogo')), 'speaking');
    }

    private function certificado()
    {
        return $this->resposta(
            'certificado',
            'Os cursos com certificado indicam isso na própria página, junto da carga horária. '
            . 'O certificado fica disponível na sua área de aluno quando você conclui o conteúdo.',
            array($this->link('Ver o catálogo', '/v2/catalogo')),
            'speaking'
        );
    }

    private function contato()
    {
        return $this->resposta(
            'contato',
            'Posso te ajudar por aqui com cadastro, acesso e escolha de curso. '
            . 'Para falar com uma pessoa da equipe, use a página de contato.',
            array($this->link('Falar com a equipe', '/v2/contato')),
            'speaking'
        );
    }

    private function naoEntendi()
    {
        // Aqui os atalhos voltam, porque e o momento em que servem: a Norminha
        // nao entendeu, e mostrar o que ela SABE fazer vale mais que repetir
        // que nao sabe.
        return $this->resposta(
            'duvida_generica',
            'Ainda não sei responder isso. Por aqui eu ajudo com cadastro, acesso à conta e '
            . 'escolha de curso — e posso te levar até quem resolve o resto.',
            array($this->link('Falar com a equipe', '/v2/contato')),
            'doubt',
            true
        );
    }

    // -----------------------------------------------------------------
    // Classificação do texto livre
    // -----------------------------------------------------------------

    /**
     * Palavras-chave, não modelo de linguagem.
     *
     * Cobre o que o visitante de fato pergunta antes de virar aluno. O que não
     * casar cai em naoEntendi(), que oferece os atalhos e o contato — nunca uma
     * resposta inventada.
     */
    private function classificar($mensagem)
    {
        $t = $this->normalizar($mensagem);
        if ($t === '') {
            return 'duvida_generica';
        }

        $mapa = array(
            'esqueci_senha' => array('esqueci a senha', 'esqueci minha senha', 'perdi a senha',
                'recuperar senha', 'nao lembro a senha', 'redefinir senha', 'trocar a senha'),
            'ja_tenho_conta' => array('ja tenho', 'ja sou aluno', 'ja me cadastrei', 'quero entrar',
                'fazer login', 'logar', 'acessar minha conta', 'entrar na conta', 'nao consigo entrar'),
            'quero_me_cadastrar' => array('cadastr', 'criar conta', 'criar uma conta', 'me inscrever',
                'nova conta', 'abrir conta', 'registrar'),
            'como_comprar' => array('comprar', 'pagar', 'pagamento', 'como faco para', 'como funciona',
                'matricula', 'matricular', 'pix', 'cartao', 'boleto'),
            'preco' => array('preco', 'precos', 'valor', 'quanto custa', 'quanto e', 'mensalidade', 'desconto'),
            'certificado' => array('certificado', 'certificacao', 'diploma', 'horas', 'carga horaria'),
            'escolher_curso' => array('curso', 'cursos', 'catalogo', 'quais cursos', 'o que voces tem',
                'area', 'formacao', 'estudar'),
            'contato' => array('falar com', 'atendimento', 'suporte', 'telefone', 'whatsapp', 'humano', 'pessoa'),
        );

        // A ordem importa: "esqueci a senha" contém "senha" e também "entrar";
        // o mais específico é testado primeiro.
        foreach ($mapa as $intencao => $termos) {
            foreach ($termos as $termo) {
                if (strpos($t, $termo) !== false) {
                    return $intencao;
                }
            }
        }

        return 'duvida_generica';
    }

    private function normalizar($texto)
    {
        $texto = trim((string) $texto);
        $texto = function_exists('mb_strtolower') ? mb_strtolower($texto, 'UTF-8') : strtolower($texto);
        $de = array('á','à','â','ã','ä','é','è','ê','ë','í','ì','î','ï','ó','ò','ô','õ','ö','ú','ù','û','ü','ç');
        $para = array('a','a','a','a','a','e','e','e','e','i','i','i','i','o','o','o','o','o','u','u','u','u','c');
        $texto = str_replace($de, $para, $texto);
        $texto = preg_replace('/[^a-z0-9]+/', ' ', $texto);

        return trim(preg_replace('/\s+/', ' ', $texto));
    }

    // -----------------------------------------------------------------
    // Auxiliares
    // -----------------------------------------------------------------

    /** Só o catálogo público, pelo mesmo caminho que a página de catálogo usa. */
    private function cursosPublicos()
    {
        try {
            $r = $this->cursoService->listPublic(array(), 1, self::CURSOS_NA_RESPOSTA);
            $cursos = isset($r['cursos']) && is_array($r['cursos']) ? $r['cursos'] : array();
        } catch (\Throwable $e) {
            // Catálogo fora do ar não pode derrubar o atendimento: o visitante
            // ainda consegue se cadastrar e entrar.
            return array();
        }

        $limpos = array();
        foreach ($cursos as $curso) {
            if (empty($curso['nome'])) {
                continue;
            }
            // Campo a campo, e não o registro inteiro: uma coluna nova no
            // catálogo não vaza para a resposta sem alguém decidir.
            $limpos[] = array(
                'nome' => (string) $curso['nome'],
                'carga_horaria' => isset($curso['carga_horaria']) ? $curso['carga_horaria'] : null,
                'valor' => isset($curso['valor']) ? $curso['valor'] : null,
                'valor_promocional' => isset($curso['valor_promocional']) ? $curso['valor_promocional'] : null,
            );
        }

        return $limpos;
    }

    private function valorDoCurso(array $curso)
    {
        $promocional = isset($curso['valor_promocional']) ? (float) $curso['valor_promocional'] : 0.0;
        $cheio = isset($curso['valor']) ? (float) $curso['valor'] : 0.0;
        $valor = $promocional > 0 ? $promocional : $cheio;

        return $valor > 0 ? $valor : null;
    }

    private function sufixoDoCurso(array $curso)
    {
        $partes = array();
        if (!empty($curso['carga_horaria'])) {
            $partes[] = (int) $curso['carga_horaria'] . 'h';
        }
        $valor = $this->valorDoCurso($curso);
        if ($valor !== null) {
            $partes[] = $this->emReais($valor);
        }

        return $partes ? ' (' . implode(', ', $partes) . ')' : '';
    }

    private function emReais($valor)
    {
        return 'R$ ' . number_format((float) $valor, 2, ',', '.');
    }

    private function acoesIniciais()
    {
        return array(
            $this->link('Criar minha conta', '/v2/cadastro'),
            $this->link('Entrar', '/v2/login'),
            $this->link('Ver cursos', '/v2/catalogo'),
        );
    }

    /** Só caminho interno: nunca um destino que alguém possa apontar para fora. */
    private function link($label, $url)
    {
        return array('label' => (string) $label, 'url' => (string) $url);
    }

    /**
     * @param bool $comSugestoes reoferece os atalhos dentro da bolha
     */
    private function resposta($intent, $mensagem, array $acoes, $estado, $comSugestoes = false)
    {
        $r = array(
            'ok' => true,
            'message' => $mensagem,
            'intent' => $intent,
            'resolved_by' => 'php',
            'avatar_state' => $estado,
            'sources' => array(),
            'actions' => array_values($acoes),
        );

        // Os atalhos NAO voltam em toda resposta. Repetir "ja tenho cadastro /
        // quero me cadastrar / vamos escolher um curso" a cada troca empilha a
        // mesma lista na tela e faz a conversa parecer um menu que nao sai do
        // lugar. Eles voltam quando sao a saida: quando a Norminha nao entendeu
        // o que foi dito, e nao ha para onde apontar.
        if ($comSugestoes) {
            $r['sugestoes'] = $this->atalhos();
        }

        return $r;
    }
}
