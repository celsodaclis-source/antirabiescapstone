<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\BiteIncident;
use App\Models\Patient;
use App\Models\SmsLog;
use App\Models\Ticket;
use App\Models\VaccinationSchedule;
use App\Models\VaccineInventory;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        [$from, $to, $fromDt, $toDt] = $this->dateRange($request);
        $stats = $this->compute($fromDt, $toDt);
        $analytics = $this->analytics($fromDt, $toDt);

        $patientVolume = Patient::whereBetween('created_at', [$fromDt, $toDt])
            ->selectRaw('DATE(created_at) as report_date, COUNT(*) as total')
            ->groupBy('report_date')->pluck('total', 'report_date');
        $vaccinationVolume = VaccinationSchedule::where('status', 'administered')
            ->whereBetween('administered_at', [$fromDt, $toDt])
            ->selectRaw('DATE(administered_at) as report_date, COUNT(*) as total')
            ->groupBy('report_date')->pluck('total', 'report_date');

        $labels = $patientValues = $vaccinationValues = [];
        for ($date = $fromDt->copy()->startOfDay(); $date->lte($toDt); $date->addDay()) {
            $key = $date->format('Y-m-d');
            $labels[] = $date->format('M d');
            $patientValues[] = (int) $patientVolume->get($key, 0);
            $vaccinationValues[] = (int) $vaccinationVolume->get($key, 0);
        }

        return view('reports.index', compact('from', 'to', 'stats', 'analytics', 'labels', 'patientValues', 'vaccinationValues'));
    }

    private function dateRange(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $from = $validated['from'] ?? now()->startOfMonth()->toDateString();
        $to = $validated['to'] ?? now()->toDateString();
        $fromDt = Carbon::parse($from)->startOfDay();
        $toDt = Carbon::parse($to)->endOfDay();

        if ($fromDt->gt($toDt)) {
            abort(422, 'The report start date must be on or before the end date.');
        }

        return [$from, $to, $fromDt, $toDt];
    }

    private function compute(Carbon $fromDt, Carbon $toDt): array
    {
        return [
            'patients_registered' => Patient::whereBetween('created_at', [$fromDt, $toDt])->count(),
            'patients_served' => Ticket::where('status', 'completed')->whereBetween('completed_at', [$fromDt, $toDt])->count(),
            'vaccinations_administered' => VaccinationSchedule::where('status', 'administered')->whereBetween('administered_at', [$fromDt, $toDt])->count(),
            'missed_appointments' => VaccinationSchedule::where('status', 'missed')->whereBetween('scheduled_date', [$fromDt->toDateString(), $toDt->toDateString()])->count(),
            'overdue_followups' => VaccinationSchedule::whereIn('status', ['scheduled', 'missed'])->where('scheduled_date', '<', today()->toDateString())->count(),
            'inventory_on_hand' => VaccineInventory::sum('quantity'),
            'new_inventory_received' => VaccineInventory::whereNotNull('received_date')->whereBetween('received_date', [$fromDt->toDateString(), $toDt->toDateString()])->sum('quantity'),
            'sms_delivered' => SmsLog::where('status', 'delivered')->whereBetween('created_at', [$fromDt, $toDt])->count(),
        ];
    }

    private function analytics(Carbon $fromDt, Carbon $toDt): array
    {
        $scheduled = VaccinationSchedule::whereBetween('scheduled_date', [$fromDt->toDateString(), $toDt->toDateString()]);
        $inventoryByVaccine = VaccineInventory::query()
            ->selectRaw('vaccine_name as label, SUM(quantity) as total')
            ->groupBy('vaccine_name')->orderBy('vaccine_name')->get()
            ->mapWithKeys(fn ($row) => [$row->label ?: 'Unspecified' => (int) $row->total]);

        return [
            'patient_types' => $this->breakdown(Patient::whereBetween('created_at', [$fromDt, $toDt]), 'patient_type'),
            'barangays' => $this->breakdown(Patient::whereBetween('created_at', [$fromDt, $toDt]), 'barangay', 10),
            'exposure_categories' => $this->breakdown(BiteIncident::whereBetween('created_at', [$fromDt, $toDt]), 'exposure_category'),
            'animal_types' => $this->breakdown(BiteIncident::whereBetween('created_at', [$fromDt, $toDt]), 'animal_type', 10),
            'ticket_statuses' => $this->breakdown(Ticket::whereBetween('created_at', [$fromDt, $toDt]), 'status'),
            'ticket_stages' => $this->breakdown(Ticket::whereBetween('created_at', [$fromDt, $toDt]), 'service_stage'),
            'schedule_statuses' => $this->breakdown(clone $scheduled, 'status'),
            'vaccine_types' => $this->breakdown(Patient::whereBetween('created_at', [$fromDt, $toDt])->whereNotNull('vaccine_type'), 'vaccine_type'),
            'inventory_by_vaccine' => $inventoryByVaccine,
            'low_stock_batches' => VaccineInventory::whereColumn('quantity', '<=', 'low_stock_threshold')->count(),
            'expiring_batches' => VaccineInventory::whereBetween('expiration_date', [today()->toDateString(), today()->addDays(30)->toDateString()])->count(),
            'expired_batches' => VaccineInventory::where('expiration_date', '<', today()->toDateString())->count(),
            'sms_statuses' => $this->breakdown(SmsLog::whereBetween('created_at', [$fromDt, $toDt]), 'status'),
            'audit_actions' => $this->breakdown(AuditLog::whereBetween('created_at', [$fromDt, $toDt]), 'action', 10),
        ];
    }

    private function breakdown(Builder $query, string $column, ?int $limit = null): Collection
    {
        $rows = $query->selectRaw("COALESCE(NULLIF(CAST({$column} AS CHAR), ''), 'Not recorded') as label, COUNT(*) as total")
            ->groupBy($column)->orderByDesc('total');

        if ($limit !== null) {
            $rows->limit($limit);
        }

        return $rows->get()->mapWithKeys(fn ($row) => [$row->label => (int) $row->total]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        [$from, $to, $fromDt, $toDt] = $this->dateRange($request);
        $stats = $this->compute($fromDt, $toDt);
        $analytics = $this->analytics($fromDt, $toDt);
        $rows = [
            ['Metric', 'Value'], ['Report period', "{$from} to {$to}"],
            ['Patients registered', $stats['patients_registered']], ['Patients served', $stats['patients_served']],
            ['Vaccinations administered', $stats['vaccinations_administered']], ['Missed appointments', $stats['missed_appointments']],
            ['Overdue follow-ups (current)', $stats['overdue_followups']], ['Vaccine stock on hand (current)', $stats['inventory_on_hand']],
            ['New inventory received', $stats['new_inventory_received']], ['SMS delivered', $stats['sms_delivered']],
        ];
        $sections = [
            'Patient type' => 'patient_types', 'Top barangays' => 'barangays', 'Exposure category' => 'exposure_categories',
            'Animal type' => 'animal_types', 'Queue status' => 'ticket_statuses', 'Queue service stage' => 'ticket_stages',
            'Vaccination schedule status' => 'schedule_statuses', 'Vaccine type' => 'vaccine_types',
            'Current stock by vaccine' => 'inventory_by_vaccine', 'SMS status' => 'sms_statuses', 'Audit action' => 'audit_actions',
        ];
        foreach ($sections as $title => $key) {
            $rows[] = [$title, 'Count / quantity'];
            foreach ($analytics[$key] as $label => $value) $rows[] = [$label, $value];
        }
        $rows[] = ['Current low-stock batches', $analytics['low_stock_batches']];
        $rows[] = ['Current batches expiring within 30 days', $analytics['expiring_batches']];
        $rows[] = ['Current expired batches', $analytics['expired_batches']];

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            foreach ($rows as $row) fputcsv($out, $row);
            fclose($out);
        }, "nvision_report_{$from}_to_{$to}.csv", ['Content-Type' => 'text/csv; charset=utf-8']);
    }

    public function exportPdf(Request $request): Response
    {
        [$from, $to, $fromDt, $toDt] = $this->dateRange($request);
        $stats = $this->compute($fromDt, $toDt);
        $analytics = $this->analytics($fromDt, $toDt);
        $pdf = Pdf::loadView('reports.pdf', compact('from', 'to', 'stats', 'analytics'));

        return $pdf->download("nvision_report_{$from}_to_{$to}.pdf");
    }
}
