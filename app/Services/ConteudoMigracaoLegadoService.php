<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use Throwable;

class ConteudoMigracaoLegadoService
{
    private $pdo;
    private $storageService;

    public function __construct($pdo = null, FileStorageService $storageService = null)
    {
        $this->pdo = $pdo ?: Database::connection();
        $this->storageService = $storageService ?: new FileStorageService();
    }

    public function diagnosticar()
    {
        $tabelas = array('modulos', 'aulas', 'materiais', 'links_externos', 'atividades', 'atividades_entregas');
        $contagens = array();

        foreach ($tabelas as $tabela) {
            $contagens[$tabela] = (int) $this->pdo->query('SELECT COUNT(*) FROM ' . $tabela . ' WHERE deleted_at IS NULL')->fetchColumn();
        }

        $diagnostico = array(
            'contagens' => $contagens,
            'cursos_afetados' => $this->buscarCursosAfetados(),
            'turmas_afetadas' => $this->buscarTurmasAfetadas(),
            'com_turma' => $this->contarComTurma(),
            'sem_turma' => $this->contarSemTurma(),
        );

        return $diagnostico;
    }

    public function simularMigracao($cursoEventoId = null)
    {
        $dados = $this->coletarLegado($cursoEventoId);

        $resumo = array(
            'curso_evento_id' => $cursoEventoId !== null ? (int) $cursoEventoId : null,
            'modulos_migrar' => 0,
            'modulos_ignorados' => 0,
            'aulas_migrar' => 0,
            'aulas_ignoradas' => 0,
            'materiais_migrar' => 0,
            'materiais_ignorados' => 0,
            'links_migrar' => 0,
            'links_ignorados' => 0,
            'atividades_migrar' => 0,
            'atividades_ignoradas' => 0,
            'inconsistencias' => 0,
            'cursos_afetados' => array(),
            'observacoes' => array(),
        );

        $modulosExistentes = array();
        foreach ($dados['modulos'] as $modulo) {
            $cursoId = (int) $modulo['curso_evento_id'];
            $modulosExistentes[(int) $modulo['id']] = true;
            $resumo['cursos_afetados'][$cursoId] = true;
            if ($this->jaMigrado('modulos', (int) $modulo['id'], 'conteudo_modulos')) {
                $resumo['modulos_ignorados']++;
            } else {
                $resumo['modulos_migrar']++;
            }
        }

        foreach ($dados['aulas'] as $aula) {
            if (empty($modulosExistentes[(int) $aula['modulo_id']])) {
                $resumo['inconsistencias']++;
                $resumo['observacoes'][] = 'Aula #' . (int) $aula['id'] . ' sem módulo legado válido.';
                continue;
            }
            if ($this->jaMigrado('aulas', (int) $aula['id'], 'conteudo_itens')) {
                $resumo['aulas_ignoradas']++;
            } else {
                $resumo['aulas_migrar']++;
            }
        }

        foreach ($dados['materiais'] as $material) {
            if ($this->jaMigrado('materiais', (int) $material['id'], 'conteudo_itens')) {
                $resumo['materiais_ignorados']++;
            } else {
                $resumo['materiais_migrar']++;
            }
        }

        foreach ($dados['links_externos'] as $link) {
            if ($this->jaMigrado('links_externos', (int) $link['id'], 'conteudo_itens')) {
                $resumo['links_ignorados']++;
            } else {
                $resumo['links_migrar']++;
            }
        }

        foreach ($dados['atividades'] as $atividade) {
            if ($this->jaMigrado('atividades', (int) $atividade['id'], 'conteudo_itens')) {
                $resumo['atividades_ignoradas']++;
            } else {
                $resumo['atividades_migrar']++;
            }
        }

        $resumo['cursos_afetados'] = array_values(array_map('intval', array_keys($resumo['cursos_afetados'])));
        sort($resumo['cursos_afetados']);

        return $resumo;
    }

