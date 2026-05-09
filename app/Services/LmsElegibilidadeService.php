<?php

namespace App\Services;

use App\Core\Database;
use App\Models\Inscricao;
use PDO;

class LmsElegibilidadeService
{
    private $inscricaoModel;
    private $criterioService;

    public function __construct()
    {
        $this->inscricaoModel = new Inscricao();
        $this->criterioService = new LmsCriterioConclusaoService();
    }

    public function calcularPorContexto($cursoId, $turmaId = null)
    {
        $inscricoes = $this->inscricaoModel->listForContext((int) $cursoId, $turmaId !== null ? (int) $turmaId : null);
        return $this->calcularPorInscricoes($inscricoes, (int) $cursoId, $turmaId !== null ? (int) $turmaId : null);
    }

    public function calcularParaInscricao(array $inscricao)
    {
        $inscricaoId = $this->resolverInscricaoId($inscricao);
        $cursoId = isset($inscricao['curso_evento_id']) ? (int) $inscricao['curso_evento_id'] : 0;
        if ($cursoId <= 0 || $inscricaoId <= 0) {
            return $this->vazio($cursoId, null);
        }
        $turmaId = !empty($inscricao['turma_id']) ? (int) $inscricao['turma_id'] : null;
        $resultado = $this->calcularPorInscricoes(array($inscricao), $cursoId, $turmaId);
        $mapa = $resultado['mapa_por_inscricao'];
        return isset($mapa[$inscricaoId]) ? $mapa[$inscricaoId] : $this->vazio($cursoId, $turmaId);
    }

    public function calcularPorInscricoes(array $inscricoes, $cursoId, $turmaId = null)
    {
        $criterios = $this->criterioService->resolver((int) $cursoId, $turmaId !== null ? (int) $turmaId : null);
        $metricasAtividade = $this->metricasAtividadePorUsuario((int) $cursoId, $turmaId !== null ? (int) $turmaId : null);
        $totaisPublicados = $this->totaisPublicados((int) $cursoId, $turmaId !== null ? (int) $turmaId : null);

        $mapa = array();
        $resumo = array(
            'em_andamento' => 0,
            'pendente' => 0,
            'apto' => 0,
            'nao_apto' => 0,
            'certificado_emitido' => 0,
        );

        foreach ($inscricoes as $inscricao) {
            $inscricaoId = $this->resolverInscricaoId($inscricao);
            if ($inscricaoId <= 0) {
                continue;
            }
            $usuarioId = !empty($inscricao['usuario_id']) ? (int) $inscricao['usuario_id'] : 0;
            $atividade = isset($metricasAtividade[$usuarioId]) ? $metricasAtividade[$usuarioId] : $this->metricasAtividadeVazio();
            $item = $this->avaliar($inscricao, $criterios, $atividade, $totaisPublicados);
            $mapa[$inscricaoId] = $item;
            if (isset($resumo[$item['situacao']])) {
                $resumo[$item['situacao']]++;
            }
        }

        return array(
            'criterios' => $criterios,
            'totais_publicados' => $totaisPublicados,
            'mapa_por_inscricao' => $mapa,
            'resumo' => $resumo,
        );
    }

