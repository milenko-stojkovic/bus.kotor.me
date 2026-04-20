<?php

namespace App\Http\Controllers\AdminPanel;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminPanel\AdminPanelCapacityUpdateRequest;
use App\Http\Requests\AdminPanel\AdminPanelReportEmailStoreRequest;
use App\Models\ReportEmail;
use App\Models\SystemConfig;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(Request $request): View
    {
        $capacity = (int) (SystemConfig::getValue('available_parking_slots') ?? 0);
        if ($capacity < 1) {
            // Conservative fallback so UI is usable even if config missing.
            $capacity = 9;
        }

        $effectiveFrom = Carbon::now()->startOfDay()->addDays(91);

        return view('admin-panel.settings.index', [
            'navActive' => 'settings',
            'pageTitle' => 'Podešavanja',
            'capacity' => $capacity,
            'capacityEffectiveFrom' => $effectiveFrom,
            'reportEmails' => ReportEmail::query()->orderBy('email')->get(),
        ]);
    }

    public function updateCapacity(AdminPanelCapacityUpdateRequest $request): RedirectResponse
    {
        $capacity = (int) $request->validated('available_parking_slots');
        SystemConfig::setValue('available_parking_slots', $capacity);

        $effectiveFrom = Carbon::now()->startOfDay()->addDays(91)->format('d.m.Y.');

        return redirect()->to(route('panel_admin.settings', [], false))
            ->with('status', "Kapacitet je sačuvan. Promena ne važi retroaktivno i primenjivaće se od dana: {$effectiveFrom}");
    }

    public function storeReportEmail(AdminPanelReportEmailStoreRequest $request): RedirectResponse
    {
        $email = (string) $request->validated('email');

        // Duplicate safety (case-insensitive, but we store lowercase).
        if (ReportEmail::query()->where('email', $email)->exists()) {
            return redirect()->to(route('panel_admin.settings', [], false))
                ->withErrors(['email' => 'Adresa se već nalazi na spisku.'])
                ->withInput();
        }

        ReportEmail::query()->create(['email' => $email]);

        return redirect()->to(route('panel_admin.settings', [], false))
            ->with('status', 'Email adresa je dodata.');
    }

    public function destroyReportEmail(ReportEmail $reportEmail): RedirectResponse
    {
        $email = $reportEmail->email;
        $reportEmail->delete();

        return redirect()->to(route('panel_admin.settings', [], false))
            ->with('status', "Email adresa {$email} je obrisana.");
    }
}

