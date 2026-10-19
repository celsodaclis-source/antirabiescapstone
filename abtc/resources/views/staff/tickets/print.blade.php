<x-app-layout>
    <x-slot name="header">Print Queue Ticket</x-slot>
    <div class="mx-auto max-w-xl space-y-4">
        <article id="print-ticket" class="rounded-xl border-2 border-slate-300 bg-white p-6 text-center shadow-sm">
            <p class="text-xs font-bold uppercase tracking-widest text-[#176b9c]">Mandaluyong City Animal Bite Treatment Center</p>
            <h2 class="mt-2 text-xl font-bold">Queue Ticket</h2>
            <p class="mt-5 text-5xl font-black text-[#142b49]">{{ $ticket->ticket_number }}</p>
            <p class="mt-4 text-lg font-semibold">{{ $ticket->patient->fullName() }}</p>
            <p class="mt-1 text-sm text-slate-600">{{ ucfirst($ticket->visit_type) }} · {{ str_replace('_', ' ', ucfirst($ticket->service_stage)) }}</p>
            @if ($ticket->vaccinationSchedule)
                <p class="mt-1 text-sm text-slate-600">Dose {{ $ticket->vaccinationSchedule->dose_number }} · Due {{ $ticket->vaccinationSchedule->scheduled_date->format('M d, Y') }}</p>
            @endif
            <p class="mt-4 text-sm text-slate-600">Please keep this ticket and wait for your number to be called.</p>
            <p class="mt-3 text-xs text-slate-500">{{ $ticket->created_at->format('M d, Y h:i A') }}</p>
        </article>
        <div class="flex justify-center gap-3 print:hidden">
            <button type="button" onclick="window.print()" class="rounded-md bg-[#176b9c] px-5 py-2 font-semibold text-white hover:bg-[#11567f]">Print ticket</button>
            <a href="{{ route('staff.dashboard') }}" class="rounded-md border border-slate-300 px-5 py-2 font-semibold text-slate-700 hover:bg-slate-50">Done</a>
        </div>
    </div>
    <style media="print">header, nav, footer, .print\:hidden { display: none !important; } body { background: white !important; } #print-ticket { border: 1px solid #334155; box-shadow: none; margin: 20mm auto; }</style>
</x-app-layout>
