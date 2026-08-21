<?php

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Escopo academico do revisor (spec 0002-perfil-revisor).
 *
 * Mesmo principio do escopo de professor: o usuario so alcanca aquilo que lhe
 * foi atribuido. A diferenca e a fonte do vinculo — aqui e
 * curso_pessoas_vinculadas com tipo_pessoa = 'revisor' e status = 'ativo'.
 *
 * O escopo e por CURSO, e nao por turma: o revisor revisa conteudo, e conteudo
 * pertence ao curso. Turma nao altera conteudo.
 *
 * Este Service nao grava nada. Ele so responde "pode ou nao pode".
 */
class RevisorAcademicScopeService
{
    const TIPO_VINCULO = 'revisor';

    /** Alvos que um comentario de revisao pode ter. */
    const ALVOS = array('conteudo_item', 'conteudo_modulo', 'quiz_pergunta', 'quiz_alternativa');

    /**
     * Cursos em que o usuario tem vinculo ativo de revisor.
     */
    public function cursosDoRevisor($usuarioId)
    {
        $usuarioId = (int) $usuarioId;
        if ($usuarioId <= 0) {
            return array();
        }

        $stmt = Database::connection()->prepare(
            'SELECT ce.id, ce.nome, ce.slug, ce.status, ce.tipo, cpv.created_at AS vinculado_em
             FROM curso_pessoas_vinculadas cpv
             INNER JOIN cursos_eventos ce ON ce.id = cpv.curso_evento_id
             WHERE cpv.usuario_id = :usuario_id
               AND cpv.tipo_pessoa = :tipo
               AND cpv.status = :status
               AND cpv.deleted_at IS NULL
               AND ce.deleted_at IS NULL
             ORDER BY ce.nome ASC'
        );
        $stmt->execute(array(
            'usuario_id' => $usuarioId,
            'tipo' => self::TIPO_VINCULO,
            'status' => 'ativo',
        ));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * O usuario pode acessar este curso como revisor?
     */
    public function podeAcessarCurso($usuarioId, $cursoId)
    {
        $usuarioId = (int) $usuarioId;
        $cursoId = (int) $cursoId;
        if ($usuarioId <= 0 || $cursoId <= 0) {
            return false;
        }

        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*)
             FROM curso_pessoas_vinculadas cpv
             INNER JOIN cursos_eventos ce ON ce.id = cpv.curso_evento_id
             WHERE cpv.usuario_id = :usuario_id
               AND cpv.curso_evento_id = :curso_id
               AND cpv.tipo_pessoa = :tipo
               AND cpv.status = :status
               AND cpv.deleted_at IS NULL
               AND ce.deleted_at IS NULL'
        );
        $stmt->execute(array(
            'usuario_id' => $usuarioId,
            'curso_id' => $cursoId,
            'tipo' => self::TIPO_VINCULO,
            'status' => 'ativo',
        ));
        return ((int) $stmt->fetchColumn()) > 0;
    }

    /**
     * Valida o contexto de uma requisicao do revisor, no formato de retorno
     * usado pelos demais Services do projeto.
     */
    public function validarContexto($usuarioId, $cursoId)
    {
        if ((int) $cursoId <= 0) {
            return array('ok' => false, 'message' => 'Curso inválido.');
        }
        if (!$this->podeAcessarCurso($usuarioId, $cursoId)) {
            return array('ok' => false, 'message' => 'Você não está vinculado a este curso como revisor.');
        }
        return array('ok' => true);
    }

    /**
     * O alvo do comentario pertence mesmo ao curso do contexto?
     *
     * Sem isso, um revisor vinculado ao curso A poderia comentar numa questao
     * do curso B apenas informando o id — o alvo e polimorfico e nao tem chave
     * estrangeira que o impeca.
     */
    public function alvoPertenceAoCurso($alvoTipo, $alvoId, $cursoId)
    {
        $alvoId = (int) $alvoId;
        $cursoId = (int) $cursoId;
        if ($alvoId <= 0 || $cursoId <= 0 || !in_array($alvoTipo, self::ALVOS, true)) {
            return false;
        }

        switch ($alvoTipo) {
            case 'conteudo_item':
                $sql = 'SELECT COUNT(*) FROM conteudo_itens
                        WHERE id = :alvo AND curso_evento_id = :curso AND deleted_at IS NULL';
                break;

            case 'conteudo_modulo':
                $sql = 'SELECT COUNT(*) FROM conteudo_modulos
                        WHERE id = :alvo AND curso_evento_id = :curso AND deleted_at IS NULL';
                break;

            case 'quiz_pergunta':
                $sql = 'SELECT COUNT(*)
                        FROM conteudo_quiz_perguntas p
                        INNER JOIN conteudo_quizzes q ON q.id = p.quiz_id AND q.deleted_at IS NULL
                        INNER JOIN conteudo_itens i ON i.id = q.item_id AND i.deleted_at IS NULL
                        WHERE p.id = :alvo AND i.curso_evento_id = :curso AND p.deleted_at IS NULL';
                break;

            case 'quiz_alternativa':
                $sql = 'SELECT COUNT(*)
                        FROM conteudo_quiz_alternativas a
                        INNER JOIN conteudo_quiz_perguntas p ON p.id = a.pergunta_id AND p.deleted_at IS NULL
                        INNER JOIN conteudo_quizzes q ON q.id = p.quiz_id AND q.deleted_at IS NULL
                        INNER JOIN conteudo_itens i ON i.id = q.item_id AND i.deleted_at IS NULL
                        WHERE a.id = :alvo AND i.curso_evento_id = :curso AND a.deleted_at IS NULL';
                break;

            default:
                return false;
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(array('alvo' => $alvoId, 'curso' => $cursoId));
        return ((int) $stmt->fetchColumn()) > 0;
    }
}
