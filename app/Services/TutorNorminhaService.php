<?php

namespace App\Services;

use App\Models\TutorFala;
use App\Models\TutorConfiguracao;

class TutorNorminhaService
{
    const AUDIO_RELATIVE_DIR = '/uploads/tutor-norminha/audio';
    const AUDIO_MAX_BYTES = 10485760;
    const AVATAR_RELATIVE_DIR = '/uploads/tutor-norminha/avatar';
    const AVATAR_MAX_BYTES = 5242880;

    private $falaModel;
    private $configModel;

    public function __construct()
    {
        $this->falaModel = new TutorFala();
        $this->configModel = new TutorConfiguracao();
    }

    private function publicPathRoot()
    {
        if (defined('PUBLIC_PATH') && is_string(PUBLIC_PATH) && trim(PUBLIC_PATH) !== '') {
            return rtrim((string) PUBLIC_PATH, "/\\");
        }

        if (defined('BASE_PATH') && is_string(BASE_PATH) && trim(BASE_PATH) !== '') {
            $basePath = rtrim((string) BASE_PATH, "/\\");
            if (preg_match('#(?:^|[\\\\/])public_html$#i', $basePath)) {
                return $basePath;
            }

            return $basePath . DIRECTORY_SEPARATOR . 'public_html';
        }

        return rtrim((string) getcwd(), "/\\") . DIRECTORY_SEPARATOR . 'public_html';
    }

    private function publicPathFor($publicUrl)
    {
        $publicUrl = trim((string) $publicUrl);
        if ($publicUrl === '' || $publicUrl[0] !== '/') {
            return null;
        }

        $publicUrl = preg_split('/[?#]/', $publicUrl, 2)[0];
        if (!is_string($publicUrl) || $publicUrl === '' || strpos($publicUrl, '..') !== false) {
            return null;
        }

        return $this->publicPathRoot() . $publicUrl;
    }

    private function avatarAbsoluteDir()
    {
        return $this->publicPathRoot() . self::AVATAR_RELATIVE_DIR;
    }

    private function audioAbsoluteDir()
    {
        return $this->publicPathRoot() . self::AUDIO_RELATIVE_DIR;
    }

    public function listAdmin(array $filtros = array())
    {
        return array(
            'falas' => $this->falaModel->listarAdmin($filtros),
            'filtros' => array(
                'busca' => isset($filtros['busca']) ? trim((string) $filtros['busca']) : '',
                'contexto' => isset($filtros['contexto']) ? trim((string) $filtros['contexto']) : '',
                'ativo' => isset($filtros['ativo']) && $filtros['ativo'] !== '' ? (int) $filtros['ativo'] : '',
            ),
            'contextos' => $this->contextosPermitidos(),
            'estados_avatar' => $this->estadosAvatar(),
        );
    }

    public function formData($id = null)
    {
        $id = (int) $id;
        $fala = $id > 0 ? $this->falaModel->buscarPorId($id) : null;

        if (!$fala) {
            $fala = $this->falaPadrao();
        }

        $audioUrlSalvo = isset($fala['audio_url']) ? trim((string) $fala['audio_url']) : '';
        $audioPublicUrl = $this->audioPublicUrl($audioUrlSalvo);

        return array(
            'fala' => $fala,
            'contextos' => $this->contextosPermitidos(),
            'estados_avatar' => $this->estadosAvatar(),
            'audio_url_salvo' => $audioUrlSalvo,
            'audio_existe' => $audioPublicUrl !== null,
            'audio_public_url' => $audioPublicUrl,
            'configuracoes' => $this->configuracoesFormData(),
        );
    }

    public function salvar(array $input, array $files, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $id = isset($input['id']) ? (int) $input['id'] : 0;
        $existente = $id > 0 ? $this->falaModel->buscarPorId($id) : null;

        if ($id > 0 && !$existente) {
            return array(
                'ok' => false,
                'errors' => array('Fala da Norminha não encontrada.'),
            );
        }

        $payloadBase = array(
            'titulo' => isset($input['titulo']) ? trim((string) $input['titulo']) : '',
            'contexto' => isset($input['contexto']) ? trim((string) $input['contexto']) : '',
            'rota' => isset($input['rota']) ? trim((string) $input['rota']) : '',
            'curso_id' => $this->nullablePositiveInt(isset($input['curso_id']) ? $input['curso_id'] : null),
            'modulo_id' => $this->nullablePositiveInt(isset($input['modulo_id']) ? $input['modulo_id'] : null),
            'aula_id' => $this->nullablePositiveInt(isset($input['aula_id']) ? $input['aula_id'] : null),
            'texto' => isset($input['texto']) ? trim((string) $input['texto']) : '',
            'estado_avatar' => isset($input['estado_avatar']) ? trim((string) $input['estado_avatar']) : 'speaking',
            'ativo' => !empty($input['ativo']) ? 1 : 0,
        );

        $errors = $this->validarPayload($payloadBase);
        if (!empty($errors)) {
            return array('ok' => false, 'errors' => $errors);
        }

        $uploadAudio = $this->prepararUploadAudio(isset($files['audio_file']) ? $files['audio_file'] : null);
        if (!empty($uploadAudio['error'])) {
            return array(
                'ok' => false,
                'errors' => array('audio_file' => $uploadAudio['error']),
            );
        }

        $payload = $payloadBase;
        $payload['audio_url'] = $this->normalizarAudioUrlSalva($existente && array_key_exists('audio_url', $existente) ? $existente['audio_url'] : null);

        if ($id > 0) {
            $ok = $this->falaModel->atualizar($id, $payload);
            if (!$ok) {
                $this->removerArquivoSeExistir($uploadAudio);
                return array(
                    'ok' => false,
                    'errors' => array('Não foi possível atualizar a fala.'),
                );
            }
        } else {
            $id = $this->falaModel->criar($payload);
            if ($id <= 0) {
                $this->removerArquivoSeExistir($uploadAudio);
                return array(
                    'ok' => false,
                    'errors' => array('Não foi possível criar a fala.'),
                );
            }
        }

        if (!empty($uploadAudio['absolute_path'])) {
            $finalizado = $this->finalizarUploadAudio($uploadAudio, $id);
            if (empty($finalizado['ok'])) {
                $this->removerArquivoSeExistir($uploadAudio);
                return array(
                    'ok' => false,
                    'errors' => array(isset($finalizado['error']) ? $finalizado['error'] : 'Não foi possível salvar o áudio enviado.'),
                );
            }

            $this->falaModel->atualizarAudioUrl($id, $finalizado['path']);
        }

        return array(
            'ok' => true,
            'id' => $id,
        );
    }

