<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LimoIncident;
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
        $listType = $request->query('type', 'pickup');

        $validator = Validator::make(
            [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'type' => $listType,
            ],
            [
                'date_from' => ['required', 'date'],
                'date_to' => ['required', 'date', 'after_or_equal:date_from'],
                'type' => ['required', 'in:pickup,incident'],
            ]
        );

        if ($validator->fails()) {
            return redirect()
                ->route('admin.limo.index', array_filter([
                    'type' => in_array($listType, ['pickup', 'incident'], true) ? $listType : 'pickup',
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                ], static fn ($v) => $v !== null && $v !== ''), false)
                ->withErrors($validator)
                ->withInput();
        }

        /** @var array{date_from: string, date_to: string, type: string} $v */
        $v = $validator->validated();

        $from = Carbon::parse($v['date_from'], self::TZ)->startOfDay();
        $to = Carbon::parse($v['date_to'], self::TZ)->endOfDay();

        $events = collect();
        $incidents = collect();

        if ($v['type'] === 'pickup') {
            $events = LimoPickupEvent::query()
                ->whereBetween('occurred_at', [$from, $to])
                ->orderByDesc('occurred_at')
                ->get([
                    'id',
                    'agency_name_snapshot',
                    'license_plate_snapshot',
                    'amount_snapshot',
                    'source',
                    'status',
                    'occurred_at',
                    'fiscal_jir',
                ]);
        } else {
            $incidents = LimoIncident::query()
                ->whereBetween('occurred_at', [$from, $to])
                ->with(['recordedBy:id,username'])
                ->orderByDesc('occurred_at')
                ->get();
        }

        return view('admin.limo.index', [
            'navActive' => 'limo',
            'pageTitle' => 'Limo događaji',
            'listType' => $v['type'],
            'events' => $events,
            'incidents' => $incidents,
            'dateFrom' => $v['date_from'],
            'dateTo' => $v['date_to'],
        ]);
    }
}
