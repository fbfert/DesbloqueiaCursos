<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Session;
use App\Models\FrontendModulo;
use App\Models\FrontendModuloExibicaoRegra;
use Exception;
use PDO;

class FrontendModuloService
{
    private $model;
    private $regraModel;
    private $auditService;
    private $trashService;
    private $imagemDirectoryPublic;
    private $imagemDirectoryAbsolute;

    public function __construct()
    {
        $this->model = new FrontendModulo();
        $this->regraModel = new FrontendModuloExibicaoRegra();
        $this->auditService = new AuditService();
        $this->trashService = new TrashService();
        $this->imagemDirectoryPublic = '/assets/uploads/modulos';
        $this->imagemDirectoryAbsolute = BASE_PATH . $this->imagemDirectoryPublic;
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
        $modulo = $id ? $this->model->findById((int) $id) : null;
        $regrasExibicao = array();

        if ($modulo && !empty($modulo['id'])) {
            try {
                $regrasExibicao = $this->regraModel->listarPorModulo((int) $modulo['id']);
            } catch (Exception $exception) {
                $this->registrarProblemaRegra('Não foi possível carregar as regras do módulo.', array(
                    'modulo_id' => (int) $modulo['id'],
                    'erro' => $exception->getMessage(),
                ));
                $regrasExibicao = array();
            }
        }

        return array(
            'modulo' => $modulo,
            'regras_exibicao' => $regrasExibicao,
        );
    }

    public function buscarAtivoPorPosicaoOuCodigo($posicao, $codigo = null, array $contexto = array())
    {
        $modulo = $this->model->findActiveByPositionOrCode($posicao, $codigo);
        if (!$modulo) {
            return null;
        }

        $modulosFiltrados = $this->filtrarModulosPorContexto(array($modulo), $contexto);
        return !empty($modulosFiltrados) ? $modulosFiltrados[0] : null;
    }

    public function buscarPorCodigo($codigo, array $contexto = array())
    {
        $modulo = $this->model->findByCode((string) $codigo);
        if (!$modulo) {
            return null;
        }

        $modulosFiltrados = $this->filtrarModulosPorContexto(array($modulo), $contexto);
        return !empty($modulosFiltrados) ? $modulosFiltrados[0] : null;
    }

    public function listarAtivosPorPosicao($posicao, $limit = null, array $contexto = array())
    {
        $modulos = $this->model->allActiveByPosition((string) $posicao, $limit);
        return $this->filtrarModulosPorContexto($modulos, $contexto);
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
        $regrasExibicao = $this->extrairRegrasExibicao(isset($input['regras_exibicao']) && is_array($input['regras_exibicao']) ? $input['regras_exibicao'] : array());

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
        try {
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

            try {
                $this->regraModel->salvarRegrasDoModulo($id, $regrasExibicao);
            } catch (Exception $exception) {
                $this->registrarProblemaRegra('Não foi possível salvar as regras de exibição do módulo.', array(
                    'modulo_id' => $id,
                    'erro' => $exception->getMessage(),
                ));
            }

            $this->auditService->record($acao, 'frontend_modulo', $id, array('antes' => $anterior, 'depois' => $payload), $usuarioId, $ipAddress, $userAgent);
            $pdo->commit();
            return array('ok' => true, 'id' => $id);
        } catch (Exception $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return array('ok' => false, 'errors' => array('Não foi possível salvar o módulo.'));
        }
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
        if (!isset($payload['regras_exibicao']) || !is_array($payload['regras_exibicao']) || empty($payload['regras_exibicao'])) {
            $payload['regras_exibicao'] = $this->formatarRegrasParaInput($this->regraModel->listarPorModulo($id));
        }

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
        try {
            $this->regraModel->excluirPorModulo($id);
        } catch (Exception $exception) {
            $this->registrarProblemaRegra('Não foi possível remover as regras de exibição do módulo.', array(
                'modulo_id' => (int) $id,
                'erro' => $exception->getMessage(),
            ));
        }
        $this->model->softDelete($id, $usuarioId, $justificativa);
        $this->auditService->record('frontend_modulo.excluido', 'frontend_modulo', $id, array('justificativa' => $justificativa, 'antes' => $modulo), $usuarioId, $ipAddress, $userAgent);
        $pdo->commit();
        return array('ok' => true);
    }

    public function obterContextoExibicao(array $contexto = array())
    {
        $requestPath = isset($contexto['route']) ? (string) $contexto['route'] : parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $requestPath = $this->normalizarCaminho($requestPath ?: '/');
        $pageKey = isset($contexto['page_key']) ? $this->normalizarChaveContexto($contexto['page_key']) : null;
        $area = isset($contexto['area']) ? $this->normalizarChaveContexto($contexto['area']) : $this->detectarAreaAtual($requestPath);
        $authState = isset($contexto['auth_state']) ? $this->normalizarChaveContexto($contexto['auth_state']) : ((Session::get('usuario_id') !== null) ? 'logged' : 'guest');

        return array(
            'route' => $requestPath,
            'page_key' => $pageKey,
            'area' => $area,
            'auth_state' => $authState,
        );
    }

