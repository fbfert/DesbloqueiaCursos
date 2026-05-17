<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Models\Categoria;
use App\Models\CursoEvento;
use App\Models\CursoPessoaVinculada;
use App\Models\Turma;
use App\Models\Usuario;
use App\Models\UsuarioCurso;
use Exception;

class CursoService
{
    private $categoriaModel;
    private $cursoModel;
    private $turmaModel;
    private $cursoPessoaModel;
    private $usuarioModel;
    private $usuarioCursoModel;
    private $auditService;
    private $trashService;
    private $thumbnailDirectoryPublic;
    private $thumbnailDirectoryAbsolute;

    private $modalidades = array(
        'presencial',
        'online_ao_vivo',
        'hibrido',
        'sob_demanda',
    );

    private $conteudoProgramaticoTipos = array(
        'texto',
        'html',
        'modulos',
    );

    public function __construct()
    {
        $this->categoriaModel = new Categoria();
        $this->cursoModel = new CursoEvento();
        $this->turmaModel = new Turma();
        $this->cursoPessoaModel = new CursoPessoaVinculada();
        $this->usuarioModel = new Usuario();
        $this->usuarioCursoModel = new UsuarioCurso();
        $this->auditService = new AuditService();
        $this->trashService = new TrashService();
        $this->thumbnailDirectoryPublic = '/assets/uploads/thumbnails';
        $this->thumbnailDirectoryAbsolute = BASE_PATH . '/public_html' . $this->thumbnailDirectoryPublic;
    }

    public function allowedModalidades()
    {
        return $this->modalidades;
    }

    public function listAdmin()
    {
        $cursos = $this->cursoModel->allWithCategoryAndCounts();
        $categorias = $this->categoriaModel->allWithCounts();
        $cursosAtivos = array();
        $cursosRascunho = array();
        $cursosInativos = array();

        foreach ($cursos as &$curso) {
            $curso = $this->anexarProfessoresResponsaveisAoCurso($curso);
            $curso['pessoas_vinculadas'] = $this->cursoPessoaModel->forCourse($curso['id']);

            $status = isset($curso['status']) ? (string) $curso['status'] : '';
            if ($status === 'ativo') {
                $cursosAtivos[] = $curso;
                continue;
            }

            if ($status === 'rascunho') {
                $cursosRascunho[] = $curso;
                continue;
            }

            $cursosInativos[] = $curso;
        }
        unset($curso);

        return array(
            'categorias' => $categorias,
            'cursos' => $cursosAtivos,
            'cursos_rascunho' => $cursosRascunho,
            'cursos_inativos' => $cursosInativos,
        );
    }

    public function listProfessor($usuarioId)
    {
        return array(
            'cursos' => $this->cursoModel->findAccessibleByUser($usuarioId),
        );
    }

    public function formData($cursoId = null)
    {
        $curso = $cursoId ? $this->cursoModel->findAdminById($cursoId) : null;
        if ($curso) {
            $curso = $this->anexarProfessoresResponsaveisAoCurso($curso);
        }

        return array(
            'curso' => $curso,
            'categorias' => $this->categoriaModel->allForSelect(),
            'turmas' => $cursoId ? $this->turmaModel->forCourse($cursoId) : array(),
            'pessoas_vinculadas' => $cursoId ? $this->cursoPessoaModel->forCourse($cursoId) : array(),
            'professores' => $this->usuarioModel->professores(),
            'professores_responsaveis' => (!empty($curso) && !empty($curso['professores_responsaveis'])) ? $curso['professores_responsaveis'] : array(),
            'professores_responsaveis_ids' => (!empty($curso) && !empty($curso['professores_responsaveis_ids'])) ? $curso['professores_responsaveis_ids'] : array(),
            'professor_responsavel' => (!empty($curso) && !empty($curso['professor_responsavel'])) ? $curso['professor_responsavel'] : null,
            'modalidades' => $this->modalidades,
            'thumbnails_disponiveis' => $this->listarThumbnailsDisponiveis(),
        );
    }