    private function avaliar(array $inscricao, array $criterios, array $atividade, array $totaisPublicados)
    {
        $motivos = array();
        $flags = array('pendente' => false, 'nao_apto' => false);

        $progresso = isset($inscricao['percentual_progresso']) && $inscricao['percentual_progresso'] !== null ? (float) $inscricao['percentual_progresso'] : 0.00;
        $presenca = isset($inscricao['presenca_percentual']) && $inscricao['presenca_percentual'] !== null ? (float) $inscricao['presenca_percentual'] : null;
        $avaliacaoNota = isset($inscricao['nota_final']) && $inscricao['nota_final'] !== null ? (float) $inscricao['nota_final'] : null;
        $certificadoEmitido = !empty($inscricao['certificado_id']) || !empty($inscricao['certificado_codigo']) || (isset($inscricao['certificado_status']) && $inscricao['certificado_status'] === 'emitido');

        if (!empty($criterios['exigir_progresso'])) {
            if ($progresso < (float) $criterios['progresso_minimo']) {
                $flags['pendente'] = true;
                $motivos[] = sprintf('Progresso %.2f%% abaixo do mínimo de %.2f%%.', $progresso, (float) $criterios['progresso_minimo']);
            }
        }

        if (!empty($criterios['exigir_atividades'])) {
            $totalAtividades = (int) $totaisPublicados['atividades_publicadas'];
            if ($totalAtividades <= 0) {
                $flags['pendente'] = true;
                $motivos[] = 'Não há atividades publicadas para validação.';
            } else {
                if ((int) $atividade['devolvidas'] > 0) {
                    $flags['pendente'] = true;
                    $motivos[] = sprintf('%d atividade(s) devolvida(s) para ajuste.', (int) $atividade['devolvidas']);
                }
                if ((int) $atividade['pendentes_correcao'] > 0) {
                    $flags['pendente'] = true;
                    $motivos[] = sprintf('%d atividade(s) aguardando correção.', (int) $atividade['pendentes_correcao']);
                }

                if ($criterios['criterio_atividades'] === 'todas_enviadas' && (int) $atividade['enviadas'] < $totalAtividades) {
                    $flags['pendente'] = true;
                    $motivos[] = sprintf('Atividades enviadas: %d de %d.', (int) $atividade['enviadas'], $totalAtividades);
                }

                if ($criterios['criterio_atividades'] === 'todas_corrigidas' && (int) $atividade['corrigidas'] < $totalAtividades) {
                    $flags['pendente'] = true;
                    $motivos[] = sprintf('Atividades corrigidas: %d de %d.', (int) $atividade['corrigidas'], $totalAtividades);
                }

                if ($criterios['criterio_atividades'] === 'media_minima') {
                    if ((int) $atividade['corrigidas'] <= 0 || $atividade['media_notas'] === null) {
                        $flags['pendente'] = true;
                        $motivos[] = 'Ainda não há notas suficientes nas atividades.';
                    } elseif ($criterios['nota_minima_atividades'] !== null && (float) $atividade['media_notas'] < (float) $criterios['nota_minima_atividades']) {
                        $flags['nao_apto'] = true;
                        $motivos[] = sprintf('Média das atividades %.2f abaixo da mínima de %.2f.', (float) $atividade['media_notas'], (float) $criterios['nota_minima_atividades']);
                    }
                }
            }
        }

        if (!empty($criterios['exigir_presenca'])) {
            if ($presenca === null) {
                $flags['pendente'] = true;
                $motivos[] = 'Presença sem dado disponível para validação.';
            } elseif ($presenca < (float) $criterios['presenca_minima']) {
                $flags['pendente'] = true;
                $motivos[] = sprintf('Presença %.2f%% abaixo do mínimo de %.2f%%.', $presenca, (float) $criterios['presenca_minima']);
            }
        }

        if (!empty($criterios['exigir_avaliacao'])) {
            if ($avaliacaoNota === null) {
                $flags['pendente'] = true;
                $motivos[] = 'Avaliação sem nota registrada.';
            } elseif ($criterios['nota_minima_avaliacao'] !== null && $avaliacaoNota < (float) $criterios['nota_minima_avaliacao']) {
                $flags['nao_apto'] = true;
                $motivos[] = sprintf('Nota de avaliação %.2f abaixo da mínima de %.2f.', $avaliacaoNota, (float) $criterios['nota_minima_avaliacao']);
            }
        }

        if ($certificadoEmitido) {
            $situacao = 'certificado_emitido';
            $motivos[] = 'Certificado já emitido.';
        } elseif ($flags['nao_apto']) {
            $situacao = 'nao_apto';
        } elseif ($flags['pendente']) {
            $situacao = $progresso > 0 ? 'em_andamento' : 'pendente';
        } else {
            $situacao = 'apto';
            $motivos[] = 'Critérios de conclusão atingidos.';
        }

        return array(
            'inscricao_id' => $this->resolverInscricaoId($inscricao),
            'usuario_id' => !empty($inscricao['usuario_id']) ? (int) $inscricao['usuario_id'] : null,
            'curso_evento_id' => (int) $inscricao['curso_evento_id'],
            'turma_id' => !empty($inscricao['turma_id']) ? (int) $inscricao['turma_id'] : null,
            'total_aulas_publicadas' => (int) $totaisPublicados['aulas_publicadas'],
            'aulas_concluidas' => $this->aulasConcluidas($progresso, (int) $totaisPublicados['aulas_publicadas']),
            'percentual_progresso' => round($progresso, 2),
            'total_atividades_publicadas' => (int) $totaisPublicados['atividades_publicadas'],
            'atividades_enviadas' => (int) $atividade['enviadas'],
            'atividades_corrigidas' => (int) $atividade['corrigidas'],
            'atividades_pendentes' => (int) $atividade['pendentes_envio'] + (int) $atividade['pendentes_correcao'],
            'atividades_devolvidas' => (int) $atividade['devolvidas'],
            'atividades_atrasadas' => (int) $atividade['atrasadas'],
            'media_atividades' => $atividade['media_notas'] !== null ? round((float) $atividade['media_notas'], 2) : null,
            'presenca_percentual' => $presenca !== null ? round($presenca, 2) : null,
            'avaliacao_nota' => $avaliacaoNota !== null ? round($avaliacaoNota, 2) : null,
            'certificado_emitido' => $certificadoEmitido ? 1 : 0,
            'situacao' => $situacao,
            'motivos' => $motivos,
            'motivos_texto' => implode(' ', $motivos),
        );
    }

