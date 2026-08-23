<?php

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Conteúdo oficial autorizado, para fundamentar a explicação da IA.
 *
 * COMO O GABARITO É EXCLUÍDO
 *
 * Não por filtro de coluna, e muito menos por instrução de prompt: por
 * ESTRUTURA. Este service lê exclusivamente as tabelas de conteúdo narrativo —
 * `conteudo_textos`, `conteudo_htmls` e a descrição curta do item. Ele não tem
 * uma única consulta às tabelas de quiz ou de entregas, onde moram
 * `conteudo_quiz_alternativas.correta`, `conteudo_quiz_perguntas.explicacao`,
 * `conteudo_avaliacoes_entregas.nota/feedback/resposta` e as correções
 * discursivas.
 *
 * Um filtro pode ser esquecido ao adicionar uma coluna. Uma tabela que nunca
 * aparece num FROM não vaza por descuido.
 *
 * Pelo mesmo motivo, itens do tipo `quiz` e `avaliacao_textual` são excluídos
 * INTEIROS — nem o enunciado é devolvido. Entregar a pergunta ao modelo é
 * convidá-lo a respondê-la, que é exatamente o que o guardrail pedagógico
 * existe para impedir.
 *
 * ESCOPO
 * Recebe o contexto JÁ VALIDADO pelo NorminhaContextService. Toda consulta é
 * amarrada ao `curso_evento_id` daquele contexto: não existe caminho para
 * conteúdo de curso arbitrário.
 *
 * TETO
 * Evidência tem limite de trechos e de caracteres. Sem teto, uma aula longa
 * sozinha estoura o custo da mensagem e afoga a pergunta do aluno.
 *
 * O MATERIAL É DADO, NÃO INSTRUÇÃO
 * Este service devolve estrutura marcada como conteúdo. Quem monta o prompt
 * (NorminhaPromptService) é responsável por dizer isso ao modelo. Não se tenta
 * "sanitizar prompt injection" com lista de palavras: a barreira real é
 * arquitetura, prompt de sistema e ferramentas do lado do servidor.
 */
class NorminhaKnowledgeService
{
    /** Tipos cujo conteúdo narrativo pode ser recuperado. */
    const TIPOS_PERMITIDOS = array('texto', 'html', 'video', 'video_incorporado', 'link', 'arquivo');

    /** Tipos que NUNCA entram na evidência, nem o enunciado. */
    const TIPOS_PROIBIDOS = array('quiz', 'avaliacao_textual', 'etiqueta');

    const MAX_TRECHOS = 4;
    const MAX_CHARS_TRECHO = 2500;
    const MAX_CHARS_TOTAL = 8000;
    const MIN_TERMO = 4;

    /**
     * Evidência para a pergunta, na ordem de preferência do plano:
     * item atual → módulo atual → curso.
     *
     * @return array trechos[], fontes[], origem, total_chars
     */
    public function evidenciaPara($usuarioId, array $contexto, $pergunta = '')
    {
        if (empty($contexto['curso_evento_id'])) {
            return $this->vazio('sem_contexto');
        }

        $trechos = array();

        // 1. A aula aberta é a evidência mais provável.
        if (!empty($contexto['item_atual']['id'])) {
            $trechos = $this->trechosDeItens(
                $contexto,
                array((int) $contexto['item_atual']['id']),
                'item_atual'
            );
        }

        // 2. Módulo atual, quando a aula não bastou.
        if (count($trechos) < self::MAX_TRECHOS && !empty($contexto['modulo_atual']['id'])) {
            $trechos = array_merge($trechos, $this->buscar(
                $contexto, $pergunta, (int) $contexto['modulo_atual']['id'], 'modulo_atual', $trechos
            ));
        }

        // 3. Curso inteiro, como último recurso.
        if (count($trechos) < self::MAX_TRECHOS) {
            $trechos = array_merge($trechos, $this->buscar(
                $contexto, $pergunta, null, 'curso', $trechos
            ));
        }

        return $this->montar($trechos);
    }

    /** Só o conteúdo da aula aberta. Usado por explicar/resumir. */
    public function conteudoDoItemAtual($usuarioId, array $contexto)
    {
        if (empty($contexto['curso_evento_id']) || empty($contexto['item_atual']['id'])) {
            return $this->vazio('sem_item');
        }

        return $this->montar($this->trechosDeItens(
            $contexto, array((int) $contexto['item_atual']['id']), 'item_atual'
        ));
    }

    // =================================================================

