<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Validator;
use App\Models\Permissao;
use App\Models\Perfil;
use App\Models\PerfilPermissao;
use App\Models\Usuario;
use App\Models\UsuarioPerfil;
use Exception;

class GestaoAcessoService
{
    private $usuarios;
    private $perfis;
    private $permissoes;
    private $usuarioPerfil;
    private $perfilPermissao;
    private $auditService;
    private $trashService;

    public function __construct()
    {
        $this->usuarios = new Usuario();
        $this->perfis = new Perfil();
        $this->permissoes = new Permissao();
        $this->usuarioPerfil = new UsuarioPerfil();
        $this->perfilPermissao = new PerfilPermissao();
        $this->auditService = new AuditService();
        $this->trashService = new TrashService();
    }

    public function painelUsuarios(array $filters = array())
    {
        $perfis = $this->perfis->all();
        return array(
            'usuarios' => $this->usuarios->allForAdmin($filters),
            'perfis' => $perfis,
            'lixeira_usuarios' => $this->listarLixeiraUsuarios(),
        );
    }

    public function dadosUsuario($id)
    {
        $usuario = $id ? $this->usuarios->findById((int) $id) : null;
        $perfilIds = array();
        if ($usuario) {
            foreach ($this->usuarioPerfil->forUser((int) $usuario['id']) as $perfil) {
                $perfilIds[] = (int) $perfil['id'];
            }
        }

        return array(
            'usuario' => $usuario,
            'perfil_ids' => $perfilIds,
            'perfis' => $this->perfis->all(),
        );
    }

    public function salvarUsuario(array $data, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $id = !empty($data['id']) ? (int) $data['id'] : 0;
        $nome = trim((string) ($data['nome'] ?? ''));
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $cpf = Validator::onlyDigits($data['cpf'] ?? '');
        $telefone = trim((string) ($data['telefone'] ?? ''));
        $cidade = trim((string) ($data['cidade'] ?? ''));
        $estado = strtoupper(trim((string) ($data['estado'] ?? '')));
        $status = in_array(($data['status'] ?? 'ativo'), array('ativo', 'inativo', 'bloqueado'), true) ? $data['status'] : 'ativo';
        $senha = (string) ($data['senha'] ?? '');
        $perfilIds = array_values(array_unique(array_map('intval', (array) ($data['perfil_ids'] ?? array()))));

        $errors = array();
        if ($nome === '') {
            $errors[] = 'Informe o nome.';
        }
        if (!Validator::email($email)) {
            $errors[] = 'Informe um e-mail válido.';
        }
        if ($cpf === '') {
            $errors[] = 'Informe o CPF.';
        }
        if (strlen($cpf) > 14) {
            $errors[] = 'O CPF deve ter no máximo 14 dígitos.';
        }
        if ($estado !== '' && !preg_match('/^[A-Z]{2}$/', $estado)) {
            $errors[] = 'Informe uma UF válida.';
        }
        if ($id === 0 && strlen($senha) < 8) {
            $errors[] = 'A senha inicial deve ter pelo menos 8 caracteres.';
        }
        if ($id > 0 && $senha !== '' && strlen($senha) < 8) {
            $errors[] = 'A nova senha deve ter pelo menos 8 caracteres.';
        }

        $emailExistente = $this->usuarios->findByEmail($email);
        if ($emailExistente && (int) $emailExistente['id'] !== $id) {
            $errors[] = 'Este e-mail já está cadastrado.';
        }
        $cpfExistente = $this->usuarios->findByCpf($cpf);
        if ($cpfExistente && (int) $cpfExistente['id'] !== $id) {
            $errors[] = 'Este CPF já está cadastrado.';
        }

        if ($errors) {
            return array('ok' => false, 'errors' => $errors);
        }

        $payload = array(
            'nome' => Validator::upperName($nome),
            'email' => $email,
            'cpf' => $cpf,
            'telefone' => $telefone !== '' ? $telefone : null,
            'cidade' => $cidade !== '' ? $cidade : null,
            'estado' => $estado !== '' ? $estado : null,
            'status' => $status,
        );

        if ($senha !== '') {
            $payload['senha_hash'] = password_hash($senha, PASSWORD_DEFAULT);
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            if ($id > 0) {
                $anterior = $this->usuarios->findById($id);
                $this->usuarios->updateAdmin($id, $payload);
                $this->usuarioPerfil->sync($id, $perfilIds);
                $acao = 'usuarios.atualizado';
            } else {
                $anterior = null;
                $payload['senha_hash'] = $payload['senha_hash'] ?? password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
                $id = $this->usuarios->create($payload);
                $this->usuarioPerfil->sync($id, $perfilIds);
                $acao = 'usuarios.criado';
            }

            $this->auditService->record($acao, 'usuario', $id, array('anterior' => $anterior, 'novo' => $payload, 'perfil_ids' => $perfilIds), $actorUserId, $ipAddress, $userAgent);
            $pdo->commit();
            return array('ok' => true, 'id' => $id);
        } catch (Exception $exception) {
            $pdo->rollBack();
            return array('ok' => false, 'errors' => array('Não foi possível salvar o usuário. Verifique CPF, e-mail e campos obrigatórios.'));
        }
    }

