<?php

namespace App\Services;

use InvalidArgumentException;

class TrashService
{
    public function requireReason($reason)
    {
        $reason = trim((string) $reason);

        if ($reason === '') {
            throw new InvalidArgumentException('A justificativa da lixeira e obrigatoria.');
        }

        return $reason;
    }
}
