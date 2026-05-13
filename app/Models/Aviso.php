<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class Aviso
{
    public function all(array $filters = array())
    {
        $where = array('a.deleted_at IS NULL');
        $params = array();

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $where[] = '(a.titulo LIKE :q OR a.mensagem LIKE :q)';
            $params['q'] = '%' . $search . '%';
        }

        $status = trim((string) ($filters['status'] ?? ''));
        $statusValidos = array('rascunho', 'enviado', 'pausado', 'encerrado');
        if ($status !== '' && in_array($status, $statusValidos, true)) {
            $where[] = 'a.status = :status';
            $params['status'] = $status;
        }

        $tipoDestino = trim((string) ($filters['tipo_destino'] ?? ''));
        $tiposValidos = array('todos_alunos', 'alunos_curso', 'alunos_sem_compra_confirmada', 'aluno_curso', 'aluno_individual');
        if ($tipoDestino !== '' && in_array($tipoDestino, $tiposValidos, true)) {
            $where[] = 'a.tipo_destino = :tipo_destino';
            $params['tipo_destino'] = $tipoDestino;
        }

        $cursoId = isset($filters['curso_evento_id']) && $filters['curso_evento_id'] !== '' ? (int) $filters['curso_evento_id'] : 0;
        if ($cursoId > 0) {
            $where[] = 'a.curso_evento_id = :curso_evento_id';
            $params['curso_evento_id'] = $cursoId;
        }

        $criadoDe = trim((string) ($filters['criado_de'] ?? ''));
        if ($criadoDe !== '') {
            $where[] = 'DATE(a.criado_em) >= :criado_de';
            $params['criado_de'] = $criadoDe;
        }

        $criadoAte = trim((string) ($filters['criado_ate'] ?? ''));
        if ($criadoAte !== '') {
            $where[] = 'DATE(a.criado_em) <= :criado_ate';
            $params['criado_ate'] = $criadoAte;
        }

        $sql = 'SELECT a.*,
                       ce.nome AS curso_nome,
                       ce.slug AS curso_slug,
                       u.nome AS usuario_nome,
                       u.email AS usuario_email,
                       u_criado.nome AS criado_por_nome,
                       u_editado.nome AS editado_por_nome,
                       u_enviado.nome AS enviado_por_nome,
                       COALESCE(resumo.total_destinatarios, 0) AS total_destinatarios,
                       COALESCE(resumo.total_visualizados, 0) AS total_visualizados,
                       COALESCE(resumo.total_ocultados, 0) AS total_ocultados,
                       COALESCE(resumo.total_ativos, 0) AS total_ativos
                FROM avisos a
                LEFT JOIN cursos_eventos ce ON ce.id = a.curso_evento_id
                LEFT JOIN usuarios u ON u.id = a.usuario_id
                LEFT JOIN usuarios u_criado ON u_criado.id = a.criado_por
                LEFT JOIN usuarios u_editado ON u_editado.id = a.editado_por
                LEFT JOIN usuarios u_enviado ON u_enviado.id = a.enviado_por
                LEFT JOIN (
                    SELECT aviso_id,
                           COUNT(*) AS total_destinatarios,
                           SUM(CASE WHEN visualizado_em IS NOT NULL THEN 1 ELSE 0 END) AS total_visualizados,
                           SUM(CASE WHEN ocultado_em IS NOT NULL THEN 1 ELSE 0 END) AS total_ocultados,
                           SUM(CASE WHEN status = "ativo" AND deleted_at IS NULL THEN 1 ELSE 0 END) AS total_ativos
                    FROM avisos_destinatarios
                    WHERE deleted_at IS NULL
                    GROUP BY aviso_id
                ) resumo ON resumo.aviso_id = a.id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY a.destaque DESC, a.prioridade DESC, a.enviado_em DESC, a.criado_em DESC, a.id DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById($id)
    {
        $stmt = Database::connection()->prepare(
            'SELECT a.*,
                    ce.nome AS curso_nome,
                    ce.slug AS curso_slug,
                    u.nome AS usuario_nome,
                    u.email AS usuario_email,
                    u_criado.nome AS criado_por_nome,
                    u_editado.nome AS editado_por_nome,
                    u_enviado.nome AS enviado_por_nome
             FROM avisos a
             LEFT JOIN cursos_eventos ce ON ce.id = a.curso_evento_id
             LEFT JOIN usuarios u ON u.id = a.usuario_id
             LEFT JOIN usuarios u_criado ON u_criado.id = a.criado_por
             LEFT JOIN usuarios u_editado ON u_editado.id = a.editado_por
             LEFT JOIN usuarios u_enviado ON u_enviado.id = a.enviado_por
             WHERE a.id = :id
               AND a.deleted_at IS NULL
             LIMIT 1'
        );

        $stmt->execute(array('id' => (int) $id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function create(array $data)
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO avisos
             (curso_evento_id, usuario_id, titulo, mensagem, tipo_destino, mostrar_inicio, mostrar_fim, permitir_ocultar, status,
              origem, gatilho, prioridade, link_url, link_rotulo, destaque,
              criado_por, criado_em, editado_por, editado_em, enviado_por, enviado_em, deleted_at)
             VALUES
             (:curso_evento_id, :usuario_id, :titulo, :mensagem, :tipo_destino, :mostrar_inicio, :mostrar_fim, :permitir_ocultar, :status,
              :origem, :gatilho, :prioridade, :link_url, :link_rotulo, :destaque,
              :criado_por, :criado_em, :editado_por, :editado_em, :enviado_por, :enviado_em, NULL)'
        );

        $stmt->execute(array(
            'curso_evento_id' => array_key_exists('curso_evento_id', $data) ? $data['curso_evento_id'] : null,
            'usuario_id' => array_key_exists('usuario_id', $data) ? $data['usuario_id'] : null,
            'titulo' => array_key_exists('titulo', $data) ? $data['titulo'] : null,
            'mensagem' => $data['mensagem'],
            'tipo_destino' => $data['tipo_destino'],
            'mostrar_inicio' => array_key_exists('mostrar_inicio', $data) ? $data['mostrar_inicio'] : null,
            'mostrar_fim' => array_key_exists('mostrar_fim', $data) ? $data['mostrar_fim'] : null,
            'permitir_ocultar' => !empty($data['permitir_ocultar']) ? 1 : 0,
            'status' => $data['status'],
            'origem' => array_key_exists('origem', $data) ? $data['origem'] : 'manual',
            'gatilho' => array_key_exists('gatilho', $data) ? $data['gatilho'] : null,
            'prioridade' => array_key_exists('prioridade', $data) ? (int) $data['prioridade'] : 0,
            'link_url' => array_key_exists('link_url', $data) ? $data['link_url'] : null,
            'link_rotulo' => array_key_exists('link_rotulo', $data) ? $data['link_rotulo'] : null,
            'destaque' => !empty($data['destaque']) ? 1 : 0,
            'criado_por' => array_key_exists('criado_por', $data) ? $data['criado_por'] : null,
            'criado_em' => array_key_exists('criado_em', $data) ? $data['criado_em'] : date('Y-m-d H:i:s'),
            'editado_por' => array_key_exists('editado_por', $data) ? $data['editado_por'] : null,
            'editado_em' => array_key_exists('editado_em', $data) ? $data['editado_em'] : null,
            'enviado_por' => array_key_exists('enviado_por', $data) ? $data['enviado_por'] : null,
            'enviado_em' => array_key_exists('enviado_em', $data) ? $data['enviado_em'] : null,
        ));

        return (int) Database::connection()->lastInsertId();
    }

    public function update(array $data, $id)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE avisos
             SET curso_evento_id = :curso_evento_id,
                 usuario_id = :usuario_id,
                 titulo = :titulo,
                 mensagem = :mensagem,
                 tipo_destino = :tipo_destino,
                 mostrar_inicio = :mostrar_inicio,
                 mostrar_fim = :mostrar_fim,
                 permitir_ocultar = :permitir_ocultar,
                 status = :status,
                 origem = :origem,
                 gatilho = :gatilho,
                 prioridade = :prioridade,
                 link_url = :link_url,
                 link_rotulo = :link_rotulo,
                 destaque = :destaque,
                 editado_por = :editado_por,
                 editado_em = :editado_em
             WHERE id = :id
               AND deleted_at IS NULL'
        );

        $stmt->execute(array(
            'curso_evento_id' => array_key_exists('curso_evento_id', $data) ? $data['curso_evento_id'] : null,
            'usuario_id' => array_key_exists('usuario_id', $data) ? $data['usuario_id'] : null,
            'titulo' => array_key_exists('titulo', $data) ? $data['titulo'] : null,
            'mensagem' => $data['mensagem'],
            'tipo_destino' => $data['tipo_destino'],
            'mostrar_inicio' => array_key_exists('mostrar_inicio', $data) ? $data['mostrar_inicio'] : null,
            'mostrar_fim' => array_key_exists('mostrar_fim', $data) ? $data['mostrar_fim'] : null,
            'permitir_ocultar' => !empty($data['permitir_ocultar']) ? 1 : 0,
            'status' => $data['status'],
            'origem' => array_key_exists('origem', $data) ? $data['origem'] : 'manual',
            'gatilho' => array_key_exists('gatilho', $data) ? $data['gatilho'] : null,
            'prioridade' => array_key_exists('prioridade', $data) ? (int) $data['prioridade'] : 0,
            'link_url' => array_key_exists('link_url', $data) ? $data['link_url'] : null,
            'link_rotulo' => array_key_exists('link_rotulo', $data) ? $data['link_rotulo'] : null,
            'destaque' => !empty($data['destaque']) ? 1 : 0,
            'editado_por' => array_key_exists('editado_por', $data) ? $data['editado_por'] : null,
            'editado_em' => array_key_exists('editado_em', $data) ? $data['editado_em'] : date('Y-m-d H:i:s'),
            'id' => (int) $id,
        ));
    }

    public function softDelete($id)
    {
        $stmt = Database::connection()->prepare(
            'UPDATE avisos
             SET deleted_at = NOW()
             WHERE id = :id
               AND deleted_at IS NULL'
        );
        $stmt->execute(array('id' => (int) $id));
    }

    public function marcarEnviado($id, $usuarioId, $status = 'enviado')
    {
        $stmt = Database::connection()->prepare(
            'UPDATE avisos
             SET status = :status,
                 enviado_por = :enviado_por,
                 enviado_em = NOW(),
                 editado_por = :editado_por,
                 editado_em = NOW()
             WHERE id = :id
               AND deleted_at IS NULL'
        );

        $stmt->execute(array(
            'status' => $status,
            'enviado_por' => $usuarioId,
            'editado_por' => $usuarioId,
            'id' => (int) $id,
        ));
    }

    public function resumoDestinatarios($avisoId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) AS total,
                    SUM(CASE WHEN visualizado_em IS NOT NULL THEN 1 ELSE 0 END) AS visualizados,
                    SUM(CASE WHEN ocultado_em IS NOT NULL THEN 1 ELSE 0 END) AS ocultados,
                    SUM(CASE WHEN status = "ativo" AND deleted_at IS NULL AND ocultado_em IS NULL THEN 1 ELSE 0 END) AS ativos
             FROM avisos_destinatarios
             WHERE aviso_id = :aviso_id
               AND deleted_at IS NULL'
        );

        $stmt->execute(array('aviso_id' => (int) $avisoId));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return array(
            'total' => !empty($row) ? (int) $row['total'] : 0,
            'visualizados' => !empty($row) ? (int) $row['visualizados'] : 0,
            'ocultados' => !empty($row) ? (int) $row['ocultados'] : 0,
            'ativos' => !empty($row) ? (int) $row['ativos'] : 0,
        );
    }
}
