<?php

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Conversas da Norminha.
 *
 * REGRA DE OURO DESTE MODEL: nenhuma leitura acontece só por `uuid`. Toda
 * busca exige (uuid, usuario_id). O uuid é público — viaja para o browser e
 * volta —, então tratá-lo como credencial permitiria a um aluno abrir a
 * conversa de outro. A propriedade é verificada no WHERE, não em PHP depois.
 *
 * FUSO: todas as datas são gravadas com NOW(), o relógio do BANCO. O PHP desta
 * aplicação roda em UTC e o MySQL em UTC−3 (ver docs/2026-08-15-fuso-horario-php-mysql.md),
 * então misturar os dois relógios produz erro de três horas. Comparações de
 * tempo também ficam em SQL, nunca contra time() do PHP.
 */
class NorminhaConversa
{
    /**
     * Cria uma conversa e devolve id + uuid.
     *
     * O contexto acadêmico gravado é o que o servidor VALIDOU, nunca o hint
     * que o browser enviou.
     */
    public function criar($usuarioId, array $contexto = array())
    {
        $usuarioId = (int) $usuarioId;
        if ($usuarioId <= 0) {
            return null;
        }

        $uuid = $this->gerarUuid();

        $stmt = Database::connection()->prepare(
            'INSERT INTO norminha_conversas
                (uuid, usuario_id, inscricao_id, curso_evento_id, turma_id,
                 contexto, rota, status, ultima_mensagem_em, created_at, updated_at)
             VALUES
                (:uuid, :usuario_id, :inscricao_id, :curso_evento_id, :turma_id,
                 :contexto, :rota, :status, NOW(), NOW(), NOW())'
        );

        $stmt->execute(array(
            'uuid' => $uuid,
            'usuario_id' => $usuarioId,
            'inscricao_id' => $this->idOuNulo(isset($contexto['inscricao_id']) ? $contexto['inscricao_id'] : null),
            'curso_evento_id' => $this->idOuNulo(isset($contexto['curso_evento_id']) ? $contexto['curso_evento_id'] : null),
            'turma_id' => $this->idOuNulo(isset($contexto['turma_id']) ? $contexto['turma_id'] : null),
            'contexto' => $this->textoLimitado(isset($contexto['contexto']) ? $contexto['contexto'] : 'area_aluno', 40, 'area_aluno'),
            'rota' => $this->textoLimitado(isset($contexto['rota']) ? $contexto['rota'] : null, 255, null),
            'status' => 'ativa',
        ));

        return array(
            'id' => (int) Database::connection()->lastInsertId(),
            'uuid' => $uuid,
        );
    }

    /** Busca por uuid E usuário. Devolve null se o uuid for de outra pessoa. */
    public function buscarPorUuid($uuid, $usuarioId)
    {
        $uuid = trim((string) $uuid);
        $usuarioId = (int) $usuarioId;

        if ($uuid === '' || $usuarioId <= 0 || !$this->uuidValido($uuid)) {
            return null;
        }

        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM norminha_conversas
             WHERE uuid = :uuid
               AND usuario_id = :usuario_id
             LIMIT 1'
        );
        $stmt->execute(array('uuid' => $uuid, 'usuario_id' => $usuarioId));

        $linha = $stmt->fetch(PDO::FETCH_ASSOC);