    public function alternarStatus($id, $ativo, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $id = (int) $id;
        if ($id <= 0) {
            return array(
                'ok' => false,
                'message' => 'Informe uma fala válida.',
            );
        }

        $fala = $this->falaModel->buscarPorId($id);
        if (!$fala) {
            return array(
                'ok' => false,
                'message' => 'Fala da Norminha não encontrada.',
            );
        }

        $ok = $this->falaModel->atualizarStatus($id, !empty($ativo) ? 1 : 0);
        if (!$ok) {
            return array(
                'ok' => false,
                'message' => 'Não foi possível atualizar o status da fala.',
            );
        }

        return array(
            'ok' => true,
            'id' => $id,
            'ativo' => !empty($ativo) ? 1 : 0,
        );
    }

    public function configuracoesFormData()
    {
        $defaults = $this->configuracoesPadrao();
        $current = $this->normalizarConfiguracoes($this->configModel->allIndexed());
        $configuracoes = array_merge($defaults, $current);
        $avatarIdlePadrao = '/assets/norminha/norminha-idle.webp';
        $avatarSpeakingPadrao = '/assets/norminha/norminha-speaking.webp';
        $avatarIdleDiagnostico = $this->resolverImagemNorminha($this->valorConfiguracaoAvatar($configuracoes, array('avatar_parado_url', 'tutor_avatar_idle')), $avatarIdlePadrao);
        $avatarSpeakingDiagnostico = $this->resolverImagemNorminha($this->valorConfiguracaoAvatar($configuracoes, array('avatar_falando_url', 'tutor_avatar_speaking')), $avatarSpeakingPadrao);

        return array_merge($configuracoes, array(
            'tutor_avatar_idle_public_url' => isset($avatarIdleDiagnostico['public_url']) ? $avatarIdleDiagnostico['public_url'] : null,
            'tutor_avatar_idle_usando_padrao' => !empty($avatarIdleDiagnostico['usando_padrao']) ? 1 : 0,
            'avatar_parado_url_public_url' => isset($avatarIdleDiagnostico['public_url']) ? $avatarIdleDiagnostico['public_url'] : null,
            'avatar_parado_url_usando_padrao' => !empty($avatarIdleDiagnostico['usando_padrao']) ? 1 : 0,
            'tutor_avatar_idle_diagnostico' => $avatarIdleDiagnostico,
            'avatar_parado_url_diagnostico' => $avatarIdleDiagnostico,
            'tutor_avatar_speaking_public_url' => isset($avatarSpeakingDiagnostico['public_url']) ? $avatarSpeakingDiagnostico['public_url'] : null,
            'tutor_avatar_speaking_usando_padrao' => !empty($avatarSpeakingDiagnostico['usando_padrao']) ? 1 : 0,
            'avatar_falando_url_public_url' => isset($avatarSpeakingDiagnostico['public_url']) ? $avatarSpeakingDiagnostico['public_url'] : null,
            'avatar_falando_url_usando_padrao' => !empty($avatarSpeakingDiagnostico['usando_padrao']) ? 1 : 0,
            'tutor_avatar_speaking_diagnostico' => $avatarSpeakingDiagnostico,
            'avatar_falando_url_diagnostico' => $avatarSpeakingDiagnostico,
        ));
    }

    public function salvarConfiguracoes(array $input, array $files = array(), $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $current = $this->normalizarConfiguracoes($this->configModel->allIndexed());
        $errors = $this->validarConfiguracoes($current, $input, $files);
        if (!empty($errors)) {
            return array(
                'ok' => false,
                'errors' => $errors,
            );
        }

        $payload = $this->extrairConfiguracoes($input, $current, $files);

        $resultado = $this->configModel->saveMany($payload);
        if (empty($resultado['ok'])) {
            return array(
                'ok' => false,
                'errors' => array('Não foi possível salvar as configurações da Norminha.'),
            );
        }

        $this->removerAvatarAnteriorSeSubstituido(
            isset($current['tutor_avatar_idle']) ? $current['tutor_avatar_idle'] : null,
            isset($payload['tutor_avatar_idle']) ? $payload['tutor_avatar_idle'] : null
        );
        $this->removerAvatarAnteriorSeSubstituido(
            isset($current['tutor_avatar_speaking']) ? $current['tutor_avatar_speaking'] : null,
            isset($payload['tutor_avatar_speaking']) ? $payload['tutor_avatar_speaking'] : null
        );

        return array(
            'ok' => true,
        );
    }

