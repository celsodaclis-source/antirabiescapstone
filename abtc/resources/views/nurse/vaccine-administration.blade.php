<x-app-layout>
    <x-slot name="header">Vaccine Administration</x-slot>

    <div class="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="mb-5 flex items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Vaccine Administration</h1>
                <p class="mt-1 text-sm text-slate-600">Verify the existing vaccination order and due dose before recording administration.</p>
            </div>
            <a href="{{ route('nurse.station.show') }}" class="text-sm font-medium text-blue-800 underline">Back to My Queue</a>
        </div>

        @if ($errors->any())
            <div role="alert" class="mb-5 rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
                <p class="font-semibold">Administration was not saved.</p>
                <ul class="mt-1 list-inside list-disc">@foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
            </div>
        @endif

        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
            <div class="mb-6 rounded-lg border border-slate-200 bg-slate-50 p-4">
                <p class="font-semibold text-slate-900">Patient #{{ $patient->id }} · Ticket {{ $ticket->ticket_number }}</p>
                <p class="mt-1 text-sm text-slate-700">{{ $patient->fullName() }}</p>
                <p class="mt-1 text-xs text-slate-600">{{ ucfirst($ticket->visit_type) }} patient · {{ $ticket->service_stage === 'follow_up' ? 'Follow-up vaccination' : 'Assessment' }}</p>
                <p class="mt-2 text-xs text-slate-500">Confirm that the saved doctor order matches this scheduled dose before proceeding.</p>
            </div>

            @if ($authorization)
                <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">{{ $authorization }} Vaccine administration is blocked until the order and dose are verified.</div>
            @elseif (! $route)
                <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">The clinic has no approved administration route configured for {{ $patient->vaccine_type }}. Ask an authorized clinician or administrator to verify the protocol before recording a dose.</div>
            @elseif (! $sites)
                <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">The clinic has not configured approved administration sites. Ask an administrator to set the clinic's protocol-approved sites before recording a dose.</div>
            @elseif ($batches->isEmpty())
                <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">No matching, unexpired vaccine batch has stock available. No dose can be recorded until inventory is replenished.</div>
            @else
                <form method="POST" action="{{ route('nurse.station.vaccine.administer', $ticket) }}" class="space-y-5">
                    @csrf
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-slate-700">Vaccine / Treatment</label>
                            <input value="{{ $patient->vaccine_type }}" readonly class="mt-1 w-full rounded-lg border-slate-300 bg-slate-50 text-sm text-slate-700">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700">Authorized administration route</label>
                            <input value="{{ $route }}" readonly class="mt-1 w-full rounded-lg border-slate-300 bg-slate-50 text-sm text-slate-700">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700">Dose number</label>
                            <input value="Dose {{ $schedule->dose_number }} of {{ $patient->planned_dose_count }} · due {{ $schedule->scheduled_date->format('M j, Y') }}" readonly class="mt-1 w-full rounded-lg border-slate-300 bg-slate-50 text-sm text-slate-700">
                        </div>
                        <div>
                            <label for="inventory_id" class="block text-sm font-medium text-slate-700">Batch / Lot Number <span class="text-rose-600">*</span></label>
                            <select id="inventory_id" name="inventory_id" required class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                                <option value="">Select available batch</option>
                                @foreach ($batches as $batch)
                                    <option value="{{ $batch->id }}" @selected((string) old('inventory_id') === (string) $batch->id)>{{ $batch->vaccine_name }} · {{ $batch->batch_number }} · {{ $batch->quantity }} available · expires {{ $batch->expiration_date->format('M j, Y') }}</option>
                                @endforeach
                            </select>
                            @error('inventory_id')<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="administration_date" class="block text-sm font-medium text-slate-700">Administration date <span class="text-rose-600">*</span></label>
                            <input id="administration_date" type="date" name="administration_date" max="{{ today()->toDateString() }}" value="{{ old('administration_date', today()->toDateString()) }}" required class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                            @error('administration_date')<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="administration_site" class="block text-sm font-medium text-slate-700">Administration site <span class="text-rose-600">*</span></label>
                            <select id="administration_site" name="administration_site" required class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                                <option value="">Select site</option>
                                @foreach ($sites as $site)<option value="{{ $site }}" @selected(old('administration_site') === $site)>{{ $site }}</option>@endforeach
                            </select>
                            @error('administration_site')<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700">Administered by</label>
                            <input value="{{ auth()->user()->name }}" readonly class="mt-1 w-full rounded-lg border-slate-300 bg-slate-50 text-sm text-slate-700">
                        </div>
                        <div class="sm:col-span-2">
                            <label for="administration_remarks" class="block text-sm font-medium text-slate-700">Clinical remarks / observations <span class="text-rose-600">*</span></label>
                            <textarea id="administration_remarks" name="administration_remarks" rows="4" maxlength="2000" required class="mt-1 w-full rounded-lg border-slate-300 text-sm" placeholder="Record required post-administration observations">{{ old('administration_remarks') }}</textarea>
                            @error('administration_remarks')<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div class="flex flex-col-reverse justify-end gap-3 border-t border-slate-200 pt-5 sm:flex-row">
                        <a href="{{ route('nurse.station.show') }}" class="rounded-lg border border-slate-300 px-4 py-2.5 text-center text-sm font-semibold text-slate-700">Cancel</a>
                        <button class="rounded-lg bg-slate-950 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-800" onclick="return confirm('Record administration and deduct one unit from this batch?')">Record Administration</button>
                    </div>
                </form>
            @endif
        </section>
    </div>
</x-app-layout>
