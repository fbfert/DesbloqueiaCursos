<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Models\Categoria;
use Exception;

class CategoriaService
{
    private $categoriaModel;
    private $auditService;
    private $trashService;

    public function __construct()
    {
        $this->categoriaModel = new Categoria();
        $this->auditService = new AuditService();
        $this->trashService = new TrashService();
    }

    public function listAdmin()
    {
        return array(
            'categorias' => $this->categoriaModel->allWithCounts(),
        );
    }

    public function listPublic()
    {
        return array(
            'categorias' => $this->categoriaModel->allPublicWithCounts(),
        );
    }

    public function listPublicHome($limit = 6)
    {
        return $this->categoriaModel->publicHome($limit);
    }

    public function findPublicBySlug($slug)
    {
        return $this->categoriaModel->findPublicBySlug($slug);
    }

    public function formData($categoriaId = null)
    {
        return array(
            'categoria' => $categoriaId ? $this->categoriaModel->findById($categoriaId) : null,
            'categorias' => $this->categoriaModel->allForSelect(),
        );
    }

    public function salvar(array $data, array $files = array(), $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $id = !empty($data['id']) ? (int) $data['id'] : 0;
        $nome = trim((string) (isset($data['nome']) ? $data['nome'] : ''));
        $slug = $this->slugify(isset($data['slug']) && trim((string) $data['slug']) !== '' ? $data['slug'] : $nome);
        $descricao = isset($data['descricao']) ? trim((string) $data['descricao']) : null;
        $thumbnail = $this->normalizarThumbnailEntrada(isset($data['thumbnail']) ? $data['thumbnail'] : null);
        $parentId = isset($data['parent_id']) && $data['parent_id'] !== '' ? (int) $data['parent_id'] : null;
        $ordem = isset($data['ordem']) ? (int) $data['ordem'] : 0;
        $status = isset($data['status']) && in_array($data['status'], array('ativo', 'inativo'), true) ? $data['status'] : 'ativo';

        $errors = array();
        if ($nome === '') {
            $errors[] = 'Nome da categoria e obrigatorio.';
        }

        if ($slug === '') {
            $errors[] = 'Slug da categoria e obrigatorio.';
        }

        $existente = $this->categoriaModel->findBySlug($slug);
        if ($existente && (int) $existente['id'] !== $id) {
            $errors[] = 'Ja existe uma categoria com este slug.';
        }

        if ($parentId && $parentId === $id) {
            $errors[] = 'Categoria pai nao pode ser a propria categoria.';
        }

        if (isset($files['thumbnail_upload']) && !empty($files['thumbnail_upload']['tmp_name'])) {
            $resultadoUpload = $this->salvarThumbnailUpload($files['thumbnail_upload']);
            if (empty($resultadoUpload['ok'])) {
                $errors[] = isset($resultadoUpload['message']) ? $resultadoUpload['message'] : 'Nao foi possivel salvar a thumbnail da categoria.';
            } else {
                $thumbnail = $resultadoUpload['path'];
            }
        }

        if ($errors) {
            return array('ok' => false, 'errors' => $errors);
        }

        $payload = array(
            'nome' => $nome,
            'slug' => $slug,
            'descricao' => $descricao,
            'thumbnail' => $thumbnail,
            'parent_id' => $parentId,
            'ordem' => $ordem,
            'status' => $status,
        );

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            if ($id > 0) {
                $anterior = $this->categoriaModel->findById($id);
                $this->categoriaModel->update($payload, $id);
                $acao = 'catalogo.categoria.atualizada';
            } else {
                $anterior = null;
                $id = $this->categoriaModel->create($payload);
                $acao = 'catalogo.categoria.criada';
            }

            $this->auditService->record(
                $acao,
                'categoria',
                $id,
                array('anterior' => $anterior, 'novo' => $payload),
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            Logger::info($acao, array('categoria_id' => $id, 'slug' => $slug));
            $pdo->commit();

            return array('ok' => true, 'id' => $id);
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('catalogo.categoria.falhou', array('message' => $exception->getMessage()));
            throw $exception;
        }
    }

    public function duplicar(array $data, array $files = array(), $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $id = !empty($data['id']) ? (int) $data['id'] : 0;
        if ($id <= 0) {
            return array('ok' => false, 'errors' => array('Categoria não encontrada.'));
        }

        $original = $this->categoriaModel->findById($id);
        if (!$original) {
            return array('ok' => false, 'errors' => array('Categoria não encontrada.'));
        }

        $nomeBase = trim((string) (isset($data['nome']) && trim((string) $data['nome']) !== '' ? $data['nome'] : $original['nome']));
        $payload = array_merge($data, array(
            'id' => 0,
            'nome' => $this->nomeDaCopia($nomeBase),
            'slug' => $this->slugDaCopia(isset($data['slug']) && trim((string) $data['slug']) !== '' ? $data['slug'] : $nomeBase),
            'status' => 'inativo',
            'thumbnail' => isset($data['thumbnail']) && trim((string) $data['thumbnail']) !== '' ? $data['thumbnail'] : (isset($original['thumbnail']) ? $original['thumbnail'] : null),
        ));

        return $this->salvar($payload, $files, $actorUserId, $ipAddress, $userAgent);
    }