        return $linha ?: null;
    }

    /** Conversas mais recentes do aluno, para retomar de onde parou. */
    public function listarRecentes($usuarioId, $limite = 5)
    {
        $usuarioId = (int) $usuarioId;
        if ($usuarioId <= 0) {
            return array();
        }

        // LIMIT não aceita placeholder com prepares emulados; o valor é
        // convertido para inteiro e limitado antes de entrar no SQL.
        $limite = max(1, min(50, (int) $limite));

        $stmt = Database::connection()->prepare(
            'SELECT id, uuid, inscricao_id, curso_evento_id, turma_id,
                    contexto, rota, status, ultima_mensagem_em
             FROM norminha_conversas
             WHERE usuario_id = :usuario_id
               AND status = "ativa"
             ORDER BY ultima_mensagem_em DESC, id DESC
             LIMIT ' . $limite
        );
        $stmt->execute(array('usuario_id' => $usuarioId));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Atualiza o contexto acadêmico validado.
     *
     * O usuario_id entra no WHERE: sem ele, um id de conversa vazado permitiria
     * reescrever o contexto de outra pessoa.
     */
    public function atualizarContexto($conversaId, $usuarioId, array $contexto)
    {
        $conversaId = (int) $conversaId;
        $usuarioId = (int) $usuarioId;
        if ($conversaId <= 0 || $usuarioId <= 0) {
            return false;
        }

        $stmt = Database::connection()->prepare(
            'UPDATE norminha_conversas
                SET inscricao_id = :inscricao_id,
                    curso_evento_id = :curso_evento_id,
                    turma_id = :turma_id,
                    contexto = :contexto,
                    rota = :rota,
                    updated_at = NOW()
              WHERE id = :id
                AND usuario_id = :usuario_id
              LIMIT 1'
        );

        $stmt->execute(array(
            'inscricao_id' => $this->idOuNulo(isset($contexto['inscricao_id']) ? $contexto['inscricao_id'] : null),
            'curso_evento_id' => $this->idOuNulo(isset($contexto['curso_evento_id']) ? $contexto['curso_evento_id'] : null),
            'turma_id' => $this->idOuNulo(isset($contexto['turma_id']) ? $contexto['turma_id'] : null),
            'contexto' => $this->textoLimitado(isset($contexto['contexto']) ? $contexto['contexto'] : 'area_aluno', 40, 'area_aluno'),
            'rota' => $this->textoLimitado(isset($contexto['rota']) ? $contexto['rota'] : null, 255, null),
            'id' => $conversaId,
            'usuario_id' => $usuarioId,
        ));

        return $stmt->rowCount() > 0;
    }

    /** Marca atividade na conversa. Chamado a cada mensagem persistida. */
    public function tocarUltimaMensagem($conversaId)
    {
        $conversaId = (int) $conversaId;
        if ($conversaId <= 0) {
            return false;
        }

        $stmt = Database::connection()->prepare(
            'UPDATE norminha_conversas
                SET ultima_mensagem_em = NOW(),
                    updated_at = NOW()
              WHERE id = :id
              LIMIT 1'
        );
        $stmt->execute(array('id' => $conversaId));

        return $stmt->rowCount() > 0;
    }

    /**
     * Grava o resumo da conversa (janela de memória, Etapa 13).
     *
     * O resumo guarda contexto conversacional, nunca dado operacional: progresso,
     * nota e certificado são sempre reconsultados, não lembrados.
     */
    public function atualizarResumo($conversaId, $usuarioId, $resumo)
    {
        $conversaId = (int) $conversaId;
        $usuarioId = (int) $usuarioId;
        if ($conversaId <= 0 || $usuarioId <= 0) {
            return false;
        }

        $stmt = Database::connection()->prepare(
            'UPDATE norminha_conversas
                SET resumo = :resumo,
                    updated_at = NOW()
              WHERE id = :id
                AND usuario_id = :usuario_id
              LIMIT 1'
        );
        $stmt->execute(array(
            'resumo' => $resumo === null ? null : (string) $resumo,
            'id' => $conversaId,
            'usuario_id' => $usuarioId,
        ));

        return $stmt->rowCount() > 0;
    }

    /** UUID v4 a partir de random_bytes (CSPRNG). Não existe helper no projeto. */
    public function gerarUuid()
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40); // versão 4
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80); // variante RFC 4122

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    /** Formato de UUID; barra lixo antes de tocar o banco. */
    public function uuidValido($uuid)
    {
        return (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            (string) $uuid
        );
    }

    private function idOuNulo($valor)
    {
        $valor = (int) $valor;

        return $valor > 0 ? $valor : null;
    }

    private function textoLimitado($valor, $maximo, $padrao)
    {
        $valor = trim((string) $valor);
        if ($valor === '') {
            return $padrao;
        }

        return function_exists('mb_substr') ? mb_substr($valor, 0, $maximo, 'UTF-8') : substr($valor, 0, $maximo);
    }
}
