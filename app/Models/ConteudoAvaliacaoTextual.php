<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class ConteudoAvaliacaoTextual
{
    public function findById($id)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM conteudo_avaliacoes_textuais
             WHERE id = :id
             LIMIT 1'
        );
        $stmt->execute(array('id' => (int) $id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByItemId($itemId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM conteudo_avaliacoes_textuais
             WHERE item_id = :item_id
             LIMIT 1'
        );
        $stmt->execute(array('item_id' => (int) $itemId));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function upsertByItemId($itemId, array $data)
    {
        $existing = $this->findByItemId($itemId);
        if ($existing) {
            $stmt = Database::connection()->prepare(
                'UPDATE conteudo_avaliacoes_textuais
                 SET enunciado = :enunciado,
                     orientacoes = :orientacoes,
                     nota_maxima = :nota_maxima,
                     nota_minima = :nota_minima,
                     peso = :peso,
                     prazo = :prazo,
                     permite_reenvio = :permite_reenvio,
                     reenvio_livre_ate_prazo = :reenvio_livre_ate_prazo,
                     updated_at = NOW()
                 WHERE item_id = :item_id'
            );
            $stmt->execute(array(
                'enunciado' => (string) $data['enunciado'],
                'orientacoes' => isset($data['orientacoes']) ? $data['orientacoes'] : null,
                'nota_maxima' => array_key_exists('nota_maxima', $data) ? $data['nota_maxima'] : null,
                'nota_minima' => array_key_exists('nota_minima', $data) ? $data['nota_minima'] : null,
                'peso' => isset($data['peso']) ? $data['peso'] : 1.00,
                'prazo' => isset($data['prazo']) && $data['prazo'] !== '' ? $data['prazo'] : null,
                'permite_reenvio' => !empty($data['permite_reenvio']) ? 1 : 0,
                'reenvio_livre_ate_prazo' => !empty($data['reenvio_livre_ate_prazo']) ? 1 : 0,
                'item_id' => (int) $itemId,
            ));
            return (int) $existing['id'];
        }

        $stmt = Database::connection()->prepare(
            'INSERT INTO conteudo_avaliacoes_textuais
             (item_id, enunciado, orientacoes, nota_maxima, nota_minima, peso, prazo, permite_reenvio, reenvio_livre_ate_prazo, created_at, updated_at)
             VALUES
             (:item_id, :enunciado, :orientacoes, :nota_maxima, :nota_minima, :peso, :prazo, :permite_reenvio, :reenvio_livre_ate_prazo, NOW(), NOW())'
        );
        $stmt->execute(array(
            'item_id' => (int) $itemId,
            'enunciado' => (string) $data['enunciado'],
            'orientacoes' => isset($data['orientacoes']) ? $data['orientacoes'] : null,
            'nota_maxima' => array_key_exists('nota_maxima', $data) ? $data['nota_maxima'] : null,
            'nota_minima' => array_key_exists('nota_minima', $data) ? $data['nota_minima'] : null,
            'peso' => isset($data['peso']) ? $data['peso'] : 1.00,
            'prazo' => isset($data['prazo']) && $data['prazo'] !== '' ? $data['prazo'] : null,
            'permite_reenvio' => !empty($data['permite_reenvio']) ? 1 : 0,
            'reenvio_livre_ate_prazo' => !empty($data['reenvio_livre_ate_prazo']) ? 1 : 0,
        ));
        return (int) Database::connection()->lastInsertId();
    }
}

