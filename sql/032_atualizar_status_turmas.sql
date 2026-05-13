-- Atualiza o status das turmas para padronizar exclusão como "excluida"
-- Compatibilidade: MySQL 5.7

SET NAMES utf8mb4;

UPDATE turmas
   SET status = 'excluida'
 WHERE status = 'cancelada';

ALTER TABLE turmas
    MODIFY status ENUM('planejada', 'aberta', 'encerrada', 'excluida') NOT NULL DEFAULT 'planejada';
