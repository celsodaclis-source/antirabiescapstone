<x-app-layout>
    <x-slot name="header">Review Nurse Assessment</x-slot>

    <main class="mx-auto max-w-4xl space-y-5 px-4 py-7 sm:px-6 lg:px-8">
        <header class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h1 class="text-xl font-bold text-slate-900">Review Workflow Rules</h1>
            <p class="mt-1 text-sm text-slate-600">Ticket {{ $ticket->ticket_number }} · {{ $patient->fullName() }} · Patient #{{ $patient->id }} · {{ $station->name }}</p>
            <p class="mt-1 text-xs text-slate-500">Assessment status: <span class="font-semibold text-slate-700">{{ $assessment->statusLabel() }}</span> · Nurse: {{ $assessment->nurse?->name ?? Auth::user()->name }} · Visit type: {{ $ticket->visit_type === 'returning' ? 'Returning Patient' : 'New Patient' }}</p>
        </header>

        @if ($missingErrors)
            <div role="alert" class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                <p class="font-semibold">This routing action cannot be confirmed yet.</p>
                <ul class="mt-1 list-inside list-disc">@foreach ($missingErrors as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <section class="space-y-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="font-semibold text-slate-900">Saved assessment</h2>
            <dl class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                <div><dt class="text-xs text-slate-500">Blood pressure</dt><dd>{{ $assessment->blood_pressure ?: 'Not recorded' }}</dd></div>
                <div><dt class="text-xs text-slate-500">Temperature</dt><dd>{{ $assessment->temperature !== null ? $assessment->temperature.' °C' : 'Not recorded' }}</dd></div>
                <div><dt class="text-xs text-slate-500">Pulse rate</dt><dd>{{ $assessment->pulse_rate !== null ? $assessment->pulse_rate.' beats/min' : 'Not recorded' }}</dd></div>
                <div><dt class="text-xs text-slate-500">Respiratory rate</dt><dd>{{ $assessment->respiratory_rate !== null ? $assessment->respiratory_rate.' breaths/min' : 'Not recorded' }}</dd></div>
                <div><dt class="text-xs text-slate-500">Weight</dt><dd>{{ $assessment->weight !== null ? $assessment->weight.' kg' : 'Not recorded' }}</dd></div>
                <div><dt class="text-xs text-slate-500">Bite site</dt><dd>{{ $assessment->bite_site ?: 'Not recorded' }}</dd></div>
                <div><dt class="text-xs text-slate-500">Animal status</dt><dd>{{ $assessment->animal_status ?: 'Not recorded' }}</dd></div>
                <div><dt class="text-xs text-slate-500">Animal vaccinated</dt><dd>{{ is_null($assessment->animal_vaccinated) ? 'Not recorded' : ($assessment->animal_vaccinated ? 'Yes' : 'No') }}</dd></div>
                <div class="sm:col-span-2"><dt class="text-xs text-slate-500">Assessment and observations</dt><dd class="mt-1 whitespace-pre-wrap text-slate-800">{{ $assessment->assessment_observations ?: 'Not entered' }}</dd></div>
                <div class="sm:col-span-2"><dt class="text-xs text-slate-500">Additional nursing remarks</dt><dd class="mt-1 whitespace-pre-wrap text-slate-800">{{ $assessment->nursing_remarks ?: 'Not entered' }}</dd></div>
            </dl>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="font-semibold text-slate-900">Selected routing option</h2>
            <p class="mt-2 text-sm font-medium text-slate-800">{{ $assessment->routingOptionLabel() }}</p>
            <h3 class="mt-4 text-sm font-semibold text-slate-900">Workflow effect</h3>
            <p class="mt-1 text-sm text-slate-700">@switch($assessment->routing_option)
                @case('send_to_doctor') The nursing assessment is marked completed, the original ticket returns to the shared Doctor Waiting List, and the patient leaves Nurse Now Serving. No duplicate ticket or patient record is created. @break
                @case('vaccine_administration') The nursing assessment is completed and the original ticket opens the existing Vaccine Administration form for the authorized due dose. No vaccine stock is deducted until an administration is saved. @break
                @case('on_hold') The assessment entered so far is saved as On Hold, the original ticket is preserved with a held status, and the patient is not marked as assessed or completed. @break
                @default No supported routing option has been selected.
            @endswitch</p>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="font-semibold text-slate-900">Prerequisites and checks</h2>
            <ul class="mt-3 divide-y divide-slate-100 text-sm">
                @foreach ($prerequisites as $check)
                    <li class="flex items-start justify-between gap-4 py-2">
                        <div>
                            <p class="font-medium text-slate-800">{{ $check['label'] }}</p>
                            <p class="mt-0.5 text-xs text-slate-600">{{ $check['detail'] }}</p>
                        </div>
                        <span class="w-fit shrink-0 rounded-full {{ $check['ok'] ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-900' }} px-2.5 py-1 text-xs font-semibold">{{ $check['ok'] ? 'Ready' : 'Verify' }}</span>
                    </li>
                @endforeach
            </ul>
        </section>

        <footer class="flex flex-col-reverse justify-between gap-3 sm:items-center sm:flex-row">
            <a href="{{ route('nurse.station.assessment.form', $ticket) }}" class="rounded-lg border border-slate-300 bg-white px-5 py-2.5 text-center text-sm font-semibold text-slate-800 hover:bg-slate-50">Return to Assessment</a>
            <form method="POST" action="{{ route('nurse.station.assessment.finalize', $ticket) }}" class="flex flex-col items-stretch gap-3 sm:items-end">
                @csrf
                <input type="hidden" name="routing_option" value="{{ $assessment->routing_option }}">
                <label class="flex items-start gap-2 text-sm text-slate-700"><input type="checkbox" name="confirmed" value="1" required class="mt-0.5 rounded border-slate-300"><span>I reviewed the saved assessment and confirm this routing action.</span></label>
                <button @disabled($missingErrors || ! $assessment->routing_option) class="rounded-lg bg-slate-950 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:bg-slate-400">Confirm &amp; Route Patient</button>
            </form>
        </footer>
    </main>
</x-app-layout>
