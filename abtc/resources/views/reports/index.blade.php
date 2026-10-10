<x-app-layout>
    <x-slot name="header">Reports</x-slot>

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
                    <div class="text-sm text-black">Total Vaccine Stock</div>
                    <div class="text-3xl font-bold">{{ $stats['inventory_used'] }}</div>
                </div>
                <div class="bg-white rounded-lg shadow p-5 border-l-4 border-teal-500 text-black">
                    <div class="text-sm text-black">New Stock Received</div>
                    <div class="text-3xl font-bold">{{ $stats['new_inventory_received'] }}</div>
                </div>
                <div class="bg-white rounded-lg shadow p-5 border-l-4 border-gray-500 text-black">
                    <div class="text-sm text-black">SMS Delivered</div>
                    <div class="text-3xl font-bold">{{ $stats['sms_sent'] }}</div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-5 text-black">
                <h3 class="font-semibold text-black mb-3 text-black">Patient Volume &mdash; {{ now()->format('F Y') }}</h3>
                <canvas id="reportChart" height="100"></canvas>
            </div>

            <p class="text-xs text-black">Report for period <strong class="text-black">{{ $from }}</strong> to <strong class="text-black">{{ $to }}</strong>. Exports are intended for submission to the Mandaluyong City Health Office and DOH.</p>
        </div>
    </div>

    @push('scripts')
    <script src="{{ asset('js/chart.min.js') }}"></script>
    <script>
        new Chart(document.getElementById('reportChart'), {
            type: 'line',
            data: {
                labels: @json($labels),
                datasets: [{
                    label: 'Patients Registered',
                    data: @json($values),
                    borderColor: '#6366f1',
                    backgroundColor: 'rgba(99,102,241,0.15)',
                    fill: true,
                    tension: 0.3,
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
            }
        });
    </script>
    @endpush
</x-app-layout>
