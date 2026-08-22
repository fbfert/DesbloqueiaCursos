<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Helpers;
use App\Core\Logger;
use App\Models\Avaliacao;
use App\Models\ConteudoAvaliacaoEntrega;
use App\Models\CursoEvento;
use App\Models\Inscricao;
use App\Models\NotaAvaliacao;
use App\Models\Modulo;
use App\Models\ProgressoUsuarioModulo;
use App\Models\Presenca;
use App\Models\Turma;
use Exception;

class AptidaoCertificadoService
{
    private $inscricaoModel;
    private $cursoModel;
    private $turmaModel;
    private $moduloModel;
    private $progressoModuloModel;
    private $presencaModel;
    private $avaliacaoModel;
    private $notaModel;
    private $entregaAvaliacaoTextualModel;
    private $auditService;
    private $scopeService;
    private $lmsElegibilidadeService;
    private $emailService;

    public function __construct(array $dependencies = array())
    {
        $this->inscricaoModel = isset($dependencies['inscricaoModel']) ? $dependencies['inscricaoModel'] : new Inscricao();
        $this->cursoModel = isset($dependencies['cursoModel']) ? $dependencies['cursoModel'] : new CursoEvento();
        $this->turmaModel = isset($dependencies['turmaModel']) ? $dependencies['turmaModel'] : new Turma();
        $this->moduloModel = isset($dependencies['moduloModel']) ? $dependencies['moduloModel'] : new Modulo();
        $this->progressoModuloModel = isset($dependencies['progressoModuloModel']) ? $dependencies['progressoModuloModel'] : new ProgressoUsuarioModulo();
        $this->presencaModel = isset($dependencies['presencaModel']) ? $dependencies['presencaModel'] : new Presenca();
        $this->avaliacaoModel = isset($dependencies['avaliacaoModel']) ? $dependencies['avaliacaoModel'] : new Avaliacao();
        $this->notaModel = isset($dependencies['notaModel']) ? $dependencies['notaModel'] : new NotaAvaliacao();
        $this->entregaAvaliacaoTextualModel = isset($dependencies['entregaAvaliacaoTextualModel']) ? $dependencies['entregaAvaliacaoTextualModel'] : new ConteudoAvaliacaoEntrega();
        $this->auditService = isset($dependencies['auditService']) ? $dependencies['auditService'] : new AuditService();
        $this->scopeService = isset($dependencies['scopeService']) ? $dependencies['scopeService'] : new ProfessorAcademicScopeService(array(
            'cursoModel' => $this->cursoModel,
            'turmaModel' => $this->turmaModel,
            'inscricaoModel' => $this->inscricaoModel,
            'avaliacaoModel' => $this->avaliacaoModel,
        ));
        $this->lmsElegibilidadeService = isset($dependencies['lmsElegibilidadeService']) ? $dependencies['lmsElegibilidadeService'] : new LmsElegibilidadeService();
        // EmailService constrói CertificadoService, que por sua vez constrói
        // AptidaoCertificadoService - instanciar EmailService aqui no
        // construtor causaria recursão infinita. Só é criado sob demanda em
        // notificarCertificadoApto(), quando realmente precisa enviar.
        $this->emailService = isset($dependencies['emailService']) ? $dependencies['emailService'] : null;
    }

    public function contexto($cursoId, $turmaId = null)
    {
        return array(
            'curso' => $cursoId ? $this->cursoModel->findById($cursoId) : null,
            'turma' => $turmaId ? $this->turmaModel->findById($turmaId) : null,
            'inscricoes' => $this->inscricaoModel->listForContext($cursoId, $turmaId),
            'avaliacoes' => $this->avaliacaoModel->listForContext($cursoId, $turmaId),
            'presencas' => $this->presencaModel->listForContext($cursoId, $turmaId),
        );
    }

