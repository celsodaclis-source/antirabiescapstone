<x-app-layout>
    <x-slot name="header">Queue Monitoring</x-slot>

    <main id="queue-monitoring-live" class="mx-auto max-w-6xl space-y-6 px-4 py-6 sm:px-6 lg:px-8" data-data-url="{{ route('queue-monitoring.data') }}">
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold text-slate-900">Unified Queue Monitoring</h1>
                <p class="mt-1 text-sm text-slate-600">Live overview of patients across nursing and doctor stations</p>
            </div>
            <span class="inline-flex items-center gap-2 rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800"><span class="h-2 w-2 rounded-full bg-emerald-500"></span>Live</span>
        </header>

        <section aria-label="Queue statistics" class="grid grid-cols-2 gap-3 md:grid-cols-3">
            @foreach ([
                'nurse_waiting' => ['Nurse Waiting', 'Awaiting assessment'],
                'doctor_waiting' => ['Doctor Waiting', 'Ready for consultation'],
                'now_serving' => ['Now Serving', 'Active patient services'],
                'on_hold' => ['On Hold', 'Waiting for recall'],
                'served_today' => ['Served Today', 'Completed tickets today'],
                'active_stations' => ['Active Stations', 'Stations currently serving'],
            ] as $key => [$label, $description])
                <article class="rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                    <p class="text-xs font-medium text-slate-500">{{ $label }}</p>
                    <p id="stat-{{ $key }}" class="mt-1 text-2xl font-bold leading-7 text-slate-900">{{ $stats[$key] }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ $description }}</p>
                </article>
            @endforeach
        </section>

        <section class="space-y-3" aria-labelledby="nurse-stations-heading">
            <div class="flex items-center justify-between"><h2 id="nurse-stations-heading" class="text-base font-bold text-slate-900">Nurse Stations</h2><span class="text-xs text-slate-500">{{ $nurseStations->count() }} stations</span></div>
            <div id="nurse-stations" class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                @foreach ($nurseStations as $station)
                    @include('queue.partials.monitor-station', ['station' => $station, 'role' => 'nurse'])
                @endforeach
            </div>
        </section>

        <section class="space-y-3" aria-labelledby="doctor-stations-heading">
            <div class="flex items-center justify-between"><h2 id="doctor-stations-heading" class="text-base font-bold text-slate-900">Doctor Stations</h2><span class="text-xs text-slate-500">{{ $doctorStations->count() }} stations</span></div>
            <div id="doctor-stations" class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                @foreach ($doctorStations as $station)
                    @include('queue.partials.monitor-station', ['station' => $station, 'role' => 'doctor'])
                @endforeach
            </div>
        </section>

        <section class="grid grid-cols-1 gap-4 border-t border-slate-200 pt-5 md:grid-cols-2" aria-label="Queue lists">
            @foreach ([['nurse_waiting', 'Nurse Waiting', $nurseWaiting, 'patients'], ['doctor_waiting', 'Doctor Waiting', $doctorWaiting, 'patients']] as [$key, $title, $items, $noun])
                <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <header class="mb-3 flex items-center justify-between"><h3 class="font-semibold text-slate-900">{{ $title }}</h3><span id="{{ $key }}-count" class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700">{{ count($items) }}</span></header>
                    <div id="{{ $key }}-preview" class="space-y-2">
                        @forelse (collect($items)->take(2) as $ticket)
                            <div class="rounded-lg border border-slate-200 p-2.5"><p class="text-sm font-semibold text-slate-900">{{ $ticket['ticket_number'] }}</p><p class="mt-0.5 text-xs text-slate-500">{{ $key === 'doctor_waiting' ? ($ticket['service_stage'] === 'Follow up' ? 'Follow-up' : 'Nurse assessed · Consultation') : ($ticket['visit_type'].' · '.$ticket['service_stage']) }}@if($ticket['dose_number']) · Dose {{ $ticket['dose_number'] }}@endif</p></div>
                        @empty
                            <p class="py-2 text-sm text-slate-500">No patients waiting.</p>
                        @endforelse
                    </div>
                    <details id="{{ $key }}-all-details" class="mt-3 border-t border-slate-100 pt-2" @if(count($items) <= 2) hidden @endif>
                        <summary id="{{ $key }}-all-label" class="cursor-pointer text-xs font-medium text-blue-800">View all {{ count($items) }} {{ $noun }}</summary>
                        <div id="{{ $key }}-all" class="mt-2 space-y-2">
                            @foreach ($items as $ticket)
                                <div class="rounded-lg border border-slate-200 p-2.5"><p class="text-sm font-semibold text-slate-900">{{ $ticket['ticket_number'] }}</p><p class="mt-0.5 text-xs text-slate-500">{{ $key === 'doctor_waiting' ? ($ticket['service_stage'] === 'Follow up' ? 'Follow-up' : 'Nurse assessed · Consultation') : ($ticket['visit_type'].' · '.$ticket['service_stage']) }}@if($ticket['dose_number']) · Dose {{ $ticket['dose_number'] }}@endif</p></div>
                            @endforeach
                        </div>
                    </details>
                </article>
            @endforeach

            <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <header class="mb-3 flex items-center justify-between"><h3 class="font-semibold text-slate-900">On Hold</h3><span id="held-count" class="rounded-full bg-orange-100 px-2 py-0.5 text-xs font-semibold text-orange-800">{{ count($held) }}</span></header>
                <div id="held-preview" class="space-y-2">
                    @forelse (collect($held)->take(2) as $ticket)
                        <div class="rounded-lg border border-slate-200 p-2.5"><p class="text-sm font-semibold text-slate-900">{{ $ticket['ticket_number'] }}</p><p class="mt-0.5 text-xs text-slate-500">{{ $ticket['service_stage'] }} · Waiting for recall</p><p class="text-xs text-slate-500">Held at {{ $ticket['updated_time'] }}</p></div>
                    @empty
                        <p class="py-2 text-sm text-slate-500">No tickets on hold.</p>
                    @endforelse
                </div>
                <details id="held-all-details" class="mt-3 border-t border-slate-100 pt-2" @if(count($held) <= 2) hidden @endif>
                    <summary id="held-all-label" class="cursor-pointer text-xs font-medium text-blue-800">View all held tickets</summary>
                    <div id="held-all" class="mt-2 space-y-2">
                        @foreach ($held as $ticket)
                            <div class="rounded-lg border border-slate-200 p-2.5"><p class="text-sm font-semibold text-slate-900">{{ $ticket['ticket_number'] }}</p><p class="mt-0.5 text-xs text-slate-500">{{ $ticket['service_stage'] }} · Waiting for recall</p><p class="text-xs text-slate-500">Held at {{ $ticket['updated_time'] }}</p></div>
                        @endforeach
                    </div>
                </details>
            </article>

            <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <header class="mb-3 flex items-center justify-between"><h3 class="font-semibold text-slate-900">Served Today</h3><span id="served-count" class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800">{{ count($servedToday) }}</span></header>
                <div id="served-preview" class="space-y-2">
                    @forelse (collect($servedToday)->take(2) as $ticket)
                        <div class="rounded-lg border border-slate-200 p-2.5"><p class="text-sm font-semibold text-slate-900">{{ $ticket['ticket_number'] }}</p><p class="mt-0.5 text-xs text-slate-500">{{ $ticket['station_name'] ?? $ticket['assigned_role'] ?? 'Queue' }} · Completed {{ $ticket['completed_time'] }}</p></div>
                    @empty
                        <p class="py-2 text-sm text-slate-500">No tickets completed today.</p>
                    @endforelse
                </div>
                <details id="served-all-details" class="mt-3 border-t border-slate-100 pt-2" @if(count($servedToday) <= 2) hidden @endif>
                    <summary id="served-all-label" class="cursor-pointer text-xs font-medium text-blue-800">View all served patients</summary>
                    <div id="served-all" class="mt-2 space-y-2">
                        @foreach ($servedToday as $ticket)
                            <div class="rounded-lg border border-slate-200 p-2.5"><p class="text-sm font-semibold text-slate-900">{{ $ticket['ticket_number'] }}</p><p class="mt-0.5 text-xs text-slate-500">{{ $ticket['station_name'] ?? $ticket['assigned_role'] ?? 'Queue' }} · Completed {{ $ticket['completed_time'] }}</p></div>
                        @endforeach
                    </div>
                </details>
            </article>
        </section>

        <footer class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 pt-3 text-xs text-slate-500">
            <span id="queue-update-error" class="hidden text-rose-700" role="status">Unable to refresh queue data. Showing the last successful update.</span>
            <span id="queue-updated" class="ml-auto">Updated {{ \Illuminate\Support\Carbon::parse($updated_at)->format('g:i:s A') }} · Refreshes every 8 seconds</span>
        </footer>
    </main>

    @push('scripts')
    <script>
        (() => {
            let refreshing = false;
            const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
            const updateStations = (targetId, stations, assignmentKey) => {
                document.getElementById(targetId).innerHTML = stations.map((station) => {
                    const serving = station.status === 'Serving';
                    const assigned = station[assignmentKey];
                    return `<article class="rounded-xl border border-slate-200 bg-white p-3 text-center shadow-sm sm:p-4"><h4 class="text-sm font-semibold text-slate-800">${esc(station.name)}</h4><p class="mt-1 min-h-6 font-mono text-lg font-bold text-blue-900">${esc(station.ticket || '—')}</p><span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${serving ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600'}">${esc(station.status)}</span><p class="mt-2 min-h-4 truncate text-[11px] text-slate-500">${esc(assigned ? `Assigned: ${assigned}` : 'No staff assigned')}</p></article>`;
                }).join('');
            };
            const itemHtml = (ticket, kind) => {
                if (kind === 'nurse_waiting') return `<div class="rounded-lg border border-slate-200 p-2.5"><p class="text-sm font-semibold text-slate-900">${esc(ticket.ticket_number)}</p><p class="mt-0.5 text-xs text-slate-500">${esc(ticket.visit_type)} · ${esc(ticket.service_stage)}${ticket.dose_number ? ` · Dose ${esc(ticket.dose_number)}` : ''}</p></div>`;
                if (kind === 'doctor_waiting') return `<div class="rounded-lg border border-slate-200 p-2.5"><p class="text-sm font-semibold text-slate-900">${esc(ticket.ticket_number)}</p><p class="mt-0.5 text-xs text-slate-500">${ticket.service_stage === 'Follow up' ? 'Follow-up' : 'Nurse assessed · Consultation'}${ticket.dose_number ? ` · Dose ${esc(ticket.dose_number)}` : ''}</p></div>`;
                if (kind === 'held') return `<div class="rounded-lg border border-slate-200 p-2.5"><p class="text-sm font-semibold text-slate-900">${esc(ticket.ticket_number)}</p><p class="mt-0.5 text-xs text-slate-500">${esc(ticket.service_stage)} · Waiting for recall</p><p class="text-xs text-slate-500">Held at ${esc(ticket.updated_time)}</p></div>`;
                return `<div class="rounded-lg border border-slate-200 p-2.5"><p class="text-sm font-semibold text-slate-900">${esc(ticket.ticket_number)}</p><p class="mt-0.5 text-xs text-slate-500">${esc(ticket.station_name || ticket.assigned_role || 'Queue')} · Completed ${esc(ticket.completed_time)}</p></div>`;
            };
            const updateListPanel = (key, title, items, noun = 'patients') => {
                const count = items.length;
                const preview = document.getElementById(`${key}-preview`);
                const allDetails = document.getElementById(`${key}-all-details`);
                const all = document.getElementById(`${key}-all`);
                document.getElementById(`${key}-count`).textContent = count;
                preview.innerHTML = count ? items.slice(0, 2).map(item => itemHtml(item, key)).join('') : `<p class="py-2 text-sm text-slate-500">${key === 'held' ? 'No tickets on hold.' : key === 'served' ? 'No tickets completed today.' : 'No patients waiting.'}</p>`;
                if (allDetails) {
                    allDetails.hidden = count <= 2;
                    all.innerHTML = items.map(item => itemHtml(item, key)).join('');
                    const label = document.getElementById(`${key}-all-label`);
                    if (label) label.textContent = key === 'held' ? `View all ${count} held tickets` : key === 'served' ? `View all ${count} served patients` : `View all ${count} ${noun}`;
                }
            };
            const refresh = async () => {
                if (refreshing || document.hidden) return;
                refreshing = true;
                try {
                    const root = document.getElementById('queue-monitoring-live');
                    const response = await fetch(root.dataset.dataUrl, {headers: {'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}, cache:'no-store'});
                    if (!response.ok) throw new Error(`HTTP ${response.status}`);
                    const data = await response.json();
                    Object.entries(data.stats).forEach(([key, value]) => {
                        const element = document.getElementById(`stat-${key}`);
                        if (element) element.textContent = value;
                    });
                    updateStations('nurse-stations', data.nurse_stations, 'assigned_nurse');
                    updateStations('doctor-stations', data.doctor_stations, 'assigned_doctor');
                    updateListPanel('nurse_waiting', 'Nurse Waiting', data.nurse_waiting || []);
                    updateListPanel('doctor_waiting', 'Doctor Waiting', data.doctor_waiting || []);
                    updateListPanel('held', 'On Hold', data.held || []);
                    updateListPanel('served', 'Served Today', data.served_today || []);
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