    public function excluirUsuario($id, $justificativa, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $usuario = $this->usuarios->findById((int) $id);
        if (!$usuario) {
            return array('ok' => false, 'message' => 'Usuário não encontrado.');
        }

        $this->trashService->record('usuario', $id, $justificativa, $usuario, $actorUserId, $ipAddress, $userAgent);
        $this->usuarios->setStatus((int) $id, 'inativo');
        $this->auditService->record('usuarios.inativado', 'usuario', (int) $id, array('justificativa' => $justificativa), $actorUserId, $ipAddress, $userAgent);
        return array('ok' => true);
    }

    public function painelPermissoes()
    {
        $perfis = $this->perfis->withPermissions();
        $permissoes = $this->permissoes->all();
        return array('perfis' => $perfis, 'permissoes' => $permissoes);
    }

    public function dadosPerfil($id)
    {
        $perfil = $id ? $this->perfis->findById((int) $id) : null;
        $permissaoIds = array();
        if ($perfil) {
            foreach ($this->perfilPermissao->forPerfil((int) $perfil['id']) as $permissao) {
                $permissaoIds[] = (int) $permissao['id'];
            }
        }
        return array('perfil' => $perfil, 'permissao_ids' => $permissaoIds, 'permissoes' => $this->permissoes->all());
    }

    public function salvarPerfil(array $data, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $id = !empty($data['id']) ? (int) $data['id'] : 0;
        $nome = trim((string) ($data['nome'] ?? ''));
        $slug = $this->slugify(($data['slug'] ?? $nome));
        $descricao = trim((string) ($data['descricao'] ?? ''));
        $status = in_array(($data['status'] ?? 'ativo'), array('ativo', 'inativo'), true) ? $data['status'] : 'ativo';
        $permissaoIds = array_values(array_unique(array_map('intval', (array) ($data['permissao_ids'] ?? array()))));

        $errors = array();
        if ($nome === '') {
            $errors[] = 'Informe o nome do papel.';
        }
        if ($slug === '') {
            $errors[] = 'Informe o slug do papel.';
        }
        if ($this->perfis->findBySlug($slug, $id > 0 ? $id : null)) {
            $errors[] = 'Já existe um papel com este slug.';
        }
        if ($errors) {
            return array('ok' => false, 'errors' => $errors);
        }

        $payload = array('nome' => $nome, 'slug' => $slug, 'descricao' => $descricao !== '' ? $descricao : null, 'status' => $status, 'sistema' => 0);

        if ($id > 0) {
            $this->perfis->update($payload, $id);
            $this->perfilPermissao->sync($id, $permissaoIds);
            $this->auditService->record('rbac.papel.atualizado', 'perfil', $id, array('novo' => $payload, 'permissao_ids' => $permissaoIds), $actorUserId, $ipAddress, $userAgent);
            return array('ok' => true, 'id' => $id);
        }

        $id = $this->perfis->create($payload);
        $this->perfilPermissao->sync($id, $permissaoIds);
        $this->auditService->record('rbac.papel.criado', 'perfil', $id, array('novo' => $payload, 'permissao_ids' => $permissaoIds), $actorUserId, $ipAddress, $userAgent);
        return array('ok' => true, 'id' => $id);
    }

