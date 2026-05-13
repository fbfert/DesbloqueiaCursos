<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class Cupom
{
    public function findById($id)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM cupons
             WHERE id = :id
               AND deleted_at IS NULL
             LIMIT 1'
        );

        $stmt->execute(array('id' => $id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findByCodigo($codigo)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM cupons
             WHERE codigo = :codigo
               AND deleted_at IS NULL
             LIMIT 1'
        );

        $stmt->execute(array('codigo' => $codigo));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function allForBackoffice()
    {
        try {
            $stmt = Database::connection()->query(
                'SELECT c.*,
                        COALESCE(cc.total_cursos, 0) AS total_cursos,
                        COALESCE(uu.total_usos, 0) AS total_usos,
                        COALESCE(uu.total_descontos, 0) AS total_descontos
                 FROM cupons c
                 LEFT JOIN (
                     SELECT cupom_id, COUNT(*) AS total_cursos
                     FROM cupom_cursos
                     GROUP BY cupom_id
                 ) cc ON cc.cupom_id = c.id
                 LEFT JOIN (
                     SELECT cupom_id,
                            COUNT(*) AS total_usos,
                            COALESCE(SUM(valor_desconto), 0) AS total_descontos
                     FROM cupons_usos
                     GROUP BY cupom_id
                 ) uu ON uu.cupom_id = c.id
                 WHERE c.deleted_at IS NULL
                 ORDER BY c.id DESC'
            );

            if (!$stmt) {
                return array();
            }

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return is_array($rows) ? $rows : array();
        } catch (\Throwable $throwable) {
            return array();
        }
    }

    public function updateStatus($cupomId, $status)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE cupons
             SET status = :status,
                 updated_at = NOW()
             WHERE id = :id
               AND deleted_at IS NULL'
        );

        $stmt->execute(array(
            'status' => $status,
            'id' => (int) $cupomId,
        ));
    }

    public function create(array $data)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO cupons
             (codigo, nome, descricao, escopo, tipo, desconto_tipo, valor_desconto, quantidade_minima_vagas, limite_total_usos,
              limite_por_usuario, data_inicio, data_fim, status, link_promocional, created_at, updated_at, deleted_at)
             VALUES
             (:codigo, :nome, :descricao, :escopo, :tipo, :desconto_tipo, :valor_desconto, :quantidade_minima_vagas, :limite_total_usos,
              :limite_por_usuario, :data_inicio, :data_fim, :status, :link_promocional, NOW(), NOW(), NULL)'
        );

        $stmt->execute(array(
            'codigo' => $data['codigo'],
            'nome' => $data['nome'],
            'descricao' => isset($data['descricao']) ? $data['descricao'] : null,
            'escopo' => isset($data['escopo']) ? $data['escopo'] : 'todo_site',
            'tipo' => isset($data['tipo']) ? $data['tipo'] : 'publico',
            'desconto_tipo' => isset($data['desconto_tipo']) ? $data['desconto_tipo'] : 'percentual',
            'valor_desconto' => isset($data['valor_desconto']) ? $data['valor_desconto'] : 0,
            'quantidade_minima_vagas' => isset($data['quantidade_minima_vagas']) ? $data['quantidade_minima_vagas'] : null,
            'limite_total_usos' => isset($data['limite_total_usos']) ? $data['limite_total_usos'] : null,
            'limite_por_usuario' => isset($data['limite_por_usuario']) ? $data['limite_por_usuario'] : null,
            'data_inicio' => isset($data['data_inicio']) ? $data['data_inicio'] : null,
            'data_fim' => isset($data['data_fim']) ? $data['data_fim'] : null,
            'status' => isset($data['status']) ? $data['status'] : 'rascunho',
            'link_promocional' => isset($data['link_promocional']) ? $data['link_promocional'] : null,
        ));

        return (int) Database::connection()->lastInsertId();
    }

    public function update($cupomId, array $data)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE cupons
             SET codigo = :codigo,
                 nome = :nome,
                 descricao = :descricao,
                 escopo = :escopo,
                 tipo = :tipo,
                 desconto_tipo = :desconto_tipo,
                 valor_desconto = :valor_desconto,
                 quantidade_minima_vagas = :quantidade_minima_vagas,
                 limite_total_usos = :limite_total_usos,
                 limite_por_usuario = :limite_por_usuario,
                 data_inicio = :data_inicio,
                 data_fim = :data_fim,
                 status = :status,
                 link_promocional = :link_promocional,
                 updated_at = NOW()
             WHERE id = :id'
        );

        $stmt->execute(array(
            'codigo' => $data['codigo'],
            'nome' => $data['nome'],
            'descricao' => isset($data['descricao']) ? $data['descricao'] : null,
            'escopo' => isset($data['escopo']) ? $data['escopo'] : 'todo_site',
            'tipo' => isset($data['tipo']) ? $data['tipo'] : 'publico',
            'desconto_tipo' => isset($data['desconto_tipo']) ? $data['desconto_tipo'] : 'percentual',
            'valor_desconto' => isset($data['valor_desconto']) ? $data['valor_desconto'] : 0,
            'quantidade_minima_vagas' => isset($data['quantidade_minima_vagas']) ? $data['quantidade_minima_vagas'] : null,
            'limite_total_usos' => isset($data['limite_total_usos']) ? $data['limite_total_usos'] : null,
            'limite_por_usuario' => isset($data['limite_por_usuario']) ? $data['limite_por_usuario'] : null,
            'data_inicio' => isset($data['data_inicio']) ? $data['data_inicio'] : null,
            'data_fim' => isset($data['data_fim']) ? $data['data_fim'] : null,
            'status' => isset($data['status']) ? $data['status'] : 'rascunho',
            'link_promocional' => isset($data['link_promocional']) ? $data['link_promocional'] : null,
            'id' => $cupomId,
        ));
    }

    public function softDelete($cupomId)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE cupons
             SET deleted_at = NOW(),
                 updated_at = NOW()
             WHERE id = :id'
        );

        $stmt->execute(array('id' => $cupomId));
    }

    public function addHistorico($cupomId, $acao, $observacao = null, $metadados = null, $alteradoPorUsuarioId = null)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO cupons_historico
             (cupom_id, acao, observacao, metadados, alterado_por_usuario_id, created_at)
             VALUES
             (:cupom_id, :acao, :observacao, :metadados, :alterado_por_usuario_id, NOW())'
        );

        $stmt->execute(array(
            'cupom_id' => $cupomId,
            'acao' => $acao,
            'observacao' => $observacao,
            'metadados' => $metadados !== null ? json_encode($metadados) : null,
            'alterado_por_usuario_id' => $alteradoPorUsuarioId,
        ));
    }
}
