<x-app-layout>
    <x-slot name="header">Edit Appointment — {{ $schedule->patient->fullName() }}</x-slot>

    <div class="py-6 text-black">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6 text-black">

            <div class="bg-white shadow rounded-lg p-5 text-black">
                <h3 class="font-semibold text-black mb-3 text-black">Dose {{ $schedule->dose_number }} — Reschedule</h3>
                <form method="POST" action="{{ route('schedules.update', $schedule) }}" class="space-y-4">
                    @csrf @method('PUT')
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="scheduled_date" value="Scheduled Date"></x-input-label>
                            <x-text-input id="scheduled_date" type="date" name="scheduled_date" class="block mt-1 w-full" :value="old('scheduled_date', $schedule->scheduled_date->toDateString())" required></x-text-input>
                            <x-input-error :messages="$errors->get('scheduled_date')" class="mt-2"></x-input-error>
                        </div>
                        <div>
                            <x-input-label for="status" value="Status"></x-input-label>
                            <select id="status" name="status" class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                                @foreach (($schedule->status === 'administered' ? ['administered'] : ['scheduled','missed']) as $st)
                                    <option value="{{ $st }}" {{ old('status', $schedule->status) === $st ? 'selected' : '' }}>{{ ucfirst($st) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 text-black">
                        <a href="{{ route('schedules.index') }}" class="px-4 py-2 text-black hover:underline text-black">Cancel</a>
                        <button type="submit" class="bg-indigo-600 text-white px-5 py-2 rounded-md font-semibold hover:bg-indigo-700">Save</button>
                    </div>
                </form>
            </div>

            @if (auth()->user()->role === 'Nurse' && $schedule->status !== 'administered')
                <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-slate-800">
                    Nurses record vaccine doses from their assigned <a class="font-semibold text-blue-800 underline" href="{{ route('nurse.station.show') }}">My Queue</a> after the due dose has been queued. This keeps the patient's ticket, station, administering nurse, batch, and observation record together.
                </div>
            @elseif ($schedule->status !== 'administered')
                <div id="administer" class="bg-white shadow rounded-lg p-5 border-l-4 border-green-500 text-black">
                    <h3 class="font-semibold text-black mb-3 text-black">Administer This Dose (auto-deducts stock)</h3>
                    @php
                        $patient = $schedule->patient;
                        $biteIncident = $patient->biteIncidents->sortByDesc('date_of_bite')->first();
                        $plannedDoseCount = (int) $patient->planned_dose_count;
                        $plannedSchedule = $plannedDoseCount > 0
                            ? collect(array_slice(\App\Models\VaccinationSchedule::DOSE_DAYS, 0, $plannedDoseCount))
                                ->map(fn ($day) => 'Day ' . $day)
                                ->implode(', ')
                            : null;
                    @endphp
                    <div class="mb-4 rounded-md border border-blue-200 bg-blue-50 p-3 text-sm text-black">
                        <p class="font-semibold">Doctor-Ordered Assessment and Vaccination Plan</p>
                        <div class="mt-2 grid grid-cols-1 gap-x-4 gap-y-1 sm:grid-cols-2">
                            <p><span class="font-medium">Exposure Category:</span> {{ $biteIncident?->exposure_category ?: 'Not recorded' }}</p>
                            <p><span class="font-medium">Vaccine Type:</span> {{ $patient->vaccine_type ?: 'Not recorded' }}</p>
                            <p><span class="font-medium">Schedule:</span> {{ $plannedSchedule ?: 'Not recorded' }}</p>
                            <p><span class="font-medium">Number of Doses in Provider-Ordered Schedule:</span> {{ $plannedDoseCount > 0 ? $plannedDoseCount . ' doses' : 'Not recorded' }}</p>
                        </div>
                    </div>
                    @if ($biteIncident)
                        <div class="mb-4 rounded-md border border-blue-200 bg-blue-50 p-3 text-sm text-black">
                            <p class="font-semibold">Exposure details for clinical review</p>
                            <p>Animal: {{ $biteIncident->animal_type }} &middot; Bite date: {{ $biteIncident->date_of_bite->format('M d, Y') }}</p>
                            <p>Animal status: {{ $biteIncident->animal_status ?? 'Unknown' }} &middot; Animal vaccinated: {{ $biteIncident->animal_vaccinated ? 'Yes' : 'No / unknown' }}</p>
                        </div>
                    @endif
                    @if ($batches->isEmpty())
                        <p class="text-sm text-black">No eligible vaccine batches are in stock. Add an unexpired vaccine batch in Inventory first. Immunoglobulin products are not listed as vaccine types.</p>
                    @else
                        <form method="POST" action="{{ route('schedules.administer', $schedule) }}" onsubmit="return confirm('Confirm dose administration? Stock will be deducted from the selected batch.');">
                            @csrf
                            @if ($errors->has('vaccine_type') || $errors->has('inventory_id'))
                                <p class="mb-3 text-sm text-red-700">{{ $errors->first('vaccine_type') ?: $errors->first('inventory_id') }}</p>
                            @endif
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                @if (auth()->user()->role !== 'Nurse')
                                    <div>
                                    <label for="vaccine_type" class="block text-sm font-medium text-black">Vaccine Type</label>
                                    <select id="vaccine_type" name="vaccine_type" required class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                                        <option value="">Select vaccine type</option>
                                        @foreach ($vaccineTypes as $type)
                                            <option value="{{ $type }}" {{ old('vaccine_type', $patient->vaccine_type) === $type ? 'selected' : '' }}>{{ $type }}</option>
                                        @endforeach
                                    </select>
                                    <x-input-error :messages="$errors->get('vaccine_type')" class="mt-2" />
                                    </div>
                                    <div>
                                    <label for="inventory_id" class="block text-sm font-medium text-black">Matching Vaccine Batch</label>
                                    <select id="inventory_id" name="inventory_id" data-old-value="{{ old('inventory_id') }}" required disabled class="block mt-1 w-full border-gray-300 rounded-md shadow-sm disabled:bg-gray-100">
                                        <option value="">Select a vaccine type first</option>
                                        @foreach ($batches as $b)
                                            <option value="{{ $b->id }}" data-vaccine-type="{{ $b->vaccine_name }}" {{ (string) old('inventory_id') === (string) $b->id ? 'selected' : '' }}>
                                                {{ $b->batch_number }} ({{ $b->quantity }} left, expires {{ $b->expiration_date->format('M Y') }})
                                            </option>
                                        @endforeach
                                    </select>
                                    <x-input-error :messages="$errors->get('inventory_id')" class="mt-2" />
                                    </div>
                                @else
                                    <p class="sm:col-span-2 text-sm text-gray-700">The Doctor's saved vaccine plan will be used, and an eligible vaccine batch will be selected automatically.</p>
                                @endif
                            </div>
                            <div class="flex justify-end mt-3 text-black">
                                <button type="submit" class="bg-green-600 text-white px-5 py-2 rounded-md font-semibold hover:bg-green-700">Mark Administered</button>
                            </div>
                        </form>
                    @endif
                </div>
            @else
                <div class="bg-green-50 border border-green-200 rounded-lg p-4 text-black">
                    This dose was administered on {{ $schedule->administered_at?->format('M d, Y h:i A') }}
                    by {{ $schedule->administeredBy?->name ?? 'staff' }} (batch {{ $schedule->inventory?->batch_number }}).
                </div>
            @endif
        </div>
    </div>

    @if (auth()->user()->role !== 'Nurse' && $schedule->status !== 'administered' && $batches->isNotEmpty())
        @push('scripts')
        <script>
            window.addEventListener('DOMContentLoaded', () => {
                const typeField = document.getElementById('vaccine_type');
                const batchField = document.getElementById('inventory_id');
                const batchOptions = Array.from(batchField.options).filter(option => option.dataset.vaccineType);
                const oldBatchId = batchField.dataset.oldValue;

                const filterBatches = (restorePreviousSelection = false) => {
                    const selectedType = typeField.value;
                    let previousBatchIsAvailable = false;

                    batchOptions.forEach(option => {
                        const matchesType = selectedType && option.dataset.vaccineType === selectedType;
                        option.hidden = !matchesType;
                        option.disabled = !matchesType;
                        if (matchesType && option.value === oldBatchId) previousBatchIsAvailable = true;
                    });

                    batchField.disabled = !selectedType;
                    batchField.options[0].textContent = selectedType ? 'Select a vaccine batch' : 'Select a vaccine type first';
                    batchField.value = restorePreviousSelection && previousBatchIsAvailable ? oldBatchId : '';
                };

                filterBatches(true);
                typeField.addEventListener('change', () => filterBatches(false));
            });
        </script>
        @endpush
    @endif
</x-app-layout>

