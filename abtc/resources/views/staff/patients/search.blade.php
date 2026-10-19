<x-app-layout>
    <x-slot name="header">Patient Search</x-slot>
    <div class="space-y-5">
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <form method="GET" action="{{ route('staff.patients.search') }}" class="flex flex-col gap-3 sm:flex-row">
                <label for="patient-search" class="sr-only">Search name, contact number, or ticket number</label>
                <input id="patient-search" name="search" value="{{ $term }}" class="w-full rounded-md border-slate-300 text-sm focus:border-[#176b9c] focus:ring-[#176b9c]" placeholder="Name, contact number, or ticket number">
                <button class="rounded-md bg-[#176b9c] px-5 py-2 text-sm font-semibold text-white hover:bg-[#11567f]">Search</button>
                <a href="{{ route('staff.patients.create') }}" class="rounded-md border border-slate-300 px-5 py-2 text-center text-sm font-semibold text-slate-700 hover:bg-slate-50">Register patient</a>
            </form>
            <p class="mt-2 text-xs text-slate-500">Search by name or phone before registering to avoid duplicate patient records.</p>
        </section>

        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-sm">
                    <thead class="bg-slate-50 text-left text-slate-600"><tr><th class="px-4 py-3">Patient</th><th class="px-4 py-3">Date of birth</th><th class="px-4 py-3">Contact</th><th class="px-4 py-3">Latest ticket</th><th class="px-4 py-3">Vaccination status</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($patients as $patient)
                            @php($nextDose = $patient->vaccinationSchedules->first(fn ($dose) => in_array($dose->status, ['scheduled', 'missed'], true)))
                            <tr>
                                <td class="px-4 py-3 font-semibold">{{ $patient->fullName() }}<span class="block text-xs font-normal text-slate-500">{{ $patient->patientTypeLabel() }}</span></td>
                                <td class="px-4 py-3">{{ $patient->dob->format('M d, Y') }}</td>
                                <td class="px-4 py-3">{{ $patient->contact_number ?? '—' }}</td>
                                <td class="px-4 py-3">{{ $patient->latestTicket?->ticket_number ?? '—' }}</td>
                                <td class="px-4 py-3">@if ($nextDose) Dose {{ $nextDose->dose_number }} · {{ ucfirst($nextDose->status) }} · {{ $nextDose->scheduled_date->format('M d, Y') }} @else No pending dose @endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">{{ $term === '' ? 'Enter a name, phone, or ticket number to search patient records.' : 'No matching patient records found.' }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-100 p-4">{{ $patients->links() }}</div>
        </section>
    </div>
</x-app-layout>
