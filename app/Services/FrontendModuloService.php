<?php

namespace App\Services;

use App\Core\Database;
use App\Models\FrontendModulo;
use Exception;
use PDO;

class FrontendModuloService
{
    private $model;
    private $auditService;
    private $trashService;
    private $imagemDirectoryPublic;
    private $imagemDirectoryAbsolute;

    public function __construct()
    {
        $this->model = new FrontendModulo();
        $this->auditService = new AuditService();
        $this->trashService = new TrashService();
        $this->imagemDirectoryPublic = '/assets/uploads/modulos';
        $this->imagemDirectoryAbsolute = BASE_PATH . '/public_html' . $this->imagemDirectoryPublic;
    }

    public function listAdmin()
    {
        return array(
            'modulos' => $this->model->allAdmin(),
            'lixeira_modulos' => $this->listarLixeira(),
        );
    }

    public function formData($id = null)
    {
        return array('modulo' => $id ? $this->model->findById((int) $id) : null);
    }

    public function buscarAtivoPorPosicaoOuCodigo($posicao, $codigo = null)
    {
        return $this->model->findActiveByPositionOrCode($posicao, $codigo);
    }

    public function buscarPorCodigo($codigo)
    {
        return $this->model->findByCode((string) $codigo);
    }

    public function listarAtivosPorPosicao($posicao, $limit = null)
    {
        return $this->model->allActiveByPosition((string) $posicao, $limit);
    }

