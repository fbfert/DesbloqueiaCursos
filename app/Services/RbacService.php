<?php

namespace App\Services;

use App\Core\Database;
use App\Models\Perfil;
use App\Models\Permissao;
use App\Models\PerfilPermissao;
use App\Models\UsuarioPerfil;

class RbacService
{
    private $perfilModel;
    private $permissaoModel;
    private $perfilPermissaoModel;
    private $usuarioPerfilModel;
    private $auditService;
    private $trashService;

    public function __construct()
    {
        $this->perfilModel = new Perfil();
        $this->permissaoModel = new Permissao();
        $this->perfilPermissaoModel = new PerfilPermissao();
        $this->usuarioPerfilModel = new UsuarioPerfil();
        $this->auditService = new AuditService();
        $this->trashService = new TrashService();
    }

    public function listProfiles()
    {
        return $this->perfilModel->withPermissions();
    }

    public function listPermissions()
    {
        return $this->permissaoModel->groupedByModule();
    }

    public function syncProfilePermissions($perfilId, array $permissaoIds, $actorUserId = null, array $context = array())
    {
        $perfil = $this->perfilModel->findById($perfilId);
        if (!$perfil) {
            return array('ok' => false, 'errors' => array('perfil_id' => 'Perfil nao encontrado.'));
        }

        $current = $this->perfilPermissaoModel->forPerfil($perfilId);
        $currentIds = array_map(function ($row) {
            return (int) $row['id'];
        }, $current);

        $newIds = array_values(array_unique(array_map('intval', $permissaoIds)));
        $removed = array();
        foreach ($current as $row) {
            if (!in_array((int) $row['id'], $newIds, true)) {
                $removed[] = $row;
            }
        }

        foreach ($removed as $row) {
            $this->trashService->record(
                'perfil_permissao',
                $perfilId,
                'Remocao de vinculacao RBAC',
                $row,
                $actorUserId,
                isset($context['ip_address']) ? $context['ip_address'] : null,
                isset($context['user_agent']) ? $context['user_agent'] : null
            );
        }

        $this->perfilPermissaoModel->sync($perfilId, $newIds);

        $this->auditService->record(
            'rbac.perfil_permissoes.atualizadas',
            'perfil',
            $perfilId,
            array(
                'perfil' => $perfil,
                'permissoes_anteriores' => $currentIds,
                'permissoes_novas' => $newIds,
            ),
            $actorUserId,
            isset($context['ip_address']) ? $context['ip_address'] : null,
            isset($context['user_agent']) ? $context['user_agent'] : null
        );

        return array('ok' => true);
    }

    public function syncUserProfiles($usuarioId, array $perfilIds, $actorUserId = null, array $context = array())
    {
        $current = $this->usuarioPerfilModel->forUser($usuarioId);
        $currentIds = array_map(function ($row) {
            return (int) $row['id'];
        }, $current);

        $newIds = array_values(array_unique(array_map('intval', $perfilIds)));
        $removed = array();
        foreach ($current as $row) {
            if (!in_array((int) $row['id'], $newIds, true)) {
                $removed[] = $row;
            }
        }

        foreach ($removed as $row) {
            $this->trashService->record(
                'usuario_perfil',
                $usuarioId,
                'Remocao de vinculacao RBAC',
                $row,
                $actorUserId,
                isset($context['ip_address']) ? $context['ip_address'] : null,
                isset($context['user_agent']) ? $context['user_agent'] : null
            );
        }

        $this->usuarioPerfilModel->sync($usuarioId, $newIds);

        $this->auditService->record(
            'rbac.usuario_perfis.atualizados',
            'usuario',
            $usuarioId,
            array(
                'perfis_anteriores' => $currentIds,
                'perfis_novos' => $newIds,
            ),
            $actorUserId,
            isset($context['ip_address']) ? $context['ip_address'] : null,
            isset($context['user_agent']) ? $context['user_agent'] : null
        );

        return array('ok' => true);
    }

    public function userHasPermission($usuarioId, $permissionSlug)
    {
        if ($this->isSuperAdmin($usuarioId)) {
            return true;
        }

        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) AS total
             FROM usuario_perfis up
             INNER JOIN perfil_permissoes pp ON pp.perfil_id = up.perfil_id
             INNER JOIN permissoes per ON per.id = pp.permissao_id
             WHERE up.usuario_id = :usuario_id
               AND per.slug = :slug'
        );
        $stmt->execute(array(
            'usuario_id' => $usuarioId,
            'slug' => $permissionSlug,
        ));

        $row = $stmt->fetch();
        return !empty($row) && (int) $row['total'] > 0;
    }

    public function isSuperAdmin($usuarioId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) AS total
             FROM usuario_perfis up
             INNER JOIN perfis p ON p.id = up.perfil_id
             WHERE up.usuario_id = :usuario_id
               AND p.slug = "superadmin"'
        );
        $stmt->execute(array('usuario_id' => $usuarioId));

        $row = $stmt->fetch();
        return !empty($row) && (int) $row['total'] > 0;
    }
}
