<?php

namespace App\Services\Quiz;

/**
 * Correcao manual da discursiva: valida e normaliza o que o corretor humano
 * informou na tela de correcao. E o corretor padrao do sistema.
 */
class CorretorDiscursivaManual implements CorretorDiscursivaInterface
{
    const LIMITE_FEEDBACK = 20000;
    const LIMITE_RUBRICA  = 5000;

    public function referencia()
    {
        return 'manual';
    }

    public function origem()
    {
        return 'manual';
    }

    public function suporta(array $contexto)
    {
        return true;
    }

    public function corrigir(array $contexto)
    {
        $notaMaxima = isset($contexto['nota_maxima']) && $contexto['nota_maxima'] !== null
            ? (float) $contexto['nota_maxima']
            : 10.0;
        if ($notaMaxima <= 0) {
            $notaMaxima = 10.0;
        }

        $notaInformada = isset($contexto['nota']) ? $contexto['nota'] : null;
        if ($notaInformada === null || $notaInformada === '') {
            return array('ok' => false, 'message' => 'Informe a nota da questão discursiva.');
        }

        $notaTexto = str_replace(',', '.', trim((string) $notaInformada));
        if (!is_numeric($notaTexto)) {
            return array('ok' => false, 'message' => 'A nota informada não é um número válido.');
        }

        $nota = (float) $notaTexto;
        if ($nota < 0) {
            return array('ok' => false, 'message' => 'A nota não pode ser negativa.');
        }
        if ($nota > $notaMaxima) {
            return array(
                'ok'      => false,
                'message' => 'A nota não pode ser maior que a nota máxima (' . rtrim(rtrim(number_format($notaMaxima, 2, ',', '.'), '0'), ',') . ').',
            );
        }

        $rubrica  = $this->normalizarTexto($contexto['rubrica'] ?? null, self::LIMITE_RUBRICA);
        $feedback = $this->normalizarTexto($contexto['feedback'] ?? null, self::LIMITE_FEEDBACK);

        return array(
            'ok'       => true,
            'nota'     => round($nota, 2),
            'rubrica'  => $rubrica,
            'feedback' => $feedback,
        );
    }

    private function normalizarTexto($valor, $limite)
    {
        if ($valor === null) {
            return null;
        }
        $texto = trim(strip_tags((string) $valor));
        if ($texto === '') {
            return null;
        }
        if (function_exists('mb_substr')) {
            return mb_substr($texto, 0, $limite);
        }
        return substr($texto, 0, $limite);
    }
}
