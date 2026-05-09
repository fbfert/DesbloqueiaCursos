-- Polo Rainbow - dados complementares do usuário
-- Compatibilidade: MySQL 5.7

SET NAMES utf8mb4;

ALTER TABLE usuarios
    ADD COLUMN cidade VARCHAR(120) NULL AFTER telefone,
    ADD COLUMN estado CHAR(2) NULL AFTER cidade,
    ADD KEY idx_usuarios_cidade (cidade),
    ADD KEY idx_usuarios_estado (estado);
