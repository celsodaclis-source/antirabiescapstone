<x-app-layout>
    <x-slot name="header">{{ $station->name }} — My Queue</x-slot>

    <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ $station->name }} — My Queue</h1>
                <p class="mt-1 text-sm text-slate-600">Patient registration review and assessment queue</p>
            </div>
            <span class="inline-flex w-fit items-center gap-2 rounded-full bg-emerald-100 px-3 py-1 text-sm font-semibold text-emerald-800"><span class="h-2 w-2 rounded-full bg-emerald-500"></span>Station Active</span>
        </div>

        @if ($errors->has('assessment') || $errors->has('station'))
            <div role="alert" class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first('assessment') ?: $errors->first('station') }}</div>
        @endif

        <section class="mb-5 rounded-xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="nurse-now-serving">
            <div id="nurse-current-state" class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 id="nurse-now-serving" class="font-semibold text-slate-900">Now Serving</h2>
                    @if ($currentTicket)
                        <p class="mt-1 text-sm text-slate-600"><span class="font-bold text-blue-900">{{ $currentTicket->ticket_number }}</span> · {{ $currentTicket->patient->fullName() }}</p>
                        @php($incident = $currentTicket->patient->biteIncidents->sortBy('date_of_bite')->first())
                        <p class="text-xs text-slate-500">Registration and bite incident review in progress · <a class="text-blue-800 underline" href="{{ route('patients.show', $currentTicket->patient) }}">Open patient record</a></p>
                        @if ($incident)
                            <dl class="mt-3 grid grid-cols-1 gap-2 text-xs text-slate-600 sm:grid-cols-3">
                                <div><dt class="font-semibold text-slate-700">Date of bite</dt><dd>{{ $incident->date_of_bite?->format('M j, Y') }}</dd></div>
                                <div><dt class="font-semibold text-slate-700">Animal</dt><dd>{{ $incident->animal_type }}</dd></div>
                                <div><dt class="font-semibold text-slate-700">Exposure Category</dt><dd>{{ $incident->exposure_category ?? 'Pending doctor review' }}</dd></div>
                            </dl>
                        @endif
                    @else
                        <p class="mt-1 text-sm text-slate-600">No patient is currently being assessed.</p>
                    @endif
                </div>
                @if ($currentTicket)
                    <form method="POST" action="{{ route('nurse.station.assessment.complete', $currentTicket) }}">@csrf
                        <div class="mb-4 grid grid-cols-1 gap-3 border-t border-slate-200 pt-4 text-left sm:grid-cols-3">
                            <div>
                                <label for="bite_site" class="block text-xs font-semibold text-slate-700">Bite site <span class="text-rose-600">*</span></label>
                                <input id="bite_site" name="bite_site" required maxlength="150" value="{{ old('bite_site', $incident?->bite_site) }}" placeholder="For example, right forearm" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                                @error('bite_site')<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="animal_status" class="block text-xs font-semibold text-slate-700">Animal status <span class="text-rose-600">*</span></label>
                                <select id="animal_status" name="animal_status" required class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                                    <option value="">Select status</option>
                                    @foreach (['Healthy', 'Sick', 'Dead', 'Unknown'] as $animalStatus)
                                        <option value="{{ $animalStatus }}" @selected(old('animal_status', $incident?->animal_status) === $animalStatus)>{{ $animalStatus }}</option>
                                    @endforeach
                                </select>
                                @error('animal_status')<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="animal_vaccinated" class="block text-xs font-semibold text-slate-700">Animal vaccinated? <span class="text-rose-600">*</span></label>
                                <select id="animal_vaccinated" name="animal_vaccinated" required class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                                    <option value="">Select answer</option>
                                    <option value="1" @selected((string) old('animal_vaccinated', '') === '1')>Yes</option>
                                    <option value="0" @selected((string) old('animal_vaccinated', '') === '0')>No</option>
                                </select>
                                @error('animal_vaccinated')<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
                            </div>
                        </div>
                        <button class="rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">Save Assessment &amp; Transfer</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('nurse.station.call-next') }}">@csrf
                        <button @disabled($waiting->isEmpty()) class="rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:bg-slate-300">Call Next Patient</button>
                    </form>
                @endif
            </div>
        </section>

        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <header class="flex flex-col gap-1 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div><h2 class="font-semibold text-slate-900">Waiting for Nurse Assessment</h2><p class="mt-1 text-sm text-slate-600">Call patients in priority order, then review their registration and bite incident.</p></div>
                <span id="nurse-waiting-count" class="w-fit rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800">{{ $waiting->total() }} Waiting</span>
            </header>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[700px] text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-600">
                        <tr><th class="px-5 py-3">Queue #</th><th class="px-5 py-3">Patient</th><th class="px-5 py-3">Visit</th><th class="px-5 py-3">Status</th></tr>
                    </thead>
                    <tbody id="nurse-waiting-rows" class="divide-y divide-slate-100">
                        @forelse ($waiting as $ticket)
                            <tr>
                                <td class="whitespace-nowrap px-5 py-4 font-semibold text-slate-900">{{ $ticket->ticket_number }}</td>
                                <td class="px-5 py-4"><a class="font-medium text-blue-800 underline decoration-blue-200 underline-offset-2" href="{{ route('patients.show', $ticket->patient) }}">{{ $ticket->patient->fullName() }}</a><span class="mt-1 block text-xs text-slate-500">{{ ucfirst($ticket->service_stage) }}</span></td>
                                <td class="px-5 py-4 text-slate-700">{{ ucfirst($ticket->visit_type) }}</td>
                                <td class="px-5 py-4"><span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800">Waiting</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-5 py-10 text-center text-slate-600">No patients are waiting for a nurse assessment.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($waiting->hasPages())
                <div class="border-t border-slate-200 px-5 py-4">{{ $waiting->links() }}</div>
            @endif
        </section>
        <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
            <p class="text-xs text-slate-500">Assessment saves the bite site and animal status details to the bite incident. It does not record vitals, clinical findings, or a diagnosis.</p>
            <form method="POST" action="{{ route('nurse.stations.release') }}">@csrf @method('DELETE')<button class="text-sm font-medium text-slate-600 underline decoration-slate-300 underline-offset-4">Leave Station</button></form>
        </div>
        <p class="text-right text-xs text-slate-500"><span id="nurse-queue-refresh-error" class="hidden text-rose-700" role="status">Unable to refresh the Nurse queue.</span><span id="nurse-queue-updated">Queue refreshes every 8 seconds.</span></p>
    </div>
    @if (! $errors->any())
        @push('scripts')
        <script>
            (() => {
                let refreshing = false;
                let assessmentDirty = false;
                const currentState = document.getElementById('nurse-current-state');
                currentState.addEventListener('input', () => assessmentDirty = true);
                currentState.addEventListener('change', () => assessmentDirty = true);
                const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
                const renderCurrent = (current, canCall) => {
                    if (!current) return `<div><h2 id="nurse-now-serving" class="font-semibold text-slate-900">Now Serving</h2><p class="mt-1 text-sm text-slate-600">No patient is currently being assessed.</p></div>
                        <form method="POST" action="{{ route('nurse.station.call-next') }}"><input type="hidden" name="_token" value="${esc(document.querySelector('meta[name=csrf-token]').content)}"><button ${canCall ? '' : 'disabled'} class="rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:bg-slate-300">Call Next Patient</button></form>`;
                    const incident = current.incident || {};
                    const selected = (key, value) => String(incident[key] ?? '') === String(value) ? 'selected' : '';
                    return `<div><h2 id="nurse-now-serving" class="font-semibold text-slate-900">Now Serving</h2>
                        <p class="mt-1 text-sm text-slate-600"><span class="font-bold text-blue-900">${esc(current.number)}</span> · ${esc(current.patient)}</p>
                        <p class="text-xs text-slate-500">Registration and bite incident review in progress${current.patient_url ? ` · <a class="text-blue-800 underline" href="${esc(current.patient_url)}">Open patient record</a>` : ''}</p>
                        <dl class="mt-3 grid grid-cols-1 gap-2 text-xs text-slate-600 sm:grid-cols-3"><div><dt class="font-semibold text-slate-700">Date of bite</dt><dd>${esc(incident.date_of_bite || '—')}</dd></div><div><dt class="font-semibold text-slate-700">Animal</dt><dd>${esc(incident.animal_type || '—')}</dd></div><div><dt class="font-semibold text-slate-700">Exposure Category</dt><dd>${esc(incident.exposure_category || 'Pending doctor review')}</dd></div></dl></div>
                        <form method="POST" action="${esc(current.assessment_url)}"><input type="hidden" name="_token" value="${esc(document.querySelector('meta[name=csrf-token]').content)}">
                            <div class="mb-4 grid grid-cols-1 gap-3 border-t border-slate-200 pt-4 text-left sm:grid-cols-3">
                                <div><label for="bite_site" class="block text-xs font-semibold text-slate-700">Bite site *</label><input id="bite_site" name="bite_site" required maxlength="150" value="${esc(incident.bite_site || '')}" placeholder="For example, right forearm" class="mt-1 w-full rounded-lg border-slate-300 text-sm"></div>
                                <div><label for="animal_status" class="block text-xs font-semibold text-slate-700">Animal status *</label><select id="animal_status" name="animal_status" required class="mt-1 w-full rounded-lg border-slate-300 text-sm"><option value="">Select status</option>${['Healthy','Sick','Dead','Unknown'].map((value) => `<option value="${value}" ${selected('animal_status', value)}>${value}</option>`).join('')}</select></div>
                                <div><label for="animal_vaccinated" class="block text-xs font-semibold text-slate-700">Animal vaccinated? *</label><select id="animal_vaccinated" name="animal_vaccinated" required class="mt-1 w-full rounded-lg border-slate-300 text-sm"><option value="">Select answer</option><option value="1">Yes</option><option value="0">No</option></select></div>
                            </div><button class="rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">Save Assessment &amp; Transfer</button>
                        </form>`;
                };
                const refresh = async () => {
                    if (refreshing || document.hidden) return;
                    refreshing = true;
                    try {
                        const response = await fetch(@json(route('nurse.station.data')), {headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'},cache:'no-store'});
                        if (!response.ok) throw new Error('Queue refresh failed');
                        const data = await response.json();
                        document.getElementById('nurse-waiting-count').textContent = `${data.waiting_count} Waiting`;
                        document.getElementById('nurse-waiting-rows').innerHTML = data.waiting.length ? data.waiting.map((item) => `<tr>
                            <td class="whitespace-nowrap px-5 py-4 font-semibold text-slate-900">${esc(item.number)}</td>
                            <td class="px-5 py-4">${item.patient_url ? `<a class="font-medium text-blue-800 underline decoration-blue-200 underline-offset-2" href="${esc(item.patient_url)}">${esc(item.patient)}</a>` : esc(item.patient)}<span class="mt-1 block text-xs text-slate-500">${esc(item.stage)}</span></td>
                            <td class="px-5 py-4 text-slate-700">${esc(item.visit)}</td><td class="px-5 py-4"><span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800">Waiting</span></td></tr>`).join('') : '<tr><td colspan="4" class="px-5 py-10 text-center text-slate-600">No patients are waiting for a nurse assessment.</td></tr>';
                        if (!assessmentDirty && !currentState.contains(document.activeElement)) currentState.innerHTML = renderCurrent(data.current, data.waiting_count > 0);
                        document.getElementById('nurse-queue-updated').textContent = `Updated ${new Date(data.updated_at).toLocaleTimeString()} · Refreshes every 8 seconds`;
                        document.getElementById('nurse-queue-refresh-error').classList.add('hidden');
                    } catch (error) { document.getElementById('nurse-queue-refresh-error').classList.remove('hidden'); }
                    finally { refreshing = false; }
                };
                window.setInterval(refresh, 8000);
            })();
        </script>
        @endpush
    @endif
</x-app-layout>
