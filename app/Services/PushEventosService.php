<?php

namespace App\Services;

use App\Core\Logger;
use App\Models\Atividade;
use App\Models\AtividadeEntrega;
use App\Models\Certificado;
use App\Models\ConteudoAvaliacaoEntrega;
use App\Models\ConteudoItem;
use App\Models\ConteudoModulo;
use App\Models\ConteudoQuiz;
use App\Models\ConteudoQuizTentativa;
use App\Models\CursoEvento;
use App\Models\Inscricao;
use App\Models\Pedido;

/**
 * Eventos do site que viram notificação no app do aluno.
 *
 * Cada método é chamado DEPOIS do commit da ação de origem (aprovar pedido,
 * corrigir avaliação...) e NUNCA lança exceção: qualquer falha — inclusive a
 * tabela app_notificacoes ainda não existir — vai para o log e a ação segue.
 * Textos em PT-BR; o payload leva só as chaves de contexto do tipo.
 */
class PushEventosService
{
    private $push;

    public function __construct(?PushService $push = null)
    {
        $this->push = $push;
    }

    private function push()
    {
        if ($this->push === null) {
            $this->push = new PushService();
        }
        return $this->push;
    }

    /** Pedido aprovado ou pago (aprovação manual, comprovante aprovado, gateway). */
    public function pedidoAprovado($pedidoId)
    {
        $this->seguro('pedido_aprovado', function () use ($pedidoId) {
            $pedido = (new Pedido())->findById((int) $pedidoId);
            if (!$pedido) {
                return;
            }
            $codigo = (string) ($pedido['codigo'] ?? $pedidoId);
            foreach ($this->donosDoPedido($pedido) as $usuarioId) {
                $this->push()->notificar(
                    $usuarioId,
                    'pedido_aprovado',
                    'Pagamento confirmado',
                    'Seu pedido ' . $codigo . ' foi aprovado. O acesso ao curso já está liberado.',
                    array('pedido_id' => (int) $pedido['id'])
                );
            }
        });
    }

    public function comprovanteReprovado($pedidoId)
    {
        $this->seguro('comprovante_reprovado', function () use ($pedidoId) {
            $pedido = (new Pedido())->findById((int) $pedidoId);
            if (!$pedido) {
                return;
            }
            $codigo = (string) ($pedido['codigo'] ?? $pedidoId);
            foreach ($this->donosDoPedido($pedido) as $usuarioId) {
                $this->push()->notificar(
                    $usuarioId,
                    'comprovante_reprovado',
                    'Comprovante não aprovado',
                    'O comprovante do pedido ' . $codigo . ' não foi aprovado. Confira o motivo e envie um novo comprovante.',
                    array('pedido_id' => (int) $pedido['id'])
                );
            }
        });
    }

    /** Avaliação textual corrigida/aprovada/reprovada/devolvida. */
    public function avaliacaoCorrigida($entregaId, $status)
    {
        $this->seguro('avaliacao_corrigida', function () use ($entregaId, $status) {
            $entrega = (new ConteudoAvaliacaoEntrega())->findById((int) $entregaId);
            if (!$entrega || empty($entrega['aluno_id'])) {
                return;
            }
            $item = (new ConteudoItem())->findById((int) $entrega['item_id']);
            $titulo = $item ? (string) $item['titulo'] : 'sua avaliação';
            $corpo = (string) $status === 'devolvida'
                ? 'Sua avaliação "' . $titulo . '" foi devolvida. Veja o retorno e envie de novo.'
                : 'Sua avaliação "' . $titulo . '" foi corrigida. Veja a nota e o retorno.';
            $this->push()->notificar(
                (int) $entrega['aluno_id'],
                'avaliacao_corrigida',
                (string) $status === 'devolvida' ? 'Avaliação devolvida' : 'Avaliação corrigida',
                $corpo,
                array('inscricao_id' => (int) $entrega['inscricao_id'], 'item_id' => (int) $entrega['item_id'])
            );
        });
    }

    /** Atividade (área do curso legada) corrigida ou devolvida. */
    public function atividadeCorrigida($entregaId, $status)
    {
        $this->seguro('atividade_corrigida', function () use ($entregaId, $status) {
            $entrega = (new AtividadeEntrega())->findById((int) $entregaId);
            if (!$entrega || empty($entrega['usuario_id'])) {
                return;
            }
            $atividade = (new Atividade())->findById((int) $entrega['atividade_id']);
            $titulo = $atividade ? (string) $atividade['titulo'] : 'sua atividade';
            $devolvida = (string) $status === 'devolvida';
            $dados = array();
            $inscricaoId = (new Inscricao())->idMaisRecenteDoUsuarioNoCurso((int) $entrega['usuario_id'], (int) $entrega['curso_evento_id']);
            if ($inscricaoId) {
                $dados['inscricao_id'] = $inscricaoId;
            }
            $this->push()->notificar(
                (int) $entrega['usuario_id'],
                'atividade_corrigida',
                $devolvida ? 'Atividade devolvida' : 'Atividade corrigida',
                $devolvida
                    ? 'Sua atividade "' . $titulo . '" foi devolvida para ajustes. Veja o retorno.'
                    : 'Sua atividade "' . $titulo . '" foi corrigida. Veja a nota e o retorno.',
                $dados
            );
        });
    }

