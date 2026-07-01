<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Models\Inscricao;
use App\Models\ParticipantePedido;
use App\Models\Pedido;
use App\Models\PedidoItem;
use PDO;

class SuperAdminTurmasService
{
    private $rbacService;
    private $auditService;
    private $pedidoModel;
    private $pedidoItemModel;
    private $participantePedidoModel;
    private $inscricaoModel;

    public function __construct()
    {
        $this->rbacService = new RbacService();
        $this->auditService = new AuditService();
        $this->pedidoModel = new Pedido();
        $this->pedidoItemModel = new PedidoItem();
        $this->participantePedidoModel = new ParticipantePedido();
        $this->inscricaoModel = new Inscricao();
    }

    public function preview()
    {
        $superadmins = $this->loadActiveSuperAdmins();
        $turmas = $this->loadEligibleTurmas();
        $existingMap = $this->loadExistingLinksMap($superadmins, $turmas);

        return $this->buildPreviewPayload($superadmins, $turmas, $existingMap);
    }

    public function sincronizar($actorUserId, $ipAddress = null, $userAgent = null)
    {
        if (!$this->rbacService->isSuperAdmin($actorUserId)) {
            return array(
                'ok' => false,
                'message' => 'Somente o superadministrador pode executar esta sincronização.',
            );
        }

        $pdo = Database::connection();

        try {
            $pdo->beginTransaction();

            $superadmins = $this->loadActiveSuperAdmins();
            $turmas = $this->loadEligibleTurmas();
            $existingMap = $this->loadExistingLinksMap($superadmins, $turmas);

            $totalCriadas = 0;
            $totalIgnoradas = 0;
            $pedidosCriados = 0;
            $superadminsProcessados = 0;

            foreach ($superadmins as $superadmin) {
                $pedidoId = null;
                $criouAlgumaInscricao = false;
                $usuarioId = (int) $superadmin['id'];

                foreach ($turmas as $turma) {
                    $turmaId = (int) $turma['id'];
                    $linkKey = $this->buildLinkKey($usuarioId, $turmaId);

                    if (isset($existingMap[$linkKey]) || $this->inscricaoModel->findByUsuarioTurma($usuarioId, $turmaId)) {
                        $totalIgnoradas++;
                        $existingMap[$linkKey] = true;
                        continue;
                    }

                    if ($pedidoId === null) {
                        $pedidoId = $this->createPedidoAcesso($superadmin, $actorUserId);
                        $pedidosCriados++;
                        $this->pedidoModel->addStatusHistory(
                            $pedidoId,
                            null,
                            'aprovado',
                            'Acesso administrativo automático para superadministrador.',
                            $actorUserId
                        );
                    }

                    $pedidoItemId = $this->pedidoItemModel->create(array(
                        'pedido_id' => $pedidoId,
                        'curso_evento_id' => (int) $turma['curso_evento_id'],
                        'turma_id' => $turmaId,
                        'quantidade' => 1,
                        'valor_unitario' => 0.00,
                        'valor_total' => 0.00,
                        'status' => 'ativo',
                    ));

                    $participantePedidoId = $this->participantePedidoModel->create(array(
                        'pedido_id' => $pedidoId,
                        'pedido_item_id' => $pedidoItemId,
                        'usuario_id' => $usuarioId,
                        'nome' => $this->valueOrNull($superadmin, 'nome'),
                        'cpf' => $this->valueOrNull($superadmin, 'cpf'),
                        'email' => $this->valueOrNull($superadmin, 'email'),
                        'telefone' => $this->valueOrNull($superadmin, 'telefone'),
                        'ordem' => 1,
                        'status' => 'ativo',
                    ));

                    $inscricaoId = $this->inscricaoModel->create(array(
                        'pedido_id' => $pedidoId,
                        'pedido_item_id' => $pedidoItemId,
                        'participante_pedido_id' => $participantePedidoId,
                        'usuario_id' => $usuarioId,
                        'curso_evento_id' => (int) $turma['curso_evento_id'],
                        'turma_id' => $turmaId,
                        'status' => 'ativa',
                        'confirmado_em' => date('Y-m-d H:i:s'),
                        'is_presente' => 1,
                        'presente_campanha_id' => null,
                        'acesso_expira_em' => null,
                    ));

                    $this->inscricaoModel->addStatusHistory(
                        $inscricaoId,
                        null,
                        'ativa',
                        'Acesso administrativo automático para superadministrador.',
                        $actorUserId
                    );

                    $existingMap[$linkKey] = true;
                    $totalCriadas++;
                    $criouAlgumaInscricao = true;
                }

                if ($criouAlgumaInscricao) {
                    $superadminsProcessados++;
                }
            }

            $pdo->commit();

            $preview = $this->buildPreviewPayload($superadmins, $turmas, $existingMap);

            $this->auditService->record(
                'admin.superadmins_turmas.sincronizadas',
                'inscricoes',
                null,
                array(
                    'total_superadmins' => $preview['total_superadmins'],
                    'total_turmas' => $preview['total_turmas'],
                    'total_criadas' => $totalCriadas,
                    'total_ignoradas' => $totalIgnoradas,
                    'actor_user_id' => (int) $actorUserId,
                ),
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            return array(
                'ok' => true,
                'total_superadmins' => $preview['total_superadmins'],
                'total_turmas' => $preview['total_turmas'],
                'inscricoes_criadas' => $totalCriadas,
                'inscricoes_ignoradas' => $totalIgnoradas,
                'pedidos_criados' => $pedidosCriados,
                'superadmins_processados' => $superadminsProcessados,
            );
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            Logger::error('admin.superadmins_turmas.sincronizacao_falhou', array(
                'actor_user_id' => $actorUserId,
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ));

            return array(
                'ok' => false,
                'message' => 'Não foi possível sincronizar os superadministradores nas turmas elegíveis.',
            );
        }
    }

    private function buildPreviewPayload(array $superadmins, array $turmas, array $existingMap)
    {
        $totalSuperadmins = count($superadmins);
        $totalTurmas = count($turmas);
        $totalVinculosExistentes = count($existingMap);
        $totalPossiveis = $totalSuperadmins * $totalTurmas;
        $inscricoesFaltantes = $totalPossiveis > $totalVinculosExistentes ? ($totalPossiveis - $totalVinculosExistentes) : 0;

        return array(
            'total_superadmins' => $totalSuperadmins,
            'total_turmas' => $totalTurmas,
            'total_vinculos_existentes' => $totalVinculosExistentes,
            'inscricoes_faltantes' => $inscricoesFaltantes,
            'superadmins' => $this->summarizeSuperadmins($superadmins),
            'turmas' => $this->summarizeTurmas($turmas),
        );
    }

    private function summarizeSuperadmins(array $superadmins)
    {
        $resumo = array();

        foreach ($superadmins as $superadmin) {
            $resumo[] = array(
                'id' => (int) $superadmin['id'],
                'nome' => $this->valueOrNull($superadmin, 'nome'),
                'email' => $this->valueOrNull($superadmin, 'email'),
                'cpf' => $this->valueOrNull($superadmin, 'cpf'),
            );
        }

        return $resumo;
    }

    private function summarizeTurmas(array $turmas)
    {
        $resumo = array();

        foreach ($turmas as $turma) {
            $resumo[] = array(
                'id' => (int) $turma['id'],
                'nome' => $this->valueOrNull($turma, 'nome'),
                'codigo' => $this->valueOrNull($turma, 'codigo'),
                'status' => $this->valueOrNull($turma, 'status'),
                'curso_evento_id' => (int) $turma['curso_evento_id'],
                'curso_nome' => $this->valueOrNull($turma, 'curso_nome'),
            );
        }

        return $resumo;
    }

    private function loadActiveSuperAdmins()
    {
        $stmt = Database::connection()->prepare(
            'SELECT DISTINCT u.id,
                            u.nome,
                            u.email,
                            u.cpf,
                            u.telefone
             FROM usuarios u
             INNER JOIN usuario_perfis up ON up.usuario_id = u.id
             INNER JOIN perfis p ON p.id = up.perfil_id
             WHERE u.status = "ativo"
               AND u.deleted_at IS NULL
               AND p.slug = "superadmin"
               AND p.deleted_at IS NULL
             ORDER BY u.nome ASC, u.id ASC'
        );

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function loadEligibleTurmas()
    {
        $stmt = Database::connection()->prepare(
            'SELECT t.id,
                    t.curso_evento_id,
                    t.nome,
                    t.codigo,
                    t.status,
                    ce.nome AS curso_nome,
                    ce.slug AS curso_slug
             FROM turmas t
             INNER JOIN cursos_eventos ce ON ce.id = t.curso_evento_id
             WHERE ce.status = "ativo"
               AND ce.deleted_at IS NULL
               AND t.deleted_at IS NULL
               AND t.status IN ("aberta", "planejada")
             ORDER BY ce.nome ASC, t.nome ASC, t.id ASC'
        );

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function loadExistingLinksMap(array $superadmins, array $turmas)
    {
        $usuarioIds = array();
        foreach ($superadmins as $superadmin) {
            $usuarioIds[] = (int) $superadmin['id'];
        }

        $turmaIds = array();
        foreach ($turmas as $turma) {
            $turmaIds[] = (int) $turma['id'];
        }

        if (empty($usuarioIds) || empty($turmaIds)) {
            return array();
        }

        $usuarioPlaceholders = implode(',', array_fill(0, count($usuarioIds), '?'));
        $turmaPlaceholders = implode(',', array_fill(0, count($turmaIds), '?'));
        $params = array_merge($usuarioIds, $turmaIds);

        $stmt = Database::connection()->prepare(
            'SELECT DISTINCT usuario_id, turma_id
             FROM inscricoes
             WHERE usuario_id IN (' . $usuarioPlaceholders . ')
               AND turma_id IN (' . $turmaPlaceholders . ')'
        );
        $stmt->execute($params);

        $map = array();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $map[$this->buildLinkKey((int) $row['usuario_id'], (int) $row['turma_id'])] = true;
        }

        return $map;
    }

    private function createPedidoAcesso(array $superadmin, $actorUserId)
    {
        $codigo = sprintf(
            'ADM-ACESSO-%d-%s-%04d',
            (int) $superadmin['id'],
            date('YmdHis'),
            mt_rand(0, 9999)
        );

        return $this->pedidoModel->create(array(
            'codigo' => $codigo,
            'comprador_usuario_id' => (int) $superadmin['id'],
            'pagador_usuario_id' => (int) $superadmin['id'],
            'pagador_nome' => $this->valueOrNull($superadmin, 'nome'),
            'pagador_cpf' => $this->valueOrNull($superadmin, 'cpf'),
            'pagador_email' => $this->valueOrNull($superadmin, 'email'),
            'pagador_telefone' => $this->valueOrNull($superadmin, 'telefone'),
            'tipo_pedido' => 'propria',
            'status' => 'aprovado',
            'subtotal' => 0.00,
            'desconto_total' => 0.00,
            'acrescimo_total' => 0.00,
            'total' => 0.00,
            'observacoes_internas' => 'Acesso administrativo automático para superadministrador.',
            'observacoes_publicas' => 'Acesso administrativo interno.',
            'canal_origem' => 'admin_superadmin_turmas',
            'is_presente' => 1,
            'presente_titulo' => 'Acesso administrativo automático',
            'presente_justificativa' => 'Inclusão automática de superadministradores em turmas elegíveis.',
            'presente_concedido_em' => date('Y-m-d H:i:s'),
            'aprovado_por_usuario_id' => (int) $actorUserId,
            'aprovado_em' => date('Y-m-d H:i:s'),
        ));
    }

    private function buildLinkKey($usuarioId, $turmaId)
    {
        return (int) $usuarioId . ':' . (int) $turmaId;
    }

    private function valueOrNull(array $row, $key)
    {
        if (!array_key_exists($key, $row)) {
            return null;
        }

        $value = trim((string) $row[$key]);
        return $value !== '' ? $value : null;
    }
}
