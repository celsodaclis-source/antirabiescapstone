<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\Ticket;
use App\Models\VaccinationSchedule;
use App\Models\VaccineInventory;
use App\Models\BiteIncident;
use App\Models\SmsLog;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to = $request->input('to', now()->toDateString());

        $fromDt = \Carbon\Carbon::parse($from)->startOfDay();
        $toDt = \Carbon\Carbon::parse($to)->endOfDay();

        $stats = $this->compute($from, $to, $fromDt, $toDt);

        // Patient volume over the selected date range (for chart)
        $volume = Patient::whereBetween('created_at', [$fromDt, $toDt])
            ->get()
            ->groupBy(fn ($p) => $p->created_at->format('Y-m-d'))
            ->map(fn ($g) => $g->count());

        $labels = [];
        $values = [];
        for ($d = $fromDt->copy(); $d->lte($toDt); $d->addDay()) {
            $labels[] = $d->format('M d');
            $values[] = $volume->get($d->format('Y-m-d'), 0);
        }

        return view('reports.index', compact('from', 'to', 'stats', 'labels', 'values'));
    }

    protected function compute(string $from, string $to, ?\Carbon\Carbon $fromDt = null, ?\Carbon\Carbon $toDt = null): array
    {
        $fromDt = $fromDt ?? \Carbon\Carbon::parse($from)->startOfDay();
        $toDt = $toDt ?? \Carbon\Carbon::parse($to)->endOfDay();

        return [
            'patients_registered' => Patient::whereBetween('created_at', [$fromDt, $toDt])->count(),
            'patients_served' => Ticket::where('status', 'completed')
                ->whereBetween('completed_at', [$fromDt, $toDt])->count(),
            'vaccinations_administered' => VaccinationSchedule::where('status', 'administered')
                ->whereBetween('administered_at', [$fromDt, $toDt])->count(),
            'missed_appointments' => VaccinationSchedule::where('status', 'missed')
                ->whereBetween('scheduled_date', [$fromDt, $toDt])->count(),
            'overdue_followups' => VaccinationSchedule::where('status', 'scheduled')
                ->where('scheduled_date', '<', now()->toDateString())->count(),
            'inventory_used' => VaccineInventory::whereBetween('created_at', [$fromDt, $toDt])->sum('quantity'),
            'new_inventory_received' => VaccineInventory::whereNotNull('received_date')
                ->whereBetween('received_date', [$fromDt, $toDt])->sum('quantity'),
            'sms_sent' => SmsLog::where('status', 'delivered')
                ->whereBetween('created_at', [$fromDt, $toDt])->count(),
        ];
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to = $request->input('to', now()->toDateString());
        $fromDt = \Carbon\Carbon::parse($from)->startOfDay();
        $toDt = \Carbon\Carbon::parse($to)->endOfDay();
        $stats = $this->compute($from, $to, $fromDt, $toDt);

        $filename = "nvision_report_{$from}_to_{$to}.csv";

        $rows = [
            ['Metric', 'Value'],
            ['Report period', "{$from} to {$to}"],
            ['Patients registered', $stats['patients_registered']],
            ['Patients served (completed tickets)', $stats['patients_served']],
            ['Vaccinations administered', $stats['vaccinations_administered']],
            ['Missed appointments', $stats['missed_appointments']],
            ['Overdue follow-ups (current)', $stats['overdue_followups']],
            ['Total vaccine stock', $stats['inventory_used']],
            ['New inventory received', $stats['new_inventory_received']],
            ['SMS reminders delivered', $stats['sms_sent']],
        ];

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=utf-8']);
    }

    public function exportPdf(Request $request): Response
    {
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to = $request->input('to', now()->toDateString());
        $fromDt = \Carbon\Carbon::parse($from)->startOfDay();
        $toDt = \Carbon\Carbon::parse($to)->endOfDay();
        $stats = $this->compute($from, $to, $fromDt, $toDt);

        $topBarangays = Patient::whereBetween('created_at', [$fromDt, $toDt])
            ->get()
            ->groupBy('barangay')
            ->map(fn ($g) => $g->count())
            ->sortDesc()
            ->take(10);

        $exposureBreakdown = BiteIncident::whereBetween('created_at', [$fromDt, $toDt])
            ->get()
            ->groupBy('exposure_category')
            ->map(fn ($g) => $g->count());

        $pdf = Pdf::loadView('reports.pdf', compact('from', 'to', 'stats', 'topBarangays', 'exposureBreakdown'));

        return $pdf->download("nvision_report_{$from}_to_{$to}.pdf");
    }
}
