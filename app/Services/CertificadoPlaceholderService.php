<?php

namespace App\Services;

use App\Core\Helpers;

class CertificadoPlaceholderService
{
    private $configService;

    public function __construct()
    {
        $this->configService = new ConfiguracaoGlobalService();
    }

    public function catalogo()
    {
        return array(
            'Aluno' => array(
                '{aluno_nome}' => 'Nome completo do aluno',
                '{aluno_email}' => 'E-mail do aluno',
                '{aluno_documento}' => 'Documento do aluno (quando aplicável)',
                '{aluno_cpf}' => 'CPF do aluno',
                '{aluno_id}' => 'ID do aluno',
            ),
            'Curso' => array(
                '{curso_nome}' => 'Nome do curso/evento',
                '{curso_slug}' => 'Slug do curso/evento',
                '{curso_categoria}' => 'Categoria do curso/evento',
                '{curso_tipo}' => 'Tipo do curso/evento',
                '{curso_modalidade}' => 'Modalidade',
                '{curso_carga_horaria}' => 'Carga horária',
                '{curso_descricao_curta}' => 'Descrição curta',
                '{curso_professor_responsavel}' => 'Professor responsável',
                '{curso_objetivo_geral}' => 'Objetivo geral',
                '{curso_ementa}' => 'Ementa',
            ),
            'Turma' => array(
                '{turma_nome}' => 'Nome da turma',
                '{turma_codigo}' => 'Código da turma',
                '{turma_data_inicio}' => 'Data de início',
                '{turma_data_fim}' => 'Data de término',
                '{turma_periodo}' => 'Período',
                '{turma_horario}' => 'Horário',
                '{turma_local}' => 'Local',
            ),
            'Inscrição e pedido' => array(
                '{inscricao_id}' => 'ID da inscrição',
                '{inscricao_status}' => 'Status da inscrição',
                '{inscricao_data}' => 'Data da inscrição',
                '{pedido_codigo}' => 'Código do pedido',
                '{pedido_status}' => 'Status do pedido',
            ),
            'Aproveitamento e conclusão' => array(
                '{frequencia_percentual}' => 'Frequência (%)',
                '{nota_final}' => 'Nota final',
                '{percentual_progresso}' => 'Progresso (%)',
                '{data_conclusao}' => 'Data de conclusão',
                '{situacao_conclusao}' => 'Situação de conclusão',
            ),
            'Certificado' => array(
                '{certificado_codigo}' => 'Código do certificado',
                '{certificado_hash}' => 'Hash (quando aplicável)',
                '{certificado_data_emissao}' => 'Data de emissão',
                '{certificado_data_reemissao}' => 'Data de reemissão',
                '{certificado_numero_via}' => 'Número da via',
                '{certificado_url_validacao}' => 'URL pública de validação',
                '{certificado_qrcode}' => 'QR Code do certificado (quando habilitado)',
            ),
            'Instituição' => array(
                '{instituicao_nome}' => 'Nome fantasia da instituição',
                '{instituicao_cnpj}' => 'CNPJ',
                '{instituicao_site}' => 'Site',
                '{instituicao_email}' => 'E-mail institucional',
                '{instituicao_endereco}' => 'Endereço',
                '{instituicao_cidade}' => 'Cidade',
                '{instituicao_estado}' => 'UF',
                '{logo_url}' => 'Logo institucional (URL/caminho)',
            ),
            'Assinaturas' => array(
                '{assinatura_1_nome}' => 'Nome assinatura 1',
                '{assinatura_1_cargo}' => 'Cargo assinatura 1',
                '{assinatura_1_imagem}' => 'Imagem assinatura 1 (URL/caminho)',
                '{assinatura_2_nome}' => 'Nome assinatura 2',
                '{assinatura_2_cargo}' => 'Cargo assinatura 2',
                '{assinatura_2_imagem}' => 'Imagem assinatura 2 (URL/caminho)',
                '{assinatura_3_nome}' => 'Nome assinatura 3',
                '{assinatura_3_cargo}' => 'Cargo assinatura 3',
                '{assinatura_3_imagem}' => 'Imagem assinatura 3 (URL/caminho)',
                '{professor_nome}' => 'Nome do professor responsável',
                '{coordenador_nome}' => 'Nome do coordenador',
            ),
            'Datas gerais' => array(
                '{data_atual}' => 'Data atual (Brasil)',
                '{ano_atual}' => 'Ano atual',
                '{cidade_data_atual}' => 'Ex.: Lages/SC, 18/05/2026',
            ),
            'Texto e sistema' => array(
                '{texto_padrao_certificado}' => 'Texto padrão do certificado (config global)',
                '{texto_rodape_certificado}' => 'Texto padrão do rodapé (config global)',
                '{url_site}' => 'URL base do portal',
                '{nome_plataforma}' => 'Nome do portal (nome fantasia)',
            ),
        );
    }