    public function configuracoesPadrao()
    {
        return array(
            'tutor_ativo' => 1,
            'tutor_home' => 1,
            'tutor_area_aluno' => 1,
            'tutor_cursos' => 1,
            'tutor_checkout' => 0,
            'tutor_minimizado_padrao' => 0,
            'tutor_ttl_fechamento_horas' => 24,
            'tutor_texto_botao' => 'Ouvir orientação',
            'tutor_titulo_padrao' => 'Norminha',
            'tutor_avatar_idle' => '/assets/norminha/norminha-idle.webp',
            'tutor_avatar_speaking' => '/assets/norminha/norminha-speaking.webp',
            'avatar_parado_url' => '/assets/norminha/norminha-idle.webp',
            'avatar_falando_url' => '/assets/norminha/norminha-speaking.webp',
        );
    }

    public function contextosPermitidos()
    {
        return array('home', 'institucional', 'area_aluno', 'cursos', 'curso', 'aula', 'checkout');
    }

    public function estadosAvatar()
    {
        return array('speaking', 'explaining', 'attention', 'celebrating', 'doubt');
    }

    private function falaPadrao()
    {
        return array(
            'id' => 0,
            'titulo' => '',
            'contexto' => '',
            'rota' => '',
            'curso_id' => null,
            'modulo_id' => null,
            'aula_id' => null,
            'texto' => '',
            'audio_url' => null,
            'estado_avatar' => 'speaking',
            'ativo' => 1,
            'criado_em' => null,
            'atualizado_em' => null,
        );
    }

    private function normalizarConfiguracoes(array $configuracoes)
    {
        $defaults = $this->configuracoesPadrao();
        $normalizadas = array();

        foreach ($defaults as $chave => $valorPadrao) {
            $valor = array_key_exists($chave, $configuracoes) ? $configuracoes[$chave] : $valorPadrao;

            if (in_array($chave, array('tutor_ativo', 'tutor_home', 'tutor_area_aluno', 'tutor_cursos', 'tutor_checkout', 'tutor_minimizado_padrao'), true)) {
                $normalizadas[$chave] = $this->normalizarBool($valor, $valorPadrao);
                continue;
            }

            if ($chave === 'tutor_ttl_fechamento_horas') {
                $normalizadas[$chave] = $this->normalizarTtlHoras($valor, $valorPadrao);
                continue;
            }

            if ($chave === 'tutor_texto_botao' || $chave === 'tutor_titulo_padrao') {
                $texto = trim(strip_tags((string) $valor));
                $normalizadas[$chave] = $texto !== '' ? $texto : $valorPadrao;
                continue;
            }

            if ($chave === 'tutor_avatar_idle' || $chave === 'tutor_avatar_speaking' || $chave === 'avatar_parado_url' || $chave === 'avatar_falando_url') {
                $normalizadas[$chave] = $this->normalizarAvatarPath($valor, $valorPadrao);
                continue;
            }

            $normalizadas[$chave] = $valor;
        }

        $normalizadas['tutor_avatar_idle'] = $this->normalizarAvatarPath(
            $this->valorConfiguracaoAvatar($normalizadas, array('tutor_avatar_idle', 'avatar_parado_url')),
            $defaults['tutor_avatar_idle']
        );
        $normalizadas['avatar_parado_url'] = $normalizadas['tutor_avatar_idle'];
        $normalizadas['tutor_avatar_speaking'] = $this->normalizarAvatarPath(
            $this->valorConfiguracaoAvatar($normalizadas, array('tutor_avatar_speaking', 'avatar_falando_url')),
            $defaults['tutor_avatar_speaking']
        );
        $normalizadas['avatar_falando_url'] = $normalizadas['tutor_avatar_speaking'];

        return $normalizadas;
    }