    private function resolverInscricaoId(array $inscricao)
    {
        if (isset($inscricao['id']) && (int) $inscricao['id'] > 0) {
            return (int) $inscricao['id'];
        }

        if (isset($inscricao['inscricao_id']) && (int) $inscricao['inscricao_id'] > 0) {
            return (int) $inscricao['inscricao_id'];
        }

        return 0;
    }

    private function totaisPublicados($cursoId, $turmaId = null)
    {
        $params = array('curso_id' => $cursoId);
        $clauseTurmaModulo = '';
        $clauseTurmaAula = '';
        $clauseTurmaAtividade = '';

        if ($turmaId !== null) {
            $params['turma_id'] = $turmaId;
            $clauseTurmaModulo = ' AND m.turma_id = :turma_id';
            $clauseTurmaAula = ' AND a.turma_id = :turma_id';
            $clauseTurmaAtividade = ' AND atv.turma_id = :turma_id';
        } else {
            $clauseTurmaModulo = ' AND m.turma_id IS NULL';
            $clauseTurmaAula = ' AND a.turma_id IS NULL';
            $clauseTurmaAtividade = ' AND atv.turma_id IS NULL';
        }

        $pdo = Database::connection();

        $sqlAulas = 'SELECT COUNT(*) AS total
                     FROM aulas a
                     INNER JOIN modulos m ON m.id = a.modulo_id AND m.deleted_at IS NULL
                     WHERE a.deleted_at IS NULL
                       AND a.curso_evento_id = :curso_id
                       AND a.status = "publicado"
                       AND m.status = "publicado"' . $clauseTurmaAula . $clauseTurmaModulo;

        $stmtAulas = $pdo->prepare($sqlAulas);
        $stmtAulas->execute($params);
        $aulas = (int) $stmtAulas->fetch(PDO::FETCH_ASSOC)['total'];

        $sqlAtividades = 'SELECT COUNT(*) AS total
                          FROM atividades atv
                          INNER JOIN aulas a ON a.id = atv.aula_id AND a.deleted_at IS NULL AND a.status = "publicado"
                          INNER JOIN modulos m ON m.id = atv.modulo_id AND m.deleted_at IS NULL AND m.status = "publicado"
                          WHERE atv.deleted_at IS NULL
                            AND atv.curso_evento_id = :curso_id
                            AND atv.status = "publicado"' . $clauseTurmaAtividade . $clauseTurmaAula . $clauseTurmaModulo;

        $stmtAtividades = $pdo->prepare($sqlAtividades);
        $stmtAtividades->execute($params);
        $atividades = (int) $stmtAtividades->fetch(PDO::FETCH_ASSOC)['total'];

        return array(
            'aulas_publicadas' => $aulas,
            'atividades_publicadas' => $atividades,
        );
    }

