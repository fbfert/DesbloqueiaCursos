<?php

namespace App\Services;

class DashboardProfessorService
{
    private $dashboardService;

    public function __construct()
    {
        $this->dashboardService = new DashboardService();
    }

    public function painel($usuarioId, array $filters = array(), $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        return $this->dashboardService->professorDashboard($usuarioId, $filters, $actorUserId, $ipAddress, $userAgent);
    }
}
