<?php

namespace App\Http\Controllers\AdminPanel;

use App\Http\Controllers\Controller;
use App\Models\AdminAlert;
use App\Services\AdminPanel\Blocking\BlockingService;
use App\Services\Operations\DailyCapacityChartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WarningsController extends Controller
{
    public function index(BlockingService $blocking, DailyCapacityChartService $capacityCharts): View
    {
        $alerts = AdminAlert::query()
            ->whereNull('removed_at')
            ->orderByRaw("CASE WHEN status = 'done' THEN 1 ELSE 0 END")
            ->orderByDesc('created_at')
            ->get();

        $charts = $capacityCharts->todayAndTomorrow();

        return view('admin-panel.warnings', [
            'alerts' => $alerts,
            'blockedDays' => $blocking->blockedDaySummaries(),
            'unavailableDays' => $blocking->unavailableForPurchaseDaySummaries(),
            'capacityCharts' => $charts,
        ]);
    }

    public function transition(Request $request, AdminAlert $alert): RedirectResponse
    {
        if ($alert->removed_at !== null) {
            abort(404);
        }

        $action = $request->validate([
            'action' => ['required', 'string', 'in:in_progress,done,remove'],
        ])['action'];

        if ($action === 'in_progress') {
            if ($alert->status !== AdminAlert::STATUS_UNREAD) {
                return back()->with('error', 'Status se ne može promeniti iz trenutnog stanja.');
            }
            $alert->status = AdminAlert::STATUS_IN_PROGRESS;
            $alert->save();

            return back()->with('status', 'Alert je označen kao u obradi.');
        }

        if ($action === 'done') {
            if ($alert->status !== AdminAlert::STATUS_IN_PROGRESS) {
                return back()->with('error', 'Samo alerti „u obradi“ mogu biti završeni.');
            }
            $alert->status = AdminAlert::STATUS_DONE;
            $alert->resolved_at = now();
            $alert->save();

            return back()->with('status', 'Alert je označen kao završen.');
        }

        if ($action === 'remove') {
            if ($alert->status !== AdminAlert::STATUS_DONE) {
                return back()->with('error', 'Ukloniti mogu samo završeni alerti.');
            }
            $alert->removed_at = now();
            $alert->save();

            return back()->with('status', 'Alert je uklonjen sa liste.');
        }

        return back();
    }
}
