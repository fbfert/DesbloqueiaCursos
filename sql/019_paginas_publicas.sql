-- Polo Rainbow - páginas institucionais dinâmicas
-- Compatibilidade: MySQL 5.7

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS paginas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    titulo VARCHAR(191) NOT NULL,
    slug VARCHAR(191) NOT NULL,
    rota VARCHAR(191) NOT NULL,
    resumo VARCHAR(500) NULL,
    conteudo_html MEDIUMTEXT NULL,
    status ENUM('rascunho', 'publicada', 'inativa') NOT NULL DEFAULT 'rascunho',
    ordem INT NOT NULL DEFAULT 0,
    publicada_em DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_paginas_slug (slug),
    UNIQUE KEY uk_paginas_rota (rota),
    KEY idx_paginas_status (status),
    KEY idx_paginas_ordem (ordem),
    KEY idx_paginas_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO paginas
    (titulo, slug, rota, resumo, conteudo_html, status, ordem, publicada_em, created_at, updated_at, deleted_at)
SELECT
    'Termos de Uso',
    'termos-de-uso',
    '/termos-de-uso',
    'Regras de uso da plataforma Polo Rainbow.',
    '<h1>Termos de Uso</h1><p>Ao acessar e utilizar esta plataforma, você concorda com estes termos.</p><h2>Uso da plataforma</h2><p>O usuário deve fornecer informações verdadeiras e manter seus dados atualizados.</p><h2>Responsabilidades</h2><p>É responsabilidade do usuário proteger suas credenciais de acesso e utilizar o portal de forma ética.</p><h2>Disposições finais</h2><p>Estes termos podem ser atualizados periodicamente para atender exigências legais e operacionais.</p>',
    'publicada',
    1,
    NOW(),
    NOW(),
    NOW(),
    NULL
WHERE NOT EXISTS (
    SELECT 1 FROM paginas WHERE slug = 'termos-de-uso' AND deleted_at IS NULL
);

INSERT INTO paginas
    (titulo, slug, rota, resumo, conteudo_html, status, ordem, publicada_em, created_at, updated_at, deleted_at)
SELECT
    'Política de Privacidade',
    'politica-de-privacidade',
    '/politica-de-privacidade',
    'Diretrizes sobre tratamento de dados pessoais.',
    '<h1>Política de Privacidade</h1><p>Esta política explica como tratamos dados pessoais no Polo Rainbow.</p><h2>Coleta de dados</h2><p>Coletamos apenas dados necessários para operação, segurança e cumprimento de obrigações legais.</p><h2>Uso de dados</h2><p>Os dados são utilizados para autenticação, gestão acadêmica e comunicação com os usuários.</p><h2>Direitos do titular</h2><p>O titular pode solicitar atualização, correção e informações sobre seus dados pelos canais de atendimento.</p>',
    'publicada',
    2,
    NOW(),
    NOW(),
    NOW(),
    NULL
WHERE NOT EXISTS (
    SELECT 1 FROM paginas WHERE slug = 'politica-de-privacidade' AND deleted_at IS NULL
);