    public function filtrarModulosPorContexto(array $modulos, array $contexto = array())
    {
        if (empty($modulos)) {
            return array();
        }

        try {
            $contexto = $this->obterContextoExibicao($contexto);
            $moduloIds = array();
            foreach ($modulos as $modulo) {
                if (is_array($modulo) && !empty($modulo['id'])) {
                    $moduloIds[] = (int) $modulo['id'];
                }
            }

            $regrasPorModulo = $this->regraModel->buscarRegrasAtivasPorModulos($moduloIds);
            $modulosFiltrados = array();

            foreach ($modulos as $modulo) {
                if (!is_array($modulo) || empty($modulo['id'])) {
                    $modulosFiltrados[] = $modulo;
                    continue;
                }

                $regras = isset($regrasPorModulo[(int) $modulo['id']]) ? $regrasPorModulo[(int) $modulo['id']] : array();
                if ($this->moduloVisivelNoContexto($modulo, $regras, $contexto)) {
                    $modulosFiltrados[] = $modulo;
                }
            }

            return $modulosFiltrados;
        } catch (Exception $exception) {
            $this->registrarProblemaRegra('Não foi possível aplicar as regras de exibição dos módulos.', array(
                'erro' => $exception->getMessage(),
            ));
        }

        return $modulos;
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

    private function extrairRegrasExibicao(array $entrada)
    {
        $tiposPermitidos = array('include', 'exclude');
        $alvosPermitidos = array('route', 'page_key', 'area', 'auth_state');
        $tipos = isset($entrada['tipo_regra']) && is_array($entrada['tipo_regra']) ? $entrada['tipo_regra'] : array();
        $alvos = isset($entrada['alvo_tipo']) && is_array($entrada['alvo_tipo']) ? $entrada['alvo_tipo'] : array();
        $valores = isset($entrada['alvo_valor']) && is_array($entrada['alvo_valor']) ? $entrada['alvo_valor'] : array();
        $ativos = isset($entrada['ativo']) && is_array($entrada['ativo']) ? $entrada['ativo'] : array();
        $ordens = isset($entrada['ordem']) && is_array($entrada['ordem']) ? $entrada['ordem'] : array();

        $quantidade = max(count($tipos), count($alvos), count($valores), count($ativos), count($ordens));
        $regras = array();

        for ($i = 0; $i < $quantidade; $i++) {
            $tipo = $this->normalizarChaveContexto(isset($tipos[$i]) ? $tipos[$i] : '');
            $alvoTipo = $this->normalizarChaveContexto(isset($alvos[$i]) ? $alvos[$i] : '');
            $alvoValor = trim((string) (isset($valores[$i]) ? $valores[$i] : ''));
            $ativo = isset($ativos[$i]) ? (int) $ativos[$i] : 0;
            $ordem = isset($ordens[$i]) ? (int) $ordens[$i] : 0;

            if ($tipo === '' && $alvoTipo === '' && $alvoValor === '') {
                continue;
            }

            if (!in_array($tipo, $tiposPermitidos, true)) {
                $this->registrarProblemaRegra('Regra de exibição ignorada por tipo inválido.', array('indice' => $i, 'tipo_regra' => $tipo));
                continue;
            }

            if (!in_array($alvoTipo, $alvosPermitidos, true)) {
                $this->registrarProblemaRegra('Regra de exibição ignorada por alvo inválido.', array('indice' => $i, 'alvo_tipo' => $alvoTipo));
                continue;
            }

            $alvoValor = $this->normalizarValorRegra($alvoValor);
            if ($alvoValor === '') {
                $this->registrarProblemaRegra('Regra de exibição ignorada por valor vazio.', array('indice' => $i, 'alvo_tipo' => $alvoTipo));
                continue;
            }

            $regras[] = array(
                'tipo_regra' => $tipo,
                'alvo_tipo' => $alvoTipo,
                'alvo_valor' => $alvoValor,
                'ativo' => $ativo === 1 ? 1 : 0,
                'ordem' => $ordem,
            );
        }

        return $regras;
    }

    private function formatarRegrasParaInput(array $regras)
    {
        $formato = array(
            'tipo_regra' => array(),
            'alvo_tipo' => array(),
            'alvo_valor' => array(),
            'ativo' => array(),
            'ordem' => array(),
        );

        foreach ($regras as $regra) {
            if (!is_array($regra)) {
                continue;
            }

            $formato['tipo_regra'][] = isset($regra['tipo_regra']) ? $regra['tipo_regra'] : 'include';
            $formato['alvo_tipo'][] = isset($regra['alvo_tipo']) ? $regra['alvo_tipo'] : 'route';
            $formato['alvo_valor'][] = isset($regra['alvo_valor']) ? $regra['alvo_valor'] : '';
            $formato['ativo'][] = isset($regra['ativo']) ? (int) $regra['ativo'] : 1;
            $formato['ordem'][] = isset($regra['ordem']) ? (int) $regra['ordem'] : 0;
        }

        return $formato;
    }

    private function moduloVisivelNoContexto(array $modulo, array $regras, array $contexto)
    {
        if (empty($regras)) {
            return true;
        }

        $regrasValidas = array();
        foreach ($regras as $regra) {
            if (!is_array($regra)) {
                continue;
            }

            $tipo = isset($regra['tipo_regra']) ? $this->normalizarChaveContexto($regra['tipo_regra']) : '';
            $alvoTipo = isset($regra['alvo_tipo']) ? $this->normalizarChaveContexto($regra['alvo_tipo']) : '';
            $alvoValor = isset($regra['alvo_valor']) ? trim((string) $regra['alvo_valor']) : '';

            if ($tipo === '' || $alvoTipo === '' || $alvoValor === '') {
                $this->registrarProblemaRegra('Regra de exibição ignorada durante o filtro.', array(
                    'modulo_id' => isset($modulo['id']) ? (int) $modulo['id'] : null,
                ));
                continue;
            }

            $regrasValidas[] = array(
                'tipo_regra' => $tipo,
                'alvo_tipo' => $alvoTipo,
                'alvo_valor' => $alvoValor,
            );
        }

        if (empty($regrasValidas)) {
            return true;
        }

        $temInclude = false;
        $matchInclude = false;

        foreach ($regrasValidas as $regra) {
            $combinou = $this->regraCombinaComContexto($regra, $contexto);
            if ($regra['tipo_regra'] === 'exclude' && $combinou) {
                return false;
            }
            if ($regra['tipo_regra'] === 'include') {
                $temInclude = true;
                if ($combinou) {
                    $matchInclude = true;
                }
            }
        }

        if ($temInclude) {
            return $matchInclude;
        }

        return true;
    }

    private function regraCombinaComContexto(array $regra, array $contexto)
    {
        $alvoTipo = isset($regra['alvo_tipo']) ? $this->normalizarChaveContexto($regra['alvo_tipo']) : '';
        $alvoValor = isset($regra['alvo_valor']) ? trim((string) $regra['alvo_valor']) : '';
        $valorContexto = isset($contexto[$alvoTipo]) ? $contexto[$alvoTipo] : null;

        if ($alvoTipo === '' || $alvoValor === '' || $valorContexto === null) {
            return false;
        }

        $valorContexto = $this->normalizarValorContexto($alvoTipo, $valorContexto);
        $alvoValor = $this->normalizarValorContexto($alvoTipo, $alvoValor);

        if ($alvoTipo === 'route') {
            return $this->coringaCombina($alvoValor, $valorContexto);
        }

        return $alvoValor === $valorContexto;
    }

    private function coringaCombina($padrao, $valor)
    {
        if ($padrao === '*') {
            return true;
        }

        $expressao = preg_quote($padrao, '#');
        $expressao = str_replace('\\*', '.*', $expressao);

        return (bool) preg_match('#^' . $expressao . '$#i', $valor);
    }

    private function normalizarValorContexto($tipo, $valor)
    {
        $valor = trim((string) $valor);
        if ($tipo === 'route') {
            return $this->normalizarCaminho($valor);
        }

        return $this->normalizarChaveContexto($valor);
    }

    private function normalizarValorRegra($valor)
    {
        $valor = trim((string) $valor);
        if ($valor === '') {
            return '';
        }

        if (strpos($valor, '/') === 0) {
            return $this->normalizarCaminho($valor);
        }

        return $this->normalizarChaveContexto($valor);
    }

    private function normalizarCaminho($valor)
    {
        $valor = trim((string) $valor);
        if ($valor === '') {
            return '/';
        }

        $valor = str_replace('\\', '/', $valor);
        if (strpos($valor, '/') !== 0) {
            $valor = '/' . $valor;
        }

        if ($valor !== '/' && substr($valor, -1) === '/') {
            $valor = rtrim($valor, '/');
        }

        return function_exists('mb_strtolower') ? mb_strtolower($valor, 'UTF-8') : strtolower($valor);
    }

    private function normalizarChaveContexto($valor)
    {
        $valor = trim((string) $valor);
        return function_exists('mb_strtolower') ? mb_strtolower($valor, 'UTF-8') : strtolower($valor);
    }

    private function detectarAreaAtual($requestPath)
    {
        if (strpos($requestPath, '/admin') === 0) {
            return 'admin';
        }

        if (strpos($requestPath, '/professor') === 0) {
            return 'professor';
        }

        if (in_array($requestPath, array('/meus-cursos', '/area-curso', '/area-curso/modulo', '/area-curso/material'), true) || strpos($requestPath, '/area-curso/') === 0) {
            return 'aluno';
        }

        return 'publica';
    }

    private function registrarProblemaRegra($mensagem, array $contexto = array())
    {
        $mensagem = trim((string) $mensagem);
        if ($mensagem === '') {
            return;
        }

        $contextoTexto = $contexto ? ' ' . json_encode($contexto, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
        @error_log('[FrontendModuloService] ' . $mensagem . $contextoTexto);
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
