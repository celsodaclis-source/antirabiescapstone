<x-app-layout>
    <x-slot name="header">Edit Patient — {{ $patient->fullName() }}</x-slot>

    <div class="py-6 text-black">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 text-black">
            <form method="POST" action="{{ route('patients.update', $patient) }}" class="bg-white shadow rounded-lg p-6 space-y-4">
                @csrf @method('PUT')

                <div class="flex items-center gap-2 border-b pb-2">
                    <svg class="w-5 h-5 text-[#176b9c]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    <h3 class="font-semibold text-black">Patient Information</h3>
                </div>

                <p class="text-xs text-black">Age: <span class="font-semibold">{{ $patient->dob->age }} years</span> &middot; Patient Type: <span class="font-semibold">{{ $patient->patientTypeLabel() }}</span></p>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <x-input-label for="first_name" value="First Name" />
                        <x-text-input id="first_name" name="first_name" class="block mt-1 w-full" :value="old('first_name', $patient->first_name)" required />
                        <x-input-error :messages="$errors->get('first_name')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="middle_name" value="Middle Name" />
                        <x-text-input id="middle_name" name="middle_name" class="block mt-1 w-full" :value="old('middle_name', $patient->middle_name)" />
                    </div>
                    <div>
                        <x-input-label for="last_name" value="Last Name" />
                        <x-text-input id="last_name" name="last_name" class="block mt-1 w-full" :value="old('last_name', $patient->last_name)" required />
                        <x-input-error :messages="$errors->get('last_name')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="suffix" value="Suffix" />
                        <select id="suffix" name="suffix" class="block mt-1 w-full rounded-md border-slate-300 shadow-sm focus:border-[#1976ba] focus:ring-[#1976ba]">
                            @foreach (\App\Models\Patient::suffixes() as $s)
                                <option value="{{ $s }}" {{ old('suffix', $patient->suffix) === $s ? 'selected' : '' }}>{{ $s ?: 'None' }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('suffix')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="dob" value="Date of Birth" />
                        <x-text-input id="dob" type="date" name="dob" class="block mt-1 w-full" :value="old('dob', $patient->dob->toDateString())" required />
                        <x-input-error :messages="$errors->get('dob')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="sex" value="Sex" />
                        <select id="sex" name="sex" class="block mt-1 w-full rounded-md border-slate-300 shadow-sm focus:border-[#1976ba] focus:ring-[#1976ba]">
                            @foreach (['Male','Female','Other'] as $s)
                                <option value="{{ $s }}" {{ old('sex', $patient->sex) === $s ? 'selected' : '' }}>{{ $s }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="rounded-lg border border-sky-200 bg-sky-50 p-3">
                        <x-input-label for="contact_number" value="Contact Number (optional)" />
                        <x-text-input id="contact_number" type="tel" name="contact_number" class="block mt-1 w-full border-sky-300 bg-white" :value="old('contact_number', $patient->contact_number)" placeholder="09171234567" inputmode="numeric" pattern="[0-9]{11}" minlength="11" maxlength="11" aria-describedby="contact-number-help" title="Enter exactly 11 digits, for example 09171234567" oninput="this.value = this.value.replace(/\D/g, '').slice(0, 11)" />
                        <p id="contact-number-help" class="mt-2 text-xs leading-5 text-slate-600">Enter an 11-digit mobile number, for example <span class="font-semibold">09171234567</span>. Use numbers only; spaces and symbols are removed automatically.</p>
                        <x-input-error :messages="$errors->get('contact_number')" class="mt-2" />
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="address" value="Home Address" />
                        <x-text-input id="address" name="address" class="block mt-1 w-full" :value="old('address', $patient->address)" required />
                    </div>
                    <div>
                        <x-input-label for="barangay" value="Barangay" />
                        <select id="barangay" name="barangay" class="block mt-1 w-full rounded-md border-slate-300 shadow-sm focus:border-[#1976ba] focus:ring-[#1976ba]" required>
                            @foreach (\App\Models\Patient::barangays() as $b)
                                <option value="{{ $b }}" {{ old('barangay', $patient->barangay) === $b ? 'selected' : '' }}>{{ $b }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('barangay')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="patient_type" value="Patient Type" />
                        <select id="patient_type" name="patient_type" class="block mt-1 w-full rounded-md border-slate-300 shadow-sm focus:border-[#1976ba] focus:ring-[#1976ba]">
                            @foreach (\App\Models\Patient::patientTypes() as $type)
                                <option value="{{ $type }}" {{ old('patient_type', $patient->patient_type) === $type ? 'selected' : '' }}>{{ ucfirst($type) }}</option>
                            @endforeach
                        </select>
                        <p class="text-xs text-black mt-1">Changes to patient type will affect queue priority</p>
                        <x-input-error :messages="$errors->get('patient_type')" class="mt-2" />
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-2 text-black">
                    <a href="{{ route('patients.show', $patient) }}" class="px-4 py-2 text-black hover:underline text-black">Cancel</a>
                    <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-md font-semibold hover:bg-indigo-700">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
