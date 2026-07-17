<?php

namespace App\Support;

/**
 * Inventário central e reutilizável dos placeholders suportados nos modelos de
 * e-mail. Concentra: nome lógico, origem, eventos válidos, fallback, formato,
 * aliases equivalentes (chave simples x chave dupla) e placeholders obrigatórios
 * por evento (que bloqueiam o disparo se não resolvidos).
 *
 * A resolução em si (montagem de contexto e substituição) permanece no
 * EmailModeloService; esta classe é a fonte única de verdade dos metadados.
 */
class EmailPlaceholders
{
    /**
     * Grupos de chaves equivalentes (mesmo dado, sintaxes diferentes).
     * Ao resolver, o primeiro valor não vazio do grupo preenche os demais,
     * garantindo retrocompatibilidade entre {chave.simples} e {{chave_dupla}}.
     *
     * @return array<int,array<int,string>>
     */
    public static function aliasGroups()
    {
        return array(
            array('aluno_nome', 'usuario.nome'),
            array('aluno_email', 'usuario.email'),
            array('pedido_codigo', 'pedido.codigo'),
            array('valor_total', 'pedido.total'),
            array('curso_nome', 'inscricao.curso_nome'),
            array('turma_nome', 'inscricao.turma_nome'),
            array('participante_nome', 'inscricao.participante_nome'),
            array('cupom_codigo', 'pedido.cupom_codigo'),
        );
    }

