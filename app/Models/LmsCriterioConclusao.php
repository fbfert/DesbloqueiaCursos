<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class LmsCriterioConclusao
{
    public function findAtivo($cursoId, $turmaId = null)
    {
        if (!$this->tabelaExiste()) {
            return null;
        }

        $sql = 'SELECT *
                FROM lms_criterios_conclusao
                WHERE curso_id = :curso_id
                  AND ativo = 1
                  AND deleted_at IS NULL';
        $params = array('curso_id' => (int) $cursoId);

        if ($turmaId !== null) {
            $sql .= ' AND turma_id = :turma_id';
            $params['turma_id'] = (int) $turmaId;
        } else {
            $sql .= ' AND turma_id IS NULL';
        }

        $sql .= ' ORDER BY id DESC LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function desativarContexto($cursoId, $turmaId = null)
    {
        if (!$this->tabelaExiste()) {
            return;
        }

        $sql = 'UPDATE lms_criterios_conclusao
                SET ativo = 0,
                    deleted_at = NOW(),
                    updated_at = NOW()
                WHERE curso_id = :curso_id
                  AND ativo = 1
                  AND deleted_at IS NULL';
        $params = array('curso_id' => (int) $cursoId);

        if ($turmaId !== null) {
            $sql .= ' AND turma_id = :turma_id';
            $params['turma_id'] = (int) $turmaId;
        } else {
            $sql .= ' AND turma_id IS NULL';
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
    }

    public function create(array $data)
    {
        if (!$this->tabelaExiste()) {
            throw new \RuntimeException('Tabela lms_criterios_conclusao não encontrada.');
        }

        $stmt = Database::connection()->prepare(
            'INSERT INTO lms_criterios_conclusao
             (curso_id, turma_id, exigir_progresso, progresso_minimo, exigir_atividades, criterio_atividades,
              nota_minima_atividades, exigir_presenca, presenca_minima, exigir_avaliacao, nota_minima_avaliacao,
              ativo, criado_por, atualizado_por, created_at, updated_at, deleted_at)
             VALUES
             (:curso_id, :turma_id, :exigir_progresso, :progresso_minimo, :exigir_atividades, :criterio_atividades,
              :nota_minima_atividades, :exigir_presenca, :presenca_minima, :exigir_avaliacao, :nota_minima_avaliacao,
              1, :criado_por, :atualizado_por, NOW(), NOW(), NULL)'
        );

        $stmt->execute(array(
            'curso_id' => (int) $data['curso_id'],
            'turma_id' => $data['turma_id'] !== null ? (int) $data['turma_id'] : null,
            'exigir_progresso' => !empty($data['exigir_progresso']) ? 1 : 0,
            'progresso_minimo' => (float) $data['progresso_minimo'],
            'exigir_atividades' => !empty($data['exigir_atividades']) ? 1 : 0,
            'criterio_atividades' => (string) $data['criterio_atividades'],
            'nota_minima_atividades' => $data['nota_minima_atividades'] !== null ? (float) $data['nota_minima_atividades'] : null,
            'exigir_presenca' => !empty($data['exigir_presenca']) ? 1 : 0,
            'presenca_minima' => (float) $data['presenca_minima'],
            'exigir_avaliacao' => !empty($data['exigir_avaliacao']) ? 1 : 0,
            'nota_minima_avaliacao' => $data['nota_minima_avaliacao'] !== null ? (float) $data['nota_minima_avaliacao'] : null,
            'criado_por' => isset($data['criado_por']) ? (int) $data['criado_por'] : null,
            'atualizado_por' => isset($data['atualizado_por']) ? (int) $data['atualizado_por'] : null,
        ));

        return (int) Database::connection()->lastInsertId();
    }

    public function updateAtivo($id, array $data)
    {
        if (!$this->tabelaExiste()) {
            throw new \RuntimeException('Tabela lms_criterios_conclusao não encontrada.');
        }

        $stmt = Database::connection()->prepare(
            'UPDATE lms_criterios_conclusao
             SET exigir_progresso = :exigir_progresso,
                 progresso_minimo = :progresso_minimo,
                 exigir_atividades = :exigir_atividades,
                 criterio_atividades = :criterio_atividades,
                 nota_minima_atividades = :nota_minima_atividades,
                 exigir_presenca = :exigir_presenca,
                 presenca_minima = :presenca_minima,
                 exigir_avaliacao = :exigir_avaliacao,
                 nota_minima_avaliacao = :nota_minima_avaliacao,
                 atualizado_por = :atualizado_por,
                 updated_at = NOW()
             WHERE id = :id
               AND ativo = 1
               AND deleted_at IS NULL'
        );

        $stmt->execute(array(
            'id' => (int) $id,
            'exigir_progresso' => !empty($data['exigir_progresso']) ? 1 : 0,
            'progresso_minimo' => (float) $data['progresso_minimo'],
            'exigir_atividades' => !empty($data['exigir_atividades']) ? 1 : 0,
            'criterio_atividades' => (string) $data['criterio_atividades'],
            'nota_minima_atividades' => $data['nota_minima_atividades'] !== null ? (float) $data['nota_minima_atividades'] : null,
            'exigir_presenca' => !empty($data['exigir_presenca']) ? 1 : 0,
            'presenca_minima' => (float) $data['presenca_minima'],
            'exigir_avaliacao' => !empty($data['exigir_avaliacao']) ? 1 : 0,
            'nota_minima_avaliacao' => $data['nota_minima_avaliacao'] !== null ? (float) $data['nota_minima_avaliacao'] : null,
            'atualizado_por' => isset($data['atualizado_por']) ? (int) $data['atualizado_por'] : null,
        ));

        return $stmt->rowCount() > 0;
    }

    private function tabelaExiste()
    {
        $stmt = Database::connection()->query("SHOW TABLES LIKE 'lms_criterios_conclusao'");
        $row = $stmt->fetch(PDO::FETCH_NUM);
        return !empty($row);
    }
}
