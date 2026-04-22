<?php

namespace App\Services;

use App\Core\Logger;

class AuditService
{
    public function record($action, $entityType, $entityId = null, array $metadata = array())
    {
        Logger::info('audit.recorded', array(
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'metadata' => $metadata,
        ));
    }
}