    public function salvar(array $data, array $files = array(), $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $id = !empty($data['id']) ? (int) $data['id'] : 0;
        $nome = trim((string) (isset($data['nome']) ? $data['nome'] : ''));
        $slug = $this->slugify(isset($data['slug']) && trim((string) $data['slug']) !== '' ? $data['slug'] : $nome);
        $categoriaId = isset($data['categoria_id']) && $data['categoria_id'] !== '' ? (int) $data['categoria_id'] : null;
        $tipo = isset($data['tipo']) && in_array($data['tipo'], array('curso', 'evento'), true) ? $data['tipo'] : 'curso';
        $modalidade = isset($data['modalidade']) && in_array($data['modalidade'], $this->modalidades, true) ? $data['modalidade'] : 'presencial';
        $thumbnail = isset($data['thumbnail']) ? trim((string) $data['thumbnail']) : null;
        $thumbnailSelecionada = isset($data['thumbnail_existente']) ? trim((string) $data['thumbnail_existente']) : '';
        $descricaoCurta = isset($data['descricao_curta']) ? trim((string) $data['descricao_curta']) : null;
        $descricaoCompleta = isset($data['descricao_completa']) ? trim((string) $data['descricao_completa']) : null;
        $cargaHoraria = isset($data['carga_horaria']) && $data['carga_horaria'] !== '' ? (int) $data['carga_horaria'] : null;
        $valorBruto = isset($data['valor']) ? $data['valor'] : null;
        $valorFoiInformado = trim((string) $valorBruto) !== '';
        $valorNormalizado = $this->normalizarDecimalOpcional($valorBruto);
        $valor = $valorNormalizado !== null ? $valorNormalizado : 0;

        $valorPromocionalBruto = isset($data['valor_promocional']) ? $data['valor_promocional'] : null;
        $valorPromocionalFoiInformado = trim((string) $valorPromocionalBruto) !== '';
        $valorPromocional = $this->normalizarDecimalOpcional($valorPromocionalBruto);
        $ordem = isset($data['ordem']) ? (int) $data['ordem'] : 0;
        $status = isset($data['status']) && in_array($data['status'], array('rascunho', 'ativo', 'inativo', 'arquivado'), true) ? $data['status'] : 'rascunho';

        $professoresResponsaveisIds = array();
        if (array_key_exists('professores_responsaveis_usuario_ids', $data)) {
            $professoresResponsaveisIds = $this->normalizarListaInteirosUnicos($data['professores_responsaveis_usuario_ids']);
        } elseif (isset($data['professor_responsavel_usuario_id']) && $data['professor_responsavel_usuario_id'] !== '') {
            $professoresResponsaveisIds = $this->normalizarListaInteirosUnicos(array($data['professor_responsavel_usuario_id']));
        }

        $objetivoGeral = $this->normalizarTextoLongoOpcional(isset($data['objetivo_geral']) ? $data['objetivo_geral'] : null);
        $objetivosEspecificos = $this->normalizarLinhasTexto(isset($data['objetivos_especificos']) ? $data['objetivos_especificos'] : null);
        $publicoAlvo = $this->normalizarTextoLongoOpcional(isset($data['publico_alvo']) ? $data['publico_alvo'] : null);
        $preRequisitosTexto = $this->normalizarTextoLongoOpcional(isset($data['pre_requisitos_texto']) ? $data['pre_requisitos_texto'] : null);
        $preRequisitosItens = $this->normalizarLinhasTexto(isset($data['pre_requisitos_itens']) ? $data['pre_requisitos_itens'] : null);
        $ementa = $this->normalizarTextoLongoOpcional(isset($data['ementa']) ? $data['ementa'] : null);
        $metodologia = $this->normalizarTextoLongoOpcional(isset($data['metodologia']) ? $data['metodologia'] : null);
        $produtoFinal = $this->normalizarLinhasTexto(isset($data['produto_final']) ? $data['produto_final'] : null);
        $avaliacao = $this->normalizarTextoLongoOpcional(isset($data['avaliacao']) ? $data['avaliacao'] : null);

        $conteudoProgramaticoTipo = isset($data['conteudo_programatico_tipo'])
            ? trim((string) $data['conteudo_programatico_tipo'])
            : 'texto';
        if ($conteudoProgramaticoTipo === '') {
            $conteudoProgramaticoTipo = 'texto';
        }

        $conteudoProgramaticoTexto = null;
        $conteudoProgramaticoModulos = null;
        if ($conteudoProgramaticoTipo === 'modulos') {
            $conteudoProgramaticoModulos = $this->prepararConteudoProgramaticoModulos($data);
        } else {
            $conteudoProgramaticoTexto = $this->normalizarTextoLongoOpcional(isset($data['conteudo_programatico_texto']) ? $data['conteudo_programatico_texto'] : null);
        }

        $errors = array();
        if ($nome === '') {
            $errors[] = 'Nome do curso/evento e obrigatorio.';
        }
        if ($slug === '') {
            $errors[] = 'Slug do curso/evento e obrigatorio.';
        }
        if ($valorFoiInformado && $valorNormalizado === null) {
            $errors[] = 'Valor invalido.';
        }
        if ($valor < 0) {
            $errors[] = 'Valor invalido.';
        }
        if ($valorPromocionalFoiInformado && $valorPromocional === null) {
            $errors[] = 'Valor promocional invalido.';
        }
        if ($valorPromocional !== null && $valorPromocional < 0) {
            $errors[] = 'Valor promocional invalido.';
        }
        if ($valorPromocional !== null) {
            if ($valor <= 0) {
                $errors[] = 'Para usar valor promocional, o valor normal deve ser maior que zero.';
            } elseif ($valorPromocional >= $valor) {
                $errors[] = 'O valor promocional deve ser menor que o valor normal.';
            }
        }
        if ($categoriaId !== null && !$this->categoriaModel->findById($categoriaId)) {
            $errors[] = 'Categoria nao encontrada.';
        }
        if (!in_array($conteudoProgramaticoTipo, $this->conteudoProgramaticoTipos, true)) {
            $errors[] = 'Tipo de conteúdo programático inválido.';
            $conteudoProgramaticoTipo = 'texto';
        }

        if ($thumbnailSelecionada !== '') {
            $thumbnailNormalizada = $this->normalizarThumbnailSelecionada($thumbnailSelecionada);
            if ($thumbnailNormalizada === null) {
                $errors[] = 'Thumbnail selecionada nao encontrada na pasta de thumbnails.';
            } else {
                $thumbnail = $thumbnailNormalizada;
            }
        }

        if (isset($files['thumbnail_upload']) && !empty($files['thumbnail_upload']['tmp_name'])) {
            $resultadoUpload = $this->salvarThumbnailUpload($files['thumbnail_upload']);
            if (empty($resultadoUpload['ok'])) {
                $errors[] = isset($resultadoUpload['message']) ? $resultadoUpload['message'] : 'Nao foi possivel enviar a thumbnail.';
            } else {
                $thumbnail = $resultadoUpload['path'];
            }
        }

        $professoresResponsaveis = array();
        if (!empty($professoresResponsaveisIds)) {
            $professoresResponsaveis = $this->validarProfessoresResponsaveis($professoresResponsaveisIds);
            if (empty($professoresResponsaveis) || count($professoresResponsaveis) !== count($professoresResponsaveisIds)) {
                $errors[] = 'Um ou mais professores responsáveis não foram encontrados.';
            }
        }

        $existente = $this->cursoModel->findBySlug($slug);
        if ($existente && (int) $existente['id'] !== $id) {
            $errors[] = 'Ja existe um curso/evento com este slug.';
        }

        if ($id > 0 && !$this->cursoModel->findById($id)) {
            $errors[] = 'Curso/evento nao encontrado.';
        }

        if ($errors) {
            return array('ok' => false, 'errors' => $errors);
        }

        $payload = array(
            'categoria_id' => $categoriaId,
            'nome' => $nome,
            'slug' => $slug,
            'tipo' => $tipo,
            'modalidade' => $modalidade,
            'thumbnail' => $thumbnail !== '' ? $thumbnail : null,
            'descricao_curta' => $descricaoCurta,
            'descricao_completa' => $descricaoCompleta,
            'carga_horaria' => $cargaHoraria,
            'valor' => $valor,
            'valor_promocional' => $valorPromocional,
            'professores_responsaveis_ids' => $professoresResponsaveisIds,
            'professores_responsaveis' => $professoresResponsaveis,
            'objetivo_geral' => $objetivoGeral,
            'objetivos_especificos' => $objetivosEspecificos,
            'publico_alvo' => $publicoAlvo,
            'pre_requisitos_texto' => $preRequisitosTexto,
            'pre_requisitos_itens' => $preRequisitosItens,
            'ementa' => $ementa,
            'conteudo_programatico_tipo' => $conteudoProgramaticoTipo,
            'conteudo_programatico_texto' => $conteudoProgramaticoTexto,
            'conteudo_programatico_modulos' => $conteudoProgramaticoModulos,
            'metodologia' => $metodologia,
            'produto_final' => $produtoFinal,
            'avaliacao' => $avaliacao,
            'em_promocao' => !empty($data['em_promocao']) ? 1 : 0,
            'destaque' => !empty($data['destaque']) ? 1 : 0,
            'ordem' => $ordem,
            'status' => $status,
        );

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            if ($id > 0) {
                $anterior = $this->cursoModel->findById($id);
                $this->cursoModel->update($payload, $id);
                $acao = 'catalogo.curso.atualizado';
            } else {
                $anterior = null;
                $id = $this->cursoModel->create($payload);
                $acao = 'catalogo.curso.criado';
            }

            $this->cursoPessoaModel->syncProfessoresResponsaveis($id, $professoresResponsaveis, 'ativo');
            $this->usuarioCursoModel->syncProfessoresForCourse($id, $professoresResponsaveisIds, 'ativo');

            $this->auditService->record(
                $acao,
                'curso_evento',
                $id,
                array('anterior' => $anterior, 'novo' => $payload),
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            Logger::info($acao, array('curso_evento_id' => $id, 'slug' => $slug));
            $pdo->commit();

            return array('ok' => true, 'id' => $id);
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('catalogo.curso.falhou', array('message' => $exception->getMessage()));
            throw $exception;
        }
    }

