-- 079 — Limite de uso da Norminha publica
--
-- O atendimento a visitante nao tem sessao, e por isso nao tem a quem
-- responsabilizar por excesso. Sem freio, um robo bate no endpoint a vontade.
--
-- O IP NAO FICA GRAVADO EM CLARO
--
-- A coluna guarda um HASH do IP com a APP_KEY como sal. Isso basta para contar
-- quantas mensagens vieram da mesma origem e nao serve para identificar
-- ninguem: sem a APP_KEY, que nao esta no banco, um dump desta tabela nao diz
-- de quem sao as linhas.
--
-- A escolha e deliberada. Endereco de IP e dado pessoal, e este projeto acabou
-- de aprender o que custa guardar dado pessoal sem necessidade.
--
-- NENHUMA MENSAGEM DO VISITANTE E GUARDADA, aqui nem em lugar nenhum. Quem nao
-- tem conta nao consentiu com nada; guardar o que essa pessoa digita criaria um
-- acervo sem base legal e sem titular identificavel para exercer direito algum.
-- Esta tabela tem contadores, e so.

CREATE TABLE IF NOT EXISTS norminha_uso_publico (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ip_hash CHAR(64) NOT NULL COMMENT 'sha256 do IP com APP_KEY como sal; nunca o IP em claro',
    dia DATE NOT NULL,
    janela_inicio DATETIME NOT NULL,
    mensagens_janela INT NOT NULL DEFAULT 0,
    mensagens_dia INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_norminha_uso_publico (ip_hash, dia),
    KEY idx_norminha_uso_publico_dia (dia)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
