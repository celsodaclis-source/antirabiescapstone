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

        <section id="admin-queue-panels" class="grid grid-cols-1 items-start gap-4 border-t border-slate-200 pt-6 lg:grid-cols-3"
            data-queue-data-url="{{ route('queue-monitoring.data', ['queue_page' => $waiting['current_page']]) }}"
            data-serve-template="{{ route('queue.serve', ['ticket' => '__TICKET__']) }}"
            data-complete-template="{{ route('queue.complete', ['ticket' => '__TICKET__']) }}"
            data-hold-template="{{ route('queue.hold', ['ticket' => '__TICKET__']) }}"
            data-recall-template="{{ route('queue.call', ['ticket' => '__TICKET__']) }}"
            data-call-next-url="{{ route('queue.call-next') }}"
            data-csrf="{{ csrf_token() }}">
            <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm" aria-labelledby="admin-now-serving-heading">
                <h2 id="admin-now-serving-heading" class="mb-3 font-semibold text-slate-900">Now Serving <span id="admin-now-serving-count" class="text-sm font-normal text-slate-500">({{ count($nowServing) }})</span></h2>
                <div id="admin-now-serving-list" class="space-y-2">
                    @forelse ($nowServing as $ticket)
                        <article class="rounded-lg border border-indigo-200 bg-indigo-50 p-3" data-queue-ticket="{{ $ticket['id'] }}">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div><p class="font-bold text-slate-900">{{ $ticket['ticket_number'] }}</p><p class="text-sm text-slate-800">{{ $ticket['patient_name'] }}</p><p class="text-xs text-violet-800">{{ $ticket['visit_type'] }} · {{ $ticket['service_stage'] }}@if($ticket['dose_number']) · Dose {{ $ticket['dose_number'] }}@endif</p><p class="text-xs text-slate-600">Counter {{ $ticket['counter'] }} · {{ $ticket['assigned_staff'] }}</p></div>
                                <span class="rounded-full bg-blue-100 px-2 py-1 text-xs font-medium text-blue-800">{{ ucfirst(str_replace('_', ' ', $ticket['status'])) }}</span>
                            </div>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <form method="POST" action="{{ route('queue.serve', ['ticket' => $ticket['id']]) }}">@csrf<button class="rounded bg-cyan-600 px-2.5 py-1.5 text-xs font-semibold text-white">Serve</button></form>
                                <form method="POST" action="{{ route('queue.complete', ['ticket' => $ticket['id']]) }}">@csrf<button class="rounded bg-green-600 px-2.5 py-1.5 text-xs font-semibold text-white">Done</button></form>
                                <form method="POST" action="{{ route('queue.hold', ['ticket' => $ticket['id']]) }}">@csrf<button class="rounded bg-amber-600 px-2.5 py-1.5 text-xs font-semibold text-white">Hold / Skip</button></form>
                            </div>
                        </article>
                    @empty
                        <p class="py-4 text-sm text-slate-600">No ticket currently being served.</p>
                    @endforelse
                </div>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm" aria-labelledby="admin-waiting-heading">
                <h2 id="admin-waiting-heading" class="mb-3 font-semibold text-slate-900">Waiting <span id="admin-waiting-count" class="text-sm font-normal text-slate-500">({{ $waiting['total'] }})</span></h2>
                <ol id="admin-waiting-list" class="space-y-2">
                    @forelse ($waiting['data'] as $ticket)
                        <li class="rounded-lg border p-3 {{ $ticket['is_priority'] ? 'border-red-200 bg-red-50' : 'border-amber-200 bg-amber-50' }}" data-queue-ticket="{{ $ticket['id'] }}">
                            <div class="flex items-center justify-between gap-2"><div><p class="text-sm"><strong>{{ $ticket['ticket_number'] }}</strong> · {{ $ticket['patient_name'] }}</p><p class="text-xs text-violet-800">{{ $ticket['visit_type'] }} · {{ $ticket['service_stage'] }}@if($ticket['dose_number']) · Dose {{ $ticket['dose_number'] }}@endif</p>@if($ticket['is_priority'])<span class="mt-1 inline-block rounded-full bg-red-500 px-2 py-0.5 text-[10px] text-white">{{ $ticket['patient_type'] }}</span>@endif</div><span class="shrink-0 text-xs text-slate-500">Next in queue</span></div>
                        </li>
                    @empty
                        <li class="py-4 text-sm text-slate-600">No one waiting.</li>
                    @endforelse
                </ol>
                <div class="mt-3 border-t border-slate-100 pt-3">
                    <p id="admin-waiting-range" class="mb-2 text-xs text-slate-500">@if($waiting['from']) Showing {{ $waiting['from'] }} to {{ $waiting['to'] }} of {{ $waiting['total'] }} results @else No waiting tickets @endif</p>
                    <nav id="admin-queue-pagination" aria-label="Admin queue waiting list pagination">@if ($waitingPaginator->hasPages()){{ $waitingPaginator->onEachSide(5)->links() }}@endif</nav>
                    <form method="POST" action="{{ route('queue.call-next') }}" class="mt-3 flex items-center gap-2">@csrf<label class="sr-only" for="admin-call-counter">Select counter</label><select id="admin-call-counter" name="counter" class="rounded border-slate-300 text-sm">@foreach([1,2,3,4] as $counter)<option value="{{ $counter }}">Counter {{ $counter }}</option>@endforeach</select><button class="rounded bg-green-600 px-3 py-2 text-sm font-semibold text-white hover:bg-green-700">Call Next</button></form>
                </div>
            </section>

            <div class="space-y-4">
                <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm" aria-labelledby="admin-held-heading">
                    <h2 id="admin-held-heading" class="mb-3 font-semibold text-slate-900">On Hold <span id="admin-held-count" class="text-sm font-normal text-slate-500">({{ count($held) }})</span></h2>
                    <ul id="admin-held-list" class="space-y-2">
                        @forelse ($held as $ticket)
                            <li class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-slate-200 bg-slate-50 p-2" data-queue-ticket="{{ $ticket['id'] }}"><span class="text-sm"><strong>{{ $ticket['ticket_number'] }}</strong> — {{ $ticket['patient_name'] }}</span><form method="POST" action="{{ route('queue.call', ['ticket' => $ticket['id']]) }}" class="flex items-center gap-1">@csrf<label class="sr-only" for="recall-counter-{{ $ticket['id'] }}">Recall counter</label><select id="recall-counter-{{ $ticket['id'] }}" name="counter" class="rounded border-slate-300 text-xs">@foreach([1,2,3,4] as $counter)<option value="{{ $counter }}">C{{ $counter }}</option>@endforeach</select><button class="rounded bg-indigo-600 px-2 py-1 text-xs font-semibold text-white">Recall</button></form></li>
                        @empty
                            <li class="text-sm text-slate-600">None on hold.</li>
                        @endforelse
                    </ul>
                </section>
                <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm" aria-labelledby="admin-served-heading">
                    <h2 id="admin-served-heading" class="mb-3 font-semibold text-slate-900">Served Today <span id="admin-served-count" class="text-sm font-normal text-slate-500">({{ count($servedToday) }})</span></h2>
                    <ul id="admin-served-list" class="divide-y divide-slate-100 text-sm">
                        @forelse ($servedToday as $ticket)
                            <li class="flex justify-between gap-3 py-2"><span>{{ $ticket['ticket_number'] }} — {{ $ticket['patient_name'] }}</span><span class="shrink-0 text-slate-500">{{ $ticket['completed_time'] }}</span></li>
                        @empty
                            <li class="py-2 text-slate-600">None completed yet.</li>
                        @endforelse
                    </ul>
                </section>
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
            const form = (url, label, classes, csrf) => `<form method="POST" action="${url}"><input type="hidden" name="_token" value="${csrf}"><button class="rounded px-2.5 py-1.5 text-xs font-semibold text-white ${classes}">${label}</button></form>`;
            const routeFor = (template, id) => template.replace('__TICKET__', encodeURIComponent(id));
            const updateQueuePanels = (root, data) => {
                const csrf = root.dataset.csrf;
                const active = data.now_serving || [];
                document.getElementById('admin-now-serving-count').textContent = `(${active.length})`;
                document.getElementById('admin-now-serving-list').innerHTML = active.length ? active.map((ticket) => `<article class="rounded-lg border border-indigo-200 bg-indigo-50 p-3"><div class="flex flex-wrap items-start justify-between gap-2"><div><p class="font-bold text-slate-900">${escapeHtml(ticket.ticket_number)}</p><p class="text-sm text-slate-800">${escapeHtml(ticket.patient_name)}</p><p class="text-xs text-violet-800">${escapeHtml(ticket.visit_type)} · ${escapeHtml(ticket.service_stage)}${ticket.dose_number ? ` · Dose ${escapeHtml(ticket.dose_number)}` : ''}</p><p class="text-xs text-slate-600">Counter ${escapeHtml(ticket.counter ?? '—')} · ${escapeHtml(ticket.assigned_staff ?? '')}</p></div><span class="rounded-full bg-blue-100 px-2 py-1 text-xs font-medium text-blue-800">${escapeHtml(ticket.status.replaceAll('_', ' '))}</span></div><div class="mt-3 flex flex-wrap gap-2">${form(routeFor(root.dataset.serveTemplate, ticket.id), 'Serve', 'bg-cyan-600', csrf)}${form(routeFor(root.dataset.completeTemplate, ticket.id), 'Done', 'bg-green-600', csrf)}${form(routeFor(root.dataset.holdTemplate, ticket.id), 'Hold / Skip', 'bg-amber-600', csrf)}</div></article>`).join('') : '<p class="py-4 text-sm text-slate-600">No ticket currently being served.</p>';

                const waiting = data.waiting || {data: [], total: 0};
                document.getElementById('admin-waiting-count').textContent = `(${waiting.total})`;
                document.getElementById('admin-waiting-range').textContent = waiting.from ? `Showing ${waiting.from} to ${waiting.to} of ${waiting.total} results` : 'No waiting tickets';
                document.getElementById('admin-waiting-list').innerHTML = waiting.data.length ? waiting.data.map((ticket) => `<li class="rounded-lg border p-3 ${ticket.is_priority ? 'border-red-200 bg-red-50' : 'border-amber-200 bg-amber-50'}"><div class="flex items-center justify-between gap-2"><div><p class="text-sm"><strong>${escapeHtml(ticket.ticket_number)}</strong> · ${escapeHtml(ticket.patient_name)}</p><p class="text-xs text-violet-800">${escapeHtml(ticket.visit_type)} · ${escapeHtml(ticket.service_stage)}${ticket.dose_number ? ` · Dose ${escapeHtml(ticket.dose_number)}` : ''}</p>${ticket.is_priority ? `<span class="mt-1 inline-block rounded-full bg-red-500 px-2 py-0.5 text-[10px] text-white">${escapeHtml(ticket.patient_type)}</span>` : ''}</div><span class="shrink-0 text-xs text-slate-500">Next in queue</span></div></li>`).join('') : '<li class="py-4 text-sm text-slate-600">No one waiting.</li>';

                const held = data.held || [];
                document.getElementById('admin-held-count').textContent = `(${held.length})`;
                document.getElementById('admin-held-list').innerHTML = held.length ? held.map((ticket) => `<li class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-slate-200 bg-slate-50 p-2"><span class="text-sm"><strong>${escapeHtml(ticket.ticket_number)}</strong> — ${escapeHtml(ticket.patient_name)}</span><form method="POST" action="${routeFor(root.dataset.recallTemplate, ticket.id)}" class="flex items-center gap-1"><input type="hidden" name="_token" value="${csrf}"><label class="sr-only" for="recall-counter-${ticket.id}">Recall counter</label><select id="recall-counter-${ticket.id}" name="counter" class="rounded border-slate-300 text-xs">${[1,2,3,4].map(counter => `<option value="${counter}">C${counter}</option>`).join('')}</select><button class="rounded bg-indigo-600 px-2 py-1 text-xs font-semibold text-white">Recall</button></form></li>`).join('') : '<li class="text-sm text-slate-600">None on hold.</li>';

                const served = data.served_today || [];
                document.getElementById('admin-served-count').textContent = `(${served.length})`;
                document.getElementById('admin-served-list').innerHTML = served.length ? served.map(ticket => `<li class="flex justify-between gap-3 py-2"><span>${escapeHtml(ticket.ticket_number)} — ${escapeHtml(ticket.patient_name)}</span><span class="shrink-0 text-slate-500">${escapeHtml(ticket.completed_time)}</span></li>`).join('') : '<li class="py-2 text-slate-600">None completed yet.</li>';

                const pagination = document.getElementById('admin-queue-pagination');
                if (pagination) {
                    const page = Number(waiting.current_page || 1);
                    const last = Number(waiting.last_page || 1);
                    if (page > last) { window.location.reload(); return; }
                    const urlForPage = number => { const url = new URL(window.location.href); url.searchParams.set('queue_page', number); return url.toString(); };
                    const pageLinks = [];
                    for (let number = Math.max(1, page - 5); number <= Math.min(last, page + 5); number++) {
                        pageLinks.push(`<a href="${urlForPage(number)}" aria-current="${number === page ? 'page' : 'false'}" class="inline-flex h-8 min-w-8 items-center justify-center border px-2 text-sm ${number === page ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50'}">${number}</a>`);
                    }
                    pagination.innerHTML = last > 1 ? `<div class="flex flex-wrap gap-1"><a href="${urlForPage(Math.max(1,page-1))}" aria-label="Previous page" class="inline-flex h-8 items-center border border-slate-300 bg-white px-2 text-sm text-slate-700">‹</a>${pageLinks.join('')}<a href="${urlForPage(Math.min(last,page+1))}" aria-label="Next page" class="inline-flex h-8 items-center border border-slate-300 bg-white px-2 text-sm text-slate-700">›</a></div>` : '';
                }
            };
            const refresh = async () => {
                if (refreshing || document.hidden) return;
                refreshing = true;
                try {
                    const root = document.getElementById('admin-queue-panels');
                    const response = await fetch(root.dataset.queueDataUrl, {
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
                    updateQueuePanels(root, data);
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