    /**
     * Busca textual dentro do escopo.
     *
     * LIKE com termos, sem FULLTEXT: adicionar índice FULLTEXT numa tabela viva
     * exige janela e muda o custo de escrita. Se a busca se mostrar insuficiente
     * com dado real, aí sim se avalia — com medida, não por suposição.
     */
    private function buscar(array $contexto, $pergunta, $moduloId, $origem, array $jaTem)
    {
        $termos = $this->termos($pergunta);
        if (!$termos) {
            return array();
        }

        $params = array('curso' => (int) $contexto['curso_evento_id']);
        $where = array(
            'i.curso_evento_id = :curso',
            'i.deleted_at IS NULL',
            'i.status = "publicado"',
            'm.deleted_at IS NULL',
            'm.status = "publicado"',
        );

        $where[] = 'i.tipo IN (' . $this->listaCitada(self::TIPOS_PERMITIDOS) . ')';

        if ($moduloId !== null) {
            $where[] = 'i.modulo_id = :modulo';
            $params['modulo'] = $moduloId;
        }

        $exclui = array();
        foreach ($jaTem as $indice => $t) {
            $chave = 'ex' . $indice;
            $exclui[] = ':' . $chave;
            $params[$chave] = $t['item_id'];
        }
        if ($exclui) {
            $where[] = 'i.id NOT IN (' . implode(', ', $exclui) . ')';
        }

        $ors = array();
        foreach ($termos as $indice => $termo) {
            $chave = 'termo' . $indice;
            $ors[] = "(t.conteudo LIKE :{$chave} OR h.conteudo LIKE :{$chave} OR i.titulo LIKE :{$chave})";
            $params[$chave] = '%' . $termo . '%';
        }
        $where[] = '(' . implode(' OR ', $ors) . ')';

        $sql = 'SELECT i.id, i.titulo, i.tipo, i.descricao_curta,
                       m.id AS modulo_id, m.titulo AS modulo_titulo,
                       t.conteudo AS texto, h.conteudo AS html
                FROM conteudo_itens i
                INNER JOIN conteudo_modulos m ON m.id = i.modulo_id
                LEFT JOIN conteudo_textos t ON t.item_id = i.id
                LEFT JOIN conteudo_htmls  h ON h.item_id = i.id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY m.ordem ASC, i.ordem ASC, i.id ASC
                LIMIT ' . (int) (self::MAX_TRECHOS - count($jaTem));

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $this->normalizar($stmt->fetchAll(PDO::FETCH_ASSOC), $origem);
    }

    /** Itens específicos, sempre amarrados ao curso do contexto. */
    private function trechosDeItens(array $contexto, array $ids, $origem)
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (!$ids) {
            return array();
        }

        $marcadores = array();
        $params = array('curso' => (int) $contexto['curso_evento_id']);
        foreach ($ids as $indice => $id) {
            $chave = 'id' . $indice;
            $marcadores[] = ':' . $chave;
            $params[$chave] = $id;
        }

        $stmt = Database::connection()->prepare(
            'SELECT i.id, i.titulo, i.tipo, i.descricao_curta,
                    m.id AS modulo_id, m.titulo AS modulo_titulo,
                    t.conteudo AS texto, h.conteudo AS html
             FROM conteudo_itens i
             INNER JOIN conteudo_modulos m ON m.id = i.modulo_id
                    AND m.deleted_at IS NULL AND m.status = "publicado"
             LEFT JOIN conteudo_textos t ON t.item_id = i.id
             LEFT JOIN conteudo_htmls  h ON h.item_id = i.id
             WHERE i.id IN (' . implode(', ', $marcadores) . ')
               AND i.curso_evento_id = :curso
               AND i.deleted_at IS NULL
               AND i.status = "publicado"
               AND i.tipo IN (' . $this->listaCitada(self::TIPOS_PERMITIDOS) . ')
             LIMIT ' . self::MAX_TRECHOS
        );
        $stmt->execute($params);

