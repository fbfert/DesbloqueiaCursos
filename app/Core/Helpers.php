<?php

namespace App\Core;

class Helpers
{
    public static function decodeEditorHtml($value)
    {
        $texto = (string) $value;

        for ($i = 0; $i < 3; $i++) {
            $decodificado = html_entity_decode($texto, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($decodificado === $texto) {
                break;
            }
            $texto = $decodificado;
        }

        return str_replace(array("\xc2\xa0", '&#160;'), ' ', $texto);
    }

    public static function renderSafeHtml($value, $profile = 'basic')
    {
        $raw = (string) $value;
        if (trim($raw) === '') {
            return '';
        }

        $normalizado = self::decodeEditorHtml($raw);

        if (!preg_match('/<\s*\/?\s*[a-z][\s\S]*>/i', $normalizado)) {
            $normalizado = preg_replace("/\r\n?/", "\n", $normalizado);
            return nl2br(self::e($normalizado));
        }

        $sanitized = \App\Support\HtmlSanitizer::clean($normalizado, $profile);
        if (trim((string) $sanitized) !== '') {
            return $sanitized;
        }

        $texto = trim(strip_tags($normalizado));
        if ($texto === '') {
            return '';
        }

        $texto = preg_replace("/\r\n?/", "\n", $texto);
        return nl2br(self::e($texto));
    }

    public static function e($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    public static function normalizarTextoLms($value, $preservarQuebras = false)
    {
        $texto = (string) $value;

        for ($i = 0; $i < 3; $i++) {
            $decodificado = html_entity_decode($texto, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($decodificado === $texto) {
                break;
            }
            $texto = $decodificado;
        }

        $texto = str_replace(array("\xc2\xa0", '&#160;'), ' ', $texto);
        $texto = str_replace('Móduldo', 'Módulo', $texto);
        $texto = str_replace('Móduldoo', 'Módulo', $texto);
        $texto = str_replace('Moduldo', 'Módulo', $texto);

        if ($preservarQuebras) {
            $texto = preg_replace("/\r\n?/", "\n", $texto);
            $linhas = explode("\n", $texto);
            foreach ($linhas as &$linha) {
                $linha = preg_replace('/[ \t]+/u', ' ', trim($linha));
            }
            unset($linha);
            $texto = implode("\n", $linhas);
            $texto = preg_replace("/\n{3,}/", "\n\n", $texto);
            return trim($texto);
        }

        $texto = preg_replace('/\s+/u', ' ', $texto);
        return trim($texto);
    }

    public static function textoLms($value)
    {
        return htmlspecialchars(self::normalizarTextoLms($value, false), ENT_QUOTES, 'UTF-8');
    }

    public static function textoLmsMultilinha($value)
    {
        return htmlspecialchars(self::normalizarTextoLms($value, true), ENT_QUOTES, 'UTF-8');
    }

    public static function statusLms($status)
    {
        $status = (string) $status;
        $mapa = array(
            'nao_iniciado' => 'Não iniciado',
            'acessado' => 'Acessado',
            'em_andamento' => 'Em andamento',
            'concluido' => 'Concluído',
            'pendente' => 'Pendente',
            'aguardando_envio' => 'Aguardando envio',
            'reenviada' => 'Aguardando correção',
            'enviada' => 'Aguardando correção',
            'corrigido' => 'Corrigido',
            'corrigida' => 'Corrigida',
            'aguardando_correcao' => 'Aguardando correção',
            'pendente_correcao' => 'Aguardando correção',
            'reprovado' => 'Reprovado',
            'devolvida' => 'Devolvida',
            'aprovada' => 'Aprovada',
            'cancelada' => 'Cancelada',
            'publicado' => 'Publicado',
            'rascunho' => 'Rascunho',
            'oculto' => 'Oculto',
            'arquivado' => 'Arquivado',
            'ativo' => 'Ativo',
            'inativo' => 'Inativo',
            'certificado_emitido' => 'Certificado emitido',
        );

        if (isset($mapa[$status])) {
            return $mapa[$status];
        }

        $status = str_replace('_', ' ', $status);
        return $status !== '' ? ucwords($status) : '-';
    }

    public static function tipoConteudoLms($tipo)
    {
        $tipo = (string) $tipo;
        $mapa = array(
            'texto' => 'Texto',
            'arquivo' => 'Arquivo',
            'link' => 'Link',
            'video' => 'Vídeo',
            'avaliacao_textual' => 'Avaliação textual',
            'etiqueta' => 'Etiqueta',
            'quiz' => 'Quiz',
            'html' => 'HTML',
            'video_incorporado' => 'Vídeo incorporado',
        );

        if (isset($mapa[$tipo])) {
            return $mapa[$tipo];
        }

        $tipo = str_replace('_', ' ', $tipo);
        return $tipo !== '' ? ucwords($tipo) : '-';
    }

    /**
     * URL de acesso direto ao conteúdo do curso na área do aluno, montada a
     * partir da situação devolvida por InscricaoService::situacaoAlunoNoCurso().
     * Sem inscrição/curso identificados, cai na listagem de cursos do aluno.
     */
    public static function urlAcessoCursoAluno(array $situacao)
    {
        $inscricaoId = isset($situacao['inscricao_id']) ? (int) $situacao['inscricao_id'] : 0;
        $cursoId = isset($situacao['curso_id']) ? (int) $situacao['curso_id'] : 0;
        $turmaId = isset($situacao['turma_id']) ? (int) $situacao['turma_id'] : 0;

        if ($inscricaoId <= 0 || $cursoId <= 0) {
            return '/aluno/meus-cursos';
        }

        return '/aluno/curso/' . $inscricaoId . '/' . $cursoId . '/' . $turmaId;
    }

    public static function modalidadeCurso($modalidade)
    {
        $modalidade = (string) $modalidade;
        $mapa = array(
            'presencial' => 'Presencial',
            'online_ao_vivo' => 'On-line ao vivo',
            'sob_demanda' => 'Sob demanda',
            'hibrido' => 'Híbrido',
            'híbrido' => 'Híbrido',
            'ead' => 'EaD',
        );

        $chave = function_exists('mb_strtolower') ? mb_strtolower($modalidade, 'UTF-8') : strtolower($modalidade);

        if (isset($mapa[$chave])) {
            return $mapa[$chave];
        }

        $chave = str_replace('_', ' ', $chave);
        return $chave !== '' ? ucwords($chave) : '-';
    }

    public static function iconeArquivo($extensao)
    {
        $extensao = strtolower(trim((string) $extensao, '. '));
        $mapa = array(
            'pdf' => '📄',
            'doc' => '📝', 'docx' => '📝', 'odt' => '📝', 'rtf' => '📝',
            'xls' => '📊', 'xlsx' => '📊', 'csv' => '📊', 'ods' => '📊',
            'ppt' => '📽️', 'pptx' => '📽️', 'odp' => '📽️',
            'jpg' => '🖼️', 'jpeg' => '🖼️', 'png' => '🖼️', 'gif' => '🖼️', 'webp' => '🖼️', 'svg' => '🖼️',
            'zip' => '🗜️', 'rar' => '🗜️', '7z' => '🗜️',
            'mp3' => '🎵', 'wav' => '🎵', 'ogg' => '🎵',
            'mp4' => '🎬', 'mov' => '🎬', 'avi' => '🎬', 'mkv' => '🎬',
            'txt' => '📃',
        );

        return isset($mapa[$extensao]) ? $mapa[$extensao] : '📎';
    }

    /**
     * Reorganiza um bloco de $_FILES['campo'][...] de um <input type="file"
     * name="campo[]" multiple> (formato nativo do PHP: um array por
     * propriedade - name[], tmp_name[], error[] etc.) em uma lista de
     * arquivos individuais no formato ['name'=>..,'tmp_name'=>..,...],
     * pronta para FileStorageService::storeUploadedFile(). Ignora slots
     * vazios (nenhum arquivo selecionado naquela posição).
     */
    public static function normalizarUploadMultiplo($filesBlock)
    {
        if (!is_array($filesBlock) || !isset($filesBlock['name']) || !is_array($filesBlock['name'])) {
            return array();
        }

        $arquivos = array();
        foreach ($filesBlock['name'] as $indice => $nome) {
            $erro = isset($filesBlock['error'][$indice]) ? (int) $filesBlock['error'][$indice] : UPLOAD_ERR_NO_FILE;
            if ($erro === UPLOAD_ERR_NO_FILE || trim((string) $nome) === '') {
                continue;
            }
            $arquivos[] = array(
                'name' => $nome,
                'type' => isset($filesBlock['type'][$indice]) ? $filesBlock['type'][$indice] : null,
                'tmp_name' => isset($filesBlock['tmp_name'][$indice]) ? $filesBlock['tmp_name'][$indice] : null,
                'error' => $erro,
                'size' => isset($filesBlock['size'][$indice]) ? $filesBlock['size'][$indice] : null,
            );
        }

        return $arquivos;
    }

    public static function formatarTamanhoArquivo($bytes)
    {
        $bytes = (float) $bytes;
        if ($bytes <= 0) {
            return '';
        }

        $unidades = array('B', 'KB', 'MB', 'GB');
        $i = 0;
        while ($bytes >= 1024 && $i < count($unidades) - 1) {
            $bytes /= 1024;
            $i++;
        }

        $casas = ($i === 0) ? 0 : 1;
        return number_format($bytes, $casas, ',', '.') . ' ' . $unidades[$i];
    }

    public static function path($relativePath = '')
    {
        return BASE_PATH . ($relativePath ? '/' . ltrim($relativePath, '/\\') : '');
    }

    public static function url($path = '')
    {
        $config = require BASE_PATH . '/config/app.php';

        return rtrim($config['url'], '/') . '/' . ltrim($path, '/');
    }

    public static function isValidCssSpacingValue($value)
    {
        if (is_array($value) || is_object($value)) {
            return false;
        }

        $value = trim((string) $value);
        if ($value === '') {
            return false;
        }

        if (preg_match('/[;{}]/', $value)) {
            return false;
        }

        if (preg_match('/(?:url|expression|var)\s*\(/i', $value)) {
            return false;
        }

        $length = '(?:\\d+(?:\\.\\d+)?(?:px|rem|em|vw|%))';
        $clamp = '/^clamp\\(\\s*' . $length . '\\s*,\\s*' . $length . '\\s*,\\s*' . $length . '\\s*\\)$/i';
        $single = '/^' . $length . '$/i';

        return preg_match($single, $value) === 1 || preg_match($clamp, $value) === 1;
    }

    public static function sanitizeCssSpacingValue($value, $fallback = 'clamp(16px, 2vw, 24px)')
    {
        $fallback = trim((string) $fallback) !== '' ? trim((string) $fallback) : 'clamp(16px, 2vw, 24px)';
        $value = trim((string) $value);

        if ($value === '') {
            return $fallback;
        }

        if (!self::isValidCssSpacingValue($value)) {
            return $fallback;
        }

        return $value;
    }
}
