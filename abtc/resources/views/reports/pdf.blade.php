<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>N-VISION Report</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; color: #1f2937; margin: 32px; }
        h1 { color: #4338ca; margin-bottom: 0; }
        .sub { color: #6b7280; font-size: 13px; margin-bottom: 18px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; font-size: 13px; }
        th, td { border: 1px solid #d1d5db; padding: 8px 10px; text-align: left; }
        th { background: #eef2ff; }
        .grid { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 12px; }
        .card { border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px 16px; min-width: 180px; }
        .card .v { font-size: 22px; font-weight: bold; }
        .card .l { font-size: 12px; color: #6b7280; }
        .footer { margin-top: 24px; font-size: 11px; color: #9ca3af; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <h1 class="text-black">N-VISION &mdash; Reports and Analytics</h1>
    <div class="sub text-black">Mandaluyong City Animal Bite Treatment Center (ABTC) &middot; Report period: {{ $from }} to {{ $to }}</div>

    <div class="grid text-black">
        <div class="card text-black"><div class="v text-black">{{ $stats['patients_registered'] }}</div><div class="l text-black">Patients Registered</div></div>
        <div class="card text-black"><div class="v text-black">{{ $stats['patients_served'] }}</div><div class="l text-black">Patients Served</div></div>
        <div class="card text-black"><div class="v text-black">{{ $stats['vaccinations_administered'] }}</div><div class="l text-black">Vaccinations Administered</div></div>
        <div class="card text-black"><div class="v text-black">{{ $stats['missed_appointments'] }}</div><div class="l text-black">Missed Appointments</div></div>
        <div class="card text-black"><div class="v text-black">{{ $stats['overdue_followups'] }}</div><div class="l text-black">Overdue Follow-ups</div></div>
        <div class="card text-black"><div class="v text-black">{{ $stats['inventory_on_hand'] }}</div><div class="l text-black">Vaccine Stock on Hand (current)</div></div>
        <div class="card text-black"><div class="v text-black">{{ $stats['new_inventory_received'] }}</div><div class="l text-black">New Stock Received</div></div>
        <div class="card text-black"><div class="v text-black">{{ $stats['sms_delivered'] }}</div><div class="l text-black">SMS Reminders Delivered</div></div>
    </div>

    <table>
        <thead><tr><th class="text-black">Metric</th><th class="text-black">Value</th></tr></thead>
        <tbody>
            <tr><td class="text-black">Patients registered</td><td class="text-black">{{ $stats['patients_registered'] }}</td></tr>
            <tr><td class="text-black">Patients served (completed tickets)</td><td class="text-black">{{ $stats['patients_served'] }}</td></tr>
            <tr><td class="text-black">Vaccinations administered</td><td class="text-black">{{ $stats['vaccinations_administered'] }}</td></tr>
            <tr><td class="text-black">Missed appointments</td><td class="text-black">{{ $stats['missed_appointments'] }}</td></tr>
            <tr><td class="text-black">Overdue follow-ups (current)</td><td class="text-black">{{ $stats['overdue_followups'] }}</td></tr>
            <tr><td class="text-black">Vaccine stock on hand (current)</td><td class="text-black">{{ $stats['inventory_on_hand'] }}</td></tr>
            <tr><td class="text-black">New inventory received</td><td class="text-black">{{ $stats['new_inventory_received'] }}</td></tr>
            <tr><td class="text-black">SMS reminders delivered</td><td class="text-black">{{ $stats['sms_delivered'] }}</td></tr>
        </tbody>
    </table>

    @foreach ([
        'Patient Types' => 'patient_types', 'Top Barangays' => 'barangays',
        'Exposure Categories' => 'exposure_categories', 'Animal Types' => 'animal_types',
        'Queue Statuses' => 'ticket_statuses', 'Queue Service Stages' => 'ticket_stages',
        'Vaccination Schedule Statuses' => 'schedule_statuses', 'Vaccine Types' => 'vaccine_types',
        'SMS Delivery Statuses' => 'sms_statuses', 'Recorded Audit Actions' => 'audit_actions',
        'Current Vaccine Stock by Type' => 'inventory_by_vaccine',
    ] as $title => $key)
        @if (!$analytics[$key]->isEmpty())
            <h3 style="margin-top:20px;" class="text-black">{{ $title }}</h3>
            <table>
                <thead><tr><th class="text-black">Category</th><th class="text-black">Count / Quantity</th></tr></thead>
                <tbody>
                    @foreach ($analytics[$key] as $label => $count)
                        <tr><td class="text-black">{{ ucfirst(str_replace('_', ' ', $label)) }}</td><td class="text-black">{{ $count }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @endforeach

    <h3 style="margin-top:20px;" class="text-black">Current Inventory Health</h3>
    <table><tbody>
        <tr><td class="text-black">Low-stock batches</td><td>{{ $analytics['low_stock_batches'] }}</td></tr>
        <tr><td class="text-black">Batches expiring within 30 days</td><td>{{ $analytics['expiring_batches'] }}</td></tr>
        <tr><td class="text-black">Expired batches</td><td>{{ $analytics['expired_batches'] }}</td></tr>
    </tbody></table>

    <div class="footer text-black">Generated by N-VISION on {{ now()->format('F d, Y h:i A') }}. Confidential &mdash; for LGU Health Office / DOH submission.</div>
</body>
</html>