    /** Quiz com discursivas: notifica quando todas foram corrigidas. */
    public function quizCorrigido($tentativaId)
    {
        $this->seguro('quiz_corrigido', function () use ($tentativaId) {
            $tentativa = (new ConteudoQuizTentativa())->findById((int) $tentativaId);
            if (!$tentativa || (string) ($tentativa['discursiva_status'] ?? '') !== 'corrigida') {
                return;
            }
            $quiz = (new ConteudoQuiz())->findById((int) $tentativa['quiz_id']);
            $item = $quiz ? (new ConteudoItem())->findById((int) $quiz['item_id']) : null;
            $titulo = $item ? (string) $item['titulo'] : 'seu quiz';
            $this->push()->notificar(
                (int) $tentativa['aluno_id'],
                'quiz_corrigido',
                'Quiz corrigido',
                'As questões discursivas de "' . $titulo . '" foram corrigidas. Veja o resultado.',
                array(
                    'inscricao_id' => (int) $tentativa['inscricao_id'],
                    'item_id' => $item ? (int) $item['id'] : null,
                    'tentativa_id' => (int) $tentativa['id'],
                )
            );
        });
    }

    public function certificadoEmitido($inscricaoId)
    {
        $this->seguro('certificado_emitido', function () use ($inscricaoId) {
            $inscricao = (new Inscricao())->findById((int) $inscricaoId);
            if (!$inscricao || empty($inscricao['usuario_id'])) {
                return;
            }
            $certificado = (new Certificado())->findByInscricao((int) $inscricaoId);
            $curso = (new CursoEvento())->findById((int) $inscricao['curso_evento_id']);
            $nomeCurso = $curso ? (string) $curso['nome'] : 'seu curso';
            $this->push()->notificar(
                (int) $inscricao['usuario_id'],
                'certificado_emitido',
                'Certificado emitido',
                'Seu certificado de "' . $nomeCurso . '" está disponível.',
                array(
                    'inscricao_id' => (int) $inscricao['id'],
                    'certificado_codigo' => $certificado && (string) $certificado['status'] === 'emitido' ? (string) $certificado['codigo'] : null,
                )
            );
        });
    }

    /**
     * Item publicado: enfileira para todos os alunos com acesso ao curso. Não
     * envia na hora (o curso pode ter centenas de alunos); o cron envia.
     */
    public function conteudoNovo($itemId)
    {
        $this->seguro('conteudo_novo', function () use ($itemId) {
            $item = (new ConteudoItem())->findById((int) $itemId);
            if (!$item || (string) $item['status'] !== 'publicado' || (string) $item['tipo'] === 'etiqueta') {
                return;
            }
            $modulo = (new ConteudoModulo())->findById((int) $item['modulo_id']);
            if (!$modulo || (string) $modulo['status'] !== 'publicado') {
                return;
            }
            $curso = (new CursoEvento())->findById((int) $item['curso_evento_id']);
            $nomeCurso = $curso ? (string) $curso['nome'] : 'seu curso';
            $acessos = (new Inscricao())->usuariosComAcessoAoCurso((int) $item['curso_evento_id']);
            if (empty($acessos)) {
                return;
            }
            // Cada aluno abre o item pela própria inscrição.
            $destinos = array();
            foreach ($acessos as $usuarioId => $inscricaoId) {
                $destinos[$usuarioId] = array('inscricao_id' => $inscricaoId);
            }
            $total = $this->push()->enfileirar(
                $destinos,
                'conteudo_novo',
                'Conteúdo novo',
                'Novo conteúdo em "' . $nomeCurso . '": ' . (string) $item['titulo'] . '.',
                array('item_id' => (int) $item['id'])
            );
            Logger::info('push.conteudo_novo.enfileirado', array('item_id' => (int) $item['id'], 'destinatarios' => $total));
        });
    }

    private function donosDoPedido(array $pedido)
    {
        $ids = array();
        foreach (array('comprador_usuario_id', 'pagador_usuario_id') as $campo) {
            if (!empty($pedido[$campo])) {
                $ids[(int) $pedido[$campo]] = true;
            }
        }

        return array_keys($ids);
    }

    private function seguro($evento, callable $acao)
    {
        try {
            $acao();
        } catch (\Throwable $e) {
            Logger::error('push.evento_falhou', array(
                'evento' => $evento,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ));
        }
    }
}