    private function metricasAtividadePorUsuario($cursoId, $turmaId = null)
    {
        $params = array('curso_id' => $cursoId);
        $clauseTurmaAtividade = '';
        $clauseTurmaAula = '';
        $clauseTurmaModulo = '';

        if ($turmaId !== null) {
            $params['turma_id'] = $turmaId;
            $clauseTurmaAtividade = ' AND atv.turma_id = :turma_id';
            $clauseTurmaAula = ' AND a.turma_id = :turma_id';
            $clauseTurmaModulo = ' AND m.turma_id = :turma_id';
        } else {
            $clauseTurmaAtividade = ' AND atv.turma_id IS NULL';
            $clauseTurmaAula = ' AND a.turma_id IS NULL';
            $clauseTurmaModulo = ' AND m.turma_id IS NULL';
        }

        $sql = 'SELECT i.usuario_id,
                       COUNT(DISTINCT atv.id) AS total_atividades,
                       COUNT(DISTINCT CASE WHEN ae.id IS NOT NULL THEN atv.id END) AS enviadas,
                       COUNT(DISTINCT CASE WHEN ae.status = "corrigida" THEN atv.id END) AS corrigidas,
                       COUNT(DISTINCT CASE WHEN ae.status = "devolvida" THEN atv.id END) AS devolvidas,
                       COUNT(DISTINCT CASE WHEN ae.status IN ("enviada", "revisao") THEN atv.id END) AS pendentes_correcao,
                       AVG(CASE WHEN ae.status = "corrigida" AND ae.nota IS NOT NULL THEN ae.nota END) AS media_notas,
                       COUNT(DISTINCT CASE
                            WHEN atv.prazo IS NOT NULL
                                 AND atv.prazo < NOW()
                                 AND (ae.id IS NULL OR ae.entregue_em > atv.prazo)
                            THEN atv.id
                       END) AS atrasadas
                FROM inscricoes i
                INNER JOIN atividades atv
                    ON atv.curso_evento_id = i.curso_evento_id
                   AND atv.deleted_at IS NULL
                   AND atv.status = "publicado"
                INNER JOIN aulas a
                    ON a.id = atv.aula_id
                   AND a.deleted_at IS NULL
                   AND a.status = "publicado"
                INNER JOIN modulos m
                    ON m.id = atv.modulo_id
                   AND m.deleted_at IS NULL
                   AND m.status = "publicado"
                LEFT JOIN atividades_entregas ae
                    ON ae.atividade_id = atv.id
                   AND ae.usuario_id = i.usuario_id
                   AND ae.deleted_at IS NULL
                WHERE i.deleted_at IS NULL
                  AND i.curso_evento_id = :curso_id' . $clauseTurmaAtividade . $clauseTurmaAula . $clauseTurmaModulo;

        if ($turmaId !== null) {
            $sql .= ' AND i.turma_id = :turma_id';
        } else {
            $sql .= ' AND i.turma_id IS NULL';
        }

        $sql .= ' GROUP BY i.usuario_id';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        $mapa = array();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $total = (int) $row['total_atividades'];
            $enviadas = (int) $row['enviadas'];
            $mapa[(int) $row['usuario_id']] = array(
                'total_atividades' => $total,
                'enviadas' => $enviadas,
                'corrigidas' => (int) $row['corrigidas'],
                'devolvidas' => (int) $row['devolvidas'],
                'pendentes_correcao' => (int) $row['pendentes_correcao'],
                'pendentes_envio' => $total > $enviadas ? ($total - $enviadas) : 0,
                'media_notas' => $row['media_notas'] !== null ? (float) $row['media_notas'] : null,
                'atrasadas' => (int) $row['atrasadas'],
            );
        }

        return $mapa;
    }

    private function aulasConcluidas($percentualProgresso, $totalAulasPublicadas)
    {
        if ($totalAulasPublicadas <= 0) {
            return 0;
        }

        $estimado = (int) round(($percentualProgresso / 100) * $totalAulasPublicadas);
        if ($estimado < 0) {
            return 0;
        }
        if ($estimado > $totalAulasPublicadas) {
            return $totalAulasPublicadas;
        }
        return $estimado;
    }

    private function metricasAtividadeVazio()
    {
        return array(
            'total_atividades' => 0,
            'enviadas' => 0,
            'corrigidas' => 0,
            'devolvidas' => 0,
            'pendentes_correcao' => 0,
            'pendentes_envio' => 0,
            'media_notas' => null,
            'atrasadas' => 0,
        );
    }

    private function vazio($cursoId, $turmaId = null)
    {
        return array(
            'inscricao_id' => null,
            'curso_evento_id' => (int) $cursoId,
            'turma_id' => $turmaId !== null ? (int) $turmaId : null,
            'situacao' => 'pendente',
            'motivos' => array('Inscrição não encontrada para o cálculo.'),
            'motivos_texto' => 'Inscrição não encontrada para o cálculo.',
        );
    }
}
