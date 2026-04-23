<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Services\FinanceiroService;
use App\Services\RateioService;

class RateiosController extends Controller
{
    private $financeiroService;
    private $rateioService;

    public function __construct()
    {
        $this->financeiroService = new FinanceiroService();
        $this->rateioService = new RateioService();
    }

    public function index(Request $request)
    {
        $apuracaoId = (int) $request->query('apuracao_id', 0);
        $panel = $this->financeiroService->painelAdmin();

        return $this->view('admin/rateios/index', array(
            'title' => 'Rateios',
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
            'apuracao_id' => $apuracaoId,
            'apuracoes' => $panel['apuracoes'],
            'rateios' => $this->rateioService->listarRateiosAdmin($apuracaoId > 0 ? $apuracaoId : null),
        ));
    }

    public function show(Request $request)
    {
        $rateioId = (int) $request->query('rateio_id', 0);
        if ($rateioId <= 0) {
            Session::flash('errors', array('Informe um rateio valido.'));
            return $this->redirect('/admin/rateios');
        }

        $resultado = $this->rateioService->showRateioAdmin($rateioId);
        if (!$resultado) {
            Session::flash('errors', array('Rateio nao encontrado.'));
            return $this->redirect('/admin/rateios');
        }

        return $this->view('admin/rateios/show', array(
            'title' => 'Rateio da apuracao',
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', array()),
            'rateio' => $resultado['rateio'],
            'participantes' => $resultado['participantes'],
        ));
    }
}
