-- Desbloqueia Cursos - modelos de e-mail transacionais
-- Compatibilidade: MySQL 5.7

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS emails_modelos (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    evento VARCHAR(120) NOT NULL,
    template VARCHAR(120) NOT NULL,
    nome VARCHAR(160) NOT NULL,
    assunto VARCHAR(255) NOT NULL,
    corpo_html LONGTEXT NOT NULL,
    gatilho_descricao TEXT NULL,
    variaveis_json LONGTEXT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    editavel TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_emails_modelos_evento (evento),
    KEY idx_emails_modelos_template (template),
    KEY idx_emails_modelos_ativo (ativo),
    KEY idx_emails_modelos_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO emails_modelos
    (evento, template, nome, assunto, corpo_html, gatilho_descricao, variaveis_json, ativo, editavel, created_at, updated_at, deleted_at)
VALUES
    ('email.welcome', 'welcome', 'Boas-vindas', 'Bem-vindo ao {sistema.nome}', '', 'Enviado quando um novo usuário conclui o cadastro no sistema, a partir de AuthService::register.', '["{usuario.nome}","{usuario.email}","{sistema.nome}","{sistema.login_url}"]', 1, 1, NOW(), NOW(), NULL),
    ('email.password_reset', 'password_reset', 'Recuperação de senha', 'Recuperação de senha', '', 'Enviado quando o usuário solicita redefinição de senha.', '["{usuario.nome}","{reset_url}","{token}"]', 1, 1, NOW(), NOW(), NULL),
    ('email.pedido_criado', 'pedido_criado', 'Pedido criado', 'Pedido {pedido.codigo} criado', '', 'Enviado após a criação do pedido no checkout, em PedidoService.', '["{pedido.codigo}","{pedido.total}"]', 1, 1, NOW(), NOW(), NULL),
    ('email.comprovante_enviado', 'comprovante_enviado', 'Comprovante PIX enviado', 'Comprovante PIX enviado - {pedido.codigo}', '', 'Enviado quando o comprovante PIX é anexado ou reenviado.', '["{pedido.codigo}","{sistema.meus_cursos_url}"]', 1, 1, NOW(), NOW(), NULL),
    ('email.pedido_aprovado', 'pedido_aprovado', 'Pedido aprovado', 'Pedido aprovado - {pedido.codigo}', '', 'Enviado quando o pedido ou comprovante é aprovado.', '["{pedido.codigo}","{observacao}"]', 1, 1, NOW(), NOW(), NULL),
    ('email.pendencia', 'pendencia', 'Pedido ou inscrição com pendência', 'Pedido com pendência - {pedido.codigo}', '', 'Enviado quando o pedido, comprovante ou inscrição recebe status de pendência.', '["{pedido.codigo}","{observacao}"]', 1, 1, NOW(), NOW(), NULL),
    ('email.curso_proximo', 'curso_proximo', 'Curso próximo', 'Seu curso está próximo', '', 'Enviado quando as inscrições são geradas ou quando a inscrição muda para em_andamento.', '["{inscricao.participante_nome}","{inscricao.curso_nome}","{inscricao.turma_nome}"]', 1, 1, NOW(), NOW(), NULL),
    ('email.concluido', 'concluido', 'Curso concluído', 'Curso concluído', '', 'Enviado quando a inscrição muda para concluida ou concluida_sem_certificado.', '["{inscricao.participante_nome}","{inscricao.curso_nome}"]', 1, 1, NOW(), NOW(), NULL),
    ('email.certificado_disponivel', 'certificado_disponivel', 'Certificado disponível', 'Certificado disponível', '', 'Enviado quando a inscrição muda para certificado_emitido.', '["{inscricao.curso_nome}"]', 1, 1, NOW(), NOW(), NULL)
ON DUPLICATE KEY UPDATE
    id = id;
