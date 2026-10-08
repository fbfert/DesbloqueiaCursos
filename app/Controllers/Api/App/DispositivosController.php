<?php

namespace App\Controllers\Api\App;

use App\Core\Request;
use App\Models\AppDispositivo;
use App\Support\AppApi\Tempo;

/**
 * Registro do token FCM do aparelho (upsert por device_id) e remoção.
 */
class DispositivosController extends AppController
{
    public function registrar(Request $request)
    {
        $deviceId = $this->texto($request, 'device_id', 100);
        $fcmToken = $this->texto($request, 'fcm_token', 512);
        $plataforma = strtolower($this->texto($request, 'plataforma', 20));
        $appVersao = $this->texto($request, 'app_versao', 40);

        $campos = array();
        if (!AuthController::deviceIdValido($deviceId)) {
            $campos['device_id'] = 'Identificador do dispositivo inválido.';
        }
        if ($fcmToken === '' || !preg_match('/^[A-Za-z0-9_:\-.]{20,512}$/', $fcmToken)) {
            $campos['fcm_token'] = 'Token de notificação inválido.';
        }
        if (!in_array($plataforma, array('android', 'ios'), true)) {
            $campos['plataforma'] = 'Plataforma inválida.';
        }
        if ($campos) {
            return $this->erro('validacao', 'Verifique os dados informados.', 422, $campos);
        }

        (new AppDispositivo())->upsert($this->usuarioId(), $deviceId, $fcmToken, $plataforma, $appVersao !== '' ? $appVersao : null, Tempo::sql());

        return $this->ok(array('ok' => true));
    }

    public function remover(Request $request)
    {
        $deviceId = $this->texto($request, 'device_id', 100);
        if (!AuthController::deviceIdValido($deviceId)) {
            return $this->erro('validacao', 'Verifique os dados informados.', 422, array('device_id' => 'Identificador do dispositivo inválido.'));
        }

        (new AppDispositivo())->removerDoUsuario($this->usuarioId(), $deviceId);

        return $this->ok(array('ok' => true));
    }
}
