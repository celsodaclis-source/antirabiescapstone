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

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                @php
                    $sections = [
                        ['Patient Registrations', ['Patient type' => $analytics['patient_types'], 'Top barangays' => $analytics['barangays']]],
                        ['Animal Bite Assessments', ['Exposure category' => $analytics['exposure_categories'], 'Animal type' => $analytics['animal_types']]],
                        ['Queue Activity', ['Ticket status' => $analytics['ticket_statuses'], 'Service stage' => $analytics['ticket_stages']]],
                        ['Vaccination Activity', ['Schedule status' => $analytics['schedule_statuses'], 'Vaccine type' => $analytics['vaccine_types']]],
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
            const canvas = document.getElementById('reportChart');
            if (!canvas || typeof Chart === 'undefined') return;
            new Chart(canvas, {
                type: 'line',
                data: {
                    labels: @json($labels),
                    datasets: [
                        { label: 'Patients Registered', data: @json($patientValues), borderColor: '#6366f1', backgroundColor: 'rgba(99,102,241,0.12)', fill: true, tension: 0.3 },
                        { label: 'Vaccinations Administered', data: @json($vaccinationValues), borderColor: '#059669', backgroundColor: 'rgba(5,150,105,0.08)', fill: false, tension: 0.3 }
                    ]
                },
                options: { responsive: true, plugins: { legend: { display: true, position: 'bottom' } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
            });
        });
    </script>
    @endpush
</x-app-layout>
