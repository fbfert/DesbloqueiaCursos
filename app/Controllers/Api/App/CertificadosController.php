<?php

namespace App\Controllers\Api\App;

use App\Core\Logger;
use App\Core\Request;
use App\Services\CertificadoRetencaoService;
use App\Services\CertificadoService;
use App\Support\AppApi\ArquivoResposta;
use App\Support\AppApi\CatalogoPresenter;

/**
 * Certificados do aluno. O PDF exige que o certificado seja do usuário do
 * token; a rota pública do site (/certificados/pdf) não muda.
 */
class CertificadosController extends AppController
{
    public function index(Request $request)
    {
        // Retidos aguardando CPF de quem já informou o CPF saem antes da listagem (login-google).
        (new CertificadoRetencaoService())->liberarSePendente($this->usuarioId());

        $saida = array();
        foreach ((new CertificadoService())->certificadosEmitidosDoAluno($this->usuarioId()) as $certificado) {
            $saida[] = CatalogoPresenter::certificado($certificado);
        }

        return $this->ok($saida);
    }

    public function pdf(Request $request)
    {
        $codigo = (string) $request->route('codigo', '');
        $resultado = (new CertificadoService())->pdfDoAluno(rawurldecode($codigo), $this->usuarioId());

        if (empty($resultado['ok'])) {
            $motivo = isset($resultado['motivo']) ? $resultado['motivo'] : 'nao_encontrado';
            if ($motivo === 'sem_acesso') {
                Logger::warning('certificado.app.pdf_negado', array('usuario_id' => $this->usuarioId(), 'codigo' => strtoupper($codigo)));
                return $this->erro('sem_acesso', 'Este certificado não pertence à sua conta.', 403);
            }
            if ($motivo === 'indisponivel') {
                return $this->erro('sem_acesso', 'O download do certificado não está disponível no momento.', 403);
            }
            if ($motivo === 'falhou') {
                return $this->erro('erro_interno', 'Não foi possível gerar o certificado.', 500);
            }
            return $this->naoEncontrado('Certificado não encontrado.');
        }

        Logger::info('certificado.app.download_pdf', array('usuario_id' => $this->usuarioId(), 'codigo' => $resultado['codigo']));

        return ArquivoResposta::deConteudo($resultado['pdf'], 'application/pdf', 'certificado-' . $resultado['codigo'] . '.pdf', 'attachment');
    }
}
