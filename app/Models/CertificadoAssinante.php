<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class CertificadoAssinante
{
    public function forCurso($cursoId, $templateId = null)
    {
        $sql = 'SELECT *
                FROM certificados_assinantes
                WHERE curso_evento_id = :curso_evento_id
                  AND deleted_at IS NULL
                  AND status = "ativo"';
        $params = array('curso_evento_id' => $cursoId);

        if ($templateId !== null) {
            $sql .= ' AND (certificado_template_id = :template_id OR certificado_template_id IS NULL)';
            $params['template_id'] = $templateId;
        }

        $sql .= ' ORDER BY ordem ASC, id ASC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
