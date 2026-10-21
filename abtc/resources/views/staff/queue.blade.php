<x-app-layout>
    <x-slot name="header">Queue</x-slot>
    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 p-5">
            <div><h2 class="font-semibold text-slate-900">Waiting Queue ({{ $waiting->count() }})</h2><p class="mt-1 text-sm text-slate-600">Tickets are ordered using the system’s existing priority and queue rules.</p></div>
            <a href="{{ route('staff.tickets.create') }}" class="rounded-md bg-[#176b9c] px-4 py-2 text-sm font-semibold text-white hover:bg-[#11567f]">Create ticket</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[680px] text-sm">
                <thead class="bg-slate-50 text-left text-slate-600"><tr><th class="px-4 py-3">Position</th><th class="px-4 py-3">Ticket</th><th class="px-4 py-3">Patient</th><th class="px-4 py-3">Visit / stage</th><th class="px-4 py-3">Waiting since</th><th class="px-4 py-3">Ticket</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($waiting as $ticket)
                        <tr>
                            <td class="px-4 py-3">{{ $loop->iteration }}</td><td class="px-4 py-3 font-bold">{{ $ticket->ticket_number }}</td>
                            <td class="px-4 py-3">{{ $ticket->patient->fullName() }}</td>
                            <td class="px-4 py-3">{{ ucfirst($ticket->visit_type) }} · {{ str_replace('_', ' ', ucfirst($ticket->service_stage)) }}@if ($ticket->vaccinationSchedule) · Dose {{ $ticket->vaccinationSchedule->dose_number }}@endif</td>
                            <td class="px-4 py-3">{{ $ticket->created_at->format('h:i A') }}</td>
                            <td class="px-4 py-3"><a class="font-semibold text-[#176b9c] hover:underline" href="{{ route('staff.tickets.print', $ticket) }}">Print</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">No patients are waiting.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-app-layout>
