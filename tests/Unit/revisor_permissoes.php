<?php

/**
 * Permissoes do perfil Revisor (spec 0002-perfil-revisor).
 *
 * Este teste e incomum: normalmente nao se testa configuracao. Aqui a
 * configuracao E a feature. A premissa do perfil Revisor e a AUSENCIA de
 * permissao de escrita em conteudo — um perfil que ganhe area_curso.gerenciar
 * por engano deixa de ser revisor e vira administrador, sem que nenhuma tela
 * mude de aparencia e sem que nenhum outro teste falhe.
 *
 * Por isso o teste afirma o conjunto EXATO de permissoes, e quebra tanto se
 * faltar quanto se sobrar.
 *
 * Execução: php tests/Unit/revisor_permissoes.php
 */

require_once __DIR__ . '/_bootstrap.php';

$pdo = testes_conectar_banco();

const PERMISSOES_ESPERADAS = array(
    'area_curso.revisor.comentar',
    'area_curso.revisor.ver',
);

function permissoesDoPerfil(PDO $pdo, $slug)
{
    $stmt = $pdo->prepare(
        'SELECT p.slug
         FROM perfil_permissoes pp
         INNER JOIN perfis pf ON pf.id = pp.perfil_id
         INNER JOIN permissoes p ON p.id = pp.permissao_id
         WHERE pf.slug = :slug AND pf.deleted_at IS NULL AND p.deleted_at IS NULL
         ORDER BY p.slug'
    );
    $stmt->execute(array('slug' => $slug));
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

describe('Perfil Revisor existe e está ativo');

it('o perfil está cadastrado, ativo e marcado como de sistema', function () use ($pdo) {
    $stmt = $pdo->prepare('SELECT * FROM perfis WHERE slug = ? AND deleted_at IS NULL');
    $stmt->execute(array('revisor'));
    $perfil = $stmt->fetch(PDO::FETCH_ASSOC);
    expect($perfil)->notToBeNull();
    expect($perfil['status'])->toBe('ativo');
    expect((int) $perfil['sistema'])->toBe(1);
});

it('as duas permissões do revisor existem', function () use ($pdo) {
    foreach (PERMISSOES_ESPERADAS as $slug) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM permissoes WHERE slug = ? AND deleted_at IS NULL');
        $stmt->execute(array($slug));
        expect((int) $stmt->fetchColumn())->toBe(1);
    }
});

describe('O conjunto de permissões é exatamente o esperado');

it('tem todas as permissões previstas', function () use ($pdo) {
    $atuais = permissoesDoPerfil($pdo, 'revisor');
    foreach (PERMISSOES_ESPERADAS as $slug) {
        expect($atuais)->toContain($slug);
    }
});

it('não tem NENHUMA permissão além das previstas', function () use ($pdo) {
    $atuais = permissoesDoPerfil($pdo, 'revisor');
    $sobrando = array_values(array_diff($atuais, PERMISSOES_ESPERADAS));
    if ($sobrando) {
        throw new RuntimeException(
            'O perfil Revisor ganhou permissões não previstas na spec: ' . implode(', ', $sobrando)
            . '. Se a mudança é intencional, atualize specs/0002-perfil-revisor/ e este teste.'
        );
    }
    expect(count($atuais))->toBe(count(PERMISSOES_ESPERADAS));
});

it('não tem nenhuma permissão de gestão de conteúdo', function () use ($pdo) {
    $proibidas = array(
        'area_curso.gerenciar',
        'area_curso.professor.gerenciar',
        'area_curso.ver',
        'area_curso.professor.ver',
    );
    $atuais = permissoesDoPerfil($pdo, 'revisor');
    foreach ($proibidas as $slug) {
        if (in_array($slug, $atuais, true)) {
            throw new RuntimeException("O perfil Revisor não pode ter {$slug}: é a permissão que a feature existe para negar.");
        }
    }
    expect(true)->toBeTrue();
});

it('nenhuma permissão do revisor termina em .gerenciar fora do seu próprio módulo', function () use ($pdo) {
    foreach (permissoesDoPerfil($pdo, 'revisor') as $slug) {
        if (substr($slug, -10) === '.gerenciar') {
            throw new RuntimeException("Permissão de gestão concedida ao Revisor: {$slug}");
        }
    }
    expect(true)->toBeTrue();
});

describe('O vínculo de revisor é reconhecido pelo banco');

it('curso_pessoas_vinculadas aceita o tipo revisor', function () use ($pdo) {
    $stmt = $pdo->query("SHOW COLUMNS FROM curso_pessoas_vinculadas LIKE 'tipo_pessoa'");
    $coluna = $stmt->fetch(PDO::FETCH_ASSOC);
    expect($coluna['Type'])->toContain("'revisor'");
});

it('a tabela de comentários existe com as colunas da spec', function () use ($pdo) {
    $colunas = $pdo->query('SHOW COLUMNS FROM revisao_comentarios')->fetchAll(PDO::FETCH_COLUMN);
    foreach (array('curso_evento_id', 'alvo_tipo', 'alvo_id', 'trecho', 'comentario',
                   'severidade', 'status', 'resposta', 'autor_id', 'triado_por', 'triado_em') as $coluna) {
        expect($colunas)->toContain($coluna);
    }
});

exit(testes_resumo());