    private function extrairConfiguracoes(array $input, array $current = array(), array $files = array())
    {
        $payload = array_merge($this->configuracoesPadrao(), $this->normalizarConfiguracoes($current));

        $payload['tutor_ativo'] = $this->normalizarBool(isset($input['tutor_ativo']) ? $input['tutor_ativo'] : $payload['tutor_ativo'], 1);
        $payload['tutor_home'] = $this->normalizarBool(isset($input['tutor_home']) ? $input['tutor_home'] : $payload['tutor_home'], 1);
        $payload['tutor_area_aluno'] = $this->normalizarBool(isset($input['tutor_area_aluno']) ? $input['tutor_area_aluno'] : $payload['tutor_area_aluno'], 1);
        $payload['tutor_cursos'] = $this->normalizarBool(isset($input['tutor_cursos']) ? $input['tutor_cursos'] : $payload['tutor_cursos'], 1);
        $payload['tutor_checkout'] = $this->normalizarBool(isset($input['tutor_checkout']) ? $input['tutor_checkout'] : $payload['tutor_checkout'], 0);
        $payload['tutor_minimizado_padrao'] = $this->normalizarBool(isset($input['tutor_minimizado_padrao']) ? $input['tutor_minimizado_padrao'] : $payload['tutor_minimizado_padrao'], 0);
        $payload['tutor_ttl_fechamento_horas'] = $this->normalizarTtlHoras(isset($input['tutor_ttl_fechamento_horas']) ? $input['tutor_ttl_fechamento_horas'] : $payload['tutor_ttl_fechamento_horas'], 24);
        $payload['tutor_texto_botao'] = $this->normalizarTexto(isset($input['tutor_texto_botao']) ? $input['tutor_texto_botao'] : $payload['tutor_texto_botao'], 'Ouvir orientação', 80);
        $payload['tutor_titulo_padrao'] = $this->normalizarTexto(isset($input['tutor_titulo_padrao']) ? $input['tutor_titulo_padrao'] : $payload['tutor_titulo_padrao'], 'Norminha', 80);

        $uploadIdle = $this->prepararUploadAvatar(isset($files['tutor_avatar_idle_file']) ? $files['tutor_avatar_idle_file'] : null, 'idle');
        if (!empty($uploadIdle['ok']) && !empty($uploadIdle['path'])) {
            $payload['tutor_avatar_idle'] = $uploadIdle['path'];
            $payload['avatar_parado_url'] = $uploadIdle['path'];
        } elseif (!isset($payload['tutor_avatar_idle']) || $payload['tutor_avatar_idle'] === '') {
            $payload['tutor_avatar_idle'] = '/assets/norminha/norminha-idle.webp';
            $payload['avatar_parado_url'] = '/assets/norminha/norminha-idle.webp';
        } else {
            $payload['avatar_parado_url'] = $payload['tutor_avatar_idle'];
        }

        $uploadSpeaking = $this->prepararUploadAvatar(isset($files['tutor_avatar_speaking_file']) ? $files['tutor_avatar_speaking_file'] : null, 'speaking');
        if (!empty($uploadSpeaking['ok']) && !empty($uploadSpeaking['path'])) {
            $payload['tutor_avatar_speaking'] = $uploadSpeaking['path'];
            $payload['avatar_falando_url'] = $uploadSpeaking['path'];
        } elseif (!isset($payload['tutor_avatar_speaking']) || $payload['tutor_avatar_speaking'] === '') {
            $payload['tutor_avatar_speaking'] = '/assets/norminha/norminha-speaking.webp';
            $payload['avatar_falando_url'] = '/assets/norminha/norminha-speaking.webp';
        } else {
            $payload['avatar_falando_url'] = $payload['tutor_avatar_speaking'];
        }

        return $payload;
    }

    private function validarConfiguracoes(array $payload, array $rawInput = array(), array $files = array())
    {
        $errors = array();

        if ($payload['tutor_ttl_fechamento_horas'] < 1 || $payload['tutor_ttl_fechamento_horas'] > 168) {
            $errors['tutor_ttl_fechamento_horas'] = 'O TTL de fechamento deve estar entre 1 e 168 horas.';
        }

        if (strlen($payload['tutor_texto_botao']) > 80) {
            $errors['tutor_texto_botao'] = 'O texto do botão deve ter no máximo 80 caracteres.';
        }

        if (strlen($payload['tutor_titulo_padrao']) > 80) {
            $errors['tutor_titulo_padrao'] = 'O título padrão deve ter no máximo 80 caracteres.';
        }

        if (!empty($files['tutor_avatar_idle_file']) && !empty($files['tutor_avatar_idle_file']['name'])) {
            $erroIdle = $this->validarUploadAvatarArquivo($files['tutor_avatar_idle_file']);
            if ($erroIdle !== null) {
                $errors['tutor_avatar_idle_file'] = $erroIdle;
            }
        }

        if (!empty($files['tutor_avatar_speaking_file']) && !empty($files['tutor_avatar_speaking_file']['name'])) {
            $erroSpeaking = $this->validarUploadAvatarArquivo($files['tutor_avatar_speaking_file']);
            if ($erroSpeaking !== null) {
                $errors['tutor_avatar_speaking_file'] = $erroSpeaking;
            }
        }

        return $errors;
    }

    private function normalizarBool($valor, $padrao = 0)
    {
        if ($valor === null || $valor === '') {
            return (int) $padrao;
        }

        if (is_bool($valor)) {
            return $valor ? 1 : 0;
        }

        $valor = trim((string) $valor);
        if ($valor === '') {
            return (int) $padrao;
        }

        return in_array($valor, array('1', 'true', 'on', 'sim', 'yes'), true) ? 1 : 0;
    }

    private function normalizarTtlHoras($valor, $padrao = 24)
    {
        if ($valor === null || $valor === '') {
            return (int) $padrao;
        }

        if (is_string($valor) && !preg_match('/^\d+$/', trim($valor))) {
            return (int) $padrao;
        }

        $valor = (int) $valor;
        if ($valor < 1 || $valor > 168) {
            return (int) $padrao;
        }

        return $valor;
    }

    private function normalizarTexto($valor, $padrao, $maximo)
    {
        $valor = trim(strip_tags((string) $valor));
        if ($valor === '') {
            return $padrao;
        }

        if (function_exists('mb_substr')) {
            return mb_substr($valor, 0, (int) $maximo, 'UTF-8');
        }

        return substr($valor, 0, (int) $maximo);
    }

    private function normalizarAvatarPath($valor, $padrao)
    {
        $valor = $this->extrairAvatarPathValido($valor);
        return $valor !== null ? $valor : $padrao;
    }

    private function avatarPathValido($valor)
    {
        $valor = $this->extrairAvatarPathValido($valor);
        if ($valor === null) {
            return false;
        }

        return true;
    }