    /**
     * Inventário documentado dos placeholders exigidos.
     * Cada item: key (chave lógica no mapa flat), nome, origem, eventos (globs
     * de evento ou ['*']), fallback, formato, aliases (tokens equivalentes).
     *
     * @return array<int,array<string,mixed>>
     */
    public static function inventory()
    {
        return array(
            array('token' => '{usuario.nome}', 'key' => 'usuario.nome', 'nome' => 'Nome do destinatário/aluno', 'origem' => 'usuário destinatário, aluno, participante ou pagador', 'eventos' => array('*'), 'fallback' => 'Aluno(a)', 'formato' => 'texto', 'aliases' => array('{{aluno_nome}}')),
            array('token' => '{usuario.email}', 'key' => 'usuario.email', 'nome' => 'E-mail do destinatário', 'origem' => 'e-mail do usuário/aluno destinatário', 'eventos' => array('*'), 'fallback' => '', 'formato' => 'e-mail', 'aliases' => array('{{aluno_email}}')),
            array('token' => '{sistema.nome}', 'key' => 'sistema.nome', 'nome' => 'Nome institucional', 'origem' => 'configuração global (nome fantasia)', 'eventos' => array('*'), 'fallback' => 'Desbloqueia Cursos', 'formato' => 'texto', 'aliases' => array()),
            array('token' => '{pedido.codigo}', 'key' => 'pedido.codigo', 'nome' => 'Código do pedido', 'origem' => 'código funcional do pedido', 'eventos' => array('email.pedido_*', 'email.comprovante_*', 'email.pendencia', 'pedido_recuperacao_*'), 'fallback' => '', 'formato' => 'texto', 'aliases' => array('{{pedido_codigo}}')),
            array('token' => '{pedido.total}', 'key' => 'pedido.total', 'nome' => 'Total do pedido', 'origem' => 'valor total do pedido', 'eventos' => array('email.pedido_*', 'email.comprovante_*', 'email.pendencia', 'pedido_recuperacao_*'), 'fallback' => '', 'formato' => 'moeda (R$ 1.250,00)', 'aliases' => array('{{valor_total}}')),
            array('token' => '{reset_url}', 'key' => 'reset_url', 'nome' => 'URL de redefinição de senha', 'origem' => 'token seguro de redefinição', 'eventos' => array('email.password_reset'), 'fallback' => '', 'formato' => 'URL absoluta', 'aliases' => array()),
            array('token' => '{observacao}', 'key' => 'observacao', 'nome' => 'Observação contextual', 'origem' => 'observação do envio', 'eventos' => array('email.pendencia', 'email.pedido_aprovado', 'email.pedido_excluido_inatividade'), 'fallback' => '', 'formato' => 'texto', 'aliases' => array()),
            array('token' => '{inscricao.participante_nome}', 'key' => 'inscricao.participante_nome', 'nome' => 'Participante da inscrição', 'origem' => 'participante vinculado à inscrição', 'eventos' => array('email.curso_proximo', 'email.concluido', 'email.certificado_disponivel'), 'fallback' => '', 'formato' => 'texto', 'aliases' => array('{participante_nome}')),
            array('token' => '{inscricao.curso_nome}', 'key' => 'inscricao.curso_nome', 'nome' => 'Curso da inscrição', 'origem' => 'curso da inscrição', 'eventos' => array('email.curso_proximo', 'email.concluido', 'email.certificado_disponivel'), 'fallback' => 'seu curso', 'formato' => 'texto', 'aliases' => array('{{curso_nome}}')),
            array('token' => '{inscricao.turma_nome}', 'key' => 'inscricao.turma_nome', 'nome' => 'Turma da inscrição', 'origem' => 'turma da inscrição (opcional em curso sob demanda)', 'eventos' => array('email.curso_proximo', 'email.concluido', 'email.certificado_disponivel'), 'fallback' => '', 'formato' => 'texto', 'aliases' => array('{{turma_nome}}')),
            array('token' => '{certificado_url_download}', 'key' => 'certificado_url_download', 'nome' => 'URL de download do certificado', 'origem' => 'certificado emitido (código público)', 'eventos' => array('email.certificado_disponivel'), 'fallback' => '', 'formato' => 'URL absoluta', 'aliases' => array()),
            array('token' => '{{pedido_codigo}}', 'key' => 'pedido_codigo', 'nome' => 'Código do pedido', 'origem' => 'código funcional do pedido', 'eventos' => array('pedido_recuperacao_*', 'email.pedido_*'), 'fallback' => '', 'formato' => 'texto', 'aliases' => array('{pedido.codigo}')),
            array('token' => '{{curso_nome}}', 'key' => 'curso_nome', 'nome' => 'Nome do curso', 'origem' => 'curso do pedido/inscrição', 'eventos' => array('pedido_recuperacao_*', 'email.certificado_disponivel', 'email.concluido', 'email.curso_proximo'), 'fallback' => 'seu curso', 'formato' => 'texto', 'aliases' => array('{inscricao.curso_nome}')),
            array('token' => '{{valor_total}}', 'key' => 'valor_total', 'nome' => 'Total do pedido', 'origem' => 'total do pedido', 'eventos' => array('pedido_recuperacao_*', 'email.pedido_*'), 'fallback' => 'R$ 0,00', 'formato' => 'moeda (R$ 1.250,00)', 'aliases' => array('{pedido.total}')),
            array('token' => '{{valor_pago}}', 'key' => 'valor_pago', 'nome' => 'Valor pago', 'origem' => 'valor efetivamente pago', 'eventos' => array('pedido_recuperacao_*', 'email.pedido_*'), 'fallback' => 'R$ 0,00', 'formato' => 'moeda (R$ 1.250,00)', 'aliases' => array()),
            array('token' => '{{valor_pendente}}', 'key' => 'valor_pendente', 'nome' => 'Saldo pendente', 'origem' => 'saldo pendente do pedido', 'eventos' => array('pedido_recuperacao_*', 'email.pedido_*'), 'fallback' => 'R$ 0,00', 'formato' => 'moeda (R$ 1.250,00)', 'aliases' => array()),
            array('token' => '{{cupom_codigo}}', 'key' => 'cupom_codigo', 'nome' => 'Código do cupom', 'origem' => 'cupom aplicado no pedido', 'eventos' => array('pedido_recuperacao_com_cupom', 'email.pedido_*'), 'fallback' => '', 'formato' => 'texto', 'aliases' => array()),
            array('token' => '{{link_pedido}}', 'key' => 'link_pedido', 'nome' => 'URL do pedido', 'origem' => 'resumo do checkout do pedido', 'eventos' => array('pedido_recuperacao_*'), 'fallback' => '', 'formato' => 'URL absoluta', 'aliases' => array()),
            array('token' => '{{link_pagamento}}', 'key' => 'link_pagamento', 'nome' => 'URL de pagamento', 'origem' => 'link de pagamento/retomada do checkout', 'eventos' => array('pedido_recuperacao_*'), 'fallback' => '', 'formato' => 'URL absoluta', 'aliases' => array()),
            array('token' => '{{link_descadastro_recuperacao}}', 'key' => 'link_descadastro_recuperacao', 'nome' => 'URL de descadastro de lembretes', 'origem' => 'token de descadastro de recuperação', 'eventos' => array('pedido_recuperacao_*'), 'fallback' => '', 'formato' => 'URL absoluta', 'aliases' => array()),
            array('token' => '{{aluno_nome}}', 'key' => 'aluno_nome', 'nome' => 'Nome do aluno/participante', 'origem' => 'aluno ou participante', 'eventos' => array('*'), 'fallback' => 'Aluno(a)', 'formato' => 'texto', 'aliases' => array('{usuario.nome}')),
            array('token' => '{{aluno_email}}', 'key' => 'aluno_email', 'nome' => 'E-mail do aluno/participante', 'origem' => 'aluno ou participante', 'eventos' => array('*'), 'fallback' => '', 'formato' => 'e-mail', 'aliases' => array('{usuario.email}')),
        );
    }

