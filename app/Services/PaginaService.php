<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Models\Pagina;
use Exception;

class PaginaService
{
    private $paginaModel;
    private $auditService;
    private $trashService;

    public function __construct()
    {
        $this->paginaModel = new Pagina();
        $this->auditService = new AuditService();
        $this->trashService = new TrashService();
    }

    public function listAdmin()
    {
        return array(
            'paginas' => $this->paginaModel->allAdmin(),
            'lixeira_paginas' => $this->listarLixeira(),
        );
    }

    public function formData($paginaId = null)
    {
        return array(
            'pagina' => $paginaId ? $this->paginaModel->findById((int) $paginaId) : null,
        );
    }

    public function salvar(array $data, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $id = !empty($data['id']) ? (int) $data['id'] : 0;
        $titulo = trim((string) (isset($data['titulo']) ? $data['titulo'] : ''));
        $slug = $this->slugify(isset($data['slug']) && trim((string) $data['slug']) !== '' ? $data['slug'] : $titulo);
        $rota = $this->normalizarRota(isset($data['rota']) ? $data['rota'] : $slug);
        $resumo = isset($data['resumo']) ? trim((string) $data['resumo']) : null;
        $conteudoHtml = isset($data['conteudo_html']) ? trim((string) $data['conteudo_html']) : null;
        $status = isset($data['status']) && in_array($data['status'], array('rascunho', 'publicada', 'inativa'), true) ? $data['status'] : 'rascunho';
        $ordem = isset($data['ordem']) ? (int) $data['ordem'] : 0;

        $errors = array();

        if ($titulo === '') {
            $errors[] = 'Informe o título da página.';
        }

        if ($slug === '') {
            $errors[] = 'Informe o slug da página.';
        }

        if ($rota === '' || $rota === '/') {
            $errors[] = 'Informe uma rota válida para a página.';
        }

        $rotasBloqueadas = array('/admin', '/login', '/logout', '/cadastro', '/cursos', '/inscricao', '/checkout');
        foreach ($rotasBloqueadas as $rotaBloqueada) {
            if ($rota === $rotaBloqueada || strpos($rota, $rotaBloqueada . '/') === 0) {
                $errors[] = 'A rota informada conflita com uma rota interna do sistema.';
                break;
            }
        }

        if ($this->paginaModel->findBySlug($slug, $id > 0 ? $id : null)) {
            $errors[] = 'Já existe uma página com este slug.';
        }

        if ($this->paginaModel->findByRota($rota, $id > 0 ? $id : null)) {
            $errors[] = 'Já existe uma página com esta rota.';
        }

        if ($errors) {
            return array('ok' => false, 'errors' => $errors);
        }

        $payload = array(
            'titulo' => $titulo,
            'slug' => $slug,
            'rota' => $rota,
            'resumo' => $resumo !== '' ? $resumo : null,
            'conteudo_html' => $conteudoHtml !== '' ? $conteudoHtml : null,
            'status' => $status,
            'ordem' => $ordem,
            'publicada_em' => $status === 'publicada' ? date('Y-m-d H:i:s') : null,
        );

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            if ($id > 0) {
                $anterior = $this->paginaModel->findById($id);
                if (!$anterior) {
                    $pdo->rollBack();
                    return array('ok' => false, 'errors' => array('Página não encontrada.'));
                }
                $this->paginaModel->update($payload, $id);
                $acao = 'conteudo.pagina.atualizada';
            } else {
                $anterior = null;
                $id = $this->paginaModel->create($payload);
                $acao = 'conteudo.pagina.criada';
            }

            $this->auditService->record(
                $acao,
                'pagina',
                $id,
                array('anterior' => $anterior, 'novo' => $payload),
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            Logger::info($acao, array('pagina_id' => $id, 'rota' => $rota));
            $pdo->commit();

            return array('ok' => true, 'id' => $id);
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('conteudo.pagina.salvar_falhou', array('message' => $exception->getMessage()));
            throw $exception;
        }
    }

    public function excluir($id, $justificativa, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $pagina = $this->paginaModel->findById((int) $id);
        if (!$pagina) {
            return array('ok' => false, 'message' => 'Página não encontrada.');
        }

        if (trim((string) $justificativa) === '') {
            return array('ok' => false, 'message' => 'Informe a justificativa para excluir a página.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $this->trashService->record('pagina', $id, $justificativa, $pagina, $actorUserId, $ipAddress, $userAgent);
            $this->paginaModel->softDelete($id);

            $this->auditService->record(
                'conteudo.pagina.excluida',
                'pagina',
                $id,
                array('justificativa' => $justificativa),
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            Logger::info('conteudo.pagina.excluida', array('pagina_id' => $id));
            $pdo->commit();

            return array('ok' => true);
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('conteudo.pagina.excluir_falhou', array('message' => $exception->getMessage()));
            throw $exception;
        }
    }

    public function buscarPublicaPorRota($rota)
    {
        return $this->paginaModel->findPublicByRota($this->normalizarRota($rota));
    }

    private function slugify($value)
    {
        $value = trim((string) $value);
        $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/i', '-', $value);
        $value = trim($value, '-');

        return $value;
    }

    private function normalizarRota($value)
    {
        $value = trim((string) $value);
        $value = '/' . trim($value, '/');

        if ($value === '//') {
            return '/';
        }

        return strtolower($value);
    }

    private function listarLixeira()
    {
        $stmt = Database::connection()->prepare(
            'SELECT l.id,
                    l.entidade_id,
                    l.justificativa,
                    l.snapshot_dados,
                    l.created_at,
                    u.nome AS excluido_por_nome
             FROM lixeira l
             LEFT JOIN usuarios u ON u.id = l.excluido_por_usuario_id
             WHERE l.entidade_tipo = "pagina"
               AND l.restaurado_em IS NULL
             ORDER BY l.id DESC'
        );
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $row['snapshot_titulo'] = '';
            if (!empty($row['snapshot_dados'])) {
                $snapshot = json_decode((string) $row['snapshot_dados'], true);
                if (is_array($snapshot) && !empty($snapshot['titulo'])) {
                    $row['snapshot_titulo'] = (string) $snapshot['titulo'];
                }
            }
        }
        unset($row);

        return $rows;
    }
}
