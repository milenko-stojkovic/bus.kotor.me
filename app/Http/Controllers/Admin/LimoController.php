<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LimoPickupEvent;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LimoController extends Controller
{
    private const TZ = 'Europe/Podgorica';

    public function index(Request $request): View|RedirectResponse
    {
        $today = Carbon::now(self::TZ)->toDateString();

        $dateFrom = $request->query('date_from', $today);
        $dateTo = $request->query('date_to', $today);

        $validator = Validator::make(
            [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            [
                'date_from' => ['required', 'date'],
                'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            ]
        );

        if ($validator->fails()) {
            return redirect()
                ->route('admin.limo.index', [], false)
                ->withErrors($validator)
                ->withInput();
        }

        /** @var array{date_from: string, date_to: string} $v */
        $v = $validator->validated();

        $from = Carbon::parse($v['date_from'], self::TZ)->startOfDay();
        $to = Carbon::parse($v['date_to'], self::TZ)->endOfDay();

        $events = LimoPickupEvent::query()
            ->whereBetween('occurred_at', [$from, $to])
            ->orderByDesc('occurred_at')
            ->get([
                'agency_name_snapshot',
                'license_plate_snapshot',
                'amount_snapshot',
                'source',
                'status',
                'occurred_at',
                'fiscal_jir',
            ]);

        return view('admin.limo.index', [
            'navActive' => 'limo',
            'pageTitle' => 'Limo pickup',
            'events' => $events,
            'dateFrom' => $v['date_from'],
            'dateTo' => $v['date_to'],
        ]);
    }
}
