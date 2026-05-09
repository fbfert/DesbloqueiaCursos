-- Polo Rainbow - perfil aluno no RBAC
-- Compatibilidade: MySQL 5.7

SET NAMES utf8mb4;

INSERT INTO perfis (nome, slug, descricao, status, sistema, created_at, updated_at, deleted_at)
SELECT
    'Aluno',
    'aluno',
    'Acesso do usuário cadastrado pelo frontend',
    'ativo',
    1,
    NOW(),
    NOW(),
    NULL
WHERE NOT EXISTS (
    SELECT 1 FROM perfis WHERE slug = 'aluno' AND deleted_at IS NULL
);

INSERT INTO usuario_perfis (usuario_id, perfil_id, created_at)
SELECT u.id, p.id, NOW()
FROM usuarios u
INNER JOIN perfis p ON p.slug = 'aluno' AND p.deleted_at IS NULL
LEFT JOIN usuario_perfis up ON up.usuario_id = u.id AND up.perfil_id = p.id
WHERE u.deleted_at IS NULL
  AND up.usuario_id IS NULL
  AND NOT EXISTS (
      SELECT 1
      FROM usuario_perfis up2
      INNER JOIN perfis p2 ON p2.id = up2.perfil_id
      WHERE up2.usuario_id = u.id
        AND p2.deleted_at IS NULL
        AND p2.slug IN ('superadmin', 'atendimento', 'financeiro', 'conteudo', 'marketing', 'professor')
  );
