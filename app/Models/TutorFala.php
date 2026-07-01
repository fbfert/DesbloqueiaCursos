<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class TutorFala
{
    public function buscarAtiva(array $contexto)
    {
        $contextoNome = isset($contexto['contexto']) ? trim((string) $contexto['contexto']) : '';
        if ($contextoNome === '') {
            return null;
        }

        $rotaNormalizada = $this->normalizarRota(isset($contexto['rota']) ? $contexto['rota'] : '');
        $cursoId = $this->normalizarInteiro(isset($contexto['curso_id']) ? $contexto['curso_id'] : null);
        $moduloId = $this->normalizarInteiro(isset($contexto['modulo_id']) ? $contexto['modulo_id'] : null);
        $aulaId = $this->normalizarInteiro(isset($contexto['aula_id']) ? $contexto['aula_id'] : null);

        if ($aulaId > 0) {
            $row = $this->buscarPorColunaInteira($contextoNome, 'aula_id', $aulaId);
            if ($row) {
                return $row;
            }
        }

        if ($moduloId > 0) {
            $row = $this->buscarPorColunaInteira($contextoNome, 'modulo_id', $moduloId);
            if ($row) {
                return $row;
            }
        }

        if ($cursoId > 0) {
            $row = $this->buscarPorColunaInteira($contextoNome, 'curso_id', $cursoId);
            if ($row) {
                return $row;
            }
        }

        if ($rotaNormalizada !== '') {
            $row = $this->buscarPorRotaExata($contextoNome, $rotaNormalizada);
            if ($row) {
                return $row;
            }

            $row = $this->buscarPorRotaWildcard($contextoNome, $rotaNormalizada);
            if ($row) {
                return $row;
            }
        }

        return $this->buscarPorContexto($contextoNome);
    }

    public function listarAdmin(array $filtros = array())
    {
        $sql = array(
            'SELECT *',
            'FROM tutor_falas',
            'WHERE 1 = 1',
        );
        $params = array();

        $busca = isset($filtros['busca']) ? trim((string) $filtros['busca']) : '';
        if ($busca !== '') {
            $sql[] = 'AND (titulo LIKE :busca OR texto LIKE :busca)';
            $params['busca'] = '%' . $busca . '%';
        }

        $contexto = isset($filtros['contexto']) ? trim((string) $filtros['contexto']) : '';
        if ($contexto !== '') {
            $sql[] = 'AND contexto = :contexto';
            $params['contexto'] = $contexto;
        }

        if (isset($filtros['ativo']) && $filtros['ativo'] !== '' && $filtros['ativo'] !== null) {
            $sql[] = 'AND ativo = :ativo';
            $params['ativo'] = (int) $filtros['ativo'];
        }

        $sql[] = 'ORDER BY ativo DESC, id DESC';

        $stmt = Database::connection()->prepare(implode("\n", $sql));
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarPorId($id)
    {
        $id = (int) $id;
        if ($id <= 0) {
            return null;
        }

        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM tutor_falas
             WHERE id = :id
             LIMIT 1'
        );
        $stmt->execute(array('id' => $id));

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function criar(array $data)
    {
        $payload = $this->normalizarPayload($data);

        $sql = 'INSERT INTO tutor_falas
                (titulo, contexto, rota, curso_id, modulo_id, aula_id, texto, audio_url, estado_avatar, ativo, criado_em, atualizado_em)
                VALUES
                (:titulo, :contexto, :rota, :curso_id, :modulo_id, :aula_id, :texto, :audio_url, :estado_avatar, :ativo, NOW(), NULL)';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($payload);

        return (int) Database::connection()->lastInsertId();
    }

    public function atualizar($id, array $data)
    {
        $id = (int) $id;
        if ($id <= 0) {
            return false;
        }

        $payload = $this->normalizarPayload($data, false);
        if (empty($payload)) {
            return true;
        }

        $sets = array();
        foreach ($payload as $coluna => $valor) {
            $sets[] = $coluna . ' = :' . $coluna;
        }

        $sql = 'UPDATE tutor_falas SET ' . implode(', ', $sets) . ', atualizado_em = NOW() WHERE id = :id';
        $stmt = Database::connection()->prepare($sql);
        $payload['id'] = $id;

        return (bool) $stmt->execute($payload);
    }

    public function atualizarAudioUrl($id, $audioUrl)
    {
        $id = (int) $id;
        if ($id <= 0) {
            return false;
        }

        $stmt = Database::connection()->prepare(
            'UPDATE tutor_falas
             SET audio_url = :audio_url,
                 atualizado_em = NOW()
             WHERE id = :id'
        );

        return (bool) $stmt->execute(array(
            'id' => $id,
            'audio_url' => $audioUrl,
        ));
    }

    public function atualizarStatus($id, $ativo)
    {
        $id = (int) $id;
        if ($id <= 0) {
            return false;
        }

        $stmt = Database::connection()->prepare(
            'UPDATE tutor_falas
             SET ativo = :ativo,
                 atualizado_em = NOW()
             WHERE id = :id'
        );

        return (bool) $stmt->execute(array(
            'id' => $id,
            'ativo' => (int) ((int) $ativo > 0 ? 1 : 0),
        ));
    }

    private function buscarPorColunaInteira($contexto, $coluna, $valor)
    {
        $sql = 'SELECT *
                FROM tutor_falas
                WHERE ativo = 1
                  AND contexto = :contexto
                  AND ' . $coluna . ' = :' . $coluna . '
                ORDER BY id DESC
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(array(
            'contexto' => $contexto,
            $coluna => (int) $valor,
        ));

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function buscarPorRotaExata($contexto, $rota)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM tutor_falas
             WHERE ativo = 1
               AND contexto = :contexto
               AND rota = :rota
             ORDER BY id DESC
             LIMIT 1'
        );
        $stmt->execute(array(
            'contexto' => $contexto,
            'rota' => $rota,
        ));

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function buscarPorRotaWildcard($contexto, $rota)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM tutor_falas
             WHERE ativo = 1
               AND contexto = :contexto
               AND rota IS NOT NULL
               AND rota <> ""
               AND rota LIKE :tem_wildcard
             ORDER BY id DESC'
        );
        $stmt->execute(array(
            'contexto' => $contexto,
            'tem_wildcard' => '%*%',
        ));

        $melhor = null;
        $melhorPontuacao = -1;

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $padrao = isset($row['rota']) ? $this->normalizarRota((string) $row['rota']) : '';
            if ($padrao === '' || strpos($padrao, '*') === false) {
                continue;
            }

            if (!$this->rotaWildCardCombina($padrao, $rota)) {
                continue;
            }

            $pontuacao = $this->pontuarWildcard($padrao, (int) $row['id']);
            if ($pontuacao > $melhorPontuacao) {
                $melhorPontuacao = $pontuacao;
                $melhor = $row;
            }
        }

        return $melhor ?: null;
    }

    private function buscarPorContexto($contexto)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM tutor_falas
             WHERE ativo = 1
               AND contexto = :contexto
               AND (rota IS NULL OR rota = "")
             ORDER BY id DESC
             LIMIT 1'
        );
        $stmt->execute(array('contexto' => $contexto));

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function rotaWildCardCombina($padrao, $rota)
    {
        $padrao = $this->normalizarRota($padrao);
        $rota = $this->normalizarRota($rota);

        if ($padrao === '' || $rota === '') {
            return false;
        }

        if (strpos($padrao, '*') === false) {
            return $padrao === $rota;
        }

        $regex = '#^' . str_replace('\*', '.*', preg_quote($padrao, '#')) . '$#i';
        return (bool) preg_match($regex, $rota);
    }

    private function pontuarWildcard($padrao, $id)
    {
        $padrao = $this->normalizarRota($padrao);
        $trechoFixo = strlen(str_replace('*', '', $padrao));
        $segmentos = substr_count($padrao, '/');
        $curinga = substr_count($padrao, '*');

        return ($trechoFixo * 1000) + ($segmentos * 10) - ($curinga * 100) + (int) $id;
    }

    private function normalizarPayload(array $data, $incluirId = true)
    {
        $payload = array(
            'titulo' => trim((string) (isset($data['titulo']) ? $data['titulo'] : '')),
            'contexto' => trim((string) (isset($data['contexto']) ? $data['contexto'] : '')),
            'rota' => $this->normalizarRotaSalva(isset($data['rota']) ? $data['rota'] : null),
            'curso_id' => $this->normalizarInteiroOuNull(isset($data['curso_id']) ? $data['curso_id'] : null),
            'modulo_id' => $this->normalizarInteiroOuNull(isset($data['modulo_id']) ? $data['modulo_id'] : null),
            'aula_id' => $this->normalizarInteiroOuNull(isset($data['aula_id']) ? $data['aula_id'] : null),
            'texto' => trim((string) (isset($data['texto']) ? $data['texto'] : '')),
            'audio_url' => $this->normalizarAudioUrlInterna(isset($data['audio_url']) ? $data['audio_url'] : null),
            'estado_avatar' => $this->normalizarEstadoAvatar(isset($data['estado_avatar']) ? $data['estado_avatar'] : null),
            'ativo' => !empty($data['ativo']) ? 1 : 0,
        );

        if ($incluirId && isset($data['id']) && (int) $data['id'] > 0) {
            $payload['id'] = (int) $data['id'];
        }

        return $payload;
    }

    private function normalizarRotaSalva($rota)
    {
        $rota = trim((string) $rota);
        if ($rota === '') {
            return null;
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

    private function normalizarAudioUrlInterna($audioUrl)
    {
        $audioUrl = trim((string) $audioUrl);
        if ($audioUrl === '') {
            return null;
        }

        if (preg_match('#^(https?:)?//#i', $audioUrl) || stripos($audioUrl, 'javascript:') === 0 || stripos($audioUrl, 'data:') === 0) {
            return null;
        }

        if (strpos($audioUrl, '/uploads/tutor-norminha/audio/') !== 0 && strpos($audioUrl, '/assets/') !== 0) {
            return null;
        }

        if (strpos($audioUrl, '..') !== false || strpos($audioUrl, '?') !== false || strpos($audioUrl, '#') !== false) {
            return null;
        }

        return $audioUrl;
    }

    private function normalizarEstadoAvatar($estadoAvatar)
    {
        $estadoAvatar = strtolower(trim((string) $estadoAvatar));
        $permitidos = array('speaking', 'explaining', 'attention', 'celebrating', 'doubt');

        if ($estadoAvatar === '' || !in_array($estadoAvatar, $permitidos, true)) {
            return 'speaking';
        }

        return $estadoAvatar;
    }

    private function normalizarInteiro($valor)
    {
        if ($valor === null || $valor === '') {
            return 0;
        }

        if (!is_numeric($valor)) {
            return 0;
        }

        $valor = (int) $valor;
        return $valor > 0 ? $valor : 0;
    }

    private function normalizarInteiroOuNull($valor)
    {
        $valor = $this->normalizarInteiro($valor);
        return $valor > 0 ? $valor : null;
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
