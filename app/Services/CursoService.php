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

        foreach ($cursos as &$curso) {
            $curso['pessoas_vinculadas'] = $this->cursoPessoaModel->forCourse($curso['id']);
            $curso['professor_responsavel'] = $this->cursoPessoaModel->findProfessorResponsavel($curso['id']);
        }
        unset($curso);

        return array(
            'categorias' => $categorias,
            'cursos' => $cursos,
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
        $professorResponsavel = $cursoId ? $this->cursoPessoaModel->findProfessorResponsavel($cursoId) : null;

        return array(
            'curso' => $curso,
            'categorias' => $this->categoriaModel->allForSelect(),
            'turmas' => $cursoId ? $this->turmaModel->forCourse($cursoId) : array(),
            'pessoas_vinculadas' => $cursoId ? $this->cursoPessoaModel->forCourse($cursoId) : array(),
            'professores' => $this->usuarioModel->professores(),
            'professor_responsavel' => $professorResponsavel,
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
        $valor = isset($data['valor']) && $data['valor'] !== '' ? (float) $data['valor'] : 0;
        $ordem = isset($data['ordem']) ? (int) $data['ordem'] : 0;
        $status = isset($data['status']) && in_array($data['status'], array('rascunho', 'ativo', 'inativo', 'arquivado'), true) ? $data['status'] : 'rascunho';
        $professorResponsavelUsuarioId = isset($data['professor_responsavel_usuario_id']) && $data['professor_responsavel_usuario_id'] !== ''
            ? (int) $data['professor_responsavel_usuario_id']
            : null;

        $errors = array();
        if ($nome === '') {
            $errors[] = 'Nome do curso/evento e obrigatorio.';
        }
        if ($slug === '') {
            $errors[] = 'Slug do curso/evento e obrigatorio.';
        }
        if ($valor < 0) {
            $errors[] = 'Valor invalido.';
        }
        if ($categoriaId !== null && !$this->categoriaModel->findById($categoriaId)) {
            $errors[] = 'Categoria nao encontrada.';
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

        $professorResponsavel = null;
        if ($professorResponsavelUsuarioId !== null) {
            $professorResponsavel = $this->validarProfessorResponsavel($professorResponsavelUsuarioId);
            if (!$professorResponsavel) {
                $errors[] = 'Professor responsavel nao encontrado.';
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

            $this->cursoPessoaModel->syncProfessorResponsavel(
                $id,
                $professorResponsavel ? (int) $professorResponsavel['id'] : null,
                $professorResponsavel ? $professorResponsavel['nome'] : '',
                'ativo'
            );
            $this->usuarioCursoModel->syncProfessorForCourse(
                $id,
                $professorResponsavel ? (int) $professorResponsavel['id'] : null,
                'ativo'
            );

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
            $curso['professor_responsavel'] = $this->cursoPessoaModel->findProfessorResponsavel($curso['id']);
            $curso['turmas_abertas'] = $this->turmaModel->forPublicCourse($curso['id'], true);
            $curso['total_turmas_abertas'] = count($curso['turmas_abertas']);
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

    public function showPublic($cursoId, $turmaId = null)
    {
        $curso = $this->cursoModel->findPublicById($cursoId);

        if (!$curso) {
            return array('curso' => null);
        }

        $curso['professor_responsavel'] = $this->cursoPessoaModel->findProfessorResponsavel($cursoId);
        $curso['turmas_abertas'] = $this->turmaModel->forPublicCourse($cursoId, true);
        $curso['turmas'] = $curso['turmas_abertas'];
        $curso['turma_selecionada'] = null;
        $curso['inscricao_disponivel'] = !empty($curso['turmas_abertas']);

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

    private function slugify($value)
    {
        $value = trim((string) $value);
        $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/i', '-', $value);
        $value = trim($value, '-');

        return $value;
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
        foreach ($this->usuarioModel->professores() as $professor) {
            if ((int) $professor['id'] === (int) $usuarioId) {
                return $professor;
            }
        }

        return null;
    }
}



