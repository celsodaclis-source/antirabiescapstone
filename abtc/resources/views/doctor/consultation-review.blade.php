<x-app-layout>
    <x-slot name="header">Review Consultation</x-slot>
    <main class="mx-auto max-w-4xl space-y-5 px-4 py-7 sm:px-6 lg:px-8">
        <header class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h1 class="text-xl font-bold text-slate-900">Review Consultation &amp; Route</h1>
            <p class="mt-1 text-sm text-slate-600">{{ $ticket->ticket_number }} · {{ $patient->fullName() }} · Review all saved information before confirming.</p>
        </header>
        @if ($missingErrors)<div role="alert" class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800"><p class="font-semibold">This consultation cannot be finalized yet.</p><ul class="mt-1 list-inside list-disc">@foreach ($missingErrors as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <section class="space-y-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div><h2 class="font-semibold text-slate-900">Clinical assessment</h2><p class="mt-1 whitespace-pre-wrap text-sm text-slate-700">{{ $consultation->clinical_narrative ?: 'Not entered' }}</p></div>
            <dl class="grid grid-cols-1 gap-4 border-t border-slate-100 pt-4 text-sm sm:grid-cols-2">
                <div><dt class="text-xs text-slate-500">Exposure category</dt><dd>{{ $consultation->exposure_category ?: 'Not selected' }}</dd></div>
                <div><dt class="text-xs text-slate-500">Treatment decision</dt><dd>{{ $consultation->treatment_decision ?: 'Not selected' }}</dd></div>
                <div><dt class="text-xs text-slate-500">Vaccine / treatment</dt><dd>{{ $consultation->vaccine_type ?: 'Not selected' }}</dd></div>
                <div><dt class="text-xs text-slate-500">Regimen / route</dt><dd>{{ $consultation->regimen_route ?: 'Not configured' }}</dd></div>
                <div><dt class="text-xs text-slate-500">Provider-ordered schedule</dt><dd>{{ $consultation->vaccination_schedule ?: 'Not selected' }} @if($consultation->planned_dose_count) · {{ $consultation->planned_dose_count }} doses @endif</dd></div>
                <div><dt class="text-xs text-slate-500">Additional instructions</dt><dd class="whitespace-pre-wrap">{{ $consultation->additional_instructions ?: 'None recorded' }}</dd></div>
                <div><dt class="text-xs text-slate-500">Next destination</dt><dd>{{ str($consultation->disposition)->replace('_', ' ')->title() ?: 'Not selected' }}</dd></div>
                <div><dt class="text-xs text-slate-500">Disposition remarks</dt><dd class="whitespace-pre-wrap">{{ $consultation->disposition_remarks ?: 'Not entered' }}</dd></div>
                @if($consultation->disposition === 'refer')<div><dt class="text-xs text-slate-500">Referral destination</dt><dd>{{ $consultation->referral_destination ?: 'Not entered' }}</dd></div>@endif
            </dl>
        </section>
        <section class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            <h2 class="font-semibold">Workflow effect</h2>
            <p class="mt-1">@switch($consultation->disposition)
                @case('route_to_nurse') The original ticket will move to the nurse follow-up queue. This does not record vaccine administration. @break
                @case('complete_visit') The visit ticket will be completed. No vaccine dose will be recorded as administered. @break
                @case('on_hold') The original ticket will be placed on hold for further assessment. @break
                @case('refer') The referral destination will be saved and the original ticket placed on hold; the system has no separate referral status. @break
                @default No supported disposition has been selected.
            @endswitch</p>
        </section>
        <footer class="flex flex-col-reverse justify-between gap-3 sm:flex-row sm:items-center">
            <a href="{{ route('doctor.station.consultation.form', $ticket) }}" class="rounded-lg border border-slate-300 bg-white px-5 py-2.5 text-center text-sm font-semibold text-slate-800 hover:bg-slate-50">Return to Consultation</a>
            <form method="POST" action="{{ route('doctor.station.consultation.finalize', $ticket) }}" class="flex flex-col items-stretch gap-3 sm:items-end">
                @csrf
                <label class="flex items-start gap-2 text-sm text-slate-700"><input type="checkbox" name="confirmed" value="1" required class="mt-0.5 rounded border-slate-300"><span>I reviewed the saved consultation and confirm this disposition.</span></label>
                <button @disabled($missingErrors) class="rounded-lg bg-slate-950 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:bg-slate-400">Confirm &amp; Finalize</button>
            </form>
        </footer>
    </main>
</x-app-layout>