    public function salvarConfiguracao(array $data, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $cursoId = isset($data['curso_evento_id']) ? (int) $data['curso_evento_id'] : 0;
        $turmaId = !empty($data['turma_id']) ? (int) $data['turma_id'] : null;

        if ($cursoId <= 0) {
            return array('ok' => false, 'message' => 'Curso invalido.');
        }

        $validacaoContexto = $this->scopeService->validarContexto($cursoId, $turmaId);
        if (empty($validacaoContexto['ok'])) {
            return $validacaoContexto;
        }

        $payload = array(
            'exige_presenca' => !empty($data['exige_presenca']) ? 1 : 0,
            'percentual_minimo_presenca' => isset($data['percentual_minimo_presenca']) ? (float) $data['percentual_minimo_presenca'] : 75.00,
            'percentual_minimo_conclusao' => isset($data['percentual_minimo_conclusao']) ? (float) $data['percentual_minimo_conclusao'] : 75.00,
            'exige_avaliacao' => !empty($data['exige_avaliacao']) ? 1 : 0,
            'nota_minima' => isset($data['nota_minima']) ? (float) $data['nota_minima'] : 70.00,
            'progresso_base' => isset($data['progresso_base']) ? trim((string) $data['progresso_base']) : 'aulas',
        );

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            if ($turmaId) {
                $stmt = $pdo->prepare(
                    'UPDATE turmas
                     SET exige_presenca = :exige_presenca,
                         percentual_minimo_presenca = :percentual_minimo_presenca,
                         percentual_minimo_conclusao = :percentual_minimo_conclusao,
                         exige_avaliacao = :exige_avaliacao,
                         nota_minima = :nota_minima,
                         progresso_base = :progresso_base,
                         updated_at = NOW()
                     WHERE id = :id'
                );
                $stmt->execute(array_merge($payload, array('id' => $turmaId)));
                $entidadeTipo = 'turma';
                $entidadeId = $turmaId;
            } else {
                $stmt = $pdo->prepare(
                    'UPDATE cursos_eventos
                     SET exige_presenca = :exige_presenca,
                         percentual_minimo_presenca = :percentual_minimo_presenca,
                         percentual_minimo_conclusao = :percentual_minimo_conclusao,
                         exige_avaliacao = :exige_avaliacao,
                         nota_minima = :nota_minima,
                         progresso_base = :progresso_base,
                         updated_at = NOW()
                     WHERE id = :id'
                );
                $stmt->execute(array_merge($payload, array('id' => $cursoId)));
                $entidadeTipo = 'curso_evento';
                $entidadeId = $cursoId;
            }

            $this->auditService->record(
                'academico.configuracao.atualizada',
                $entidadeTipo,
                $entidadeId,
                $payload,
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            Logger::info('academico.configuracao.atualizada', array(
                'entidade_tipo' => $entidadeTipo,
                'entidade_id' => $entidadeId,
            ));

            $pdo->commit();

            return array('ok' => true);
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('academico.configuracao.falhou', array('message' => $exception->getMessage()));
            throw $exception;
        }
    }

