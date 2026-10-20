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
        $dailyTrends = $this->dailyTrends($fromDt, $toDt);
        $labels = $dailyTrends->pluck('label')->all();
        $patientValues = $dailyTrends->pluck('registrations')->all();
        $vaccinationValues = $dailyTrends->pluck('vaccinations')->all();
        $smsValues = $dailyTrends->pluck('sms_delivered')->all();

        return view('reports.index', compact('from', 'to', 'stats', 'analytics', 'labels', 'patientValues', 'vaccinationValues', 'smsValues', 'dailyTrends'));
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

    private function dailyTrends(Carbon $fromDt, Carbon $toDt): Collection
    {
        $registrations = Patient::whereBetween('created_at', [$fromDt, $toDt])
            ->selectRaw('DATE(created_at) as report_date, COUNT(*) as total')
            ->groupBy('report_date')->pluck('total', 'report_date');
        $vaccinations = VaccinationSchedule::where('status', 'administered')
            ->whereBetween('administered_at', [$fromDt, $toDt])
            ->selectRaw('DATE(administered_at) as report_date, COUNT(*) as total')
            ->groupBy('report_date')->pluck('total', 'report_date');
        $sms = SmsLog::where('status', 'delivered')->whereBetween('created_at', [$fromDt, $toDt])
            ->selectRaw('DATE(created_at) as report_date, COUNT(*) as total')
            ->groupBy('report_date')->pluck('total', 'report_date');

        $rows = collect();
        for ($date = $fromDt->copy()->startOfDay(); $date->lte($toDt); $date->addDay()) {
            $key = $date->format('Y-m-d');
            $rows->push([
                'date' => $key,
                'label' => $date->format('M d, Y'),
                'registrations' => (int) $registrations->get($key, 0),
                'vaccinations' => (int) $vaccinations->get($key, 0),
                'sms_delivered' => (int) $sms->get($key, 0),
            ]);
        }

        return $rows;
    }

    private function analytics(Carbon $fromDt, Carbon $toDt): array
    {
        $scheduled = VaccinationSchedule::whereBetween('scheduled_date', [$fromDt->toDateString(), $toDt->toDateString()]);
        $inventoryByVaccine = VaccineInventory::query()
            ->selectRaw('vaccine_name as label, SUM(quantity) as total')
            ->groupBy('vaccine_name')->orderBy('vaccine_name')->get()
            ->mapWithKeys(fn ($row) => [$row->label ?: 'Unspecified' => (int) $row->total]);
        $inventoryReceiptsByDay = VaccineInventory::whereNotNull('received_date')
            ->whereBetween('received_date', [$fromDt->toDateString(), $toDt->toDateString()])
            ->selectRaw('received_date as label, SUM(quantity) as total')
            ->groupBy('received_date')->orderBy('received_date')->get()
            ->mapWithKeys(fn ($row) => [$row->label => (int) $row->total]);

        return [
            'patient_types' => $this->breakdown(Patient::whereBetween('created_at', [$fromDt, $toDt]), 'patient_type'),
            'patient_sex' => $this->breakdown(Patient::whereBetween('created_at', [$fromDt, $toDt]), 'sex'),
            'planned_doses' => $this->breakdown(Patient::whereBetween('created_at', [$fromDt, $toDt])->whereNotNull('planned_dose_count'), 'planned_dose_count'),
            'barangays' => $this->breakdown(Patient::whereBetween('created_at', [$fromDt, $toDt]), 'barangay', 10),
            'exposure_categories' => $this->breakdown(BiteIncident::whereBetween('created_at', [$fromDt, $toDt]), 'exposure_category'),
            'animal_types' => $this->breakdown(BiteIncident::whereBetween('created_at', [$fromDt, $toDt]), 'animal_type', 10),
            'ticket_statuses' => $this->breakdown(Ticket::whereBetween('created_at', [$fromDt, $toDt]), 'status'),
            'ticket_stages' => $this->breakdown(Ticket::whereBetween('created_at', [$fromDt, $toDt]), 'service_stage'),
            'visit_types' => $this->breakdown(Ticket::whereBetween('created_at', [$fromDt, $toDt]), 'visit_type'),
            'schedule_statuses' => $this->breakdown(clone $scheduled, 'status'),
            'vaccine_types' => $this->breakdown(Patient::whereBetween('created_at', [$fromDt, $toDt])->whereNotNull('vaccine_type'), 'vaccine_type'),
            'inventory_by_vaccine' => $inventoryByVaccine,
            'inventory_receipts_by_day' => $inventoryReceiptsByDay,
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
        $dailyTrends = $this->dailyTrends($fromDt, $toDt);
        $generatedBy = $request->user()?->name ?? 'System';
        $generatedRole = $request->user()?->role ?? 'System';
        $generatedAt = now();
        $rows = [
            ['N-VISION — Reports and Analytics'], ['Mandaluyong City Animal Bite Treatment Center (ABTC)'],
            ['Logo', asset('images/nvision_lgu_seal_logo.png')],
            ['Report period', "{$from} to {$to}"],
            ['Generated by', "{$generatedBy} ({$generatedRole})"], ['Generated at', $generatedAt->format('Y-m-d H:i:s T')],
            ['Metric', 'Value', 'Description'],
            ['Patients registered', $stats['patients_registered'], 'New patient records created during the selected period.'],
            ['Patients served', $stats['patients_served'], 'Queue tickets completed during the selected period.'],
            ['Vaccinations administered', $stats['vaccinations_administered'], 'Doses recorded as administered during the selected period.'],
            ['Missed appointments', $stats['missed_appointments'], 'Vaccination appointments marked missed during the selected period.'],
            ['Overdue follow-ups (current)', $stats['overdue_followups'], 'Scheduled or missed doses dated before today.'],
            ['Vaccine stock on hand (current)', $stats['inventory_on_hand'], 'Current total quantity across all inventory batches.'],
            ['New inventory received', $stats['new_inventory_received'], 'Vaccine quantity received during the selected dates.'],
            ['SMS delivered', $stats['sms_delivered'], 'SMS records marked delivered during the selected period.'],
        ];
        $sections = [
            'Patient type' => ['patient_types', 'New patient records grouped by regular or priority patient type.'],
            'Patient sex' => ['patient_sex', 'New patient records grouped by recorded sex.'],
            'Planned doses' => ['planned_doses', 'New patient records grouped by provider-ordered dose count.'],
            'Top barangays' => ['barangays', 'Top barangays by new patient registrations.'],
            'Exposure category' => ['exposure_categories', 'Bite incidents grouped by assessed exposure category.'],
            'Animal type' => ['animal_types', 'Bite incidents grouped by animal type.'],
            'Queue status' => ['ticket_statuses', 'Tickets created in the period grouped by recorded status.'],
            'Queue service stage' => ['ticket_stages', 'Tickets created in the period grouped by service stage.'],
            'Visit type' => ['visit_types', 'Tickets grouped by new or returning visit type.'],
            'Vaccination schedule status' => ['schedule_statuses', 'Doses due in the selected period grouped by status.'],
            'Vaccine type' => ['vaccine_types', 'New patient records grouped by prescribed vaccine type.'],
            'Current stock by vaccine' => ['inventory_by_vaccine', 'Current stock grouped by vaccine type; not limited to the report period.'],
            'Stock received by date' => ['inventory_receipts_by_day', 'Quantity of vaccine stock received each day in the selected period.'],
            'SMS status' => ['sms_statuses', 'SMS records created in the period grouped by delivery status.'],
            'Audit action' => ['audit_actions', 'Most frequent actions recorded in the audit log during the period.'],
        ];
        foreach ($sections as $title => [$key, $description]) {
            $rows[] = [$title, 'Count / quantity', $description];
            foreach ($analytics[$key] as $label => $value) $rows[] = [$label, $value];
        }
        $rows[] = ['Current low-stock batches', $analytics['low_stock_batches'], 'Inventory batches at or below their low-stock threshold.'];
        $rows[] = ['Current batches expiring within 30 days', $analytics['expiring_batches'], 'Inventory batches whose expiration date is within the next 30 days.'];
        $rows[] = ['Current expired batches', $analytics['expired_batches'], 'Inventory batches with an expiration date before today.'];
        $rows[] = ['Daily Activity Trend', '', 'Registrations, vaccinations administered, and delivered SMS by date.'];
        $rows[] = ['Date', 'Registrations', 'Vaccinations Administered', 'SMS Delivered'];
        foreach ($dailyTrends as $trend) {
            $rows[] = [$trend['date'], $trend['registrations'], $trend['vaccinations'], $trend['sms_delivered']];
        }
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
        $dailyTrends = $this->dailyTrends($fromDt, $toDt);
        $generatedBy = $request->user()?->name ?? 'System';
        $generatedRole = $request->user()?->role ?? 'System';
        $generatedAt = now();
        $logoPath = public_path('images/nvision_lgu_seal_logo.png');
        $pdf = Pdf::loadView('reports.pdf', compact('from', 'to', 'stats', 'analytics', 'dailyTrends', 'generatedBy', 'generatedRole', 'generatedAt', 'logoPath'));

        return $pdf->download("nvision_report_{$from}_to_{$to}.pdf");
    }
}
