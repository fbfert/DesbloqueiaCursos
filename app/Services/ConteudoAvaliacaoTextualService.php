<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Helpers;
use App\Core\Logger;
use App\Models\ConteudoAvaliacaoEntrega;
use App\Models\ConteudoAvaliacaoEntregaImagem;
use App\Models\ConteudoAvaliacaoTextual;
use App\Models\ConteudoItem;
use App\Models\ConteudoLogAluno;
use App\Models\ConteudoProgressoAluno;
use App\Models\CursoEvento;
use App\Models\Inscricao;
use App\Services\FileStorageService;
use Exception;
use PDO;

class ConteudoAvaliacaoTextualService
{
    private const STATUS_VALIDOS = array('enviada', 'reenviada', 'corrigida', 'devolvida', 'aprovada', 'reprovada', 'cancelada');

    // Anexos de imagem na resposta do aluno. Limite de tamanho por imagem
    // deliberadamente conservador: o php.ini efetivo do servidor hoje tem
    // upload_max_filesize=2M e post_max_size=8M (conferido em
    // /home/desbloqueiacursos/etc/php.ini) - 5 imagens no limite maximo
    // ultrapassariam post_max_size e o PHP descartaria o POST inteiro
    // silenciosamente (sem chegar a rodar validacao nenhuma em PHP).
    private const MAX_IMAGENS_POR_ENTREGA = 5;
    private const LIMITE_IMAGEM_BYTES = 1572864; // 1,5 MB por imagem (5 x 1,5MB = 7,5MB, dentro do post_max_size=8M atual)
    private const EXTENSOES_IMAGEM_VALIDAS = array('jpg', 'jpeg', 'png', 'webp');
    private const MIME_IMAGEM_VALIDOS = array('image/jpeg', 'image/png', 'image/webp');

    private $avaliacaoModel;
    private $entregaModel;
    private $imagemModel;
    private $itemModel;
    private $logModel;
    private $cursoModel;
    private $progressoModel;
    private $inscricaoModel;
    private $fileStorageService;

    public function __construct()
    {
        $this->avaliacaoModel = new ConteudoAvaliacaoTextual();
        $this->entregaModel = new ConteudoAvaliacaoEntrega();
        $this->imagemModel = new ConteudoAvaliacaoEntregaImagem();
        $this->itemModel = new ConteudoItem();
        $this->logModel = new ConteudoLogAluno();
        $this->cursoModel = new CursoEvento();
        $this->progressoModel = new ConteudoProgressoAluno();
        $this->inscricaoModel = new Inscricao();
        $this->fileStorageService = new FileStorageService();
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
            return array('ok' => false, 'message' => 'Dados inválidos para envio da avaliação textual.');
        }
        $respostaTamanho = function_exists('mb_strlen') ? mb_strlen($resposta) : strlen($resposta);
        if ($resposta === '' || $respostaTamanho < 3) {
            return array('ok' => false, 'message' => 'Informe uma resposta com pelo menos 3 caracteres.');
        }
        if ($respostaTamanho > 50000) {
            return array('ok' => false, 'message' => 'A resposta ultrapassou o limite de 50.000 caracteres.');
        }

        $imagens = isset($dados['imagens']) && is_array($dados['imagens']) ? $dados['imagens'] : array();
        $validacaoImagens = $this->validarImagensEnvio($imagens);
        if (empty($validacaoImagens['ok'])) {
            return $validacaoImagens;
        }

        $item = $this->itemModel->findById($itemId);
        if (!$item || (int) $item['curso_evento_id'] !== $cursoEventoId || (string) $item['tipo'] !== 'avaliacao_textual') {
            return array('ok' => false, 'message' => 'Item de avaliação textual não encontrado.');
        }

        $avaliacao = $avaliacaoId > 0 ? $this->avaliacaoModel->findById($avaliacaoId) : $this->avaliacaoModel->findByItemId($itemId);
        if (!$avaliacao) {
            return array('ok' => false, 'message' => 'Avaliação textual não encontrada para este item.');
        }