    public function migrarTudo($cursoEventoId = null, $usuarioId = null)
    {
        $dados = $this->coletarLegado($cursoEventoId);
        $resultado = array(
            'curso_evento_id' => $cursoEventoId !== null ? (int) $cursoEventoId : null,
            'migrados' => array('modulos' => 0, 'aulas' => 0, 'materiais' => 0, 'links_externos' => 0, 'atividades' => 0),
            'ignorados' => array('modulos' => 0, 'aulas' => 0, 'materiais' => 0, 'links_externos' => 0, 'atividades' => 0),
            'erros' => array(),
        );

        $modulosPorCurso = array();
        foreach ($dados['modulos'] as $modulo) {
            $modulosPorCurso[(int) $modulo['curso_evento_id']][] = $modulo;
        }

        $aulasPorModulo = $this->agruparPorCampo($dados['aulas'], 'modulo_id');
        $materiaisPorCurso = $this->agruparPorCampo($dados['materiais'], 'curso_evento_id');
        $linksPorCurso = $this->agruparPorCampo($dados['links_externos'], 'curso_evento_id');
        $atividadesPorCurso = $this->agruparPorCampo($dados['atividades'], 'curso_evento_id');

        foreach ($modulosPorCurso as $cursoId => $modulosCurso) {
            foreach ($modulosCurso as $modulo) {
                $this->pdo->beginTransaction();
                try {
                    $moduloDestinoId = $this->migrarModulo($modulo, $usuarioId, $resultado);
                    $moduloLegadoId = (int) $modulo['id'];

                    if (!empty($aulasPorModulo[$moduloLegadoId])) {
                        foreach ($aulasPorModulo[$moduloLegadoId] as $aula) {
                            $this->migrarAula($aula, $moduloDestinoId, $usuarioId, $resultado);
                        }
                    }

                    $this->pdo->commit();
                } catch (Throwable $e) {
                    $this->pdo->rollBack();
                    $mensagem = 'Falha no módulo legado #' . (int) $modulo['id'] . ': ' . $e->getMessage();
                    $resultado['erros'][] = $mensagem;
                    $this->registrarErro('modulos', (int) $modulo['id'], (int) $modulo['curso_evento_id'], isset($modulo['turma_id']) ? (int) $modulo['turma_id'] : null, $mensagem);
                }
            }

            $fallbackModuloId = null;
            $materiaisCurso = isset($materiaisPorCurso[$cursoId]) ? $materiaisPorCurso[$cursoId] : array();
            foreach ($materiaisCurso as $material) {
                try {
                    $fallbackModuloId = $fallbackModuloId ?: $this->obterOuCriarModuloFallback($cursoId, $usuarioId);
                    $this->migrarMaterial($material, $fallbackModuloId, $usuarioId, $resultado);
                } catch (Throwable $e) {
                    $mensagem = 'Falha no material legado #' . (int) $material['id'] . ': ' . $e->getMessage();
                    $resultado['erros'][] = $mensagem;
                    $this->registrarErro('materiais', (int) $material['id'], (int) $material['curso_evento_id'], isset($material['turma_id']) ? (int) $material['turma_id'] : null, $mensagem);
                }
            }

            $linksCurso = isset($linksPorCurso[$cursoId]) ? $linksPorCurso[$cursoId] : array();
            foreach ($linksCurso as $link) {
                try {
                    $fallbackModuloId = $fallbackModuloId ?: $this->obterOuCriarModuloFallback($cursoId, $usuarioId);
                    $this->migrarLinkExterno($link, $fallbackModuloId, $usuarioId, $resultado);
                } catch (Throwable $e) {
                    $mensagem = 'Falha no link externo legado #' . (int) $link['id'] . ': ' . $e->getMessage();
                    $resultado['erros'][] = $mensagem;
                    $this->registrarErro('links_externos', (int) $link['id'], (int) $link['curso_evento_id'], isset($link['turma_id']) ? (int) $link['turma_id'] : null, $mensagem);
                }
            }

            $atividadesCurso = isset($atividadesPorCurso[$cursoId]) ? $atividadesPorCurso[$cursoId] : array();
            foreach ($atividadesCurso as $atividade) {
                try {
                    $fallbackModuloId = $fallbackModuloId ?: $this->obterOuCriarModuloFallback($cursoId, $usuarioId);
                    $this->migrarAtividade($atividade, $fallbackModuloId, $usuarioId, $resultado);
                } catch (Throwable $e) {
                    $mensagem = 'Falha na atividade legada #' . (int) $atividade['id'] . ': ' . $e->getMessage();
                    $resultado['erros'][] = $mensagem;
                    $this->registrarErro('atividades', (int) $atividade['id'], (int) $atividade['curso_evento_id'], isset($atividade['turma_id']) ? (int) $atividade['turma_id'] : null, $mensagem);
                }
            }
        }

        return $resultado;
    }

    public function migrarModulo($moduloAntigo, $usuarioId = null, array &$resultado = null)
    {
        $origemId = (int) $moduloAntigo['id'];
        if ($this->jaMigrado('modulos', $origemId, 'conteudo_modulos')) {
            if ($resultado !== null) {
                $resultado['ignorados']['modulos']++;
            }
            return (int) $this->buscarDestinoId('modulos', $origemId, 'conteudo_modulos');
        }

        $sql = 'INSERT INTO conteudo_modulos (curso_evento_id, titulo, descricao, ordem, status, criado_por, atualizado_por, created_at, updated_at, deleted_at)
                VALUES (:curso_evento_id, :titulo, :descricao, :ordem, :status, :criado_por, :atualizado_por, :created_at, :updated_at, NULL)';
        $stmt = $this->pdo->prepare($sql);

        $status = $this->mapearStatus($moduloAntigo);
        $createdAt = !empty($moduloAntigo['created_at']) ? $moduloAntigo['created_at'] : date('Y-m-d H:i:s');
        $updatedAt = !empty($moduloAntigo['updated_at']) ? $moduloAntigo['updated_at'] : $createdAt;

        $stmt->execute(array(
            'curso_evento_id' => (int) $moduloAntigo['curso_evento_id'],
            'titulo' => $this->normalizarTitulo(isset($moduloAntigo['titulo']) ? $moduloAntigo['titulo'] : ''),
            'descricao' => isset($moduloAntigo['descricao']) ? $moduloAntigo['descricao'] : null,
            'ordem' => isset($moduloAntigo['ordem']) ? (int) $moduloAntigo['ordem'] : 0,
            'status' => $status,
            'criado_por' => $this->resolverUsuario($moduloAntigo, 'criado_por', $usuarioId),
            'atualizado_por' => $this->resolverUsuario($moduloAntigo, 'atualizado_por', $usuarioId),
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
        ));

        $destinoId = (int) $this->pdo->lastInsertId();
        $observacao = null;
        if (!empty($moduloAntigo['turma_id'])) {
            $observacao = 'Origem possuía turma_id=' . (int) $moduloAntigo['turma_id'] . '; migrado para conteúdo global do curso conforme nova regra.';
        }

        $this->registrarMigracao('modulos', $origemId, 'conteudo_modulos', $destinoId, (int) $moduloAntigo['curso_evento_id'], isset($moduloAntigo['turma_id']) ? (int) $moduloAntigo['turma_id'] : null, 'modulo', 'migrado', $observacao);
        if ($resultado !== null) {
            $resultado['migrados']['modulos']++;
        }

        return $destinoId;
    }

