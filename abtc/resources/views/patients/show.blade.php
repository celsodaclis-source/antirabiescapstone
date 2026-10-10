<x-app-layout>
    <x-slot name="header">{{ $patient->fullName() }}</x-slot>

    <div class="py-6 text-black">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            {{-- Quick Stats Cards --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 text-center">
                    <p class="text-xs text-black/60">Age</p>
                    <p class="text-xl font-bold text-black">{{ $patient->dob->age }} yrs</p>
                </div>
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 text-center">
                    <p class="text-xs text-black/60">Patient Type</p>
                    <p class="text-xl font-bold">
                        @if ($patient->isPriority())
                            <span class="text-red-600">{{ $patient->patientTypeLabel() }}</span>
                        @else
                            <span class="text-black">Regular</span>
                        @endif
                    </p>
                </div>
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 text-center">
                    <p class="text-xs text-black/60">Contact</p>
                    <p class="text-sm font-bold text-black">{{ $patient->contact_number ?? '—' }}</p>
                </div>
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 text-center">
                    <p class="text-xs text-black/60">Barangay</p>
                    <p class="text-sm font-bold text-black">{{ $patient->barangay }}</p>
                </div>
            </div>

            {{-- Profile & Actions --}}
            <div class="flex flex-col sm:flex-row justify-between items-start gap-4 bg-white rounded-xl border border-slate-200 shadow-sm p-5">
                <div class="flex items-center gap-3">
                    <a href="{{ route('patients.index') }}" class="bg-gray-200 text-black px-4 py-2 rounded-md text-sm font-semibold hover:bg-gray-300 transition-colors border border-gray-300">&larr; Back</a>
                    <div>
                        <p class="font-semibold text-black">{{ $patient->fullName() }}</p>
                        <p class="text-black">{{ $patient->sex }} &middot; Born {{ $patient->dob->format('M d, Y') }}</p>
                        <p class="text-black">{{ $patient->address }}, Brgy. {{ $patient->barangay }}</p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('patients.edit', $patient) }}" class="bg-indigo-600 text-white px-4 py-2 rounded-md text-sm font-semibold hover:bg-indigo-700 transition-colors">Edit</a>
                    <a href="{{ route('schedules.show', $patient) }}" class="bg-blue-600 text-white px-4 py-2 rounded-md text-sm font-semibold hover:bg-blue-700 transition-colors">Schedule</a>
                    <button onclick="window.print()" class="bg-slate-100 text-black px-4 py-2 rounded-md text-sm font-semibold hover:bg-slate-200 transition-colors border border-slate-300">🖨️ Print</button>
                    <form method="POST" action="{{ route('patients.destroy', $patient) }}" onsubmit="return confirm('Are you sure you want to permanently delete {{ $patient->fullName() }}? All related records (tickets, bite incidents, vaccination schedules) will also be removed.');" class="inline">
                        @csrf @method('DELETE')
                        <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded-md text-sm font-semibold hover:bg-red-700 transition-colors">Delete</button>
                    </form>
                </div>
            </div>

            {{-- Next Dose Highlight --}}
            @php $nextDose = $patient->vaccinationSchedules->where('status', 'scheduled')->reject(fn ($s) => $s->scheduled_date->lt(now()->startOfDay()))->sortBy('scheduled_date')->first(); @endphp
            @if ($nextDose)
                <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 flex items-center gap-3">
                    <svg class="w-6 h-6 text-amber-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <div>
                        <p class="font-semibold text-amber-800 text-sm">Next Dose: Dose {{ $nextDose->dose_number }}</p>
                        <p class="text-xs text-amber-700">Scheduled for {{ $nextDose->scheduled_date->format('l, F j, Y') }}</p>
                    </div>
                </div>
            @endif

            {{-- Bite Incidents --}}
            <div class="bg-white shadow rounded-xl border border-slate-200 p-5">
                <h3 class="font-semibold text-black mb-3 flex items-center gap-2">
                    <svg class="w-5 h-5 text-[#176b9c]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    Bite Incident(s)
                </h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-left bg-sky-50">
                            <tr>
                                <th class="px-3 py-2 font-semibold text-black">Date</th>
                                <th class="px-3 py-2 font-semibold text-black">Animal</th>
                                <th class="px-3 py-2 font-semibold text-black">Category</th>
                                <th class="px-3 py-2 font-semibold text-black">Site</th>
                                <th class="px-3 py-2 font-semibold text-black">Status</th>
                                <th class="px-3 py-2 font-semibold text-black">Vaccinated</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @foreach ($patient->biteIncidents as $b)
                                <tr class="hover:bg-sky-50/50">
                                    <td class="px-3 py-2 text-black">{{ $b->date_of_bite->format('M d, Y') }}</td>
                                    <td class="px-3 py-2 text-black">{{ $b->animal_type }}</td>
                                    <td class="px-3 py-2"><x-status-badge :status="$b->exposure_category === 'III' ? 'missed' : ($b->exposure_category === 'II' ? 'called' : 'scheduled')"></x-status-badge> {{ $b->exposure_category }}</td>
                                    <td class="px-3 py-2 text-black">{{ $b->bite_site ?? '—' }}</td>
                                    <td class="px-3 py-2 text-black">{{ $b->animal_status ?? '—' }}</td>
                                    <td class="px-3 py-2 text-black">{{ $b->animal_vaccinated ? 'Yes' : 'No' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Vaccination History --}}
            <div class="bg-white shadow rounded-xl border border-slate-200 p-5">
                <h3 class="font-semibold text-black mb-3 flex items-center gap-2">
                    <svg class="w-5 h-5 text-[#176b9c]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>
                    Vaccination History (PEP Schedule)
                </h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-left bg-sky-50">
                            <tr>
                                <th class="px-3 py-2 font-semibold text-black">Dose</th>
                                <th class="px-3 py-2 font-semibold text-black">Scheduled</th>
                                <th class="px-3 py-2 font-semibold text-black">Status</th>
                                <th class="px-3 py-2 font-semibold text-black">Administered</th>
                                <th class="px-3 py-2 font-semibold text-black">By</th>
                                <th class="px-3 py-2 font-semibold text-black">Batch</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @foreach ($patient->vaccinationSchedules as $s)
                                <tr class="hover:bg-sky-50/50 {{ $nextDose && $s->id === $nextDose->id ? 'bg-amber-50/70 font-medium' : '' }}">
                                    <td class="px-3 py-2 text-black">Dose {{ $s->dose_number }}</td>
                                    <td class="px-3 py-2 text-black">{{ $s->scheduled_date->format('M d, Y') }}</td>
                                    <td class="px-3 py-2"><x-status-badge :status="$s->status" /></td>
                                    <td class="px-3 py-2 text-black">{{ $s->administered_at?->format('M d, Y h:i A') ?? '—' }}</td>
                                    <td class="px-3 py-2 text-black">{{ $s->administeredBy?->name ?? '—' }}</td>
                                    <td class="px-3 py-2 text-black">{{ $s->inventory?->batch_number ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Queue Tickets --}}
            <div class="bg-white shadow rounded-xl border border-slate-200 p-5">
                <h3 class="font-semibold text-black mb-3 flex items-center gap-2">
                    <svg class="w-5 h-5 text-[#176b9c]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    Queue Tickets
                </h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-left bg-sky-50">
                            <tr>
                                <th class="px-3 py-2 font-semibold text-black">Ticket</th>
                                <th class="px-3 py-2 font-semibold text-black">Status</th>
                                <th class="px-3 py-2 font-semibold text-black">Counter</th>
                                <th class="px-3 py-2 font-semibold text-black">Called</th>
                                <th class="px-3 py-2 font-semibold text-black">Completed</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @forelse ($patient->tickets as $t)
                                <tr class="hover:bg-sky-50/50">
                                    <td class="px-3 py-2 text-black font-medium">{{ $t->ticket_number }}</td>
                                    <td class="px-3 py-2"><x-status-badge :status="$t->status" /></td>
                                    <td class="px-3 py-2 text-black">{{ $t->counter_assigned ?? '—' }}</td>
                                    <td class="px-3 py-2 text-black">{{ $t->called_at?->format('h:i A') ?? '—' }}</td>
                                    <td class="px-3 py-2 text-black">{{ $t->completed_at?->format('h:i A') ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-3 py-4 text-center text-black">No tickets found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <style media="print">
        body * { visibility: hidden; }
        .max-w-5xl, .max-w-5xl * { visibility: visible; }
        .max-w-5xl { position: absolute; left: 0; top: 0; width: 100%; }
        .no-print { display: none !important; }
        button { display: none !important; }
        a[href] { text-decoration: underline; color: #000; }
    </style>
    @endpush
</x-app-layout>
