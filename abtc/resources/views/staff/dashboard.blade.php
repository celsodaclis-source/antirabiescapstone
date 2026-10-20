<x-app-layout>
    <x-slot name="header">Staff Dashboard</x-slot>
    <div class="space-y-6">
        <div class="rounded-xl bg-white p-5 shadow-sm border border-slate-200">
            <h2 class="text-lg font-bold text-slate-900">Welcome, <span class="text-[#176b9c]">{{ Auth::user()->name }}</span></h2>
            <p class="mt-1 text-sm text-slate-600">Patient registration, returning-patient tickets, and today’s waiting queue.</p>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <a href="{{ route('staff.patients.create') }}" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm hover:shadow-md">
                <p class="text-sm font-medium text-slate-600">Patients Registered Today</p><p class="mt-1 text-3xl font-bold">{{ $patientsToday }}</p>
            </a>
            <a href="{{ route('staff.queue.index') }}" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm hover:shadow-md">
                <p class="text-sm font-medium text-slate-600">Waiting Patients</p><p class="mt-1 text-3xl font-bold">{{ $waitingCount }}</p>
            </a>
            <a href="{{ route('staff.queue.index') }}" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm hover:shadow-md">
                <p class="text-sm font-medium text-slate-600">Currently Called or Serving</p><p class="mt-1 text-3xl font-bold">{{ $servingCount }}</p>
            </a>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <a href="{{ route('staff.patients.create') }}" class="rounded-xl bg-[#176b9c] p-5 text-white shadow-sm hover:bg-[#11567f]"><h3 class="font-bold">Register a New Patient</h3><p class="mt-1 text-sm text-white/90">Create a patient record and initial queue ticket.</p></a>
            <a href="{{ route('staff.tickets.create') }}" class="rounded-xl bg-[#142b49] p-5 text-white shadow-sm hover:bg-[#10223a]"><h3 class="font-bold">Create a Returning-Patient Ticket</h3><p class="mt-1 text-sm text-white/90">Queue an eligible due or missed dose for an existing patient.</p></a>
        </div>

        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-3 flex items-center justify-between"><h3 class="font-semibold">Active Tickets</h3><a href="{{ route('staff.queue.index') }}" class="text-sm font-semibold text-[#176b9c] hover:underline">Waiting queue →</a></div>
            <div class="divide-y divide-slate-100">
                @forelse ($recentTickets as $ticket)
                    <div class="flex flex-wrap items-center justify-between gap-2 py-3 text-sm">
                        <span class="font-bold">{{ $ticket->ticket_number }}</span>
                        <span>{{ $ticket->patient->fullName() }}</span>
                        <span class="text-slate-600">{{ ucfirst($ticket->visit_type) }} · {{ str_replace('_', ' ', ucfirst($ticket->service_stage)) }}@if ($ticket->vaccinationSchedule) · Dose {{ $ticket->vaccinationSchedule->dose_number }}@endif</span>
                        <a href="{{ route('staff.tickets.print', $ticket) }}" class="text-[#176b9c] hover:underline">Print ticket</a>
                    </div>
                @empty
                    <p class="py-3 text-sm text-slate-600">There are no active tickets.</p>
                @endforelse
            </div>
        </section>
    </div>
</x-app-layout>