    private function listarThumbnailsDisponiveis()
    {
        if (!is_dir($this->thumbnailDirectoryAbsolute)) {
            return array();
        }

        $arquivos = @scandir($this->thumbnailDirectoryAbsolute);
        if ($arquivos === false) {
            return array();
        }

        $permitidas = array('jpg', 'jpeg', 'png', 'webp', 'gif');
        $lista = array();

        foreach ($arquivos as $arquivo) {
            if ($arquivo === '.' || $arquivo === '..') {
                continue;
            }

            $caminhoAbsoluto = $this->thumbnailDirectoryAbsolute . '/' . $arquivo;
            if (!is_file($caminhoAbsoluto)) {
                continue;
            }

            $extensao = strtolower((string) pathinfo($arquivo, PATHINFO_EXTENSION));
            if (!in_array($extensao, $permitidas, true)) {
                continue;
            }

            $lista[] = $this->thumbnailDirectoryPublic . '/' . $arquivo;
        }

        sort($lista, SORT_NATURAL | SORT_FLAG_CASE);
        return $lista;
    }

    private function normalizarThumbnailSelecionada($valor)
    {
        $valor = trim((string) $valor);
        if ($valor === '') {
            return null;
        }

        $valor = str_replace('\\', '/', $valor);
        $prefixo = $this->thumbnailDirectoryPublic . '/';

        if (strpos($valor, $prefixo) !== 0) {
            return null;
        }

        $nomeArquivo = basename($valor);
        if ($nomeArquivo === '' || $nomeArquivo === '.' || $nomeArquivo === '..') {
            return null;
        }

        $caminhoAbsoluto = $this->thumbnailDirectoryAbsolute . '/' . $nomeArquivo;
        if (!is_file($caminhoAbsoluto)) {
            return null;
        }

        return $this->thumbnailDirectoryPublic . '/' . $nomeArquivo;
    }