    /**
     * Placeholders obrigatórios por evento: se presentes no conteúdo e não
     * resolvidos, o disparo deve ser bloqueado. Para recuperação, exige-se ao
     * menos um entre link_pedido e link_pagamento (tratado no serviço).
     *
     * @return array<int,string> chaves flat obrigatórias
     */
    public static function requiredKeysForEvent($evento)
    {
        $evento = (string) $evento;

        if ($evento === 'email.password_reset') {
            return array('reset_url');
        }

        if ($evento === 'email.certificado_disponivel') {
            return array('certificado_url_download');
        }

        return array();
    }

    /** Ao menos uma das chaves precisa resolver (ex.: recuperação de pedido). */
    public static function requiredAnyForEvent($evento)
    {
        $evento = (string) $evento;
        if (strpos($evento, 'pedido_recuperacao_') === 0) {
            return array('link_pedido', 'link_pagamento');
        }

        return array();
    }

    /** Namespaces cujas subchaves são consideradas conhecidas (não "desconhecidas"). */
    public static function knownNamespaces()
    {
        return array('usuario', 'sistema', 'pedido', 'inscricao', 'cursos', 'certificado');
    }

    /**
     * Chaves flat de nível superior aceitas além do inventário (dados auxiliares
     * já usados pelos fluxos), para não gerar falso "placeholder desconhecido".
     */
    public static function knownExtraKeys()
    {
        return array(
            'token', 'reset_url', 'observacao', 'certificado_url_download',
            'data_pedido', 'data_expiracao', 'whatsapp_atendimento',
            'valor_nao_pago', 'admin_pedido_url', 'admin_financeiro_url',
            'link_pedido', 'link_pagamento', 'link_descadastro_recuperacao',
            'cupom_codigo', 'valor_total', 'valor_pago', 'valor_pendente',
            'pedido_codigo', 'curso_nome', 'turma_nome', 'participante_nome',
            'aluno_nome', 'aluno_email',
            // Chaves usadas por notificações administrativas (comprovante PIX ao financeiro).
            'pedido_valor', 'pedido_status', 'comprovante_id', 'comprovante_status',
            'comprovante_data_envio', 'comprovante_arquivo_nome', 'comprovante_arquivo_url',
            'turma_id', 'url_site', 'data_atual', 'nome_plataforma',
        );
    }

