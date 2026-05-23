-- Polo Rainbow - trava de inscrição única por turma
-- Compatibilidade: MySQL 5.7

SET NAMES utf8mb4;

ALTER TABLE inscricoes
    ADD UNIQUE KEY uk_inscricoes_usuario_turma (usuario_id, turma_id);