    public function excluirPerfil($id, $justificativa, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $perfil = $this->perfis->findById((int) $id);
        if (!$perfil) {
            return array('ok' => false, 'message' => 'Papel não encontrado.');
        }
        if (!empty($perfil['sistema'])) {
            return array('ok' => false, 'message' => 'Não é permitido excluir papéis de sistema.');
        }
        $this->trashService->record('perfil', $id, $justificativa, $perfil, $actorUserId, $ipAddress, $userAgent);
        $this->perfis->softDelete((int) $id);
        $this->auditService->record('rbac.papel.excluido', 'perfil', (int) $id, array('justificativa' => $justificativa), $actorUserId, $ipAddress, $userAgent);
        return array('ok' => true);
    }

    public function dadosPermissao($id)
    {
        return array('permissao' => $id ? $this->permissoes->findById((int) $id) : null);
    }

    public function salvarPermissao(array $data, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $id = !empty($data['id']) ? (int) $data['id'] : 0;
        $modulo = trim((string) ($data['modulo'] ?? ''));
        $acao = trim((string) ($data['acao'] ?? ''));
        $nome = trim((string) ($data['nome'] ?? ''));
        $descricao = trim((string) ($data['descricao'] ?? ''));
        $slug = trim((string) ($data['slug'] ?? ''));
        if ($slug === '' && $modulo !== '' && $acao !== '') {
            $slug = $this->slugify($modulo) . '.' . $this->slugify($acao);
        }

        $errors = array();
        if ($modulo === '' || $acao === '' || $nome === '' || $slug === '') {
            $errors[] = 'Preencha módulo, ação, nome e slug da permissão.';
        }
        if ($this->permissoes->findBySlug($slug, $id > 0 ? $id : null)) {
            $errors[] = 'Já existe uma permissão com este slug.';
        }
        if ($errors) {
            return array('ok' => false, 'errors' => $errors);
        }

        $payload = array('modulo' => $modulo, 'acao' => $acao, 'slug' => $slug, 'nome' => $nome, 'descricao' => $descricao !== '' ? $descricao : null);
        if ($id > 0) {
            $this->permissoes->update($payload, $id);
            $this->auditService->record('rbac.permissao.atualizada', 'permissao', $id, array('novo' => $payload), $actorUserId, $ipAddress, $userAgent);
            return array('ok' => true, 'id' => $id);
        }
        $id = $this->permissoes->create($payload);
        $this->auditService->record('rbac.permissao.criada', 'permissao', $id, array('novo' => $payload), $actorUserId, $ipAddress, $userAgent);
        return array('ok' => true, 'id' => $id);
    }

    public function excluirPermissao($id, $justificativa, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $permissao = $this->permissoes->findById((int) $id);
        if (!$permissao) {
            return array('ok' => false, 'message' => 'Permissão não encontrada.');
        }
        $this->trashService->record('permissao', $id, $justificativa, $permissao, $actorUserId, $ipAddress, $userAgent);
        $this->permissoes->softDelete((int) $id);
        $this->auditService->record('rbac.permissao.excluida', 'permissao', (int) $id, array('justificativa' => $justificativa), $actorUserId, $ipAddress, $userAgent);
        return array('ok' => true);
    }

    private function slugify($value)
    {
        $value = trim((string) $value);
        $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/i', '-', $value);
        return trim($value, '-');
    }

    private function listarLixeiraUsuarios()
    {
        $stmt = Database::connection()->prepare(
            'SELECT l.id,
                    l.entidade_id,
                    l.justificativa,
                    l.snapshot_dados,
                    l.created_at,
                    u.nome AS excluido_por_nome
             FROM lixeira l
             LEFT JOIN usuarios u ON u.id = l.excluido_por_usuario_id
             WHERE l.entidade_tipo = "usuario"
               AND l.restaurado_em IS NULL
             ORDER BY l.id DESC'
        );
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $row['snapshot_nome'] = '';
            if (!empty($row['snapshot_dados'])) {
                $snapshot = json_decode((string) $row['snapshot_dados'], true);
                if (is_array($snapshot) && !empty($snapshot['nome'])) {
                    $row['snapshot_nome'] = (string) $snapshot['nome'];
                }
            }
        }
        unset($row);

        return $rows;
    }
}