    /** Verifica se um evento casa com um glob simples (com sufixo "*"). */
    public static function eventMatches($evento, $glob)
    {
        $evento = (string) $evento;
        $glob = (string) $glob;
        if ($glob === '*' || $glob === $evento) {
            return true;
        }
        if (substr($glob, -1) === '*') {
            return strpos($evento, substr($glob, 0, -1)) === 0;
        }

        return false;
    }

    /** Inventário aplicável a um evento específico (para a tela de edição). */
    public static function forEvent($evento)
    {
        $result = array();
        foreach (self::inventory() as $item) {
            foreach ($item['eventos'] as $glob) {
                if (self::eventMatches($evento, $glob)) {
                    $result[] = $item;
                    break;
                }
            }
        }

        return $result;
    }

    /** true se a chave flat é reconhecida (inventário, alias, extra ou namespace). */
    public static function isKnownKey($key)
    {
        $key = (string) $key;

        foreach (self::inventory() as $item) {
            if ($item['key'] === $key) {
                return true;
            }
        }

        if (in_array($key, self::knownExtraKeys(), true)) {
            return true;
        }

        $ns = strpos($key, '.') !== false ? substr($key, 0, strpos($key, '.')) : '';
        if ($ns !== '' && in_array($ns, self::knownNamespaces(), true)) {
            return true;
        }

        return false;
    }

    /**
     * Detecta problemas ESTRUTURAIS de placeholders (que devem bloquear o salvamento):
     * placeholder partido por tag HTML, chaves desbalanceadas e placeholder vazio.
     * Não bloqueia por placeholder "desconhecido" (isso é apenas aviso na UI) nem por
     * chaves de CSS (blocos <style> são desconsiderados).
     *
     * @return array<int,string> descrições legíveis dos problemas encontrados
     */
    public static function structuralIssues($html)
    {
        $html = (string) $html;
        if ($html === '') {
            return array();
        }

        // Desconsidera CSS em blocos <style> (chaves legítimas de CSS).
        $work = preg_replace('#<style\b[^>]*>.*?</style>#is', '', $html);
        // Remove placeholders válidos para analisar apenas resíduos.
        $semValidos = preg_replace('/\{\{[a-zA-Z0-9_.]+\}\}|\{[a-zA-Z0-9_.]+\}/', '', (string) $work);

        $issues = array();

        // 1) Placeholder dividido por tag HTML entre as chaves (corrupção do editor).
        if (preg_match('/\{[^{}]*<[^{}]*>[^{}]*\}/s', (string) $semValidos)) {
            $issues[] = 'Há placeholder dividido por formatação/tag HTML (chaves separadas por <span> ou outra tag).';
        }

        // 2) Chave dupla fechada com apenas uma chave: {{nome}
        if (preg_match('/\{\{[a-zA-Z0-9_.]+\}(?!\})/', $html)) {
            $issues[] = 'Há placeholder de chave dupla fechado com apenas uma chave (ex.: {{nome}).';
        }

        // 3) Chave simples fechada com duas chaves: {nome}}
        if (preg_match('/(?<!\{)\{[a-zA-Z0-9_.]+\}\}/', $html)) {
            $issues[] = 'Há placeholder de chave simples fechado com duas chaves (ex.: {nome}}).';
        }

        // 4) Placeholder vazio {{}}.
        if (preg_match('/\{\{\s*\}\}/', $html)) {
            $issues[] = 'Há placeholder vazio {{}}.';
        }

        return array_values(array_unique($issues));
    }

    /** Fallback textual configurado para uma chave (string vazia se não houver). */
    public static function fallbackForKey($key)
    {
        foreach (self::inventory() as $item) {
            if ($item['key'] === $key) {
                return (string) $item['fallback'];
            }
        }

        return '';
    }
}
