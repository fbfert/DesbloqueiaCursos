<?php

namespace App\Services;

use App\Models\CertificadoAssinante;
use App\Models\CertificadoTemplate;

class CertificadoTemplateService
{
    private $templateModel;
    private $assinanteModel;

    public function __construct()
    {
        $this->templateModel = new CertificadoTemplate();
        $this->assinanteModel = new CertificadoAssinante();
    }

    public function listTemplates()
    {
        return $this->templateModel->allActive();
    }

    public function defaultTemplate()
    {
        return $this->templateModel->defaultTemplate();
    }

    public function assinantesDoCurso($cursoId, $templateId = null)
    {
        return $this->assinanteModel->forCurso($cursoId, $templateId);
    }
}
