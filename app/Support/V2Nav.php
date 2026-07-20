<?php

namespace App\Support;

/**
 * Fonte única dos links de navegação do ambiente V2 (Fase 2.13).
 *
 * Centraliza as rotas internas V2 para cabeçalho, rodapé e navegação inferior,
 * evitando strings espalhadas e vazamentos para o visual V1. Todos os destinos
 * são caminhos internos fixos dentro de `/v2/`. `areaHref` depende do papel do
 * usuário (admin/professor mantêm suas áreas de backend) e é resolvido pelo
 * chamador; aqui só entra como parâmetro com fallback V2 para o aluno.
 */
class V2Nav
{
    const HOME = '/v2/';
    const CATALOGO = '/v2/catalogo/';
    const CATEGORIAS = '/v2/categorias/';
    const CERTIFICADOS = '/v2/certificados/validar';
    const LOGIN = '/v2/login';
    const CADASTRO = '/v2/cadastro';
    const ALUNO = '/v2/aluno/';
    const PEDIDOS = '/v2/aluno/?aba=pedidos';
    const PERFIL = '/v2/aluno/?aba=perfil';
    // Fase 2.13B — páginas institucionais canônicas V2 (conteúdo real vindo do
    // backend/tabela `paginas`). As rotas casam com o `slug` cadastrado, para
    // que uma edição no admin apareça na V2 sem mudança de código.
    const QUEM_SOMOS = '/v2/quem-somos';
    const COMO_FUNCIONA_SALA = '/v2/como-funciona-a-sala-virtual';
    const ONDE_ESTAMOS = '/v2/onde-estamos';
    const TERMOS = '/v2/termos-de-uso';
    const PRIVACIDADE = '/v2/politica-de-privacidade';
    const REMOVA_ME = '/v2/remova-me';
    // Aliases semânticos usados pela navegação atual (mapeados às rotas reais).
    const SOBRE = self::QUEM_SOMOS;
    const CONTATO = self::ONDE_ESTAMOS;
    const COMO_FUNCIONA = self::COMO_FUNCIONA_SALA;

    /**
     * Conjunto canônico de hrefs V2 para as partials de navegação. Sobrescreve
     * quaisquer valores herdados que apontariam para o V1, exceto `areaHref`
     * (papel-dependente) e `homeHref`, preservados quando já informados.
     */
    public static function links($areaHref = null, $loggedIn = false)
    {
        $area = is_string($areaHref) && $areaHref !== '' ? $areaHref : self::ALUNO;

        return array(
            'homeHref' => self::HOME,
            'catalogoHref' => self::CATALOGO,
            // Ambas as grafias usadas pelas views (categoriasHref/categoriesHref).
            'categoriasHref' => self::CATEGORIAS,
            'categoriesHref' => self::CATEGORIAS,
            'certificadosHref' => self::CERTIFICADOS,
            'loginHref' => self::LOGIN,
            'registerHref' => self::CADASTRO,
            'sobreHref' => self::SOBRE,
            'contatoHref' => self::CONTATO,
            'comoFuncionaHref' => self::COMO_FUNCIONA,
            // Institucionais canônicas (Fase 2.13B).
            'quemSomosHref' => self::QUEM_SOMOS,
            'comoFuncionaSalaHref' => self::COMO_FUNCIONA_SALA,
            'ondeEstamosHref' => self::ONDE_ESTAMOS,
            'termosHref' => self::TERMOS,
            'privacidadeHref' => self::PRIVACIDADE,
            'removaMeHref' => self::REMOVA_ME,
            'pedidosHref' => self::PEDIDOS,
            'contaHref' => self::PERFIL,
            'areaHref' => $area,
            'loggedIn' => (bool) $loggedIn,
        );
    }
}
