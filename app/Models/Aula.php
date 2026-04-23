<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class Aula
{
    public function findById($id)
    {
        $stmt = Database::connection()->prepare('SELECT * FROM aulas WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(array('id' => $id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function listForModulo($moduloId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM aulas
             WHERE modulo_id = :modulo_id
               AND deleted_at IS NULL
             ORDER BY ordem ASC, id ASC'
        );
        $stmt->execute(array('modulo_id' => $moduloId));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO aulas
             (modulo_id, curso_evento_id, turma_id, titulo, conteudo, tipo, url_video, duracao_minutos, visivel, obrigatoria, ordem, created_at, updated_at, deleted_at)
             VALUES
             (:modulo_id, :curso_evento_id, :turma_id, :titulo, :conteudo, :tipo, :url_video, :duracao_minutos, :visivel, :obrigatoria, :ordem, NOW(), NOW(), NULL)'
        );

        $stmt->execute(array(
            'modulo_id' => $data['modulo_id'],
            'curso_evento_id' => $data['curso_evento_id'],
            'turma_id' => isset($data['turma_id']) ? $data['turma_id'] : null,
            'titulo' => $data['titulo'],
            'conteudo' => isset($data['conteudo']) ? $data['conteudo'] : null,
            'tipo' => isset($data['tipo']) ? $data['tipo'] : 'texto',
            'url_video' => isset($data['url_video']) ? $data['url_video'] : null,
            'duracao_minutos' => isset($data['duracao_minutos']) ? (int) $data['duracao_minutos'] : null,
            'visivel' => !empty($data['visivel']) ? 1 : 0,
            'obrigatoria' => !empty($data['obrigatoria']) ? 1 : 0,
            'ordem' => isset($data['ordem']) ? (int) $data['ordem'] : 1,
        ));

        return (int) Database::connection()->lastInsertId();
    }

    public function update(array $data, $id)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE aulas
             SET modulo_id = :modulo_id,
                 curso_evento_id = :curso_evento_id,
                 turma_id = :turma_id,
                 titulo = :titulo,
                 conteudo = :conteudo,
                 tipo = :tipo,
                 url_video = :url_video,
                 duracao_minutos = :duracao_minutos,
                 visivel = :visivel,
                 obrigatoria = :obrigatoria,
                 ordem = :ordem,
                 updated_at = NOW()
             WHERE id = :id'
        );

        $stmt->execute(array(
            'modulo_id' => $data['modulo_id'],
            'curso_evento_id' => $data['curso_evento_id'],
            'turma_id' => isset($data['turma_id']) ? $data['turma_id'] : null,
            'titulo' => $data['titulo'],
            'conteudo' => isset($data['conteudo']) ? $data['conteudo'] : null,
            'tipo' => isset($data['tipo']) ? $data['tipo'] : 'texto',
            'url_video' => isset($data['url_video']) ? $data['url_video'] : null,
            'duracao_minutos' => isset($data['duracao_minutos']) ? (int) $data['duracao_minutos'] : null,
            'visivel' => !empty($data['visivel']) ? 1 : 0,
            'obrigatoria' => !empty($data['obrigatoria']) ? 1 : 0,
            'ordem' => isset($data['ordem']) ? (int) $data['ordem'] : 1,
            'id' => $id,
        ));
    }

    public function softDelete($id)
    {
        $stmt = Database::connection()->prepare('UPDATE aulas SET deleted_at = NOW(), updated_at = NOW() WHERE id = :id');
        $stmt->execute(array('id' => $id));
    }
}