    public function recalcularInscricao($inscricaoId, $actorUserId = null, $ipAddress = null, $userAgent = null, $cursoId = null, $turmaId = null)
    {
        if ($cursoId !== null && $cursoId !== '') {
            $validacaoContexto = $this->scopeService->validarContexto($cursoId, $turmaId);
            if (empty($validacaoContexto['ok'])) {
                return $validacaoContexto;
            }

            $validacaoInscricao = $this->scopeService->validarInscricaoNoContexto($inscricaoId, $cursoId, $turmaId);
            if (empty($validacaoInscricao['ok'])) {
                return $validacaoInscricao;
            }
        }

        $inscricao = $this->inscricaoModel->findById($inscricaoId);

        if (!$inscricao) {
            return array('ok' => false, 'message' => 'Inscricao nao encontrada.');
        }

        $aptoAntes = !empty($inscricao['apto_certificado']) ? 1 : 0;

        $curso = $this->cursoModel->findById((int) $inscricao['curso_evento_id']);
        $turma = !empty($inscricao['turma_id']) ? $this->turmaModel->findById((int) $inscricao['turma_id']) : null;
        $config = $this->resolverConfiguracao($curso, $turma);

        $presencaPercentual = $this->calcularPresenca($inscricao);
        $notaFinal = $this->calcularNotaFinal($inscricao);
        $percentualProgresso = $this->calcularProgresso($inscricao, $config);

        $concluidaEm = !empty($inscricao['concluida_em']) ? $inscricao['concluida_em'] : null;
        if ($percentualProgresso >= (float) $config['percentual_minimo_conclusao'] && !$concluidaEm) {
            $concluidaEm = date('Y-m-d H:i:s');
        }

        $this->inscricaoModel->updateAcademico((int) $inscricaoId, array(
            'percentual_progresso' => $percentualProgresso,
            'presenca_percentual' => $presencaPercentual,
            'nota_final' => $notaFinal,
            'apto_certificado' => 0,
            'concluida_em' => $concluidaEm,
        ));

        $inscricaoAtualizada = $this->inscricaoModel->findById((int) $inscricaoId);
        $elegibilidade = $inscricaoAtualizada ? $this->lmsElegibilidadeService->calcularParaInscricao($inscricaoAtualizada) : null;
        $apto = (!empty($elegibilidade) && in_array((string) ($elegibilidade['situacao'] ?? ''), array('apto', 'certificado_emitido'), true)) ? 1 : 0;

        $this->inscricaoModel->updateAcademico((int) $inscricaoId, array(
            'apto_certificado' => $apto,
        ));

        $this->auditService->record(
            'academico.apto.recalculado',
            'inscricao',
            $inscricaoId,
            array(
                'percentual_progresso' => $percentualProgresso,
                'presenca_percentual' => $presencaPercentual,
                'nota_final' => $notaFinal,
                'apto_certificado' => $apto,
                'elegibilidade_situacao' => $elegibilidade['situacao'] ?? null,
                'elegibilidade_motivos' => $elegibilidade['motivos'] ?? array(),
            ),
            $actorUserId,
            $ipAddress,
            $userAgent
        );

        Logger::info('academico.apto.recalculado', array(
            'inscricao_id' => $inscricaoId,
            'apto_certificado' => $apto,
        ));

        // Notifica só na TRANSIÇÃO 0 -> 1 (a inscrição acabou de se tornar
        // apta agora), não a cada recálculo em que ela já era/continua apta -
        // senão o e-mail dispararia de novo a cada presença/avaliação
        // registrada depois disso.
        if ($aptoAntes === 0 && $apto === 1 && $inscricaoAtualizada) {
            $this->notificarCertificadoApto($inscricaoAtualizada, $curso, $turma);
        }

        return array(
            'ok' => true,
            'percentual_progresso' => $percentualProgresso,
            'presenca_percentual' => $presencaPercentual,
            'nota_final' => $notaFinal,
            'apto_certificado' => $apto,
            'concluida_em' => $concluidaEm,
        );
    }

