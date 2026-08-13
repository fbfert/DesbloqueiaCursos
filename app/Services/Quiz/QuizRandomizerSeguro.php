<?php

namespace App\Services\Quiz;

use Exception;

/**
 * Aleatoriedade padrao de producao: Fisher-Yates com random_int (CSPRNG).
 * Se o gerador criptografico nao estiver disponivel, cai para mt_rand.
 */
class QuizRandomizerSeguro implements QuizRandomizerInterface
{
    public function embaralhar(array $itens)
    {
        $lista = array_values($itens);
        $total = count($lista);
        for ($i = $total - 1; $i > 0; $i--) {
            $j = $this->inteiroAleatorio(0, $i);
            if ($j !== $i) {
                $tmp        = $lista[$i];
                $lista[$i]  = $lista[$j];
                $lista[$j]  = $tmp;
            }
        }
        return $lista;
    }

    public function selecionar(array $itens, $quantidade)
    {
        $quantidade = (int) $quantidade;
        if ($quantidade <= 0) {
            return array();
        }
        $embaralhados = $this->embaralhar($itens);
        return array_slice($embaralhados, 0, $quantidade);
    }

    private function inteiroAleatorio($min, $max)
    {
        if ($min >= $max) {
            return $min;
        }
        try {
            return random_int($min, $max);
        } catch (Exception $e) {
            return mt_rand($min, $max);
        }
    }
}