    private function salvarThumbnailUpload(array $arquivo)
    {
        if (!isset($arquivo['error']) || (int) $arquivo['error'] !== UPLOAD_ERR_OK) {
            return array('ok' => false, 'message' => 'Upload de thumbnail invalido.');
        }

        if (empty($arquivo['tmp_name']) || !is_uploaded_file($arquivo['tmp_name'])) {
            return array('ok' => false, 'message' => 'Arquivo de thumbnail invalido.');
        }

        $tamanho = isset($arquivo['size']) ? (int) $arquivo['size'] : 0;
        if ($tamanho <= 0) {
            return array('ok' => false, 'message' => 'O arquivo de thumbnail esta vazio.');
        }

        if ($tamanho > 5 * 1024 * 1024) {
            return array('ok' => false, 'message' => 'A thumbnail deve ter no maximo 5 MB.');
        }

        $nomeOriginal = isset($arquivo['name']) ? (string) $arquivo['name'] : '';
        $extensao = strtolower((string) pathinfo($nomeOriginal, PATHINFO_EXTENSION));
        $extensoesPermitidas = array('jpg', 'jpeg', 'png', 'webp', 'gif');

        if (!in_array($extensao, $extensoesPermitidas, true)) {
            return array('ok' => false, 'message' => 'Formato de thumbnail nao permitido. Use JPG, PNG, WEBP ou GIF.');
        }

        $mime = $this->detectarMimeType($arquivo['tmp_name']);
        $mimesPermitidos = array('image/jpeg', 'image/png', 'image/webp', 'image/gif');
        if (!in_array(strtolower((string) $mime), $mimesPermitidos, true)) {
            return array('ok' => false, 'message' => 'Tipo de arquivo de thumbnail nao permitido.');
        }

        if (!is_dir($this->thumbnailDirectoryAbsolute)) {
            if (!@mkdir($this->thumbnailDirectoryAbsolute, 0775, true) && !is_dir($this->thumbnailDirectoryAbsolute)) {
                return array('ok' => false, 'message' => 'Nao foi possivel criar a pasta de thumbnails.');
            }
        }

        try {
            $nomeSeguro = 'thumb-' . date('YmdHis') . '-' . bin2hex(random_bytes(6)) . '.' . $extensao;
        } catch (Exception $exception) {
            $nomeSeguro = 'thumb-' . date('YmdHis') . '-' . mt_rand(100000, 999999) . '.' . $extensao;
        }

        $destino = $this->thumbnailDirectoryAbsolute . '/' . $nomeSeguro;
        if (!move_uploaded_file($arquivo['tmp_name'], $destino)) {
            return array('ok' => false, 'message' => 'Nao foi possivel salvar a thumbnail enviada.');
        }

        return array(
            'ok' => true,
            'path' => $this->thumbnailDirectoryPublic . '/' . $nomeSeguro,
        );
    }

    private function detectarMimeType($arquivoTmp)
    {
        if (!is_file($arquivoTmp)) {
            return null;
        }

        if (function_exists('finfo_open')) {
            $finfo = @finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = @finfo_file($finfo, $arquivoTmp);
                @finfo_close($finfo);
                if ($mime) {
                    return $mime;
                }
            }
        }

