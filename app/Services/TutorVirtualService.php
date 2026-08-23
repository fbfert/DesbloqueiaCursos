<?php

namespace App\Services;

use App\Models\TutorConfiguracao;
use App\Models\TutorFala;

class TutorVirtualService
{
    private $configuracaoModel;
    private $falaModel;

    public function __construct()
    {
        $this->configuracaoModel = new TutorConfiguracao();
        $this->falaModel = new TutorFala();
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

    public function componenteParaLayout($requestPath, array $query = array())
    {
        $contexto = $this->resolverContexto($requestPath, $query);
        if (empty($contexto)) {
            return null;
        }

        $configuracoes = $this->configuracoes();
        if (empty($configuracoes['tutor_ativo'])) {
            return null;
        }

        if (!$this->contextoHabilitado($contexto['contexto'], $configuracoes)) {
            return null;
        }

        $fala = $this->falaModel->buscarAtiva($contexto);

        // Sem fala cadastrada, o componente costumava simplesmente nao aparecer.
        // Isso fazia sentido enquanto ele era so um aviso: nada a dizer, nada a
        // mostrar. Como chat, a regra se inverte — a Norminha some exatamente
        // onde o aluno mais precisa dela, e so volta se um administrador lembrar
        // de cadastrar uma fala para cada contexto novo.
        //
        // Nas areas de estudo usamos uma saudacao padrao. Fora delas o
        // comportamento antigo continua: sem fala, sem componente.
        if (!$fala) {
            if (!in_array($contexto['contexto'], array('area_aluno', 'aula', 'avaliacao', 'curso'), true)) {
                return null;
            }

            $fala = array(
                'titulo' => null,
                'texto' => 'Olá! Posso estudar com você. Me pergunte sobre o seu progresso, '
                    . 'onde você parou ou o seu certificado.',
                'audio_url' => null,
                'estado_avatar' => 'speaking',
            );
        }

        $audioUrl = isset($fala['audio_url']) ? trim((string) $fala['audio_url']) : '';
        $audioUrlPublica = $this->normalizarAudioUrl($audioUrl);
        $audioDisponivel = $audioUrlPublica !== '';

        return array(
            'titulo' => $this->tituloComFallback(isset($fala['titulo']) ? $fala['titulo'] : null, isset($configuracoes['tutor_titulo_padrao']) ? $configuracoes['tutor_titulo_padrao'] : 'Norminha'),
            'texto' => isset($fala['texto']) ? (string) $fala['texto'] : '',
            'audio_url' => $audioDisponivel ? $audioUrlPublica : null,
            'audio_existe' => $audioDisponivel ? 1 : 0,
            'estado_avatar' => $this->normalizarEstadoAvatar(isset($fala['estado_avatar']) ? $fala['estado_avatar'] : null),
            'texto_botao' => isset($configuracoes['tutor_texto_botao']) ? (string) $configuracoes['tutor_texto_botao'] : 'Ouvir orientação',
            'titulo_padrao' => isset($configuracoes['tutor_titulo_padrao']) ? (string) $configuracoes['tutor_titulo_padrao'] : 'Norminha',
            'avatar_idle' => $this->avatarPublicUrl($this->obterAvatarConfiguracao($configuracoes, array('tutor_avatar_idle', 'avatar_parado_url')), '/assets/norminha/norminha-idle.webp'),
            'avatar_speaking' => $this->avatarPublicUrl($this->obterAvatarConfiguracao($configuracoes, array('tutor_avatar_speaking', 'avatar_falando_url')), '/assets/norminha/norminha-speaking.webp'),
            'ttl_fechamento_horas' => isset($configuracoes['tutor_ttl_fechamento_horas']) ? (int) $configuracoes['tutor_ttl_fechamento_horas'] : 24,
            'minimizado_padrao' => !empty($configuracoes['tutor_minimizado_padrao']) ? 1 : 0,
            'contexto' => $contexto['contexto'],
            'rota' => $contexto['rota'],
        );
    }

    public function configuracoes()
    {
        $defaults = array(
            'tutor_ativo' => '1',
            'tutor_home' => '1',
            'tutor_area_aluno' => '1',
            'tutor_cursos' => '1',
            'tutor_checkout' => '0',
            'tutor_minimizado_padrao' => '0',
            'tutor_ttl_fechamento_horas' => '24',
            'tutor_texto_botao' => 'Ouvir orientação',
            'tutor_titulo_padrao' => 'Norminha',
            'tutor_avatar_idle' => '/assets/norminha/norminha-idle.webp',
            'tutor_avatar_speaking' => '/assets/norminha/norminha-speaking.webp',
            'avatar_parado_url' => '/assets/norminha/norminha-idle.webp',
            'avatar_falando_url' => '/assets/norminha/norminha-speaking.webp',
        );

        $configuracoes = array_merge($defaults, $this->configuracaoModel->allIndexed());

        $configuracoes['tutor_ativo'] = $this->normalizarConfigBool(isset($configuracoes['tutor_ativo']) ? $configuracoes['tutor_ativo'] : 1, 1);
        $configuracoes['tutor_home'] = $this->normalizarConfigBool(isset($configuracoes['tutor_home']) ? $configuracoes['tutor_home'] : 1, 1);
        $configuracoes['tutor_area_aluno'] = $this->normalizarConfigBool(isset($configuracoes['tutor_area_aluno']) ? $configuracoes['tutor_area_aluno'] : 1, 1);
        $configuracoes['tutor_cursos'] = $this->normalizarConfigBool(isset($configuracoes['tutor_cursos']) ? $configuracoes['tutor_cursos'] : 1, 1);
        $configuracoes['tutor_checkout'] = $this->normalizarConfigBool(isset($configuracoes['tutor_checkout']) ? $configuracoes['tutor_checkout'] : 0, 0);
        $configuracoes['tutor_minimizado_padrao'] = $this->normalizarConfigBool(isset($configuracoes['tutor_minimizado_padrao']) ? $configuracoes['tutor_minimizado_padrao'] : 0, 0);
        $configuracoes['tutor_ttl_fechamento_horas'] = $this->normalizarConfigTtl(isset($configuracoes['tutor_ttl_fechamento_horas']) ? $configuracoes['tutor_ttl_fechamento_horas'] : 24, 24);
        $configuracoes['tutor_texto_botao'] = $this->normalizarConfigTexto(isset($configuracoes['tutor_texto_botao']) ? $configuracoes['tutor_texto_botao'] : 'Ouvir orientação', 'Ouvir orientação', 80);
        $configuracoes['tutor_titulo_padrao'] = $this->normalizarConfigTexto(isset($configuracoes['tutor_titulo_padrao']) ? $configuracoes['tutor_titulo_padrao'] : 'Norminha', 'Norminha', 80);
        $configuracoes['tutor_avatar_idle'] = $this->normalizarConfigAvatar($this->obterAvatarConfiguracao($configuracoes, array('tutor_avatar_idle', 'avatar_parado_url')), '/assets/norminha/norminha-idle.webp');
        $configuracoes['avatar_parado_url'] = $configuracoes['tutor_avatar_idle'];
        $configuracoes['tutor_avatar_speaking'] = $this->normalizarConfigAvatar($this->obterAvatarConfiguracao($configuracoes, array('tutor_avatar_speaking', 'avatar_falando_url')), '/assets/norminha/norminha-speaking.webp');
        $configuracoes['avatar_falando_url'] = $configuracoes['tutor_avatar_speaking'];

        return $configuracoes;
    }

    private function contextoHabilitado($contexto, array $configuracoes)
    {
        $mapa = array(
            'home' => 'tutor_home',
            'area_aluno' => 'tutor_area_aluno',
            'cursos' => 'tutor_cursos',
            'checkout' => 'tutor_checkout',
        );

        if (!isset($mapa[$contexto])) {
            return true;
        }

        return !empty($configuracoes[$mapa[$contexto]]);
    }

    private function resolverContexto($requestPath, array $query)
    {
        $rota = $this->normalizarRota($requestPath);

        if ($rota === '/admin' || strpos($rota, '/admin/') === 0 || $rota === '/professor' || strpos($rota, '/professor/') === 0) {
            return array();
        }

        $contexto = 'publico';

        // Rotas V2 primeiro: a area do aluno viva e a V2 (HOME_VERSION=v2), e
        // ate 22/08/2026 nenhuma delas era reconhecida aqui — todas caiam em
        // 'publico', contexto para o qual nao existe fala cadastrada. Na
        // pratica, a Norminha nunca aparecia na area do aluno.
        if ($rota === '/v2' || $rota === '/v2/') {
            return $this->contextoResolvido('home', $rota, $query);
        }
        if (strpos($rota, '/v2/quiz') === 0 || strpos($rota, '/v2/atividade') === 0) {
            return $this->contextoResolvido('avaliacao', $rota, $query);
        }
        if (strpos($rota, '/v2/aula') === 0) {
            return $this->contextoResolvido('aula', $rota, $query);
        }
        if (strpos($rota, '/v2/aluno') === 0 || strpos($rota, '/v2/minha-conta') === 0) {
            return $this->contextoResolvido('area_aluno', $rota, $query);
        }
        if (strpos($rota, '/v2/catalogo') === 0 || strpos($rota, '/v2/categorias') === 0) {
            return $this->contextoResolvido('cursos', $rota, $query);
        }
        if (strpos($rota, '/v2/curso') === 0) {
            return $this->contextoResolvido('curso', $rota, $query);
        }
        if (strpos($rota, '/v2/checkout') === 0) {
            return $this->contextoResolvido('checkout', $rota, $query);
        }
        if (strpos($rota, '/v2/') === 0) {
            return $this->contextoResolvido('institucional', $rota, $query);
        }

        if ($rota === '/') {
            $contexto = 'home';
        } elseif ($rota === '/como-funciona' || $rota === '/sobre' || $rota === '/contato') {
            $contexto = 'institucional';
        } elseif ($rota === '/checkout' || strpos($rota, '/checkout/') === 0) {
            $contexto = 'checkout';
        } elseif ($rota === '/cursos' || strpos($rota, '/cursos/') === 0 || strpos($rota, '/categorias') === 0) {
            $contexto = 'cursos';
        } elseif ($rota === '/curso' || strpos($rota, '/curso/') === 0) {
            $contexto = 'curso';
        } elseif ($rota === '/meus-cursos' || $rota === '/area-curso' || strpos($rota, '/area-curso/') === 0) {
            $contexto = 'area_aluno';
        } elseif ($rota === '/aluno/cursos') {
            $contexto = 'area_aluno';
        } elseif (strpos($rota, '/aluno/curso/') === 0) {
            $contexto = 'aula';
        } elseif (strpos($rota, '/aluno/') === 0) {
            $contexto = 'area_aluno';
        }

        return $this->contextoResolvido($contexto, $rota, $query);
    }

    /** Monta o contexto com os ids que a rota e a query permitem resolver. */
    private function contextoResolvido($contexto, $rota, array $query)
    {
        return array(
            'contexto' => $contexto,
            'rota' => $rota,
            'curso_id' => $this->resolverInteiroContexto($query, $rota, array('curso_id', 'curso_evento_id'), '/cursos/detalhe'),
            'modulo_id' => $this->resolverModuloId($query, $rota),
            'aula_id' => $this->resolverAulaId($query, $rota),
        );
    }

    private function resolverInteiroContexto(array $query, $rota, array $chaves, $rotaPadrao = null)
    {
        foreach ($chaves as $chave) {
            if (isset($query[$chave]) && (int) $query[$chave] > 0) {
                return (int) $query[$chave];
            }
        }

        if ($rotaPadrao !== null && $rota === $rotaPadrao && isset($query['curso_id']) && (int) $query['curso_id'] > 0) {
            return (int) $query['curso_id'];
        }

        if (preg_match('#^/aluno/curso/(\d+)/(\d+)/(\d+)(?:/.*)?$#', $rota, $matches)) {
            return isset($matches[2]) ? (int) $matches[2] : 0;
        }

        if (preg_match('#^/curso/(\d+)(?:/.*)?$#', $rota, $matches)) {
            return isset($matches[1]) ? (int) $matches[1] : 0;
        }

        return 0;
    }

    private function resolverModuloId(array $query, $rota)
    {
        if (isset($query['modulo_id']) && (int) $query['modulo_id'] > 0) {
            return (int) $query['modulo_id'];
        }

        if (isset($query['conteudo_modulo_id']) && (int) $query['conteudo_modulo_id'] > 0) {
            return (int) $query['conteudo_modulo_id'];
        }

        if (preg_match('#^/aluno/curso/(\d+)/(\d+)/(\d+)/modulo/(\d+)(?:/.*)?$#', $rota, $matches)) {
            return isset($matches[4]) ? (int) $matches[4] : 0;
        }

        if (preg_match('#^/curso/(\d+)/modulo/(\d+)(?:/.*)?$#', $rota, $matches)) {
            return isset($matches[2]) ? (int) $matches[2] : 0;
        }

        return 0;
    }

    private function resolverAulaId(array $query, $rota)
    {
        if (isset($query['aula_id']) && (int) $query['aula_id'] > 0) {
            return (int) $query['aula_id'];
        }

        if (isset($query['conteudo_id']) && (int) $query['conteudo_id'] > 0) {
            return (int) $query['conteudo_id'];
        }

        if (isset($query['item_id']) && (int) $query['item_id'] > 0) {
            return (int) $query['item_id'];
        }

        if (isset($query['id']) && (int) $query['id'] > 0) {
            return (int) $query['id'];
        }

        if (preg_match('#^/aluno/curso/(\d+)/(\d+)/(\d+)/modulo/(\d+)/conteudo/(\d+)(?:/.*)?$#', $rota, $matches)) {
            return isset($matches[5]) ? (int) $matches[5] : 0;
        }

        if (preg_match('#^/curso/(\d+)/modulo/(\d+)/conteudo/(\d+)(?:/.*)?$#', $rota, $matches)) {
            return isset($matches[3]) ? (int) $matches[3] : 0;
        }

        return 0;
    }

    private function normalizarAudioUrl($audioUrl)
    {
        $audioUrl = trim((string) $audioUrl);
        if ($audioUrl === '') {
            return '';
        }

        if (preg_match('#^(https?:)?//#i', $audioUrl) || stripos($audioUrl, 'javascript:') === 0 || stripos($audioUrl, 'data:') === 0) {
            return '';
        }

        if (strpos($audioUrl, '/uploads/tutor-norminha/audio/') !== 0) {
            return '';
        }

        if (strpos($audioUrl, '..') !== false || strpos($audioUrl, '?') !== false || strpos($audioUrl, '#') !== false) {
            return '';
        }

        if (!$this->audioArquivoExiste($audioUrl)) {
            return '';
        }

        return $audioUrl;
    }

    private function normalizarEstadoAvatar($estadoAvatar)
    {
        $estadoAvatar = strtolower(trim((string) $estadoAvatar));
        $permitidos = array('speaking', 'explaining', 'attention', 'celebrating', 'doubt');

        if (in_array($estadoAvatar, $permitidos, true)) {
            return $estadoAvatar;
        }

        return 'speaking';
    }

    private function normalizarConfigBool($valor, $padrao = 0)
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

    private function normalizarConfigTtl($valor, $padrao = 24)
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

    private function normalizarConfigTexto($valor, $padrao, $maximo)
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

    private function normalizarConfigAvatar($valor, $padrao)
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

    private function avatarPublicUrl($valor, $fallback)
    {
        $valor = trim((string) $valor);
        $fallback = trim((string) $fallback);

        if ($valor !== '' && $this->avatarPathValido($valor) && $this->avatarArquivoExiste($valor)) {
            return $this->adicionarVersaoPublicUrl($this->extrairAvatarPathValido($valor));
        }

        if ($fallback !== '' && $this->avatarPathValido($fallback) && $this->avatarArquivoExiste($fallback)) {
            return $this->adicionarVersaoPublicUrl($this->extrairAvatarPathValido($fallback));
        }

        return null;
    }

    private function avatarArquivoExiste($valor)
    {
        $valor = $this->extrairAvatarPathValido($valor);
        if ($valor === null) {
            return false;
        }

        if (strpos($valor, '/uploads/tutor-norminha/avatar/') === 0) {
            $caminho = $this->publicPathFor($valor);
        } elseif (strpos($valor, '/assets/norminha/') === 0) {
            $caminho = $this->publicPathFor($valor);
        } else {
            $caminho = null;
        }

        return $caminho !== null && is_file($caminho) && is_readable($caminho) && (int) @filesize($caminho) > 0;
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

        $path = preg_split('/[?#]/', $valor, 2)[0];
        if (!is_string($path) || $path === '') {
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

    private function adicionarVersaoPublicUrl($publicUrl)
    {
        $publicUrl = trim((string) $publicUrl);
        if ($publicUrl === '') {
            return null;
        }

        $caminhoFisico = null;
        if (strpos($publicUrl, '/uploads/tutor-norminha/avatar/') === 0) {
            $caminhoFisico = $this->publicPathFor($publicUrl);
        } elseif (strpos($publicUrl, '/assets/norminha/') === 0) {
            $caminhoFisico = $this->publicPathFor($publicUrl);
        }

        if ($caminhoFisico === null || !is_file($caminhoFisico)) {
            return $publicUrl;
        }

        $versao = @filemtime($caminhoFisico);
        if (!$versao) {
            return $publicUrl;
        }

        return $publicUrl . '?v=' . (int) $versao;
    }

    private function obterAvatarConfiguracao(array $configuracoes, array $chaves)
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

    private function audioArquivoExiste($audioUrl)
    {
        $audioUrl = trim((string) $audioUrl);
        if ($audioUrl === '') {
            return false;
        }

        if (strpos($audioUrl, '/uploads/tutor-norminha/audio/') !== 0) {
            return false;
        }

        if (strpos($audioUrl, '..') !== false || strpos($audioUrl, '?') !== false || strpos($audioUrl, '#') !== false) {
            return false;
        }

        $caminhoFisico = $this->publicPathFor($audioUrl);
        return $caminhoFisico !== null && is_file($caminhoFisico);
    }

    private function tituloComFallback($titulo, $padrao)
    {
        $titulo = trim(strip_tags((string) $titulo));
        if ($titulo === '') {
            $titulo = trim(strip_tags((string) $padrao));
        }

        return $titulo !== '' ? $titulo : 'Norminha';
    }

    private function normalizarRota($rota)
    {
        $rota = trim((string) $rota);
        if ($rota === '') {
            return '/';
        }

        $rota = str_replace('\\', '/', $rota);
        if (strpos($rota, '/') !== 0) {
            $rota = '/' . $rota;
        }

        if ($rota !== '/') {
            $rota = rtrim($rota, '/');
        }

        return function_exists('mb_strtolower') ? mb_strtolower($rota, 'UTF-8') : strtolower($rota);
    }
}