        return $this->normalizar($stmt->fetchAll(PDO::FETCH_ASSOC), $origem);
    }

    private function normalizar(array $linhas, $origem)
    {
        $trechos = array();

        foreach ($linhas as $l) {
            // Cinto e suspensório: o tipo já foi filtrado no SQL, mas se alguém
            // acrescentar um tipo novo à lista permitida sem pensar, esta guarda
            // ainda barra os que valem nota.
            if (in_array((string) $l['tipo'], self::TIPOS_PROIBIDOS, true)) {
                continue;
            }

            $bruto = (string) ($l['html'] !== null && trim((string) $l['html']) !== '' ? $l['html'] : ($l['texto'] ?? ''));
            $limpo = $this->paraTextoLimpo($bruto);

            if ($limpo === '' && trim((string) $l['descricao_curta']) !== '') {
                $limpo = $this->paraTextoLimpo((string) $l['descricao_curta']);
            }
            if ($limpo === '') {
                continue;
            }

            $trechos[] = array(
                'item_id' => (int) $l['id'],
                'titulo' => (string) $l['titulo'],
                'tipo' => (string) $l['tipo'],
                'modulo_id' => (int) $l['modulo_id'],
                'modulo_titulo' => (string) $l['modulo_titulo'],
                'origem' => $origem,
                'texto' => $this->limitar($limpo, self::MAX_CHARS_TRECHO),
            );
        }

        return $trechos;
    }

    /**
     * HTML vira texto legível, preservando a estrutura que ajuda a entender:
     * títulos viram linhas próprias, itens de lista ganham marcador.
     * Jogar HTML cru no prompt gastaria tokens com marcação e atrapalharia.
     */
    private function paraTextoLimpo($bruto)
    {
        $bruto = (string) $bruto;
        if (trim($bruto) === '') {
            return '';
        }

        // Script e style saem com conteúdo; strip_tags sozinho deixaria o corpo.
        $bruto = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', ' ', $bruto);
        $bruto = preg_replace('#<(h[1-6])\b[^>]*>#i', "\n", $bruto);
        $bruto = preg_replace('#</(h[1-6]|p|div|tr)>#i', "\n", $bruto);
        $bruto = preg_replace('#<li\b[^>]*>#i', "\n— ", $bruto);
        $bruto = preg_replace('#<br\s*/?>#i', "\n", $bruto);

        $texto = strip_tags($bruto);
        $texto = html_entity_decode($texto, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $texto = str_replace("\xC2\xA0", ' ', $texto);
        $texto = preg_replace('/[ \t]+/u', ' ', $texto);
        $texto = preg_replace('/\n{3,}/u', "\n\n", $texto);

        return trim($texto);
    }

    /** Termos úteis da pergunta: sem palavras curtas nem vazias de significado. */
    private function termos($pergunta)
    {
        $pergunta = mb_strtolower(trim((string) $pergunta), 'UTF-8');
        if ($pergunta === '') {
            return array();
        }

        $vazias = array('para','pelo','pela','como','onde','quando','porque','porquê','qual','quais',
                        'esse','essa','isso','este','esta','isto','aquele','aquela','sobre','entre',
                        'meu','minha','seu','sua','uma','uns','umas','dos','das','nos','nas','com',
                        'que','nao','não','sim','ser','tem','fazer','pode','preciso','entendi','aula');

        $bruto = preg_split('/[^\p{L}\p{N}]+/u', $pergunta, -1, PREG_SPLIT_NO_EMPTY);
        $termos = array();

        foreach ((array) $bruto as $t) {
            if (mb_strlen($t, 'UTF-8') < self::MIN_TERMO || in_array($t, $vazias, true)) {
                continue;
            }
            $termos[$t] = true;
            if (count($termos) >= 6) {
                break;
            }
        }

        return array_keys($termos);
    }

    private function montar(array $trechos)
    {
        $finais = array();
        $fontes = array();
        $total = 0;

        foreach ($trechos as $t) {
            if (count($finais) >= self::MAX_TRECHOS) {
                break;
            }
            $tamanho = mb_strlen($t['texto'], 'UTF-8');
            if ($total + $tamanho > self::MAX_CHARS_TOTAL) {
                $sobra = self::MAX_CHARS_TOTAL - $total;
                if ($sobra < 200) {
                    break;
                }
                $t['texto'] = $this->limitar($t['texto'], $sobra);
                $tamanho = mb_strlen($t['texto'], 'UTF-8');
            }

            $finais[] = $t;
            $total += $tamanho;

            // Fonte exibível ao aluno: sem id interno de banco além do item.
            $fontes[] = array(
                'tipo' => 'conteudo_oficial',
                'item_id' => $t['item_id'],
                'label' => $t['modulo_titulo'] . ' › ' . $t['titulo'],
            );
        }

        return array(
            'trechos' => $finais,
            'fontes' => $fontes,
            'origem' => $finais ? $finais[0]['origem'] : 'nenhuma',
            'total_chars' => $total,
            'tem_evidencia' => !empty($finais),
        );
    }

    private function vazio($motivo)
    {
        return array(
            'trechos' => array(),
            'fontes' => array(),
            'origem' => $motivo,
            'total_chars' => 0,
            'tem_evidencia' => false,
        );
    }

    private function limitar($texto, $maximo)
    {
        if (mb_strlen($texto, 'UTF-8') <= $maximo) {
            return $texto;
        }

        return rtrim(mb_substr($texto, 0, $maximo - 1, 'UTF-8')) . '…';
    }

    /** Lista de constantes do próprio código; nunca entrada de usuário. */
    private function listaCitada(array $valores)
    {
        $saida = array();
        foreach ($valores as $v) {
            $saida[] = '"' . preg_replace('/[^a-z_]/', '', (string) $v) . '"';
        }

        return implode(', ', $saida);
    }
}
