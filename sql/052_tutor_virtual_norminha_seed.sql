-- Seeds iniciais do Tutor Virtual Norminha
-- Compatibilidade: MySQL 5.7

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

INSERT INTO tutor_falas (
    titulo,
    contexto,
    rota,
    curso_id,
    modulo_id,
    aula_id,
    texto,
    audio_url,
    estado_avatar,
    ativo
)
SELECT
    'Bem-vindo ao Desbloqueia Cursos',
    'home',
    '/',
    NULL,
    NULL,
    NULL,
    'Olá! Eu sou a Norminha, tutora virtual do Desbloqueia Cursos. Estou aqui para te ajudar a encontrar cursos rápidos, práticos e feitos para facilitar sua aprendizagem.',
    '/uploads/tutor-norminha/audio/home-boas-vindas.mp3',
    'speaking',
    1
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1
    FROM tutor_falas
    WHERE contexto = 'home'
      AND rota = '/'
      AND titulo = 'Bem-vindo ao Desbloqueia Cursos'
      AND audio_url = '/uploads/tutor-norminha/audio/home-boas-vindas.mp3'
);

INSERT INTO tutor_falas (
    titulo,
    contexto,
    rota,
    curso_id,
    modulo_id,
    aula_id,
    texto,
    audio_url,
    estado_avatar,
    ativo
)
SELECT
    'Como funciona',
    'institucional',
    '/como-funciona',
    NULL,
    NULL,
    NULL,
    'Aqui no Desbloqueia Cursos, você escolhe um curso, faz sua inscrição e acessa os conteúdos de forma simples. Avance no seu ritmo e conclua as etapas para finalizar sua formação.',
    '/uploads/tutor-norminha/audio/como-funciona.mp3',
    'speaking',
    1
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1
    FROM tutor_falas
    WHERE contexto = 'institucional'
      AND rota = '/como-funciona'
      AND titulo = 'Como funciona'
      AND audio_url = '/uploads/tutor-norminha/audio/como-funciona.mp3'
);

INSERT INTO tutor_falas (
    titulo,
    contexto,
    rota,
    curso_id,
    modulo_id,
    aula_id,
    texto,
    audio_url,
    estado_avatar,
    ativo
)
SELECT
    'Sua área de estudos',
    'area_aluno',
    '/aluno/cursos',
    NULL,
    NULL,
    NULL,
    'Bem-vindo à sua área de estudos. Escolha um curso, acesse os módulos disponíveis e continue de onde parou.',
    '/uploads/tutor-norminha/audio/area-aluno.mp3',
    'speaking',
    1
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1
    FROM tutor_falas
    WHERE contexto = 'area_aluno'
      AND rota = '/aluno/cursos'
      AND titulo = 'Sua área de estudos'
      AND audio_url = '/uploads/tutor-norminha/audio/area-aluno.mp3'
);

INSERT INTO tutor_falas (
    titulo,
    contexto,
    rota,
    curso_id,
    modulo_id,
    aula_id,
    texto,
    audio_url,
    estado_avatar,
    ativo
)
SELECT
    'Orientação sobre o curso',
    'curso',
    '/curso/*',
    NULL,
    NULL,
    NULL,
    'Antes de começar, leia as informações do curso com atenção. Veja a carga horária, o conteúdo disponível e siga as etapas para concluir sua formação.',
    '/uploads/tutor-norminha/audio/curso-orientacao.mp3',
    'speaking',
    1
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1
    FROM tutor_falas
    WHERE contexto = 'curso'
      AND rota = '/curso/*'
      AND titulo = 'Orientação sobre o curso'
      AND audio_url = '/uploads/tutor-norminha/audio/curso-orientacao.mp3'
);

INSERT INTO tutor_falas (
    titulo,
    contexto,
    rota,
    curso_id,
    modulo_id,
    aula_id,
    texto,
    audio_url,
    estado_avatar,
    ativo
)
SELECT
    'Orientação da aula',
    'aula',
    '/aluno/curso/*',
    NULL,
    NULL,
    NULL,
    'Nesta aula, leia o conteúdo com atenção e avance somente quando sentir que compreendeu os principais pontos.',
    '/uploads/tutor-norminha/audio/aula-orientacao.mp3',
    'speaking',
    1
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1
    FROM tutor_falas
    WHERE contexto = 'aula'
      AND rota = '/aluno/curso/*'
      AND titulo = 'Orientação da aula'
      AND audio_url = '/uploads/tutor-norminha/audio/aula-orientacao.mp3'
);

SET FOREIGN_KEY_CHECKS = 1;
