<?php

namespace App\Http\Controllers\AdminPanel;

use App\Http\Controllers\Controller;
use App\Services\AdminPanel\AdminSystemStatusService;
use Illuminate\View\View;

class SystemStatusController extends Controller
{
    public function __invoke(AdminSystemStatusService $systemStatus): View
    {
        return view('admin-panel.system-status', [
            'status' => $systemStatus->snapshot(),
        ]);
    }
}