    public function migrarAula($aulaAntiga, $conteudoModuloId, $usuarioId = null, array &$resultado = null)
    {
        $origemId = (int) $aulaAntiga['id'];
        if ($this->jaMigrado('aulas', $origemId, 'conteudo_itens')) {
            if ($resultado !== null) {
                $resultado['ignorados']['aulas']++;
            }
            return (int) $this->buscarDestinoId('aulas', $origemId, 'conteudo_itens');
        }

        $tipo = $this->mapearTipoAula($aulaAntiga);
        $itemId = $this->criarItemConteudo($aulaAntiga, $conteudoModuloId, $tipo, $usuarioId);

        if ($tipo === 'video') {
            $stmt = $this->pdo->prepare('INSERT INTO conteudo_videos (item_id, url, provedor, embed_html, duracao_segundos, created_at, updated_at)
                                         VALUES (:item_id, :url, :provedor, NULL, NULL, :created_at, :updated_at)');
            $stmt->execute(array(
                'item_id' => $itemId,
                'url' => isset($aulaAntiga['url_video']) ? trim((string) $aulaAntiga['url_video']) : '',
                'provedor' => $this->detectarProvedorVideo(isset($aulaAntiga['url_video']) ? (string) $aulaAntiga['url_video'] : ''),
                'created_at' => !empty($aulaAntiga['created_at']) ? $aulaAntiga['created_at'] : date('Y-m-d H:i:s'),
                'updated_at' => !empty($aulaAntiga['updated_at']) ? $aulaAntiga['updated_at'] : (!empty($aulaAntiga['created_at']) ? $aulaAntiga['created_at'] : date('Y-m-d H:i:s')),
            ));
        } else {
            $stmt = $this->pdo->prepare('INSERT INTO conteudo_textos (item_id, conteudo, created_at, updated_at)
                                         VALUES (:item_id, :conteudo, :created_at, :updated_at)');
            $stmt->execute(array(
                'item_id' => $itemId,
                'conteudo' => isset($aulaAntiga['conteudo']) ? $aulaAntiga['conteudo'] : null,
                'created_at' => !empty($aulaAntiga['created_at']) ? $aulaAntiga['created_at'] : date('Y-m-d H:i:s'),
                'updated_at' => !empty($aulaAntiga['updated_at']) ? $aulaAntiga['updated_at'] : (!empty($aulaAntiga['created_at']) ? $aulaAntiga['created_at'] : date('Y-m-d H:i:s')),
            ));
        }

        $observacao = null;
        if (!empty($aulaAntiga['turma_id'])) {
            $observacao = 'Origem possuía turma_id=' . (int) $aulaAntiga['turma_id'] . '; migrado para conteúdo global do curso conforme nova regra.';
        }

        $this->registrarMigracao('aulas', $origemId, 'conteudo_itens', $itemId, (int) $aulaAntiga['curso_evento_id'], isset($aulaAntiga['turma_id']) ? (int) $aulaAntiga['turma_id'] : null, $tipo, 'migrado', $observacao);
        if ($resultado !== null) {
            $resultado['migrados']['aulas']++;
        }

        return $itemId;
    }

    public function migrarMaterial($materialAntigo, $conteudoModuloId, $usuarioId = null, array &$resultado = null)
    {
        $origemId = (int) $materialAntigo['id'];
        if ($this->jaMigrado('materiais', $origemId, 'conteudo_itens')) {
            if ($resultado !== null) {
                $resultado['ignorados']['materiais']++;
            }
            return (int) $this->buscarDestinoId('materiais', $origemId, 'conteudo_itens');
        }

        $isLink = $this->materialEhLink($materialAntigo);
        $tipo = $isLink ? 'link' : 'arquivo';
        $itemId = $this->criarItemConteudo($materialAntigo, $conteudoModuloId, $tipo, $usuarioId, 0);
        $observacao = '';

        if ($isLink) {
            $url = trim((string) ($materialAntigo['url'] ?? ''));
            $modo = 'nova_aba';
            $statusItem = $this->validarUrl($url) ? $this->mapearStatus($materialAntigo) : 'rascunho';
            if ($statusItem !== $this->mapearStatus($materialAntigo)) {
                $this->atualizarStatusItem($itemId, $statusItem);
                $observacao = 'URL inválida no legado; item mantido em rascunho.';
            }

            $stmt = $this->pdo->prepare('INSERT INTO conteudo_links (item_id, url, modo_abertura, provedor, embed_html, created_at, updated_at)
                                         VALUES (:item_id, :url, :modo, :provedor, NULL, :created_at, :updated_at)');
            $stmt->execute(array(
                'item_id' => $itemId,
                'url' => $url !== '' ? $url : '#',
                'modo' => $modo,
                'provedor' => $this->detectarProvedorLink($url),
                'created_at' => !empty($materialAntigo['created_at']) ? $materialAntigo['created_at'] : date('Y-m-d H:i:s'),
                'updated_at' => !empty($materialAntigo['updated_at']) ? $materialAntigo['updated_at'] : (!empty($materialAntigo['created_at']) ? $materialAntigo['created_at'] : date('Y-m-d H:i:s')),
            ));
        } else {
            $arquivoDados = $this->migrarArquivoFisicoMaterial($materialAntigo, $itemId);
            $stmtArquivo = $this->pdo->prepare('INSERT INTO conteudo_arquivos (item_id, nome_original, nome_arquivo, caminho, mime_type, extensao, tamanho_bytes, versao_atual_id, permite_download, created_at, updated_at)
                                                VALUES (:item_id, :nome_original, :nome_arquivo, :caminho, :mime_type, :extensao, :tamanho_bytes, NULL, 1, :created_at, :updated_at)');
            $stmtArquivo->execute(array(
                'item_id' => $itemId,
                'nome_original' => $arquivoDados['nome_original'],
                'nome_arquivo' => $arquivoDados['nome_arquivo'],
                'caminho' => $arquivoDados['caminho'],
                'mime_type' => $arquivoDados['mime_type'],
                'extensao' => $arquivoDados['extensao'],
                'tamanho_bytes' => $arquivoDados['tamanho_bytes'],
                'created_at' => !empty($materialAntigo['created_at']) ? $materialAntigo['created_at'] : date('Y-m-d H:i:s'),
                'updated_at' => !empty($materialAntigo['updated_at']) ? $materialAntigo['updated_at'] : (!empty($materialAntigo['created_at']) ? $materialAntigo['created_at'] : date('Y-m-d H:i:s')),
            ));
            $arquivoId = (int) $this->pdo->lastInsertId();

            $stmtVersao = $this->pdo->prepare('INSERT INTO conteudo_arquivos_versoes (arquivo_id, item_id, nome_original, nome_arquivo, caminho, mime_type, extensao, tamanho_bytes, versao, substituido_por, criado_por, created_at)
                                               VALUES (:arquivo_id, :item_id, :nome_original, :nome_arquivo, :caminho, :mime_type, :extensao, :tamanho_bytes, 1, NULL, :criado_por, :created_at)');
            $stmtVersao->execute(array(
                'arquivo_id' => $arquivoId,
                'item_id' => $itemId,
                'nome_original' => $arquivoDados['nome_original'],
                'nome_arquivo' => $arquivoDados['nome_arquivo'],
                'caminho' => $arquivoDados['caminho'],
                'mime_type' => $arquivoDados['mime_type'],
                'extensao' => $arquivoDados['extensao'],
                'tamanho_bytes' => $arquivoDados['tamanho_bytes'],
                'criado_por' => $this->resolverUsuario($materialAntigo, 'criado_por', $usuarioId),
                'created_at' => !empty($materialAntigo['created_at']) ? $materialAntigo['created_at'] : date('Y-m-d H:i:s'),
            ));
            $versaoId = (int) $this->pdo->lastInsertId();
            $this->pdo->prepare('UPDATE conteudo_arquivos SET versao_atual_id = :versao_id WHERE id = :id')->execute(array('versao_id' => $versaoId, 'id' => $arquivoId));

            if (!empty($arquivoDados['observacao'])) {
                $observacao = trim($arquivoDados['observacao']);
            }
        }

        if (!empty($materialAntigo['turma_id'])) {
            $trechoTurma = 'Origem possuía turma_id=' . (int) $materialAntigo['turma_id'] . '; migrado para conteúdo global do curso conforme nova regra.';
            $observacao = $observacao !== '' ? ($observacao . ' ' . $trechoTurma) : $trechoTurma;
        }

        $this->registrarMigracao('materiais', $origemId, 'conteudo_itens', $itemId, (int) $materialAntigo['curso_evento_id'], isset($materialAntigo['turma_id']) ? (int) $materialAntigo['turma_id'] : null, $tipo, 'migrado', $observacao !== '' ? $observacao : null);
        if ($resultado !== null) {
            $resultado['migrados']['materiais']++;
        }

        return $itemId;
    }

    public function migrarLinkExterno($linkAntigo, $conteudoModuloId, $usuarioId = null, array &$resultado = null)
    {
        $origemId = (int) $linkAntigo['id'];
        if ($this->jaMigrado('links_externos', $origemId, 'conteudo_itens')) {
            if ($resultado !== null) {
                $resultado['ignorados']['links_externos']++;
            }
            return (int) $this->buscarDestinoId('links_externos', $origemId, 'conteudo_itens');
        }

        $itemId = $this->criarItemConteudo($linkAntigo, $conteudoModuloId, 'link', $usuarioId, 0);
        $url = trim((string) ($linkAntigo['url'] ?? ''));
        $observacao = null;

        if (!$this->validarUrl($url)) {
            $this->atualizarStatusItem($itemId, 'rascunho');
            $observacao = 'URL inválida no legado; item mantido em rascunho.';
        }

        $stmt = $this->pdo->prepare('INSERT INTO conteudo_links (item_id, url, modo_abertura, provedor, embed_html, created_at, updated_at)
                                     VALUES (:item_id, :url, :modo, :provedor, NULL, :created_at, :updated_at)');
        $stmt->execute(array(
            'item_id' => $itemId,
            'url' => $url !== '' ? $url : '#',
            'modo' => 'nova_aba',
            'provedor' => $this->detectarProvedorLink($url),
            'created_at' => !empty($linkAntigo['created_at']) ? $linkAntigo['created_at'] : date('Y-m-d H:i:s'),
            'updated_at' => !empty($linkAntigo['updated_at']) ? $linkAntigo['updated_at'] : (!empty($linkAntigo['created_at']) ? $linkAntigo['created_at'] : date('Y-m-d H:i:s')),
        ));

        if (!empty($linkAntigo['turma_id'])) {
            $trechoTurma = 'Origem possuía turma_id=' . (int) $linkAntigo['turma_id'] . '; migrado para conteúdo global do curso conforme nova regra.';
            $observacao = $observacao ? ($observacao . ' ' . $trechoTurma) : $trechoTurma;
        }

        $this->registrarMigracao('links_externos', $origemId, 'conteudo_itens', $itemId, (int) $linkAntigo['curso_evento_id'], isset($linkAntigo['turma_id']) ? (int) $linkAntigo['turma_id'] : null, 'link', 'migrado', $observacao);
        if ($resultado !== null) {
            $resultado['migrados']['links_externos']++;
        }

        return $itemId;
    }

    public function migrarAtividade($atividadeAntiga, $conteudoModuloId, $usuarioId = null, array &$resultado = null)
    {
        $origemId = (int) $atividadeAntiga['id'];
        if ($this->jaMigrado('atividades', $origemId, 'conteudo_itens')) {
            if ($resultado !== null) {
                $resultado['ignorados']['atividades']++;
            }
            return (int) $this->buscarDestinoId('atividades', $origemId, 'conteudo_itens');
        }

        $itemId = $this->criarItemConteudo($atividadeAntiga, $conteudoModuloId, 'avaliacao_textual', $usuarioId, $this->resolverObrigatorioAtividade($atividadeAntiga));
        $notaMaxima = isset($atividadeAntiga['nota_maxima']) && $atividadeAntiga['nota_maxima'] !== null ? (float) $atividadeAntiga['nota_maxima'] : 10.0;
        $descricao = isset($atividadeAntiga['descricao']) ? (string) $atividadeAntiga['descricao'] : '';

        $stmt = $this->pdo->prepare('INSERT INTO conteudo_avaliacoes_textuais (item_id, enunciado, orientacoes, nota_maxima, nota_minima, peso, prazo, permite_reenvio, reenvio_livre_ate_prazo, created_at, updated_at)
                                     VALUES (:item_id, :enunciado, :orientacoes, :nota_maxima, :nota_minima, :peso, :prazo, :permite_reenvio, :reenvio_livre_ate_prazo, :created_at, :updated_at)');
        $stmt->execute(array(
            'item_id' => $itemId,
            'enunciado' => $descricao !== '' ? $descricao : ('Atividade migrada: ' . (string) ($atividadeAntiga['titulo'] ?? '')),
            'orientacoes' => $descricao !== '' ? $descricao : null,
            'nota_maxima' => $notaMaxima,
            'nota_minima' => null,
            'peso' => 1,
            'prazo' => !empty($atividadeAntiga['prazo']) ? $atividadeAntiga['prazo'] : null,
            'permite_reenvio' => 1,
            'reenvio_livre_ate_prazo' => 1,
            'created_at' => !empty($atividadeAntiga['created_at']) ? $atividadeAntiga['created_at'] : date('Y-m-d H:i:s'),
            'updated_at' => !empty($atividadeAntiga['updated_at']) ? $atividadeAntiga['updated_at'] : (!empty($atividadeAntiga['created_at']) ? $atividadeAntiga['created_at'] : date('Y-m-d H:i:s')),
        ));

        $observacao = null;
        if (!empty($atividadeAntiga['turma_id'])) {
            $observacao = 'Origem possuía turma_id=' . (int) $atividadeAntiga['turma_id'] . '; migrado para conteúdo global do curso conforme nova regra.';
        }

        $this->registrarMigracao('atividades', $origemId, 'conteudo_itens', $itemId, (int) $atividadeAntiga['curso_evento_id'], isset($atividadeAntiga['turma_id']) ? (int) $atividadeAntiga['turma_id'] : null, 'avaliacao_textual', 'migrado', $observacao);
        if ($resultado !== null) {
            $resultado['migrados']['atividades']++;
        }

        return $itemId;
    }

    public function jaMigrado($origemTabela, $origemId, $destinoTabela)
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM conteudo_migracao_legado WHERE origem_tabela = :origem_tabela AND origem_id = :origem_id AND destino_tabela = :destino_tabela AND status = "migrado" LIMIT 1');
        $stmt->execute(array(
            'origem_tabela' => $origemTabela,
            'origem_id' => (int) $origemId,
            'destino_tabela' => $destinoTabela,
        ));

        return (bool) $stmt->fetchColumn();
    }

    public function registrarMigracao($origemTabela, $origemId, $destinoTabela, $destinoId, $cursoEventoId, $turmaId = null, $tipoDestino = null, $status = 'migrado', $observacao = null)
    {
        $sql = 'INSERT INTO conteudo_migracao_legado
                (origem_tabela, origem_id, destino_tabela, destino_id, curso_evento_id, turma_id, tipo_destino, status, observacao, created_at)
                VALUES
                (:origem_tabela, :origem_id, :destino_tabela, :destino_id, :curso_evento_id, :turma_id, :tipo_destino, :status, :observacao, :created_at)
                ON DUPLICATE KEY UPDATE destino_id = VALUES(destino_id), status = VALUES(status), observacao = VALUES(observacao)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array(
            'origem_tabela' => $origemTabela,
            'origem_id' => (int) $origemId,
            'destino_tabela' => $destinoTabela,
            'destino_id' => (int) $destinoId,
            'curso_evento_id' => (int) $cursoEventoId,
            'turma_id' => $turmaId !== null ? (int) $turmaId : null,
            'tipo_destino' => $tipoDestino,
            'status' => $status,
            'observacao' => $observacao,
            'created_at' => date('Y-m-d H:i:s'),
        ));
    }

    public function registrarErro($origemTabela, $origemId, $cursoEventoId, $turmaId = null, $observacao = null)
    {
        $this->registrarMigracao($origemTabela, (int) $origemId, 'erro_migracao', 0, (int) $cursoEventoId, $turmaId, null, 'erro', $observacao);
    }

    private function coletarLegado($cursoEventoId = null)
    {
        $where = ' WHERE deleted_at IS NULL';
        $params = array();
        if ($cursoEventoId !== null) {
            $where .= ' AND curso_evento_id = :curso_id';
            $params['curso_id'] = (int) $cursoEventoId;
        }

        $dados = array();
        $dados['modulos'] = $this->fetchAll('SELECT * FROM modulos' . $where . ' ORDER BY curso_evento_id, turma_id IS NULL DESC, ordem, id', $params);
        $dados['aulas'] = $this->fetchAll('SELECT * FROM aulas' . $where . ' ORDER BY curso_evento_id, modulo_id, ordem, id', $params);
        $dados['materiais'] = $this->fetchAll('SELECT * FROM materiais' . $where . ' ORDER BY curso_evento_id, modulo_id, ordem, id', $params);
        $dados['links_externos'] = $this->fetchAll('SELECT * FROM links_externos' . $where . ' ORDER BY curso_evento_id, modulo_id, ordem, id', $params);
        $dados['atividades'] = $this->fetchAll('SELECT * FROM atividades' . $where . ' ORDER BY curso_evento_id, modulo_id, ordem, id', $params);
        $dados['atividades_entregas'] = $this->fetchAll('SELECT * FROM atividades_entregas' . $where . ' ORDER BY curso_evento_id, atividade_id, id', $params);

        return $dados;
    }

    private function buscarCursosAfetados()
    {
        $sql = 'SELECT DISTINCT curso_evento_id FROM (
                    SELECT curso_evento_id FROM modulos WHERE deleted_at IS NULL
                    UNION SELECT curso_evento_id FROM aulas WHERE deleted_at IS NULL
                    UNION SELECT curso_evento_id FROM materiais WHERE deleted_at IS NULL
                    UNION SELECT curso_evento_id FROM links_externos WHERE deleted_at IS NULL
                    UNION SELECT curso_evento_id FROM atividades WHERE deleted_at IS NULL
                ) x ORDER BY curso_evento_id';
        return array_map('intval', $this->pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN));
    }

    private function buscarTurmasAfetadas()
    {
        $sql = 'SELECT DISTINCT turma_id FROM (
                    SELECT turma_id FROM modulos WHERE deleted_at IS NULL
                    UNION SELECT turma_id FROM aulas WHERE deleted_at IS NULL
                    UNION SELECT turma_id FROM materiais WHERE deleted_at IS NULL
                    UNION SELECT turma_id FROM links_externos WHERE deleted_at IS NULL
                    UNION SELECT turma_id FROM atividades WHERE deleted_at IS NULL
                ) x WHERE turma_id IS NOT NULL ORDER BY turma_id';
        return array_map('intval', $this->pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN));
    }

    private function contarComTurma()
    {
        $tabelas = array('modulos', 'aulas', 'materiais', 'links_externos', 'atividades');
        $resultado = array();
        foreach ($tabelas as $tabela) {
            $resultado[$tabela] = (int) $this->pdo->query('SELECT COUNT(*) FROM ' . $tabela . ' WHERE deleted_at IS NULL AND turma_id IS NOT NULL')->fetchColumn();
        }
        return $resultado;
    }

    private function contarSemTurma()
    {
        $tabelas = array('modulos', 'aulas', 'materiais', 'links_externos', 'atividades');
        $resultado = array();
        foreach ($tabelas as $tabela) {
            $resultado[$tabela] = (int) $this->pdo->query('SELECT COUNT(*) FROM ' . $tabela . ' WHERE deleted_at IS NULL AND turma_id IS NULL')->fetchColumn();
        }
        return $resultado;
    }

    private function criarItemConteudo(array $origem, $moduloId, $tipo, $usuarioId = null, $obrigatorio = null)
    {
        $sql = 'INSERT INTO conteudo_itens
                (curso_evento_id, modulo_id, tipo, titulo, descricao_curta, obrigatorio, ordem, status, abre_em, criado_por, atualizado_por, created_at, updated_at, deleted_at)
                VALUES
                (:curso_evento_id, :modulo_id, :tipo, :titulo, :descricao_curta, :obrigatorio, :ordem, :status, :abre_em, :criado_por, :atualizado_por, :created_at, :updated_at, NULL)';
        $stmt = $this->pdo->prepare($sql);

        $stmt->execute(array(
            'curso_evento_id' => (int) $origem['curso_evento_id'],
            'modulo_id' => (int) $moduloId,
            'tipo' => $tipo,
            'titulo' => $this->normalizarTitulo(isset($origem['titulo']) ? $origem['titulo'] : ''),
            'descricao_curta' => $this->resolverDescricaoCurta($origem),
            'obrigatorio' => $obrigatorio !== null ? (int) $obrigatorio : $this->resolverObrigatorio($origem),
            'ordem' => isset($origem['ordem']) ? (int) $origem['ordem'] : 0,
            'status' => $this->mapearStatus($origem),
            'abre_em' => $tipo === 'link' ? 'nova_aba' : 'mesma_pagina',
            'criado_por' => $this->resolverUsuario($origem, 'criado_por', $usuarioId),
            'atualizado_por' => $this->resolverUsuario($origem, 'atualizado_por', $usuarioId),
            'created_at' => !empty($origem['created_at']) ? $origem['created_at'] : date('Y-m-d H:i:s'),
            'updated_at' => !empty($origem['updated_at']) ? $origem['updated_at'] : (!empty($origem['created_at']) ? $origem['created_at'] : date('Y-m-d H:i:s')),
        ));

        return (int) $this->pdo->lastInsertId();
    }

    private function mapearStatus(array $origem)
    {
        $statusOrigem = strtolower(trim((string) ($origem['status'] ?? '')));
        $visivel = array_key_exists('visivel', $origem) ? (int) $origem['visivel'] : 1;

        if ($statusOrigem === 'arquivado') {
            return 'arquivado';
        }
        if ($statusOrigem === 'oculto' || $visivel === 0 || $statusOrigem === 'inativo') {
            return 'oculto';
        }
        if ($statusOrigem === 'rascunho' || $statusOrigem === 'draft') {
            return 'rascunho';
        }
        if ($statusOrigem === 'publicado' || $statusOrigem === 'ativo' || $statusOrigem === '') {
            return 'publicado';
        }

        return 'publicado';
    }

    private function mapearTipoAula(array $aula)
    {
        $tipo = strtolower(trim((string) ($aula['tipo'] ?? 'texto')));
        $urlVideo = trim((string) ($aula['url_video'] ?? ''));

        if ($urlVideo !== '') {
            return 'video';
        }

        if (strpos($tipo, 'video') !== false) {
            return 'video';
        }

        return 'texto';
    }

    private function materialEhLink(array $material)
    {
        $tipoMaterial = strtolower(trim((string) ($material['tipo_material'] ?? '')));
        $url = trim((string) ($material['url'] ?? ''));
        if ($url !== '' && ($tipoMaterial === '' || strpos($tipoMaterial, 'link') !== false || strpos($tipoMaterial, 'url') !== false)) {
            return true;
        }
        return strpos($tipoMaterial, 'link') !== false;
    }

    private function resolverObrigatorio(array $origem)
    {
        if (isset($origem['obrigatoria'])) {
            return (int) $origem['obrigatoria'] ? 1 : 0;
        }
        if (isset($origem['obrigatorio'])) {
            return (int) $origem['obrigatorio'] ? 1 : 0;
        }
        return 0;
    }

    private function resolverObrigatorioAtividade(array $atividade)
    {
        if (isset($atividade['obrigatoria'])) {
            return (int) $atividade['obrigatoria'] ? 1 : 0;
        }
        if (isset($atividade['obrigatorio'])) {
            return (int) $atividade['obrigatorio'] ? 1 : 0;
        }
        return 0;
    }

    private function resolverDescricaoCurta(array $origem)
    {
        if (!empty($origem['descricao'])) {
            return mb_substr((string) $origem['descricao'], 0, 400);
        }
        if (!empty($origem['conteudo'])) {
            return mb_substr(strip_tags((string) $origem['conteudo']), 0, 400);
        }
        return null;
    }

    private function normalizarTitulo($titulo)
    {
        $titulo = trim((string) $titulo);
        return $titulo !== '' ? $titulo : 'Conteúdo migrado sem título';
    }

    private function resolverUsuario(array $origem, $chave, $fallback = null)
    {
        if (!empty($origem[$chave])) {
            return (int) $origem[$chave];
        }
        return $fallback !== null ? (int) $fallback : null;
    }

    private function validarUrl($url)
    {
        if ($url === null) {
            return false;
        }
        $url = trim((string) $url);
        if ($url === '') {
            return false;
        }
        return (bool) filter_var($url, FILTER_VALIDATE_URL);
    }

    private function detectarProvedorVideo($url)
    {
        $host = (string) parse_url((string) $url, PHP_URL_HOST);
        if ($host === '') {
            return null;
        }
        $host = strtolower($host);
        if (strpos($host, 'youtube') !== false || strpos($host, 'youtu.be') !== false) {
            return 'youtube';
        }
        if (strpos($host, 'vimeo') !== false) {
            return 'vimeo';
        }
        return 'externo';
    }

    private function detectarProvedorLink($url)
    {
        $host = (string) parse_url((string) $url, PHP_URL_HOST);
        if ($host === '') {
            return null;
        }
        return strtolower($host);
    }

    private function atualizarStatusItem($itemId, $status)
    {
        $stmt = $this->pdo->prepare('UPDATE conteudo_itens SET status = :status, updated_at = :updated_at WHERE id = :id');
        $stmt->execute(array(
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s'),
            'id' => (int) $itemId,
        ));
    }

    private function obterOuCriarModuloFallback($cursoId, $usuarioId = null)
    {
        $origemIdSintetico = 900000000 + (int) $cursoId;
        $destinoId = $this->buscarDestinoId('modulos_fallback_curso', $origemIdSintetico, 'conteudo_modulos');
        if ($destinoId) {
            return (int) $destinoId;
        }

        $stmt = $this->pdo->prepare('SELECT id FROM conteudo_modulos WHERE curso_evento_id = :curso AND titulo = :titulo AND deleted_at IS NULL ORDER BY id ASC LIMIT 1');
        $titulo = 'Conteúdo migrado (sem módulo legado)';
        $stmt->execute(array('curso' => (int) $cursoId, 'titulo' => $titulo));
        $existente = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($existente && !empty($existente['id'])) {
            $this->registrarMigracao('modulos_fallback_curso', $origemIdSintetico, 'conteudo_modulos', (int) $existente['id'], (int) $cursoId, null, 'modulo_fallback', 'migrado', 'Módulo técnico criado/reutilizado para conteúdos legados sem módulo.');
            return (int) $existente['id'];
        }

        $ordem = (int) $this->pdo->query('SELECT COALESCE(MAX(ordem),0)+1 FROM conteudo_modulos WHERE curso_evento_id = ' . (int) $cursoId . ' AND deleted_at IS NULL')->fetchColumn();
        $ins = $this->pdo->prepare('INSERT INTO conteudo_modulos (curso_evento_id, titulo, descricao, ordem, status, criado_por, atualizado_por, created_at, updated_at, deleted_at)
                                    VALUES (:curso, :titulo, :descricao, :ordem, :status, :criado_por, :atualizado_por, :created_at, :updated_at, NULL)');
        $agora = date('Y-m-d H:i:s');
        $ins->execute(array(
            'curso' => (int) $cursoId,
            'titulo' => $titulo,
            'descricao' => 'Módulo gerado automaticamente para migração de conteúdos legados sem vínculo de módulo.',
            'ordem' => $ordem,
            'status' => 'rascunho',
            'criado_por' => $usuarioId !== null ? (int) $usuarioId : null,
            'atualizado_por' => $usuarioId !== null ? (int) $usuarioId : null,
            'created_at' => $agora,
            'updated_at' => $agora,
        ));
        $novoId = (int) $this->pdo->lastInsertId();
        $this->registrarMigracao('modulos_fallback_curso', $origemIdSintetico, 'conteudo_modulos', $novoId, (int) $cursoId, null, 'modulo_fallback', 'migrado', 'Módulo técnico criado/reutilizado para conteúdos legados sem módulo.');

        return $novoId;
    }

    private function buscarDestinoId($origemTabela, $origemId, $destinoTabela)
    {
        $stmt = $this->pdo->prepare('SELECT destino_id FROM conteudo_migracao_legado WHERE origem_tabela = :origem_tabela AND origem_id = :origem_id AND destino_tabela = :destino_tabela AND status = "migrado" ORDER BY id DESC LIMIT 1');
        $stmt->execute(array(
            'origem_tabela' => $origemTabela,
            'origem_id' => (int) $origemId,
            'destino_tabela' => $destinoTabela,
        ));
        $valor = $stmt->fetchColumn();
        return $valor !== false ? (int) $valor : null;
    }

    private function migrarArquivoFisicoMaterial(array $material, $itemId)
    {
        $nomeOriginal = !empty($material['arquivo_nome_original']) ? (string) $material['arquivo_nome_original'] : (string) ($material['titulo'] ?? 'arquivo');
        $nomeArquivo = !empty($material['arquivo_nome_original']) ? basename((string) $material['arquivo_nome_original']) : basename((string) ($material['arquivo_caminho'] ?? 'arquivo'));
        $mime = !empty($material['arquivo_mime_type']) ? (string) $material['arquivo_mime_type'] : null;
        $tamanho = !empty($material['arquivo_tamanho_bytes']) ? (int) $material['arquivo_tamanho_bytes'] : null;
        $caminhoOrigem = trim((string) ($material['arquivo_caminho'] ?? ''));
        $observacao = '';
        $caminhoFinal = $caminhoOrigem !== '' ? $caminhoOrigem : null;

        $arquivoFonte = $this->resolverArquivoFonte($caminhoOrigem);
        if ($arquivoFonte && is_file($arquivoFonte)) {
            $ext = strtolower(pathinfo($nomeArquivo, PATHINFO_EXTENSION));
            $destinoRelativo = 'conteudos/itens/' . (int) $itemId;
            $destinoAbsolutoDir = $this->storageService->privatePath($destinoRelativo);
            if (!is_dir($destinoAbsolutoDir)) {
                @mkdir($destinoAbsolutoDir, 0775, true);
            }
            $nomeSeguro = 'migrado-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
            if ($ext !== '') {
                $nomeSeguro .= '.' . $ext;
            }
            $destinoRelativoArquivo = $destinoRelativo . '/' . $nomeSeguro;
            $destinoAbsoluto = $this->storageService->privatePath($destinoRelativoArquivo);
            if (@copy($arquivoFonte, $destinoAbsoluto)) {
                $caminhoFinal = $destinoRelativoArquivo;
                $nomeArquivo = $nomeSeguro;
                if ($tamanho === null) {
                    $tamanho = @filesize($destinoAbsoluto) ?: null;
                }
                $observacao = 'Arquivo legado copiado para storage privado. Origem: ' . $caminhoOrigem;
            } else {
                $observacao = 'Falha ao copiar arquivo legado; mantido caminho original: ' . $caminhoOrigem;
            }
        } else {
            $observacao = 'Arquivo físico legado não localizado; item mantido com referência original.';
            $this->atualizarStatusItem($itemId, 'rascunho');
        }

        return array(
            'nome_original' => $nomeOriginal,
            'nome_arquivo' => $nomeArquivo !== '' ? $nomeArquivo : null,
            'caminho' => $caminhoFinal,
            'mime_type' => $mime,
            'extensao' => strtolower(pathinfo((string) $nomeArquivo, PATHINFO_EXTENSION)) ?: null,
            'tamanho_bytes' => $tamanho,
            'observacao' => $observacao,
        );
    }

    private function resolverArquivoFonte($caminho)
    {
        if ($caminho === null || trim((string) $caminho) === '') {
            return null;
        }
        $caminho = trim((string) $caminho);
        if (preg_match('/^[A-Za-z]:[\\\\\\/]/', $caminho) || strpos($caminho, '/') === 0) {
            return $caminho;
        }

        $candidatos = array(
            BASE_PATH . '/' . ltrim($caminho, '/\\'),
            BASE_PATH . '/public_html/' . ltrim($caminho, '/\\'),
            BASE_PATH . '/storage/private_uploads/' . ltrim($caminho, '/\\'),
        );
        foreach ($candidatos as $cand) {
            if (is_file($cand)) {
                return $cand;
            }
        }
        return null;
    }

    private function agruparPorCampo(array $linhas, $campo)
    {
        $saida = array();
        foreach ($linhas as $linha) {
            $chave = isset($linha[$campo]) ? (int) $linha[$campo] : 0;
            $saida[$chave][] = $linha;
        }
        return $saida;
    }

    private function fetchAll($sql, array $params = array())
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

