-- Polo Rainbow - refino incremental do catalogo
-- Compatibilidade: MySQL 5.7
-- Objetivo: adicionar campos operacionais sem reescrever a migration anterior.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE cursos_eventos
    ADD COLUMN usar_turmas TINYINT(1) NOT NULL DEFAULT 1 AFTER valor,
    ADD COLUMN permite_compra_lote TINYINT(1) NOT NULL DEFAULT 1 AFTER usar_turmas,
    ADD COLUMN permite_compra_terceiros TINYINT(1) NOT NULL DEFAULT 1 AFTER permite_compra_lote,
    ADD COLUMN certificado_previsto TINYINT(1) NOT NULL DEFAULT 1 AFTER permite_compra_terceiros;

ALTER TABLE turmas
    ADD COLUMN hora_inicio TIME NULL AFTER data_fim,
    ADD COLUMN hora_fim TIME NULL AFTER hora_inicio,
    ADD COLUMN inscricoes_abrem_em DATETIME NULL AFTER hora_fim,
    ADD COLUMN inscricoes_encerram_em DATETIME NULL AFTER inscricoes_abrem_em,
    ADD COLUMN local_nome VARCHAR(191) NULL AFTER inscricoes_encerram_em,
    ADD COLUMN local_endereco VARCHAR(255) NULL AFTER local_nome,
    ADD COLUMN link_transmissao VARCHAR(255) NULL AFTER local_endereco,
    ADD COLUMN link_gravacao VARCHAR(255) NULL AFTER link_transmissao,
    ADD COLUMN observacoes_publicas TEXT NULL AFTER link_gravacao,
    ADD COLUMN valor_override DECIMAL(10,2) NULL AFTER observacoes_publicas;

SET FOREIGN_KEY_CHECKS = 1;
