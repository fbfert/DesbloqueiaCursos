<?php

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Leitura do conteudo para a area do revisor (spec 0002-perfil-revisor).
 *
 * Existe para manter os controllers finos SEM injetar ConteudoCursoService,
 * que e o Service de GRAVACAO de conteudo. A separacao e proposital: nenhum
 * caminho do revisor deve ter, a mao, um objeto capaz de escrever em
 * conteudo_*. Aqui so ha SELECT.
 */
class RevisorLeituraService
{
    public function curso($cursoId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, nome, slug, status, carga_horaria, descricao_curta
             FROM cursos_eventos WHERE id = :id AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute(array('id' => (int) $cursoId));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Modulos do curso com seus itens, na ordem em que o aluno os ve.
     */
    public function modulosComItens($cursoId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, titulo, ordem, status
             FROM conteudo_modulos
             WHERE curso_evento_id = :curso AND deleted_at IS NULL
             ORDER BY ordem ASC, id ASC'
        );
        $stmt->execute(array('curso' => (int) $cursoId));
        $modulos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = Database::connection()->prepare(
            'SELECT i.id, i.modulo_id, i.tipo, i.titulo, i.ordem, i.status,
                    q.id AS quiz_id,
                    (SELECT COUNT(*) FROM conteudo_quiz_perguntas p
                      WHERE p.quiz_id = q.id AND p.deleted_at IS NULL) AS total_questoes,
                    (SELECT LENGTH(h.conteudo) FROM conteudo_htmls h WHERE h.item_id = i.id) AS bytes_html
             FROM conteudo_itens i
             LEFT JOIN conteudo_quizzes q ON q.item_id = i.id AND q.deleted_at IS NULL
             WHERE i.curso_evento_id = :curso AND i.deleted_at IS NULL
             ORDER BY i.ordem ASC, i.id ASC'
        );
        $stmt->execute(array('curso' => (int) $cursoId));

        $porModulo = array();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
            $porModulo[(int) $item['modulo_id']][] = $item;
        }
        foreach ($modulos as $indice => $modulo) {
            $modulos[$indice]['itens'] = isset($porModulo[(int) $modulo['id']])
                ? $porModulo[(int) $modulo['id']] : array();
        }
        return $modulos;
    }

    public function item($itemId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT i.*, m.titulo AS modulo_titulo, m.ordem AS modulo_ordem
             FROM conteudo_itens i
             INNER JOIN conteudo_modulos m ON m.id = i.modulo_id
             WHERE i.id = :id AND i.deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute(array('id' => (int) $itemId));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Corpo de um item de leitura. Cobre html e texto, que sao os tipos que o
     * revisor precisa ler. Demais tipos devolvem null e a view avisa.
     */
    public function corpoDoItem(array $item)
    {
        if ($item['tipo'] === 'html') {
            $stmt = Database::connection()->prepare('SELECT conteudo FROM conteudo_htmls WHERE item_id = :id LIMIT 1');
            $stmt->execute(array('id' => (int) $item['id']));
            $valor = $stmt->fetchColumn();
            return $valor !== false ? $valor : null;
        }
        if ($item['tipo'] === 'texto') {
            $stmt = Database::connection()->prepare('SELECT conteudo FROM conteudo_textos WHERE item_id = :id LIMIT 1');
            $stmt->execute(array('id' => (int) $item['id']));
            $valor = $stmt->fetchColumn();
            return $valor !== false ? $valor : null;
        }
        return null;
    }

    /**
     * Banco de questoes de um item do tipo quiz, com gabarito e explicacao.
     *
     * O gabarito fica a vista de proposito: e exatamente o que o revisor
     * precisa julgar. Ver a secao de riscos da spec.
     */
    public function bancoDoQuiz($itemId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT q.* FROM conteudo_quizzes q
             WHERE q.item_id = :item AND q.deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute(array('item' => (int) $itemId));
        $quiz = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$quiz) {
            return null;
        }

        $stmt = Database::connection()->prepare(
            'SELECT id, codigo, titulo, quantidade_sortear, tipo_questao, ordem
             FROM conteudo_quiz_blocos
             WHERE quiz_id = :quiz AND deleted_at IS NULL ORDER BY ordem ASC, id ASC'
        );
        $stmt->execute(array('quiz' => (int) $quiz['id']));
        $blocos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = Database::connection()->prepare(
            'SELECT id, bloco_id, enunciado, tipo, dificuldade, tema, explicacao, referencia, ordem
             FROM conteudo_quiz_perguntas
             WHERE quiz_id = :quiz AND deleted_at IS NULL
             ORDER BY FIELD(dificuldade, \'facil\', \'media\', \'dificil\'), id ASC'
        );
        $stmt->execute(array('quiz' => (int) $quiz['id']));
        $perguntas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($perguntas) {
            $ids = array_map(function ($p) {
                return (int) $p['id'];
            }, $perguntas);
            $lista = implode(',', $ids);
            $alternativas = Database::connection()->query(
                "SELECT id, pergunta_id, texto, correta, ordem
                 FROM conteudo_quiz_alternativas
                 WHERE pergunta_id IN ({$lista}) AND deleted_at IS NULL
                 ORDER BY ordem ASC, id ASC"
            )->fetchAll(PDO::FETCH_ASSOC);

            $porPergunta = array();
            foreach ($alternativas as $alt) {
                $porPergunta[(int) $alt['pergunta_id']][] = $alt;
            }
            foreach ($perguntas as $indice => $pergunta) {
                $perguntas[$indice]['alternativas'] = isset($porPergunta[(int) $pergunta['id']])
                    ? $porPergunta[(int) $pergunta['id']] : array();
            }
        }

        return array('quiz' => $quiz, 'blocos' => $blocos, 'perguntas' => $perguntas);
    }
}
