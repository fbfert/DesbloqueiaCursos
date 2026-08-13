<?php

namespace App\Services\Quiz;

/**
 * Aleatoriedade determinista por semente — uso exclusivo em testes.
 *
 * Implementa um gerador linear congruente proprio (parametros de Numerical
 * Recipes) para nao depender do estado global de mt_rand/mt_srand nem de
 * mudancas de implementacao entre versoes do PHP: a mesma semente produz
 * sempre a mesma sequencia.
 */
class QuizRandomizerSemente implements QuizRandomizerInterface
{
    private $estado;
    private $sementeInicial;

    public function __construct($semente = 1)
    {
        $this->sementeInicial = (int) $semente;
        $this->estado         = (int) $semente & 0xFFFFFFFF;
    }

    public function embaralhar(array $itens)
    {
        $lista = array_values($itens);
        $total = count($lista);
        for ($i = $total - 1; $i > 0; $i--) {
            $j = $this->proximoInteiro($i + 1);
            if ($j !== $i) {
                $tmp       = $lista[$i];
                $lista[$i] = $lista[$j];
                $lista[$j] = $tmp;
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
        return array_slice($this->embaralhar($itens), 0, $quantidade);
    }

    /**
     * Reinicia a sequencia, permitindo repetir exatamente o mesmo sorteio.
     */
    public function reiniciar()
    {
        $this->estado = $this->sementeInicial & 0xFFFFFFFF;
    }

    /**
     * @param int $limite Limite exclusivo (>0).
     * @return int Inteiro em [0, $limite - 1].
     */
    private function proximoInteiro($limite)
    {
        $limite = (int) $limite;
        if ($limite <= 1) {
            return 0;
        }
        $this->estado = (1664525 * $this->estado + 1013904223) & 0xFFFFFFFF;
        return (int) ($this->estado % $limite);
    }
}
