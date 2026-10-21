<x-app-layout>
    <x-slot name="header">Nurse Assessment</x-slot>

    @php
        $saved = $assessment;
        $value = fn ($key, $fallback = null) => old($key, $saved?->{$key} ?? $fallback);
        $animalVaccinated = old('animal_vaccinated', $saved?->animal_vaccinated === null ? null : ($saved->animal_vaccinated ? '1' : '0'));
        $statusLabel = match ($saved?->status) {
            'completed' => 'Completed',
            'on_hold' => 'On Hold',
            'draft' => 'In Assessment',
            default => 'Awaiting Assessment',
        };
        $statusClass = match ($saved?->status) {
            'completed' => 'bg-emerald-100 text-emerald-800',
            'on_hold' => 'bg-amber-100 text-amber-800',
            'draft' => 'bg-blue-100 text-blue-800',
            default => 'bg-slate-100 text-slate-700',
        };
    @endphp

    <main class="mx-auto max-w-5xl space-y-5 px-4 py-7 sm:px-6 lg:px-8">
        <header class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-xl font-bold text-slate-900">Nurse Assessment</h1>
                    <p class="mt-1 text-sm text-slate-600">{{ $station->name }} · Ticket {{ $ticket->ticket_number }} · Patient #{{ $patient->id }}</p>
                </div>
                <span class="rounded-full {{ $statusClass }} px-3 py-1 text-xs font-semibold">{{ $statusLabel }}</span>
            </div>

            <div class="mt-4 border-t border-slate-100 pt-4">
                <h2 class="text-sm font-bold text-slate-900">Patient Information</h2>
                <dl class="mt-2 grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-medium text-slate-500">Patient Name</dt>
                        <dd class="mt-1 text-slate-900"><a class="font-medium text-blue-800 underline decoration-blue-200 underline-offset-2" href="{{ route('patients.show', $patient) }}">{{ $patient->fullName() }}</a></dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-slate-500">Visit Type</dt>
                        <dd class="mt-1 flex flex-wrap gap-2">
                            <span class="rounded-full {{ $ticket->visit_type === 'new' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600' }} px-3 py-1 text-xs font-semibold">New Patient</span>
                            <span class="rounded-full {{ $ticket->visit_type === 'returning' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600' }} px-3 py-1 text-xs font-semibold">Returning Patient</span>
                        </dd>
                        <p class="mt-1 text-xs text-slate-500">Visit type comes from the registered encounter and cannot be changed on this form.</p>
                    </div>
                </dl>
            </div>
        </header>

        @if (session('status'))<div role="status" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>@endif
        @if ($errors->any())<div role="alert" class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800"><p class="font-semibold">Please review the following:</p><ul class="mt-1 list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

        <form id="nurse-assessment-form" method="POST" class="space-y-5">
            @csrf

            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <h2 class="text-base font-bold text-slate-900">1. Vital Signs</h2>
                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="blood_pressure" class="block text-sm font-medium text-slate-700">Blood Pressure</label>
                        <input id="blood_pressure" name="blood_pressure" maxlength="20" value="{{ $value('blood_pressure') }}" placeholder="e.g. 120/80 mmHg" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        @error('blood_pressure')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="temperature" class="block text-sm font-medium text-slate-700">Temperature (°C)</label>
                        <input id="temperature" name="temperature" type="number" step="0.1" min="30" max="45" value="{{ $value('temperature') }}" placeholder="°C" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        @error('temperature')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="pulse_rate" class="block text-sm font-medium text-slate-700">Pulse Rate (beats/min)</label>
                        <input id="pulse_rate" name="pulse_rate" type="number" min="20" max="250" value="{{ $value('pulse_rate') }}" placeholder="beats/min" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        @error('pulse_rate')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="respiratory_rate" class="block text-sm font-medium text-slate-700">Respiratory Rate (breaths/min)</label>
                        <input id="respiratory_rate" name="respiratory_rate" type="number" min="5" max="80" value="{{ $value('respiratory_rate') }}" placeholder="breaths/min" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        @error('respiratory_rate')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="weight" class="block text-sm font-medium text-slate-700">Weight (kg)</label>
                        <input id="weight" name="weight" type="number" step="0.01" min="0.5" max="300" value="{{ $value('weight') }}" placeholder="kg" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        @error('weight')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
                    </div>
                </div>
                <p class="mt-3 text-xs text-slate-500">Record only measurements actually taken. Values outside safe clinical ranges are rejected, and saved measurements are restored when the form is reopened.</p>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <h2 class="text-base font-bold text-slate-900">2. Nursing Assessment</h2>

                <h3 class="mt-4 text-sm font-semibold text-slate-800">Bite incident details</h3>
                <div class="mt-2 grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <label for="bite_site" class="block text-sm font-medium text-slate-700">Bite site <span class="text-rose-600">*</span></label>
                        <input id="bite_site" name="bite_site" required maxlength="150" value="{{ $value('bite_site', $incident->bite_site) }}" placeholder="For example, right forearm" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        @error('bite_site')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="animal_status" class="block text-sm font-medium text-slate-700">Animal status <span class="text-rose-600">*</span></label>
                        <select id="animal_status" name="animal_status" required class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Select status</option>
                            @foreach (['Healthy', 'Sick', 'Dead', 'Unknown'] as $animalStatus)
                                <option value="{{ $animalStatus }}" @selected($value('animal_status', $incident->animal_status) === $animalStatus)>{{ $animalStatus }}</option>
                            @endforeach
                        </select>
                        @error('animal_status')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="animal_vaccinated" class="block text-sm font-medium text-slate-700">Animal vaccinated? <span class="text-rose-600">*</span></label>
                        <select id="animal_vaccinated" name="animal_vaccinated" required class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Select answer</option>
                            <option value="1" @selected((string) $animalVaccinated === '1')>Yes</option>
                            <option value="0" @selected((string) $animalVaccinated === '0')>No</option>
                        </select>
                        @error('animal_vaccinated')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
                    </div>
                </div>

                <label for="assessment_observations" class="mt-4 block text-sm font-medium text-slate-700">Assessment and Observations <span class="text-rose-600">*</span></label>
                <textarea id="assessment_observations" name="assessment_observations" rows="5" maxlength="10000" placeholder="Document the patient's condition, wound observations, reported symptoms, and relevant assessment findings..." class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ $value('assessment_observations') }}</textarea>
                @error('assessment_observations')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror

                <label for="nursing_remarks" class="mt-4 block text-sm font-medium text-slate-700">Additional Nursing Remarks</label>
                <textarea id="nursing_remarks" name="nursing_remarks" rows="3" maxlength="4000" placeholder="Record relevant notes, concerns, or instructions..." class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ $value('nursing_remarks') }}</textarea>
                @error('nursing_remarks')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
                <p class="mt-2 text-xs text-slate-500">Drafts stay attached to this ticket, patient, and nurse, and are restored when the form is reopened. Only a validated assessment is marked completed.</p>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <h2 class="text-base font-bold text-slate-900">3. Patient Routing</h2>
                <fieldset class="mt-4 space-y-3">
                    <legend class="mb-2 text-sm font-medium text-slate-700">Next destination <span class="text-rose-600">*</span></legend>
                    <label class="flex items-start gap-3 text-sm text-slate-800"><input type="radio" name="routing_option" value="send_to_doctor" @checked($defaultRoutingOption === 'send_to_doctor') class="mt-0.5 border-slate-300 text-slate-900 focus:ring-slate-500"><span>Send to Doctor — For consultation or reassessment</span></label>
                    <label class="flex items-start gap-3 text-sm text-slate-800"><input type="radio" name="routing_option" value="vaccine_administration" @checked($defaultRoutingOption === 'vaccine_administration') class="mt-0.5 border-slate-300 text-slate-900 focus:ring-slate-500"><span>Proceed to Vaccine Administration — Under an authorized plan</span></label>
                    <label class="flex items-start gap-3 text-sm text-slate-800"><input type="radio" name="routing_option" value="on_hold" @checked($defaultRoutingOption === 'on_hold') class="mt-0.5 border-slate-300 text-slate-900 focus:ring-slate-500"><span>On Hold — Awaiting further assessment</span></label>
                </fieldset>
                @error('routing_option')<p class="mt-2 text-sm text-rose-700">{{ $message }}</p>@enderror

                <div id="routing-vaccine-panel" class="mt-4 hidden rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm">
                    <p class="font-semibold text-slate-900">Vaccine Administration</p>
                    <p class="mt-1 text-xs text-slate-600">Verify the existing clinical order, scheduled dose, patient eligibility, vaccine stock, and applicable ABTC protocol before proceeding.</p>
                    @if ($vaccinePlan[0])
                        <p class="mt-2 rounded-md bg-emerald-100 px-3 py-2 text-xs font-semibold text-emerald-800">Authorized plan found: dose {{ $vaccinePlan[0]->dose_number }} due {{ $vaccinePlan[0]->scheduled_date?->format('M j, Y') }}. Opening the vaccine form does not deduct stock.</p>
                    @endif
                    @foreach ($vaccinePlan[1] as $message)
                        <p class="mt-2 rounded-md bg-amber-100 px-3 py-2 text-xs font-semibold text-amber-900">{{ $message }}</p>
                    @endforeach
                </div>

                <div id="routing-hold-panel" class="mt-3 hidden rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm">
                    <p class="font-semibold text-slate-900">On Hold — Awaiting further assessment</p>
                    <p class="mt-1 text-xs text-slate-600">The reason and required next step must be recorded in Additional Nursing Remarks. The original ticket and patient record are preserved, and the patient is not marked as assessed or completed. Staff can resume the assessment from My Queue.</p>
                </div>

                <p class="mt-4 rounded-lg bg-slate-50 p-3 text-xs text-slate-600">Routing options are mutually exclusive. Sending to the doctor or vaccinating completes the nursing assessment; On Hold keeps it open. No vaccine stock is deducted by this form.</p>
            </section>

            <footer class="flex flex-col-reverse justify-end gap-3 sm:flex-row">
                <button type="button" id="copy-draft-button" class="rounded-lg border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-800 hover:bg-slate-50">Copy Form Draft</button>
                <button type="submit" formaction="{{ route('nurse.station.assessment.draft', $ticket) }}" class="rounded-lg border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-800 hover:bg-slate-50">Save Draft</button>
                <button type="submit" formaction="{{ route('nurse.station.assessment.review', $ticket) }}" class="rounded-lg bg-slate-950 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">Review Workflow Rules</button>
            </footer>
            <p id="copy-draft-result" class="hidden text-right text-xs text-emerald-700" role="status"></p>
        </form>

        <p class="text-xs text-slate-500">Nurse: {{ Auth::user()->name }} · Ticket {{ $ticket->ticket_number }} remains assigned to {{ $station->name }} until a routing option is confirmed. Assessment data and timestamps are saved using the application timezone.</p>
        <p class="text-right text-xs"><a class="font-medium text-slate-600 underline decoration-slate-300 underline-offset-4" href="{{ route('nurse.station.show') }}">Back to My Queue</a></p>
    </main>

    @push('scripts')
    <script>
        (() => {
            const optionInputs = Array.from(document.querySelectorAll('input[name="routing_option"]'));
            const vaccinePanel = document.getElementById('routing-vaccine-panel');
            const holdPanel = document.getElementById('routing-hold-panel');
            const syncPanels = () => {
                const selected = optionInputs.find((input) => input.checked)?.value;
                vaccinePanel.classList.toggle('hidden', selected !== 'vaccine_administration');
                holdPanel.classList.toggle('hidden', selected !== 'on_hold');
            };
            optionInputs.forEach((input) => input.addEventListener('change', syncPanels));
            syncPanels();

            const result = document.getElementById('copy-draft-result');
            const text = () => {
                const form = document.getElementById('nurse-assessment-form');
                const field = (id) => form.querySelector('#' + id)?.value?.trim() || 'Not recorded';
                const selected = optionInputs.find((input) => input.checked);
                const visit = @json($ticket->visit_type) === 'returning' ? 'Returning Patient' : 'New Patient';
                return [
                    'Nurse Assessment Draft — Ticket {{ $ticket->ticket_number }}',
                    'Station: {{ $station->name }} · Nurse: {{ Auth::user()->name }}',
                    'Patient: {{ $patient->fullName() }} (Patient #{{ $patient->id }}) · Visit: ' + visit,
                    'Status: {{ $saved?->statusLabel() ?? 'Not started' }} (draft copy — nothing new is submitted or saved by this action)',
                    '',
                    '1. Vital Signs',
                    'Blood pressure: ' + field('blood_pressure'),
                    'Temperature: ' + field('temperature') + ' °C',
                    'Pulse rate: ' + field('pulse_rate') + ' beats/min',
                    'Respiratory rate: ' + field('respiratory_rate') + ' breaths/min',
                    'Weight: ' + field('weight') + ' kg',
                    '',
                    '2. Nursing Assessment',
                    'Bite site: ' + field('bite_site'),
                    'Animal status: ' + field('animal_status'),
                    'Animal vaccinated: ' + (form.querySelector('#animal_vaccinated')?.value === '1' ? 'Yes' : form.querySelector('#animal_vaccinated')?.value === '0' ? 'No' : 'Not recorded'),
                    'Assessment and observations: ' + field('assessment_observations'),
                    'Additional nursing remarks: ' + field('nursing_remarks'),
                    '',
                    '3. Patient Routing',
                    'Selected option: ' + (selected ? selected.parentElement.textContent.trim() : 'Not selected'),
                ].join('\n');
            };
            document.getElementById('copy-draft-button').addEventListener('click', async () => {
                const content = text();
                try {
                    if (navigator.clipboard?.writeText) {
                        await navigator.clipboard.writeText(content);
                    } else {
                        const helper = document.createElement('textarea');
                        helper.value = content;
                        helper.className = 'fixed -top-[9999px]';
                        document.body.appendChild(helper);
                        helper.select();
                        document.execCommand('copy');
                        helper.remove();
                    }
                    result.textContent = 'Form draft copied to the clipboard. Nothing was submitted or saved.';
                } catch (error) {
                    result.textContent = 'Unable to copy automatically. Select the form entries and copy them manually.';
                }
                result.classList.remove('hidden', 'text-emerald-700');
                result.classList.add(result.textContent.startsWith('Unable') ? 'text-rose-700' : 'text-emerald-700');
            });
        })();
    </script>
    @endpush
</x-app-layout>
