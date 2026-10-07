-- 080 — Acentuação dos perfis e permissões semeados
--
-- Os nomes e descrições de perfis e permissões criados pelas migrações 002 a
-- 013 foram gravados sem acento ("Gestao", "Ver area do professor") e aparecem
-- assim nas telas de controle de acesso do admin.
--
-- Cada UPDATE só altera a linha cujo texto AINDA É exatamente o da semente
-- (comparação BINARY, sensível a acento): o que um administrador já editou
-- fica como está. Slugs e chaves não mudam. Pode rodar mais de uma vez.
--
-- Compatível com MySQL 5.7 / MariaDB 10.5.

UPDATE perfis SET descricao = 'Gestão financeira e conciliação', updated_at = NOW() WHERE slug = 'financeiro' AND BINARY descricao = 'Gestao financeira e conciliacao';
UPDATE perfis SET nome = 'Conteúdo', updated_at = NOW() WHERE slug = 'conteudo' AND BINARY nome = 'Conteudo';
UPDATE perfis SET descricao = 'Gestão de cursos e conteúdos', updated_at = NOW() WHERE slug = 'conteudo' AND BINARY descricao = 'Gestao de cursos e conteudos';
UPDATE perfis SET descricao = 'Operação de marketing e comunicação', updated_at = NOW() WHERE slug = 'marketing' AND BINARY descricao = 'Operacao de marketing e comunicacao';
UPDATE permissoes SET descricao = 'Atualizar vinculações de perfis', updated_at = NOW() WHERE slug = 'rbac.perfis.gerenciar' AND BINARY descricao = 'Atualizar vinculacoes de perfis';
UPDATE permissoes SET nome = 'Ver permissões', updated_at = NOW() WHERE slug = 'rbac.permissoes.ver' AND BINARY nome = 'Ver permissoes';
UPDATE permissoes SET descricao = 'Listar permissões do sistema', updated_at = NOW() WHERE slug = 'rbac.permissoes.ver' AND BINARY descricao = 'Listar permissoes do sistema';
UPDATE permissoes SET nome = 'Gerenciar permissões', updated_at = NOW() WHERE slug = 'rbac.permissoes.gerenciar' AND BINARY nome = 'Gerenciar permissoes';
UPDATE permissoes SET descricao = 'Atualizar vinculações de permissões', updated_at = NOW() WHERE slug = 'rbac.permissoes.gerenciar' AND BINARY descricao = 'Atualizar vinculacoes de permissoes';
UPDATE permissoes SET nome = 'Ver usuários', updated_at = NOW() WHERE slug = 'usuarios.ver' AND BINARY nome = 'Ver usuarios';
UPDATE permissoes SET descricao = 'Consultar usuários do sistema', updated_at = NOW() WHERE slug = 'usuarios.ver' AND BINARY descricao = 'Consultar usuarios do sistema';
UPDATE permissoes SET nome = 'Gerenciar usuários', updated_at = NOW() WHERE slug = 'usuarios.gerenciar' AND BINARY nome = 'Gerenciar usuarios';
UPDATE permissoes SET descricao = 'Administrar usuários do sistema', updated_at = NOW() WHERE slug = 'usuarios.gerenciar' AND BINARY descricao = 'Administrar usuarios do sistema';
UPDATE permissoes SET nome = 'Ver conteúdo', updated_at = NOW() WHERE slug = 'conteudo.ver' AND BINARY nome = 'Ver conteudo';
UPDATE permissoes SET descricao = 'Acessar área de conteúdo', updated_at = NOW() WHERE slug = 'conteudo.ver' AND BINARY descricao = 'Acessar area de conteudo';
UPDATE permissoes SET nome = 'Gerenciar conteúdo', updated_at = NOW() WHERE slug = 'conteudo.gerenciar' AND BINARY nome = 'Gerenciar conteudo';
UPDATE permissoes SET descricao = 'Administrar conteúdos', updated_at = NOW() WHERE slug = 'conteudo.gerenciar' AND BINARY descricao = 'Administrar conteudos';
UPDATE permissoes SET descricao = 'Visualizar apurações, repasses e pagamentos', updated_at = NOW() WHERE slug = 'financeiro.ver' AND BINARY descricao = 'Visualizar apuracoes, repasses e pagamentos';
UPDATE permissoes SET descricao = 'Administrar apurações, repasses e pagamentos', updated_at = NOW() WHERE slug = 'financeiro.gerenciar' AND BINARY descricao = 'Administrar apuracoes, repasses e pagamentos';
UPDATE permissoes SET nome = 'Ver área do professor', updated_at = NOW() WHERE slug = 'professor.ver' AND BINARY nome = 'Ver area do professor';
UPDATE permissoes SET descricao = 'Acessar área do professor', updated_at = NOW() WHERE slug = 'professor.ver' AND BINARY descricao = 'Acessar area do professor';
UPDATE permissoes SET nome = 'Gerenciar área do professor', updated_at = NOW() WHERE slug = 'professor.gerenciar' AND BINARY nome = 'Gerenciar area do professor';
UPDATE permissoes SET descricao = 'Administrar área do professor', updated_at = NOW() WHERE slug = 'professor.gerenciar' AND BINARY descricao = 'Administrar area do professor';
UPDATE permissoes SET nome = 'Ver catálogo', updated_at = NOW() WHERE slug = 'catalogo.ver' AND BINARY nome = 'Ver catalogo';
UPDATE permissoes SET descricao = 'Acessar listagem do catálogo', updated_at = NOW() WHERE slug = 'catalogo.ver' AND BINARY descricao = 'Acessar listagem do catalogo';
UPDATE permissoes SET nome = 'Gerenciar catálogo', updated_at = NOW() WHERE slug = 'catalogo.gerenciar' AND BINARY nome = 'Gerenciar catalogo';
UPDATE permissoes SET descricao = 'Administrar catálogo de cursos e eventos', updated_at = NOW() WHERE slug = 'catalogo.gerenciar' AND BINARY descricao = 'Administrar catalogo de cursos e eventos';
UPDATE permissoes SET nome = 'Ver área do professor', updated_at = NOW() WHERE slug = 'catalogo.professor.ver' AND BINARY nome = 'Ver area do professor';
UPDATE permissoes SET descricao = 'Acessar área restrita do professor', updated_at = NOW() WHERE slug = 'catalogo.professor.ver' AND BINARY descricao = 'Acessar area restrita do professor';
UPDATE permissoes SET descricao = 'Consultar resumo de usos e aplicações', updated_at = NOW() WHERE slug = 'cupons.usos.ver' AND BINARY descricao = 'Consultar resumo de usos e aplicacoes';
UPDATE permissoes SET nome = 'Ver e-mails', updated_at = NOW() WHERE slug = 'emails.ver' AND BINARY nome = 'Ver emails';
UPDATE permissoes SET descricao = 'Consultar configurações e fila de e-mails', updated_at = NOW() WHERE slug = 'emails.ver' AND BINARY descricao = 'Consultar configuracoes e fila de emails';
UPDATE permissoes SET nome = 'Gerenciar e-mails', updated_at = NOW() WHERE slug = 'emails.gerenciar' AND BINARY nome = 'Gerenciar emails';
UPDATE permissoes SET descricao = 'Atualizar configuração SMTP e fila de e-mails', updated_at = NOW() WHERE slug = 'emails.gerenciar' AND BINARY descricao = 'Atualizar configuracao SMTP e fila de emails';
UPDATE permissoes SET nome = 'Ver área do curso', updated_at = NOW() WHERE slug = 'area_curso.ver' AND BINARY nome = 'Ver area do curso';
UPDATE permissoes SET descricao = 'Visualizar a área interna do curso', updated_at = NOW() WHERE slug = 'area_curso.ver' AND BINARY descricao = 'Visualizar a area interna do curso';
UPDATE permissoes SET nome = 'Gerenciar área do curso', updated_at = NOW() WHERE slug = 'area_curso.gerenciar' AND BINARY nome = 'Gerenciar area do curso';
UPDATE permissoes SET descricao = 'Administrar instruções, módulos, aulas, materiais e links', updated_at = NOW() WHERE slug = 'area_curso.gerenciar' AND BINARY descricao = 'Administrar instrucoes, modulos, aulas, materiais e links';
UPDATE permissoes SET nome = 'Ver área do professor', updated_at = NOW() WHERE slug = 'area_curso.professor.ver' AND BINARY nome = 'Ver area do professor';
UPDATE permissoes SET descricao = 'Acessar a área interna com escopo de professor', updated_at = NOW() WHERE slug = 'area_curso.professor.ver' AND BINARY descricao = 'Acessar a area interna com escopo de professor';
UPDATE permissoes SET nome = 'Gerenciar área do professor', updated_at = NOW() WHERE slug = 'area_curso.professor.gerenciar' AND BINARY nome = 'Gerenciar area do professor';
UPDATE permissoes SET descricao = 'Administrar conteúdo da área interna como professor', updated_at = NOW() WHERE slug = 'area_curso.professor.gerenciar' AND BINARY descricao = 'Administrar conteudo da area interna como professor';
UPDATE permissoes SET nome = 'Ver área acadêmica', updated_at = NOW() WHERE slug = 'academico.ver' AND BINARY nome = 'Ver area academica';
UPDATE permissoes SET descricao = 'Visualizar presença, avaliação e aptidão', updated_at = NOW() WHERE slug = 'academico.ver' AND BINARY descricao = 'Visualizar presenca, avaliacao e aptidao';
UPDATE permissoes SET nome = 'Gerenciar área acadêmica', updated_at = NOW() WHERE slug = 'academico.gerenciar' AND BINARY nome = 'Gerenciar area academica';
UPDATE permissoes SET descricao = 'Gerenciar presença, avaliação e aptidão', updated_at = NOW() WHERE slug = 'academico.gerenciar' AND BINARY descricao = 'Gerenciar presenca, avaliacao e aptidao';
UPDATE permissoes SET nome = 'Ver configurações globais', updated_at = NOW() WHERE slug = 'configuracoes_globais.ver' AND BINARY nome = 'Ver configuracoes globais';
UPDATE permissoes SET descricao = 'Visualizar configurações institucionais e operacionais', updated_at = NOW() WHERE slug = 'configuracoes_globais.ver' AND BINARY descricao = 'Visualizar configuracoes institucionais e operacionais';
UPDATE permissoes SET nome = 'Gerenciar configurações globais', updated_at = NOW() WHERE slug = 'configuracoes_globais.gerenciar' AND BINARY nome = 'Gerenciar configuracoes globais';
UPDATE permissoes SET descricao = 'Editar configurações institucionais e operacionais', updated_at = NOW() WHERE slug = 'configuracoes_globais.gerenciar' AND BINARY descricao = 'Editar configuracoes institucionais e operacionais';
