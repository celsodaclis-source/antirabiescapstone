<x-app-layout>
    <x-slot name="header">Create Returning-Patient Ticket</x-slot>
    <div class="mx-auto max-w-4xl space-y-5">
        <div class="rounded-xl border border-sky-200 bg-sky-50 p-5 text-sm text-slate-700">
            Select an existing patient’s due or missed dose. The dose stays linked to the ticket and the ticket completes only after an authorized clinician records administration.
        </div>
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-4 font-semibold text-slate-900">Eligible Follow-up Doses ({{ $dueSchedules->count() }})</h2>
            @if ($dueSchedules->isEmpty())
                <p class="text-sm text-slate-600">No due follow-up doses are available to queue.</p>
            @else
                <div class="space-y-3">
                    @foreach ($dueSchedules as $schedule)
                        <form method="POST" action="{{ route('staff.tickets.store') }}" class="flex flex-col gap-3 rounded-lg border border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between">
                            @csrf
                            <input type="hidden" name="schedule_id" value="{{ $schedule->id }}">
                            <div>
                                <p class="font-semibold">{{ $schedule->patient->fullName() }}</p>
                                <p class="text-sm text-slate-600">Dose {{ $schedule->dose_number }} · {{ ucfirst($schedule->status) }} · Due {{ $schedule->scheduled_date->format('M d, Y') }}</p>
                                <p class="text-xs text-slate-500">{{ $schedule->patient->contact_number ?? 'No phone on record' }}</p>
                            </div>
                            <button class="rounded-md bg-[#176b9c] px-4 py-2 text-sm font-semibold text-white hover:bg-[#11567f]">Create ticket</button>
                        </form>
                    @endforeach
                </div>
            @endif
            <x-input-error :messages="$errors->get('schedule_id')" class="mt-3" />
        </section>
    </div>
</x-app-layout>
