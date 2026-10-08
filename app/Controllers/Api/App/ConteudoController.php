<?php

namespace App\Controllers\Api\App;

use App\Core\Request;
use App\Models\Inscricao;
use App\Services\ConteudoAcessoAlunoService;
use App\Services\ConteudoCursoService;
use App\Support\AppApi\ArquivoResposta;
use App\Support\AppApi\CursoPresenter;

/**
 * Itens do curso: abrir, concluir e baixar o arquivo. Mesmas regras do site
 * (ConteudoAcessoAlunoService, extraído de AreaCursoController).
 */
class ConteudoController extends AppController
{
    private $acesso;
    private $conteudo;

    public function __construct()
    {
        $this->conteudo = new ConteudoCursoService();
        $this->acesso = new ConteudoAcessoAlunoService(null, $this->conteudo);
    }

    public function item(Request $request)
    {
        $contexto = $this->inscricaoDoAluno($request, $this->acesso);
        if ($contexto === null) {
            return $this->semAcesso();
        }
        $inscricao = $contexto['inscricao'];
        $cursoId = (int) $contexto['curso_id'];
        $turmaId = (int) $contexto['turma_id'];
        $itemId = (int) $request->route('item', 0);

        $arvore = $this->conteudo->listarConteudoPublicadoAluno($cursoId, $this->usuarioId(), (int) $inscricao['id'], $turmaId > 0 ? $turmaId : null);
        $modulos = !empty($arvore['ok']) ? (array) $arvore['modulos'] : array();

        $detalhe = $this->conteudo->buscarItemPublicadoParaAluno($itemId, $this->usuarioId(), (int) $inscricao['id'], $cursoId, $turmaId > 0 ? $turmaId : null);
        if (empty($detalhe['ok'])) {
            return $this->naoEncontrado('Conteúdo não encontrado.');
        }
        $moduloId = (int) ($detalhe['modulo']['id'] ?? 0);

        $abertura = $this->acesso->abrirItem(
            $this->usuarioId(),
            $inscricao,
            $cursoId,
            $turmaId,
            $moduloId,
            $itemId,
            $detalhe,
            $request->ip(),
            $request->userAgent()
        );

        $item = $abertura['item'];
        // A avaliação textual tem o status pela última entrega, que só a árvore carrega.
        if ((string) $item['tipo'] === 'avaliacao_textual') {
            $daArvore = $this->itemDaArvore($modulos, $itemId);
            if ($daArvore !== null) {
                $item['status_publico'] = $daArvore['status_publico'] ?? $item['status_publico'];
            }
        }

        return $this->ok(CursoPresenter::itemAberto(
            (int) $inscricao['id'],
            $item,
            isset($abertura['detalhe']['detalhe']) && is_array($abertura['detalhe']['detalhe']) ? $abertura['detalhe']['detalhe'] : null,
            $this->acesso->navegacao($modulos, $itemId, $moduloId)
        ));
    }

    public function concluir(Request $request)
    {
        $contexto = $this->inscricaoDoAluno($request, $this->acesso);
        if ($contexto === null) {
            return $this->semAcesso();
        }
        $inscricao = $contexto['inscricao'];
        $cursoId = (int) $contexto['curso_id'];
        $turmaId = (int) $contexto['turma_id'];
        $itemId = (int) $request->route('item', 0);

        $detalhe = $this->conteudo->buscarItemPublicadoParaAluno($itemId, $this->usuarioId(), (int) $inscricao['id'], $cursoId, $turmaId > 0 ? $turmaId : null);
        if (empty($detalhe['ok'])) {
            return $this->naoEncontrado('Conteúdo não encontrado.');
        }

        $tipo = (string) $detalhe['item']['tipo'];
        if (in_array($tipo, array('quiz', 'avaliacao_textual'), true)) {
            return $this->erro(
                'nao_concluivel',
                $tipo === 'quiz' ? 'O quiz é concluído automaticamente ao ser enviado.' : 'A avaliação é concluída pela correção.',
                422
            );
        }

        $resultado = $this->conteudo->concluirItemAluno(array(
            'curso_evento_id' => $cursoId,
            'turma_id' => $turmaId > 0 ? $turmaId : null,
            'inscricao_id' => (int) $inscricao['id'],
            'aluno_id' => $this->usuarioId(),
            'item_id' => $itemId,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ));
        if (empty($resultado['ok'])) {
            return $this->erro('nao_concluivel', $resultado['message'] ?? 'Não foi possível concluir este conteúdo.', 422);
        }

        $atualizado = $this->conteudo->buscarItemPublicadoParaAluno($itemId, $this->usuarioId(), (int) $inscricao['id'], $cursoId, $turmaId > 0 ? $turmaId : null);
        $inscricaoAtual = (new Inscricao())->findById((int) $inscricao['id']);

        return $this->ok(array(
            'item' => CursoPresenter::itemArvore(!empty($atualizado['ok']) ? $atualizado['item'] : $detalhe['item']),
            'progresso_percentual' => (int) round((float) ($inscricaoAtual['percentual_progresso'] ?? 0)),
        ));
    }

    public function arquivo(Request $request)
    {
        $contexto = $this->inscricaoDoAluno($request, $this->acesso);
        if ($contexto === null) {
            return $this->semAcesso();
        }

        $arquivo = $this->acesso->arquivoDoItem(
            $this->usuarioId(),
            $contexto['inscricao'],
            (int) $request->route('item', 0),
            $request->ip(),
            $request->userAgent()
        );
        if (empty($arquivo['ok'])) {
            return $this->naoEncontrado('Arquivo não encontrado.');
        }

        return new ArquivoResposta($arquivo['caminho'], $arquivo['mime'], $arquivo['nome'], 'attachment');
    }

    private function itemDaArvore(array $modulos, $itemId)
    {
        foreach ($modulos as $modulo) {
            foreach ((array) ($modulo['itens'] ?? array()) as $item) {
                if ((int) $item['id'] === (int) $itemId) {
                    return $item;
                }
            }
        }
        return null;
    }
}
