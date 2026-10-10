<x-app-layout>
    <x-slot name="header">Vaccination Schedule — {{ $patient->fullName() }}</x-slot>

    <div class="py-6 text-black">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4 text-black">

    <a href="{{ route('patients.show', $patient) }}" class="inline-flex items-center gap-1 bg-gray-200 text-black px-4 py-2 rounded-md text-sm font-semibold hover:bg-gray-300 transition-colors border border-gray-300">&larr; Back to patient</a>

            <div class="bg-white shadow rounded-lg p-5 text-black">
                <h3 class="font-semibold text-black mb-3 text-black">PEP Schedule (Day 0 / 3 / 7 / 28)</h3>
                <table class="w-full text-sm">
                    <thead class="text-left text-black">
                        <tr><th class="text-black">Dose</th><th class="text-black">Day</th><th class="text-black">Scheduled</th><th class="text-black">Status</th><th class="text-black">Administered</th><th class="text-black">Actions</th></tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($patient->vaccinationSchedules as $s)
                            <tr class="{{ $s->isOverdue() ? 'bg-red-50' : '' }}">
                                <td class="py-2 text-black">{{ $s->dose_number }}</td>
                                <td class="text-black">Day {{ \App\Models\VaccinationSchedule::DOSE_DAYS[$s->dose_number - 1] ?? '?' }}</td>
                                <td class="text-black">{{ $s->scheduled_date->format('M d, Y') }}</td>
                                <td><x-status-badge :status="$s->status"></x-status-badge></td>
                                <td class="text-black">{{ $s->administered_at?->format('M d, Y') ?? '—' }}</td>
                                <td class="text-black">
                                    <a href="{{ route('schedules.edit', $s) }}" class="text-black hover:underline text-black">Edit</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($patient->biteIncidents->isNotEmpty())
                <div class="bg-white shadow rounded-lg p-5 text-black">
                    <h3 class="font-semibold text-black mb-2 text-black">Bite Incident</h3>
                    @foreach ($patient->biteIncidents as $b)
                        <p class="text-sm text-black">
                            {{ $b->date_of_bite->format('M d, Y') }} &middot; {{ $b->animal_type }} &middot;
                            Category {{ $b->exposure_category }} &middot; Site: {{ $b->bite_site }} &middot;
                            Animal: {{ $b->animal_status }} ({{ $b->animal_vaccinated ? 'vaccinated' : 'not vaccinated' }})
                        </p>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