        $validacao = $this->validarConfiguracaoAvaliacao($avaliacao);
        if (empty($validacao['ok'])) {
            return $validacao;
        }

        if (!$this->podeReenviar((int) $avaliacao['id'], $alunoId, $inscricaoId)) {
            return array('ok' => false, 'message' => 'Reenvio não permitido no momento. Aguarde liberação de prazo.');
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

            $this->armazenarImagensEntrega($entregaId, $imagens);

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
            $this->notificarAvaliadorPedagogico($entregaId, array(
                'curso_evento_id' => $cursoEventoId,
                'turma_id' => $turmaId,
                'inscricao_id' => $inscricaoId,
                'aluno_id' => $alunoId,
                'item_id' => $itemId,
                'item_titulo' => (string) $item['titulo'],
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
            return array('ok' => false, 'message' => 'Entrega inválida.');
        }

        $entrega = $this->entregaModel->findById($entregaId);
        if (!$entrega) {
            return array('ok' => false, 'message' => 'Entrega não encontrada.');
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
            return array('ok' => false, 'message' => 'Configuração da avaliação não encontrada.');
        }

        $notaMaximaAvaliacao = $this->normalizarDecimalInput($avaliacao['nota_maxima'] ?? null, 'nota máxima', true);
        if (empty($notaMaximaAvaliacao['ok'])) {
            return $notaMaximaAvaliacao;
        }
        $notaMinimaAvaliacao = $this->normalizarDecimalInput($avaliacao['nota_minima'] ?? null, 'nota mínima', true);
        if (empty($notaMinimaAvaliacao['ok'])) {
            return $notaMinimaAvaliacao;
        }
        $notaMaximaValor = $notaMaximaAvaliacao['value'];
        $notaMinimaValor = $notaMinimaAvaliacao['value'];

        if ($nota !== null && $nota < 0) {
            return array('ok' => false, 'message' => 'A nota não pode ser negativa.');
        }
        if ($nota !== null && $notaMaximaValor !== null && $nota > $notaMaximaValor) {
            return array('ok' => false, 'message' => 'A nota não pode ser maior que a nota máxima.');
        }
        if ($nota === null && !in_array($statusInformado, array('devolvida', 'cancelada'), true) && $notaMaximaValor !== null) {
            return array('ok' => false, 'message' => 'Informe a nota da correção.');
        }
        if ($statusInformado !== '' && !in_array($statusInformado, self::STATUS_VALIDOS, true)) {
            return array('ok' => false, 'message' => 'Status inválido.');
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
            return array('ok' => false, 'message' => 'Não foi possível salvar a correção.');
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
            return array('ok' => false, 'message' => 'Entrega inválida.');
        }
        $entrega = $this->entregaModel->findById($entregaId);
        if (!$entrega) {
            return array('ok' => false, 'message' => 'Entrega não encontrada.');
        }

        $novoPrazo = trim((string) $novoPrazo);
        if ($novoPrazo === '' || strtotime($novoPrazo) === false) {
            return array('ok' => false, 'message' => 'Informe um novo prazo válido.');
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
        if (!$row) {
            return null;
        }
        $row['imagens'] = $this->imagensEntrega($entregaId);
        return $row;
    }

    /**
     * Lista as imagens de uma entrega (sem expor o caminho fisico de
     * armazenamento - o download real acontece por rota controlada que
     * valida posse antes de ler o arquivo).
     */
    public function imagensEntrega($entregaId)
    {
        $entregaId = (int) $entregaId;
        if ($entregaId <= 0) {
            return array();
        }
        $imagens = $this->imagemModel->listByEntregaId($entregaId);
        foreach ($imagens as &$imagem) {
            unset($imagem['caminho']);
        }
        unset($imagem);
        return $imagens;
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

    public function listarNotasAvaliacoesTextuais($cursoEventoId, $turmaId = null, $alunoId = null)
    {
        $cursoEventoId = (int) $cursoEventoId;
        if ($cursoEventoId <= 0) {
            return array();
        }

        $sql = 'SELECT
                    e.aluno_id,
                    u.nome AS aluno_nome,
                    e.inscricao_id,
                    e.turma_id,
                    e.curso_evento_id,
                    m.id AS modulo_id,
                    m.titulo AS modulo_titulo,
                    i.id AS item_id,
                    a.id AS avaliacao_id,
                    i.titulo AS avaliacao_titulo,
                    e.tentativa,
                    e.status,
                    e.nota,
                    a.nota_maxima,
                    a.nota_minima,
                    CASE
                        WHEN a.peso IS NULL OR a.peso <= 0 THEN 1.00
                        ELSE a.peso
                    END AS peso,
                    e.feedback,
                    e.enviado_em,
                    e.corrigido_em,
                    e.corrigido_por,
                    i.obrigatorio,
                    a.prazo
                FROM conteudo_avaliacoes_entregas e
                INNER JOIN (
                    SELECT MAX(e2.id) AS id
                    FROM conteudo_avaliacoes_entregas e2
                    WHERE e2.deleted_at IS NULL
                      AND e2.status <> "cancelada"
                      AND e2.curso_evento_id = :curso_evento_id
                    GROUP BY e2.avaliacao_id, e2.aluno_id, e2.inscricao_id
                ) ult ON ult.id = e.id
                INNER JOIN conteudo_avaliacoes_textuais a ON a.id = e.avaliacao_id
                INNER JOIN conteudo_itens i ON i.id = e.item_id
                INNER JOIN conteudo_modulos m ON m.id = i.modulo_id
                INNER JOIN usuarios u ON u.id = e.aluno_id
                WHERE e.deleted_at IS NULL
                  AND e.curso_evento_id = :curso_evento_id2
                  AND i.deleted_at IS NULL
                  AND i.tipo = "avaliacao_textual"
                  AND i.status = "publicado"
                  AND m.deleted_at IS NULL
                  AND m.status = "publicado"';
        $params = array(
            'curso_evento_id' => $cursoEventoId,
            'curso_evento_id2' => $cursoEventoId,
        );
        if ($turmaId !== null && (int) $turmaId > 0) {
            $sql .= ' AND e.turma_id = :turma_id';
            $params['turma_id'] = (int) $turmaId;
        }
        if ($alunoId !== null && (int) $alunoId > 0) {
            $sql .= ' AND e.aluno_id = :aluno_id';
            $params['aluno_id'] = (int) $alunoId;
        }
        $sql .= ' ORDER BY u.nome ASC, m.ordem ASC, i.ordem ASC, e.id DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($items as &$item) {
            $item['nota'] = $item['nota'] !== null ? (float) $item['nota'] : null;
            $item['nota_maxima'] = $item['nota_maxima'] !== null ? (float) $item['nota_maxima'] : null;
            $item['nota_minima'] = $item['nota_minima'] !== null ? (float) $item['nota_minima'] : null;
            $item['peso'] = $item['peso'] !== null && (float) $item['peso'] > 0 ? (float) $item['peso'] : 1.0;
            $item['obrigatorio'] = !empty($item['obrigatorio']) ? 1 : 0;
        }
        unset($item);
        return $items;
    }

    public function resumoNotasAvaliacoesTextuaisPorAluno($cursoEventoId, $turmaId = null)
    {
        $notas = $this->listarNotasAvaliacoesTextuais($cursoEventoId, $turmaId, null);
        $resumo = array();

        foreach ($notas as $nota) {
            $inscricaoId = (int) ($nota['inscricao_id'] ?? 0);
            if ($inscricaoId <= 0) {
                continue;
            }
            if (!isset($resumo[$inscricaoId])) {
                $resumo[$inscricaoId] = array(
                    'aluno_id' => (int) $nota['aluno_id'],
                    'aluno_nome' => (string) ($nota['aluno_nome'] ?? ''),
                    'inscricao_id' => $inscricaoId,
                    'turma_id' => !empty($nota['turma_id']) ? (int) $nota['turma_id'] : null,
                    'curso_evento_id' => (int) $nota['curso_evento_id'],
                    'total_pontos_ponderados' => 0.0,
                    'soma_pesos' => 0.0,
                    'media_ponderada' => null,
                    'avaliacoes_obrigatorias' => 0,
                    'corrigidas' => 0,
                    'aprovadas' => 0,
                    'reprovadas' => 0,
                    'pendentes' => 0,
                    'itens' => array(),
                );
            }

            $peso = isset($nota['peso']) && (float) $nota['peso'] > 0 ? (float) $nota['peso'] : 1.0;
            $status = (string) ($nota['status'] ?? '');
            $temNota = array_key_exists('nota', $nota) && $nota['nota'] !== null;

            if (!empty($nota['obrigatorio'])) {
                $resumo[$inscricaoId]['avaliacoes_obrigatorias']++;
            }
            if ($temNota) {
                $valorNota = (float) $nota['nota'];
                $resumo[$inscricaoId]['total_pontos_ponderados'] += ($valorNota * $peso);
                $resumo[$inscricaoId]['soma_pesos'] += $peso;
                $resumo[$inscricaoId]['corrigidas']++;
            } else {
                $resumo[$inscricaoId]['pendentes']++;
            }
            if ($status === 'aprovada') {
                $resumo[$inscricaoId]['aprovadas']++;
            } elseif ($status === 'reprovada') {
                $resumo[$inscricaoId]['reprovadas']++;
            }

            $resumo[$inscricaoId]['itens'][] = $nota;
        }

        foreach ($resumo as &$linha) {
            if ($linha['soma_pesos'] > 0) {
                $linha['media_ponderada'] = round($linha['total_pontos_ponderados'] / $linha['soma_pesos'], 2);
            }
        }
        unset($linha);

        return array_values($resumo);
    }

    public function calcularMediaAvaliacoesTextuaisAluno($cursoEventoId, $turmaId, $alunoId)
    {
        $resumos = $this->resumoNotasAvaliacoesTextuaisPorAluno($cursoEventoId, $turmaId);
        $alunoId = (int) $alunoId;
        foreach ($resumos as $resumo) {
            if ((int) ($resumo['aluno_id'] ?? 0) === $alunoId) {
                return $resumo;
            }
        }
        return array(
            'aluno_id' => $alunoId,
            'media_ponderada' => null,
            'total_pontos_ponderados' => 0.0,
            'soma_pesos' => 0.0,
            'avaliacoes_obrigatorias' => 0,
            'corrigidas' => 0,
            'aprovadas' => 0,
            'reprovadas' => 0,
            'pendentes' => 0,
            'itens' => array(),
        );
    }

    /**
     * Normaliza e valida os arquivos de imagem recebidos ($_FILES['imagens']
     * ja reorganizado pelo controller em uma lista de arquivos individuais,
     * ver Helpers::normalizarUploadMultiplo). Nao move nenhum arquivo aqui -
     * so valida quantidade/extensao/tamanho antes de abrir a transacao.
     */
    private function validarImagensEnvio(array $imagens)
    {
        if (count($imagens) > self::MAX_IMAGENS_POR_ENTREGA) {
            return array('ok' => false, 'message' => 'Envie no máximo ' . self::MAX_IMAGENS_POR_ENTREGA . ' imagens.');
        }

        foreach ($imagens as $imagem) {
            if (empty($imagem['tmp_name']) || !is_uploaded_file($imagem['tmp_name'])) {
                return array('ok' => false, 'message' => 'Um dos arquivos enviados é inválido.');
            }
            if (!empty($imagem['error']) && (int) $imagem['error'] !== UPLOAD_ERR_OK) {
                return array('ok' => false, 'message' => 'Falha ao enviar uma das imagens. Tente novamente.');
            }
            if (!empty($imagem['size']) && (int) $imagem['size'] > self::LIMITE_IMAGEM_BYTES) {
                return array('ok' => false, 'message' => 'Cada imagem deve ter no máximo ' . round(self::LIMITE_IMAGEM_BYTES / 1024 / 1024, 1) . ' MB.');
            }
            $originalName = isset($imagem['name']) ? basename((string) $imagem['name']) : '';
            $extensao = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            if (!in_array($extensao, self::EXTENSOES_IMAGEM_VALIDAS, true)) {
                return array('ok' => false, 'message' => 'Formato de imagem não permitido. Use JPG, PNG ou WEBP.');
            }
        }

        return array('ok' => true);
    }

    private function armazenarImagensEntrega($entregaId, array $imagens)
    {
        $ordem = 1;
        foreach ($imagens as $imagem) {
            $diretorio = 'avaliacoes-textuais/entregas/' . (int) $entregaId;
            $upload = $this->fileStorageService->storeUploadedFile($imagem, $diretorio, 'entrega-imagem', array(
                'max_size_bytes' => self::LIMITE_IMAGEM_BYTES,
                'allowed_extensions' => self::EXTENSOES_IMAGEM_VALIDAS,
                'allowed_mime_types' => self::MIME_IMAGEM_VALIDOS,
            ));

            $this->imagemModel->create(array(
                'entrega_id' => $entregaId,
                'nome_original' => $upload['original_name'],
                'nome_arquivo' => basename($upload['absolute_path']),
                'caminho' => $upload['relative_path'],
                'mime_type' => $upload['mime_type'],
                'extensao' => strtolower(pathinfo($upload['original_name'], PATHINFO_EXTENSION)),
                'tamanho_bytes' => $upload['size'],
                'ordem' => $ordem,
            ));
            $ordem++;
        }
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
            return array('ok' => false, 'message' => 'O peso da avaliação deve ser maior que zero.');
        }
        $notaMaximaNormalizada = $this->normalizarDecimalInput($avaliacao['nota_maxima'] ?? null, 'nota máxima', true);
        if (empty($notaMaximaNormalizada['ok'])) {
            return $notaMaximaNormalizada;
        }
        $notaMinimaNormalizada = $this->normalizarDecimalInput($avaliacao['nota_minima'] ?? null, 'nota mínima', true);
        if (empty($notaMinimaNormalizada['ok'])) {
            return $notaMinimaNormalizada;
        }
        $notaMaxima = $notaMaximaNormalizada['value'];
        $notaMinima = $notaMinimaNormalizada['value'];
        if ($notaMaxima !== null && $notaMinima !== null && $notaMinima > $notaMaxima) {
            return array('ok' => false, 'message' => 'A nota mínima não pode ser maior que a nota máxima.');
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
                : array('ok' => false, 'message' => 'Informe um valor válido para ' . $campo . '.');
        }

        if (strpos($texto, ',') !== false && strpos($texto, '.') !== false) {
            return array('ok' => false, 'message' => 'Formato inválido para ' . $campo . '. Use apenas vírgula ou ponto decimal.');
        }

        if (!preg_match('/^\\d+(?:[\\.,]\\d+)?$/', $texto)) {
            return array('ok' => false, 'message' => 'Formato inválido para ' . $campo . '.');
        }

        $normalizado = str_replace(',', '.', $texto);
        if (!is_numeric($normalizado)) {
            return array('ok' => false, 'message' => 'Formato inválido para ' . $campo . '.');
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
        $inscricaoId = (int) ($contexto['inscricao_id'] ?? 0);
        $nomes = $this->resolverNomesInscricao($inscricaoId, (int) ($contexto['aluno_id'] ?? 0), (int) ($contexto['turma_id'] ?? 0));
        $alunoNome = $nomes['aluno_nome'];
        $turmaNome = $nomes['turma_nome'];
        $link = '/professor/area-curso/conteudo/avaliacao/corrigir?id=' . (int) $entregaId . '&curso_id=' . $cursoEventoId . ((int) ($contexto['turma_id'] ?? 0) > 0 ? '&turma_id=' . (int) $contexto['turma_id'] : '');

        $emailService = new EmailService();
        foreach ($professores as $professor) {
            $emailService->sendCustomHtml(
                'conteudo_avaliacao_textual_enviada',
                'pendencia',
                (string) $professor['email'],
                (string) $professor['nome'],
                'Nova avaliação textual enviada - ' . (!empty($curso['nome']) ? $curso['nome'] : 'Curso'),
                '<p>Nova entrega de avaliação textual.</p><p>Curso: ' . htmlspecialchars((string) (!empty($curso['nome']) ? $curso['nome'] : '')) . '</p><p>Turma: ' . htmlspecialchars((string) $turmaNome) . '</p><p>Aluno: ' . htmlspecialchars((string) $alunoNome) . '</p><p>Avaliação: ' . htmlspecialchars((string) ($contexto['item_titulo'] ?? '')) . '</p><p>Data de envio: ' . date('d/m/Y H:i') . '</p><p>Acesse para corrigir: ' . htmlspecialchars((string) $link) . '</p>',
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

    /**
     * Avisa o e-mail do avaliador pedagógico configurado em Configurações
     * Globais (independente de haver ou não professor vinculado ao curso -
     * ver `notificarProfessorNovaEntrega`, que é quem cobre o professor).
     * Se o e-mail não estiver configurado, `EmailService::avaliacaoTextualPendente()`
     * apenas registra e não bloqueia o envio da entrega.
     */
    private function notificarAvaliadorPedagogico($entregaId, array $contexto)
    {
        $cursoEventoId = (int) ($contexto['curso_evento_id'] ?? 0);
        if ($cursoEventoId <= 0) {
            return;
        }

        $curso = $this->cursoModel->findById($cursoEventoId);
        $inscricaoId = (int) ($contexto['inscricao_id'] ?? 0);
        $nomes = $this->resolverNomesInscricao($inscricaoId, (int) ($contexto['aluno_id'] ?? 0), (int) ($contexto['turma_id'] ?? 0));
        $link = '/admin/area-curso/conteudo/avaliacao/corrigir?id=' . (int) $entregaId;

        (new EmailService())->avaliacaoTextualPendente(array(
            'entrega_id' => $entregaId,
            'curso_nome' => !empty($curso['nome']) ? $curso['nome'] : '',
            'turma_nome' => $nomes['turma_nome'],
            'aluno_nome' => $nomes['aluno_nome'],
            'item_titulo' => (string) ($contexto['item_titulo'] ?? ''),
            'enviado_em' => date('d/m/Y H:i'),
            'link_correcao' => Helpers::url($link),
        ));
    }

    /**
     * `inscricoes` não tem colunas próprias de nome de aluno/turma - resolve
     * via join com `participantes_pedido`/`usuarios` (aluno) e `turmas`
     * (mesmo padrão usado em `Certificado::listEligible()`), com fallback
     * para os IDs somente se a inscrição não existir mais.
     */
    private function resolverNomesInscricao($inscricaoId, $alunoId = 0, $turmaId = 0)
    {
        $alunoNome = $alunoId > 0 ? ('Aluno #' . $alunoId) : 'Aluno(a)';
        $turmaNome = $turmaId > 0 ? ('Turma #' . $turmaId) : 'Sem turma';

        if ($inscricaoId <= 0) {
            return array('aluno_nome' => $alunoNome, 'turma_nome' => $turmaNome);
        }

        $stmt = Database::connection()->prepare(
            'SELECT COALESCE(u.nome, pp.nome) AS aluno_nome, t.nome AS turma_nome
             FROM inscricoes i
             LEFT JOIN participantes_pedido pp ON pp.id = i.participante_pedido_id
             LEFT JOIN usuarios u ON u.id = i.usuario_id
             LEFT JOIN turmas t ON t.id = i.turma_id
             WHERE i.id = :id
             LIMIT 1'
        );
        $stmt->bindValue(':id', $inscricaoId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return array(
            'aluno_nome' => $row && !empty($row['aluno_nome']) ? $row['aluno_nome'] : $alunoNome,
            'turma_nome' => $row && !empty($row['turma_nome']) ? $row['turma_nome'] : $turmaNome,
        );
    }
}

