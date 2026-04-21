<?php

namespace App\Http\Controllers\AdminPanel;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminPanel\AdminPanelAnalyticsRequest;
use App\Services\AdminPanel\Analytics\AdminAnalyticsDateBounds;
use App\Services\AdminPanel\Analytics\AdminAnalyticsService;
use App\Services\Pdf\AdminAnalyticsPdfGenerator;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnalyticsController extends Controller
{
    public function index(
        Request $request,
        AdminAnalyticsDateBounds $bounds,
        AdminAnalyticsService $analytics,
    ): View {
        $min = $bounds->minFromDate()->toDateString();
        $max = $bounds->maxToDate()->toDateString();

        $dateFrom = (string) $request->query('date_from', $min);
        $dateTo = (string) $request->query('date_to', $max);
        $includeFree = (bool) $request->boolean('include_free');

        $dataset = null;
        if ($request->query('show') === '1') {
            $validated = $request->validate((new AdminPanelAnalyticsRequest)->rules());
            $includeFree = (bool) ($validated['include_free'] ?? false);
            $dataset = $analytics->build($validated['date_from'], $validated['date_to'], $includeFree);
            $dateFrom = $validated['date_from'];
            $dateTo = $validated['date_to'];
        }

        return view('admin-panel.analytics.index', [
            'navActive' => 'analytics',
            'pageTitle' => 'Analitika',
            'minDate' => $min,
            'maxDate' => $max,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'includeFree' => $includeFree,
            'dataset' => $dataset,
        ]);
    }

    public function pdf(
        AdminPanelAnalyticsRequest $request,
        AdminAnalyticsService $analytics,
        AdminAnalyticsPdfGenerator $pdf,
    ): \Symfony\Component\HttpFoundation\StreamedResponse {
        $v = $request->validated();
        $includeFree = (bool) ($v['include_free'] ?? false);
        $dataset = $analytics->build($v['date_from'], $v['date_to'], $includeFree);

        $binary = $pdf->renderBinary($dataset);

        return response()->streamDownload(
            static function () use ($binary): void {
                echo $binary;
            },
            'analytics-'.$v['date_from'].'-'.$v['date_to'].'.pdf',
            ['Content-Type' => 'application/pdf'],
        );
    }
}

