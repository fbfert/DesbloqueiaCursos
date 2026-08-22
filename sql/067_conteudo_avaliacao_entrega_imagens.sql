-- Desbloqueia Cursos - Anexos de imagem na entrega de avaliacao textual
-- Compatibilidade: MySQL 5.7
-- Todas as operacoes sao idempotentes (CREATE TABLE IF NOT EXISTS)

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Ate 5 imagens por entrega (limite aplicado em PHP, nao em SQL).
CREATE TABLE IF NOT EXISTS conteudo_avaliacoes_entregas_imagens (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    entrega_id     BIGINT UNSIGNED NOT NULL,
    nome_original  VARCHAR(255) NULL,
    nome_arquivo   VARCHAR(255) NULL,
    caminho        VARCHAR(500) NOT NULL,
    mime_type      VARCHAR(190) NULL,
    extensao       VARCHAR(20) NULL,
    tamanho_bytes  BIGINT UNSIGNED NULL,
    ordem          INT NOT NULL DEFAULT 0,
    created_at     DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_conteudo_avaliacoes_entregas_imagens_entrega (entrega_id),
    CONSTRAINT fk_conteudo_avaliacoes_entregas_imagens_entrega
        FOREIGN KEY (entrega_id) REFERENCES conteudo_avaliacoes_entregas (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
