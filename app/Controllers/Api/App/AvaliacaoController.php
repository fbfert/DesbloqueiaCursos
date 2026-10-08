<?php

namespace App\Controllers\Api\App;

use App\Core\Helpers;
use App\Core\Logger;
use App\Core\Request;
use App\Models\ConteudoAvaliacaoEntrega;
use App\Models\ConteudoAvaliacaoEntregaImagem;
use App\Services\ConteudoAvaliacaoTextualService;
use App\Services\ConteudoCursoService;
use App\Services\FileStorageService;
use App\Support\AppApi\ArquivoResposta;
use App\Support\AppApi\AvaliacaoPresenter;

/**
 * Avaliação textual (`item.tipo = avaliacao_textual`): estado, envio com
 * imagens (multipart) e imagens do próprio aluno.
 */
class AvaliacaoController extends AppController
{
    private $avaliacaoService;

    public function __construct()
    {
        $this->avaliacaoService = new ConteudoAvaliacaoTextualService();
    }

    public function estado(Request $request)
    {
        $ctx = $this->carregar($request);
        if (isset($ctx['erro'])) {
            return $ctx['erro'];
        }

        return $this->ok(AvaliacaoPresenter::estado(
            $ctx['avaliacao'],
            $this->entregas($ctx),
            $this->avaliacaoService->podeReenviar((int) $ctx['avaliacao']['id'], $this->usuarioId(), $ctx['inscricao_id'])
        ));
    }

    public function enviar(Request $request)
    {
        $ctx = $this->carregar($request);
        if (isset($ctx['erro'])) {
            return $ctx['erro'];
        }

        $texto = $request->input('texto', '');
        try {
            $resultado = $this->avaliacaoService->enviarResposta(array(
                'item_id' => $ctx['item_id'],
                'avaliacao_id' => (int) $ctx['avaliacao']['id'],
                'curso_evento_id' => $ctx['curso_id'],
                'turma_id' => $ctx['turma_id'] > 0 ? $ctx['turma_id'] : null,
                'inscricao_id' => $ctx['inscricao_id'],
                'aluno_id' => $this->usuarioId(),
                'resposta' => is_scalar($texto) ? (string) $texto : '',
                'imagens' => Helpers::normalizarUploadMultiplo(isset($_FILES['imagens']) ? $_FILES['imagens'] : null),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ));
        } catch (\InvalidArgumentException $e) {
            return $this->erro('validacao', $e->getMessage(), 422, array('imagens' => $e->getMessage()));
        }

        if (empty($resultado['ok'])) {
            $mensagem = $resultado['message'] ?? 'Não foi possível enviar a resposta.';
            $campo = stripos($mensagem, 'imagem') !== false ? 'imagens' : (stripos($mensagem, 'resposta') !== false ? 'texto' : null);
            return $this->erro('validacao', $mensagem, 422, $campo !== null ? array($campo => $mensagem) : array());
        }

        $entrega = (new ConteudoAvaliacaoEntrega())->findById((int) $resultado['id']);
        $entrega['imagens'] = $this->avaliacaoService->imagensEntrega((int) $resultado['id']);

        return $this->ok(AvaliacaoPresenter::entrega($entrega), 201);
    }

    public function imagem(Request $request)
    {
        $imagemId = (int) $request->route('id', 0);
        $imagem = (new ConteudoAvaliacaoEntregaImagem())->findById($imagemId);
        if (!$imagem) {
            return $this->naoEncontrado('Imagem não encontrada.');
        }

        $entrega = $this->avaliacaoService->buscarEntregaParaCorrecao((int) $imagem['entrega_id']);
        if (!$entrega || (int) $entrega['aluno_id'] !== $this->usuarioId()) {
            Logger::info('conteudo.avaliacao.imagem.bloqueio_acesso', array('contexto' => 'app', 'imagem_id' => $imagemId, 'usuario_id' => $this->usuarioId()));
            return $this->naoEncontrado('Imagem não encontrada.');
        }

        $caminho = (new FileStorageService())->privatePath((string) $imagem['caminho']);
        if (!is_file($caminho)) {
            return $this->naoEncontrado('Arquivo não encontrado.');
        }

        return new ArquivoResposta($caminho, (string) ($imagem['mime_type'] ?: 'application/octet-stream'), (string) $imagem['nome_original'], 'inline');
    }

    private function entregas(array $ctx)
    {
        $entregas = $this->avaliacaoService->listarEntregasAluno((int) $ctx['avaliacao']['id'], $this->usuarioId(), $ctx['inscricao_id']);
        foreach ($entregas as &$entrega) {
            $entrega['imagens'] = $this->avaliacaoService->imagensEntrega((int) $entrega['id']);
        }
        unset($entrega);

        return $entregas;
    }

    /** @return array contexto ou ['erro' => Response] */
    private function carregar(Request $request)
    {
        $contexto = $this->inscricaoDoAluno($request);
        if ($contexto === null) {
            return array('erro' => $this->semAcesso());
        }
        $inscricao = $contexto['inscricao'];
        $cursoId = (int) $contexto['curso_id'];
        $turmaId = (int) $contexto['turma_id'];
        $itemId = (int) $request->route('item', 0);

        $detalhe = (new ConteudoCursoService())->buscarItemPublicadoParaAluno($itemId, $this->usuarioId(), (int) $inscricao['id'], $cursoId, $turmaId > 0 ? $turmaId : null);
        if (empty($detalhe['ok']) || (string) ($detalhe['item']['tipo'] ?? '') !== 'avaliacao_textual' || empty($detalhe['detalhe'])) {
            return array('erro' => $this->naoEncontrado('Avaliação não encontrada.'));
        }

        return array(
            'inscricao_id' => (int) $inscricao['id'],
            'curso_id' => $cursoId,
            'turma_id' => $turmaId,
            'item_id' => $itemId,
            'avaliacao' => $detalhe['detalhe'],
        );
    }
}
