<?php

namespace App\Services;

use App\Core\Session;

class AuthService
{
    public function userId()
    {
        return Session::get('usuario_id');
    }

    public function check()
    {
        return $this->userId() !== null;
    }
}
