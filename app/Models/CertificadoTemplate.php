<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class CertificadoTemplate
{
    public function findById($id)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM certificados_templates
             WHERE id = :id
               AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(array('id' => $id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findBySlug($slug)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM certificados_templates
             WHERE slug = :slug
               AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(array('slug' => (string) $slug));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function defaultTemplate()
    {
        $stmt = Database::connection()->query(
            'SELECT *
             FROM certificados_templates
             WHERE deleted_at IS NULL
               AND ativo = 1
             ORDER BY padrao DESC, id ASC
             LIMIT 1'
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function listAdmin()
    {
        $stmt = Database::connection()->query(
            'SELECT *
             FROM certificados_templates
             WHERE deleted_at IS NULL
             ORDER BY padrao DESC, ativo DESC, nome ASC'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function allActive()
    {
        $stmt = Database::connection()->query(
            'SELECT *
             FROM certificados_templates
             WHERE deleted_at IS NULL
               AND ativo = 1
             ORDER BY padrao DESC, nome ASC'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findForTurma($turmaId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM certificados_templates
             WHERE deleted_at IS NULL
               AND ativo = 1
               AND turma_id = :turma_id
             ORDER BY padrao DESC, id ASC
             LIMIT 1'
        );
        $stmt->execute(array('turma_id' => (int) $turmaId));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findForCurso($cursoId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM certificados_templates
             WHERE deleted_at IS NULL
               AND ativo = 1
               AND curso_id = :curso_id
               AND (turma_id IS NULL OR turma_id = 0)
             ORDER BY padrao DESC, id ASC
             LIMIT 1'
        );
        $stmt->execute(array('curso_id' => (int) $cursoId));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findGlobalActive()
    {
        $stmt = Database::connection()->query(
            "SELECT *
             FROM certificados_templates
             WHERE deleted_at IS NULL
               AND ativo = 1
               AND (contexto IS NULL OR contexto = '' OR contexto = 'global')
               AND (curso_id IS NULL OR curso_id = 0)
               AND (turma_id IS NULL OR turma_id = 0)
             ORDER BY padrao DESC, id ASC
             LIMIT 1"
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $data)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO certificados_templates
             (nome, slug, descricao, status, contexto, curso_id, turma_id, corpo_html, css, imagem_fundo, logo, observacoes, cor_fundo, cor_texto, ativo, padrao, orientacao, tamanho_papel, margem_top, margem_bottom, margem_left, margem_right, criado_por, atualizado_por, created_at, updated_at, deleted_at)
             VALUES
             (:nome, :slug, :descricao, :status, :contexto, :curso_id, :turma_id, :corpo_html, :css, :imagem_fundo, :logo, :observacoes, :cor_fundo, :cor_texto, :ativo, :padrao, :orientacao, :tamanho_papel, :margem_top, :margem_bottom, :margem_left, :margem_right, :criado_por, :atualizado_por, NOW(), NOW(), NULL)'
        );
        $stmt->execute($data);
        return (int) Database::connection()->lastInsertId();
    }

    public function update($id, array $data)
    {
        $data['id'] = (int) $id;
        $stmt = Database::connection()->prepare(
            'UPDATE certificados_templates
             SET nome = :nome,
                 slug = :slug,
                 descricao = :descricao,
                 status = :status,
                 contexto = :contexto,
                 curso_id = :curso_id,
                 turma_id = :turma_id,
                 corpo_html = :corpo_html,
                 css = :css,
                 imagem_fundo = :imagem_fundo,
                 logo = :logo,
                 observacoes = :observacoes,
                 cor_fundo = :cor_fundo,
                 cor_texto = :cor_texto,
                 ativo = :ativo,
                 padrao = :padrao,
                 orientacao = :orientacao,
                 tamanho_papel = :tamanho_papel,
                 margem_top = :margem_top,
                 margem_bottom = :margem_bottom,
                 margem_left = :margem_left,
                 margem_right = :margem_right,
                 atualizado_por = :atualizado_por,
                 updated_at = NOW()
             WHERE id = :id
               AND deleted_at IS NULL'
        );
        $stmt->execute($data);
        return (int) $id;
    }

    public function softDelete($id, $usuarioId = null)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE certificados_templates
             SET deleted_at = NOW(),
                 atualizado_por = :usuario_id,
                 updated_at = NOW()
             WHERE id = :id
               AND deleted_at IS NULL'
        );
        $stmt->execute(array('id' => (int) $id, 'usuario_id' => $usuarioId ? (int) $usuarioId : null));
        return (int) $id;
    }

    public function unsetDefaultGlobal($exceptId)
    {
        $stmt = Database::connection()->prepare(
            "UPDATE certificados_templates
             SET padrao = 0,
                 updated_at = NOW()
             WHERE deleted_at IS NULL
               AND id <> :id
               AND ativo = 1
               AND (contexto IS NULL OR contexto = '' OR contexto = 'global')
               AND (curso_id IS NULL OR curso_id = 0)
               AND (turma_id IS NULL OR turma_id = 0)
               AND padrao = 1"
        );
        $stmt->execute(array('id' => (int) $exceptId));
    }
}