    public function salvar(array $input, array $files = array(), $usuarioId = null, $ipAddress = null, $userAgent = null)
    {
        $id = isset($input['id']) ? (int) $input['id'] : 0;
        $codigo = $this->normalizarSlug(isset($input['codigo']) ? $input['codigo'] : '');
        $registroAtual = $id > 0 ? $this->model->findById($id) : null;
        $imagemAtual = $registroAtual && !empty($registroAtual['imagem_caminho']) ? (string) $registroAtual['imagem_caminho'] : null;
        $imagemCaminho = isset($input['imagem_caminho_atual']) ? $this->normalizarImagemExistente($input['imagem_caminho_atual']) : $imagemAtual;

        if (isset($input['remover_imagem']) && (int) $input['remover_imagem'] === 1) {
            $imagemCaminho = null;
        }

        $payload = array(
            'codigo' => $codigo,
            'nome_admin' => trim((string) (isset($input['nome_admin']) ? $input['nome_admin'] : '')),
            'titulo' => $this->nullableTrim(isset($input['titulo']) ? $input['titulo'] : null),
            'subtitulo' => $this->nullableTrim(isset($input['subtitulo']) ? $input['subtitulo'] : null),
            'conteudo' => $this->nullableTrim(isset($input['conteudo']) ? $input['conteudo'] : null),
            'imagem_caminho' => $imagemCaminho,
            'imagem_alt' => $this->nullableTrim(isset($input['imagem_alt']) ? $input['imagem_alt'] : null),
            'posicao' => $this->normalizarSlug(isset($input['posicao']) ? $input['posicao'] : ''),
            'tipo' => $this->normalizarSlug(isset($input['tipo']) ? $input['tipo'] : 'bloco_texto'),
            'ativo' => isset($input['ativo']) ? 1 : 0,
            'ordem' => isset($input['ordem']) ? (int) $input['ordem'] : 0,
            'permite_html' => isset($input['permite_html']) ? 1 : 0,
            'observacoes_admin' => $this->nullableTrim(isset($input['observacoes_admin']) ? $input['observacoes_admin'] : null),
            'criado_por' => $usuarioId ? (int) $usuarioId : null,
            'atualizado_por' => $usuarioId ? (int) $usuarioId : null,
        );

        $errors = array();

        if (isset($files['imagem_upload']) && !empty($files['imagem_upload']['tmp_name'])) {
            $resultadoUpload = $this->salvarImagemModuloUpload($files['imagem_upload']);
            if (empty($resultadoUpload['ok'])) {
                $errors[] = isset($resultadoUpload['message']) ? $resultadoUpload['message'] : 'Não foi possível enviar a imagem do módulo.';
            } else {
                $payload['imagem_caminho'] = $resultadoUpload['path'];
            }
        }

        if ($payload['nome_admin'] === '') {
            $errors[] = 'Informe o nome administrativo do módulo.';
        }
        if ($payload['codigo'] === '') {
            $errors[] = 'Informe o código do módulo.';
        }
        if ($payload['posicao'] === '') {
            $errors[] = 'Informe a posição do módulo.';
        }
        if ($payload['tipo'] === '') {
            $errors[] = 'Informe o tipo do módulo.';
        }
        if ($payload['permite_html'] === 1) {
            $errors[] = 'HTML livre não está habilitado por segurança.';
            $payload['permite_html'] = 0;
        }
        if ($this->model->findByCode($payload['codigo'], $id > 0 ? $id : null)) {
            $errors[] = 'Já existe um módulo com este código.';
        }
        if ($errors) {
            return array('ok' => false, 'errors' => $errors);
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        if ($id > 0) {
            $anterior = $registroAtual ?: $this->model->findById($id);
            if (!$anterior) {
                $pdo->rollBack();
                return array('ok' => false, 'errors' => array('Módulo não encontrado.'));
            }
            $this->model->update($id, $payload);
            $acao = 'frontend_modulo.atualizado';
        } else {
            $anterior = null;
            $id = $this->model->create($payload);
            $acao = 'frontend_modulo.criado';
        }

        $this->auditService->record($acao, 'frontend_modulo', $id, array('antes' => $anterior, 'depois' => $payload), $usuarioId, $ipAddress, $userAgent);
        $pdo->commit();
        return array('ok' => true, 'id' => $id);
    }

    public function duplicar(array $input, array $files = array(), $usuarioId = null, $ipAddress = null, $userAgent = null)
    {
        $id = isset($input['id']) ? (int) $input['id'] : 0;
        if ($id <= 0) {
            return array('ok' => false, 'errors' => array('Módulo não encontrado.'));
        }

        $modulo = $this->model->findById($id);
        if (!$modulo) {
            return array('ok' => false, 'errors' => array('Módulo não encontrado.'));
        }

        $codigoBase = isset($input['codigo']) && trim((string) $input['codigo']) !== '' ? $input['codigo'] : $modulo['codigo'];
        $posicaoBase = isset($input['posicao']) && trim((string) $input['posicao']) !== '' ? $input['posicao'] : $modulo['posicao'];
        $payload = $input;
        $payload['id'] = 0;
        $payload['codigo'] = $this->codigoDaCopia($codigoBase);
        $payload['nome_admin'] = $this->nomeDaCopia(isset($input['nome_admin']) && trim((string) $input['nome_admin']) !== '' ? $input['nome_admin'] : $modulo['nome_admin']);
        $payload['titulo'] = $this->nomeDaCopia(isset($input['titulo']) && trim((string) $input['titulo']) !== '' ? $input['titulo'] : $modulo['titulo']);
        $payload['posicao'] = $this->posicaoDaCopia($posicaoBase);
        $payload['ativo'] = 0;

        return $this->salvar($payload, $files, $usuarioId, $ipAddress, $userAgent);
    }

    public function excluir($id, $justificativa, $usuarioId = null, $ipAddress = null, $userAgent = null)
    {
        $modulo = $this->model->findById((int) $id);
        if (!$modulo) {
            return array('ok' => false, 'message' => 'Módulo não encontrado.');
        }

        $justificativa = trim((string) $justificativa);
        if ($justificativa === '') {
            return array('ok' => false, 'message' => 'Informe a justificativa para excluir o módulo.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        $this->trashService->record('frontend_modulo', $id, $justificativa, $modulo, $usuarioId, $ipAddress, $userAgent);
        $this->model->softDelete($id, $usuarioId, $justificativa);
        $this->auditService->record('frontend_modulo.excluido', 'frontend_modulo', $id, array('justificativa' => $justificativa, 'antes' => $modulo), $usuarioId, $ipAddress, $userAgent);
        $pdo->commit();
        return array('ok' => true);
    }

    private function detectarMimeType($arquivoTmp, $fallback = null)
    {
        if (!is_file($arquivoTmp)) {
            return $fallback;
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

        return $fallback;
    }

    private function salvarImagemModuloUpload(array $arquivo)
    {
        if (!isset($arquivo['error']) || (int) $arquivo['error'] !== UPLOAD_ERR_OK) {
            return array('ok' => false, 'message' => 'Upload de imagem inválido.');
        }

        if (empty($arquivo['tmp_name']) || !is_uploaded_file($arquivo['tmp_name'])) {
            return array('ok' => false, 'message' => 'Arquivo de imagem inválido.');
        }

        $tamanho = isset($arquivo['size']) ? (int) $arquivo['size'] : 0;
        if ($tamanho <= 0) {
            return array('ok' => false, 'message' => 'A imagem enviada está vazia.');
        }

        if ($tamanho > 5 * 1024 * 1024) {
            return array('ok' => false, 'message' => 'A imagem do módulo deve ter no máximo 5 MB.');
        }

        $nomeOriginal = isset($arquivo['name']) ? (string) $arquivo['name'] : '';
        $extensao = strtolower((string) pathinfo($nomeOriginal, PATHINFO_EXTENSION));
        $extensoesPermitidas = array('jpg', 'jpeg', 'png', 'webp', 'gif');

        if (!in_array($extensao, $extensoesPermitidas, true)) {
            return array('ok' => false, 'message' => 'Formato de imagem não permitido. Use JPG, PNG, WEBP ou GIF.');
        }

        $mime = $this->detectarMimeType($arquivo['tmp_name'], isset($arquivo['type']) ? $arquivo['type'] : null);
        $mimesPermitidos = array('image/jpeg', 'image/png', 'image/webp', 'image/gif');
        if (!in_array(strtolower((string) $mime), $mimesPermitidos, true)) {
            return array('ok' => false, 'message' => 'Tipo de imagem não permitido.');
        }

        if (!is_dir($this->imagemDirectoryAbsolute)) {
            if (!@mkdir($this->imagemDirectoryAbsolute, 0775, true) && !is_dir($this->imagemDirectoryAbsolute)) {
                return array('ok' => false, 'message' => 'Não foi possível criar a pasta de imagens dos módulos.');
            }
        }

        try {
            $nomeSeguro = 'modulo-' . date('YmdHis') . '-' . bin2hex(random_bytes(6)) . '.' . $extensao;
        } catch (Exception $exception) {
            $nomeSeguro = 'modulo-' . date('YmdHis') . '-' . mt_rand(100000, 999999) . '.' . $extensao;
        }

        $destino = $this->imagemDirectoryAbsolute . '/' . $nomeSeguro;
        if (!move_uploaded_file($arquivo['tmp_name'], $destino)) {
            return array('ok' => false, 'message' => 'Não foi possível salvar a imagem enviada.');
        }

        return array(
            'ok' => true,
            'path' => $this->imagemDirectoryPublic . '/' . $nomeSeguro,
        );
    }

    private function normalizarImagemExistente($valor)
    {
        $valor = trim((string) $valor);
        if ($valor === '') {
            return null;
        }

        $valor = str_replace('\\', '/', $valor);
        $prefixo = $this->imagemDirectoryPublic . '/';
        if (strpos($valor, $prefixo) !== 0) {
            return null;
        }

        $nomeArquivo = basename($valor);
        if ($nomeArquivo === '' || $nomeArquivo === '.' || $nomeArquivo === '..') {
            return null;
        }

        return $this->imagemDirectoryPublic . '/' . $nomeArquivo;
    }

    private function normalizarSlug($value)
    {
        $value = trim((string) $value);
        $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
        $value = preg_replace('/[^a-z0-9_\-]+/', '_', $value);
        return trim((string) $value, '_');
    }

    private function nomeDaCopia($valor)
    {
        $valor = trim((string) $valor);
        $valor = preg_replace('/^c[oó]pia de\s+/iu', '', $valor);
        return 'Cópia de ' . $valor;
    }

    private function codigoDaCopia($valor)
    {
        $base = $this->normalizarSlug($valor);
        $base = preg_replace('/-copia(?:-\d+)?$/', '', $base);
        $base = trim((string) $base, '_-');
        if ($base === '') {
            $base = 'modulo';
        }

        $codigo = $base . '-copia';
        $sufixo = 2;
        while ($this->model->findByCode($codigo)) {
            $codigo = $base . '-copia-' . $sufixo;
            $sufixo++;
        }

        return $codigo;
    }

    private function posicaoDaCopia($valor)
    {
        $valor = trim((string) $valor);
        $valor = preg_replace('/-copia(?:-\d+)?$/', '', $valor);
        if ($valor === '') {
            $valor = 'modulo';
        }

        $posicao = $valor . '-copia';
        $sufixo = 2;
        while ($this->model->findActiveByPositionOrCode($posicao, null)) {
            $posicao = $valor . '-copia-' . $sufixo;
            $sufixo++;
        }

        return $posicao;
    }

    private function nullableTrim($value)
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function listarLixeira()
    {
        $stmt = Database::connection()->prepare(
            'SELECT l.id, l.entidade_id, l.justificativa, l.snapshot_dados, l.created_at, u.nome AS excluido_por_nome
             FROM lixeira l
             LEFT JOIN usuarios u ON u.id = l.excluido_por_usuario_id
             WHERE l.entidade_tipo = "frontend_modulo"
               AND l.restaurado_em IS NULL
             ORDER BY l.id DESC'
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