    /**
     * Avisa o e-mail de certificados (Configurações Globais) quando uma
     * inscrição acaba de se tornar apta (transição 0 -> 1). `inscricoes` não
     * tem colunas de nome/e-mail próprias, então resolve o nome do
     * aluno/participante via join com `participantes_pedido`/`usuarios`,
     * mesmo padrão usado em `Certificado::listEligible()`.
     */
    private function notificarCertificadoApto(array $inscricao, ?array $curso, ?array $turma)
    {
        $stmt = Database::connection()->prepare(
            'SELECT COALESCE(u.nome, pp.nome) AS aluno_nome
             FROM inscricoes i
             LEFT JOIN participantes_pedido pp ON pp.id = i.participante_pedido_id
             LEFT JOIN usuarios u ON u.id = i.usuario_id
             WHERE i.id = :id
             LIMIT 1'
        );
        $stmt->bindValue(':id', (int) $inscricao['id'], \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();

        $contexto = array(
            'inscricao_id' => (int) $inscricao['id'],
            'curso_nome' => $curso && !empty($curso['nome']) ? $curso['nome'] : '',
            'turma_nome' => $turma && !empty($turma['nome']) ? $turma['nome'] : '',
            'aluno_nome' => $row && !empty($row['aluno_nome']) ? $row['aluno_nome'] : 'Aluno(a)',
            'link_emissao' => Helpers::url('/admin/certificados/emitir?inscricao_id=' . (int) $inscricao['id']),
        );

        $emailService = $this->emailService ?: new EmailService();
        $emailService->certificadoAptoParaEmissao($contexto);
    }

    private function resolverConfiguracao(?array $curso = null, ?array $turma = null)
    {
        $config = array(
            'exige_presenca' => 0,
            'percentual_minimo_presenca' => 75.00,
            'percentual_minimo_conclusao' => 75.00,
            'exige_avaliacao' => 0,
            'nota_minima' => 70.00,
            'progresso_base' => 'aulas',
        );

        if ($curso) {
            foreach ($config as $campo => $valor) {
                if (isset($curso[$campo]) && $curso[$campo] !== null) {
                    $config[$campo] = $curso[$campo];
                }
            }
        }

        if ($turma) {
            foreach ($config as $campo => $valor) {
                if (isset($turma[$campo]) && $turma[$campo] !== null) {
                    $config[$campo] = $turma[$campo];
                }
            }
        }

        return $config;
    }

    private function calcularPresenca(array $inscricao)
    {
        $presencas = $this->presencaModel->listForInscricao((int) $inscricao['id']);
        $presentes = 0;
        $total = 0;

        foreach ($presencas as $presenca) {
            $total++;
            if ($presenca['status'] === 'presente') {
                $presentes++;
            }
        }

        return $total > 0 ? round(($presentes / $total) * 100, 2) : 0.00;
    }

    private function calcularNotaFinal(array $inscricao)
    {
        $notas = $this->notaModel->listForInscricao((int) $inscricao['id']);

        if (empty($notas)) {
            // Curso sem avaliações no sistema legado (tabela `avaliacoes`):
            // usa as avaliações textuais do Conteúdo Unificado como
            // alternativa, para o critério "exigir avaliação" não ficar
            // permanentemente bloqueado em cursos montados só nele.
            return $this->calcularNotaFinalConteudoUnificado($inscricao);
        }

        $soma = 0;
        $quantidade = 0;
        foreach ($notas as $nota) {
            if ($nota['status'] === 'reprovada' && (float) $nota['nota'] <= 0) {
                continue;
            }
            $soma += (float) $nota['nota'];
            $quantidade++;
        }

        if ($quantidade === 0) {
            return null;
        }

        return round($soma / $quantidade, 2);
    }

    private function calcularNotaFinalConteudoUnificado(array $inscricao)
    {
        $alunoId = !empty($inscricao['usuario_id']) ? (int) $inscricao['usuario_id'] : 0;
        $inscricaoId = (int) ($inscricao['id'] ?? 0);
        if ($alunoId <= 0 || $inscricaoId <= 0) {
            return null;
        }

        $entregas = $this->entregaAvaliacaoTextualModel->listUltimasPorInscricao($alunoId, $inscricaoId);
        if (empty($entregas)) {
            return null;
        }

        $soma = 0;
        $quantidade = 0;
        foreach ($entregas as $entrega) {
            if ($entrega['nota'] === null) {
                continue;
            }
            if ((string) $entrega['status'] === 'reprovada' && (float) $entrega['nota'] <= 0) {
                continue;
            }
            $soma += (float) $entrega['nota'];
            $quantidade++;
        }

        if ($quantidade === 0) {
            return null;
        }

        return round($soma / $quantidade, 2);
    }

    private function calcularProgresso(array $inscricao, array $config)
    {
        if ($config['progresso_base'] === 'modulos') {
            $modulos = $this->moduloModel->listForContext((int) $inscricao['curso_evento_id'], !empty($inscricao['turma_id']) ? (int) $inscricao['turma_id'] : null);
            $total = 0;
            $concluidos = 0;

            foreach ($modulos as $modulo) {
                if (empty($modulo['visivel'])) {
                    continue;
                }

                $total++;
                $progresso = $this->progressoModuloModel->findByContext((int) $inscricao['id'], (int) $inscricao['usuario_id'], (int) $modulo['id']);
                if ($progresso && !empty($progresso['concluido'])) {
                    $concluidos++;
                }
            }

            return $total > 0 ? round(($concluidos / $total) * 100, 2) : 0.00;
        }

        return isset($inscricao['percentual_progresso']) ? (float) $inscricao['percentual_progresso'] : 0.00;
    }

    private function calcularApto(array $config, $percentualProgresso, $presencaPercentual, $notaFinal)
    {
        if ($percentualProgresso < (float) $config['percentual_minimo_conclusao']) {
            return 0;
        }

        if (!empty($config['exige_presenca']) && $presencaPercentual < (float) $config['percentual_minimo_presenca']) {
            return 0;
        }

        if (!empty($config['exige_avaliacao'])) {
            if ($notaFinal === null) {
                return 0;
            }

            if ($notaFinal < (float) $config['nota_minima']) {
                return 0;
            }
        }

        return 1;
    }
}