    public function placeholdersFlat()
    {
        $flat = array();
        foreach ($this->catalogo() as $grupo => $items) {
            foreach ($items as $placeholder => $descricao) {
                $flat[$placeholder] = $descricao;
            }
        }
        return $flat;
    }

    public function contextoPreview()
    {
        $institucional = $this->configService->institucional();
        $nomeInstituicao = !empty($institucional['nome_fantasia']) ? $institucional['nome_fantasia'] : 'Desbloqueia Cursos';
        $cidade = !empty($institucional['cidade']) ? $institucional['cidade'] : 'Lages';
        $uf = !empty($institucional['uf']) ? $institucional['uf'] : 'SC';

        $dataAtualBr = $this->formatarDataBr(date('Y-m-d'));

        return array(
            'aluno' => array(
                'id' => 101,
                'nome' => 'Maria da Silva',
                'email' => 'maria.silva@example.com',
                'documento' => '***.***.***-**',
                'cpf' => '12345678901',
            ),
            'curso' => array(
                'nome' => 'Curso Formativo de Oratória e Semiótica',
                'slug' => 'curso-formativo-oratoria-semiotica',
                'categoria' => 'Comunicação',
                'tipo' => 'curso',
                'modalidade' => 'Presencial',
                'carga_horaria' => '20 h/a',
                'descricao_curta' => 'Formação introdutória com prática e teoria.',
                'professor_responsavel' => 'Prof. João Pereira',
                'objetivo_geral' => 'Aprimorar a comunicação e a expressão.',
                'ementa' => 'Oratória, semiótica aplicada e práticas de apresentação.',
            ),
            'turma' => array(
                'nome' => 'Turma Presencial Sábado',
                'codigo' => 'TURMA-SAB',
                'data_inicio' => $this->formatarDataBr(date('Y-m-d', strtotime('-30 days'))),
                'data_fim' => $this->formatarDataBr(date('Y-m-d')),
                'periodo' => 'Sábados',
                'horario' => '08:30 às 12:30',
                'local' => $cidade . '/' . $uf,
            ),
            'inscricao' => array(
                'id' => 9001,
                'status' => 'concluida',
                'data' => $this->formatarDataBr(date('Y-m-d', strtotime('-40 days'))),
            ),
            'pedido' => array(
                'codigo' => 'PED-2026-000123',
                'status' => 'pago',
            ),
            'aproveitamento' => array(
                'frequencia_percentual' => '100%',
                'nota_final' => '9,5',
                'percentual_progresso' => '100%',
                'data_conclusao' => $dataAtualBr,
                'situacao_conclusao' => 'Concluído',
            ),
            'certificado' => array(
                'codigo' => 'DC-2026-000001',
                'hash' => 'dc26a1b2c3',
                'data_emissao' => $dataAtualBr,
                'data_reemissao' => '',
                'numero_via' => '1',
            ),
            'instituicao' => array(
                'nome' => $nomeInstituicao,
                'cnpj' => !empty($institucional['cnpj']) ? $institucional['cnpj'] : '',
                'site' => rtrim((string) (require BASE_PATH . '/config/app.php')['url'], '/'),
                'email' => !empty($institucional['email_institucional']) ? $institucional['email_institucional'] : '',
                'endereco' => '',
                'cidade' => $cidade,
                'estado' => $uf,
                'logo_url' => !empty($institucional['logo_caminho']) ? $institucional['logo_caminho'] : '',
            ),
            'assinaturas' => array(
                'assinatura_1_nome' => '',
                'assinatura_1_cargo' => '',
                'assinatura_1_imagem' => '',
                'assinatura_2_nome' => '',
                'assinatura_2_cargo' => '',
                'assinatura_2_imagem' => '',
                'assinatura_3_nome' => '',
                'assinatura_3_cargo' => '',
                'assinatura_3_imagem' => '',
                'professor_nome' => 'Prof. João Pereira',
                'coordenador_nome' => '',
            ),
        );
    }

    public function renderizar($conteudo, array $contexto = array(), array $opcoes = array())
    {
        $conteudo = (string) $conteudo;
        $debug = !empty($opcoes['debug_placeholders']);
        $escape = array_key_exists('escape', $opcoes) ? (bool) $opcoes['escape'] : true;

        $map = $this->montarMapa($contexto, $escape);

        $rendered = strtr($conteudo, $map);

        if (!$debug) {
            return $rendered;
        }

        return $rendered;
    }

