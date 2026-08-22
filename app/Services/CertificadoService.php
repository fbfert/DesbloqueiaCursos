<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Helpers;
use App\Core\Logger;
use App\Core\Validator;
use App\Models\Certificado;
use App\Models\CertificadoEmissaoExcecao;
use App\Models\CertificadoAssinante;
use App\Models\CursoEvento;
use App\Models\CertificadoTemplate;
use App\Models\CursoPessoaVinculada;
use App\Models\Aula;
use App\Models\Modulo;
use App\Models\ConteudoItem;
use App\Models\ConteudoModulo;
use App\Models\Inscricao;
use App\Models\Usuario;
use App\Models\Pedido;
use App\Models\ParticipantePedido;
use App\Models\Turma;
use App\Services\RbacService;
use Exception;

class CertificadoService
{
    private $certificadoModel;
    private $certificadoExcecaoModel;
    private $templateModel;
    private $assinanteModel;
    private $cursoModel;
    private $cursoService;
    private $cursoPessoaModel;
    private $moduloModel;
    private $aulaModel;
    private $conteudoModuloModel;
    private $conteudoItemModel;
    private $inscricaoModel;
    private $usuarioModel;
    private $pedidoModel;
    private $participanteModel;
    private $turmaModel;
    private $auditService;
    private $trashService;
    private $inscricaoService;
    private $templateService;
    private $placeholderService;
    private $globalConfigService;
    private $rbacService;
    private $elegibilidadeService;
    private $aptidaoService;
    private $certificadosConfig;

    public function __construct()
    {
        $this->certificadoModel = new Certificado();
        $this->certificadoExcecaoModel = new CertificadoEmissaoExcecao();
        $this->templateModel = new CertificadoTemplate();
        $this->assinanteModel = new CertificadoAssinante();
        $this->cursoModel = new CursoEvento();
        $this->cursoService = new CursoService();
        $this->cursoPessoaModel = new CursoPessoaVinculada();
        $this->moduloModel = new Modulo();
        $this->aulaModel = new Aula();
        $this->conteudoModuloModel = new ConteudoModulo();
        $this->conteudoItemModel = new ConteudoItem();
        $this->inscricaoModel = new Inscricao();
        $this->usuarioModel = new Usuario();
        $this->pedidoModel = new Pedido();
        $this->participanteModel = new ParticipantePedido();
        $this->turmaModel = new Turma();
        $this->auditService = new AuditService();
        $this->trashService = new TrashService();
        $this->inscricaoService = new InscricaoService();
        $this->templateService = new CertificadoTemplateService();
        $this->placeholderService = new CertificadoPlaceholderService();
        $this->globalConfigService = new ConfiguracaoGlobalService();
        $this->rbacService = new RbacService();
        $this->elegibilidadeService = new LmsElegibilidadeService();
        $this->aptidaoService = new AptidaoCertificadoService();
        $this->certificadosConfig = null;
    }

    public function configuracaoCertificados()
    {
        return $this->configuracaoCertificadosCache();
    }

