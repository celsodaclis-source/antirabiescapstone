<x-app-layout>
    <x-slot name="header">Record Vaccination Plan</x-slot>

    <div class="py-6 text-black">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-black">
            <form method="POST" action="{{ route('patients.plan.store', $patient) }}" class="bg-white shadow rounded-lg p-6 space-y-6">
                @csrf
                @method('PUT')

                <div class="rounded-lg bg-[#176b9c] p-5">
                    <div class="mb-4 flex items-center gap-2">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        <h3 class="font-semibold text-white">{{ $patient->fullName() }}</h3>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm text-white">
                        <div>
                            <span class="block text-xs uppercase tracking-wide text-white/70">Date of Birth</span>
                            {{ $patient->dob->format('M d, Y') }}
                        </div>
                        <div>
                            <span class="block text-xs uppercase tracking-wide text-white/70">Patient Type</span>
                            {{ $patient->patientTypeLabel() }}
                        </div>
                        <div>
                            <span class="block text-xs uppercase tracking-wide text-white/70">Barangay</span>
                            {{ $patient->barangay }}
                        </div>
                    </div>
                </div>

                <section class="rounded-lg bg-[#176b9c] p-5">
                    <div class="mb-4 flex items-center gap-2">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        <h3 class="font-semibold text-white">Animal Bite Incident</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-white">
                            <thead class="text-left">
                                <tr>
                                    <th class="px-3 py-2 font-semibold">Date of Bite</th>
                                    <th class="px-3 py-2 font-semibold">Animal</th>
                                    <th class="px-3 py-2 font-semibold">Category</th>
                                    <th class="px-3 py-2 font-semibold">Site</th>
                                    <th class="px-3 py-2 font-semibold">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/20">
                                @foreach ($patient->biteIncidents as $b)
                                    <tr>
                                        <td class="px-3 py-2">{{ $b->date_of_bite->format('M d, Y') }}</td>
                                        <td class="px-3 py-2">{{ $b->animal_type }}</td>
                                        <td class="px-3 py-2">{{ $b->exposure_category }}</td>
                                        <td class="px-3 py-2">{{ $b->bite_site ?? '—' }}</td>
                                        <td class="px-3 py-2">{{ $b->animal_status ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="rounded-lg border border-sky-200 bg-sky-50 p-5">
                    <div class="mb-2">
                        <h3 class="font-semibold text-slate-900">Vaccination Plan</h3>
                        <p class="mt-1 text-sm text-slate-700">Review the animal and exposure details, then record the vaccine and dose schedule ordered by the healthcare provider. Animal type alone does not determine the regimen.</p>
                    </div>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="vaccine_type" value="Vaccine Type" />
                            <select id="vaccine_type" name="vaccine_type" class="block mt-1 w-full rounded-lg border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 shadow-sm" required>
                                <option value="">Select stocked vaccine</option>
                                @foreach ($vaccineTypes as $type)
                                    <option value="{{ $type }}" {{ old('vaccine_type') === $type ? 'selected' : '' }}>{{ $type }}</option>
                                @endforeach
                            </select>
                            @if (empty($vaccineTypes))
                                <p class="mt-1 text-sm text-rose-700">No eligible vaccine is in stock. Update Inventory before recording the vaccination plan.</p>
                            @endif
                            <x-input-error :messages="$errors->get('vaccine_type')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="planned_dose_count" value="Number of Doses in Provider-Ordered Schedule" />
                            <select id="planned_dose_count" name="planned_dose_count" class="block mt-1 w-full rounded-lg border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 shadow-sm" required>
                                <option value="">Select the ordered schedule</option>
                                <option value="3" {{ old('planned_dose_count') === '3' ? 'selected' : '' }}>3 doses — Day 0, Day 3, Day 7</option>
                                <option value="4" {{ old('planned_dose_count') === '4' ? 'selected' : '' }}>4 doses — Day 0, Day 3, Day 7, Day 28</option>
                            </select>
                            <x-input-error :messages="$errors->get('planned_dose_count')" class="mt-2" />
                        </div>
                    </div>
                </section>

                <section class="rounded-lg border border-amber-200 bg-amber-50 p-5">
                    <label class="inline-flex items-start gap-3">
                        <input type="checkbox" name="requires_medical_review" value="1" {{ old('requires_medical_review') ? 'checked' : '' }} class="mt-1 rounded border-amber-400 text-amber-700 focus:ring-amber-600">
                        <span><span class="block font-semibold text-slate-900">Route for medical review</span><span class="mt-1 block text-sm text-slate-700">Select when the clinical assessment requires review before routine vaccination. Emergency patient type is routed for review automatically.</span></span>
                    </label>
                    <x-input-error :messages="$errors->get('schedules')" class="mt-2" />
                </section>

                <div class="flex justify-end gap-3">
                    <a href="{{ route('patients.show', $patient) }}" class="px-4 py-2 text-black hover:underline">Cancel</a>
                    <button type="submit" class="bg-[#176b9c] text-white px-6 py-2 rounded-md font-semibold hover:bg-[#11567f] transition-colors">Record Plan</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
