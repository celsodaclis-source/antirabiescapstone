<x-app-layout>
    <x-slot name="header">Reports and Analytics</x-slot>

    <div class="py-6 text-black">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6 text-black">

            <form method="GET" action="{{ route('reports.index') }}" class="bg-white shadow rounded-lg p-4 flex flex-wrap items-end gap-3">
                <div class="text-black">
                    <label class="block text-sm font-medium text-black">From</label>
                    <input type="date" name="from" value="{{ $from }}" class="mt-1 border-gray-300 rounded-md shadow-sm">
                </div>
                <div class="text-black">
                    <label class="block text-sm font-medium text-black">To</label>
                    <input type="date" name="to" value="{{ $to }}" class="mt-1 border-gray-300 rounded-md shadow-sm">
                </div>
                <button class="bg-indigo-600 text-white px-4 py-2 rounded-md">Apply</button>
                <div class="ml-auto flex gap-2 text-black">
                    <a href="{{ route('reports.export.csv', ['from' => $from, 'to' => $to]) }}" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">Export CSV</a>
                    <a href="{{ route('reports.export.pdf', ['from' => $from, 'to' => $to]) }}" target="_blank" class="bg-red-600 text-white px-4 py-2 rounded-md hover:bg-red-700">Export PDF</a>
                </div>
            </form>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 text-black">
                <div class="bg-white rounded-lg shadow p-5 border-2 border-[#176b9c] text-black">
                    <div class="text-sm text-black">Patients Registered</div>
                    <div class="text-3xl font-bold">{{ $stats['patients_registered'] }}</div>
                    <p class="mt-2 text-xs text-slate-500">New patient records created during the selected period.</p>
                </div>
                <div class="bg-white rounded-lg shadow p-5 border-2 border-[#ffad00] text-black">
                    <div class="text-sm text-black">Patients Served</div>
                    <div class="text-3xl font-bold">{{ $stats['patients_served'] }}</div>
                    <p class="mt-2 text-xs text-slate-500">Queue tickets completed during the selected period.</p>
                </div>
                <div class="bg-white rounded-lg shadow p-5 border-2 border-[#00a5e8] text-black">
                    <div class="text-sm text-black">Vaccinations Administered</div>
                    <div class="text-3xl font-bold">{{ $stats['vaccinations_administered'] }}</div>
                    <p class="mt-2 text-xs text-slate-500">Vaccine doses recorded as administered during the selected period.</p>
                </div>
                <div class="bg-white rounded-lg shadow p-5 border-2 border-[#176b9c] text-black">
                    <div class="text-sm text-black">Missed Appointments</div>
                    <div class="text-3xl font-bold">{{ $stats['missed_appointments'] }}</div>
                    <p class="mt-2 text-xs text-slate-500">Vaccination appointments marked missed in the selected period.</p>
                </div>
                <div class="bg-white rounded-lg shadow p-5 border-2 border-[#ffad00] text-black">
                    <div class="text-sm text-black">Overdue Follow-ups</div>
                    <div class="text-3xl font-bold">{{ $stats['overdue_followups'] }}</div>
                    <p class="mt-2 text-xs text-slate-500">Scheduled or missed doses with dates before today.</p>
                </div>
                <div class="bg-white rounded-lg shadow p-5 border-2 border-[#00a5e8] text-black">
                    <div class="text-sm text-black">Vaccine Stock on Hand</div>
                    <div class="text-3xl font-bold">{{ $stats['inventory_on_hand'] }}</div>
                    <p class="mt-2 text-xs text-slate-500">Current total quantity across all vaccine inventory batches.</p>
                </div>
                <div class="bg-white rounded-lg shadow p-5 border-2 border-[#176b9c] text-black">
                    <div class="text-sm text-black">New Stock Received</div>
                    <div class="text-3xl font-bold">{{ $stats['new_inventory_received'] }}</div>
                    <p class="mt-2 text-xs text-slate-500">Vaccine quantity received between the selected dates.</p>
                </div>
                <div class="bg-white rounded-lg shadow p-5 border-2 border-[#ffad00] text-black">
                    <div class="text-sm text-black">SMS Delivered</div>
                    <div class="text-3xl font-bold">{{ $stats['sms_delivered'] }}</div>
                    <p class="mt-2 text-xs text-slate-500">SMS records marked delivered during the selected period.</p>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-5 text-black">
                <h3 class="font-semibold text-black mb-3">Daily System Activity</h3>
                <p class="mb-3 text-sm text-slate-500">Daily patient registrations, administered doses, and delivered SMS messages during the selected period.</p>
                <div class="relative h-72 w-full">
                    <canvas id="reportChart"></canvas>
                </div>
            </div>

            <section>
                <h2 class="mb-4 text-lg font-semibold text-slate-800">Operational Analytics</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                    @foreach ([
                        ['patientTypeChart', 'Patient Type', 'Share of new registrations by priority or regular patient type.', $analytics['patient_types'], 'doughnut'],
                        ['patientSexChart', 'Patient Sex', 'New registrations grouped by the sex recorded in the patient profile.', $analytics['patient_sex'], 'doughnut'],
                        ['exposureChart', 'Exposure Category', 'Recorded bite incidents grouped by the doctor-assessed exposure category.', $analytics['exposure_categories'], 'doughnut'],
                        ['animalTypeChart', 'Animal Type', 'Recorded bite incidents grouped by animal type.', $analytics['animal_types'], 'bar'],
                        ['queueChart', 'Queue Status', 'Tickets created in this period grouped by their current recorded status.', $analytics['ticket_statuses'], 'bar'],
                        ['serviceStageChart', 'Queue Service Stage', 'Tickets grouped by the workflow stage assigned at creation.', $analytics['ticket_stages'], 'bar'],
                        ['visitTypeChart', 'Visit Type', 'Tickets grouped by new or returning visit type.', $analytics['visit_types'], 'doughnut'],
                        ['scheduleChart', 'Vaccination Schedule Status', 'Doses with appointment dates in this period, grouped by status.', $analytics['schedule_statuses'], 'bar'],
                        ['dosePlanChart', 'Provider-Ordered Dose Count', 'New patient records grouped by the prescribed number of doses.', $analytics['planned_doses'], 'bar'],
                        ['vaccineTypeChart', 'Prescribed Vaccine Type', 'New patient records grouped by the vaccine type on their plan.', $analytics['vaccine_types'], 'bar'],
                        ['barangayChart', 'Top 10 Patient Barangays', 'Barangays with the most patient registrations in this period.', $analytics['barangays'], 'bar'],
                        ['vaccineStockChart', 'Current Vaccine Stock by Type', 'Current inventory quantity grouped by vaccine name; this is not period-limited.', $analytics['inventory_by_vaccine'], 'bar'],
                        ['inventoryReceivedChart', 'Vaccine Stock Received by Date', 'Quantity of vaccine stock received on each date in the selected period.', $analytics['inventory_receipts_by_day'], 'bar'],
                        ['smsChart', 'SMS Delivery Status', 'SMS records created in this period grouped by delivery status.', $analytics['sms_statuses'], 'doughnut'],
                        ['auditChart', 'Recorded System Actions', 'Most frequent audit actions recorded during this period.', $analytics['audit_actions'], 'bar'],
                    ] as [$chartId, $chartTitle, $chartDescription, $chartData, $chartType])
                        <div class="rounded-lg bg-white p-5 shadow text-black">
                            <h3 class="mb-3 font-semibold">{{ $chartTitle }}</h3>
                            <p class="mb-3 min-h-10 text-sm text-slate-500">{{ $chartDescription }}</p>
                            @if ($chartData->isEmpty())
                                <p class="flex h-56 items-center justify-center text-sm text-slate-500">No records available.</p>
                            @else
                                <div class="relative h-56">
                                    <canvas id="{{ $chartId }}"></canvas>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                @php
                    $sections = [
                        ['Patient Registrations', ['Patient type' => $analytics['patient_types'], 'Sex' => $analytics['patient_sex'], 'Top barangays' => $analytics['barangays']]],
                        ['Animal Bite Assessments', ['Exposure category' => $analytics['exposure_categories'], 'Animal type' => $analytics['animal_types']]],
                        ['Queue Activity', ['Ticket status' => $analytics['ticket_statuses'], 'Service stage' => $analytics['ticket_stages'], 'Visit type' => $analytics['visit_types']]],
                        ['Vaccination Activity', ['Schedule status' => $analytics['schedule_statuses'], 'Vaccine type' => $analytics['vaccine_types'], 'Provider-ordered doses' => $analytics['planned_doses']]],
                        ['SMS Activity', ['Delivery status' => $analytics['sms_statuses']]],
                        ['System Audit Activity', ['Recorded actions' => $analytics['audit_actions']]],
                    ];
                @endphp
                @foreach ($sections as [$title, $groups])
                    <section class="rounded-lg bg-white p-5 shadow text-black">
                        <h3 class="mb-4 font-semibold">{{ $title }}</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            @foreach ($groups as $groupTitle => $items)
                                <div>
                                    <h4 class="mb-2 text-sm font-medium text-slate-600">{{ $groupTitle }}</h4>
                                    @if ($items->isEmpty())
                                        <p class="text-sm text-slate-500">No records for this period.</p>
                                    @else
                                        <ul class="space-y-1 text-sm">
                                            @foreach ($items as $label => $count)
                                                <li class="flex justify-between gap-3 border-b border-slate-100 py-1"><span>{{ ucfirst(str_replace('_', ' ', $label)) }}</span><strong>{{ $count }}</strong></li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endforeach
                <section class="rounded-lg bg-white p-5 shadow text-black">
                    <h3 class="mb-4 font-semibold">Vaccine Inventory Health</h3>
                    <div class="mb-4 grid grid-cols-3 gap-3 text-center text-sm">
                        <div class="rounded bg-amber-50 p-3"><strong class="block text-lg">{{ $analytics['low_stock_batches'] }}</strong>Low stock<p class="mt-1 text-xs text-slate-500">Batches at or below their reorder threshold.</p></div>
                        <div class="rounded bg-orange-50 p-3"><strong class="block text-lg">{{ $analytics['expiring_batches'] }}</strong>Expiring in 30 days<p class="mt-1 text-xs text-slate-500">Batches nearing their expiration date.</p></div>
                        <div class="rounded bg-red-50 p-3"><strong class="block text-lg">{{ $analytics['expired_batches'] }}</strong>Expired<p class="mt-1 text-xs text-slate-500">Batches past their expiration date.</p></div>
                    </div>
                    <h4 class="mb-2 text-sm font-medium text-slate-600">Current quantity by vaccine</h4>
                    @forelse ($analytics['inventory_by_vaccine'] as $vaccine => $quantity)
                        <div class="flex justify-between border-b border-slate-100 py-1 text-sm"><span>{{ $vaccine }}</span><strong>{{ $quantity }}</strong></div>
                    @empty
                        <p class="text-sm text-slate-500">No vaccine inventory recorded.</p>
                    @endforelse
                </section>
            </div>

            <p class="text-xs text-slate-600">Period activity covers <strong>{{ $from }}</strong> to <strong>{{ $to }}</strong>. Inventory health, stock on hand, and overdue follow-ups show current values.</p>
        </div>
    </div>

    @push('scripts')
    <script src="{{ asset('js/chart.min.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof Chart === 'undefined') return;
            const palette = ['#4f46e5', '#059669', '#f59e0b', '#ef4444', '#0ea5e9', '#8b5cf6', '#14b8a6', '#f97316', '#64748b', '#ec4899'];
            const renderChart = function (id, type, labels, values, horizontal = false, extraDatasets = []) {
                const canvas = document.getElementById(id);
                if (!canvas) return;
                const isCircular = type === 'doughnut';
                new Chart(canvas, {
                    type: type,
                    data: {
                        labels: labels,
                        datasets: [{
                            label: id === 'reportChart' ? 'Patients Registered' : undefined,
                            data: values,
                            backgroundColor: isCircular ? palette : (type === 'line' ? 'rgba(79,70,229,0.12)' : palette[0]),
                            borderColor: isCircular ? '#ffffff' : palette[0],
                            borderWidth: 1,
                            borderRadius: isCircular ? 0 : 4,
                            fill: type === 'line',
                            tension: type === 'line' ? 0.3 : 0,
                        }, ...extraDatasets]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        indexAxis: horizontal ? 'y' : 'x',
                        plugins: { legend: { display: isCircular || id === 'reportChart', position: 'bottom' } },
                        scales: isCircular ? {} : {
                            x: horizontal ? { beginAtZero: true, ticks: { precision: 0 } } : {},
                            y: horizontal ? {} : { beginAtZero: true, ticks: { precision: 0 } },
                        },
                    }
                });
            };
            renderChart('reportChart', 'line', @json($labels), @json($patientValues), false, [
                { label: 'Vaccinations Administered', data: @json($vaccinationValues), borderColor: '#059669', backgroundColor: 'rgba(5,150,105,0.08)', fill: false, tension: 0.3 },
                { label: 'SMS Delivered', data: @json($smsValues), borderColor: '#0f766e', backgroundColor: 'rgba(15,118,110,0.08)', fill: false, tension: 0.3 }
            ]);
            renderChart('patientTypeChart', 'doughnut', @json($analytics['patient_types']->keys()), @json($analytics['patient_types']->values()));
            renderChart('patientSexChart', 'doughnut', @json($analytics['patient_sex']->keys()), @json($analytics['patient_sex']->values()));
            renderChart('exposureChart', 'doughnut', @json($analytics['exposure_categories']->keys()), @json($analytics['exposure_categories']->values()));
            renderChart('animalTypeChart', 'bar', @json($analytics['animal_types']->keys()), @json($analytics['animal_types']->values()), true);
            renderChart('queueChart', 'bar', @json($analytics['ticket_statuses']->keys()), @json($analytics['ticket_statuses']->values()));
            renderChart('serviceStageChart', 'bar', @json($analytics['ticket_stages']->keys()), @json($analytics['ticket_stages']->values()), true);
            renderChart('visitTypeChart', 'doughnut', @json($analytics['visit_types']->keys()), @json($analytics['visit_types']->values()));
            renderChart('scheduleChart', 'bar', @json($analytics['schedule_statuses']->keys()), @json($analytics['schedule_statuses']->values()));
            renderChart('dosePlanChart', 'bar', @json($analytics['planned_doses']->keys()), @json($analytics['planned_doses']->values()));
            renderChart('vaccineTypeChart', 'bar', @json($analytics['vaccine_types']->keys()), @json($analytics['vaccine_types']->values()), true);
            renderChart('barangayChart', 'bar', @json($analytics['barangays']->keys()), @json($analytics['barangays']->values()), true);
            renderChart('vaccineStockChart', 'bar', @json($analytics['inventory_by_vaccine']->keys()), @json($analytics['inventory_by_vaccine']->values()), true);
            renderChart('inventoryReceivedChart', 'bar', @json($analytics['inventory_receipts_by_day']->keys()), @json($analytics['inventory_receipts_by_day']->values()));
            renderChart('smsChart', 'doughnut', @json($analytics['sms_statuses']->keys()), @json($analytics['sms_statuses']->values()));
            renderChart('auditChart', 'bar', @json($analytics['audit_actions']->keys()), @json($analytics['audit_actions']->values()), true);
        });
    </script>
    @endpush
</x-app-layout>
