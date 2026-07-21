<?php

namespace App\Controllers\V2;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\CertificadoService;

/**
 * Validação Pública de Certificados V2 (Fase 2.11) — casca visual V2 sobre o
 * MESMO fluxo público já existente.
 *
 * A fonte única de verdade continua sendo o domínio atual:
 * - `CertificadoService::configuracaoCertificados()` — habilitação/feature flags;
 * - `CertificadoService::validarPublicamente($codigo, $cpf, $ip, $ua)` — toda a
 *   regra real de código/CPF/status/revogação/cancelamento e os campos públicos
 *   permitidos (inclusive `pdf_url` e `cpf_mascarado`).
 *
 * Este controller é FINO: lê código/CPF, delega ao service público e renderiza a
 * view V2 com apenas os campos que a validação pública já revela hoje. Não há SQL
 * próprio, nem decisão de validade, PDF, QR Code ou versão online aqui. Retornos
 * são sempre internos; nenhum `redirect`/`next`/`return`/URL do usuário é aceito.
 */
class CertificadoValidacaoController extends Controller
{
    /** @var CertificadoService */
    private $certificadoService;

    public function __construct()
    {
        $this->certificadoService = new CertificadoService();
    }

    public function index(Request $request)
    {
        $base = $this->dadosLayout();

        $config = $this->certificadoService->configuracaoCertificados();
        if (empty($config['certificados_habilitado']) || empty($config['certificados_validacao_publica_habilitada'])) {
            return $this->render($base, null, '', '', array(
                'tipo' => 'erro',
                'mensagem' => 'A validação pública está temporariamente indisponível.',
            ), 200);
        }

        $ehPost = $request->method() === 'POST';
        $codigo = (string) ($ehPost ? $request->input('codigo', '') : $request->query('codigo', ''));
        $cpf = (string) ($ehPost ? $request->input('cpf', '') : $request->query('cpf', ''));

        // Estado inicial: sem código informado → apenas o formulário.
        if (trim($codigo) === '') {
            // Num POST/GET com código vazio, evita uma consulta inútil (e seu log)
            // e orienta o usuário — sem recriar qualquer regra de validação.
            $erro = ($ehPost || $request->query('codigo') !== null)
                ? array('tipo' => 'aviso', 'mensagem' => 'Informe o código do certificado para validar.')
                : null;
            return $this->render($base, null, '', $cpf, $erro, 200);
        }

        // Validação REAL e única: o service decide tudo (código, CPF, status...).
        $resultado = $this->certificadoService->validarPublicamente(
            $codigo,
            $cpf,
            $request->ip(),
            $request->userAgent()
        );

        if (!empty($resultado['ok']) && !empty($resultado['certificado']) && is_array($resultado['certificado'])) {
            // Exibe SOMENTE os campos que a validação pública já revela hoje.
            $cert = $resultado['certificado'];
            $certView = array(
                'nome_participante' => (string) ($cert['nome_participante'] ?? ''),
                'cpf_mascarado' => (string) ($cert['cpf_mascarado'] ?? ''),
                'curso_nome' => (string) ($cert['curso_nome'] ?? ''),
                'codigo' => (string) ($cert['codigo'] ?? ''),
                // URL pública oficial já existente; o download segue gated pela
                // própria rota /certificados/pdf (config de download). Guarda
                // defensiva: só repassa o href quando ele for EXATAMENTE a rota
                // oficial interna de PDF (rejeita external/javascript:/data:/
                // caminho físico). Não inventa URL nem altera a regra do service.
                'pdf_url' => $this->pdfUrlOficialSeguro((string) ($cert['pdf_url'] ?? '')),
            );
            return $this->render($base, $certView, $codigo, $cpf, null, 200);
        }

        // Falha: reaproveita a mensagem/classificação retornada pela regra atual.
        return $this->render($base, null, $codigo, $cpf, $this->mensagemAmigavel($resultado), 200);
    }

    /**
     * Só aceita renderizar o botão de PDF quando a URL vinda do service for
     * EXATAMENTE a rota pública oficial de PDF do certificado
     * (`/certificados/pdf`), em http(s) ou relativa. Qualquer outro esquema
     * (`javascript:`, `data:`, `file:`...), host com caminho diferente ou string
     * suspeita resulta em '' (botão omitido). Não reescreve a URL do service.
     */
    private function pdfUrlOficialSeguro($url)
    {
        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }

        // Esquemas perigosos fora já barram aqui (javascript:, data:, file:...).
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if ($scheme !== null && $scheme !== '' && !in_array(strtolower((string) $scheme), array('http', 'https'), true)) {
            return '';
        }

        // A base precisa ser EXATAMENTE a rota oficial de PDF, construída pelo
        // mesmo helper do service (fixa esquema + host + caminho). Qualquer host
        // externo, caminho diferente ou string suspeita não passa.
        $baseOficial = \App\Core\Helpers::url('certificados/pdf');
        $qpos = strpos($url, '?');
        $parteBase = $qpos === false ? $url : substr($url, 0, $qpos);
        if ($parteBase !== $baseOficial) {
            return '';
        }

        return $url;
    }

    /**
     * Espelha o mapeamento de mensagens amigáveis já usado pela tela pública atual
     * (sem revelar detalhes internos de existência/CPF/motivo/histórico).
     */
    private function mensagemAmigavel($resultado)
    {
        $amigaveis = array(
            'Certificado nao encontrado.' => 'Não localizamos um certificado com os dados informados. Confira o código e tente novamente.',
            'CPF nao confere com o certificado.' => 'O CPF informado não confere com o certificado.',
            'Certificado nao esta ativo para validacao.' => 'Este certificado ainda não está ativo para validação pública.',
            'A validacao publica esta temporariamente indisponivel.' => 'A validação pública está temporariamente indisponível.',
            'A validação pública está temporariamente indisponível.' => 'A validação pública está temporariamente indisponível.',
        );

        $original = isset($resultado['message']) ? trim((string) $resultado['message']) : 'Certificado inválido.';
        $mensagem = isset($amigaveis[$original]) ? $amigaveis[$original] : $original;
        $tipo = strpos($original, 'CPF') !== false ? 'aviso' : 'erro';

        return array('tipo' => $tipo, 'mensagem' => $mensagem);
    }

    private function render(array $base, $certView, $codigo, $cpf, $erro, $status)
    {
        $data = array_merge($base, array(
            'title' => 'Validar certificado — Desbloqueia Cursos',
            'pageTitle' => 'Validar certificado — Desbloqueia Cursos',
            'pageDescription' => 'Confirme a autenticidade de um certificado pelo código.',
            'certificado' => is_array($certView) ? $certView : null,
            'codigo' => (string) $codigo,
            'cpf' => (string) $cpf,
            'erro' => is_array($erro) ? $erro : null,
        ));

        return new Response(View::render('v2/certificados-validar', $data, false), (int) $status);
    }

    private function dadosLayout()
    {
        $usuarioId = (int) Session::get('usuario_id', 0);
        $usuarioNome = trim((string) Session::get('usuario_nome', ''));
        $sessionPerfis = Session::get('usuario_perfis', array());
        $hasAdminAccess = (bool) Session::get('usuario_admin') || (bool) Session::get('is_admin') || in_array('admin', $sessionPerfis, true);
        $hasProfessorAccess = (bool) Session::get('usuario_professor') || (bool) Session::get('is_professor') || in_array('professor', $sessionPerfis, true);

        $areaHref = '/v2/aluno';
        if ($hasAdminAccess) {
            $areaHref = '/admin';
        } elseif ($hasProfessorAccess) {
            $areaHref = '/professor/dashboard';
        }

        return array(
            'loggedIn' => $usuarioId > 0,
            'usuarioNome' => $usuarioNome,
            'usuarioPrimeiroNome' => $usuarioId > 0 ? $this->primeiroNome($usuarioNome) : '',
            'areaHref' => $areaHref,
            'loginHref' => '/v2/login',
            'registerHref' => '/v2/cadastro',
            'catalogoHref' => '/v2/catalogo/',
            'categoriasHref' => '/categorias',
            'certificadosHref' => '/v2/certificados/validar/',
            'sobreHref' => '/sobre',
            'contatoHref' => '/contato',
            'homeHref' => '/v2/',
        );
    }

    private function primeiroNome($nome)
    {
        $nome = trim((string) $nome);
        if ($nome === '') {
            return 'aluno';
        }
        $partes = preg_split('/\s+/', $nome);
        return ($partes && !empty($partes[0])) ? (string) $partes[0] : $nome;
    }
}
