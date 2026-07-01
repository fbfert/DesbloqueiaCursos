<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class TutorConfiguracao
{
    public function allIndexed()
    {
        $stmt = Database::connection()->query(
            'SELECT chave, valor
             FROM tutor_configuracoes'
        );

        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : array();
        $configuracoes = array();

        foreach ($rows as $row) {
            $chave = isset($row['chave']) ? trim((string) $row['chave']) : '';
            if ($chave === '') {
                continue;
            }

            $configuracoes[$chave] = isset($row['valor']) ? $row['valor'] : null;
        }

        return $configuracoes;
    }

    public function find($chave)
    {
        $chave = trim((string) $chave);
        if ($chave === '') {
            return null;
        }

        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM tutor_configuracoes
             WHERE chave = :chave
             LIMIT 1'
        );
        $stmt->execute(array('chave' => $chave));

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function saveMany(array $configuracoes)
    {
        $resultado = array(
            'ok' => true,
            'ids' => array(),
        );

        foreach ($configuracoes as $chave => $valor) {
            $chave = trim((string) $chave);
            if ($chave === '') {
                continue;
            }

            $stmt = Database::connection()->prepare(
                'INSERT INTO tutor_configuracoes
                 (chave, valor, atualizado_em)
                 VALUES
                 (:chave, :valor, NOW())
                 ON DUPLICATE KEY UPDATE
                     valor = VALUES(valor),
                     atualizado_em = NOW()'
            );
            $stmt->execute(array(
                'chave' => $chave,
                'valor' => $valor === null ? null : (string) $valor,
            ));

            $resultado['ids'][$chave] = (int) Database::connection()->lastInsertId();
        }

        return $resultado;
    }
}
