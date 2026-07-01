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

        return $row ? $this->normalizarHtmlCertificadoLegado($row) : null;
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

        return $row ? $this->normalizarHtmlCertificadoLegado($row) : null;
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

        return $row ? $this->normalizarHtmlCertificadoLegado($row) : null;
    }

    public function listAdmin()
    {
        $stmt = Database::connection()->query(
            'SELECT *
             FROM certificados_templates
             WHERE deleted_at IS NULL
             ORDER BY padrao DESC, ativo DESC, nome ASC'
        );

        return $this->normalizarListaHtmlCertificadoLegado($stmt->fetchAll(PDO::FETCH_ASSOC));
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

        return $this->normalizarListaHtmlCertificadoLegado($stmt->fetchAll(PDO::FETCH_ASSOC));
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
        return $row ? $this->normalizarHtmlCertificadoLegado($row) : null;
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
        return $row ? $this->normalizarHtmlCertificadoLegado($row) : null;
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
             (nome, slug, descricao, status, contexto, curso_id, turma_id, corpo_html, html_segunda_pagina, css, imagem_fundo, logo, assinatura_url, observacoes, cor_fundo, cor_texto, ativo, padrao, orientacao, tamanho_papel, margem_top, margem_bottom, margem_left, margem_right, criado_por, atualizado_por, created_at, updated_at, deleted_at)
             VALUES
             (:nome, :slug, :descricao, :status, :contexto, :curso_id, :turma_id, :corpo_html, :html_segunda_pagina, :css, :imagem_fundo, :logo, :assinatura_url, :observacoes, :cor_fundo, :cor_texto, :ativo, :padrao, :orientacao, :tamanho_papel, :margem_top, :margem_bottom, :margem_left, :margem_right, :criado_por, :atualizado_por, NOW(), NOW(), NULL)'
        );
        $stmt->execute($data);
        return (int) Database::connection()->lastInsertId();
    }

    public function update($id, array $data)
    {
        $data['id'] = (int) $id;
        if (array_key_exists('criado_por', $data)) {
            unset($data['criado_por']);
        }
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
                 html_segunda_pagina = :html_segunda_pagina,
                 css = :css,
                 imagem_fundo = :imagem_fundo,
                 logo = :logo,
                 assinatura_url = :assinatura_url,
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

    private function normalizarListaHtmlCertificadoLegado(array $rows)
    {
        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                continue;
            }

            $rows[$index] = $this->normalizarHtmlCertificadoLegado($row);
        }

        return $rows;
    }

    private function normalizarHtmlCertificadoLegado(array $row)
    {
        if (!isset($row['corpo_html']) || !is_string($row['corpo_html'])) {
            return $row;
        }

        $html = trim($row['corpo_html']);
        if ($html === '') {
            return $row;
        }

        $temLegacy = stripos($html, 'border:2px solid #0f2742') !== false
            || stripos($html, 'border:2px solid #d8bd72') !== false
            || stripos($html, 'border:1px solid #0f2742') !== false
            || stripos($html, 'border-top:1px solid #111827') !== false
            || stripos($html, 'border-top:1px solid #d8c39b') !== false
            || stripos($html, 'certificado-linha-topo') !== false;

        if (!$temLegacy) {
            return $row;
        }

        $html = preg_replace(
            '/^<table cellpadding="0" cellspacing="0" border="0" style="width:100%; border-collapse:collapse; font-family:Georgia, \'Times New Roman\', serif; color:#111827; background:#ffffff;">\s*<tr>\s*<td style="border:2px solid #0f2742; padding:6px;">\s*<table cellpadding="0" cellspacing="0" border="0" style="width:100%; border-collapse:collapse;">\s*<tr>\s*<td style="border:2px solid #d8bd72; padding:6px;">\s*<table cellpadding="0" cellspacing="0" border="0" style="width:100%; border-collapse:collapse;">\s*<tr>\s*<td style="border:1px solid #0f2742; padding:18px 30px 18px 30px;">\s*/is',
            '<div class="certificado-documento">',
            $html,
            1
        );

        $html = preg_replace(
            '/\s*<\/td>\s*<\/tr>\s*<\/table>\s*<\/td>\s*<\/tr>\s*<\/table>\s*<\/td>\s*<\/tr>\s*<\/table>\s*$/is',
            '</div>',
            $html,
            1
        );

        $html = str_replace(
            '<div style="border-top:1px solid #111827; padding-top:5px; font-size:11.5px; line-height:15px; color:#111827; text-align:center;">',
            '<div style="width:65%; margin:0 auto; border-top:1px solid #111827; padding-top:5px; font-size:11.5px; line-height:15px; color:#111827; text-align:center;">',
            $html
        );

        $row['corpo_html'] = $html;

        return $row;
    }
}