    private function avatarPublicUrl($valor, $fallback = null, $comVersao = true)
    {
        $publicUrl = $this->resolverAvatarPublicUrl($valor);
        if ($publicUrl === null) {
            $publicUrl = $this->resolverAvatarPublicUrl($fallback);
        }

        if ($publicUrl === null) {
            return null;
        }

        return $comVersao ? $this->adicionarVersaoPublicUrl($publicUrl) : $publicUrl;
    }

    private function prepararUploadAvatar($arquivo, $tipo)
    {
        if (empty($arquivo) || empty($arquivo['tmp_name'])) {
            return array('ok' => true, 'path' => null);
        }

        $erro = $this->validarUploadAvatarArquivo($arquivo);
        if ($erro !== null) {
            return array('error' => $erro);
        }

        $nomeOriginal = basename((string) $arquivo['name']);
        $extensao = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));

        $diretorioAvatar = $this->avatarAbsoluteDir();
        if (!is_dir($diretorioAvatar)) {
            if (!@mkdir($diretorioAvatar, 0775, true) && !is_dir($diretorioAvatar)) {
                return array('error' => 'Não foi possível preparar a pasta de imagens da Norminha.');
            }
        }

        if (!is_writable($diretorioAvatar)) {
            return array('error' => 'A pasta de imagens da Norminha não tem permissão de escrita.');
        }

        $timestamp = date('YmdHis');
        $nomeSeguro = 'norminha-avatar-' . $tipo . '-' . $timestamp . '-' . bin2hex(random_bytes(5)) . '.' . $extensao;
        $destinoAbsoluto = rtrim($diretorioAvatar, '/\\') . '/' . $nomeSeguro;

        if (!move_uploaded_file($arquivo['tmp_name'], $destinoAbsoluto)) {
            return array('error' => 'Não foi possível salvar a imagem enviada.');
        }

        if (!$this->imagemArquivoValida($destinoAbsoluto, $extensao)) {
            @unlink($destinoAbsoluto);
            return array('error' => 'A imagem não foi salva corretamente.');
        }

        @chmod($destinoAbsoluto, 0644);

        return array(
            'ok' => true,
            'path' => self::AVATAR_RELATIVE_DIR . '/' . $nomeSeguro,
            'absolute_path' => $destinoAbsoluto,
        );
    }

    private function validarUploadAvatarArquivo($arquivo)
    {
        if (empty($arquivo) || empty($arquivo['tmp_name'])) {
            return null;
        }

        if (!isset($arquivo['error']) || (int) $arquivo['error'] !== UPLOAD_ERR_OK) {
            return 'Não foi possível ler a imagem enviada.';
        }

        if (empty($arquivo['name'])) {
            return 'Informe uma imagem válida.';
        }

        if (empty($arquivo['tmp_name']) || !is_uploaded_file($arquivo['tmp_name'])) {
            return 'Imagem inválida.';
        }

        if (!isset($arquivo['size']) || (int) $arquivo['size'] <= 0) {
            return 'Imagem inválida.';
        }

        if ((int) $arquivo['size'] > self::AVATAR_MAX_BYTES) {
            return 'A imagem enviada excede o limite de 5 MB.';
        }

        $nomeOriginal = basename((string) $arquivo['name']);
        $extensao = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));
        $extensoesPermitidas = array('jpg', 'jpeg', 'png', 'webp', 'gif');
        if (!in_array($extensao, $extensoesPermitidas, true)) {
            return 'Envie uma imagem nos formatos JPG, PNG, WEBP ou GIF.';
        }

        $mime = $this->detectarMimeType($arquivo['tmp_name']);
        $mimesPermitidos = array('image/jpeg', 'image/png', 'image/webp', 'image/gif');
        if ($mime === null || !in_array(strtolower((string) $mime), $mimesPermitidos, true)) {
            return 'Tipo de imagem não permitido.';
        }

        return null;
    }

    private function resolverCaminhoAvatar($valor)
    {
        $valor = $this->extrairAvatarPathValido($valor);
        if ($valor === null) {
            return null;
        }

        if (strpos($valor, '/uploads/tutor-norminha/avatar/') === 0) {
            return $this->publicPathFor($valor);
        }

        if (strpos($valor, '/assets/norminha/') === 0) {
            return $this->publicPathFor($valor);
        }

        return null;
    }

    private function imagemArquivoValida($caminhoFisico, $extensaoEsperada = null)
    {
        $caminhoFisico = trim((string) $caminhoFisico);
        if ($caminhoFisico === '' || !is_file($caminhoFisico) || !is_readable($caminhoFisico)) {
            return false;
        }

        clearstatcache(true, $caminhoFisico);
        $tamanho = @filesize($caminhoFisico);
        if ($tamanho === false || (int) $tamanho <= 0) {
            return false;
        }

        $mime = $this->detectarMimeType($caminhoFisico);
        if ($mime === null) {
            return false;
        }

        $mime = strtolower((string) $mime);
        $extensaoEsperada = strtolower(trim((string) $extensaoEsperada));
        if ($extensaoEsperada === 'jpg' || $extensaoEsperada === 'jpeg') {
            return strpos($mime, 'image/jpeg') === 0;
        }

        if ($extensaoEsperada === 'png') {
            return strpos($mime, 'image/png') === 0;
        }

        if ($extensaoEsperada === 'webp') {
            return strpos($mime, 'image/webp') === 0;
        }

        if ($extensaoEsperada === 'gif') {
            return strpos($mime, 'image/gif') === 0;
        }

        return in_array($mime, array('image/jpeg', 'image/png', 'image/webp', 'image/gif'), true);
    }

    private function validarPayload(array $payload)
    {
        $errors = array();

        if ($payload['titulo'] === '') {
            $errors['titulo'] = 'Informe o título.';
        } elseif (strlen($payload['titulo']) > 255) {
            $errors['titulo'] = 'O título deve ter no máximo 255 caracteres.';
        }

        if ($payload['contexto'] === '') {
            $errors['contexto'] = 'Informe o contexto.';
        } elseif (strlen($payload['contexto']) > 80) {
            $errors['contexto'] = 'O contexto deve ter no máximo 80 caracteres.';
        } elseif (!in_array($payload['contexto'], $this->contextosPermitidos(), true)) {
            $errors['contexto'] = 'Selecione um contexto válido.';
        }

        if ($payload['rota'] !== '') {
            if (strlen($payload['rota']) > 255) {
                $errors['rota'] = 'A rota deve ter no máximo 255 caracteres.';
            } elseif (strpos($payload['rota'], '/') !== 0) {
                $errors['rota'] = 'A rota deve começar com /.';
            } elseif (!preg_match('#^/[A-Za-z0-9/_\-\*]*$#', $payload['rota'])) {
                $errors['rota'] = 'A rota contém caracteres inválidos.';
            }
        }

        if ($payload['curso_id'] !== null && !$this->isPositiveInteger($payload['curso_id'])) {
            $errors['curso_id'] = 'Informe um curso válido ou deixe em branco.';
        }

        if ($payload['modulo_id'] !== null && !$this->isPositiveInteger($payload['modulo_id'])) {
            $errors['modulo_id'] = 'Informe um módulo válido ou deixe em branco.';
        }

        if ($payload['aula_id'] !== null && !$this->isPositiveInteger($payload['aula_id'])) {
            $errors['aula_id'] = 'Informe uma aula válida ou deixe em branco.';
        }

        if ($payload['texto'] === '') {
            $errors['texto'] = 'Informe o texto da fala.';
        }

        if (!in_array($payload['estado_avatar'], $this->estadosAvatar(), true)) {
            $errors['estado_avatar'] = 'Selecione um estado de avatar válido.';
        }

        if (!in_array((int) $payload['ativo'], array(0, 1), true)) {
            $errors['ativo'] = 'Status inválido.';
        }

        return $errors;
    }

    private function prepararUploadAudio($arquivo)
    {
        if (empty($arquivo) || empty($arquivo['tmp_name'])) {
            return array('ok' => true, 'path' => null);
        }

        if (!isset($arquivo['error']) || (int) $arquivo['error'] !== UPLOAD_ERR_OK) {
            return array('error' => 'Não foi possível ler o áudio enviado.');
        }

        if (empty($arquivo['name'])) {
            return array('error' => 'Informe um arquivo de áudio válido.');
        }

        if (empty($arquivo['tmp_name']) || !is_uploaded_file($arquivo['tmp_name'])) {
            return array('error' => 'Arquivo de áudio inválido.');
        }

        if (!isset($arquivo['size']) || (int) $arquivo['size'] <= 0) {
            return array('error' => 'Arquivo de áudio inválido.');
        }

        if ((int) $arquivo['size'] > self::AUDIO_MAX_BYTES) {
            return array('error' => 'O áudio enviado excede o limite de 10 MB.');
        }

        $nomeOriginal = basename((string) $arquivo['name']);
        $extensao = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));
        $extensoesPermitidas = array('mp3', 'ogg', 'wav');
        if (!in_array($extensao, $extensoesPermitidas, true)) {
            return array('error' => 'Envie um áudio nos formatos MP3, OGG ou WAV.');
        }

        $mime = $this->detectarMimeType($arquivo['tmp_name']);
        $mimesPermitidos = array('audio/mpeg', 'audio/mp3', 'audio/ogg', 'audio/wav', 'audio/x-wav', 'audio/vnd.wave', 'audio/x-pn-wav');
        if ($mime === null || !in_array(strtolower((string) $mime), $mimesPermitidos, true)) {
            return array('error' => 'Tipo de áudio não permitido.');
        }

        $diretorioAudio = $this->audioAbsoluteDir();
        if (!is_dir($diretorioAudio)) {
            if (!@mkdir($diretorioAudio, 0775, true) && !is_dir($diretorioAudio)) {
                return array('error' => 'Não foi possível preparar a pasta de áudio.');
            }
        }

        if (!is_writable($diretorioAudio)) {
            return array('error' => 'A pasta de áudio não tem permissão de escrita.');
        }

        $timestamp = date('YmdHis');
        $nomeTemporario = 'norminha-upload-' . $timestamp . '-' . bin2hex(random_bytes(5)) . '.' . $extensao;
        $destinoAbsoluto = rtrim($diretorioAudio, '/\\') . '/' . $nomeTemporario;

        if (!move_uploaded_file($arquivo['tmp_name'], $destinoAbsoluto)) {
            return array('error' => 'Não foi possível salvar o áudio enviado.');
        }

        if (!$this->audioArquivoValido($destinoAbsoluto, $extensao)) {
            @unlink($destinoAbsoluto);
            return array('error' => 'O áudio enviado não foi gravado corretamente.');
        }

        @chmod($destinoAbsoluto, 0644);

        return array(
            'ok' => true,
            'path' => self::AUDIO_RELATIVE_DIR . '/' . $nomeTemporario,
            'absolute_path' => $destinoAbsoluto,
            'timestamp' => $timestamp,
            'extension' => $extensao,
        );
    }

    private function finalizarUploadAudio(array $uploadAudio, $id)
    {
        $id = (int) $id;
        if ($id <= 0) {
            return array('ok' => false, 'error' => 'Não foi possível identificar a fala salva.');
        }

        if (empty($uploadAudio['absolute_path']) || empty($uploadAudio['path'])) {
            return array('ok' => false, 'error' => 'Arquivo de áudio inválido.');
        }

        $extensao = isset($uploadAudio['extension']) ? strtolower((string) $uploadAudio['extension']) : '';
        if ($extensao === '') {
            $extensao = 'mp3';
        }

        $timestamp = isset($uploadAudio['timestamp']) ? (string) $uploadAudio['timestamp'] : date('YmdHis');
        $nomeFinal = 'norminha-fala-' . $id . '-' . $timestamp . '.' . $extensao;
        $destinoFinalAbsoluto = rtrim($this->audioAbsoluteDir(), '/\\') . '/' . $nomeFinal;

        if ($uploadAudio['absolute_path'] !== $destinoFinalAbsoluto) {
            if (is_file($destinoFinalAbsoluto)) {
                @unlink($destinoFinalAbsoluto);
            }

            if (!@rename($uploadAudio['absolute_path'], $destinoFinalAbsoluto)) {
                return array('ok' => false, 'error' => 'Não foi possível finalizar o áudio enviado.');
            }
        }

        if (!$this->audioArquivoValido($destinoFinalAbsoluto, $extensao)) {
            @unlink($destinoFinalAbsoluto);
            return array('ok' => false, 'error' => 'O áudio enviado não foi gravado corretamente.');
        }

        @chmod($destinoFinalAbsoluto, 0644);

        return array(
            'ok' => true,
            'path' => self::AUDIO_RELATIVE_DIR . '/' . $nomeFinal,
            'absolute_path' => $destinoFinalAbsoluto,
        );
    }

    private function removerArquivoSeExistir(array $uploadAudio)
    {
        if (!empty($uploadAudio['absolute_path']) && is_file($uploadAudio['absolute_path'])) {
            @unlink($uploadAudio['absolute_path']);
        }
    }

    private function audioPublicUrl($audioUrl)
    {
        $audioUrl = $this->normalizarAudioUrlSalva($audioUrl);
        if ($audioUrl === null) {
            return null;
        }

        $caminhoFisico = $this->publicPathFor($audioUrl);
        if (!$this->audioArquivoValido($caminhoFisico, pathinfo($audioUrl, PATHINFO_EXTENSION))) {
            return null;
        }

        return $audioUrl;
    }

    private function normalizarAudioUrlSalva($audioUrl)
    {
        $audioUrl = trim((string) $audioUrl);
        if ($audioUrl === '') {
            return null;
        }

        if (preg_match('#^(https?:)?//#i', $audioUrl) || stripos($audioUrl, 'javascript:') === 0 || stripos($audioUrl, 'data:') === 0) {
            return null;
        }

        if (strpos($audioUrl, '/uploads/tutor-norminha/audio/') !== 0) {
            return null;
        }

        if (strpos($audioUrl, '..') !== false || strpos($audioUrl, '?') !== false || strpos($audioUrl, '#') !== false || strpos($audioUrl, 'public_html') !== false) {
            return null;
        }

        if ($audioUrl[0] !== '/') {
            return null;
        }

        return $audioUrl;
    }

    private function audioPhysicalPath($audioUrl)
    {
        $audioUrl = trim((string) $audioUrl);
        if ($audioUrl === '') {
            return null;
        }

        if (strpos($audioUrl, '/uploads/tutor-norminha/audio/') !== 0) {
            return null;
        }

        if (strpos($audioUrl, '..') !== false || strpos($audioUrl, '?') !== false || strpos($audioUrl, '#') !== false) {
            return null;
        }

        return $this->publicPathFor($audioUrl);
    }

    private function audioArquivoValido($caminhoFisico, $extensaoEsperada = null)
    {
        $caminhoFisico = trim((string) $caminhoFisico);
        if ($caminhoFisico === '' || !is_file($caminhoFisico) || !is_readable($caminhoFisico)) {
            return false;
        }

        clearstatcache(true, $caminhoFisico);
        $tamanho = @filesize($caminhoFisico);
        if ($tamanho === false || (int) $tamanho <= 0) {
            return false;
        }

        $mime = $this->detectarMimeType($caminhoFisico);
        if ($mime === null) {
            return false;
        }

        $mime = strtolower((string) $mime);
        $extensaoEsperada = strtolower(trim((string) $extensaoEsperada));
        if ($extensaoEsperada === 'mp3' && strpos($mime, 'audio/mpeg') !== 0 && $mime !== 'audio/mp3' && $mime !== 'audio/x-mpeg') {
            return false;
        }

        if ($extensaoEsperada === 'ogg' && strpos($mime, 'audio/ogg') !== 0 && $mime !== 'application/ogg') {
            return false;
        }

        if ($extensaoEsperada === 'wav' && strpos($mime, 'audio/wav') !== 0 && strpos($mime, 'audio/x-wav') !== 0 && strpos($mime, 'audio/vnd.wave') !== 0 && strpos($mime, 'audio/x-pn-wav') !== 0) {
            return false;
        }

        return true;
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
                if (is_string($mime) && $mime !== '') {
                    return $mime;
                }
            }
        }

        if (function_exists('mime_content_type')) {
            $mime = @mime_content_type($arquivoTmp);
            if (is_string($mime) && $mime !== '') {
                return $mime;
            }
        }

        return null;
    }

    private function nullablePositiveInt($value)
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);
        }

        if ($value === '' || $value === '0' || $value === 0) {
            return null;
        }

        if (!ctype_digit((string) $value)) {
            return null;
        }

        $id = (int) $value;
        return $id > 0 ? $id : null;
    }

    private function isPositiveInteger($value)
    {
        if ($value === null || $value === '') {
            return false;
        }

        if (is_string($value) && !preg_match('/^\d+$/', $value)) {
            return false;
        }

        return (int) $value > 0;
    }

    private function extrairAvatarPathValido($valor)
    {
        $valor = trim((string) $valor);
        if ($valor === '') {
            return null;
        }

        $valor = str_replace('\\', '/', $valor);

        if (preg_match('#^(https?:)?//#i', $valor) || stripos($valor, 'javascript:') === 0 || stripos($valor, 'data:') === 0) {
            return null;
        }

        $path = $valor;
        $path = preg_split('/[?#]/', $path, 2)[0];
        if ($path === null || $path === '') {
            return null;
        }

        $uploadsPos = strpos($path, '/uploads/tutor-norminha/avatar/');
        $assetsPos = strpos($path, '/assets/norminha/');

        if ($uploadsPos !== false) {
            $path = substr($path, $uploadsPos);
        } elseif ($assetsPos !== false) {
            $path = substr($path, $assetsPos);
        } elseif ($path[0] !== '/' && strpos($path, 'uploads/tutor-norminha/avatar/') === 0) {
            $path = '/' . $path;
        } elseif ($path[0] !== '/' && strpos($path, 'assets/norminha/') === 0) {
            $path = '/' . $path;
        } else {
            return null;
        }

        if (strpos($path, '..') !== false || strpos($path, 'public_html') !== false) {
            return null;
        }

        return $path;
    }

    private function valorConfiguracaoAvatar(array $configuracoes, array $chaves)
    {
        foreach ($chaves as $chave) {
            if (isset($configuracoes[$chave])) {
                $valor = trim((string) $configuracoes[$chave]);
                if ($valor !== '') {
                    return $valor;
                }
            }
        }

        return '';
    }

    private function resolverAvatarPublicUrl($valor)
    {
        $path = $this->extrairAvatarPathValido($valor);
        if ($path === null) {
            return null;
        }

        $caminhoFisico = $this->resolverCaminhoAvatar($path);
        if ($caminhoFisico === null || !is_file($caminhoFisico) || !is_readable($caminhoFisico) || (int) @filesize($caminhoFisico) <= 0) {
            return null;
        }

        return $path;
    }

    private function adicionarVersaoPublicUrl($publicUrl)
    {
        $publicUrl = trim((string) $publicUrl);
        if ($publicUrl === '') {
            return null;
        }

        $caminhoFisico = $this->resolverCaminhoAvatar($publicUrl);
        if ($caminhoFisico === null || !is_file($caminhoFisico)) {
            return $publicUrl;
        }

        $versao = @filemtime($caminhoFisico);
        if (!$versao) {
            return $publicUrl;
        }

        return $publicUrl . '?v=' . (int) $versao;
    }

    private function resolverImagemNorminha($valor, $fallback = null)
    {
        $valorNormalizado = $this->extrairAvatarPathValido($valor);
        $fallbackNormalizado = $this->extrairAvatarPathValido($fallback);
        $candidato = $valorNormalizado !== null ? $valorNormalizado : $fallbackNormalizado;

        $diagnostico = array(
            'valor_banco' => trim((string) $valor),
            'valor_normalizado' => $candidato,
            'url_publica' => null,
            'caminho_fisico' => null,
            'file_exists' => 0,
            'usando_padrao' => 0,
        );

        if ($candidato === null) {
            $diagnostico['usando_padrao'] = 1;
            return $diagnostico;
        }

        $caminhoFisico = $this->resolverCaminhoAvatar($candidato);
        $diagnostico['caminho_fisico'] = $caminhoFisico;

        if ($caminhoFisico !== null && is_file($caminhoFisico) && is_readable($caminhoFisico) && (int) @filesize($caminhoFisico) > 0) {
            $diagnostico['file_exists'] = 1;
            $diagnostico['url_publica'] = $this->adicionarVersaoPublicUrl($candidato);
            $diagnostico['usando_padrao'] = $candidato === $fallbackNormalizado ? 1 : 0;
            return $diagnostico;
        }

        $diagnostico['usando_padrao'] = 1;
        return $diagnostico;
    }

    private function removerAvatarAnteriorSeSubstituido($anterior, $novo)
    {
        $anteriorPath = $this->extrairAvatarPathValido($anterior);
        $novoPath = $this->extrairAvatarPathValido($novo);

        if ($anteriorPath === null || $novoPath === null || $anteriorPath === $novoPath) {
            return;
        }

        if (strpos($anteriorPath, '/uploads/tutor-norminha/avatar/') !== 0) {
            return;
        }

        $caminhoFisico = $this->resolverCaminhoAvatar($anteriorPath);
        if ($caminhoFisico !== null && is_file($caminhoFisico)) {
            @unlink($caminhoFisico);
        }
    }
}
