<?php
/**
 * Funções de apresentação do checkout no tema caderno (só formatação; nenhuma
 * regra de negócio). Incluído com require_once pelas páginas do checkout.
 *
 *   caderno_ck_dinheiro($valor)   "R$ 1.234,50" (sempre com centavos: é dinheiro de pedido)
 *   caderno_ck_status($status)    rótulo legível do status real do pedido
 *   caderno_ck_erros($errors)     lista de mensagens do servidor → array(
 *                                   'lista'  => textos para o aviso do topo,
 *                                   'campos' => array(name do campo => texto) )
 *
 * As mensagens de validação do servidor chegam sem acento ("CPF valido");
 * aqui elas ganham a acentuação correta e, quando apontam um campo conhecido,
 * são repetidas junto dele. Mensagem desconhecida passa como veio.
 */

if (!function_exists('caderno_ck_dinheiro')) {
    function caderno_ck_dinheiro($valor): string
    {
        return 'R$ ' . number_format((float) $valor, 2, ',', '.');
    }

    /** Contorno à caneta do botão secundário (.btn-sec): último filho do botão. */
    function caderno_ck_contorno(): string
    {
        return '<svg viewBox="0 0 200 50" preserveAspectRatio="none" aria-hidden="true" focusable="false"><path d="M8 26 C 6 8, 60 4, 110 6 S 196 8, 194 26 S 150 46, 100 45 S 4 44, 10 22"/></svg>';
    }

    function caderno_ck_status($status): string
    {
        $status = strtolower(trim((string) $status));
        $rotulos = array(
            'rascunho' => 'Rascunho',
            'aguardando_pagamento' => 'Aguardando pagamento',
            'pendencia' => 'Pendência',
            'aguardando_reenvio' => 'Aguardando reenvio do comprovante',
            'comprovante_enviado' => 'Comprovante enviado',
            'em_analise' => 'Em análise',
            'aprovado' => 'Aprovado',
            'pago' => 'Pago',
            'cancelado' => 'Cancelado',
            'expirado' => 'Expirado',
            'reembolsado' => 'Reembolsado',
        );
        if (isset($rotulos[$status])) {
            return $rotulos[$status];
        }

        return $status !== '' ? ucfirst(str_replace('_', ' ', $status)) : '—';
    }

    /** @return array{0:string,1:string} [texto acentuado, name do campo ou ''] */
    function caderno_ck_erro($mensagem): array
    {
        $m = trim((string) $mensagem);
        $conhecidas = array(
            'Selecione um curso valido.' => array('Selecione um curso válido.', ''),
            'A turma selecionada nao esta disponivel para inscricao.' => array('A turma selecionada não está disponível para inscrição.', ''),
            'Informe uma quantidade valida de vagas.' => array('Informe uma quantidade válida de vagas.', 'quantidade'),
            'Informe o nome do pagador.' => array('Informe o nome do pagador.', 'pagador_nome'),
            'Informe um CPF valido do pagador.' => array('Informe um CPF válido do pagador.', 'pagador_cpf'),
            'Informe um e-mail valido do pagador.' => array('Informe um e-mail válido do pagador.', 'pagador_email'),
        );
        if (isset($conhecidas[$m])) {
            return $conhecidas[$m];
        }

        if (preg_match('/^O participante #(\d+) precisa de nome\.$/', $m, $r)) {
            return array('O participante ' . (int) $r[1] . ' precisa de nome.', 'participantes[' . ((int) $r[1] - 1) . '][nome]');
        }
        if (preg_match('/^O (e-mail|CPF) do participante #(\d+) e invalido\.$/', $m, $r)) {
            $campo = $r[1] === 'CPF' ? 'cpf' : 'email';
            return array('O ' . $r[1] . ' do participante ' . (int) $r[2] . ' é inválido.', 'participantes[' . ((int) $r[2] - 1) . '][' . $campo . ']');
        }

        // Palavras que nunca têm outra grafia nas mensagens do checkout.
        $m = preg_replace(
            array('/\bpossivel\b/u', '/\bnao\b/u', '/\bNao\b/u', '/\bpermissao\b/u', '/\bpropria\b/u', '/\binscricao\b/u'),
            array('possível', 'não', 'Não', 'permissão', 'própria', 'inscrição'),
            $m
        );

        return array($m, '');
    }

    /**
     * Marcação de erro de um campo: classe extra do .campo, atributos do
     * controle (aria-invalid/aria-describedby) e a mensagem junto do campo.
     * $ajudaId: id de um texto de ajuda que o campo já descreve (opcional).
     */
    function caderno_ck_campo(array $campos, string $name, string $id, string $ajudaId = ''): array
    {
        $ids = $ajudaId;
        if (!isset($campos[$name])) {
            return array('classe' => '', 'attrs' => $ids !== '' ? ' aria-describedby="' . $ids . '"' : '', 'msg' => '');
        }
        $msgId = $id . '-erro';
        $ids = trim($msgId . ' ' . $ids);

        return array(
            'classe' => ' erro',
            'attrs' => ' aria-invalid="true" aria-describedby="' . htmlspecialchars($ids, ENT_QUOTES, 'UTF-8') . '"',
            'msg' => '<p class="erro-msg" id="' . htmlspecialchars($msgId, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($campos[$name], ENT_QUOTES, 'UTF-8') . '</p>',
        );
    }

    function caderno_ck_erros($errors): array
    {
        $saida = array('lista' => array(), 'campos' => array());
        if (!is_array($errors)) {
            return $saida;
        }
        foreach ($errors as $erro) {
            if (is_array($erro) || trim((string) $erro) === '') {
                continue;
            }
            $e = caderno_ck_erro($erro);
            $saida['lista'][] = $e[0];
            if ($e[1] !== '' && !isset($saida['campos'][$e[1]])) {
                $saida['campos'][$e[1]] = $e[0];
            }
        }

        return $saida;
    }
}
