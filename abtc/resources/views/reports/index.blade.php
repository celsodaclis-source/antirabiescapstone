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
                <div class="bg-white rounded-lg shadow p-5 border-l-4 border-blue-500 text-black">
                    <div class="text-sm text-black">Patients Registered</div>
                    <div class="text-3xl font-bold">{{ $stats['patients_registered'] }}</div>
                </div>
                <div class="bg-white rounded-lg shadow p-5 border-l-4 border-green-500 text-black">
                    <div class="text-sm text-black">Patients Served</div>
                    <div class="text-3xl font-bold">{{ $stats['patients_served'] }}</div>
                </div>
                <div class="bg-white rounded-lg shadow p-5 border-l-4 border-indigo-500 text-black">
                    <div class="text-sm text-black">Vaccinations Administered</div>
                    <div class="text-3xl font-bold">{{ $stats['vaccinations_administered'] }}</div>
                </div>
                <div class="bg-white rounded-lg shadow p-5 border-l-4 border-red-500 text-black">
                    <div class="text-sm text-black">Missed Appointments</div>
                    <div class="text-3xl font-bold">{{ $stats['missed_appointments'] }}</div>
                </div>
                <div class="bg-white rounded-lg shadow p-5 border-l-4 border-yellow-500 text-black">
                    <div class="text-sm text-black">Overdue Follow-ups</div>
                    <div class="text-3xl font-bold">{{ $stats['overdue_followups'] }}</div>
                </div>
                <div class="bg-white rounded-lg shadow p-5 border-l-4 border-purple-500 text-black">
                    <div class="text-sm text-black">Vaccine Stock on Hand</div>
                    <div class="text-3xl font-bold">{{ $stats['inventory_on_hand'] }}</div>
                </div>
                <div class="bg-white rounded-lg shadow p-5 border-l-4 border-teal-500 text-black">
                    <div class="text-sm text-black">New Stock Received</div>
                    <div class="text-3xl font-bold">{{ $stats['new_inventory_received'] }}</div>
                </div>
                <div class="bg-white rounded-lg shadow p-5 border-l-4 border-gray-500 text-black">
                    <div class="text-sm text-black">SMS Delivered</div>
                    <div class="text-3xl font-bold">{{ $stats['sms_delivered'] }}</div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-5 text-black">
                <h3 class="font-semibold text-black mb-3">Daily Registrations and Vaccinations</h3>
                <canvas id="reportChart" height="100"></canvas>
            </div>

            <section>
                <h2 class="mb-4 text-lg font-semibold text-slate-800">Operational Analytics</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                    @foreach ([
                        ['patientTypeChart', 'Patient Type', $analytics['patient_types'], 'doughnut'],
                        ['patientSexChart', 'Patient Sex', $analytics['patient_sex'], 'doughnut'],
                        ['exposureChart', 'Exposure Category', $analytics['exposure_categories'], 'doughnut'],
                        ['animalTypeChart', 'Animal Type', $analytics['animal_types'], 'bar'],
                        ['queueChart', 'Queue Status', $analytics['ticket_statuses'], 'bar'],
                        ['serviceStageChart', 'Queue Service Stage', $analytics['ticket_stages'], 'bar'],
                        ['visitTypeChart', 'Visit Type', $analytics['visit_types'], 'doughnut'],
                        ['scheduleChart', 'Vaccination Schedule Status', $analytics['schedule_statuses'], 'bar'],
                        ['dosePlanChart', 'Provider-Ordered Dose Count', $analytics['planned_doses'], 'bar'],
                        ['vaccineTypeChart', 'Prescribed Vaccine Type', $analytics['vaccine_types'], 'bar'],
                        ['barangayChart', 'Top 10 Patient Barangays', $analytics['barangays'], 'bar'],
                        ['vaccineStockChart', 'Current Vaccine Stock by Type', $analytics['inventory_by_vaccine'], 'bar'],
                        ['smsChart', 'SMS Delivery Status', $analytics['sms_statuses'], 'doughnut'],
                        ['auditChart', 'Recorded System Actions', $analytics['audit_actions'], 'bar'],
                    ] as [$chartId, $chartTitle, $chartData, $chartType])
                        <div class="rounded-lg bg-white p-5 shadow text-black">
                            <h3 class="mb-3 font-semibold">{{ $chartTitle }}</h3>
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
                        <div class="rounded bg-amber-50 p-3"><strong class="block text-lg">{{ $analytics['low_stock_batches'] }}</strong>Low stock</div>
                        <div class="rounded bg-orange-50 p-3"><strong class="block text-lg">{{ $analytics['expiring_batches'] }}</strong>Expiring in 30 days</div>
                        <div class="rounded bg-red-50 p-3"><strong class="block text-lg">{{ $analytics['expired_batches'] }}</strong>Expired</div>
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
                        plugins: { legend: { display: isCircular, position: 'bottom' } },
                        scales: isCircular ? {} : {
                            x: horizontal ? { beginAtZero: true, ticks: { precision: 0 } } : {},
                            y: horizontal ? {} : { beginAtZero: true, ticks: { precision: 0 } },
                        },
                    }
                });
            };
            renderChart('reportChart', 'line', @json($labels), @json($patientValues), false, [{ label: 'Vaccinations Administered', data: @json($vaccinationValues), borderColor: '#059669', backgroundColor: 'rgba(5,150,105,0.08)', fill: false, tension: 0.3 }]);
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
            renderChart('vaccineStockChart', 'bar', @json($analytics['inventory_by_vaccine']->keys()), @json($analytics['inventory_by_vaccine']->values()));
            renderChart('smsChart', 'doughnut', @json($analytics['sms_statuses']->keys()), @json($analytics['sms_statuses']->values()));
            renderChart('auditChart', 'bar', @json($analytics['audit_actions']->keys()), @json($analytics['audit_actions']->values()), true);
        });
    </script>
    @endpush
</x-app-layout>