        return null;
    }

    public function excluir($id, $justificativa, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $curso = $this->cursoModel->findById($id);
        if (!$curso) {
            return array('ok' => false, 'message' => 'Curso/evento nao encontrado.');
        }

        if (!empty($this->turmaModel->forCourse($id))) {
            return array('ok' => false, 'message' => 'Não e seguro excluir curso/evento com turmas vinculadas.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $this->trashService->record('curso_evento', $id, $justificativa, $curso, $actorUserId, $ipAddress, $userAgent);
            $this->cursoModel->softDelete($id);

            $this->auditService->record(
                'catalogo.curso.excluido',
                'curso_evento',
                $id,
                array('justificativa' => $justificativa),
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            Logger::info('catalogo.curso.excluido', array('curso_evento_id' => $id));
            $pdo->commit();

            return array('ok' => true);
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('catalogo.curso.excluir_falhou', array('message' => $exception->getMessage()));
            throw $exception;
        }
    }

    public function listPublic()
    {
        $cursos = $this->cursoModel->allPublic();

        foreach ($cursos as &$curso) {
            $curso = $this->anexarProfessoresResponsaveisAoCurso($curso);
            $curso['turmas_abertas'] = $this->turmaModel->forPublicCourse($curso['id'], true);
            $curso['total_turmas_abertas'] = count($curso['turmas_abertas']);
            $curso['valor_efetivo'] = $this->calcularValorEfetivoCurso($curso);
            $curso['desconto_promocional'] = $this->calcularDescontoPromocional($curso);
        }
        unset($curso);

        return array('cursos' => $cursos);
    }

    public function listPublicHome($limit = 6)
    {
        $contexto = $this->listPublic();
        $cursos = isset($contexto['cursos']) ? $contexto['cursos'] : array();
        $limit = (int) $limit;
        if ($limit < 1 || $limit > 12) {
            $limit = 6;
        }

        return array_slice($cursos, 0, $limit);
    }

    public function listPublicTopVendas($limit = 5)
    {
        $cursos = $this->cursoModel->topPublicBySales($limit);

        foreach ($cursos as &$curso) {
            $curso = $this->anexarProfessoresResponsaveisAoCurso($curso);
            $curso['turmas_abertas'] = $this->turmaModel->forPublicCourse($curso['id'], true);
            $curso['total_turmas_abertas'] = count($curso['turmas_abertas']);
            $curso['valor_efetivo'] = $this->calcularValorEfetivoCurso($curso);
            $curso['desconto_promocional'] = $this->calcularDescontoPromocional($curso);
            $curso['total_vendas'] = isset($curso['total_vendas']) ? (int) $curso['total_vendas'] : 0;
        }
        unset($curso);

        return $cursos;
    }

    public function showPublic($cursoId, $turmaId = null)
    {
        $curso = $this->cursoModel->findPublicById($cursoId);

        if (!$curso) {
            return array('curso' => null);
        }

        $curso = $this->anexarProfessoresResponsaveisAoCurso($curso);
        $curso['turmas_abertas'] = $this->turmaModel->forPublicCourse($cursoId, true);
        $curso['turmas'] = $curso['turmas_abertas'];
        $curso['turma_selecionada'] = null;
        $curso['inscricao_disponivel'] = !empty($curso['turmas_abertas']);
        $curso['valor_efetivo'] = $this->calcularValorEfetivoCurso($curso);
        $curso['desconto_promocional'] = $this->calcularDescontoPromocional($curso);
        $curso['conteudo_programatico_view'] = $this->prepararConteudoProgramaticoParaView($curso);

        if ($turmaId) {
            $curso['turma_selecionada'] = $this->turmaModel->findPublicOpenForCourse($cursoId, $turmaId);
        }

        if ($curso['turma_selecionada'] === null && !empty($curso['turmas_abertas'])) {
            $curso['turma_selecionada'] = $curso['turmas_abertas'][0];
        }

        return array('curso' => $curso);
    }

    public function validarTurmaPublicaParaInscricao($cursoId, $turmaId = null)
    {
        $curso = $this->cursoModel->findPublicById($cursoId);
        if (!$curso) {
            return array('ok' => false, 'message' => 'Curso nao encontrado.');
        }

        $usarTurmas = isset($curso['usar_turmas']) ? (int) $curso['usar_turmas'] : 1;
        if ($usarTurmas !== 1) {
            return array('ok' => true, 'curso' => $curso, 'turma' => null);
        }

        if ((int) $turmaId <= 0) {
            return array('ok' => false, 'message' => 'Selecione uma turma aberta para continuar.');
        }

        $turma = $this->turmaModel->findPublicOpenForCourse($cursoId, $turmaId);
        if (!$turma) {
            return array('ok' => false, 'message' => 'A turma selecionada nao esta aberta para inscricao.');
        }

        return array('ok' => true, 'curso' => $curso, 'turma' => $turma);
    }

    public function calcularValorEfetivoCurso(array $curso)
    {
        $valor = isset($curso['valor']) ? (float) $curso['valor'] : 0.0;
        $emPromocao = !empty($curso['em_promocao']);
        $valorPromocional = isset($curso['valor_promocional']) && $curso['valor_promocional'] !== '' ? (float) $curso['valor_promocional'] : null;

        if ($emPromocao && $valorPromocional !== null && $valor > 0 && $valorPromocional >= 0 && $valorPromocional < $valor) {
            return $valorPromocional;
        }

        return $valor;
    }

    public function calcularDescontoPromocional(array $curso)
    {
        $valor = isset($curso['valor']) ? (float) $curso['valor'] : 0.0;
        $valorEfetivo = $this->calcularValorEfetivoCurso($curso);
        if ($valor <= 0 || $valorEfetivo >= $valor) {
            return null;
        }

        $descontoValor = $valor - $valorEfetivo;
        $percentual = ($descontoValor / $valor) * 100;

        return array(
            'valor_original' => $valor,
            'valor_promocional' => $valorEfetivo,
            'desconto_valor' => $descontoValor,
            'desconto_percentual' => $percentual,
        );
    }

    public function prepararConteudoProgramaticoParaView(array $curso)
    {
        $tipo = isset($curso['conteudo_programatico_tipo']) && $curso['conteudo_programatico_tipo'] !== ''
            ? (string) $curso['conteudo_programatico_tipo']
            : 'texto';

        if (!in_array($tipo, $this->conteudoProgramaticoTipos, true)) {
            $tipo = 'texto';
        }

        $texto = isset($curso['conteudo_programatico_texto']) ? (string) $curso['conteudo_programatico_texto'] : '';
        $modulos = isset($curso['conteudo_programatico_modulos']) ? (string) $curso['conteudo_programatico_modulos'] : '';

        $resultado = array(
            'tipo' => $tipo,
            'texto' => null,
            'html' => null,
            'modulos' => array(),
        );

        if ($tipo === 'modulos') {
            $resultado['modulos'] = $this->parsearConteudoProgramaticoModulosJson($modulos);
            return $resultado;
        }

        if (trim($texto) === '') {
            return $resultado;
        }

        if ($tipo === 'html') {
            $resultado['html'] = $this->sanitizarHtmlBasico($texto);
            return $resultado;
        }

        $resultado['texto'] = $texto;
        return $resultado;
    }

    private function parsearConteudoProgramaticoModulosJson($json)
    {
        $json = trim((string) $json);
        if ($json === '') {
            return array();
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return array();
        }

        $saida = array();
        foreach ($decoded as $modulo) {
            if (!is_array($modulo)) {
                continue;
            }

            $titulo = isset($modulo['titulo']) ? trim((string) $modulo['titulo']) : '';
            $itens = isset($modulo['itens']) && is_array($modulo['itens']) ? $modulo['itens'] : array();

            $itensLimpos = array();
            foreach ($itens as $item) {
                $item = trim((string) $item);
                if ($item === '') {
                    continue;
                }
                $itensLimpos[] = $item;
            }

            if ($titulo === '' && empty($itensLimpos)) {
                continue;
            }

            $saida[] = array(
                'titulo' => $titulo,
                'itens' => $itensLimpos,
            );
        }

        return $saida;
    }

    public function modalidadeLabel($modalidade)
    {
        $map = array(
            'presencial' => 'Presencial',
            'online_ao_vivo' => 'Online ao vivo',
            'hibrido' => 'Hibrido',
            'sob_demanda' => 'Sob demanda',
        );

        return isset($map[$modalidade]) ? $map[$modalidade] : (string) $modalidade;
    }

    public function normalizarLinhasTexto($texto)
    {
        if ($texto === null) {
            return null;
        }

        $texto = str_replace("\r\n", "\n", (string) $texto);
        $texto = str_replace("\r", "\n", $texto);
        $linhas = explode("\n", $texto);

        $limpas = array();
        foreach ($linhas as $linha) {
            $linha = trim((string) $linha);
            if ($linha === '') {
                continue;
            }
            $limpas[] = $linha;
        }

        if (empty($limpas)) {
            return null;
        }

        return implode("\n", $limpas);
    }

    public function prepararConteudoProgramaticoModulos($data)
    {
        $titulos = isset($data['conteudo_programatico_modulos_titulo']) ? (array) $data['conteudo_programatico_modulos_titulo'] : array();
        $itensPorModulo = isset($data['conteudo_programatico_modulos_itens']) ? (array) $data['conteudo_programatico_modulos_itens'] : array();

        $modulos = array();
        $max = max(count($titulos), count($itensPorModulo));

        for ($i = 0; $i < $max; $i++) {
            $titulo = isset($titulos[$i]) ? trim((string) $titulos[$i]) : '';
            $itensRaw = isset($itensPorModulo[$i]) ? (string) $itensPorModulo[$i] : '';

            $itensNormalizados = $this->normalizarLinhasTexto($itensRaw);
            $itens = $itensNormalizados !== null ? explode("\n", $itensNormalizados) : array();

            if ($titulo === '' && empty($itens)) {
                continue;
            }

            $modulos[] = array(
                'titulo' => $titulo,
                'itens' => $itens,
            );
        }

        if (empty($modulos)) {
            return null;
        }

        return json_encode($modulos, JSON_UNESCAPED_UNICODE);
    }

    private function normalizarTextoLongoOpcional($texto)
    {
        $texto = $texto === null ? '' : (string) $texto;
        $texto = trim($texto);
        return $texto === '' ? null : $texto;
    }

    private function normalizarDecimalOpcional($valor)
    {
        if ($valor === null) {
            return null;
        }

        $valor = trim((string) $valor);
        if ($valor === '') {
            return null;
        }

        $valor = str_replace(array(' ', "\xc2\xa0"), '', $valor);
        $possuiVirgula = strpos($valor, ',') !== false;
        $possuiPonto = strpos($valor, '.') !== false;

        if ($possuiVirgula && $possuiPonto) {
            $ultimaVirgula = strrpos($valor, ',');
            $ultimoPonto = strrpos($valor, '.');
            if ($ultimaVirgula > $ultimoPonto) {
                $valor = str_replace('.', '', $valor);
                $valor = str_replace(',', '.', $valor);
            } else {
                $valor = str_replace(',', '', $valor);
            }
        } elseif ($possuiVirgula) {
            $valor = str_replace('.', '', $valor);
            $valor = str_replace(',', '.', $valor);
        }

        if (!is_numeric($valor)) {
            return null;
        }

        return (float) $valor;
    }

    private function sanitizarHtmlBasico($html)
    {
        $html = (string) $html;
        if (trim($html) === '') {
            return '';
        }

        if (!class_exists('DOMDocument')) {
            return htmlspecialchars($html, ENT_QUOTES, 'UTF-8');
        }

        $allowedTags = array(
            'p', 'br', 'strong', 'b', 'em', 'i', 'u',
            'ul', 'ol', 'li',
            'h3', 'h4', 'h5', 'h6',
            'blockquote',
            'a',
        );

        $allowedAttrs = array(
            'a' => array('href', 'title', 'target', 'rel'),
        );

        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument('1.0', 'UTF-8');

        $wrapped = '<div>' . $html . '</div>';
        $dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        $xpath = new \DOMXPath($dom);

        foreach ($xpath->query('//*') as $node) {
            $tag = strtolower($node->nodeName);

            if (!in_array($tag, $allowedTags, true)) {
                $this->domRemoverMantendoFilhos($node);
                continue;
            }

            if ($node->hasAttributes()) {
                $attrs = array();
                foreach ($node->attributes as $attr) {
                    $attrs[] = $attr->nodeName;
                }

                foreach ($attrs as $attrName) {
                    $attrLower = strtolower($attrName);
                    if (strpos($attrLower, 'on') === 0) {
                        $node->removeAttribute($attrName);
                        continue;
                    }

                    if (!isset($allowedAttrs[$tag]) || !in_array($attrLower, $allowedAttrs[$tag], true)) {
                        $node->removeAttribute($attrName);
                        continue;
                    }

                    if ($tag === 'a' && $attrLower === 'href') {
                        $href = trim((string) $node->getAttribute('href'));
                        if ($href === '' || preg_match('/^\\s*javascript:/i', $href)) {
                            $node->removeAttribute('href');
                        }
                    }
                }
            }

            if ($tag === 'a') {
                $target = strtolower(trim((string) $node->getAttribute('target')));
                if ($target === '_blank') {
                    $node->setAttribute('rel', 'noopener noreferrer');
                } else {
                    $node->removeAttribute('target');
                }
            }
        }

        $saida = '';
        $container = $dom->getElementsByTagName('div')->item(0);
        if ($container) {
            foreach ($container->childNodes as $child) {
                $saida .= $dom->saveHTML($child);
            }
        }

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $saida;
    }

    private function domRemoverMantendoFilhos(\DOMNode $node)
    {
        $parent = $node->parentNode;
        if (!$parent) {
            return;
        }

        while ($node->firstChild) {
            $parent->insertBefore($node->firstChild, $node);
        }

        $parent->removeChild($node);
    }

    private function slugify($value)
    {
        $value = trim((string) $value);
        $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/i', '-', $value);
        $value = trim($value, '-');

        return $value;
    }

    private function nomeDaCopia($nome)
    {
        $nome = trim((string) $nome);
        $nome = preg_replace('/^c[oó]pia de\s+/iu', '', $nome);

        return 'Cópia de ' . $nome;
    }

    private function slugDaCopia($valor)
    {
        $base = $this->slugify($valor);
        $base = preg_replace('/-copia(?:-\d+)?$/', '', $base);
        $base = trim((string) $base, '-');
        if ($base === '') {
            $base = 'curso';
        }

        $slug = $base . '-copia';
        $sufixo = 2;
        while ($this->cursoModel->findBySlug($slug)) {
            $slug = $base . '-copia-' . $sufixo;
            $sufixo++;
        }

        return $slug;
    }

    public function duplicar(array $data, array $files = array(), $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $id = !empty($data['id']) ? (int) $data['id'] : 0;
        if ($id <= 0) {
            return array('ok' => false, 'errors' => array('Curso/evento não encontrado.'));
        }

        $original = $this->cursoModel->findAdminById($id);
        if (!$original) {
            return array('ok' => false, 'errors' => array('Curso/evento não encontrado.'));
        }

        $nomeBase = trim((string) (isset($data['nome']) && trim((string) $data['nome']) !== '' ? $data['nome'] : $original['nome']));
        $slugBase = isset($data['slug']) && trim((string) $data['slug']) !== '' ? $data['slug'] : $nomeBase;
        $payload = $data;
        $payload['id'] = 0;
        $payload['nome'] = $this->nomeDaCopia($nomeBase);
        $payload['slug'] = $this->slugDaCopia($slugBase);
        $payload['status'] = 'rascunho';

        return $this->salvar($payload, $files, $actorUserId, $ipAddress, $userAgent);
    }

    public function atualizarStatus($id, $status, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $curso = $this->cursoModel->findById($id);
        if (!$curso) {
            return array('ok' => false, 'message' => 'Curso/evento nao encontrado.');
        }

        if (!in_array($status, array('ativo', 'inativo'), true)) {
            return array('ok' => false, 'message' => 'Status invalido para o curso/evento.');
        }

        try {
            $this->cursoModel->updateStatus($id, $status);

            $this->auditService->record(
                'catalogo.curso.status_atualizado',
                'curso_evento',
                $id,
                array('status_anterior' => $curso['status'], 'status_novo' => $status),
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            Logger::info('catalogo.curso.status_atualizado', array('curso_evento_id' => $id, 'status' => $status));

            return array('ok' => true);
        } catch (Exception $exception) {
            Logger::error('catalogo.curso.status_falhou', array(
                'curso_evento_id' => $id,
                'status' => $status,
                'message' => $exception->getMessage(),
            ));

            return array('ok' => false, 'message' => 'Nao foi possivel atualizar o status do curso/evento.');
        }
    }

    private function validarProfessorResponsavel($usuarioId)
    {
        $professores = $this->validarProfessoresResponsaveis(array($usuarioId));
        return !empty($professores) ? $professores[0] : null;
    }

    private function anexarProfessoresResponsaveisAoCurso(array $curso)
    {
        if (empty($curso['id'])) {
            $curso['professores_responsaveis'] = array();
            $curso['professores_responsaveis_ids'] = array();
            $curso['professor_responsavel'] = null;
            return $curso;
        }

        $professoresResponsaveis = $this->cursoPessoaModel->findProfessoresResponsaveis($curso['id']);
        $professoresResponsaveisIds = array();

        foreach ($professoresResponsaveis as $professorResponsavel) {
            if (!empty($professorResponsavel['usuario_id'])) {
                $professoresResponsaveisIds[] = (int) $professorResponsavel['usuario_id'];
            }
        }

        $curso['professores_responsaveis'] = $professoresResponsaveis;
        $curso['professores_responsaveis_ids'] = $professoresResponsaveisIds;
        $curso['professor_responsavel'] = !empty($professoresResponsaveis) ? $professoresResponsaveis[0] : null;

        return $curso;
    }

    private function normalizarListaInteirosUnicos($valor)
    {
        if ($valor === null) {
            return array();
        }

        if (!is_array($valor)) {
            $valor = array($valor);
        }

        $ids = array();
        foreach ($valor as $item) {
            if (is_array($item)) {
                continue;
            }

            $item = trim((string) $item);
            if ($item === '' || !is_numeric($item)) {
                continue;
            }

            $numero = (int) $item;
            if ($numero <= 0 || isset($ids[$numero])) {
                continue;
            }

            $ids[$numero] = $numero;
        }

        return array_values($ids);
    }

    private function validarProfessoresResponsaveis(array $usuarioIds)
    {
        $professoresDisponiveis = array();
        foreach ($this->usuarioModel->professores() as $professor) {
            $professorId = isset($professor['id']) ? (int) $professor['id'] : 0;
            if ($professorId > 0) {
                $professoresDisponiveis[$professorId] = $professor;
            }
        }

        $selecionados = array();
        foreach ($usuarioIds as $usuarioId) {
            $usuarioId = (int) $usuarioId;
            if ($usuarioId <= 0 || !isset($professoresDisponiveis[$usuarioId])) {
                return array();
            }

            $professor = $professoresDisponiveis[$usuarioId];
            $selecionados[] = array(
                'usuario_id' => $usuarioId,
                'nome' => isset($professor['nome']) ? trim((string) $professor['nome']) : '',
            );
        }

        return $selecionados;
    }
}