    public function resolverImagemPublica($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        if (preg_match('#^data:#i', $value) || preg_match('#^https?://#i', $value)) {
            return $value;
        }

        if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $value)) {
            return '';
        }

        $value = str_replace('\\', '/', $value);
        $basePublic = str_replace('\\', '/', BASE_PATH . '/public_html');
        if (strpos($value, $basePublic) === 0) {
            $value = substr($value, strlen($basePublic));
        }

        if (strpos($value, 'public_html/') === 0) {
            $value = substr($value, strlen('public_html'));
        }

        $value = preg_replace('#/+#', '/', $value);
        if ($value === '') {
            return '';
        }

        if ($value[0] !== '/') {
            $value = '/' . ltrim($value, '/');
        }

        return $value;
    }

    public function resolverLogoTemplatePublico(array $template)
    {
        $logo = $this->resolverImagemPublica((string) ($template['logo'] ?? ''));
        if ($logo !== '') {
            return $logo;
        }

        $configCertificados = $this->configuracaoCertificadosCache();
        $logoConfig = $this->resolverImagemPublica((string) ($configCertificados['certificados_logo_padrao'] ?? ''));
        if ($logoConfig !== '') {
            return $logoConfig;
        }

        $institucional = $this->globalConfigService->institucional();
        if (!empty($configCertificados['certificados_usar_logo_institucional'])) {
            $logoInstitucional = $this->resolverImagemPublica((string) ($institucional['logo_caminho'] ?? ''));
            if ($logoInstitucional !== '') {
                return $logoInstitucional;
            }
        }

        return $this->logoTransparenteDataUri();
    }

    public function resolverImagemFundoTemplatePublica(array $template)
    {
        return $this->resolverImagemPublica((string) ($template['imagem_fundo'] ?? ''));
    }

    public function resolverImagemPublicaOuDataUri($value)
    {
        $publico = $this->resolverImagemPublica($value);
        if ($publico === '') {
            return '';
        }

        $arquivo = $this->resolverImagemPdf($publico);
        if (is_string($arquivo) && $arquivo !== '' && is_file($arquivo)) {
            $mime = $this->detectarMimeType($arquivo);
            if (!is_string($mime) || $mime === '') {
                $mime = 'image/png';
            }

            $conteudo = @file_get_contents($arquivo);
            if ($conteudo !== false) {
                return 'data:' . $mime . ';base64,' . base64_encode($conteudo);
            }
        }

        return $publico;
    }

    private function configuracaoCertificadosCache()
    {
        if ($this->certificadosConfig === null) {
            $this->certificadosConfig = $this->globalConfigService->certificados();
        }

        return $this->certificadosConfig;
    }

    private function configCertificado($key, $default = null)
    {
        $config = $this->configuracaoCertificadosCache();

        return array_key_exists($key, $config) ? $config[$key] : $default;
    }

    private function certificadosHabilitados()
    {
        return !empty($this->configCertificado('certificados_habilitado', 1));
    }

    private function emissaoCertificadosHabilitada()
    {
        return !empty($this->configCertificado('certificados_emissao_habilitada', 1));
    }

    private function validacaoPublicaHabilitada()
    {
        return !empty($this->configCertificado('certificados_validacao_publica_habilitada', 1));
    }

    private function downloadCertificadoPermitido()
    {
        return !empty($this->configCertificado('certificados_permitir_download', 1));
    }

    private function segundaViaPermitida()
    {
        return !empty($this->configCertificado('certificados_permitir_segunda_via', 1));
    }

    private function cancelamentoPermitido()
    {
        return !empty($this->configCertificado('certificados_permitir_cancelamento', 1));
    }

    private function motivoCancelamentoObrigatorio()
    {
        return !empty($this->configCertificado('certificados_exigir_motivo_cancelamento', 1));
    }

    private function regenerarPdfMesmoCodigo()
    {
        return !empty($this->configCertificado('certificados_regenerar_pdf_mesmo_codigo', 1));
    }

    private function codigoPrefixo()
    {
        $prefixo = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) $this->configCertificado('certificados_codigo_prefixo', '')));

        if ($prefixo === '') {
            $prefixo = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) $this->globalConfigService->certificatePrefix()));
        }

        if ($prefixo === '') {
            $prefixo = 'PRC';
        }

        return substr($prefixo, 0, 12);
    }

    private function codigoFormato()
    {
        $formato = strtolower(trim((string) $this->configCertificado('certificados_codigo_formato', 'alfanumerico')));
        $permitidos = array('alfanumerico', 'prefixo_ano_sequencial', 'prefixo_ano_aleatorio', 'prefixo_aleatorio', 'hash_curto');

        if (!in_array($formato, $permitidos, true)) {
            return 'alfanumerico';
        }

        return $formato;
    }

    private function codigoTamanhoMinimo()
    {
        $tamanho = (int) $this->configCertificado('certificados_codigo_tamanho_minimo', 10);
        return $tamanho > 0 ? $tamanho : 10;
    }

    private function inscricaoPodeEmitirCertificado(array $inscricao)
    {
        $status = (string) ($inscricao['status'] ?? '');
        if (!in_array($status, array('ativa', 'em_andamento', 'concluida', 'concluida_sem_certificado', 'certificado_emitido'), true)) {
            return false;
        }

        $acessoExpiraEm = isset($inscricao['acesso_expira_em']) ? trim((string) $inscricao['acesso_expira_em']) : '';
        if ($acessoExpiraEm !== '' && strtotime($acessoExpiraEm) !== false && strtotime($acessoExpiraEm) < time()) {
            return false;
        }

        $pedidoStatus = (string) ($inscricao['pedido_status'] ?? '');
        $comprovanteStatus = (string) ($inscricao['comprovante_status'] ?? '');
        if (in_array($pedidoStatus, array('aprovado', 'pago', 'confirmado', 'ativo', 'concluido'), true)) {
            return true;
        }

        return $comprovanteStatus === 'aprovado';
    }

    public function listarAptos()
    {
        $inscricoes = $this->inscricaoModel->allForBackoffice();
        $aptos = array();

        foreach ($inscricoes as $inscricao) {
            if (!$this->inscricaoPodeEmitirCertificado($inscricao)) {
                continue;
            }

            if (!empty($inscricao['certificado_id']) || (isset($inscricao['certificado_status']) && (string) $inscricao['certificado_status'] === 'emitido')) {
                continue;
            }

            $elegibilidade = $this->elegibilidadeService->calcularParaInscricao($inscricao);
            $situacao = (string) ($elegibilidade['situacao'] ?? '');
            if (!in_array($situacao, array('apto', 'certificado_emitido'), true)) {
                continue;
            }

            $inscricao['situacao_elegibilidade'] = $situacao;
            $inscricao['motivos_pendencias'] = isset($elegibilidade['motivos']) && is_array($elegibilidade['motivos']) ? $elegibilidade['motivos'] : array();
            $inscricao['motivos_pendencias_texto'] = isset($elegibilidade['motivos_texto']) ? $elegibilidade['motivos_texto'] : '';
            $aptos[] = $inscricao;
        }

        return $aptos;
    }

    public function buscarAlunosEmissaoRapida($termo, $limit = 20)
    {
        return $this->usuarioModel->buscarAlunosParaCertificadoRapido($termo, $limit);
    }

    public function buscarAlunoEmissaoRapidaPorId($usuarioId)
    {
        $usuarioId = (int) $usuarioId;
        if ($usuarioId <= 0) {
            return null;
        }

        return $this->usuarioModel->findAlunoById($usuarioId);
    }

    public function carregarInscricoesAlunoEmissaoRapida($usuarioId)
    {
        $usuarioId = (int) $usuarioId;
        if ($usuarioId <= 0) {
            return array();
        }

        $inscricoes = $this->inscricaoModel->forAlunoBackoffice($usuarioId);
        $resultado = array();

        foreach ($inscricoes as $inscricao) {
            $resultado[] = $this->enriquecerInscricaoEmissaoRapida($inscricao);
        }

        return $resultado;
    }

    public function emitirRapidaIndividual($usuarioId, array $inscricaoIds, array $opcoes = array(), $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $usuarioId = (int) $usuarioId;
        if ($usuarioId <= 0) {
            return array('ok' => false, 'message' => 'Aluno inválido.');
        }

        $aluno = $this->buscarAlunoEmissaoRapidaPorId($usuarioId);
        if (!$aluno) {
            return array('ok' => false, 'message' => 'Aluno não encontrado.');
        }

        $emailAluno = strtolower(trim((string) ($aluno['email'] ?? '')));
        if ($emailAluno === '' || filter_var($emailAluno, FILTER_VALIDATE_EMAIL) === false) {
            return array('ok' => false, 'message' => 'O aluno não possui e-mail válido cadastrado. Atualize o cadastro antes de emitir o certificado.');
        }

        $inscricoes = $this->carregarInscricoesAlunoEmissaoRapida($usuarioId);
        $mapaInscricoes = array();
        foreach ($inscricoes as $inscricao) {
            $mapaInscricoes[(int) ($inscricao['id'] ?? 0)] = $inscricao;
        }

        $selecionadas = array_values(array_unique(array_filter(array_map('intval', $inscricaoIds))));
        if (empty($selecionadas)) {
            return array('ok' => false, 'message' => 'Selecione ao menos uma inscrição válida para emitir.');
        }

        $errosValidacao = array();
        foreach ($selecionadas as $inscricaoId) {
            if (!isset($mapaInscricoes[$inscricaoId])) {
                $errosValidacao[] = 'A inscrição ' . $inscricaoId . ' não pertence ao aluno selecionado.';
                continue;
            }

            if (empty($mapaInscricoes[$inscricaoId]['selecionavel'])) {
                $errosValidacao[] = 'A inscrição "' . $this->descricaoInscricaoEmissaoRapida($mapaInscricoes[$inscricaoId]) . '" não está apta para emissão.';
            }
        }

        if (!empty($errosValidacao)) {
            return array('ok' => false, 'message' => implode(' ', $errosValidacao), 'erros' => $errosValidacao);
        }

        $resultados = array();
        $emitidos = 0;
        $falhas = 0;
        $emailsEnviados = 0;
        $emailsFalhos = 0;

        foreach ($selecionadas as $inscricaoId) {
            $resultadoEmissao = $this->emitir(
                $inscricaoId,
                array_merge($opcoes, array('nao_duplicar' => true)),
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            if (empty($resultadoEmissao['ok'])) {
                $falhas++;
                $resultados[] = array(
                    'ok' => false,
                    'inscricao_id' => $inscricaoId,
                    'message' => isset($resultadoEmissao['message']) ? $resultadoEmissao['message'] : 'Não foi possível emitir o certificado.',
                    'codigo' => null,
                    'certificado_id' => isset($resultadoEmissao['certificado_id']) ? (int) $resultadoEmissao['certificado_id'] : null,
                    'email_ok' => null,
                    'email_message' => null,
                    'certificado_url_download' => null,
                    'versao_online_url' => null,
                    'validacao_url' => null,
                );
                continue;
            }

            $emitidos++;
            $emailResultado = isset($resultadoEmissao['email_result']) && is_array($resultadoEmissao['email_result']) ? $resultadoEmissao['email_result'] : null;
            $emailOk = null;
            $emailMessage = null;
            if (is_array($emailResultado)) {
                $emailOk = !empty($emailResultado['ok']);
                $emailMessage = isset($emailResultado['message']) ? (string) $emailResultado['message'] : null;
                if ($emailOk) {
                    $emailsEnviados++;
                } else {
                    $emailsFalhos++;
                }
            }

            $codigo = isset($resultadoEmissao['codigo']) ? (string) $resultadoEmissao['codigo'] : '';
            $resultados[] = array(
                'ok' => true,
                'inscricao_id' => $inscricaoId,
                'certificado_id' => isset($resultadoEmissao['certificado_id']) ? (int) $resultadoEmissao['certificado_id'] : null,
                'codigo' => $codigo,
                'email_ok' => $emailOk,
                'email_message' => $emailMessage,
                'certificado_url_download' => $codigo !== '' ? Helpers::url('certificados/pdf?codigo=' . urlencode($codigo)) : null,
                'versao_online_url' => $codigo !== '' ? Helpers::url('certificados/versao-online?codigo=' . urlencode($codigo)) : null,
                'validacao_url' => $codigo !== '' ? Helpers::url('certificados/validar?codigo=' . urlencode($codigo)) : null,
                'aluno_nome' => isset($aluno['nome']) ? (string) $aluno['nome'] : '',
                'aluno_email' => $emailAluno,
            );
        }

        return array(
            'ok' => true,
            'usuario_id' => $usuarioId,
            'aluno' => array(
                'id' => $usuarioId,
                'nome' => isset($aluno['nome']) ? (string) $aluno['nome'] : '',
                'email' => $emailAluno,
                'cpf' => isset($aluno['cpf']) ? (string) $aluno['cpf'] : '',
            ),
            'resumo' => array(
                'selecionadas' => count($selecionadas),
                'emitidos' => $emitidos,
                'falhas' => $falhas,
                'emails_enviados' => $emailsEnviados,
                'emails_falhos' => $emailsFalhos,
            ),
            'resultados' => $resultados,
        );
    }

    public function listarCertificados()
    {
        return $this->certificadoModel->listAdmin();
    }

    public function listarTemplates()
    {
        return $this->templateService->listTemplates();
    }

    public function listarCursosEmissaoManual()
    {
        return $this->cursoModel->allForSelect(array('ativo'));
    }

    public function listarTurmasEmissaoManual($cursoId = null)
    {
        if (!empty($cursoId)) {
            return $this->turmaModel->forCourse((int) $cursoId);
        }

        return $this->turmaModel->allForSelect();
    }

    public function buscarCandidatosEmissaoManual(array $filters = array())
    {
        $candidatos = $this->certificadoModel->searchManualCandidates($filters);

        foreach ($candidatos as &$candidato) {
            $elegibilidade = $this->elegibilidadeService->calcularParaInscricao($candidato);
            $candidato['situacao_elegibilidade'] = isset($elegibilidade['situacao']) ? $elegibilidade['situacao'] : null;
            $candidato['motivos_pendencias'] = isset($elegibilidade['motivos']) && is_array($elegibilidade['motivos']) ? $elegibilidade['motivos'] : array();
            $candidato['motivos_pendencias_texto'] = isset($elegibilidade['motivos_texto']) ? $elegibilidade['motivos_texto'] : '';
        }
        unset($candidato);

        return $candidatos;
    }

    private function enriquecerInscricaoEmissaoRapida(array $inscricao)
    {
        $elegibilidade = $this->elegibilidadeService->calcularParaInscricao($inscricao);
        $situacao = (string) ($elegibilidade['situacao'] ?? '');
        $certificadoEmitido = !empty($inscricao['certificado_id']) || !empty($inscricao['certificado_codigo']) || (isset($inscricao['certificado_status']) && (string) $inscricao['certificado_status'] === 'emitido');
        $motivos = isset($elegibilidade['motivos']) && is_array($elegibilidade['motivos']) ? $elegibilidade['motivos'] : array();
        $motivoTexto = isset($elegibilidade['motivos_texto']) ? trim((string) $elegibilidade['motivos_texto']) : '';

        if ($certificadoEmitido) {
            $situacaoExibicao = 'Certificado já emitido';
            $selecionavel = false;
            $motivoExibicao = 'Certificado já emitido.';
        } elseif ($situacao === 'apto') {
            $situacaoExibicao = 'Apto para emissão';
            $selecionavel = true;
            $motivoExibicao = 'Critérios de conclusão atingidos.';
        } else {
            $situacaoExibicao = 'Não apto para emissão';
            $selecionavel = false;
            $motivoExibicao = $motivoTexto !== '' ? $motivoTexto : 'A inscrição ainda não está apta para certificado.';
        }

        $inscricao['situacao_elegibilidade'] = $situacao;
        $inscricao['situacao_elegibilidade_label'] = $situacaoExibicao;
        $inscricao['selecionavel'] = $selecionavel ? 1 : 0;
        $inscricao['motivos_pendencias'] = $motivos;
        $inscricao['motivos_pendencias_texto'] = $motivoTexto;
        $inscricao['motivo_resumido'] = $motivoExibicao;
        $inscricao['certificado_emitido'] = $certificadoEmitido ? 1 : 0;
        $inscricao['turma_periodo'] = $this->formatarPeriodoTurma(
            $inscricao['turma_data_inicio'] ?? null,
            $inscricao['turma_data_fim'] ?? null
        );
        $inscricao['turma_horario'] = $this->formatarHorarioTurma(
            $inscricao['turma_hora_inicio'] ?? null,
            $inscricao['turma_hora_fim'] ?? null
        );

        return $inscricao;
    }

    private function formatarPeriodoTurma($dataInicio, $dataFim)
    {
        $inicio = $this->formatarDataBr($dataInicio);
        $fim = $this->formatarDataBr($dataFim);

        if ($inicio !== '' && $fim !== '') {
            return $inicio === $fim ? $inicio : $inicio . ' a ' . $fim;
        }

        return $inicio !== '' ? $inicio : $fim;
    }

    private function formatarHorarioTurma($horaInicio, $horaFim)
    {
        $inicio = $this->formatarHoraBr($horaInicio);
        $fim = $this->formatarHoraBr($horaFim);

        if ($inicio !== '' && $fim !== '') {
            return $inicio === $fim ? $inicio : $inicio . ' às ' . $fim;
        }

        return $inicio !== '' ? $inicio : $fim;
    }

    private function formatarDataBr($valor)
    {
        $valor = trim((string) $valor);
        if ($valor === '' || strpos($valor, '0000-00-00') === 0) {
            return '';
        }

        $timestamp = strtotime($valor);
        return $timestamp !== false ? date('d/m/Y', $timestamp) : '';
    }

    private function formatarHoraBr($valor)
    {
        $valor = trim((string) $valor);
        if ($valor === '') {
            return '';
        }

        $timestamp = strtotime($valor);
        return $timestamp !== false ? date('H:i', $timestamp) : '';
    }

    private function descricaoInscricaoEmissaoRapida(array $inscricao)
    {
        $curso = isset($inscricao['curso_nome']) ? (string) $inscricao['curso_nome'] : 'Curso';
        $turma = !empty($inscricao['turma_nome']) ? ' - ' . (string) $inscricao['turma_nome'] : '';
        return $curso . $turma;
    }

    public function detalhar($certificadoId)
    {
        $certificado = $this->certificadoModel->findById($certificadoId);

        if (!$certificado) {
            return array('certificado' => null);
        }

        $certificado['historico'] = $this->certificadoModel->historyForCertificado($certificadoId);
        $certificado['validacoes'] = $this->certificadoModel->validationLogsForCertificado($certificadoId);
        $certificado['emissao_excepcional'] = $this->certificadoExcecaoModel->findByCertificadoId($certificadoId);
        $certificado['assinantes'] = array();

        return array('certificado' => $certificado);
    }

    public function emitir($inscricaoId, array $opcoes = array(), $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        if (!$this->certificadosHabilitados() || !$this->emissaoCertificadosHabilitada()) {
            return array('ok' => false, 'message' => 'A emissão de certificados está desativada nas configurações globais.');
        }

        $contexto = array(
            'permitir_pendencias' => false,
            'emissao_excepcional' => false,
            'nao_duplicar' => !empty($opcoes['nao_duplicar']),
        );

        return $this->emitirInterno($inscricaoId, $opcoes, $actorUserId, $ipAddress, $userAgent, $contexto);
    }

    public function emitirComExcecaoAdministrativa($inscricaoId, array $opcoes = array(), $justificativa = '', $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $opcoes['justificativa'] = $justificativa;

        return $this->emitirInterno($inscricaoId, $opcoes, $actorUserId, $ipAddress, $userAgent, array(
            'permitir_pendencias' => true,
            'emissao_excepcional' => true,
            'nao_duplicar' => true,
            'justificativa' => $justificativa,
        ));
    }

    public function emitirLoteManual(array $inscricaoIds, array $opcoes = array(), $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        if (!$this->certificadosHabilitados() || !$this->emissaoCertificadosHabilitada()) {
            return array(
                'ok' => false,
                'message' => 'A emissão de certificados está desativada nas configurações globais.',
                'resumo' => array(
                    'selecionados' => 0,
                    'emitidos' => 0,
                    'emitidos_excecao' => 0,
                    'ja_emitidos' => 0,
                    'bloqueados' => 0,
                    'erros' => 1,
                ),
                'resultados' => array(),
            );
        }

        $inscricaoIds = array_values(array_unique(array_filter(array_map('intval', $inscricaoIds))));
        $resumo = array(
            'selecionados' => count($inscricaoIds),
            'emitidos' => 0,
            'emitidos_excecao' => 0,
            'ja_emitidos' => 0,
            'bloqueados' => 0,
            'erros' => 0,
        );
        $resultados = array();
        $permitirExcecao = !empty($opcoes['permitir_excecao']);
        $justificativaExcecao = isset($opcoes['justificativa_excecao']) ? trim((string) $opcoes['justificativa_excecao']) : '';

        foreach ($inscricaoIds as $inscricaoId) {
            try {
                $inscricao = $this->inscricaoModel->findById($inscricaoId);
                if (!$inscricao) {
                    $resumo['erros']++;
                    $resultados[] = array(
                        'inscricao_id' => $inscricaoId,
                        'ok' => false,
                        'status' => 'erro',
                        'message' => 'Inscrição não encontrada.',
                    );
                    continue;
                }

                $this->aptidaoService->recalcularInscricao((int) $inscricao['id'], $actorUserId, $ipAddress, $userAgent);
                $inscricao = $this->inscricaoModel->findById($inscricaoId);
                $elegibilidade = $this->elegibilidadeService->calcularParaInscricao($inscricao);
                $situacao = (string) ($elegibilidade['situacao'] ?? 'pendente');
                $existente = $this->certificadoModel->findByInscricao($inscricaoId);

                if ($existente && isset($existente['status']) && (string) $existente['status'] === 'emitido') {
                    $resumo['ja_emitidos']++;
                    $resultados[] = array(
                        'inscricao_id' => $inscricaoId,
                        'ok' => false,
                        'status' => 'ja_emitido',
                        'message' => 'Esta inscrição já possui certificado emitido.',
                        'certificado_id' => (int) $existente['id'],
                    );
                    continue;
                }

                $estaApto = in_array($situacao, array('apto', 'certificado_emitido'), true);
                $contexto = array(
                    'emissao_manual' => true,
                    'permitir_pendencias' => true,
                    'emissao_excepcional' => !$estaApto,
                    'justificativa' => !$estaApto ? $justificativaExcecao : '',
                    'nao_duplicar' => true,
                );

                $resultado = $this->emitirInterno(
                    $inscricaoId,
                    array_merge($opcoes, array('nao_duplicar' => true)),
                    $actorUserId,
                    $ipAddress,
                    $userAgent,
                    $contexto
                );

                if (!empty($resultado['ok'])) {
                    if ($estaApto) {
                        $resumo['emitidos']++;
                    } else {
                        $resumo['emitidos_excecao']++;
                    }

                    $resultados[] = array(
                        'inscricao_id' => $inscricaoId,
                        'ok' => true,
                        'status' => $estaApto ? 'emitido' : 'emitido_excecao',
                        'certificado_id' => isset($resultado['certificado_id']) ? (int) $resultado['certificado_id'] : null,
                        'codigo' => isset($resultado['codigo']) ? $resultado['codigo'] : null,
                        'emissao_excepcional' => $estaApto ? 0 : 1,
                    );
                    continue;
                }

                $mensagem = isset($resultado['message']) ? $resultado['message'] : 'Não foi possível emitir o certificado com exceção.';
                if (strpos((string) $mensagem, 'já possui certificado emitido') !== false) {
                    $resumo['ja_emitidos']++;
                    $resultados[] = array(
                        'inscricao_id' => $inscricaoId,
                        'ok' => false,
                        'status' => 'ja_emitido',
                        'message' => $mensagem,
                    );
                    continue;
                }

                if (strpos((string) $mensagem, 'Acesso negado') !== false) {
                    $resumo['erros']++;
                } elseif ($estaApto) {
                    $resumo['erros']++;
                } else {
                    $resumo['bloqueados']++;
                }

                $resultados[] = array(
                    'inscricao_id' => $inscricaoId,
                    'ok' => false,
                    'status' => strpos((string) $mensagem, 'Acesso negado') !== false ? 'erro' : ($estaApto ? 'erro' : 'bloqueado'),
                    'message' => $mensagem,
                    'situacao_elegibilidade' => $situacao,
                    'motivos_pendencias' => isset($elegibilidade['motivos']) && is_array($elegibilidade['motivos']) ? $elegibilidade['motivos'] : array(),
                );
            } catch (\Throwable $exception) {
                $resumo['erros']++;
                Logger::error('certificados.emissao_manual.item.falhou', array(
                    'inscricao_id' => $inscricaoId,
                    'message' => $exception->getMessage(),
                    'actor_user_id' => $actorUserId,
                ));

                $resultados[] = array(
                    'inscricao_id' => $inscricaoId,
                    'ok' => false,
                    'status' => 'erro',
                    'message' => $exception->getMessage(),
                );
            }
        }

        return array(
            'ok' => true,
            'resumo' => $resumo,
            'resultados' => $resultados,
        );
    }

    private function emitirInterno($inscricaoId, array $opcoes, $actorUserId, $ipAddress, $userAgent, array $contexto = array())
    {
        $emissaoManual = !empty($contexto['emissao_manual']);
        $permitirPendencias = !empty($contexto['permitir_pendencias']);
        $emissaoExcepcional = !empty($contexto['emissao_excepcional']);
        $naoDuplicar = !empty($contexto['nao_duplicar']);
        $justificativa = isset($contexto['justificativa']) ? trim((string) $contexto['justificativa']) : trim((string) (isset($opcoes['justificativa']) ? $opcoes['justificativa'] : ''));

        if ($actorUserId && !$this->podeGerirCertificados($actorUserId)) {
            $this->registrarAcessoNegado('certificado.emitir_negado', $inscricaoId, $actorUserId, $ipAddress, $userAgent);
            return array('ok' => false, 'message' => 'Acesso negado.');
        }

        if ($emissaoExcepcional && $actorUserId && !$this->podeEmitirComExcecao($actorUserId)) {
            $this->registrarAcessoNegado('certificado.emitir_excecao_negado', $inscricaoId, $actorUserId, $ipAddress, $userAgent);
            return array('ok' => false, 'message' => 'Acesso negado.');
        }

        $inscricao = $this->inscricaoModel->findById($inscricaoId);
        if (!$inscricao) {
            return array('ok' => false, 'message' => 'Inscrição não encontrada.');
        }

        $config = $this->globalConfigService->certificados();
        $statusInscricao = (string) $inscricao['status'];
        if ($emissaoExcepcional) {
            if (empty($config['certificados_permitir_emissao_com_pendencias_admin'])) {
                return array('ok' => false, 'message' => 'Emissão com pendências está desabilitada nas configurações globais de certificados.');
            }

            $justificativaLength = function_exists('mb_strlen') ? mb_strlen($justificativa) : strlen($justificativa);
            if ($justificativaLength < 30 || $justificativaLength > 2000) {
                return array('ok' => false, 'message' => 'Informe uma justificativa administrativa com pelo menos 30 caracteres para emitir certificado com pendências.');
            }

            if ($this->statusInscricaoBloqueadaExcecao($statusInscricao)) {
                return array('ok' => false, 'message' => 'Esta inscrição não pode receber emissão excepcional.');
            }

            $permitidos = $this->statusInscricaoPermitidosDoConfig($config);
            if (!empty($permitidos) && !in_array($statusInscricao, $permitidos, true)) {
                return array('ok' => false, 'message' => 'O status atual da inscrição não permite emissão excepcional.');
            }

            if (!$actorUserId) {
                return array('ok' => false, 'message' => 'Não foi possível identificar o usuário responsável pela emissão com pendências.');
            }
        }

        $this->aptidaoService->recalcularInscricao((int) $inscricao['id'], $actorUserId, $ipAddress, $userAgent);
        $inscricao = $this->inscricaoModel->findById($inscricaoId);
        $elegibilidade = $this->elegibilidadeService->calcularParaInscricao($inscricao);
        $situacao = (string) ($elegibilidade['situacao'] ?? 'pendente');
        $motivos = isset($elegibilidade['motivos']) && is_array($elegibilidade['motivos']) ? $elegibilidade['motivos'] : array();

        if (!in_array($situacao, array('apto', 'certificado_emitido'), true) && !$emissaoManual && !$permitirPendencias) {
            $mensagemConteudo = 'O aluno ainda possui itens obrigatórios pendentes no Conteúdo do curso.';
            foreach ($motivos as $motivo) {
                if (strpos((string) $motivo, 'conteúdo_unificado_obrigatorio') !== false || strpos((string) $motivo, 'Conteúdo') !== false || strpos((string) $motivo, 'conteudo_') !== false) {
                    return array('ok' => false, 'message' => $mensagemConteudo, 'motivos' => $motivos, 'situacao_elegibilidade' => $situacao);
                }
            }

            return array('ok' => false, 'message' => 'A inscrição ainda não está apta para certificado.', 'motivos' => $motivos, 'situacao_elegibilidade' => $situacao);
        }

        if ($emissaoExcepcional) {
            $justificativaLength = function_exists('mb_strlen') ? mb_strlen($justificativa) : strlen($justificativa);
            if ($justificativaLength < 30) {
                return array('ok' => false, 'message' => 'Informe uma justificativa administrativa para emitir certificado com pendências.');
            }
        }

        $template = $this->resolveTemplate(
            isset($opcoes['template_id']) ? (int) $opcoes['template_id'] : null,
            (int) $inscricao['curso_evento_id'],
            !empty($inscricao['turma_id']) ? (int) $inscricao['turma_id'] : null
        );
        $existente = $this->certificadoModel->findByInscricao($inscricaoId);
        if ($naoDuplicar && $existente && isset($existente['status']) && (string) $existente['status'] === 'emitido') {
            return array('ok' => false, 'message' => 'Esta inscrição já possui certificado emitido.', 'certificado_id' => (int) $existente['id']);
        }

        $manterCodigo = !empty($opcoes['manter_codigo']);

        $pedido = $this->pedidoModel->findById($inscricao['pedido_id']);
        $participante = $this->findParticipante((int) $inscricao['participante_pedido_id']);
        $curso = $this->findCurso((int) $inscricao['curso_evento_id']);
        $turma = !empty($inscricao['turma_id']) ? $this->turmaModel->findPublicById((int) $inscricao['turma_id']) : null;
        $alunoDataInicio = $this->obterDataInicioAlunoCertificado(array(
            'inscricao_id' => (int) $inscricao['id'],
            'pedido_id' => (int) $inscricao['pedido_id'],
            'curso_evento_id' => (int) $inscricao['curso_evento_id'],
            'turma_id' => !empty($inscricao['turma_id']) ? (int) $inscricao['turma_id'] : null,
        ));
        $agora = date('Y-m-d H:i:s');
        $alunoDataFim = date('d/m/Y', strtotime($agora));

        $codigo = $existente && $manterCodigo ? $existente['codigo'] : $this->novoCodigo();
        $versao = $existente && $manterCodigo ? ((int) $existente['versao'] + 1) : 1;
        $cpfDigits = Validator::onlyDigits(isset($participante['cpf']) ? $participante['cpf'] : '');
        $cpfMasked = $this->mascararCpf($cpfDigits);
        $titulo = isset($template['nome']) ? $template['nome'] : 'Certificado de Conclusao';
        $assinantes = $this->assinantesDoCurso((int) $inscricao['curso_evento_id'], isset($template['id']) ? (int) $template['id'] : null);

        $payload = array(
            'template_id' => isset($template['id']) ? (int) $template['id'] : null,
            'curso_evento_id' => (int) $inscricao['curso_evento_id'],
            'turma_id' => !empty($inscricao['turma_id']) ? (int) $inscricao['turma_id'] : null,
            'pedido_id' => (int) $inscricao['pedido_id'],
            'inscricao_id' => (int) $inscricao['id'],
            'participante_pedido_id' => (int) $inscricao['participante_pedido_id'],
            'usuario_id' => !empty($inscricao['usuario_id']) ? (int) $inscricao['usuario_id'] : null,
            'aluno_data_inicio' => $alunoDataInicio,
            'codigo' => $codigo,
            'versao' => $versao,
            'nome_participante' => isset($participante['nome']) ? $participante['nome'] : 'Participante',
            'cpf_participante' => $cpfDigits,
            'cpf_mascarado' => $cpfMasked,
            'titulo' => $titulo,
            'status' => 'emitido',
            'emitido_por_usuario_id' => $actorUserId,
            'emitido_em' => $agora,
            'data_fim' => $alunoDataFim,
            'reemitido_de_certificado_id' => $existente && !$manterCodigo ? (int) $existente['id'] : null,
            'emissao_excepcional' => $emissaoExcepcional ? 1 : 0,
            'emissao_excepcional_justificativa' => $emissaoExcepcional ? $justificativa : null,
        );

        $pdfBytes = $this->gerarPdfCompleto(
            $payload,
            is_array($template) ? $template : array(),
            is_array($assinantes) ? $assinantes : array(),
            is_array($curso) ? $curso : array(),
            is_array($turma) ? $turma : array(),
            is_array($pedido) ? $pedido : array()
        );
        $pdfRelativePath = $this->salvarPdf($codigo, $pdfBytes);
        $payload['pdf_caminho'] = $pdfRelativePath;
        $payload['pdf_nome_original'] = 'certificado-' . $codigo . '.pdf';

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            if ($existente && $manterCodigo) {
                $this->certificadoModel->update(array_merge($payload, array(
                    'substituido_por_certificado_id' => isset($existente['substituido_por_certificado_id']) ? $existente['substituido_por_certificado_id'] : null,
                )), $existente['id']);
                $certificadoId = (int) $existente['id'];
            } else {
                if ($existente) {
                    $this->certificadoModel->updateStatus($existente['id'], 'substituido', array(
                        'substituido_por_certificado_id' => null,
                    ));
                    $this->certificadoModel->createHistory($existente['id'], $existente['status'], 'substituido', 'Certificado substituído em nova emissão', $actorUserId);
                }

                $certificadoId = $this->certificadoModel->create($payload);
                if ($existente) {
                    $this->certificadoModel->updateStatus($existente['id'], 'substituido', array(
                        'substituido_por_certificado_id' => $certificadoId,
                    ));
                }
            }

            $observacaoHistorico = 'Emissão manual do certificado';
            if ($emissaoExcepcional) {
                $observacaoHistorico = 'Emissão excepcional administrativa com pendências. Justificativa: ' . $this->resumirJustificativa($justificativa);
            }

            $this->certificadoModel->createHistory($certificadoId, $existente ? $existente['status'] : null, 'emitido', $observacaoHistorico, $actorUserId);

            $eventoAudit = $emissaoExcepcional ? 'certificado.emitido_excecao' : 'certificado.emitido';
            $payloadAudit = array(
                'inscricao_id' => (int) $inscricao['id'],
                'codigo' => $codigo,
                'versao' => $versao,
                'template_id' => isset($template['id']) ? (int) $template['id'] : null,
                'manter_codigo' => $manterCodigo,
                'curso_evento_id' => (int) $inscricao['curso_evento_id'],
                'turma_id' => !empty($inscricao['turma_id']) ? (int) $inscricao['turma_id'] : null,
                'participante_pedido_id' => (int) $inscricao['participante_pedido_id'],
                'usuario_id' => !empty($inscricao['usuario_id']) ? (int) $inscricao['usuario_id'] : null,
                'situacao_elegibilidade' => $situacao,
                'motivos_pendencias' => $motivos,
                'ip' => $ipAddress,
                'user_agent' => $userAgent,
            );
            if ($emissaoExcepcional) {
                $payloadAudit['justificativa'] = $justificativa;
            }

            $this->auditService->record(
                $eventoAudit,
                'certificado',
                $certificadoId,
                $payloadAudit,
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            if ($emissaoExcepcional) {
                $this->certificadoExcecaoModel->create(array(
                    'certificado_id' => $certificadoId,
                    'inscricao_id' => (int) $inscricao['id'],
                    'curso_evento_id' => (int) $inscricao['curso_evento_id'],
                    'turma_id' => !empty($inscricao['turma_id']) ? (int) $inscricao['turma_id'] : null,
                    'participante_pedido_id' => (int) $inscricao['participante_pedido_id'],
                    'usuario_id' => !empty($inscricao['usuario_id']) ? (int) $inscricao['usuario_id'] : null,
                    'emitido_por_usuario_id' => (int) $actorUserId,
                    'justificativa' => $justificativa,
                    'situacao_elegibilidade' => $situacao,
                    'motivos_pendencias' => json_encode($motivos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'snapshot_elegibilidade' => json_encode($elegibilidade, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent,
                ));
            }

            Logger::info($eventoAudit, array(
                'certificado_id' => $certificadoId,
                'codigo' => $codigo,
                'inscricao_id' => (int) $inscricao['id'],
                'excecao' => $emissaoExcepcional ? 1 : 0,
            ));

            $pdo->commit();

            $resultadoStatus = $this->inscricaoService->alterarStatus(
                (int) $inscricao['id'],
                'certificado_emitido',
                $emissaoExcepcional ? 'Certificado emitido por exceção administrativa' : 'Certificado emitido manualmente',
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            return array(
                'ok' => true,
                'certificado_id' => $certificadoId,
                'codigo' => $codigo,
                'situacao_elegibilidade' => $situacao,
                'motivos_pendencias' => $motivos,
                'emissao_excepcional' => $emissaoExcepcional ? 1 : 0,
                'email_result' => isset($resultadoStatus['email_result']) ? $resultadoStatus['email_result'] : null,
            );
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('certificado.emitir_falhou', array(
                'inscricao_id' => $inscricaoId,
                'message' => $exception->getMessage(),
            ));

            throw $exception;
        }
    }

    private function podeEmitirComExcecao($usuarioId)
    {
        if (!$usuarioId) {
            return false;
        }

        return $this->rbacService->userHasPermission($usuarioId, 'certificados.emitir_excecao');
    }

    private function statusInscricaoBloqueadaExcecao($status)
    {
        return in_array((string) $status, array('pendente', 'cancelada', 'reprovada', 'excluida'), true);
    }

    private function statusInscricaoPermitidosDoConfig(array $config)
    {
        $raw = isset($config['certificados_status_inscricao_permitidos']) ? (string) $config['certificados_status_inscricao_permitidos'] : '';
        $statuses = array_filter(array_map('trim', explode(',', $raw)), 'strlen');

        return array_values(array_unique($statuses));
    }

    private function resumirJustificativa($justificativa, $limite = 160)
    {
        $justificativa = trim((string) $justificativa);
        if (strlen($justificativa) <= $limite) {
            return $justificativa;
        }

        return rtrim(substr($justificativa, 0, $limite - 3)) . '...';
    }

    public function reemitir($certificadoId, $manterCodigo = true, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        if ($actorUserId && !$this->podeGerirCertificados($actorUserId)) {
            $this->registrarAcessoNegado('certificado.reemitir_negado', $certificadoId, $actorUserId, $ipAddress, $userAgent);
            return array('ok' => false, 'message' => 'Acesso negado.');
        }

        $certificado = $this->certificadoModel->findById($certificadoId);

        if (!$certificado) {
            return array('ok' => false, 'message' => 'Certificado nao encontrado.');
        }

        if (!$this->segundaViaPermitida() && !$manterCodigo) {
            return array('ok' => false, 'message' => 'A segunda via de certificados está desativada nas configurações globais.');
        }

        if ($manterCodigo && !$this->regenerarPdfMesmoCodigo()) {
            // Mantém o fluxo legado: quando a regeneração com o mesmo código está desativada, gera nova via.
            $manterCodigo = false;
        }

        return $this->emitir(
            (int) $certificado['inscricao_id'],
            array(
                'template_id' => $certificado['template_id'],
                'manter_codigo' => (bool) $manterCodigo,
            ),
            $actorUserId,
            $ipAddress,
            $userAgent
        );
    }

    public function cancelar($certificadoId, $observacao = null, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        if ($actorUserId && !$this->podeGerirCertificados($actorUserId)) {
            $this->registrarAcessoNegado('certificado.cancelar_negado', $certificadoId, $actorUserId, $ipAddress, $userAgent);
            return array('ok' => false, 'message' => 'Acesso negado.');
        }

        if (!$this->cancelamentoPermitido()) {
            return array('ok' => false, 'message' => 'O cancelamento de certificados está desativado nas configurações globais.');
        }

        if ($this->motivoCancelamentoObrigatorio() && trim((string) $observacao) === '') {
            return array('ok' => false, 'message' => 'Informe um motivo para cancelar o certificado.');
        }

        return $this->alterarStatusInterno($certificadoId, 'cancelado', $observacao, $actorUserId, $ipAddress, $userAgent, array(
            'cancelado_por_usuario_id' => $actorUserId,
            'cancelado_em' => date('Y-m-d H:i:s'),
        ));
    }

    public function revogar($certificadoId, $observacao = null, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        if ($actorUserId && !$this->podeGerirCertificados($actorUserId)) {
            $this->registrarAcessoNegado('certificado.revogar_negado', $certificadoId, $actorUserId, $ipAddress, $userAgent);
            return array('ok' => false, 'message' => 'Acesso negado.');
        }

        if (!$this->cancelamentoPermitido()) {
            return array('ok' => false, 'message' => 'O cancelamento de certificados está desativado nas configurações globais.');
        }

        if ($this->motivoCancelamentoObrigatorio() && trim((string) $observacao) === '') {
            return array('ok' => false, 'message' => 'Informe um motivo para revogar o certificado.');
        }

        return $this->alterarStatusInterno($certificadoId, 'revogado', $observacao, $actorUserId, $ipAddress, $userAgent, array(
            'revogado_por_usuario_id' => $actorUserId,
            'revogado_em' => date('Y-m-d H:i:s'),
        ));
    }

    public function validarPublicamente($codigo, $cpfInformado = null, $ipAddress = null, $userAgent = null)
    {
        if (!$this->certificadosHabilitados() || !$this->validacaoPublicaHabilitada()) {
            return array('ok' => false, 'message' => 'A validação pública está temporariamente indisponível.');
        }

        $codigo = strtoupper(trim((string) $codigo));
        $certificado = $this->certificadoModel->findByCodigo($codigo);

        if (!$certificado) {
            $this->certificadoModel->logValidation(null, $codigo, Validator::onlyDigits($cpfInformado), 'nao_encontrado', $ipAddress, $userAgent);
            return array('ok' => false, 'message' => 'Certificado nao encontrado.');
        }

        if ($certificado['status'] !== 'emitido') {
            $this->certificadoModel->logValidation((int) $certificado['id'], $codigo, Validator::onlyDigits($cpfInformado), $certificado['status'], $ipAddress, $userAgent);
            return array('ok' => false, 'message' => 'Certificado nao esta ativo para validacao.');
        }

        $cpfDigits = Validator::onlyDigits($cpfInformado);
        if ($cpfDigits !== '' && $cpfDigits !== $certificado['cpf_participante']) {
            $this->certificadoModel->logValidation((int) $certificado['id'], $codigo, $cpfDigits, 'cpf_invalido', $ipAddress, $userAgent);
            return array('ok' => false, 'message' => 'CPF nao confere com o certificado.');
        }

        $this->certificadoModel->logValidation((int) $certificado['id'], $codigo, $cpfDigits !== '' ? $cpfDigits : null, 'valido', $ipAddress, $userAgent);

        $certificado['cpf_mascarado'] = $this->mascararCpf($certificado['cpf_participante']);
        $certificado['pdf_url'] = Helpers::url('certificados/pdf?codigo=' . urlencode($certificado['codigo']));
        $certificado['validacao_url'] = $this->urlValidacaoCertificado($certificado['codigo']);
        $certificado['codigo'] = $codigo;

        return array('ok' => true, 'certificado' => $certificado);
    }

    public function pdfBytesByCodigo($codigo)
    {
        $certificado = $this->certificadoModel->findByCodigo($codigo);
        if (!$certificado) {
            return null;
        }

        return $this->pdfContentForCertificate($certificado);
    }

    public function localizarCertificadoPublico($codigo)
    {
        if (!$this->certificadosHabilitados() || !$this->validacaoPublicaHabilitada()) {
            return null;
        }

        $codigo = strtoupper(trim((string) $codigo));
        if ($codigo === '') {
            return null;
        }

        $certificado = $this->certificadoModel->findByCodigo($codigo);
        if (!$certificado || ($certificado['status'] ?? '') !== 'emitido') {
            return null;
        }

        $certificado['cpf_mascarado'] = $this->mascararCpf(isset($certificado['cpf_participante']) ? $certificado['cpf_participante'] : '');
        $certificado['pdf_url'] = Helpers::url('certificados/pdf?codigo=' . urlencode($certificado['codigo']));
        $certificado['validacao_url'] = $this->urlValidacaoCertificado($certificado['codigo']);
        try {
            $certificado['qr_svg'] = $this->qrSvgDataUri($certificado['validacao_url']);
        } catch (Exception $exception) {
            $certificado['qr_svg'] = '';
            Logger::warning('certificado.qrcode.falhou', array(
                'certificado_id' => isset($certificado['id']) ? (int) $certificado['id'] : null,
                'codigo' => isset($certificado['codigo']) ? (string) $certificado['codigo'] : null,
                'message' => $exception->getMessage(),
            ));
        }
        $certificado['assinantes'] = $this->assinantesDoCurso((int) $certificado['curso_evento_id'], isset($certificado['template_id']) ? (int) $certificado['template_id'] : null);

        return $certificado;
    }

    public function renderizarVersaoOnlinePublicaPorCodigo($codigo)
    {
        if (!$this->certificadosHabilitados() || !$this->validacaoPublicaHabilitada()) {
            return null;
        }

        $codigo = strtoupper(trim((string) $codigo));
        if ($codigo === '') {
            return null;
        }

        $certificado = $this->localizarCertificadoPublico($codigo);
        if (empty($certificado)) {
            return null;
        }

        $template = $this->resolveTemplate(
            isset($certificado['template_id']) ? (int) $certificado['template_id'] : null,
            (int) $certificado['curso_evento_id'],
            !empty($certificado['turma_id']) ? (int) $certificado['turma_id'] : null
        );
        $assinantes = $this->assinantesDoCurso((int) $certificado['curso_evento_id'], isset($template['id']) ? (int) $template['id'] : null);
        $curso = $this->findCurso((int) $certificado['curso_evento_id']);
        $turma = !empty($certificado['turma_id']) ? $this->turmaModel->findPublicById((int) $certificado['turma_id']) : array();
        $pedido = !empty($certificado['pedido_id']) ? $this->pedidoModel->findById((int) $certificado['pedido_id']) : array();
        $certificado['aluno_data_inicio'] = $this->obterDataInicioAlunoCertificado($certificado);
        $certificado['data_fim'] = !empty($certificado['emitido_em']) ? date('d/m/Y', strtotime((string) $certificado['emitido_em'])) : '';

        $renderizacao = $this->montarRenderizacaoCertificadoHtml(
            $certificado,
            is_array($template) ? $template : array(),
            is_array($assinantes) ? $assinantes : array(),
            is_array($curso) ? $curso : array(),
            is_array($turma) ? $turma : array(),
            is_array($pedido) ? $pedido : array()
        );

        if ($renderizacao === null) {
            return null;
        }

        return array_merge($renderizacao, array(
            'certificado' => $certificado,
            'template' => is_array($template) ? $template : array(),
            'assinantes' => is_array($assinantes) ? $assinantes : array(),
            'curso' => is_array($curso) ? $curso : array(),
            'turma' => is_array($turma) ? $turma : array(),
            'pedido' => is_array($pedido) ? $pedido : array(),
        ));
    }

    public function usuarioPodeAcessarCertificado(array $certificado, $usuarioId)
    {
        if (!$usuarioId) {
            return false;
        }

        if ($this->rbacService->userHasPermission($usuarioId, 'certificados.ver')
            || $this->rbacService->userHasPermission($usuarioId, 'certificados.gerenciar')) {
            return true;
        }

        if (!empty($certificado['usuario_id']) && (int) $certificado['usuario_id'] === (int) $usuarioId) {
            return true;
        }

        if (!empty($certificado['pedido_id'])) {
            $pedido = $this->pedidoModel->findById((int) $certificado['pedido_id']);
            if ($pedido && ((int) $pedido['comprador_usuario_id'] === (int) $usuarioId || (int) $pedido['pagador_usuario_id'] === (int) $usuarioId)) {
                return true;
            }
        }

        if (!empty($certificado['inscricao_id'])) {
            $inscricao = $this->inscricaoModel->findById((int) $certificado['inscricao_id']);
            if ($inscricao && !empty($inscricao['usuario_id']) && (int) $inscricao['usuario_id'] === (int) $usuarioId) {
                return true;
            }
        }

        return false;
    }

    public function certificadoParaEmail(?array $certificado = null)
    {
        if (!$certificado) {
            return array();
        }

        $certificado['cpf_mascarado'] = $this->mascararCpf(isset($certificado['cpf_participante']) ? $certificado['cpf_participante'] : '');
        $certificado['pdf_url'] = Helpers::url('certificados/pdf?codigo=' . urlencode($certificado['codigo']));
        $certificado['validacao_url'] = Helpers::url('certificados/validar?codigo=' . urlencode($certificado['codigo']));
        return $certificado;
    }

    public function assinantesDoCurso($cursoId, $templateId = null)
    {
        $assinantes = $this->templateService->assinantesDoCurso($cursoId, $templateId);

        if (!empty($assinantes)) {
            return $assinantes;
        }

        return $this->cursoPessoaModel->forCourse($cursoId);
    }

    private function alterarStatusInterno($certificadoId, $status, $observacao, $actorUserId, $ipAddress, $userAgent, array $fields = array())
    {
        $certificado = $this->certificadoModel->findById($certificadoId);

        if (!$certificado) {
            return array('ok' => false, 'message' => 'Certificado nao encontrado.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $this->certificadoModel->updateStatus($certificadoId, $status, $fields);
            $this->certificadoModel->createHistory($certificadoId, $certificado['status'], $status, $observacao, $actorUserId);

            $this->auditService->record(
                'certificado.status.atualizado',
                'certificado',
                $certificadoId,
                array(
                    'status_anterior' => $certificado['status'],
                    'status_novo' => $status,
                    'observacao' => $observacao,
                ),
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            Logger::info('certificado.status.atualizado', array(
                'certificado_id' => $certificadoId,
                'status_novo' => $status,
            ));

            $pdo->commit();

            return array('ok' => true);
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('certificado.status.falhou', array(
                'certificado_id' => $certificadoId,
                'message' => $exception->getMessage(),
            ));
            throw $exception;
        }
    }

    private function resolveTemplate($templateId = null, $cursoId = null, $turmaId = null)
    {
        if ($templateId) {
            $template = $this->templateModel->findById($templateId);
            if ($template) {
                return $template;
            }
        }

        if ($turmaId) {
            $template = $this->templateModel->findForTurma((int) $turmaId);
            if ($template) {
                return $template;
            }
        }

        if ($cursoId) {
            $template = $this->templateModel->findForCurso((int) $cursoId);
            if ($template) {
                return $template;
            }
        }

        $config = $this->globalConfigService->certificados();
        if (!empty($config['certificados_template_padrao_id'])) {
            $template = $this->templateModel->findById((int) $config['certificados_template_padrao_id']);
            if ($template && (int) ($template['ativo'] ?? 0) === 1) {
                return $template;
            }
        }

        $template = $this->templateModel->findGlobalActive();
        if ($template) {
            return $template;
        }

        return $this->templateModel->defaultTemplate();
    }

    private function findParticipante($participantePedidoId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM participantes_pedido
             WHERE id = :id
               AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(array('id' => $participantePedidoId));
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ?: array();
    }

    private function findCurso($cursoId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM cursos_eventos
             WHERE id = :id
               AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(array('id' => $cursoId));
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return array();
        }

        return $this->enriquecerCursoComProfessorResponsavel($row);
    }

    private function enriquecerCursoComProfessorResponsavel(array $curso)
    {
        $cursoId = !empty($curso['id']) ? (int) $curso['id'] : 0;
        if ($cursoId <= 0) {
            if (!array_key_exists('professor_responsavel', $curso)) {
                $curso['professor_responsavel'] = '';
            }

            return $curso;
        }

        $professorAtual = trim((string) ($curso['professor_responsavel'] ?? ''));
        if ($professorAtual !== '') {
            return $curso;
        }

        $professor = $this->cursoPessoaModel->findProfessorResponsavel($cursoId);
        $nomeProfessor = trim((string) ($professor['nome'] ?? ''));
        if ($nomeProfessor !== '') {
            $curso['professor_responsavel'] = $nomeProfessor;
        } elseif (!array_key_exists('professor_responsavel', $curso)) {
            $curso['professor_responsavel'] = '';
        }

        return $curso;
    }

    private function obterDataInicioAlunoCertificado(array $certificado)
    {
        $certificadoId = !empty($certificado['id']) ? (int) $certificado['id'] : 0;
        $codigo = !empty($certificado['codigo']) ? trim((string) $certificado['codigo']) : '';

        if ($certificadoId <= 0 && $codigo === '') {
            return '';
        }

        $sql = 'SELECT COALESCE(pedido_inscricao.created_at, pedido_certificado.created_at) AS created_at
                FROM certificados c
                LEFT JOIN inscricoes i
                    ON i.id = c.inscricao_id
                   AND i.deleted_at IS NULL
                LEFT JOIN pedidos pedido_inscricao
                    ON pedido_inscricao.id = i.pedido_id
                   AND pedido_inscricao.deleted_at IS NULL
                LEFT JOIN pedidos pedido_certificado
                    ON pedido_certificado.id = c.pedido_id
                   AND pedido_certificado.deleted_at IS NULL
                WHERE c.deleted_at IS NULL';
        $params = array();

        if ($certificadoId > 0) {
            $sql .= ' AND c.id = :certificado_id';
            $params['certificado_id'] = $certificadoId;
        } else {
            $sql .= ' AND c.codigo = :codigo';
            $params['codigo'] = $codigo;
        }

        $sql .= ' LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row || empty($row['created_at'])) {
            return '';
        }

        return date('d/m/Y', strtotime((string) $row['created_at']));
    }

    private function novoCodigo()
    {
        $prefixo = $this->codigoPrefixo();
        $formato = $this->codigoFormato();
        $tamanhoMinimo = $this->codigoTamanhoMinimo();

        for ($tentativa = 0; $tentativa < 12; $tentativa++) {
            $codigo = $this->montarCodigoCertificado($prefixo, $formato, $tamanhoMinimo, $tentativa);
            if ($codigo !== '' && !$this->codigoCertificadoJaExiste($codigo)) {
                return $codigo;
            }
        }

        throw new Exception('Não foi possível gerar um código de certificado único.');
    }

    private function montarCodigoCertificado($prefixo, $formato, $tamanhoMinimo, $tentativa = 0)
    {
        $prefixo = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) $prefixo));
        $ano = date('Y');

        switch ($formato) {
            case 'prefixo_ano_sequencial':
                $sequencial = $this->proximoSequencialCodigoCertificado($prefixo, $ano);
                $codigo = $prefixo . '-' . $ano . '-' . str_pad((string) $sequencial, 6, '0', STR_PAD_LEFT);
                break;
            case 'prefixo_ano_aleatorio':
                $codigo = $prefixo . '-' . $ano . '-' . $this->randomCodigoSegmento(6);
                break;
            case 'prefixo_aleatorio':
                $codigo = $prefixo . '-' . $this->randomCodigoSegmento(8);
                break;
            case 'hash_curto':
                $codigo = $prefixo . '-' . strtoupper(substr(bin2hex(random_bytes(6)), 0, 8));
                break;
            case 'alfanumerico':
            default:
                $codigo = $prefixo . $this->randomCodigoSegmento(max(6, $tamanhoMinimo - strlen($prefixo)));
                break;
        }

        $codigo = strtoupper(preg_replace('/[^A-Z0-9\-]/', '', $codigo));
        if (strlen($codigo) < $tamanhoMinimo) {
            $codigo .= $this->randomCodigoSegmento($tamanhoMinimo - strlen($codigo));
        }

        return $codigo;
    }

    private function randomCodigoSegmento($length)
    {
        $length = max(4, (int) $length);
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $resultado = '';
        $max = strlen($chars) - 1;

        for ($i = 0; $i < $length; $i++) {
            $resultado .= $chars[random_int(0, $max)];
        }

        return $resultado;
    }

    private function proximoSequencialCodigoCertificado($prefixo, $ano)
    {
        $like = $prefixo . '-' . $ano . '-%';
        $stmt = Database::connection()->prepare('SELECT codigo FROM certificados WHERE codigo LIKE :like AND deleted_at IS NULL ORDER BY codigo DESC LIMIT 200');
        $stmt->execute(array('like' => $like));
        $maior = 0;

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $codigo = (string) ($row['codigo'] ?? '');
            if (!preg_match('/^' . preg_quote($prefixo, '/') . '-' . preg_quote($ano, '/') . '-(\d+)$/', $codigo, $matches)) {
                continue;
            }

            $numero = (int) $matches[1];
            if ($numero > $maior) {
                $maior = $numero;
            }
        }

        return $maior + 1;
    }

    private function codigoCertificadoJaExiste($codigo)
    {
        $stmt = Database::connection()->prepare('SELECT id FROM certificados WHERE codigo = :codigo AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(array('codigo' => $codigo));

        return (bool) $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    private function mascararCpf($cpf)
    {
        $cpf = Validator::onlyDigits($cpf);
        if (strlen($cpf) !== 11) {
            return $cpf;
        }

        return substr($cpf, 0, 3) . '.***.***-' . substr($cpf, -2);
    }

    private function salvarPdf($codigo, $bytes)
    {
        $relative = 'certificados/' . date('Y/m') . '/' . $codigo . '.pdf';
        $absolute = BASE_PATH . '/storage/private_uploads/' . $relative;
        $directory = dirname($absolute);

        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        file_put_contents($absolute, $bytes);

        return $relative;
    }

    private function gerarPdf(array $certificado, array $template, array $assinantes, array $curso, array $turma, array $pedido, $templatePossuiQrCode = false)
    {
        $qrMatrix = $templatePossuiQrCode ? array() : $this->buildQrMatrix($this->urlValidacaoCertificado($certificado['codigo']));
        $lines = array(
            'CERTIFICADO DE CONCLUSÃO',
            'Código: ' . $certificado['codigo'],
            'Participante: ' . $certificado['nome_participante'],
            'CPF: ' . $certificado['cpf_participante'],
            'Curso: ' . (isset($curso['nome']) ? $curso['nome'] : ''),
            'Turma: ' . (isset($turma['nome']) ? $turma['nome'] : 'N/A'),
            'Pedido: ' . (isset($pedido['codigo']) ? $pedido['codigo'] : ''),
            'Validação pública: ' . Helpers::url('certificados/validar?codigo=' . urlencode($certificado['codigo'])),
        );
        return $this->buildSimplePdf($lines, $qrMatrix);
    }

    private function pdfContentForCertificate(array $certificado)
    {
        $template = $this->resolveTemplate(
            isset($certificado['template_id']) ? (int) $certificado['template_id'] : null,
            (int) $certificado['curso_evento_id'],
            !empty($certificado['turma_id']) ? (int) $certificado['turma_id'] : null
        );
        $assinantes = $this->assinantesDoCurso((int) $certificado['curso_evento_id'], isset($template['id']) ? (int) $template['id'] : null);
        $curso = $this->findCurso((int) $certificado['curso_evento_id']);
        $turma = !empty($certificado['turma_id']) ? $this->turmaModel->findPublicById((int) $certificado['turma_id']) : array();
        $pedido = !empty($certificado['pedido_id']) ? $this->pedidoModel->findById((int) $certificado['pedido_id']) : array();
        $certificado['aluno_data_inicio'] = $this->obterDataInicioAlunoCertificado($certificado);
        $certificado['data_fim'] = !empty($certificado['emitido_em']) ? date('d/m/Y', strtotime((string) $certificado['emitido_em'])) : '';

        return $this->gerarPdfCompleto(
            $certificado,
            is_array($template) ? $template : array(),
            is_array($assinantes) ? $assinantes : array(),
            is_array($curso) ? $curso : array(),
            is_array($turma) ? $turma : array(),
            is_array($pedido) ? $pedido : array()
        );
    }

    private function gerarPdfPorTemplateHtml(array $certificado, array $template, array $assinantes, array $curso, array $turma, array $pedido, $templatePossuiQrCode = false)
    {
        $corpoHtml = trim((string) ($template['corpo_html'] ?? ''));
        if ($corpoHtml === '') {
            $corpoHtml = $this->corpoHtmlCertificadoPadrao();
        }

        if (!class_exists('TCPDF', false)) {
            $tcpdfFile = BASE_PATH . '/app/Support/Tcpdf/tcpdf.php';
            if (is_file($tcpdfFile)) {
                require_once $tcpdfFile;
            }
        }

        if (!class_exists('TCPDF', false)) {
            return null;
        }

        $templatePossuiQrCode = $templatePossuiQrCode || $this->templatePossuiQrCodeHtml($corpoHtml);
        $contexto = $this->montarContextoPdfCertificado($certificado, $template, $assinantes, $curso, $turma, $pedido);
        $htmlRenderizado = $this->placeholderService->renderizar($corpoHtml, $contexto, array('escape' => true));
        $htmlRenderizado = strtr($htmlRenderizado, array(
            '__CERTIFICADO_ASSINATURA__' => $this->renderizarAssinaturaPlaceholderHtml($template, $contexto),
        ));
        $htmlRenderizado = $this->normalizarTextoUtf8($htmlRenderizado);
        $htmlRenderizado = $this->normalizarHtmlCertificadoPdf($htmlRenderizado, $contexto);
        $htmlRenderizado = $this->completarHtmlCertificadoPdf($htmlRenderizado, $contexto, $templatePossuiQrCode);
        $htmlRenderizado = $this->normalizarTextoUtf8($htmlRenderizado);
        $css = (string) ($template['css'] ?? '');
        $backgroundImage = $this->resolverImagemPdf((string) ($template['imagem_fundo'] ?? ''));
        $backgroundColor = trim((string) ($template['cor_fundo'] ?? ''));
        $color = trim((string) ($template['cor_texto'] ?? ''));

        $styleExtra = '';
        if ($backgroundImage !== '') {
            $styleExtra .= 'body{background-image:url("' . $this->escapeCssUrl($backgroundImage) . '");background-size:cover;background-position:center center;background-repeat:no-repeat;}';
        }
        if ($backgroundColor !== '') {
            $styleExtra .= 'body{background-color:' . $this->sanitizeCssValue($backgroundColor) . ';}';
        }
        if ($color !== '') {
            $styleExtra .= 'body{color:' . $this->sanitizeCssValue($color) . ';}';
        }

        $contextoSegundaPagina = $contexto;
        $contextoSegundaPagina['curso']['carga_horaria'] = trim((string) ($contextoSegundaPagina['curso']['carga_horaria'] ?? '')) !== '' ? $contextoSegundaPagina['curso']['carga_horaria'] : 'Não informado';
        $contextoSegundaPagina['curso']['ementa'] = trim((string) ($contextoSegundaPagina['curso']['ementa'] ?? '')) !== '' ? $contextoSegundaPagina['curso']['ementa'] : 'Não informado';
        $contextoSegundaPagina['curso']['objetivo_geral'] = trim((string) ($contextoSegundaPagina['curso']['objetivo_geral'] ?? '')) !== '' ? $contextoSegundaPagina['curso']['objetivo_geral'] : 'Não informado';
        $contextoSegundaPagina['curso']['professor_responsavel'] = trim((string) ($contextoSegundaPagina['curso']['professor_responsavel'] ?? '')) !== '' ? $contextoSegundaPagina['curso']['professor_responsavel'] : 'Não informado';
        $contextoSegundaPagina['turma']['nome'] = trim((string) ($contextoSegundaPagina['turma']['nome'] ?? '')) !== '' ? $contextoSegundaPagina['turma']['nome'] : 'Não informado';
        $contextoSegundaPagina['turma']['periodo'] = trim((string) ($contextoSegundaPagina['turma']['periodo'] ?? '')) !== '' ? $contextoSegundaPagina['turma']['periodo'] : 'Não informado';
        if (trim((string) ($contextoSegundaPagina['logo_url'] ?? '')) === '') {
            $contextoSegundaPagina['logo_url'] = $this->logoTransparenteDataUri();
        }

        $segundaPaginaHtml = $this->renderizarSegundaPaginaCertificado($template, $contexto);
        $paginaPrimeira = $this->montarHtmlCertificadoPdf($htmlRenderizado, $css, $styleExtra);
        $paginaSegunda = $this->montarHtmlCertificadoPdf($segundaPaginaHtml, $css, $styleExtra);
        $this->registrarHtmlDebugCertificado(
            isset($certificado['codigo']) ? (string) $certificado['codigo'] : '',
            $paginaPrimeira,
            $paginaSegunda,
            $template
        );

        try {
            $pdf = new \TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
            $pdf->SetCreator('Desbloqueia Cursos');
            $pdf->SetAuthor('Desbloqueia Cursos');
            $pdf->SetTitle((string) ($certificado['titulo'] ?? 'Certificado'));
            $pdf->SetSubject('Certificado');
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->setImageScale(1);
            $pdf->SetHeaderMargin(0);
            $pdf->SetFooterMargin(0);
            $pdf->SetMargins(8, 8, 8);
            $pdf->SetAutoPageBreak(false, 0);
            $pdf->setCellPadding(0);
            $pdf->SetFont('dejavusans', '', 12);
            $this->escreverPaginaPdfCertificado($pdf, $backgroundImage, $paginaPrimeira, true);
            $this->escreverPaginaPdfCertificado($pdf, $backgroundImage, $paginaSegunda);

            return $pdf->Output('', 'S');
        } catch (Exception $exception) {
            Logger::error('certificado.pdf.html_falhou', array(
                'certificado_id' => isset($certificado['id']) ? (int) $certificado['id'] : null,
                'template_id' => isset($template['id']) ? (int) $template['id'] : null,
                'codigo' => isset($certificado['codigo']) ? (string) $certificado['codigo'] : null,
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ));
            return null;
        }
    }

    private function montarContextoPdfCertificado(array $certificado, array $template, array $assinantes, array $curso, array $turma, array $pedido)
    {
        $codigo = (string) ($certificado['codigo'] ?? '');
        $dataEmissao = !empty($certificado['emitido_em']) ? date('d/m/Y H:i', strtotime((string) $certificado['emitido_em'])) : date('d/m/Y H:i');
        $pedidoCodigo = isset($pedido['codigo']) ? (string) $pedido['codigo'] : '';
        $validacaoUrl = $this->urlValidacaoCertificado($codigo);
        $qrcode = $codigo !== '' ? $this->qrSvgDataUri($validacaoUrl) : '';
        $institucional = $this->globalConfigService->institucional();
        $configCertificados = $this->globalConfigService->certificados();
        $logo = $this->resolverLogoCertificado($template, $configCertificados, $institucional);
        $imagemFundo = $this->resolverImagemPdf((string) ($template['imagem_fundo'] ?? ''));
        $nomeInstituicao = !empty($institucional['nome_fantasia']) ? (string) $institucional['nome_fantasia'] : 'Desbloqueia Cursos';
        $cidade = !empty($institucional['cidade']) ? (string) $institucional['cidade'] : '';
        $uf = !empty($institucional['uf']) ? (string) $institucional['uf'] : '';
        $cidadeUf = trim($cidade . ($uf !== '' ? '/' . $uf : ''));

        $assinaturas = array();
        foreach ($assinantes as $assinante) {
            $assinaturas[] = array(
                'nome' => isset($assinante['nome']) ? (string) $assinante['nome'] : '',
                'cargo' => isset($assinante['cargo']) ? (string) $assinante['cargo'] : '',
                'imagem' => '',
            );
        }

        $assinatura1 = isset($assinaturas[0]) ? $assinaturas[0] : array('nome' => '', 'cargo' => '', 'imagem' => '');
        $assinatura2 = isset($assinaturas[1]) ? $assinaturas[1] : array('nome' => '', 'cargo' => '', 'imagem' => '');
        $assinatura3 = isset($assinaturas[2]) ? $assinaturas[2] : array('nome' => '', 'cargo' => '', 'imagem' => '');

        return array(
            'aluno' => array(
                'id' => isset($certificado['usuario_id']) ? (int) $certificado['usuario_id'] : null,
                'nome' => isset($certificado['nome_participante']) ? (string) $certificado['nome_participante'] : '',
                'email' => '',
                'documento' => isset($certificado['cpf_mascarado']) ? (string) $certificado['cpf_mascarado'] : '',
                'cpf' => isset($certificado['cpf_participante']) ? (string) $certificado['cpf_participante'] : '',
                'data_inicio' => isset($certificado['aluno_data_inicio']) ? (string) $certificado['aluno_data_inicio'] : '',
            ),
            'curso' => array(
                'id' => isset($curso['id']) ? (int) $curso['id'] : (!empty($certificado['curso_evento_id']) ? (int) $certificado['curso_evento_id'] : null),
                'curso_evento_id' => isset($curso['id']) ? (int) $curso['id'] : (!empty($certificado['curso_evento_id']) ? (int) $certificado['curso_evento_id'] : null),
                'nome' => isset($curso['nome']) ? (string) $curso['nome'] : '',
                'slug' => isset($curso['slug']) ? (string) $curso['slug'] : '',
                'categoria' => isset($curso['categoria']) ? (string) $curso['categoria'] : '',
                'tipo' => isset($curso['tipo']) ? (string) $curso['tipo'] : '',
                'modalidade' => isset($curso['modalidade']) ? (string) $curso['modalidade'] : '',
                'carga_horaria' => isset($curso['carga_horaria']) ? (string) $curso['carga_horaria'] : '',
                'descricao_curta' => isset($curso['descricao_curta']) ? (string) $curso['descricao_curta'] : '',
                'professor_responsavel' => isset($curso['professor_responsavel']) ? (string) $curso['professor_responsavel'] : '',
                'objetivo_geral' => isset($curso['objetivo_geral']) ? (string) $curso['objetivo_geral'] : '',
                'ementa' => isset($curso['ementa']) ? (string) $curso['ementa'] : '',
            ),
            'turma' => array(
                'id' => isset($turma['id']) ? (int) $turma['id'] : (!empty($certificado['turma_id']) ? (int) $certificado['turma_id'] : null),
                'nome' => isset($turma['nome']) ? (string) $turma['nome'] : '',
                'codigo' => isset($turma['codigo']) ? (string) $turma['codigo'] : '',
                'data_inicio' => isset($turma['data_inicio']) ? (string) $turma['data_inicio'] : '',
                'data_fim' => isset($turma['data_fim']) ? (string) $turma['data_fim'] : '',
                'periodo' => isset($turma['periodo']) ? (string) $turma['periodo'] : '',
                'horario' => isset($turma['horario']) ? (string) $turma['horario'] : '',
                'local' => isset($turma['local']) ? (string) $turma['local'] : '',
            ),
            'inscricao' => array(
                'id' => isset($certificado['inscricao_id']) ? (int) $certificado['inscricao_id'] : null,
                'status' => isset($certificado['inscricao_status']) ? (string) $certificado['inscricao_status'] : '',
                'data' => isset($certificado['created_at']) ? date('d/m/Y H:i', strtotime((string) $certificado['created_at'])) : '',
            ),
            'pedido' => array(
                'codigo' => $pedidoCodigo,
                'status' => isset($pedido['status']) ? (string) $pedido['status'] : '',
            ),
            'aproveitamento' => array(
                'frequencia_percentual' => isset($certificado['presenca_percentual']) ? (string) $certificado['presenca_percentual'] : '',
                'nota_final' => isset($certificado['nota_final']) ? (string) $certificado['nota_final'] : '',
                'percentual_progresso' => isset($certificado['percentual_progresso']) ? (string) $certificado['percentual_progresso'] : '',
                'data_conclusao' => isset($certificado['concluida_em']) ? (string) $certificado['concluida_em'] : '',
                'situacao_conclusao' => isset($certificado['status']) ? (string) $certificado['status'] : '',
            ),
            'certificado' => array(
                'codigo' => $codigo,
                'hash' => isset($certificado['hash']) ? (string) $certificado['hash'] : '',
                'data_emissao' => $dataEmissao,
                'data_reemissao' => isset($certificado['reemitido_em']) ? (string) $certificado['reemitido_em'] : '',
                'data_fim' => trim((string) ($certificado['data_fim'] ?? '')) !== ''
                    ? (string) $certificado['data_fim']
                    : (!empty($certificado['emitido_em']) ? date('d/m/Y', strtotime((string) $certificado['emitido_em'])) : ''),
                'numero_via' => isset($certificado['versao']) ? (string) $certificado['versao'] : '1',
            ),
            'instituicao' => array(
                'nome' => $nomeInstituicao,
                'cnpj' => !empty($institucional['cnpj']) ? (string) $institucional['cnpj'] : '',
                'site' => rtrim((string) (require BASE_PATH . '/config/app.php')['url'], '/'),
                'email' => !empty($institucional['email_institucional']) ? (string) $institucional['email_institucional'] : '',
                'endereco' => !empty($institucional['endereco']) ? (string) $institucional['endereco'] : '',
                'cidade' => $cidade,
                'estado' => $uf,
                'logo_url' => $logo,
            ),
            'assinaturas' => array(
                'assinatura_1_nome' => $assinatura1['nome'],
                'assinatura_1_cargo' => $assinatura1['cargo'],
                'assinatura_1_imagem' => $assinatura1['imagem'],
                'assinatura_2_nome' => $assinatura2['nome'],
                'assinatura_2_cargo' => $assinatura2['cargo'],
                'assinatura_2_imagem' => $assinatura2['imagem'],
                'assinatura_3_nome' => $assinatura3['nome'],
                'assinatura_3_cargo' => $assinatura3['cargo'],
                'assinatura_3_imagem' => $assinatura3['imagem'],
                'professor_nome' => isset($curso['professor_responsavel']) ? (string) $curso['professor_responsavel'] : '',
                'coordenador_nome' => '',
            ),
            'certificado_url_validacao' => $validacaoUrl,
            'certificado_qrcode' => $qrcode,
            'logo_url' => $logo,
            'background_url' => $imagemFundo,
            'imagem_fundo_url' => $imagemFundo,
            'assinatura_url' => trim((string) ($template['assinatura_url'] ?? '')),
            'assinatura' => '__CERTIFICADO_ASSINATURA__',
            'cidade_data_atual' => $cidadeUf !== '' ? ($cidadeUf . ', ' . date('d/m/Y')) : date('d/m/Y'),
        );
    }

    private function gerarPdfCompleto(array $certificado, array $template, array $assinantes, array $curso, array $turma, array $pedido, $templatePossuiQrCode = false)
    {
        $templatePossuiQrCode = $templatePossuiQrCode || $this->templatePossuiQrCodeHtml((string) ($template['corpo_html'] ?? ''));
        $pdfHtml = $this->gerarPdfPorTemplateHtml($certificado, $template, $assinantes, $curso, $turma, $pedido, $templatePossuiQrCode);

        if ($pdfHtml !== null && $pdfHtml !== '') {
            return $pdfHtml;
        }

        return $this->gerarPdf($certificado, $template, $assinantes, $curso, $turma, $pedido, $templatePossuiQrCode);
    }

    private function montarRenderizacaoCertificadoHtml(array $certificado, array $template, array $assinantes, array $curso, array $turma, array $pedido)
    {
        $corpoHtml = trim((string) ($template['corpo_html'] ?? ''));
        if ($corpoHtml === '') {
            $corpoHtml = $this->corpoHtmlCertificadoPadrao();
        }

        $contexto = $this->montarContextoPdfCertificado($certificado, $template, $assinantes, $curso, $turma, $pedido);
        $htmlRenderizado = $this->placeholderService->renderizar($corpoHtml, $contexto, array('escape' => true));
        $htmlRenderizado = strtr($htmlRenderizado, array(
            '__CERTIFICADO_ASSINATURA__' => $this->renderizarAssinaturaPlaceholderHtml($template, $contexto),
        ));
        $templatePossuiQrCode = $this->templatePossuiQrCodeHtml($corpoHtml);
        $htmlRenderizado = $this->normalizarTextoUtf8($htmlRenderizado);
        $htmlRenderizado = $this->normalizarHtmlCertificadoPdf($htmlRenderizado, $contexto);
        $htmlRenderizado = $this->completarHtmlCertificadoPdf($htmlRenderizado, $contexto, $templatePossuiQrCode);
        $htmlRenderizado = $this->normalizarTextoUtf8($htmlRenderizado);
        $css = (string) ($template['css'] ?? '');
        $backgroundImage = $this->resolverImagemPdf((string) ($template['imagem_fundo'] ?? ''));
        $backgroundColor = trim((string) ($template['cor_fundo'] ?? ''));
        $color = trim((string) ($template['cor_texto'] ?? ''));

        $styleExtra = '';
        if ($backgroundImage !== '') {
            $styleExtra .= 'body{background-image:url("' . $this->escapeCssUrl($backgroundImage) . '");background-size:cover;background-position:center center;background-repeat:no-repeat;}';
        }
        if ($backgroundColor !== '') {
            $styleExtra .= 'body{background-color:' . $this->sanitizeCssValue($backgroundColor) . ';}';
        }
        if ($color !== '') {
            $styleExtra .= 'body{color:' . $this->sanitizeCssValue($color) . ';}';
        }

        $segundaPaginaHtml = $this->renderizarSegundaPaginaCertificado($template, $contexto);
        $paginaPrimeira = $this->montarHtmlCertificadoPdf($htmlRenderizado, $css, $styleExtra);
        $paginaSegunda = $this->montarHtmlCertificadoPdf($segundaPaginaHtml, $css, $styleExtra);

        return array(
            'pagina_primeira_html' => $paginaPrimeira,
            'pagina_segunda_html' => $paginaSegunda,
            'css' => $css,
            'style_extra' => $styleExtra,
            'background_image' => $backgroundImage,
            'background_color' => $backgroundColor,
            'color' => $color,
            'contexto' => $contexto,
        );
    }

    private function corpoHtmlCertificadoPadrao()
    {
        return '
            <div class="certificado-documento">
                <div class="certificado-logo-institucional">
                    <img src="{logo_url}" alt="{instituicao_nome}" width="180" height="54" style="width:180px;height:auto;border:none;background:transparent;">
                </div>

                <div class="certificado-titulo">Certificado</div>
                <div class="certificado-subtitulo">de conclusão</div>

                <p class="certificado-intro">Certificamos que</p>
                <div class="certificado-nome">{aluno_nome}</div>
                <p class="certificado-frase">concluiu com êxito o curso <strong>{curso_nome}</strong>.</p>

                <table class="certificado-resumo" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        <td width="50%">
                            <span class="rotulo">Carga horária</span>
                            <span class="valor">{curso_carga_horaria}</span>
                        </td>
                        <td width="50%">
                            <span class="rotulo">Modalidade</span>
                            <span class="valor">{curso_modalidade}</span>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <span class="rotulo">Turma</span>
                            <span class="valor">{turma_nome}</span>
                        </td>
                        <td>
                            <span class="rotulo">Período</span>
                            <span class="valor">{turma_data_inicio} a {turma_data_fim}</span>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <span class="rotulo">Local de emissão</span>
                            <span class="valor">{turma_local}</span>
                        </td>
                        <td>
                            <span class="rotulo">Data de emissão</span>
                            <span class="valor">{certificado_data_emissao}</span>
                        </td>
                    </tr>
                </table>

                <div class="certificado-bloco-complementar">
                    <table class="certificado-info" cellpadding="0" cellspacing="0" border="0">
                        <tr>
                            <td width="60%"><span class="rotulo">Valide em:</span> {certificado_url_validacao}</td>
                            <td width="40%"><span class="rotulo">Código:</span> <span class="codigo">{certificado_codigo}</span></td>
                        </tr>
                    </table>
                </div>

                <table class="certificado-assinaturas" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        <td width="33%">
                            <div class="assinatura-linha"></div>
                            <strong class="assinatura-nome">{assinatura_1_nome}</strong><br>
                            <span class="assinatura-cargo">{assinatura_1_cargo}</span>
                        </td>
                        <td width="33%">
                            <div class="assinatura-linha"></div>
                            <strong class="assinatura-nome">{assinatura_2_nome}</strong><br>
                            <span class="assinatura-cargo">{assinatura_2_cargo}</span>
                        </td>
                        <td width="34%">
                            <div class="assinatura-linha"></div>
                            <strong class="assinatura-nome">{assinatura_3_nome}</strong><br>
                            <span class="assinatura-cargo">{assinatura_3_cargo}</span>
                        </td>
                    </tr>
                </table>

                <table class="certificado-rodape" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        <td width="100%" align="center">
                            <img class="certificado-qrcode" src="{certificado_qrcode}" alt="QR Code do certificado" width="70" height="70">
                        </td>
                    </tr>
                </table>
            </div>
        ';
    }

    public function corpoHtmlSegundaPaginaCertificado(array $template = array())
    {
        $htmlCustom = trim((string) ($template['html_segunda_pagina'] ?? ''));
        if ($htmlCustom !== '') {
            return $htmlCustom;
        }

        return '
            <div style="padding:8px;">
                <div style="border:1.5px solid #b9a06a;padding:16px 18px 14px 18px;">
                    <div style="text-align:center;margin:0 0 6px 0;">
                        <div class="certificado-logo-institucional" style="margin-bottom:6px;">
                            <img src="{logo_url}" alt="{instituicao_nome}" width="150" height="45" style="width:150px;height:auto;border:none;background:transparent;display:block;margin:0 auto;">
                        </div>
                    </div>

                    <div style="width:62%;border-top:1px solid #d7c18c;margin:7px auto 10px auto;"></div>
                    <div style="font-size:20pt;line-height:1.05;margin:0;text-align:center;font-weight:bold;letter-spacing:1px;text-transform:uppercase;color:#3d3420;">Conteúdo programático</div>
                    <div style="font-size:10.5pt;line-height:1.15;margin:2px 0 10px 0;text-align:center;color:#5a4d31;">{curso_nome}</div>

                    <table style="width:100%;table-layout:fixed;border-collapse:collapse;margin:0 0 8px 0;" cellpadding="0" cellspacing="0" border="0">
                        <tr>
                            <td width="50%" style="padding:0 4px 4px 0;vertical-align:top;font-size:8.8pt;line-height:1.22;">
                                <strong>Aluno:</strong> {aluno_nome}<br>
                                <strong>Curso:</strong> {curso_nome}
                            </td>
                            <td width="50%" style="padding:0 0 4px 4px;vertical-align:top;font-size:8.8pt;line-height:1.22;">
                                <strong>Carga horária:</strong> {curso_carga_horaria}<br>
                                <strong>Código:</strong> {certificado_codigo}
                            </td>
                        </tr>
                    </table>

                    <table style="width:100%;table-layout:fixed;border-collapse:collapse;margin:0;" cellpadding="0" cellspacing="0" border="0">
                        <tr>
                            <td style="border:1px solid #d9cfb8;padding:10px 12px;vertical-align:top;">
                                <div style="font-size:8pt;text-transform:uppercase;letter-spacing:.4px;color:#6b5a39;margin-bottom:6px;font-weight:bold;">Conteúdo programático</div>
                                <div style="font-size:9.3pt;line-height:1.36;color:#202020;white-space:pre-line;word-break:break-word;min-height:110px;">{curso_ementa}</div>
                            </td>
                        </tr>
                    </table>

                    <table style="width:100%;table-layout:fixed;border-collapse:collapse;margin-top:8px;" cellpadding="0" cellspacing="0" border="0">
                        <tr>
                            <td width="50%" style="padding:0 4px 0 0;vertical-align:top;font-size:8.8pt;line-height:1.22;">
                                <strong>Objetivo geral:</strong><br>
                                <span style="white-space:pre-line;">{curso_objetivo_geral}</span>
                            </td>
                            <td width="50%" style="padding:0 0 0 4px;vertical-align:top;font-size:8.8pt;line-height:1.22;">
                                <strong>Professor responsável:</strong> {curso_professor_responsavel}<br>
                                <strong>Turma:</strong> {turma_nome}<br>
                                <strong>Período:</strong> {turma_periodo}
                            </td>
                        </tr>
                    </table>

                    <table style="width:100%;table-layout:fixed;border-collapse:collapse;margin-top:8px;" cellpadding="0" cellspacing="0" border="0">
                        <tr>
                            <td width="60%" style="padding:0 4px 0 0;font-size:8.2pt;line-height:1.18;vertical-align:top;">
                                <strong>Validação pública:</strong> <span style="word-break:break-word;">{certificado_url_validacao}</span>
                            </td>
                            <td width="40%" style="padding:0 0 0 4px;font-size:8.2pt;line-height:1.18;vertical-align:top;">
                                <strong>Data de emissão:</strong> {certificado_data_emissao}
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        ';
    }

    public function renderizarSegundaPaginaCertificado(array $template, array $contexto = array())
    {
        $contexto = $this->prepararContextoSegundaPaginaCertificado($contexto);
        $blocos = $this->montarBlocosSegundaPaginaCertificado($contexto);
        $htmlFonte = $this->corpoHtmlSegundaPaginaCertificado($template);
        $htmlFonte = str_replace(
            array('{conteudo_programatico}', '{modulos_conteudos}', '{modulos_nomes}', '{assinatura}'),
            array('__CERTIFICADO_CONTEUDO_PROGRAMATICO__', '__CERTIFICADO_MODULOS_CONTEUDOS__', '__CERTIFICADO_MODULOS_NOMES__', '__CERTIFICADO_ASSINATURA__'),
            $htmlFonte
        );
        $renderizado = $this->placeholderService->renderizar($htmlFonte, $contexto, array('escape' => true));
        $renderizado = strtr($renderizado, array(
            '__CERTIFICADO_CONTEUDO_PROGRAMATICO__' => $blocos['conteudo_programatico'],
            '__CERTIFICADO_MODULOS_CONTEUDOS__' => $blocos['modulos_conteudos'],
            '__CERTIFICADO_MODULOS_NOMES__' => $blocos['modulos_nomes'],
            '__CERTIFICADO_ASSINATURA__' => $this->renderizarAssinaturaPlaceholderHtml($template, $contexto),
        ));

        return $this->normalizarTextoUtf8($renderizado);
    }

    private function prepararContextoSegundaPaginaCertificado(array $contexto)
    {
        if (!isset($contexto['curso']) || !is_array($contexto['curso'])) {
            $contexto['curso'] = array();
        }
        if (!isset($contexto['turma']) || !is_array($contexto['turma'])) {
            $contexto['turma'] = array();
        }

        $contexto['curso']['carga_horaria'] = trim((string) ($contexto['curso']['carga_horaria'] ?? '')) !== '' ? $contexto['curso']['carga_horaria'] : 'Não informado';
        $contexto['curso']['ementa'] = trim((string) ($contexto['curso']['ementa'] ?? '')) !== '' ? $contexto['curso']['ementa'] : 'Não informado';
        $contexto['curso']['objetivo_geral'] = trim((string) ($contexto['curso']['objetivo_geral'] ?? '')) !== '' ? $contexto['curso']['objetivo_geral'] : 'Não informado';
        $contexto['curso']['professor_responsavel'] = trim((string) ($contexto['curso']['professor_responsavel'] ?? '')) !== '' ? $contexto['curso']['professor_responsavel'] : 'Não informado';
        $contexto['curso']['conteudo_programatico_tipo'] = trim((string) ($contexto['curso']['conteudo_programatico_tipo'] ?? '')) !== '' ? $contexto['curso']['conteudo_programatico_tipo'] : 'texto';
        $contexto['curso']['conteudo_programatico_texto'] = isset($contexto['curso']['conteudo_programatico_texto']) ? (string) $contexto['curso']['conteudo_programatico_texto'] : '';
        $contexto['curso']['conteudo_programatico_modulos'] = isset($contexto['curso']['conteudo_programatico_modulos']) ? (string) $contexto['curso']['conteudo_programatico_modulos'] : '';
        $contexto['turma']['nome'] = trim((string) ($contexto['turma']['nome'] ?? '')) !== '' ? $contexto['turma']['nome'] : 'Não informado';
        $contexto['turma']['periodo'] = trim((string) ($contexto['turma']['periodo'] ?? '')) !== '' ? $contexto['turma']['periodo'] : 'Não informado';

        if (trim((string) ($contexto['logo_url'] ?? '')) === '') {
            $contexto['logo_url'] = $this->logoTransparenteDataUri();
        }

        $contexto['assinatura'] = '__CERTIFICADO_ASSINATURA__';
        $contexto['conteudo_programatico'] = '__CERTIFICADO_CONTEUDO_PROGRAMATICO__';
        $contexto['modulos_conteudos'] = '__CERTIFICADO_MODULOS_CONTEUDOS__';
        $contexto['modulos_nomes'] = '__CERTIFICADO_MODULOS_NOMES__';

        return $contexto;
    }

    private function montarBlocosSegundaPaginaCertificado(array $contexto)
    {
        $curso = isset($contexto['curso']) && is_array($contexto['curso']) ? $contexto['curso'] : array();
        $turma = isset($contexto['turma']) && is_array($contexto['turma']) ? $contexto['turma'] : array();
        $certificado = isset($contexto['certificado']) && is_array($contexto['certificado']) ? $contexto['certificado'] : array();
        $cursoId = isset($curso['id']) ? (int) $curso['id'] : 0;
        $turmaId = isset($turma['id']) ? (int) $turma['id'] : (!empty($certificado['turma_id']) ? (int) $certificado['turma_id'] : null);
        $codigo = isset($certificado['codigo']) ? (string) $certificado['codigo'] : '';
        $inscricaoId = !empty($certificado['inscricao_id']) ? (int) $certificado['inscricao_id'] : 0;
        $programatico = $this->cursoService->prepararConteudoProgramaticoParaView($curso);
        $tipo = isset($programatico['tipo']) ? (string) $programatico['tipo'] : 'texto';
        $texto = isset($programatico['texto']) ? trim((string) $programatico['texto']) : '';
        $html = isset($programatico['html']) ? trim((string) $programatico['html']) : '';
        $modulos = isset($programatico['modulos']) && is_array($programatico['modulos']) ? $programatico['modulos'] : array();

        $conteudoProgramaticoModulosHtml = $this->renderizarConteudoProgramaticoModulosHtmlSegundaPagina($modulos);
        $modulosNomesHtml = trim((string) ($contexto['modulos_nomes_html'] ?? ''));
        if ($modulosNomesHtml === '') {
            $modulosNomesHtml = $this->renderizarModulosNomesHtmlSegundaPagina($cursoId, $turmaId, $codigo, $inscricaoId);
        }

        $modulosHtml = trim((string) ($contexto['modulos_conteudos_html'] ?? ''));
        if ($modulosHtml === '') {
            $modulosHtml = $this->renderizarModulosConteudosHtmlSegundaPagina($cursoId, $turmaId, $codigo, $inscricaoId);
        }
        if ($tipo === 'modulos') {
            $conteudoProgramaticoHtml = $conteudoProgramaticoModulosHtml;
        } elseif ($html !== '') {
            $conteudoProgramaticoHtml = '<div class="certificado-conteudo-programatico certificado-conteudo-programatico--html" style="font-size:9.3pt;line-height:1.36;color:#202020;word-break:break-word;">' . $html . '</div>';
        } elseif ($texto !== '') {
            $conteudoProgramaticoHtml = '<div class="certificado-conteudo-programatico certificado-conteudo-programatico--texto" style="font-size:9.3pt;line-height:1.36;color:#202020;white-space:pre-line;word-break:break-word;">' . nl2br(Helpers::e($texto)) . '</div>';
        } else {
            $conteudoProgramaticoHtml = '<p style="margin:0;font-size:9.3pt;line-height:1.36;color:#202020;">Conteúdo programático não informado.</p>';
        }

        return array(
            'conteudo_programatico' => $conteudoProgramaticoHtml,
            'modulos_conteudos' => $modulosHtml,
            'modulos_nomes' => $modulosNomesHtml,
        );
    }

    private function renderizarConteudoProgramaticoModulosHtmlSegundaPagina(array $modulos)
    {
        if (empty($modulos)) {
            return '<p style="margin:0;font-size:9.3pt;line-height:1.36;color:#202020;">Conteúdo programático não informado.</p>';
        }

        $html = '<div class="certificado-modulos-conteudos" style="font-size:9.3pt;line-height:1.36;color:#202020;">';

        foreach ($modulos as $modulo) {
            if (!is_array($modulo)) {
                continue;
            }

            $numeroModulo = isset($modulo['ordem']) ? (int) $modulo['ordem'] : 0;
            $titulo = $this->formatarTituloModuloParaPdf((string) ($modulo['titulo'] ?? ''), $numeroModulo);
            $itens = isset($modulo['itens']) && is_array($modulo['itens']) ? $modulo['itens'] : array();
            $tituloLabel = $titulo !== '' ? $titulo : 'Módulo';

            $html .= '<h3 style="margin:10px 0 6px 0;font-size:11pt;line-height:1.2;color:#3d3420;">' . Helpers::e($tituloLabel) . '</h3>';

            if (!empty($itens)) {
                $html .= '<ul style="margin:0 0 8px 18px;padding:0;">';
                foreach ($itens as $item) {
                    $item = trim((string) $item);
                    if ($item === '') {
                        continue;
                    }
                    $html .= '<li style="margin:0 0 3px 0;">' . Helpers::e($item) . '</li>';
                }
                $html .= '</ul>';
            } else {
                $html .= '<p style="margin:0 0 8px 0;">Conteúdo não informado.</p>';
            }
        }

        $html .= '</div>';
        return $html;
    }

    private function renderizarModulosConteudosHtmlSegundaPagina($cursoId, $turmaId = null, $codigo = '', $inscricaoId = 0)
    {
        $cursoId = (int) $cursoId;
        if ($cursoId <= 0) {
            return '<p style="margin:0;font-size:9.3pt;line-height:1.36;color:#202020;">Módulos e conteúdos não informados.</p>';
        }

        $turmaId = $turmaId !== null ? (int) $turmaId : null;
        $diagnostico = $this->carregarModulosConteudosCertificado($cursoId, $turmaId);
        $modulos = isset($diagnostico['modulos']) && is_array($diagnostico['modulos']) ? $diagnostico['modulos'] : array();
        $origem = isset($diagnostico['origem']) ? (string) $diagnostico['origem'] : 'desconhecida';
        $blocos = array();
        $quantidadeModulos = 0;
        $quantidadeItens = 0;

        foreach ($modulos as $modulo) {
            if (!is_array($modulo)) {
                continue;
            }

            $moduloId = (int) ($modulo['id'] ?? 0);
            if ($moduloId <= 0) {
                continue;
            }

            $itens = isset($modulo['itens']) && is_array($modulo['itens']) ? $modulo['itens'] : array();
            $itensHtml = array();
            foreach ($itens as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $tituloItem = $this->limparTituloModuloParaPdf((string) ($item['titulo'] ?? ''));
                if ($tituloItem === '') {
                    continue;
                }

                $quantidadeItens++;
                $itensHtml[] = '&nbsp;&nbsp;&bull; ' . Helpers::textoLms($tituloItem) . '<br>';
            }

            $quantidadeModulos++;
            $numeroModulo = isset($modulo['ordem']) ? (int) $modulo['ordem'] : 0;
            $tituloModulo = $this->limparTituloModuloParaPdf((string) ($modulo['titulo'] ?? ''));
            if ($tituloModulo === '') {
                $tituloModulo = 'Módulo';
            }
            if ($numeroModulo > 0 && !preg_match('/^Módulo\s+\d+/iu', $tituloModulo)) {
                $tituloModulo = 'Módulo ' . $numeroModulo . ' — ' . $tituloModulo;
            }

            $bloco = '<div style="margin:0 0 8px 0;">';
            $bloco .= '<strong>' . Helpers::textoLms($tituloModulo) . '</strong><br>';
            if (!empty($itensHtml)) {
                $bloco .= implode('', $itensHtml);
            } else {
                $bloco .= 'Conteúdo não informado.';
            }
            $bloco .= '</div>';
            $blocos[] = $bloco;
        }

        Logger::info('certificado.modulos_conteudos.diagnosticado', array(
            'codigo' => $codigo !== '' ? $codigo : null,
            'inscricao_id' => $inscricaoId > 0 ? $inscricaoId : null,
            'curso_id' => $cursoId,
            'curso_evento_id' => $cursoId,
            'turma_id' => $turmaId !== null ? (int) $turmaId : null,
            'relacao' => $origem,
            'modulos' => $quantidadeModulos,
            'itens' => $quantidadeItens,
        ));

        if (empty($blocos)) {
            return '<p style="margin:0;font-size:9.3pt;line-height:1.36;color:#202020;">Módulos e conteúdos não informados.</p>';
        }

        return '<div class="certificado-modulos-conteudos" style="font-size:9.3pt;line-height:1.36;color:#202020;">' . implode("\n", $blocos) . '</div>';
    }

    private function renderizarModulosNomesHtmlSegundaPagina($cursoId, $turmaId = null, $codigo = '', $inscricaoId = 0)
    {
        $cursoId = (int) $cursoId;
        if ($cursoId <= 0) {
            return '<p style="margin:0;font-size:9.3pt;line-height:1.36;color:#202020;">Módulos não informados.</p>';
        }

        $turmaId = $turmaId !== null ? (int) $turmaId : null;
        $diagnostico = $this->carregarModulosConteudosCertificado($cursoId, $turmaId);
        $modulos = isset($diagnostico['modulos']) && is_array($diagnostico['modulos']) ? $diagnostico['modulos'] : array();
        $origem = isset($diagnostico['origem']) ? (string) $diagnostico['origem'] : 'desconhecida';
        $blocos = array();
        $quantidadeModulos = 0;

        foreach ($modulos as $modulo) {
            if (!is_array($modulo)) {
                continue;
            }

            $numeroModulo = isset($modulo['ordem']) ? (int) $modulo['ordem'] : 0;
            $tituloModulo = $this->formatarTituloModuloParaPdf((string) ($modulo['titulo'] ?? ''), $numeroModulo);
            if ($tituloModulo === '') {
                continue;
            }

            $blocos[] = '<div style="margin:0 0 4px 0;">' . Helpers::textoLms($tituloModulo) . '</div>';
            $quantidadeModulos++;
        }

        Logger::info('certificado.modulos_nomes.diagnosticado', array(
            'codigo' => $codigo !== '' ? $codigo : null,
            'inscricao_id' => $inscricaoId > 0 ? $inscricaoId : null,
            'curso_id' => $cursoId,
            'curso_evento_id' => $cursoId,
            'turma_id' => $turmaId !== null ? (int) $turmaId : null,
            'relacao' => $origem,
            'modulos' => $quantidadeModulos,
        ));

        if (empty($blocos)) {
            return '<p style="margin:0;font-size:9.3pt;line-height:1.36;color:#202020;">Módulos não informados.</p>';
        }

        return '<div class="certificado-modulos-nomes" style="font-size:9.3pt;line-height:1.36;color:#202020;">' . implode("\n", $blocos) . '</div>';
    }

    private function carregarModulosConteudosCertificado($cursoId, $turmaId = null)
    {
        $cursoId = (int) $cursoId;
        $turmaId = $turmaId !== null ? (int) $turmaId : null;

        $modulos = $this->carregarModulosConteudosCertificadoUnificado($cursoId);
        if (!empty($modulos)) {
            return array(
                'origem' => 'conteudo_unificado',
                'modulos' => $modulos,
            );
        }

        $modulos = $this->carregarModulosConteudosCertificadoLegado($cursoId, $turmaId);
        return array(
            'origem' => 'modulos_aulas_legado',
            'modulos' => $modulos,
        );
    }

    private function carregarModulosConteudosCertificadoUnificado($cursoId)
    {
        $modulos = $this->conteudoModuloModel->listForCurso($cursoId, 'publicado');
        if (empty($modulos)) {
            return array();
        }

        $resultado = array();
        foreach ($modulos as $modulo) {
            if (!is_array($modulo)) {
                continue;
            }

            $moduloId = (int) ($modulo['id'] ?? 0);
            if ($moduloId <= 0) {
                continue;
            }

            $itens = $this->conteudoItemModel->listForModulo($moduloId, 'publicado');
            $itensNormalizados = array();
            foreach ($itens as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $tituloItem = trim((string) ($item['titulo'] ?? ''));
                if ($tituloItem === '') {
                    continue;
                }
                $item['titulo'] = $tituloItem;
                $itensNormalizados[] = $item;
            }

            $modulo['itens'] = $itensNormalizados;
            $resultado[] = $modulo;
        }

        return $resultado;
    }

    private function carregarModulosConteudosCertificadoLegado($cursoId, $turmaId = null)
    {
        $modulos = $this->moduloModel->listForContext($cursoId, $turmaId);
        if (empty($modulos)) {
            return array();
        }

        $resultado = array();
        foreach ($modulos as $modulo) {
            if (!is_array($modulo)) {
                continue;
            }

            if (!$this->conteudoPublicadoParaCertificado($modulo)) {
                continue;
            }

            $moduloId = (int) ($modulo['id'] ?? 0);
            if ($moduloId <= 0) {
                continue;
            }

            $itens = $this->aulaModel->listForModulo($moduloId);
            $itensFiltrados = array();
            foreach ($itens as $item) {
                if (!is_array($item)) {
                    continue;
                }
                if (!$this->conteudoPublicadoParaCertificado($item)) {
                    continue;
                }
                $tituloItem = trim((string) ($item['titulo'] ?? ''));
                if ($tituloItem === '') {
                    continue;
                }
                $item['titulo'] = $tituloItem;
                $itensFiltrados[] = $item;
            }

            $modulo['itens'] = $itensFiltrados;
            $resultado[] = $modulo;
        }

        return $resultado;
    }

    private function conteudoPublicadoParaCertificado(array $conteudo)
    {
        if (isset($conteudo['status']) && $conteudo['status'] !== '') {
            return (string) $conteudo['status'] === 'publicado';
        }

        if (array_key_exists('visivel', $conteudo)) {
            return !empty($conteudo['visivel']);
        }

        return true;
    }

    private function completarHtmlCertificadoPdf($html, array $contexto, $templatePossuiQrCode = false)
    {
        $html = trim((string) $html);
        if ($html === '') {
            return $html;
        }

        $blocos = array();
        $certificado = isset($contexto['certificado']) && is_array($contexto['certificado']) ? $contexto['certificado'] : array();
        $assinaturas = isset($contexto['assinaturas']) && is_array($contexto['assinaturas']) ? $contexto['assinaturas'] : array();
        $qrcode = trim((string) ($contexto['certificado_qrcode'] ?? ''));

        if ($templatePossuiQrCode) {
            $html .= "\n<!-- certificado-qrcode -->";
            if (empty($blocos)) {
                return $html;
            }

            return $html . "\n" . implode("\n", $blocos);
        }

        $temQrcode = stripos($html, 'certificado-qrcode') !== false || stripos($html, '{certificado_qrcode}') !== false;
        $temValidacao = stripos($html, 'certificado_codigo') !== false
            || stripos($html, 'certificado_url_validacao') !== false
            || stripos($html, 'Código de validação') !== false
            || stripos($html, 'Valide em') !== false
            || stripos($html, 'Validação pública') !== false
            || stripos($html, 'Data de emissão') !== false
            || stripos($html, 'Data:') !== false;

        $codigo = (string) ($certificado['codigo'] ?? '');
        $validacaoUrl = Helpers::url('certificados/validar?codigo=' . urlencode($codigo));
        $dataEmissao = (string) ($certificado['data_emissao'] ?? ($contexto['cidade_data_atual'] ?? date('d/m/Y')));
        $e = function ($value) {
            return Helpers::e((string) ($value === null ? '' : $value));
        };

        if ($qrcode !== '' && !$temQrcode) {
            if ($temValidacao) {
                $blocos[] = '
                <table class="certificado-qrcode-somente" cellpadding="0" cellspacing="0" border="0" style="width:100%;table-layout:fixed;margin-top:8px;">
                    <tr>
                        <td align="center">
                            <img class="certificado-qrcode" src="' . $e($qrcode) . '" alt="QR Code do certificado" width="70" height="70">
                        </td>
                    </tr>
                </table>
            ';
            } elseif ($codigo !== '') {
                $blocos[] = '
                <table class="certificado-rodape" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                      <td width="70%">
                          <div><strong>Código:</strong> ' . $e($codigo) . '</div>
                          <div><strong>Validação pública:</strong> ' . $e($validacaoUrl) . '</div>
                          <div><strong>Data:</strong> ' . $e($dataEmissao) . '</div>
                      </td>
                        <td width="30%" align="center">
                            <img class="certificado-qrcode" src="' . $e($qrcode) . '" alt="QR Code do certificado" width="70" height="70">
                        </td>
                    </tr>
                </table>
            ';
            }
        } elseif ($qrcode === '' && !$temValidacao && $codigo !== '') {
            $blocos[] = '
              <table class="certificado-rodape" cellpadding="0" cellspacing="0" border="0">
                  <tr>
                      <td width="100%">
                          <div><strong>Código:</strong> ' . $e($codigo) . '</div>
                          <div><strong>Validação pública:</strong> ' . $e($validacaoUrl) . '</div>
                          <div><strong>Data:</strong> ' . $e($dataEmissao) . '</div>
                      </td>
                  </tr>
              </table>
          ';
        }

        $html .= "\n<!-- certificado-qrcode -->";

        if (stripos($html, 'certificado-qrcode') === false && stripos($html, '{certificado_qrcode}') === false) {
            $codigo = (string) ($certificado['codigo'] ?? '');
            $validacaoUrl = Helpers::url('certificados/validar?codigo=' . urlencode($codigo));
            $dataEmissao = (string) ($certificado['data_emissao'] ?? ($contexto['cidade_data_atual'] ?? date('d/m/Y')));
              if ($codigo !== '' || $qrcode !== '') {
                  $e = function ($value) {
                      return Helpers::e((string) ($value === null ? '' : $value));
                  };
                  $blocos[] = '
                  <table class="certificado-rodape" cellpadding="0" cellspacing="0" border="0">
                      <tr>
                          <td width="70%">
                              <div><strong>Código:</strong> ' . $e($codigo) . '</div>
                              <div><strong>Validação pública:</strong> ' . $e($validacaoUrl) . '</div>
                              <div><strong>Data:</strong> ' . $e($dataEmissao) . '</div>
                          </td>
                          <td width="30%" align="center">
                              <img class="certificado-qrcode" src="' . $e($qrcode) . '" alt="QR Code do certificado" width="70" height="70">
                          </td>
                    </tr>
                </table>
            ';
            }
        }

        if (empty($blocos)) {
            return $html;
        }

        return $html . "\n" . implode("\n", $blocos);
    }

    private function resolverLogoCertificado(array $template, array $configCertificados, array $institucional)
    {
        $logoTemplate = $this->resolverImagemPdf((string) ($template['logo'] ?? ''));
        if ($logoTemplate !== '') {
            return $logoTemplate;
        }

        $logoCertificados = $this->resolverImagemPdf((string) ($configCertificados['certificados_logo_padrao'] ?? ''));
        if ($logoCertificados !== '') {
            return $logoCertificados;
        }

        if (!empty($configCertificados['certificados_usar_logo_institucional'])) {
            $logoInstitucional = $this->resolverImagemPdf((string) ($institucional['logo_caminho'] ?? ''));
            if ($logoInstitucional !== '') {
                return $logoInstitucional;
            }
        }

        return $this->logoTransparenteDataUri();
    }

    private function montarHtmlCertificadoPdf($conteudoHtml, $css, $styleExtra)
    {
        return '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><style>' .
            'html,body{margin:0;padding:0;width:100%;font-family:DejaVu Sans,sans-serif;font-size:10pt;line-height:1.25;}' .
            'table{border-collapse:collapse;border-spacing:0;}' .
            'img{border:0;display:block;}' .
            '.certificado-pdf-page{width:100%;}' .
            '.certificado-pdf-page td{vertical-align:top;}' .
            '.certificado-documento{padding:16px 18px 14px 18px;}' .
            '.certificado-logo-institucional{text-align:center;margin:0 0 6px 0;}' .
            '.certificado-logo-institucional img{width:180px;height:auto;border:none !important;background:transparent !important;display:block;margin:0 auto;}' .
            '.certificado-logo-institucional img[src^="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/L9kAAAAASUVORK5CYII="]{width:1px;height:1px;max-width:1px;max-height:1px;opacity:0;}' .
            '.certificado-titulo{font-size:24pt;line-height:1.05;margin:0;text-align:center;font-weight:bold;letter-spacing:1px;text-transform:uppercase;color:#3d3420;}' .
            '.certificado-subtitulo{font-size:11pt;line-height:1.15;margin:2px 0 10px 0;text-align:center;color:#5a4d31;}' .
            '.certificado-intro{font-size:11pt;line-height:1.35;margin:0 0 6px 0;text-align:center;color:#3f3f3f;}' .
            '.certificado-nome{font-size:20pt;line-height:1.12;margin:0 0 7px 0;text-align:center;font-weight:bold;color:#1f2937;}' .
            '.certificado-frase{font-size:11pt;line-height:1.35;margin:0 0 8px 0;text-align:center;color:#3f3f3f;}' .
            '.certificado-resumo{width:100%;table-layout:fixed;margin:8px 0 8px 0;}' .
            '.certificado-resumo td{border:1px solid #d9cfb8;padding:7px 9px;font-size:8.8pt;line-height:1.25;vertical-align:top;}' .
            '.certificado-resumo .rotulo{display:block;font-size:7.8pt;text-transform:uppercase;letter-spacing:.4px;color:#6b5a39;margin-bottom:2px;font-weight:bold;}' .
            '.certificado-resumo .valor{display:block;font-size:9.2pt;color:#202020;}' .
            '.certificado-bloco-complementar{margin-top:8px;}' .
            '.certificado-info{width:100%;table-layout:fixed;}' .
            '.certificado-info td{padding:0 4px 4px 0;font-size:8.8pt;line-height:1.22;}' .
            '.certificado-info .rotulo{font-weight:bold;white-space:nowrap;color:#5a4d31;}' .
            '.certificado-assinaturas{width:100%;table-layout:fixed;margin-top:12px;}' .
            '.certificado-assinaturas td{padding:0 6px;vertical-align:top;text-align:center;font-size:8.4pt;line-height:1.15;}' .
            '.certificado-assinaturas .assinatura-linha{border-top:1px solid #111;width:65%;margin:0 auto 5px auto;}' .
            '.certificado-assinaturas .assinatura-nome{font-weight:bold;color:#111827;}' .
            '.certificado-assinaturas .assinatura-cargo{display:block;margin-top:1px;color:#555;}' .
            '.certificado-qrcode{width:70px;height:70px;max-width:70px;max-height:70px;margin:0 auto 4px auto;}' .
            '.certificado-rodape{width:100%;table-layout:fixed;margin-top:10px;}' .
            '.certificado-rodape td{padding:0 4px;font-size:8.2pt;line-height:1.18;vertical-align:top;}' .
            '.certificado-rodape .codigo{font-weight:bold;word-break:break-word;color:#202020;}' .
            '.certificado-segunda-pagina{width:100%;}' .
            $css . $styleExtra .
            '</style></head><body><div class="certificado-pdf-page">' . $conteudoHtml . '</div></body></html>';
    }

    private function desenharMolduraCertificado($pdf)
    {
        $pageWidth = (float) $pdf->getPageWidth();
        $pageHeight = (float) $pdf->getPageHeight();
        $outerInset = 7.5;
        $innerInset = 10.5;

        $pdf->SetLineStyle(array(
            'width' => 0.55,
            'color' => array(15, 39, 66),
        ));
        $pdf->Rect(
            $outerInset,
            $outerInset,
            $pageWidth - ($outerInset * 2),
            $pageHeight - ($outerInset * 2)
        );

        $pdf->SetLineStyle(array(
            'width' => 0.30,
            'color' => array(216, 189, 114),
        ));
        $pdf->Rect(
            $innerInset,
            $innerInset,
            $pageWidth - ($innerInset * 2),
            $pageHeight - ($innerInset * 2)
        );
    }

    private function escreverPaginaPdfCertificado($pdf, $backgroundImage, $htmlPagina, $desenharMoldura = false)
    {
        $pdf->AddPage('L', 'A4');

        if (trim((string) $backgroundImage) !== '') {
            $pdf->Image($backgroundImage, 0, 0, $pdf->getPageWidth(), $pdf->getPageHeight(), '', '', '', false, 300, '', false, false, 0, false, false, false);
        }

        if ($desenharMoldura) {
            $this->desenharMolduraCertificado($pdf);
        }

        $pdf->writeHTML($htmlPagina, true, false, true, false, '');
    }

    private function logoTransparenteDataUri()
    {
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/L9kAAAAASUVORK5CYII=';
    }

    private function renderizarAssinaturaPlaceholderHtml(array $template, array $contexto)
    {
        $assinaturaUrl = trim((string) ($contexto['assinatura_url'] ?? ($template['assinatura_url'] ?? '')));
        if ($assinaturaUrl === '') {
            return '';
        }

        $src = $this->resolverImagemPdf($assinaturaUrl);
        if (!is_string($src) || trim($src) === '' || !is_file($src)) {
            Logger::warning('certificado.assinatura.nao_encontrada', array(
                'assinatura_url' => $assinaturaUrl,
                'assinatura_fisica' => is_string($src) ? $src : null,
            ));
            return '';
        }

        return '<img src="' . Helpers::e($src) . '" alt="Assinatura" style="width:160px;height:auto;border:none;background:transparent;">';
    }

    private function normalizarHtmlCertificadoPdf($html, array $contexto)
    {
        $html = trim((string) $html);
        if ($html === '') {
            return $html;
        }

        $logoAtual = trim((string) ($contexto['instituicao']['logo_url'] ?? ''));
        if ($logoAtual === '') {
            $logoAtual = trim((string) ($contexto['logo_url'] ?? ''));
        }
        if ($logoAtual === '') {
            return $html;
        }

        $logosAntigos = array(
            'https://desbloqueiacursos.com.br/assets/uploads/modulos/modulo-20260513005207-c9b85f4683e3.png',
            'http://desbloqueiacursos.com.br/assets/uploads/modulos/modulo-20260513005207-c9b85f4683e3.png',
            'https://www.desbloqueiacursos.com.br/assets/uploads/modulos/modulo-20260513005207-c9b85f4683e3.png',
            'http://www.desbloqueiacursos.com.br/assets/uploads/modulos/modulo-20260513005207-c9b85f4683e3.png',
            '/assets/uploads/modulos/modulo-20260513005207-c9b85f4683e3.png',
            'assets/uploads/modulos/modulo-20260513005207-c9b85f4683e3.png',
        );

        return str_replace($logosAntigos, $logoAtual, $html);
    }

    private function buildSimplePdf(array $lines, array $qrMatrix)
    {
        $width = 842;
        $height = 595;
        $content = array();
        $content[] = 'q';
        $content[] = '0.95 0.97 1 rg';
        $content[] = '0 0 ' . $width . ' ' . $height . ' re f';
        $content[] = '0 0 0 rg';
        $content[] = 'BT';
        $content[] = '/F1 22 Tf';
        $content[] = '50 ' . ($height - 60) . ' Td';
        $content[] = '(' . $this->escapePdfText($this->normalizarTextoPdf($lines[0])) . ') Tj';

        $content[] = '0 -34 Td';
        $content[] = '/F1 12 Tf';
        $content[] = '16 TL';
        for ($i = 1; $i < count($lines); $i++) {
            $content[] = '(' . $this->escapePdfText($this->normalizarTextoPdf($lines[$i])) . ') Tj';
            if ($i < count($lines) - 1) {
                $content[] = 'T*';
            }
        }
        $content[] = 'ET';

        $qrX = 560;
        $qrY = 170;
        $moduleSize = 6;
        $quiet = 4;
        $qrRows = isset($qrMatrix['bcode']) && is_array($qrMatrix['bcode']) ? $qrMatrix['bcode'] : $qrMatrix;
        $size = isset($qrMatrix['num_rows']) ? (int) $qrMatrix['num_rows'] : count($qrRows);
        $sizeCols = isset($qrMatrix['num_cols']) ? (int) $qrMatrix['num_cols'] : ($size > 0 && isset($qrRows[0]) && is_array($qrRows[0]) ? count($qrRows[0]) : $size);

        if (!empty($qrRows)) {
            $content[] = '0 0 0 rg';
            for ($row = 0; $row < $size; $row++) {
                for ($col = 0; $col < $sizeCols; $col++) {
                    if (empty($qrRows[$row][$col])) {
                        continue;
                    }

                    $x = $qrX + (($col + $quiet) * $moduleSize);
                    $y = $qrY + ((($size - 1 - $row) + $quiet) * $moduleSize);
                    $content[] = $x . ' ' . $y . ' ' . $moduleSize . ' ' . $moduleSize . ' re f';
                }
            }
        }

        $content[] = 'Q';

        $stream = implode("\n", $content);
        $objects = array();
        $objects[] = '1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj';
        $objects[] = '2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj';
        $objects[] = '3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . $width . ' ' . $height . '] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >> endobj';
        $objects[] = '4 0 obj << /Length ' . strlen($stream) . ' >> stream' . "\n" . $stream . "\nendstream endobj";
        $objects[] = '5 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj';

        $pdf = "%PDF-1.4\n";
        $offsets = array(0);
        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object . "\n";
        }

        $xrefPos = strlen($pdf);
        $pdf .= 'xref' . "\n";
        $pdf .= '0 ' . (count($offsets)) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i < count($offsets); $i++) {
            $pdf .= sprintf('%010d 00000 n ', $offsets[$i]) . "\n";
        }
        $pdf .= 'trailer << /Size ' . count($offsets) . ' /Root 1 0 R >>' . "\n";
        $pdf .= 'startxref' . "\n" . $xrefPos . "\n%%EOF";

        return $pdf;
    }

    private function templatePossuiQrCodeHtml($html)
    {
        $html = (string) $html;

        return stripos($html, '{certificado_qrcode}') !== false;
    }

    private function normalizarTextoPdf($value)
    {
        $value = $this->normalizarTextoUtf8((string) $value);
        $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $value);
        if ($converted === false || $converted === null) {
            $converted = utf8_decode($value);
        }

        return $converted;
    }

    private function normalizarTextoUtf8($texto)
    {
        $texto = (string) $texto;
        $texto = str_replace(
            array(
                'Não',
                'Módulo',
                'Módulos',
                'Conteúdo',
                'Conteúdos',
                'emissão',
                'validação',
                'período',
                'responsável',
                'informações',
                'pré',
                'pública',
                'página',
                'avaliação',
                'conclusão',
                'horária',
                'correção',
            ),
            array(
                'Não',
                'Módulo',
                'Módulos',
                'Conteúdo',
                'Conteúdos',
                'emissão',
                'validação',
                'período',
                'responsável',
                'informações',
                'pré',
                'pública',
                'página',
                'avaliação',
                'conclusão',
                'horária',
                'correção',
            ),
            $texto
        );

        return $texto;
    }

    private function limparTituloModuloParaPdf($titulo)
    {
        $titulo = $this->normalizarTextoUtf8((string) $titulo);
        $titulo = preg_replace('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}]/u', '', $titulo);
        $titulo = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $titulo);
        $titulo = preg_replace('/\s+/u', ' ', trim($titulo));

        return $titulo;
    }

    private function formatarTituloModuloParaPdf($titulo, $numeroModulo = 0)
    {
        $titulo = $this->limparTituloModuloParaPdf($titulo);
        if ($titulo === '') {
            return $numeroModulo > 0 ? 'Módulo ' . (int) $numeroModulo : '';
        }

        $tituloSemPrefixo = preg_replace('/^(?:M[oó]dulo\s+\d+\s*[:\-—–]?\s*)+/iu', '', $titulo);
        $tituloSemPrefixo = preg_replace('/\s+/u', ' ', trim((string) $tituloSemPrefixo));

        if ($tituloSemPrefixo === '') {
            return $titulo;
        }

        if ($numeroModulo > 0) {
            return 'Módulo ' . (int) $numeroModulo . ' — ' . $tituloSemPrefixo;
        }

        return $tituloSemPrefixo;
    }

    private function normalizarFormatoPapel($value)
    {
        $value = strtoupper(trim((string) $value));
        if ($value === 'CARTA' || $value === 'LETTER') {
            return 'LETTER';
        }

        return 'A4';
    }

    private function normalizarOrientacaoPdf($value)
    {
        $value = strtolower(trim((string) $value));
        if ($value === 'retrato' || $value === 'portrait' || $value === 'p') {
            return 'P';
        }

        return 'L';
    }

    private function normalizarMargensPdf(array $template)
    {
        $top = isset($template['margem_top']) && is_numeric($template['margem_top']) ? (float) $template['margem_top'] : 0.0;
        $bottom = isset($template['margem_bottom']) && is_numeric($template['margem_bottom']) ? (float) $template['margem_bottom'] : 0.0;
        $left = isset($template['margem_left']) && is_numeric($template['margem_left']) ? (float) $template['margem_left'] : 0.0;
        $right = isset($template['margem_right']) && is_numeric($template['margem_right']) ? (float) $template['margem_right'] : 0.0;

        return array(
            'top' => $top,
            'bottom' => $bottom,
            'left' => $left,
            'right' => $right,
        );
    }

    private function resolverImagemPdf($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        if (preg_match('#^data:#i', $value) || preg_match('#^https?://#i', $value)) {
            return $value;
        }

        $caminhoRelativo = ltrim($value, '/\\');
        $candidatos = array(
            $this->publicRootPath() . '/' . $caminhoRelativo,
            BASE_PATH . '/' . $caminhoRelativo,
            BASE_PATH . '/public_html/' . $caminhoRelativo,
            BASE_PATH . '/public/' . $caminhoRelativo,
            BASE_PATH . '/storage/' . $caminhoRelativo,
        );

        foreach ($candidatos as $arquivo) {
            if (is_file($arquivo)) {
                return $arquivo;
            }
        }

        if ($value[0] === '/') {
            $publico = BASE_PATH . '/public_html' . $value;
            if (is_file($publico)) {
                return $publico;
            }
        }

        return $value;
    }

    private function publicRootPath()
    {
        $candidatos = array(
            BASE_PATH . '/public_html',
            BASE_PATH . '/public',
            BASE_PATH,
        );

        foreach ($candidatos as $candidato) {
            if (is_dir($candidato)) {
                return $candidato;
            }
        }

        return BASE_PATH;
    }

    private function registrarHtmlDebugCertificado($codigo, $paginaPrimeira, $paginaSegunda, array $template)
    {
        $codigo = strtoupper(trim((string) $codigo));
        if ($codigo === '') {
            return;
        }

        $debugHabilitado = getenv('CERTIFICADO_DEBUG_HTML');
        if ($codigo !== 'DESRTKQ5NH' && $debugHabilitado !== '1') {
            return;
        }

        $diretorio = BASE_PATH . '/storage/tmp';
        if (!is_dir($diretorio)) {
            @mkdir($diretorio, 0775, true);
        }

        if (!is_dir($diretorio) || !is_writable($diretorio)) {
            Logger::warning('certificado.debug_html.diretorio_indisponivel', array(
                'codigo' => $codigo,
                'diretorio' => $diretorio,
            ));
            return;
        }

        $arquivo = $diretorio . '/certificado-debug-' . $codigo . '.html';
        $conteudo = "<!-- certificado debug: " . $codigo . " -->\n";
        $conteudo .= "<!-- template_id: " . (int) ($template['id'] ?? 0) . " -->\n";
        $conteudo .= "<!-- primeira pagina -->\n";
        $conteudo .= (string) $paginaPrimeira;
        $conteudo .= "\n<!-- segunda pagina -->\n";
        $conteudo .= (string) $paginaSegunda;

        $salvo = @file_put_contents($arquivo, $conteudo);
        if ($salvo === false) {
            Logger::warning('certificado.debug_html.falhou', array(
                'codigo' => $codigo,
                'arquivo' => $arquivo,
            ));
            return;
        }

        Logger::info('certificado.debug_html.salvo', array(
            'codigo' => $codigo,
            'arquivo' => $arquivo,
            'bytes' => $salvo,
        ));
    }

    private function sanitizeCssValue($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        return preg_replace('/[<>]/', '', $value);
    }

    private function escapeCssUrl($value)
    {
        $value = (string) $value;
        return str_replace(array('"', "'", '(', ')', ' '), array('%22', '%27', '%28', '%29', '%20'), $value);
    }

    private function podeGerirCertificados($usuarioId)
    {
        if (!$usuarioId) {
            return false;
        }

        return $this->rbacService->userHasPermission($usuarioId, 'certificados.gerenciar')
            || $this->rbacService->userHasPermission($usuarioId, 'certificados.ver');
    }

    private function registrarAcessoNegado($evento, $recursoId, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $payload = array(
            'recurso_id' => $recursoId,
            'usuario_id' => $actorUserId,
            'ip_address' => $ipAddress,
        );

        $this->auditService->record($evento, 'certificado', $recursoId, $payload, $actorUserId, $ipAddress, $userAgent);
        Logger::error($evento, $payload);
    }

    private function escapePdfText($text)
    {
        $text = (string) $text;
        $text = str_replace(array('\\', '(', ')'), array('\\\\', '\\(', '\\)'), $text);
        return $text;
    }

    private function buildQrMatrix($text)
    {
        $barcode = $this->buildQrBarcode((string) $text);
        if ($barcode !== null) {
            return $barcode->getBarcodeArray();
        }

        $text = (string) $text;
        if (strlen($text) > 17) {
            Logger::warning('certificado.qrcode.falhou', array(
                'message' => 'Encoder QR alternativo indisponível para o conteúdo informado.',
                'texto_len' => strlen($text),
            ));

            return array_fill(0, 21, array_fill(0, 21, false));
        }

        $data = $this->qrDataBytes($text);
        $ecc = $this->qrEccBytes($data, 7);
        $codewords = array_merge($data, $ecc);
        $bits = array();
        foreach ($codewords as $byte) {
            for ($i = 7; $i >= 0; $i--) {
                $bits[] = (($byte >> $i) & 1) === 1;
            }
        }

        $size = 21;
        $matrix = array_fill(0, $size, array_fill(0, $size, null));

        $this->qrDrawFinder($matrix, 0, 0);
        $this->qrDrawFinder($matrix, $size - 7, 0);
        $this->qrDrawFinder($matrix, 0, $size - 7);
        for ($i = 0; $i < $size; $i++) {
            if ($matrix[6][$i] === null) {
                $matrix[6][$i] = ($i % 2) === 0;
            }
            if ($matrix[$i][6] === null) {
                $matrix[$i][6] = ($i % 2) === 0;
            }
        }

        $matrix[8][13] = true;

        $this->qrReserveFormatInfo($matrix);
        $this->qrPlaceData($matrix, $bits);
        $this->qrPlaceFormatBits($matrix, '111011111000100');

        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                if ($matrix[$y][$x] === null) {
                    $matrix[$y][$x] = false;
                }
            }
        }

        return $matrix;
    }

    private function buildQrBarcode($text)
    {
        $text = trim((string) $text);
        if ($text === '') {
            return null;
        }

        if (!class_exists('TCPDF2DBarcode', false)) {
            $barcodeFile = BASE_PATH . '/app/Support/Tcpdf/tcpdf_barcodes_2d.php';
            if (is_file($barcodeFile)) {
                require_once $barcodeFile;
            }
        }

        if (!class_exists('TCPDF2DBarcode', false)) {
            return null;
        }

        return new \TCPDF2DBarcode($text, 'QRCODE,H');
    }

    private function qrDataBytes($text)
    {
        $text = (string) $text;
        if (strlen($text) > 17) {
            throw new Exception('Codigo do certificado muito longo para QR de versao simples.');
        }

        $bits = array();
        $this->qrAppendBits($bits, 0b0100, 4);
        $this->qrAppendBits($bits, strlen($text), 8);
        foreach (str_split($text) as $char) {
            $this->qrAppendBits($bits, ord($char), 8);
        }

        $dataBytes = $this->qrBitsToBytes($bits);
        while (count($dataBytes) < 19) {
            $dataBytes[] = (count($dataBytes) % 2 === 0) ? 0xEC : 0x11;
        }

        return array_slice($dataBytes, 0, 19);
    }

    private function qrEccBytes(array $data, $ecLength)
    {
        $generator = array(1);
        for ($i = 0; $i < $ecLength; $i++) {
            $generator = $this->qrPolyMultiply($generator, array(1, $this->qrGfPow(2, $i)));
        }

        $message = array_merge($data, array_fill(0, $ecLength, 0));
        $limit = count($data);
        for ($i = 0; $i < $limit; $i++) {
            $coef = $message[$i];
            if ($coef === 0) {
                continue;
            }

            for ($j = 0; $j < count($generator); $j++) {
                $message[$i + $j] ^= $this->qrGfMul($generator[$j], $coef);
            }
        }

        return array_slice($message, -$ecLength);
    }

    private function qrPolyMultiply(array $a, array $b)
    {
        $result = array_fill(0, count($a) + count($b) - 1, 0);

        for ($i = 0; $i < count($a); $i++) {
            for ($j = 0; $j < count($b); $j++) {
                $result[$i + $j] ^= $this->qrGfMul($a[$i], $b[$j]);
            }
        }

        return $result;
    }

    private function qrGfMul($x, $y)
    {
        static $exp = null;
        static $log = null;

        if ($exp === null || $log === null) {
            $exp = array_fill(0, 512, 0);
            $log = array_fill(0, 256, 0);
            $value = 1;
            for ($i = 0; $i < 255; $i++) {
                $exp[$i] = $value;
                $log[$value] = $i;
                $value <<= 1;
                if ($value & 0x100) {
                    $value ^= 0x11D;
                }
            }
            for ($i = 255; $i < 512; $i++) {
                $exp[$i] = $exp[$i - 255];
            }
        }

        if ($x === 0 || $y === 0) {
            return 0;
        }

        return $exp[$log[$x] + $log[$y]];
    }

    private function qrGfPow($x, $power)
    {
        $result = 1;
        for ($i = 0; $i < $power; $i++) {
            $result = $this->qrGfMul($result, $x);
        }
        return $result;
    }

    private function qrAppendBits(array &$bits, $value, $length)
    {
        for ($i = $length - 1; $i >= 0; $i--) {
            $bits[] = (($value >> $i) & 1) === 1;
        }
    }

    private function qrBitsToBytes(array $bits)
    {
        $bytes = array();
        $value = 0;
        $count = 0;
        foreach ($bits as $bit) {
            $value = ($value << 1) | ($bit ? 1 : 0);
            $count++;
            if ($count === 8) {
                $bytes[] = $value;
                $value = 0;
                $count = 0;
            }
        }
        if ($count > 0) {
            $bytes[] = $value << (8 - $count);
        }

        return $bytes;
    }

    private function qrDrawFinder(array &$matrix, $x, $y)
    {
        for ($dy = -1; $dy <= 7; $dy++) {
            for ($dx = -1; $dx <= 7; $dx++) {
                $xx = $x + $dx;
                $yy = $y + $dy;
                if ($xx < 0 || $yy < 0 || $xx >= 21 || $yy >= 21) {
                    continue;
                }

                $border = $dx === -1 || $dx === 7 || $dy === -1 || $dy === 7;
                if ($border) {
                    $matrix[$yy][$xx] = false;
                    continue;
                }

                $inner = ($dx === 0 || $dx === 6 || $dy === 0 || $dy === 6);
                $middle = ($dx >= 2 && $dx <= 4 && $dy >= 2 && $dy <= 4);
                $matrix[$yy][$xx] = $inner || $middle;
            }
        }
    }

    private function qrReserveFormatInfo(array &$matrix)
    {
        $reserved = array(
            array(8, 0), array(8, 1), array(8, 2), array(8, 3), array(8, 4), array(8, 5),
            array(8, 7), array(8, 8), array(7, 8), array(5, 8), array(4, 8), array(3, 8), array(2, 8), array(1, 8), array(0, 8),
            array(20, 8), array(19, 8), array(18, 8), array(17, 8), array(16, 8), array(15, 8), array(14, 8), array(13, 8),
            array(8, 20), array(8, 19), array(8, 18), array(8, 17), array(8, 16), array(8, 15), array(8, 14),
        );

        foreach ($reserved as $cell) {
            $matrix[$cell[1]][$cell[0]] = false;
        }
    }

    private function qrPlaceData(array &$matrix, array $bits)
    {
        $size = 21;
        $bitIndex = 0;
        $directionUp = true;

        for ($x = $size - 1; $x > 0; $x -= 2) {
            if ($x === 6) {
                $x--;
            }

            for ($yOffset = 0; $yOffset < $size; $yOffset++) {
                $y = $directionUp ? ($size - 1 - $yOffset) : $yOffset;

                for ($dx = 0; $dx < 2; $dx++) {
                    $xx = $x - $dx;
                    if ($matrix[$y][$xx] !== null) {
                        continue;
                    }

                    $bit = isset($bits[$bitIndex]) ? $bits[$bitIndex] : false;
                    $mask = (($xx + $y) % 2) === 0;
                    $matrix[$y][$xx] = $bit ^ $mask;
                    $bitIndex++;
                }
            }

            $directionUp = !$directionUp;
        }
    }

    private function qrPlaceFormatBits(array &$matrix, $bits)
    {
        $bitsArray = str_split($bits);
        $positionsA = array(
            array(8, 0), array(8, 1), array(8, 2), array(8, 3), array(8, 4), array(8, 5),
            array(8, 7), array(8, 8), array(7, 8), array(5, 8), array(4, 8), array(3, 8), array(2, 8), array(1, 8), array(0, 8)
        );
        $positionsB = array(
            array(20, 8), array(19, 8), array(18, 8), array(17, 8), array(16, 8), array(15, 8), array(14, 8), array(13, 8),
            array(8, 20), array(8, 19), array(8, 18), array(8, 17), array(8, 16), array(8, 15), array(8, 14)
        );

        foreach ($positionsA as $index => $cell) {
            $matrix[$cell[1]][$cell[0]] = $bitsArray[$index] === '1';
        }

        foreach ($positionsB as $index => $cell) {
            $matrix[$cell[1]][$cell[0]] = $bitsArray[$index] === '1';
        }
    }

    private function qrSvgDataUri($text)
    {
        try {
            $barcode = $this->buildQrBarcode($text);
            if ($barcode !== null) {
                $pngData = $barcode->getBarcodePngData(4, 4, array(17, 24, 39));
                if (is_object($pngData) && method_exists($pngData, 'getImageBlob')) {
                    $pngData = $pngData->getImageBlob();
                }

                if (is_string($pngData) && $pngData !== '') {
                    return 'data:image/png;base64,' . base64_encode($pngData);
                }

                $svgData = $barcode->getBarcodeSVGcode(4, 4, '#111827');
                if ($svgData !== '') {
                    return 'data:image/svg+xml;base64,' . base64_encode($svgData);
                }

                return '';
            }

            $text = (string) $text;
            if (strlen($text) > 17) {
                Logger::warning('certificado.qrcode.falhou', array(
                    'message' => 'Encoder QR alternativo indisponível para o conteúdo informado.',
                    'texto_len' => strlen($text),
                ));

                return '';
            }

            $matrix = $this->buildQrMatrix($text);
            $size = count($matrix);
            $quiet = 4;
            $moduleSize = 4;
            $svgSize = ($size + ($quiet * 2)) * $moduleSize;
            $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $svgSize . ' ' . $svgSize . '" shape-rendering="crispEdges">';
            $svg .= '<rect width="100%" height="100%" fill="#ffffff"/>';
            for ($y = 0; $y < $size; $y++) {
                for ($x = 0; $x < $size; $x++) {
                    if (empty($matrix[$y][$x])) {
                        continue;
                    }
                    $rectX = ($x + $quiet) * $moduleSize;
                    $rectY = ($y + $quiet) * $moduleSize;
                    $svg .= '<rect x="' . $rectX . '" y="' . $rectY . '" width="' . $moduleSize . '" height="' . $moduleSize . '" fill="#111827"/>';
                }
            }
            $svg .= '</svg>';

            return 'data:image/svg+xml;base64,' . base64_encode($svg);
        } catch (\Throwable $exception) {
            Logger::warning('certificado.qrcode.falhou', array(
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ));

            return '';
        }
    }

    public function gerarQrSvgDataUri($text)
    {
        return $this->qrSvgDataUri($text);
    }

    private function envelopeCertificadoParaEmail(?array $certificado = null)
    {
        if (!$certificado) {
            return array();
        }

        $certificado['cpf_mascarado'] = $this->mascararCpf(isset($certificado['cpf_participante']) ? $certificado['cpf_participante'] : '');
        $certificado['pdf_url'] = Helpers::url('certificados/pdf?codigo=' . urlencode($certificado['codigo']));
        $certificado['validacao_url'] = $this->urlValidacaoCertificado($certificado['codigo']);
        $certificado['qr_svg'] = $this->qrSvgDataUri($certificado['validacao_url']);
        return $certificado;
    }

    private function urlValidacaoCertificado($codigo)
    {
        $codigo = strtoupper(trim((string) $codigo));
        if ($codigo === '') {
            return Helpers::url('certificados/validar');
        }

        return Helpers::url('certificados/validar?codigo=' . urlencode($codigo));
    }
}




