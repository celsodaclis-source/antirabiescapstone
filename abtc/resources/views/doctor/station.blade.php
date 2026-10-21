<x-app-layout>
    <x-slot name="header">{{ $station->name }} — Consultation</x-slot>

    <div id="doctor-station-live" data-current-ticket-id="{{ $currentTicket?->id ?? '' }}" data-current-status="{{ $currentTicket?->status ?? '' }}" data-plan-ready="{{ $planReady ? '1' : '0' }}" class="mx-auto max-w-6xl space-y-6 px-4 py-7 sm:px-6 lg:px-8">
        <header class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h1 class="text-xl font-bold text-slate-900">Doctor Station {{ str($station->name)->after('Doctor ')->toString() }}</h1>
                    <p class="mt-1 text-sm text-slate-600">Consultation and patient queue management</p>
                </div>
                <span class="inline-flex w-fit items-center gap-2 rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800"><span class="h-2 w-2 rounded-full bg-emerald-500"></span>Station Active</span>
            </div>

            <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="rounded-lg bg-slate-50 px-4 py-3">
                    <p class="text-xs font-medium text-slate-600">Doctor Waiting</p>
                    <p id="doctor-station-waiting-stat" class="mt-1 text-2xl font-bold text-slate-950">{{ $doctorWaiting }}</p>
                </div>
                <div class="rounded-lg bg-slate-50 px-4 py-3">
                    <p class="text-xs font-medium text-slate-600">Patients Consulted Today</p>
                    <p id="doctor-station-completed-stat" class="mt-1 text-2xl font-bold text-slate-950">{{ $patientsConsultedToday }}</p>
                </div>
            </div>
        </header>

        @if ($errors->has('consultation') || $errors->has('station'))
            <div role="alert" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first('consultation') ?: $errors->first('station') }}</div>
        @endif

        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="now-serving-title">
            <div class="mb-5 flex items-center justify-between gap-3">
                <h2 id="now-serving-title" class="text-lg font-bold text-slate-900">Now Serving</h2>
                @if ($currentTicket)
                    <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-800">{{ $currentTicket->status === 'in_progress' ? 'In Consultation' : 'Called' }}</span>
                @endif
            </div>

            @if ($currentTicket)
                @php
                    $patient = $currentTicket->patient;
                    $assessedBy = $currentTicket->assessmentCompletedBy?->name;
                @endphp
                <div class="mb-5 border-b border-slate-200 pb-4">
                    <p class="text-2xl font-bold tracking-wide text-blue-800">{{ $currentTicket->ticket_number }}</p>
                    <h3 class="mt-1 font-semibold text-slate-900">Patient Consultation · {{ $patient->fullName() }}</h3>
                    <p class="mt-1 text-sm text-slate-600">Assigned Doctor: {{ $currentTicket->assignedStaff?->name ?? Auth::user()->name }} · {{ $currentTicket->status === 'in_progress' ? 'Consultation in progress' : 'Patient called' }}</p>
                </div>

                <dl class="divide-y divide-slate-100 text-sm">
                    <div class="flex items-center justify-between gap-4 py-3"><dt class="font-medium text-slate-700">Assessment Status</dt><dd>@if ($currentTicket->assessment_completed_at)<span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-800">Nurse Completed</span>@if ($assessedBy)<span class="ml-2 text-xs text-slate-500">{{ $assessedBy }}</span>@endif @elseif ($currentTicket->service_stage === 'follow_up')<span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">Assessment Not Required</span>@else<span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800">Pending Nurse Assessment</span>@endif</dd></div>
                    <div class="flex items-center justify-between gap-4 py-3"><dt class="font-medium text-slate-700">Patient Record</dt><dd><span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $patientRecordReady ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">{{ $patientRecordReady ? 'Ready for Review' : 'Incomplete Registration' }}</span></dd></div>
                    <div class="flex items-center justify-between gap-4 py-3"><dt class="font-medium text-slate-700">Queue Status</dt><dd><span class="rounded-full bg-blue-100 px-2.5 py-1 text-xs font-semibold text-blue-800">{{ $currentTicket->status === 'in_progress' ? 'Now Serving' : 'Called' }}</span></dd></div>
                </dl>

                @if ($currentTicket->assessment_completed_at)
                    @php($biteAssessment = $patient->biteIncidents->sortBy('date_of_bite')->first())
                    <section class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50/60 p-4" aria-label="Saved nursing assessment">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h4 class="text-sm font-semibold text-slate-900">Nursing Assessment</h4>
                            <span class="text-xs text-slate-600">{{ $assessedBy ? 'Completed by '.$assessedBy : 'Nurse assessment completed' }} · {{ $currentTicket->assessment_completed_at->format('M j, Y g:i A') }}</span>
                        </div>
                        @if ($biteAssessment)
                            <dl class="mt-3 grid grid-cols-1 gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
                                <div><dt class="text-xs font-medium text-slate-500">Date of bite</dt><dd class="mt-0.5 text-slate-800">{{ $biteAssessment->date_of_bite?->format('M j, Y') ?? '—' }}</dd></div>
                                <div><dt class="text-xs font-medium text-slate-500">Animal / bite site</dt><dd class="mt-0.5 text-slate-800">{{ $biteAssessment->animal_type }} · {{ $biteAssessment->bite_site ?: '—' }}</dd></div>
                                <div><dt class="text-xs font-medium text-slate-500">Animal status</dt><dd class="mt-0.5 text-slate-800">{{ $biteAssessment->animal_status ?: '—' }}</dd></div>
                                <div><dt class="text-xs font-medium text-slate-500">Animal vaccinated</dt><dd class="mt-0.5 text-slate-800">{{ $biteAssessment->animal_vaccinated ? 'Yes' : 'No' }}</dd></div>
                            </dl>
                        @else
                            <p class="mt-2 text-sm text-amber-800">The ticket is marked assessed, but its bite incident record is unavailable.</p>
                        @endif
                    </section>
                @endif

                @if ($patient->vaccinationSchedules->isNotEmpty())
                    <section class="mt-4 rounded-lg border border-slate-200 p-4" aria-label="Vaccination history">
                        <h4 class="text-sm font-semibold text-slate-900">Vaccination History</h4>
                        <div class="mt-2 overflow-x-auto">
                            <table class="w-full min-w-[360px] text-left text-xs">
                                <thead class="text-slate-500"><tr><th class="py-2 pr-3">Dose</th><th class="py-2 pr-3">Scheduled</th><th class="py-2">Status</th></tr></thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach ($patient->vaccinationSchedules->sortBy('dose_number') as $dose)
                                        <tr><td class="py-2 pr-3 text-slate-700">Dose {{ $dose->dose_number }}</td><td class="py-2 pr-3 text-slate-700">{{ $dose->scheduled_date?->format('M j, Y') ?? '—' }}</td><td class="py-2 text-slate-700">{{ ucfirst($dose->status) }}</td></tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>
                @endif

                <div class="mt-5 flex flex-wrap gap-3">
                    <a href="{{ route('patients.show', $patient) }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 hover:bg-slate-50">View Patient Record</a>
                    @if (! $planReady)
                        <a href="{{ route('patients.plan', $patient) }}" class="rounded-lg border border-blue-300 bg-blue-50 px-4 py-2.5 text-sm font-semibold text-blue-900 hover:bg-blue-100">Complete Vaccination Plan</a>
                    @endif
                </div>

                @if ($currentTicket->status === 'called')
                    <form method="POST" action="{{ route('queue.serve', $currentTicket) }}" class="mt-5">@csrf<button class="w-full rounded-lg bg-slate-950 px-5 py-3 text-sm font-semibold text-white hover:bg-slate-800">Start Consultation</button></form>
                @elseif ($currentTicket->status === 'in_progress')
                    <form method="POST" action="{{ route('doctor.station.complete', $currentTicket) }}" class="mt-5">
                        @csrf
                        <button @disabled(! $planReady) class="flex w-full items-center justify-center gap-2 rounded-lg px-5 py-3 text-sm font-semibold text-white {{ $planReady ? 'bg-slate-950 hover:bg-slate-800' : 'cursor-not-allowed bg-slate-400' }}">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="m8 12 2.5 2.5L16 9"/></svg>
                            Complete Consultation
                        </button>
                        @if (! $planReady)<p class="mt-2 text-center text-xs text-amber-800">Record the required exposure assessment and provider-ordered vaccination plan before completing this consultation.</p>@endif
                    </form>
                @endif
            @else
                <div class="rounded-lg border border-dashed border-slate-300 bg-slate-50 px-5 py-8 text-center">
                    <p class="font-medium text-slate-800">No patient is currently assigned to this station.</p>
                    <p class="mt-1 text-sm text-slate-600">Call the next patient with a completed nurse assessment when ready.</p>
                </div>
            @endif

            <form method="POST" action="{{ route('doctor.station.call-next') }}" class="mt-4">
                @csrf
                <button @disabled($callDisabled) class="flex w-full items-center justify-center gap-2 rounded-lg border px-5 py-3 text-sm font-semibold {{ $callDisabled ? 'cursor-not-allowed border-slate-200 bg-slate-100 text-slate-500' : 'border-slate-400 bg-white text-slate-900 hover:bg-slate-50' }}">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 11 18-5-5 18-4-8-9-5Z"/><path d="m12 16 5-5"/></svg>
                    Call Next Patient
                </button>
                @if ($currentTicket)<p class="mt-2 text-center text-xs text-slate-500">Complete or resolve the current consultation before calling another patient.</p>@elseif ($stationBusy)<p class="mt-2 text-center text-xs text-slate-500">This station's queue counter is currently serving another ticket.</p>@elseif ($doctorHasActiveTicket)<p class="mt-2 text-center text-xs text-slate-500">Resolve your active ticket in the queue before calling another patient.</p>@endif
            </form>
        </section>

        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm" aria-labelledby="waiting-list-title">
            <header class="flex flex-col gap-2 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div><h2 id="waiting-list-title" class="text-lg font-bold text-slate-900">Doctor Waiting List</h2><p class="mt-1 text-sm text-slate-600">Patients with completed nurse assessment, ordered by queue priority.</p></div>
                <span id="doctor-station-waiting-count" class="w-fit rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800">{{ $doctorWaiting }} Waiting</span>
            </header>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[600px] text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-600"><tr><th class="px-5 py-3">Queue #</th><th class="px-5 py-3">Nurse Assessment</th><th class="px-5 py-3">Status</th></tr></thead>
                    <tbody id="doctor-station-waiting-rows" class="divide-y divide-slate-100">
                        @forelse ($waiting as $ticket)
                            <tr><td class="px-5 py-3 font-semibold text-slate-900">{{ $ticket->ticket_number }}</td><td class="px-5 py-3"><span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-800">Completed</span></td><td class="px-5 py-3"><span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800">{{ ucfirst($ticket->status) }}</span></td></tr>
                        @empty
                            <tr><td colspan="3" class="px-5 py-9 text-center text-slate-600">No patients are currently waiting for a doctor.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($waiting->hasPages())<div class="border-t border-slate-200 px-5 py-4">{{ $waiting->links() }}</div>@endif
        </section>

        <footer class="flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('dashboard') }}" class="text-sm font-medium text-slate-700 underline decoration-slate-300 underline-offset-4">Back to Doctor Dashboard</a>
            @if (! $currentTicket)
                <form method="POST" action="{{ route('doctor.stations.release') }}">@csrf @method('DELETE')<button class="text-sm font-medium text-slate-600 underline decoration-slate-300 underline-offset-4">Leave Station</button></form>
            @endif
        </footer>
    </div>
    <div class="mx-auto max-w-6xl px-4 pb-4 text-right text-xs text-slate-500 sm:px-6 lg:px-8"><span id="doctor-station-refresh-error" class="hidden text-rose-700" role="status">Unable to refresh station queue.</span><span id="doctor-station-updated">Queue refreshes every 8 seconds.</span></div>
    @push('scripts')
    <script>
        (() => {
            let refreshing = false;
            const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
            const refresh = async () => {
                if (refreshing || document.hidden) return;
                refreshing = true;
                try {
                    const response = await fetch(@json(route('doctor.station.data', ['page' => $waiting->currentPage()])), {headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'},cache:'no-store'});
                    if (!response.ok) throw new Error('Queue refresh failed');
                    const data = await response.json();
                    const root = document.getElementById('doctor-station-live');
                    const currentId = root.dataset.currentTicketId || '';
                    const currentStatus = root.dataset.currentStatus || '';
                    if (String(data.current_ticket_id ?? '') !== currentId || (data.current_status ?? '') !== currentStatus || String(Number(data.plan_ready)) !== root.dataset.planReady) {
                        window.location.reload();
                        return;
                    }
                    document.getElementById('doctor-station-waiting-stat').textContent = data.waiting_count;
                    document.getElementById('doctor-station-waiting-count').textContent = `${data.waiting_count} Waiting`;
                    document.getElementById('doctor-station-completed-stat').textContent = data.patients_consulted_today;
                    document.getElementById('doctor-station-waiting-rows').innerHTML = data.waiting.length ? data.waiting.map((row) => `<tr><td class="px-5 py-3 font-semibold text-slate-900">${esc(row.ticket_number)}</td><td class="px-5 py-3"><span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-800">Completed</span></td><td class="px-5 py-3"><span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800">${esc(row.status)}</span></td></tr>`).join('') : '<tr><td colspan="3" class="px-5 py-9 text-center text-slate-600">No patients are currently waiting for a doctor.</td></tr>';
                    document.getElementById('doctor-station-updated').textContent = `Updated ${new Date(data.updated_at).toLocaleTimeString()} · Refreshes every 8 seconds`;
                    document.getElementById('doctor-station-refresh-error').classList.add('hidden');
                } catch (error) { document.getElementById('doctor-station-refresh-error').classList.remove('hidden'); }
                finally { refreshing = false; }
            };
            window.setInterval(refresh, 8000);
        })();
    </script>
    @endpush
</x-app-layout>
