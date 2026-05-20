<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Models\ConteudoAvaliacaoEntrega;
use App\Models\ConteudoAvaliacaoTextual;
use App\Models\ConteudoItem;
use App\Models\ConteudoLogAluno;
use App\Models\ConteudoProgressoAluno;
use App\Models\CursoEvento;
use App\Models\Inscricao;
use Exception;
use PDO;

class ConteudoAvaliacaoTextualService
{
    private const STATUS_VALIDOS = array('enviada', 'reenviada', 'corrigida', 'devolvida', 'aprovada', 'reprovada', 'cancelada');

    private $avaliacaoModel;
    private $entregaModel;
    private $itemModel;
    private $logModel;
    private $cursoModel;
    private $progressoModel;
    private $inscricaoModel;

    public function __construct()
    {
        $this->avaliacaoModel = new ConteudoAvaliacaoTextual();
        $this->entregaModel = new ConteudoAvaliacaoEntrega();
        $this->itemModel = new ConteudoItem();
        $this->logModel = new ConteudoLogAluno();
        $this->cursoModel = new CursoEvento();
        $this->progressoModel = new ConteudoProgressoAluno();
        $this->inscricaoModel = new Inscricao();
    }

    public function enviarResposta($dados)
    {
        $dados = (array) $dados;
        $itemId = (int) ($dados['item_id'] ?? 0);
        $avaliacaoId = (int) ($dados['avaliacao_id'] ?? 0);
        $cursoEventoId = (int) ($dados['curso_evento_id'] ?? 0);
        $alunoId = (int) ($dados['aluno_id'] ?? 0);
        $inscricaoId = (int) ($dados['inscricao_id'] ?? 0);
        $turmaId = isset($dados['turma_id']) && $dados['turma_id'] !== '' ? (int) $dados['turma_id'] : null;
        $resposta = trim((string) ($dados['resposta'] ?? ''));

        if ($itemId <= 0 || $cursoEventoId <= 0 || $alunoId <= 0 || $inscricaoId <= 0) {
            return array('ok' => false, 'message' => 'Dados invÃ¡lidos para envio da avaliaÃ§Ã£o textual.');
        }
        $respostaTamanho = function_exists('mb_strlen') ? mb_strlen($resposta) : strlen($resposta);
        if ($resposta === '' || $respostaTamanho < 3) {
            return array('ok' => false, 'message' => 'Informe uma resposta com pelo menos 3 caracteres.');
        }
        if ($respostaTamanho > 50000) {
            return array('ok' => false, 'message' => 'A resposta ultrapassou o limite de 50.000 caracteres.');
        }

        $item = $this->itemModel->findById($itemId);
        if (!$item || (int) $item['curso_evento_id'] !== $cursoEventoId || (string) $item['tipo'] !== 'avaliacao_textual') {
            return array('ok' => false, 'message' => 'Item de avaliaÃ§Ã£o textual nÃ£o encontrado.');
        }

        $avaliacao = $avaliacaoId > 0 ? $this->avaliacaoModel->findById($avaliacaoId) : $this->avaliacaoModel->findByItemId($itemId);
        if (!$avaliacao) {
            return array('ok' => false, 'message' => 'AvaliaÃ§Ã£o textual nÃ£o encontrada para este item.');
        }

        $validacao = $this->validarConfiguracaoAvaliacao($avaliacao);
        if (empty($validacao['ok'])) {
            return $validacao;
        }

        if (!$this->podeReenviar((int) $avaliacao['id'], $alunoId, $inscricaoId)) {
            return array('ok' => false, 'message' => 'Reenvio nÃ£o permitido no momento. Aguarde liberaÃ§Ã£o de prazo.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $ultimaEntrega = $this->entregaModel->findLatestByContext((int) $avaliacao['id'], $alunoId, $inscricaoId);
            $statusEntrega = $ultimaEntrega ? 'reenviada' : 'enviada';
            $tentativa = $ultimaEntrega ? ((int) $ultimaEntrega['tentativa'] + 1) : 1;

            $entregaId = $this->entregaModel->create(array(
                'avaliacao_id' => (int) $avaliacao['id'],
                'item_id' => $itemId,
                'curso_evento_id' => $cursoEventoId,
                'turma_id' => $turmaId,
                'inscricao_id' => $inscricaoId,
                'aluno_id' => $alunoId,
                'resposta' => $resposta,
                'status' => $statusEntrega,
                'enviado_em' => date('Y-m-d H:i:s'),
                'tentativa' => $tentativa,
            ));

            $acaoLog = $statusEntrega === 'reenviada' ? 'reenviou_avaliacao' : 'enviou_avaliacao';
            $this->logModel->create(array(
                'curso_evento_id' => $cursoEventoId,
                'turma_id' => $turmaId,
                'inscricao_id' => $inscricaoId,
                'aluno_id' => $alunoId,
                'modulo_id' => (int) $item['modulo_id'],
                'item_id' => $itemId,
                'acao' => $acaoLog,
                'dados_json' => json_encode(array('entrega_id' => $entregaId, 'avaliacao_id' => (int) $avaliacao['id'], 'tentativa' => $tentativa)),
                'ip' => $dados['ip'] ?? null,
                'user_agent' => $dados['user_agent'] ?? null,
            ));

            $this->atualizarProgressoAvaliacao((int) $item['modulo_id'], $itemId, $cursoEventoId, $turmaId, $inscricaoId, $alunoId, 'pendente_correcao');
            $this->notificarProfessorNovaEntrega($entregaId, array(
                'curso_evento_id' => $cursoEventoId,
                'turma_id' => $turmaId,
                'inscricao_id' => $inscricaoId,
                'aluno_id' => $alunoId,
                'item_id' => $itemId,
                'item_titulo' => (string) $item['titulo'],
                'status_entrega' => $statusEntrega,
            ));

            $pdo->commit();
            return array('ok' => true, 'id' => $entregaId, 'status' => $statusEntrega);
        } catch (Exception $e) {
            $pdo->rollBack();
            Logger::error('conteudo.avaliacao_textual.enviar_falhou', array('message' => $e->getMessage()));
            throw $e;
        }
    }

    public function podeReenviar($avaliacaoId, $alunoId, $inscricaoId)
    {
        $avaliacaoId = (int) $avaliacaoId;
        $alunoId = (int) $alunoId;
        $inscricaoId = (int) $inscricaoId;
        if ($avaliacaoId <= 0 || $alunoId <= 0 || $inscricaoId <= 0) {
            return false;
        }

        $avaliacao = $this->avaliacaoModel->findById($avaliacaoId);
        if (!$avaliacao) {
            return false;
        }

        $ultimaEntrega = $this->entregaModel->findLatestByContext($avaliacaoId, $alunoId, $inscricaoId);
        if (!$ultimaEntrega) {
            return $this->prazoAberto($avaliacao);
        }

        $agora = time();
        $prazoLiberado = !empty($ultimaEntrega['prazo_liberado_ate']) ? strtotime((string) $ultimaEntrega['prazo_liberado_ate']) : false;
        $liberacaoAtiva = $prazoLiberado !== false && $agora <= $prazoLiberado;

        if (empty($avaliacao['permite_reenvio'])) {
            return $liberacaoAtiva;
        }

        if (!$this->prazoAberto($avaliacao)) {
            return $liberacaoAtiva;
        }

        if (!empty($avaliacao['reenvio_livre_ate_prazo'])) {
            return true;
        }

        return $liberacaoAtiva;
    }

    public function corrigirEntrega($entregaId, $dados)
    {
        $entregaId = (int) $entregaId;
        if ($entregaId <= 0) {
            return array('ok' => false, 'message' => 'Entrega invÃ¡lida.');
        }

        $entrega = $this->entregaModel->findById($entregaId);
        if (!$entrega) {
            return array('ok' => false, 'message' => 'Entrega nÃ£o encontrada.');
        }

        $dados = (array) $dados;
        $notaNormalizada = $this->normalizarDecimalInput($dados['nota'] ?? null, 'nota', true);
        if (empty($notaNormalizada['ok'])) {
            return $notaNormalizada;
        }
        $nota = $notaNormalizada['value'];
        $feedback = isset($dados['feedback']) ? trim((string) $dados['feedback']) : null;
        $corrigidoPor = (int) ($dados['corrigido_por'] ?? 0);
        $statusInformado = trim((string) ($dados['status'] ?? ''));

        $avaliacao = $this->avaliacaoModel->findById((int) $entrega['avaliacao_id']);
        if (!$avaliacao) {
            return array('ok' => false, 'message' => 'ConfiguraÃ§Ã£o da avaliaÃ§Ã£o nÃ£o encontrada.');
        }

        $notaMaximaAvaliacao = $this->normalizarDecimalInput($avaliacao['nota_maxima'] ?? null, 'nota mÃ¡xima', true);
        if (empty($notaMaximaAvaliacao['ok'])) {
            return $notaMaximaAvaliacao;
        }
        $notaMinimaAvaliacao = $this->normalizarDecimalInput($avaliacao['nota_minima'] ?? null, 'nota mÃ­nima', true);
        if (empty($notaMinimaAvaliacao['ok'])) {
            return $notaMinimaAvaliacao;
        }
        $notaMaximaValor = $notaMaximaAvaliacao['value'];
        $notaMinimaValor = $notaMinimaAvaliacao['value'];

        if ($nota !== null && $nota < 0) {
            return array('ok' => false, 'message' => 'A nota nÃ£o pode ser negativa.');
        }
        if ($nota !== null && $notaMaximaValor !== null && $nota > $notaMaximaValor) {
            return array('ok' => false, 'message' => 'A nota nÃ£o pode ser maior que a nota mÃ¡xima.');
        }
        if ($nota === null && !in_array($statusInformado, array('devolvida', 'cancelada'), true) && $notaMaximaValor !== null) {
            return array('ok' => false, 'message' => 'Informe a nota da correÃ§Ã£o.');
        }
        if ($statusInformado !== '' && !in_array($statusInformado, self::STATUS_VALIDOS, true)) {
            return array('ok' => false, 'message' => 'Status invÃ¡lido.');
        }

        $status = $statusInformado;
        if ($status === '') {
            if ($notaMinimaValor !== null && $nota !== null) {
                $status = $nota >= $notaMinimaValor ? 'aprovada' : 'reprovada';
            } else {
                $status = 'corrigida';
            }
        }

        $ok = $this->entregaModel->update(array(
            'resposta' => (string) $entrega['resposta'],
            'status' => $status,
            'nota' => $nota !== null ? number_format($nota, 2, '.', '') : null,
            'feedback' => $feedback,
            'corrigido_por' => $corrigidoPor > 0 ? $corrigidoPor : null,
            'corrigido_em' => date('Y-m-d H:i:s'),
            'enviado_em' => $entrega['enviado_em'],
            'prazo_liberado_ate' => $entrega['prazo_liberado_ate'],
            'liberado_reenvio_por' => $entrega['liberado_reenvio_por'],
            'liberado_reenvio_em' => $entrega['liberado_reenvio_em'],
            'tentativa' => (int) $entrega['tentativa'],
        ), $entregaId);

        if (!$ok) {
            return array('ok' => false, 'message' => 'NÃ£o foi possÃ­vel salvar a correÃ§Ã£o.');
        }

        $item = $this->itemModel->findById((int) $entrega['item_id']);
        $moduloId = $item ? (int) $item['modulo_id'] : 0;
        $statusProgresso = 'em_andamento';
        if (in_array($status, array('aprovada', 'corrigida'), true)) {
            $statusProgresso = 'concluido';
        } elseif ($status === 'reprovada') {
            $statusProgresso = 'reprovado';
        } elseif (in_array($status, array('enviada', 'reenviada'), true)) {
            $statusProgresso = 'pendente_correcao';
        }
        if ($moduloId > 0) {
            $this->atualizarProgressoAvaliacao($moduloId, (int) $entrega['item_id'], (int) $entrega['curso_evento_id'], !empty($entrega['turma_id']) ? (int) $entrega['turma_id'] : null, (int) $entrega['inscricao_id'], (int) $entrega['aluno_id'], $statusProgresso);
        }

        $this->logModel->create(array(
            'curso_evento_id' => (int) $entrega['curso_evento_id'],
            'turma_id' => $entrega['turma_id'],
            'inscricao_id' => $entrega['inscricao_id'],
            'aluno_id' => (int) $entrega['aluno_id'],
            'modulo_id' => $moduloId > 0 ? $moduloId : null,
            'item_id' => (int) $entrega['item_id'],
            'acao' => 'recebeu_correcao',
            'dados_json' => json_encode(array('entrega_id' => $entregaId, 'status' => $status, 'nota' => $nota)),
            'ip' => $dados['ip'] ?? null,
            'user_agent' => $dados['user_agent'] ?? null,
        ));

        if ((int) $entrega['inscricao_id'] > 0) {
            (new ConteudoCursoService())->recalcularProgressoInscricao((int) $entrega['inscricao_id']);
        }

        return array('ok' => true, 'status' => $status);
    }

    public function liberarNovoPrazo($entregaId, $novoPrazo, $usuarioId)
    {
        $entregaId = (int) $entregaId;
        if ($entregaId <= 0) {
            return array('ok' => false, 'message' => 'Entrega invÃ¡lida.');
        }
        $entrega = $this->entregaModel->findById($entregaId);
        if (!$entrega) {
            return array('ok' => false, 'message' => 'Entrega nÃ£o encontrada.');
        }

        $novoPrazo = trim((string) $novoPrazo);
        if ($novoPrazo === '' || strtotime($novoPrazo) === false) {
            return array('ok' => false, 'message' => 'Informe um novo prazo vÃ¡lido.');
        }

        $ok = $this->entregaModel->update(array(
            'resposta' => (string) $entrega['resposta'],
            'status' => (string) $entrega['status'],
            'nota' => $entrega['nota'],
            'feedback' => $entrega['feedback'],
            'corrigido_por' => $entrega['corrigido_por'],
            'corrigido_em' => $entrega['corrigido_em'],
            'enviado_em' => $entrega['enviado_em'],
            'prazo_liberado_ate' => $novoPrazo,
            'liberado_reenvio_por' => $usuarioId ? (int) $usuarioId : null,
            'liberado_reenvio_em' => date('Y-m-d H:i:s'),
            'tentativa' => (int) $entrega['tentativa'],
        ), $entregaId);

        return array('ok' => $ok);
    }

    public function listarPendentesProfessor($professorId)
    {
        $professorId = (int) $professorId;
        $sql = 'SELECT e.*,
                       ce.nome AS curso_nome,
                       t.nome AS turma_nome,
                       u.nome AS aluno_nome,
                       i.titulo AS item_titulo
                FROM conteudo_avaliacoes_entregas e
                INNER JOIN cursos_eventos ce ON ce.id = e.curso_evento_id
                LEFT JOIN turmas t ON t.id = e.turma_id
                INNER JOIN usuarios u ON u.id = e.aluno_id
                INNER JOIN conteudo_itens i ON i.id = e.item_id
                WHERE e.deleted_at IS NULL
                  AND e.status IN ("enviada","reenviada","devolvida")';
        $params = array();

        if ($professorId > 0) {
            $ids = $this->cursoIdsAcessiveisProfessor($professorId);
            if (empty($ids)) {
                return array('ok' => true, 'items' => array());
            }
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql .= ' AND e.curso_evento_id IN (' . $placeholders . ')';
            $params = $ids;
        }

        $sql .= ' ORDER BY e.enviado_em IS NULL, e.enviado_em DESC, e.id DESC LIMIT 200';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        if ((int) $professorId <= 0) {
            return array('ok' => true, 'items' => $stmt->fetchAll(PDO::FETCH_ASSOC));
        }
        $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array('ok' => true, 'items' => $itens);
    }

    public function contarPendentesProfessor($professorId)
    {
        if ((int) $professorId <= 0) {
            $stmt = Database::connection()->query(
                'SELECT COUNT(*) AS total
                 FROM conteudo_avaliacoes_entregas
                 WHERE deleted_at IS NULL
                   AND status IN ("enviada","reenviada","devolvida")'
            );
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return array('ok' => true, 'total' => !empty($row['total']) ? (int) $row['total'] : 0);
        }
        $ids = $this->cursoIdsAcessiveisProfessor($professorId);
        $total = $this->entregaModel->countPendentesByCursoIds($ids);
        return array('ok' => true, 'total' => $total);
    }

    public function listarEntregasPorAvaliacao($avaliacaoId)
    {
        $avaliacaoId = (int) $avaliacaoId;
        if ($avaliacaoId <= 0) {
            return array();
        }
        $stmt = Database::connection()->prepare(
            'SELECT e.*, u.nome AS aluno_nome, u.email AS aluno_email
             FROM conteudo_avaliacoes_entregas e
             INNER JOIN usuarios u ON u.id = e.aluno_id
             WHERE e.deleted_at IS NULL AND e.avaliacao_id = :avaliacao_id
             ORDER BY e.tentativa DESC, e.id DESC'
        );
        $stmt->execute(array('avaliacao_id' => $avaliacaoId));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarEntregaParaCorrecao($entregaId)
    {
        $entregaId = (int) $entregaId;
        if ($entregaId <= 0) {
            return null;
        }
        $stmt = Database::connection()->prepare(
            'SELECT e.*, a.enunciado, a.orientacoes, a.nota_maxima, a.nota_minima, a.peso, a.prazo,
                    i.titulo AS item_titulo, m.titulo AS modulo_titulo,
                    ce.nome AS curso_nome, t.nome AS turma_nome,
                    u.nome AS aluno_nome, u.email AS aluno_email
             FROM conteudo_avaliacoes_entregas e
             INNER JOIN conteudo_avaliacoes_textuais a ON a.id = e.avaliacao_id
             INNER JOIN conteudo_itens i ON i.id = e.item_id
             INNER JOIN conteudo_modulos m ON m.id = i.modulo_id
             INNER JOIN cursos_eventos ce ON ce.id = e.curso_evento_id
             LEFT JOIN turmas t ON t.id = e.turma_id
             INNER JOIN usuarios u ON u.id = e.aluno_id
             WHERE e.id = :id AND e.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(array('id' => $entregaId));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function listarEntregasAluno($avaliacaoId, $alunoId, $inscricaoId)
    {
        $avaliacaoId = (int) $avaliacaoId;
        $alunoId = (int) $alunoId;
        $inscricaoId = (int) $inscricaoId;
        if ($avaliacaoId <= 0 || $alunoId <= 0 || $inscricaoId <= 0) {
            return array();
        }
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM conteudo_avaliacoes_entregas
             WHERE avaliacao_id = :avaliacao_id
               AND aluno_id = :aluno_id
               AND inscricao_id = :inscricao_id
               AND deleted_at IS NULL
             ORDER BY tentativa DESC, id DESC'
        );
        $stmt->execute(array('avaliacao_id' => $avaliacaoId, 'aluno_id' => $alunoId, 'inscricao_id' => $inscricaoId));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarNotasAvaliacoesTextuais($cursoEventoId, $turmaId = null)
    {
        $cursoEventoId = (int) $cursoEventoId;
        if ($cursoEventoId <= 0) {
            return array();
        }
        $sql = 'SELECT e.aluno_id, e.inscricao_id, e.item_id, e.nota, e.status, e.corrigido_em
                FROM conteudo_avaliacoes_entregas e
                WHERE e.deleted_at IS NULL
                  AND e.curso_evento_id = :curso_evento_id
                  AND e.status IN ("corrigida","aprovada","reprovada")';
        $params = array('curso_evento_id' => $cursoEventoId);
        if ($turmaId !== null && (int) $turmaId > 0) {
            $sql .= ' AND e.turma_id = :turma_id';
            $params['turma_id'] = (int) $turmaId;
        }
        $sql .= ' ORDER BY e.corrigido_em DESC, e.id DESC';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function cursoIdsAcessiveisProfessor($professorId)
    {
        $professorId = (int) $professorId;
        if ($professorId <= 0) {
            return array();
        }
        $cursos = $this->cursoModel->findAccessibleByUser($professorId);
        $ids = array();
        foreach ($cursos as $curso) {
            $ids[] = (int) $curso['id'];
        }
        return array_values(array_unique($ids));
    }

    private function validarConfiguracaoAvaliacao(array $avaliacao)
    {
        $pesoNormalizado = $this->normalizarDecimalInput($avaliacao['peso'] ?? 1, 'peso', false);
        if (empty($pesoNormalizado['ok'])) {
            return $pesoNormalizado;
        }
        $peso = $pesoNormalizado['value'];
        if ($peso <= 0) {
            return array('ok' => false, 'message' => 'O peso da avaliaÃ§Ã£o deve ser maior que zero.');
        }
        $notaMaximaNormalizada = $this->normalizarDecimalInput($avaliacao['nota_maxima'] ?? null, 'nota mÃ¡xima', true);
        if (empty($notaMaximaNormalizada['ok'])) {
            return $notaMaximaNormalizada;
        }
        $notaMinimaNormalizada = $this->normalizarDecimalInput($avaliacao['nota_minima'] ?? null, 'nota mÃ­nima', true);
        if (empty($notaMinimaNormalizada['ok'])) {
            return $notaMinimaNormalizada;
        }
        $notaMaxima = $notaMaximaNormalizada['value'];
        $notaMinima = $notaMinimaNormalizada['value'];
        if ($notaMaxima !== null && $notaMinima !== null && $notaMinima > $notaMaxima) {
            return array('ok' => false, 'message' => 'A nota mÃ­nima nÃ£o pode ser maior que a nota mÃ¡xima.');
        }
        return array('ok' => true);
    }

    private function normalizarDecimalInput($valor, $campo, $permitirNulo = true)
    {
        if ($valor === null) {
            return array('ok' => true, 'value' => null);
        }

        $texto = trim((string) $valor);
        if ($texto === '') {
            return $permitirNulo
                ? array('ok' => true, 'value' => null)
                : array('ok' => false, 'message' => 'Informe um valor vÃ¡lido para ' . $campo . '.');
        }

        if (strpos($texto, ',') !== false && strpos($texto, '.') !== false) {
            return array('ok' => false, 'message' => 'Formato invÃ¡lido para ' . $campo . '. Use apenas vÃ­rgula ou ponto decimal.');
        }

        if (!preg_match('/^\\d+(?:[\\.,]\\d+)?$/', $texto)) {
            return array('ok' => false, 'message' => 'Formato invÃ¡lido para ' . $campo . '.');
        }

        $normalizado = str_replace(',', '.', $texto);
        if (!is_numeric($normalizado)) {
            return array('ok' => false, 'message' => 'Formato invÃ¡lido para ' . $campo . '.');
        }

        return array('ok' => true, 'value' => (float) $normalizado);
    }

    private function prazoAberto(array $avaliacao)
    {
        if (empty($avaliacao['prazo'])) {
            return true;
        }
        $prazo = strtotime((string) $avaliacao['prazo']);
        if ($prazo === false) {
            return false;
        }
        return time() <= $prazo;
    }

    private function atualizarProgressoAvaliacao($moduloId, $itemId, $cursoEventoId, $turmaId, $inscricaoId, $alunoId, $status)
    {
        $agora = date('Y-m-d H:i:s');
        $existente = $this->progressoModel->findByContext((int) $alunoId, (int) $inscricaoId, (int) $itemId);
        $percentual = 50.0;
        $concluidoEm = null;
        if ($status === 'concluido') {
            $percentual = 100.0;
            $concluidoEm = $agora;
        } elseif ($status === 'reprovado') {
            $percentual = 0.0;
        } elseif ($status === 'em_andamento') {
            $percentual = 20.0;
        }
        if ($status !== 'concluido') {
            $concluidoEm = null;
        }
        $this->progressoModel->upsert(array(
            'curso_evento_id' => (int) $cursoEventoId,
            'turma_id' => $turmaId !== null ? (int) $turmaId : null,
            'inscricao_id' => (int) $inscricaoId,
            'aluno_id' => (int) $alunoId,
            'modulo_id' => (int) $moduloId,
            'item_id' => (int) $itemId,
            'status' => (string) $status,
            'percentual' => $percentual,
            'obrigatorio' => 1,
            'primeiro_acesso_em' => $existente && !empty($existente['primeiro_acesso_em']) ? $existente['primeiro_acesso_em'] : $agora,
            'ultimo_acesso_em' => $agora,
            'concluido_em' => $concluidoEm,
        ));
    }

    private function notificarProfessorNovaEntrega($entregaId, array $contexto)
    {
        $cursoEventoId = (int) ($contexto['curso_evento_id'] ?? 0);
        if ($cursoEventoId <= 0) {
            return;
        }
        $stmt = Database::connection()->prepare(
            'SELECT DISTINCT u.email, u.nome
             FROM usuario_cursos uc
             INNER JOIN usuarios u ON u.id = uc.usuario_id
             WHERE uc.curso_evento_id = :curso_evento_id
               AND uc.tipo_vinculo = "professor"
               AND uc.status = "ativo"
               AND uc.deleted_at IS NULL
               AND u.deleted_at IS NULL
               AND u.status = "ativo"'
        );
        $stmt->execute(array('curso_evento_id' => $cursoEventoId));
        $professores = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($professores)) {
            Logger::info('conteudo.avaliacao_textual.sem_professor_notificacao', array('entrega_id' => $entregaId, 'curso_evento_id' => $cursoEventoId));
            return;
        }

        $curso = $this->cursoModel->findById($cursoEventoId);
        $inscricao = $this->inscricaoModel->findById((int) ($contexto['inscricao_id'] ?? 0));
        $alunoNome = $inscricao && !empty($inscricao['participante_nome']) ? $inscricao['participante_nome'] : ('Aluno #' . (int) ($contexto['aluno_id'] ?? 0));
        $turmaNome = !empty($inscricao['turma_nome']) ? $inscricao['turma_nome'] : ((int) ($contexto['turma_id'] ?? 0) > 0 ? ('Turma #' . (int) $contexto['turma_id']) : 'Sem turma');
        $link = '/professor/area-curso/conteudo/avaliacao/corrigir?id=' . (int) $entregaId . '&curso_id=' . $cursoEventoId . ((int) ($contexto['turma_id'] ?? 0) > 0 ? '&turma_id=' . (int) $contexto['turma_id'] : '');

        $emailService = new EmailService();
        foreach ($professores as $professor) {
            $emailService->sendCustomHtml(
                'conteudo_avaliacao_textual_enviada',
                'pendencia',
                (string) $professor['email'],
                (string) $professor['nome'],
                'Nova avaliaÃ§Ã£o textual enviada - ' . (!empty($curso['nome']) ? $curso['nome'] : 'Curso'),
                '<p>Nova entrega de avaliaÃ§Ã£o textual.</p><p>Curso: ' . htmlspecialchars((string) (!empty($curso['nome']) ? $curso['nome'] : '')) . '</p><p>Turma: ' . htmlspecialchars((string) $turmaNome) . '</p><p>Aluno: ' . htmlspecialchars((string) $alunoNome) . '</p><p>AvaliaÃ§Ã£o: ' . htmlspecialchars((string) ($contexto['item_titulo'] ?? '')) . '</p><p>Data de envio: ' . date('d/m/Y H:i') . '</p><p>Acesse para corrigir: ' . htmlspecialchars((string) $link) . '</p>',
                array(
                    'curso' => !empty($curso['nome']) ? $curso['nome'] : '',
                    'turma' => $turmaNome,
                    'aluno' => $alunoNome,
                    'avaliacao' => (string) ($contexto['item_titulo'] ?? ''),
                    'link_correcao' => $link,
                )
            );
        }
    }
}

