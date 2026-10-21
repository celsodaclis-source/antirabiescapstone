<x-app-layout>
    <x-slot name="header">Queue Monitoring</x-slot>

    <div class="mx-auto max-w-6xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-sm text-slate-600">Monitor patients across nursing and doctor stations</p>
            </div>
            <span class="inline-flex items-center gap-2 rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800"><span class="h-2 w-2 rounded-full bg-emerald-500"></span>Live Queue</span>
        </div>

        <section aria-label="Queue statistics" class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            @foreach ([
                'nurse_waiting' => ['Nurse Waiting', 'Nurse patients awaiting assessment'],
                'doctor_waiting' => ['Doctor Waiting', 'Assessed patients awaiting consultation'],
                'nurse_serving' => ['Nurse Now Serving', 'Active nurse assessments'],
                'doctor_serving' => ['Doctor Now Serving', 'Active doctor consultations'],
            ] as $key => [$label, $description])
                <article class="rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                    <p class="text-xs font-medium text-slate-500">{{ $label }}</p>
                    <div id="stat-{{ $key }}" class="mt-1 text-2xl font-bold leading-7 text-slate-900">{{ $stats[$key] }}</div>
                    <p class="mt-1 text-xs text-slate-500">{{ $description }}</p>
                </article>
            @endforeach
        </section>

        <section class="space-y-3" aria-labelledby="nurse-stations-heading">
            <div class="flex items-center justify-between"><h3 id="nurse-stations-heading" class="text-base font-bold text-slate-900">Nurse Stations</h3><span class="text-xs text-slate-500">{{ $nurseStations->count() }} stations</span></div>
            <div id="nurse-stations" class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                @foreach ($nurseStations as $station)
                    @include('queue.partials.monitor-station', ['station' => $station, 'role' => 'nurse'])
                @endforeach
            </div>
        </section>

        <section class="space-y-3" aria-labelledby="doctor-stations-heading">
            <div class="flex items-center justify-between"><h3 id="doctor-stations-heading" class="text-base font-bold text-slate-900">Doctor Stations</h3><span class="text-xs text-slate-500">{{ $doctorStations->count() }} stations</span></div>
            <div id="doctor-stations" class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                @foreach ($doctorStations as $station)
                    @include('queue.partials.monitor-station', ['station' => $station, 'role' => 'doctor'])
                @endforeach
            </div>
        </section>

        <div class="flex items-center justify-between border-t border-slate-200 pt-3 text-xs text-slate-500">
            <span id="queue-update-error" class="hidden text-rose-700" role="status">Unable to refresh queue data. Showing the last successful update.</span>
            <span id="queue-updated" class="ml-auto">Updated {{ \Illuminate\Support\Carbon::parse($updated_at)->format('g:i:s A') }} · Refreshes every 8 seconds</span>
        </div>
    </div>

    @push('scripts')
    <script>
        (() => {
            let refreshing = false;
            const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
            const updateStations = (targetId, stations, assignmentKey) => {
                const target = document.getElementById(targetId);
                target.innerHTML = stations.map((station) => {
                    const serving = station.status === 'Serving';
                    const assigned = station[assignmentKey];
                    return `<article class="rounded-xl border border-slate-200 bg-white p-3 text-center shadow-sm sm:p-4">
                        <h4 class="text-sm font-semibold text-slate-800">${escapeHtml(station.name)}</h4>
                        <p class="mt-1 min-h-6 font-mono text-lg font-bold text-blue-900">${escapeHtml(station.ticket || '—')}</p>
                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${serving ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600'}">${serving ? 'Serving' : 'Idle'}</span>
                        <p class="mt-2 min-h-4 truncate text-[11px] text-slate-500" title="${escapeHtml(assigned ? `Assigned: ${assigned}` : 'No staff assigned')}">${escapeHtml(assigned ? `Assigned: ${assigned}` : 'No staff assigned')}</p>
                    </article>`;
                }).join('');
            };
            const refresh = async () => {
                if (refreshing || document.hidden) return;
                refreshing = true;
                try {
                    const response = await fetch(@json(route('queue-monitoring.data')), {
                        headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                        cache: 'no-store',
                    });
                    if (!response.ok) throw new Error(`HTTP ${response.status}`);
                    const data = await response.json();
                    Object.entries(data.stats).forEach(([key, value]) => {
                        const element = document.getElementById(`stat-${key}`);
                        if (element) element.textContent = value;
                    });
                    updateStations('nurse-stations', data.nurse_stations, 'assigned_nurse');
                    updateStations('doctor-stations', data.doctor_stations, 'assigned_doctor');
                    document.getElementById('queue-updated').textContent = `Updated ${new Date(data.updated_at).toLocaleTimeString()} · Refreshes every 8 seconds`;
                    document.getElementById('queue-update-error').classList.add('hidden');
                } catch (error) {
                    document.getElementById('queue-update-error').classList.remove('hidden');
                } finally {
                    refreshing = false;
                }
            };
            window.setInterval(refresh, 8000);
        })();
    </script>
    @endpush
</x-app-layout>