    private function montarMapa(array $contexto, $escape = true)
    {
        $institucional = $this->configService->institucional();
        $certConfig = $this->configService->certificados();

        $nomePortal = !empty($institucional['nome_fantasia']) ? (string) $institucional['nome_fantasia'] : 'Desbloqueia Cursos';
        $siteUrl = rtrim((string) (require BASE_PATH . '/config/app.php')['url'], '/');
        if ($siteUrl === '') {
            $siteUrl = '/';
        }

        $aluno = isset($contexto['aluno']) && is_array($contexto['aluno']) ? $contexto['aluno'] : array();
        $curso = isset($contexto['curso']) && is_array($contexto['curso']) ? $contexto['curso'] : array();
        $turma = isset($contexto['turma']) && is_array($contexto['turma']) ? $contexto['turma'] : array();
        $inscricao = isset($contexto['inscricao']) && is_array($contexto['inscricao']) ? $contexto['inscricao'] : array();
        $pedido = isset($contexto['pedido']) && is_array($contexto['pedido']) ? $contexto['pedido'] : array();
        $aproveitamento = isset($contexto['aproveitamento']) && is_array($contexto['aproveitamento']) ? $contexto['aproveitamento'] : array();
        $certificado = isset($contexto['certificado']) && is_array($contexto['certificado']) ? $contexto['certificado'] : array();
        $instituicao = isset($contexto['instituicao']) && is_array($contexto['instituicao']) ? $contexto['instituicao'] : array();
        $assinaturas = isset($contexto['assinaturas']) && is_array($contexto['assinaturas']) ? $contexto['assinaturas'] : array();

        $codigo = isset($certificado['codigo']) ? (string) $certificado['codigo'] : '';
        $validacaoUrl = $codigo !== '' ? Helpers::url('certificados/validar?codigo=' . urlencode($codigo)) : Helpers::url('certificados/validar');

        $cidade = !empty($institucional['cidade']) ? (string) $institucional['cidade'] : '';
        $uf = !empty($institucional['uf']) ? (string) $institucional['uf'] : '';
        $cidadeUf = trim($cidade . ($uf ? '/' . $uf : ''));
        $cidadeDataAtual = $cidadeUf !== '' ? ($cidadeUf . ', ' . $this->formatarDataBr(date('Y-m-d'))) : $this->formatarDataBr(date('Y-m-d'));

        $value = function ($v) use ($escape) {
            $v = $v === null ? '' : (string) $v;
            return $escape ? Helpers::e($v) : $v;
        };

        return array(
            '{aluno_nome}' => $value($aluno['nome'] ?? ''),
            '{aluno_email}' => $value($aluno['email'] ?? ''),
            '{aluno_documento}' => $value($aluno['documento'] ?? ''),
            '{aluno_cpf}' => $value($aluno['cpf'] ?? ''),
            '{aluno_id}' => $value($aluno['id'] ?? ''),

            '{curso_nome}' => $value($curso['nome'] ?? ''),
            '{curso_slug}' => $value($curso['slug'] ?? ''),
            '{curso_categoria}' => $value($curso['categoria'] ?? ''),
            '{curso_tipo}' => $value($curso['tipo'] ?? ''),
            '{curso_modalidade}' => $value($curso['modalidade'] ?? ''),
            '{curso_carga_horaria}' => $value($curso['carga_horaria'] ?? ''),
            '{curso_descricao_curta}' => $value($curso['descricao_curta'] ?? ''),
            '{curso_professor_responsavel}' => $value($curso['professor_responsavel'] ?? ''),
            '{curso_objetivo_geral}' => $value($curso['objetivo_geral'] ?? ''),
            '{curso_ementa}' => $value($curso['ementa'] ?? ''),

            '{turma_nome}' => $value($turma['nome'] ?? ''),
            '{turma_codigo}' => $value($turma['codigo'] ?? ''),
            '{turma_data_inicio}' => $value($turma['data_inicio'] ?? ''),
            '{turma_data_fim}' => $value($turma['data_fim'] ?? ''),
            '{turma_periodo}' => $value($turma['periodo'] ?? ''),
            '{turma_horario}' => $value($turma['horario'] ?? ''),
            '{turma_local}' => $value($turma['local'] ?? ''),

            '{inscricao_id}' => $value($inscricao['id'] ?? ''),
            '{inscricao_status}' => $value($inscricao['status'] ?? ''),
            '{inscricao_data}' => $value($inscricao['data'] ?? ''),
            '{pedido_codigo}' => $value($pedido['codigo'] ?? ''),
            '{pedido_status}' => $value($pedido['status'] ?? ''),

            '{frequencia_percentual}' => $value($aproveitamento['frequencia_percentual'] ?? ''),
            '{nota_final}' => $value($aproveitamento['nota_final'] ?? ''),
            '{percentual_progresso}' => $value($aproveitamento['percentual_progresso'] ?? ''),
            '{data_conclusao}' => $value($aproveitamento['data_conclusao'] ?? ''),
            '{situacao_conclusao}' => $value($aproveitamento['situacao_conclusao'] ?? ''),

            '{certificado_codigo}' => $value($codigo),
            '{certificado_hash}' => $value($certificado['hash'] ?? ''),
            '{certificado_data_emissao}' => $value($certificado['data_emissao'] ?? ''),
            '{certificado_data_reemissao}' => $value($certificado['data_reemissao'] ?? ''),
            '{certificado_numero_via}' => $value($certificado['numero_via'] ?? ''),
            '{certificado_url_validacao}' => $value($validacaoUrl),
            '{certificado_qrcode}' => '',

            '{instituicao_nome}' => $value($instituicao['nome'] ?? $institucional['nome_fantasia'] ?? ''),
            '{instituicao_cnpj}' => $value($instituicao['cnpj'] ?? $institucional['cnpj'] ?? ''),
            '{instituicao_site}' => $value($instituicao['site'] ?? $siteUrl),
            '{instituicao_email}' => $value($instituicao['email'] ?? $institucional['email_institucional'] ?? ''),
            '{instituicao_endereco}' => $value($instituicao['endereco'] ?? ''),
            '{instituicao_cidade}' => $value($instituicao['cidade'] ?? $institucional['cidade'] ?? ''),
            '{instituicao_estado}' => $value($instituicao['estado'] ?? $institucional['uf'] ?? ''),
            '{logo_url}' => $value($instituicao['logo_url'] ?? $institucional['logo_caminho'] ?? ''),

            '{assinatura_1_nome}' => $value($assinaturas['assinatura_1_nome'] ?? ($certConfig['certificados_assinatura_1_nome'] ?? '')),
            '{assinatura_1_cargo}' => $value($assinaturas['assinatura_1_cargo'] ?? ($certConfig['certificados_assinatura_1_cargo'] ?? '')),
            '{assinatura_1_imagem}' => $value($assinaturas['assinatura_1_imagem'] ?? ($certConfig['certificados_assinatura_1_imagem'] ?? '')),
            '{assinatura_2_nome}' => $value($assinaturas['assinatura_2_nome'] ?? ($certConfig['certificados_assinatura_2_nome'] ?? '')),
            '{assinatura_2_cargo}' => $value($assinaturas['assinatura_2_cargo'] ?? ($certConfig['certificados_assinatura_2_cargo'] ?? '')),
            '{assinatura_2_imagem}' => $value($assinaturas['assinatura_2_imagem'] ?? ($certConfig['certificados_assinatura_2_imagem'] ?? '')),
            '{assinatura_3_nome}' => $value($assinaturas['assinatura_3_nome'] ?? ($certConfig['certificados_assinatura_3_nome'] ?? '')),
            '{assinatura_3_cargo}' => $value($assinaturas['assinatura_3_cargo'] ?? ($certConfig['certificados_assinatura_3_cargo'] ?? '')),
            '{assinatura_3_imagem}' => $value($assinaturas['assinatura_3_imagem'] ?? ($certConfig['certificados_assinatura_3_imagem'] ?? '')),
            '{professor_nome}' => $value($assinaturas['professor_nome'] ?? ''),
            '{coordenador_nome}' => $value($assinaturas['coordenador_nome'] ?? ''),

            '{data_atual}' => $value($this->formatarDataBr(date('Y-m-d'))),
            '{ano_atual}' => $value(date('Y')),
            '{cidade_data_atual}' => $value($cidadeDataAtual),

            '{texto_padrao_certificado}' => $value($certConfig['certificados_texto_padrao'] ?? ''),
            '{texto_rodape_certificado}' => $value($certConfig['certificados_texto_rodape'] ?? ''),
            '{url_site}' => $value($siteUrl),
            '{nome_plataforma}' => $value($nomePortal),
        );
    }

    private function formatarDataBr($ymd)
    {
        $ymd = (string) $ymd;
        if (!preg_match('/^\\d{4}-\\d{2}-\\d{2}/', $ymd)) {
            return $ymd;
        }
        $parts = explode('-', substr($ymd, 0, 10));
        return $parts[2] . '/' . $parts[1] . '/' . $parts[0];
    }
}