    public function excluir($id, $justificativa, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $categoria = $this->categoriaModel->findById($id);
        if (!$categoria) {
            return array('ok' => false, 'message' => 'Categoria nao encontrada.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $this->trashService->record('categoria', $id, $justificativa, $categoria, $actorUserId, $ipAddress, $userAgent);
            $this->categoriaModel->softDelete($id);

            $this->auditService->record(
                'catalogo.categoria.excluida',
                'categoria',
                $id,
                array('justificativa' => $justificativa),
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            Logger::info('catalogo.categoria.excluida', array('categoria_id' => $id));
            $pdo->commit();

            return array('ok' => true);
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('catalogo.categoria.excluir_falhou', array('message' => $exception->getMessage()));
            throw $exception;
        }
    }

    private function slugify($value)
    {
        $value = trim((string) $value);
        $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/i', '-', $value);
        $value = trim($value, '-');

        return $value;
    }

    private function nomeDaCopia($nome)
    {
        $nome = trim((string) $nome);
        $nome = preg_replace('/^c[oó]pia de\s+/iu', '', $nome);

        return 'Cópia de ' . $nome;
    }

    private function slugDaCopia($valor)
    {
        $base = $this->slugify($valor);
        $base = preg_replace('/-copia(?:-\d+)?$/', '', $base);
        $base = trim((string) $base, '-');
        if ($base === '') {
            $base = 'categoria';
        }

        $slug = $base . '-copia';
        $sufixo = 2;
        while ($this->categoriaModel->findBySlug($slug)) {
            $slug = $base . '-copia-' . $sufixo;
            $sufixo++;
        }

        return $slug;
    }

    private function normalizarThumbnailEntrada($valor)
    {
        $valor = trim((string) $valor);
        if ($valor === '') {
            return null;
        }

        $valor = str_replace('\\', '/', $valor);
        if (strpos($valor, '/') !== 0 && !preg_match('#^https?://#i', $valor)) {
            $valor = '/' . ltrim($valor, '/');
        }

        return $valor;
    }

    private function salvarThumbnailUpload(array $arquivo)
    {
        $diretorioPublico = '/assets/uploads/categorias';
        $diretorioAbsoluto = BASE_PATH . $diretorioPublico;

        if (!isset($arquivo['error']) || (int) $arquivo['error'] !== UPLOAD_ERR_OK) {
            return array('ok' => false, 'message' => 'Upload de thumbnail inválido.');
        }

        if (empty($arquivo['tmp_name']) || !is_uploaded_file($arquivo['tmp_name'])) {
            return array('ok' => false, 'message' => 'Arquivo de thumbnail inválido.');
        }

        $tamanho = isset($arquivo['size']) ? (int) $arquivo['size'] : 0;
        if ($tamanho <= 0) {
            return array('ok' => false, 'message' => 'O arquivo de thumbnail está vazio.');
        }

        if ($tamanho > 5 * 1024 * 1024) {
            return array('ok' => false, 'message' => 'A thumbnail deve ter no máximo 5 MB.');
        }

        $nomeOriginal = isset($arquivo['name']) ? (string) $arquivo['name'] : '';
        $extensao = strtolower((string) pathinfo($nomeOriginal, PATHINFO_EXTENSION));
        $extensoesPermitidas = array('jpg', 'jpeg', 'png', 'webp', 'gif');
        if (!in_array($extensao, $extensoesPermitidas, true)) {
            return array('ok' => false, 'message' => 'Formato de thumbnail não permitido. Use JPG, PNG, WEBP ou GIF.');
        }

        $mime = $this->detectarMimeType($arquivo['tmp_name']);
        $mimesPermitidos = array('image/jpeg', 'image/png', 'image/webp', 'image/gif');
        if ($mime !== null && !in_array(strtolower((string) $mime), $mimesPermitidos, true)) {
            return array('ok' => false, 'message' => 'Tipo de arquivo de thumbnail não permitido.');
        }

        if (!is_dir($diretorioAbsoluto)) {
            if (!@mkdir($diretorioAbsoluto, 0775, true) && !is_dir($diretorioAbsoluto)) {
                return array('ok' => false, 'message' => 'Não foi possível criar a pasta de thumbnails.');
            }
        }

        try {
            $nomeSeguro = 'categoria-' . date('YmdHis') . '-' . bin2hex(random_bytes(6)) . '.' . $extensao;
        } catch (Exception $exception) {
            $nomeSeguro = 'categoria-' . date('YmdHis') . '-' . mt_rand(100000, 999999) . '.' . $extensao;
        }

        $destino = $diretorioAbsoluto . '/' . $nomeSeguro;
        if (!move_uploaded_file($arquivo['tmp_name'], $destino)) {
            return array('ok' => false, 'message' => 'Não foi possível salvar a thumbnail enviada.');
        }

        return array(
            'ok' => true,
            'path' => $diretorioPublico . '/' . $nomeSeguro,
        );
    }

    private function detectarMimeType($arquivoTmp)
    {
        if (!is_file($arquivoTmp)) {
            return null;
        }

        if (function_exists('finfo_open')) {
            $finfo = @finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = @finfo_file($finfo, $arquivoTmp);
                @finfo_close($finfo);
                if ($mime) {
                    return $mime;
                }
            }
        }

        return null;
    }
}


