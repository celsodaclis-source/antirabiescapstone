<x-app-layout>
    <x-slot name="header">Doctor Dashboard</x-slot>

    <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="mb-7 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">Doctor Dashboard</h1>
                <p class="mt-1 text-sm text-slate-600">Select your station to begin patient consultations.</p>
            </div>
            <span class="inline-flex w-fit items-center gap-2 rounded-full px-3 py-1 text-sm font-semibold {{ $assignedStation ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700' }}">
                <span class="h-2 w-2 rounded-full {{ $assignedStation ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                {{ $assignedStation ? 'On Duty' : 'Off Duty' }}
            </span>
        </div>

        @if ($errors->has('station'))
            <div role="alert" class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first('station') }}</div>
        @endif

        <section aria-label="Doctor performance indicators" class="mb-8 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                'my_patients_today' => ['My Patients Today', 'Unique patients assigned today', 'users'],
                'consultations_completed' => ['Consultations Completed', 'Completed by you today', 'check'],
                'doctor_waiting' => ['Doctor Waiting', 'Eligible patients in the shared queue', 'queue'],
                'currently_serving' => ['Currently Serving', 'At your active station', 'clock'],
            ] as $key => [$label, $description, $icon])
                <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-2">
                        <div><p class="text-xs font-medium text-slate-500">{{ $label }}</p><p id="doctor-stat-{{ $key }}" class="mt-1 text-2xl font-bold text-slate-900">{{ $stats[$key] }}</p></div>
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-slate-100 text-slate-700" aria-hidden="true">
                            @if ($icon === 'users')
                                <x-icon name="users" class="h-4 w-4" />
                            @elseif ($icon === 'queue')
                                <x-icon name="queue" class="h-4 w-4" />
                            @elseif ($icon === 'clock')
                                <x-icon name="clock" class="h-4 w-4" />
                            @else
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/></svg>
                            @endif
                        </span>
                    </div>
                    <p class="mt-1 text-xs text-slate-500">{{ $description }}</p>
                </article>
            @endforeach
        </section>

        <section aria-label="Doctor Stations">
            <div class="mb-4">
                <h2 class="text-lg font-bold text-slate-900">Doctor Stations</h2>
                <p class="mt-0.5 text-sm text-slate-600">Select your assigned station</p>
            </div>

            @if ($assignedStation)
                <div class="mb-5 flex flex-col gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-emerald-900">You are assigned to <strong>{{ $assignedStation->name }}</strong>. Your patient queue is ready.</p>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('doctor.station.show') }}" class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Open Doctor Station</a>
                        <form method="POST" action="{{ route('doctor.stations.release') }}">
                            @csrf @method('DELETE')
                            <button class="rounded-lg border border-emerald-300 bg-white px-4 py-2 text-sm font-semibold text-emerald-900 hover:bg-emerald-100">Leave Station</button>
                        </form>
                    </div>
                </div>
            @endif

            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                @foreach ($stations as $station)
                    @php($occupied = $station->user_id !== null)
                    <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-slate-100 text-slate-800" aria-hidden="true">
                                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3v5a4 4 0 0 0 8 0V3M4 3h4M12 3h4M10 12v1a5 5 0 0 0 10 0v-1"/><circle cx="20" cy="10" r="2"/><path d="M17 18a3 3 0 1 0 6 0"/></svg>
                                </span>
                                <div>
                                    <h3 class="text-lg font-semibold text-slate-900">{{ $station->name }}</h3>
                                    <p class="mt-0.5 text-sm text-slate-600">
                                        {{ $occupied ? ($station->user_id === Auth::id() ? 'Assigned to you' : 'Assigned to another doctor') : 'No doctor assigned' }}
                                    </p>
                                    @if ($occupied)
                                        <p class="mt-1 text-xs text-slate-500">
                                            {{ $station->user_id === Auth::id() ? 'Current assignment: ' : 'Assigned doctor: ' }}{{ $station->doctor?->name }}
                                        </p>
                                    @endif
                                </div>
                            </div>
                            <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $occupied ? 'bg-slate-100 text-slate-700' : 'bg-emerald-100 text-emerald-800' }}">{{ $occupied ? 'In Use' : 'Available' }}</span>
                        </div>

                        <div class="mt-6">
                            @if ($occupied)
                                <button type="button" disabled class="w-full cursor-not-allowed rounded-lg bg-slate-100 px-4 py-3 text-sm font-semibold text-slate-500">In Use</button>
                            @else
                                <form method="POST" action="{{ route('doctor.stations.assign', $station) }}">
                                    @csrf
                                    <button type="submit" class="w-full rounded-lg bg-slate-950 px-4 py-3 text-sm font-semibold text-white hover:bg-slate-800">Open Station</button>
                                </form>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="mt-8 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm" aria-labelledby="consultation-activity-heading">
            <header class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 px-5 py-4">
                <div><h2 id="consultation-activity-heading" class="font-semibold text-slate-900">Today's Consultation Activity</h2><p class="mt-0.5 text-xs text-slate-500">Your consultations and active patient assignments today</p></div>
                <span id="activity-count" class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ $activity->total() }} records</span>
            </header>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[600px] text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Queue #</th><th class="px-5 py-3">Patient</th><th class="px-5 py-3">Station</th><th class="px-5 py-3">Status</th></tr></thead>
                    <tbody id="doctor-activity-rows" class="divide-y divide-slate-100">
                        @forelse ($activity as $item)
                            <tr>
                                <td class="whitespace-nowrap px-5 py-3 font-semibold text-blue-900">{{ $item['ticket_number'] }}</td>
                                <td class="px-5 py-3 text-slate-800">{{ $item['patient_name'] }}</td>
                                <td class="px-5 py-3 text-slate-700">{{ $item['station_name'] }}</td>
                                <td class="px-5 py-3"><span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $item['status'] === 'Completed' ? 'bg-emerald-100 text-emerald-800' : 'bg-blue-100 text-blue-800' }}">{{ $item['status'] }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-5 py-10 text-center text-sm text-slate-500">No consultation activity recorded today.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($activity->hasPages())
                <div class="border-t border-slate-200 px-5 py-3">{{ $activity->links() }}</div>
            @endif
        </section>

        <div class="mt-3 flex justify-end text-xs text-slate-500"><span id="doctor-dashboard-updated">Updated {{ \Illuminate\Support\Carbon::parse($updated_at)->format('g:i:s A') }} · Refreshes every 8 seconds</span><span id="doctor-dashboard-error" class="hidden text-rose-700" role="status">Unable to refresh dashboard information.</span></div>

        <p class="mt-6 text-center text-xs text-slate-500">Station availability reflects current database assignments. Assigned stations remain in use until the doctor leaves the station.</p>
    </div>

    @push('scripts')
    <script>
        (() => {
            let refreshing = false;
            const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
            const refreshDoctorDashboard = async () => {
                if (refreshing || document.hidden) return;
                refreshing = true;
                try {
                    const response = await fetch(@json(route('doctor.dashboard.data', ['page' => $activity->currentPage()])), {
                        headers: {'Accept':'application/json', 'X-Requested-With':'XMLHttpRequest'}, cache: 'no-store'
                    });
                    if (!response.ok) throw new Error(`HTTP ${response.status}`);
                    const data = await response.json();
                    Object.entries(data.stats).forEach(([key, value]) => {
                        const node = document.getElementById(`doctor-stat-${key}`);
                        if (node) node.textContent = value;
                    });
                    const rows = data.activity.data || [];
                    document.getElementById('doctor-activity-rows').innerHTML = rows.length ? rows.map((row) => `<tr>
                        <td class="whitespace-nowrap px-5 py-3 font-semibold text-blue-900">${esc(row.ticket_number)}</td>
                        <td class="px-5 py-3 text-slate-800">${esc(row.patient_name)}</td>
                        <td class="px-5 py-3 text-slate-700">${esc(row.station_name)}</td>
                        <td class="px-5 py-3"><span class="rounded-full px-2.5 py-1 text-xs font-semibold ${row.status === 'Completed' ? 'bg-emerald-100 text-emerald-800' : 'bg-blue-100 text-blue-800'}">${esc(row.status)}</span></td>
                    </tr>`).join('') : '<tr><td colspan="4" class="px-5 py-10 text-center text-sm text-slate-500">No consultation activity recorded today.</td></tr>';
                    document.getElementById('activity-count').textContent = `${data.activity.total} records`;
                    document.getElementById('doctor-dashboard-updated').textContent = `Updated ${new Date(data.updated_at).toLocaleTimeString()} · Refreshes every 8 seconds`;
                    document.getElementById('doctor-dashboard-error').classList.add('hidden');
                } catch (error) {
                    document.getElementById('doctor-dashboard-error').classList.remove('hidden');
                } finally {
                    refreshing = false;
                }
            };
            window.setInterval(refreshDoctorDashboard, 8000);
        })();
    </script>
    @endpush
</x-app-layout>
