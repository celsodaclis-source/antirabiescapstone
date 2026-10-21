<x-app-layout>
    <x-slot name="header">Doctor Consultation</x-slot>

    @php
        $saved = $consultation;
        $value = fn ($key, $fallback = null) => old($key, $saved?->{$key} ?? $fallback);
        $selectedVaccine = $value('vaccine_type', $existingOrder['vaccine_type']);
        $patientType = ucfirst($ticket->visit_type ?? $patient->patient_type ?? 'Patient');
        $dispositions = [
            'route_to_nurse' => 'Route to Nurse — For Vaccination / Treatment',
            'complete_visit' => 'Complete Visit — No further on-site service',
            'on_hold' => 'On Hold — For further assessment',
            'refer' => 'Refer to another facility / service',
        ];
    @endphp

    <main class="mx-auto max-w-5xl space-y-5 px-4 py-7 sm:px-6 lg:px-8">
        <header class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-xl font-bold text-slate-900">Doctor Consultation</h1>
                    <p class="mt-1 text-sm text-slate-600">Ticket {{ $ticket->ticket_number }} · {{ $patient->fullName() }} · Patient ID {{ $patient->id }}</p>
                </div>
                <span class="rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-800">{{ $ticket->status === 'in_progress' ? 'In Consultation' : ucfirst(str_replace('_', ' ', $ticket->status)) }}</span>
            </div>
        </header>

        @if (session('status'))<div role="status" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>@endif
        @if ($errors->any())<div role="alert" class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800"><p class="font-semibold">Please review the following:</p><ul class="mt-1 list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

        <form id="consultation-form" method="POST" class="space-y-5">
            @csrf
            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <h2 class="text-base font-bold text-slate-900">Nursing Assessment Summary</h2>
                <p class="mt-1 text-xs text-slate-500">Read-only information from the saved nurse assessment and bite incident record.</p>
                @php($nurseAssessment = $ticket->nurseAssessment)
                <dl class="mt-4 grid grid-cols-1 gap-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
                    <div><dt class="text-xs font-medium text-slate-500">Date of bite / exposure</dt><dd class="mt-1 text-slate-900">{{ $incident->date_of_bite?->format('M j, Y') ?? 'Not recorded' }}</dd></div>
                    <div><dt class="text-xs font-medium text-slate-500">Animal type</dt><dd class="mt-1 text-slate-900">{{ $incident->animal_type ?: 'Not recorded' }}</dd></div>
                    <div><dt class="text-xs font-medium text-slate-500">Bite site</dt><dd class="mt-1 text-slate-900">{{ $incident->bite_site ?: 'Not recorded' }}</dd></div>
                    <div><dt class="text-xs font-medium text-slate-500">Animal status</dt><dd class="mt-1 text-slate-900">{{ $incident->animal_status ?: 'Not recorded' }}</dd></div>
                    <div><dt class="text-xs font-medium text-slate-500">Animal vaccinated</dt><dd class="mt-1 text-slate-900">{{ is_null($incident->animal_vaccinated) ? 'Not recorded' : ($incident->animal_vaccinated ? 'Yes' : 'No') }}</dd></div>
                    <div><dt class="text-xs font-medium text-slate-500">Vital signs</dt><dd class="mt-1 text-slate-900">{{ $nurseAssessment?->vitalSignsSummary() ?: 'Not recorded' }}</dd></div>
                    <div class="sm:col-span-2 lg:col-span-3"><dt class="text-xs font-medium text-slate-500">Assessment and observations</dt><dd class="mt-1 whitespace-pre-wrap text-slate-900">{{ $nurseAssessment?->assessment_observations ?: 'Not recorded' }}</dd></div>
                    <div class="sm:col-span-2 lg:col-span-3"><dt class="text-xs font-medium text-slate-500">Additional nursing remarks</dt><dd class="mt-1 whitespace-pre-wrap text-slate-900">{{ $nurseAssessment?->nursing_remarks ?: 'None recorded' }}</dd></div>
                    <div><dt class="text-xs font-medium text-slate-500">Assessment status</dt><dd class="mt-1 text-slate-900">{{ $ticket->assessment_completed_at ? 'Completed'.($nurseAssessment?->completed_at ? ' · '.$nurseAssessment->completed_at->format('M j, Y g:i A') : '') : 'Not recorded' }}</dd></div>
                </dl>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <h2 class="text-base font-bold text-slate-900">1. Medical Narrative</h2>
                <label for="clinical_narrative" class="mt-4 block text-sm font-medium text-slate-700">Clinical assessment and findings <span class="text-rose-600">*</span></label>
                <textarea id="clinical_narrative" name="clinical_narrative" rows="5" maxlength="10000" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Document the bite or exposure history, relevant findings, assessment, and clinical reasoning...">{{ $value('clinical_narrative') }}</textarea>
                @error('clinical_narrative')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
                <p class="mt-2 text-xs text-slate-500">The narrative is saved to this ticket's consultation and remains available when reopened.</p>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <h2 class="text-base font-bold text-slate-900">2. Vaccination / Treatment Plan</h2>
                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="exposure_category" class="block text-sm font-medium text-slate-700">Exposure Category</label>
                        <select id="exposure_category" name="exposure_category" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm"><option value="">Select category</option>@foreach (['I', 'II', 'III'] as $category)<option value="{{ $category }}" @selected($value('exposure_category', $incident->exposure_category) === $category)>{{ $category }}</option>@endforeach</select>
                        @error('exposure_category')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="vaccine_type" class="block text-sm font-medium text-slate-700">Vaccine / Treatment</label>
                        <select id="vaccine_type" name="vaccine_type" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm"><option value="">Select available inventory item</option>@foreach ($vaccineTypes as $type)<option value="{{ $type }}" @selected($selectedVaccine === $type)>{{ $type }}</option>@endforeach</select>
                        @if (!$vaccineTypes)<p class="mt-1 text-xs text-amber-800">No unexpired vaccine with stock is available. The treatment decision must be reviewed before finalization.</p>@endif
                        @error('vaccine_type')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label for="schedule_code" class="block text-sm font-medium text-slate-700">Vaccination Schedule</label>
                        <select id="schedule_code" name="schedule_code" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm"><option value="">Select provider-ordered regimen</option>@foreach ($scheduleOptions as $option)<option value="{{ $option['code'] }}" data-dose-count="{{ $option['dose_count'] }}" data-label="{{ $option['label'] }}" @selected($value('schedule_code', $existingOrder['schedule_code']) === $option['code'])>{{ $option['label'] }} ({{ $option['dose_count'] }} doses)</option>@endforeach</select>
                        <p class="mt-1 text-xs text-slate-500">Select only the schedule authorized by the clinical order.</p>
                        @error('schedule_code')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="planned_dose_count" class="block text-sm font-medium text-slate-700">Number of Doses in Provider-Ordered Schedule</label>
                        <input id="planned_dose_count" name="planned_dose_count" type="number" min="1" max="255" value="{{ $value('planned_dose_count', $existingOrder['planned_dose_count']) }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm" readonly>
                        @error('planned_dose_count')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="vaccination_schedule" class="block text-sm font-medium text-slate-700">Authorized Schedule Reference</label>
                        <input id="vaccination_schedule" name="vaccination_schedule" value="{{ $value('vaccination_schedule', $existingOrder['schedule_code'] ? collect($scheduleOptions)->firstWhere('code', $existingOrder['schedule_code'])['label'] : null) }}" class="mt-1 w-full rounded-lg border-slate-300 bg-slate-50 text-sm shadow-sm" readonly>
                        @error('vaccination_schedule')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label for="additional_instructions" class="block text-sm font-medium text-slate-700">Additional Treatment / Instructions</label>
                        <textarea id="additional_instructions" name="additional_instructions" rows="3" maxlength="4000" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm" placeholder="Record other indicated treatments, observations, or follow-up instructions...">{{ $value('additional_instructions') }}</textarea>
                        @error('additional_instructions')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
                    </div>
                </div>
                <p class="mt-4 rounded-lg bg-slate-50 p-3 text-xs text-slate-600">Exposure category alone does not authorize a vaccine, dose, regimen, or route. Planned doses remain separate from administered doses.</p>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <h2 class="text-base font-bold text-slate-900">3. Patient Disposition</h2>
                <fieldset class="mt-4 space-y-3">
                    <legend class="mb-2 text-sm font-medium text-slate-700">Next destination <span class="text-rose-600">*</span></legend>
                    @foreach ($dispositions as $key => $label)
                        <label class="flex items-start gap-3 text-sm text-slate-800"><input type="radio" name="disposition" value="{{ $key }}" @checked($value('disposition', 'route_to_nurse') === $key) class="mt-0.5 border-slate-300 text-slate-900 focus:ring-slate-500"><span>{{ $label }}</span></label>
                    @endforeach
                </fieldset>
                @error('disposition')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
                <div id="referral-field" class="mt-4 hidden">
                    <label for="referral_destination" class="block text-sm font-medium text-slate-700">Referral destination</label>
                    <input id="referral_destination" name="referral_destination" maxlength="255" value="{{ $value('referral_destination') }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm" placeholder="Facility or service name">
                    @error('referral_destination')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
                    <p class="mt-1 text-xs text-amber-800">This application records referrals as held tickets with a saved referral disposition; it has no separate referral workflow.</p>
                </div>
                <label for="disposition_remarks" class="mt-4 block text-sm font-medium text-slate-700">Disposition Remarks</label>
                <textarea id="disposition_remarks" name="disposition_remarks" rows="3" maxlength="4000" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm" placeholder="Additional instructions for the next service or reason for disposition...">{{ $value('disposition_remarks') }}</textarea>
                @error('disposition_remarks')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
            </section>

            <footer class="flex flex-col-reverse justify-end gap-3 sm:flex-row">
                <button type="submit" formaction="{{ route('doctor.station.consultation.draft', $ticket) }}" class="rounded-lg border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-800 hover:bg-slate-50">Review Saved Draft</button>
                <button type="submit" formaction="{{ route('doctor.station.consultation.review', $ticket) }}" class="rounded-lg bg-slate-950 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">Review Finalize &amp; Route Rules</button>
            </footer>
        </form>
        <p class="text-xs text-slate-500">Doctor: {{ Auth::user()->name }} · Consultation data and timestamps are saved using the application timezone.</p>
    </main>

    @push('scripts')
    <script>
        (() => {
            const schedules = document.getElementById('schedule_code');
            const count = document.getElementById('planned_dose_count');
            const text = document.getElementById('vaccination_schedule');
            const syncSchedule = () => {
                const option = schedules.options[schedules.selectedIndex];
                count.value = option?.dataset.doseCount || '';
                text.value = option?.dataset.label || '';
            };
            const syncDisposition = () => {
                const refer = document.querySelector('input[name="disposition"]:checked')?.value === 'refer';
                document.getElementById('referral-field').classList.toggle('hidden', !refer);
            };
            schedules.addEventListener('change', syncSchedule);
            document.querySelectorAll('input[name="disposition"]').forEach(input => input.addEventListener('change', syncDisposition));
            syncDisposition();
        })();
    </script>
    @endpush
</x-app-layout>
