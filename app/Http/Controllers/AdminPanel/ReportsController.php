<?php

namespace App\Http\Controllers\AdminPanel;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminPanel\AdminPanelReportPdfRequest;
use App\Services\AdminPanel\Reports\AdminReportsCreatedAtBounds;
use App\Services\AdminPanel\Reports\AdminReportsService;
use App\Services\Pdf\AdminReportsPdfGenerator;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportsController extends Controller
{
    public function index(Request $request, AdminReportsCreatedAtBounds $bounds): View
    {
        $min = $bounds->minDate()->toDateString();
        $max = $bounds->maxDate()->toDateString();

        $minYear = (int) Carbon::parse($min)->format('Y');
        $maxYear = (int) Carbon::parse($max)->format('Y');

        return view('admin-panel.reports.index', [
            'navActive' => 'reports',
            'pageTitle' => 'Izvještaji',
            'minDate' => $min,
            'maxDate' => $max,
            'minYear' => $minYear,
            'maxYear' => $maxYear,
        ]);
    }

    public function pdf(
        AdminPanelReportPdfRequest $request,
        AdminReportsService $reports,
        AdminReportsPdfGenerator $pdf,
    ): \Symfony\Component\HttpFoundation\Response {
        $v = $request->validated();

        [$from, $to, $periodLabel] = $this->rangeFromValidated($v);

        $kind = (string) $v['kind'];
        $dataset = match ($kind) {
            'by_payment' => [
                'title' => 'Finansijski izveštaj po uplati za '.$periodLabel,
                'subtitle' => 'Uplate za rezervacije',
                'kind' => $kind,
                'period' => $periodLabel,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'data' => $reports->byPayment($from, $to),
            ],
            'by_realization' => [
                'title' => 'Finansijski izveštaj po realizaciji za '.$periodLabel,
                'subtitle' => 'Prihod od realizovanih rezervacija',
                'kind' => $kind,
                'period' => $periodLabel,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'data' => $reports->byRealization($from, $to),
            ],
            'by_vehicle_type' => [
                'title' => 'Izveštaj po tipu vozila za '.$periodLabel,
                'subtitle' => 'Realizovane rezervacije po tipu vozila',
                'kind' => $kind,
                'period' => $periodLabel,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'data' => $reports->byVehicleType($from, $to),
            ],
            'advance_obligations' => (function () use ($v, $reports): array {
                if (! (bool) config('features.advance_payments')) {
                    abort(404);
                }

                $d = Carbon::parse((string) $v['date'])->startOfDay();
                $label = $this->fmtDateCg($d);

                return [
                    'title' => 'Izvještaj o obavezama po osnovu avansnih uplata na dan '.$label,
                    'subtitle' => 'Prikaz predstavlja stanje neiskorišćenih avansnih sredstava po agencijama na izabrani dan.',
                    'kind' => 'advance_obligations',
                    'period' => $label,
                    'from' => $d->toDateString(),
                    'to' => $d->toDateString(),
                    'data' => $reports->advanceObligationsSnapshot($d->copy()->endOfDay()),
                ];
            })(),
            default => abort(400),
        };

        $binary = $pdf->renderBinary($dataset);

        $filename = 'izvestaj-'.str_replace('_', '-', $kind).'-'.$from->toDateString().'-'.$to->toDateString().'.pdf';

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }

    /**
     * @param  array<string, mixed>  $v
     * @return array{0:Carbon,1:Carbon,2:string}
     */
    private function rangeFromValidated(array $v): array
    {
        $when = (string) $v['when'];

        if ($when === 'daily') {
            $d = Carbon::parse((string) $v['date'])->startOfDay();
            return [$d, $d, $this->fmtDateCg($d)];
        }

        if ($when === 'monthly') {
            $y = (int) $v['year'];
            $m = (int) $v['month'];
            $from = Carbon::create($y, $m, 1)->startOfDay();
            $to = $from->copy()->endOfMonth()->startOfDay();
            return [$from, $to, $this->fmtMonthCg($from)];
        }

        if ($when === 'yearly') {
            $y = (int) $v['year'];
            $from = Carbon::create($y, 1, 1)->startOfDay();
            $to = Carbon::create($y, 12, 31)->startOfDay();
            return [$from, $to, $this->fmtYearCg($from)];
        }

        // period
        $from = Carbon::parse((string) $v['date_from'])->startOfDay();
        $to = Carbon::parse((string) $v['date_to'])->startOfDay();
        return [$from, $to, $this->fmtDateCg($from).' – '.$this->fmtDateCg($to)];
    }

    private function fmtDateCg(Carbon $d): string
    {
        return $d->format('d.m.Y').'.';
    }

    private function fmtMonthCg(Carbon $d): string
    {
        return $d->format('m.Y').'.';
    }

    private function fmtYearCg(Carbon $d): string
    {
        return $d->format('Y').'.';
    }
}

